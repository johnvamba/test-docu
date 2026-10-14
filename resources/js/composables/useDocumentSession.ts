import { computed, onBeforeUnmount, ref, shallowRef } from 'vue';
import { documentsApi } from '@/composables/useDocumentsApi';
import { errorMessage, isStatus } from '@/lib/http';
import type { DocumentDetail, DocumentPatch, SaveConflict } from '@/types/api';

export type SaveState = 'idle' | 'dirty' | 'saving' | 'saved' | 'error' | 'conflict';

const CONTENT_DEBOUNCE_MS = 800;
const TITLE_DEBOUNCE_MS = 500;

/**
 * Owns one open document: loading, debounced autosave, and conflict handling.
 *
 * Saves are serialized (never two in flight) and always carry the version the
 * client last saw. A 409 stops autosave and surfaces the conflict so the user
 * chooses: load the other person's version, or overwrite it with theirs.
 */
export function useDocumentSession(id: number) {
  const doc = shallowRef<DocumentDetail | null>(null);
  const loadError = ref<string | null>(null);
  const loadStatus = ref<number | null>(null);
  const state = ref<SaveState>('idle');
  const saveError = ref<string | null>(null);
  const lastSavedAt = ref<Date | null>(null);
  const conflict = shallowRef<SaveConflict | null>(null);

  let version = 0;
  let pending: Omit<DocumentPatch, 'version'> = {};
  let timer: number | undefined;
  let inFlight: Promise<void> | null = null;

  const hasUnsaved = computed(() => ['dirty', 'saving', 'error', 'conflict'].includes(state.value));

  async function load(): Promise<DocumentDetail | null> {
    loadError.value = null;
    try {
      const fresh = await documentsApi.get(id);
      apply(fresh);
      state.value = 'idle';
      return fresh;
    } catch (error) {
      loadStatus.value = (error as { response?: { status?: number } }).response?.status ?? null;
      loadError.value = errorMessage(error, 'This document could not be loaded.');
      return null;
    }
  }

  function apply(fresh: DocumentDetail): void {
    doc.value = fresh;
    version = fresh.version;
  }

  function queue(patch: Omit<DocumentPatch, 'version'>, delay: number): void {
    if (state.value === 'conflict') {
      pending = { ...pending, ...patch }; // keep collecting, but don't send until resolved
      return;
    }
    pending = { ...pending, ...patch };
    state.value = 'dirty';
    window.clearTimeout(timer);
    timer = window.setTimeout(() => void save(), delay);
  }

  const setContent = (content: string) => {
    // No-op edits (e.g. the editor normalising markup on load) must not create a new version.
    if (pending.content === undefined && content === doc.value?.content) return;
    queue({ content }, CONTENT_DEBOUNCE_MS);
  };
  const setTitle = (title: string) => queue({ title }, TITLE_DEBOUNCE_MS);

  async function save(): Promise<void> {
    window.clearTimeout(timer);
    if (inFlight) {
      await inFlight;
      if (Object.keys(pending).length && state.value !== 'conflict') return save();
      return;
    }
    if (!Object.keys(pending).length || state.value === 'conflict') return;

    const sending = pending;
    pending = {};
    state.value = 'saving';
    saveError.value = null;

    inFlight = (async () => {
      try {
        const saved = await documentsApi.update(id, { version, ...sending });
        apply(saved);
        lastSavedAt.value = new Date();
        state.value = Object.keys(pending).length ? 'dirty' : 'saved';
      } catch (error) {
        pending = { ...sending, ...pending }; // newer keystrokes win over what we tried to send
        if (isStatus(error, 409)) {
          conflict.value = (error.response?.data as SaveConflict) ?? null;
          state.value = 'conflict';
        } else {
          saveError.value = errorMessage(error, 'Your changes could not be saved.');
          state.value = 'error';
        }
      } finally {
        inFlight = null;
      }
    })();

    await inFlight;
    if ((state.value as SaveState) === 'dirty') timer = window.setTimeout(() => void save(), CONTENT_DEBOUNCE_MS);
  }

  /** Discard local edits and adopt the latest saved version. Returns it so the editor can reset. */
  function acceptTheirs(): DocumentDetail | null {
    const latest = conflict.value?.document ?? null;
    if (latest) apply(latest);
    pending = {};
    conflict.value = null;
    state.value = 'idle';
    return latest;
  }

  /** Re-send local edits on top of the latest version, replacing the other person's changes. */
  async function keepMine(current: { title: string; content: string }): Promise<void> {
    const latest = conflict.value?.document;
    if (latest) version = latest.version;
    conflict.value = null;
    pending = { title: current.title, content: current.content };
    state.value = 'dirty';
    await save();
  }

  /** A teammate saved. If we have nothing unsaved, pull their version in quietly. */
  async function onRemoteSave(remoteVersion: number): Promise<'reloaded' | 'diverged' | 'ignored'> {
    if (remoteVersion <= version) return 'ignored';
    if (hasUnsaved.value || inFlight) return 'diverged';
    const fresh = await load();
    return fresh ? 'reloaded' : 'ignored';
  }

  function warnBeforeUnload(event: BeforeUnloadEvent): void {
    if (hasUnsaved.value) {
      event.preventDefault();
      void save();
    }
  }
  window.addEventListener('beforeunload', warnBeforeUnload);
  onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', warnBeforeUnload);
    if (Object.keys(pending).length && state.value !== 'conflict') void save();
    window.clearTimeout(timer);
  });

  return {
    doc,
    state,
    saveError,
    lastSavedAt,
    conflict,
    loadError,
    loadStatus,
    hasUnsaved,
    load,
    apply,
    setContent,
    setTitle,
    save,
    acceptTheirs,
    keepMine,
    onRemoteSave,
  };
}
