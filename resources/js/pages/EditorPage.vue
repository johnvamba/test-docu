<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import { Placeholder } from '@tiptap/extensions';
import { ArrowLeft, Download, Ellipsis, FileUp, Share2, Trash2 } from '@lucide/vue';
import AccessTag from '@/components/AccessTag.vue';
import BlueprintFrame from '@/components/BlueprintFrame.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import ShareDialog from '@/components/ShareDialog.vue';
import EditorToolbar from '@/components/editor/EditorToolbar.vue';
import PresenceStack from '@/components/editor/PresenceStack.vue';
import SaveStatus from '@/components/editor/SaveStatus.vue';
import { useAccessEvents } from '@/composables/useAccessEvents';
import { useAuth } from '@/composables/useAuth';
import { useDocumentSession } from '@/composables/useDocumentSession';
import { checkImportFile, documentsApi, SUPPORTED_IMPORTS } from '@/composables/useDocumentsApi';
import { usePresence } from '@/composables/usePresence';
import { useToasts } from '@/composables/useToasts';
import { setPageTitle } from '@/lib/app';
import { exportHtml, exportMarkdown } from '@/lib/exporters';
import { errorMessage, fieldErrors } from '@/lib/http';
import { firstName, timeAgo } from '@/lib/format';
import type { DocumentDetail, DocumentSavedEvent } from '@/types/api';

const props = defineProps<{ id: number }>();

const route = useRoute();
const router = useRouter();
const toast = useToasts();
const { user } = useAuth();
const { lastEvent } = useAccessEvents();
const session = useDocumentSession(props.id);
const { doc, state } = session;

const titleDraft = ref('');
const titleInput = ref<HTMLInputElement | null>(null);
const titleError = ref<string | null>(null);
const shareOpen = ref(false);
const menuOpen = ref(false);
const confirmDelete = ref(false);
const deleting = ref(false);
const importing = ref(false);
const importInput = ref<HTMLInputElement | null>(null);
const accessRevokedBy = ref<string | null>(null);
const remoteNotice = ref<string | null>(null);

const canEdit = computed(() => !!doc.value?.permissions.edit && !accessRevokedBy.value);

const editor = useEditor({
  extensions: [
    StarterKit.configure({
      heading: { levels: [1, 2, 3] },
      link: { openOnClick: false, autolink: true, defaultProtocol: 'https' },
    }),
    Placeholder.configure({ placeholder: 'Start writing…' }),
  ],
  editable: false,
  editorProps: {
    attributes: { class: 'doc-prose', 'aria-label': 'Document body', role: 'textbox', 'aria-multiline': 'true' },
  },
  onUpdate: ({ editor: e }) => {
    if (canEdit.value) session.setContent(e.getHTML());
  },
});

function hydrate(fresh: DocumentDetail): void {
  titleDraft.value = fresh.title;
  editor.value?.commands.setContent(fresh.content || '', { emitUpdate: false });
  editor.value?.setEditable(canEdit.value, false);
}

onMounted(async () => {
  const fresh = await session.load();
  if (!fresh) return;
  setPageTitle(fresh.title);
  await nextTick();
  hydrate(fresh);
  await nextTick(); // let the title input render the loaded title before selecting it
  if (route.query.new && canEdit.value) {
    titleInput.value?.focus();
    titleInput.value?.select();
    void router.replace({ query: {} });
  }
});

// The editor instance can arrive after the document; hydrate whichever comes last.
watch(editor, (e) => {
  if (e && doc.value) hydrate(doc.value);
});
watch(canEdit, (editable) => editor.value?.setEditable(editable, false));

/* ---------- Title ---------- */
function onTitleInput(): void {
  const value = titleDraft.value.replace(/\s+/g, ' ').trim();
  titleError.value = value ? (value.length > 200 ? 'Titles can be up to 200 characters.' : null) : 'Give the document a title.';
  if (!titleError.value) session.setTitle(value);
}
function onTitleBlur(): void {
  if (!titleDraft.value.trim() && doc.value) {
    titleDraft.value = doc.value.title;
    titleError.value = null;
  }
}
watch(
  () => doc.value?.title,
  (t) => t && setPageTitle(t),
);

/* ---------- Conflicts and teammates ---------- */
function useTheirs(): void {
  const latest = session.acceptTheirs();
  if (latest) hydrate(latest);
  toast.info('Loaded the latest version', 'Your unsaved edits were discarded.');
}

async function keepMine(): Promise<void> {
  await session.keepMine({ title: titleDraft.value.trim() || 'Untitled document', content: editor.value?.getHTML() ?? '' });
}

async function onRemoteSaved(event: DocumentSavedEvent): Promise<void> {
  const result = await session.onRemoteSave(event.version);
  const who = firstName(event.saved_by.name);
  if (result === 'reloaded' && doc.value) {
    const selection = editor.value?.state.selection;
    hydrate(doc.value);
    if (selection && editor.value) {
      const max = editor.value.state.doc.content.size;
      editor.value.commands.setTextSelection({ from: Math.min(selection.from, max), to: Math.min(selection.to, max) });
    }
    remoteNotice.value = `${who} updated this document ${timeAgo(event.saved_at)}.`;
  } else if (result === 'diverged') {
    remoteNotice.value = `${who} just saved changes. Your next save will ask which version to keep.`;
  }
}

const presence = usePresence(props.id, (event) => void onRemoteSaved(event));

watch(lastEvent, async (event) => {
  if (!event || event.document_id !== props.id) return;
  if (event.role === null) {
    accessRevokedBy.value = event.changed_by.name;
  } else {
    const fresh = await session.load();
    if (fresh) editor.value?.setEditable(canEdit.value, false);
  }
});

/* ---------- Menu actions ---------- */
function closeMenuOnOutside(e: MouseEvent): void {
  if (!(e.target as HTMLElement).closest('.menu-anchor')) menuOpen.value = false;
}
document.addEventListener('mousedown', closeMenuOnOutside);
onBeforeUnmount(() => document.removeEventListener('mousedown', closeMenuOnOutside));

async function importInto(file: File | undefined): Promise<void> {
  menuOpen.value = false;
  if (!file || !editor.value) return;
  const problem = checkImportFile(file);
  if (problem) {
    toast.error('Could not import that file', problem);
    return;
  }
  importing.value = true;
  try {
    const { html, source_filename } = await documentsApi.convertFile(props.id, file);
    editor.value.chain().focus().insertContent(html).run(); // triggers onUpdate → versioned save
    toast.success('Content imported', `Inserted ${source_filename} at the cursor.`);
  } catch (error) {
    toast.error('Could not import that file', fieldErrors(error).file?.[0] ?? errorMessage(error));
  } finally {
    importing.value = false;
    if (importInput.value) importInput.value.value = '';
  }
}

function download(kind: 'md' | 'html'): void {
  menuOpen.value = false;
  const html = editor.value?.getHTML() ?? doc.value?.content ?? '';
  const title = titleDraft.value.trim() || 'Untitled document';
  if (kind === 'md') exportMarkdown(title, html);
  else exportHtml(title, html);
}

async function deleteDocument(): Promise<void> {
  deleting.value = true;
  try {
    await documentsApi.remove(props.id);
    toast.success('Document deleted', `“${doc.value?.title}” was removed.`);
    await router.push({ name: 'documents' });
  } catch (error) {
    toast.error('Could not delete the document', errorMessage(error));
  } finally {
    deleting.value = false;
    confirmDelete.value = false;
  }
}

function onShared(updated: DocumentDetail): void {
  session.apply({ ...updated, content: doc.value?.content ?? updated.content });
}

/* Ctrl/Cmd+S saves now instead of opening the browser's save dialog. */
function onKeydown(e: KeyboardEvent): void {
  if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') {
    e.preventDefault();
    void session.save();
  }
}
document.addEventListener('keydown', onKeydown);
onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
  editor.value?.destroy();
});
</script>

<template>
  <div class="page">
    <!-- Load failure -->
    <div v-if="session.loadError.value" style="padding: var(--space-8) 0">
      <BlueprintFrame class="stack" style="max-width: 560px; margin: 0 auto; gap: var(--space-3)">
        <span class="card-kicker">{{ session.loadStatus.value === 403 ? 'No access' : 'Unavailable' }}</span>
        <h2 style="font-size: 28px">{{ session.loadStatus.value === 403 ? 'This document isn’t shared with you' : 'This document can’t be opened' }}</h2>
        <p class="muted">
          {{ session.loadStatus.value === 403 ? 'Ask the owner to share it with your account.' : session.loadError.value }}
        </p>
        <div class="row">
          <RouterLink to="/" class="btn btn-primary"><ArrowLeft :size="18" :stroke-width="1.5" /> All documents</RouterLink>
          <button v-if="session.loadStatus.value !== 403" class="btn" type="button" @click="session.load().then((d) => d && hydrate(d))">Try again</button>
        </div>
      </BlueprintFrame>
    </div>

    <template v-else>
      <header class="doc-head">
        <RouterLink to="/" class="back"><ArrowLeft :size="16" :stroke-width="1.5" aria-hidden="true" /> All documents</RouterLink>

        <div v-if="!doc" class="stack" style="gap: 10px; padding: var(--space-3) 0" aria-busy="true" aria-label="Loading document">
          <i class="sk" style="height: 36px; width: 46%" />
          <i class="sk" style="height: 14px; width: 24%" />
        </div>

        <template v-else>
          <div class="title-row">
            <div class="field" style="flex: 1; min-width: 0; gap: 2px">
              <label for="doc-title" class="sr-only">Document title</label>
              <input
                id="doc-title"
                ref="titleInput"
                v-model="titleDraft"
                class="title-input"
                :readonly="!canEdit"
                maxlength="200"
                autocomplete="off"
                :aria-invalid="!!titleError"
                @input="onTitleInput"
                @blur="onTitleBlur"
                @keydown.enter.prevent="editor?.commands.focus('start')"
              />
              <p v-if="titleError" class="err">{{ titleError }}</p>
            </div>

            <div class="row actions">
              <PresenceStack v-if="user" :members="presence.members.value" :self-id="user.id" />
              <button v-if="doc.permissions.share" class="btn btn-primary" type="button" @click="shareOpen = true">
                <Share2 :size="18" :stroke-width="1.5" aria-hidden="true" /> Share
              </button>
              <div class="menu-anchor" style="position: relative">
                <button class="btn btn-icon" type="button" aria-label="More actions" :aria-expanded="menuOpen" aria-haspopup="menu" @click="menuOpen = !menuOpen">
                  <Ellipsis :size="20" :stroke-width="1.5" />
                </button>
                <Transition name="pop">
                  <div v-if="menuOpen" class="menu" role="menu">
                    <button v-if="canEdit" class="btn btn-ghost" role="menuitem" type="button" :disabled="importing" @click="importInput?.click()">
                      <FileUp :size="18" :stroke-width="1.5" /> Import file at cursor…
                    </button>
                    <button class="btn btn-ghost" role="menuitem" type="button" @click="download('md')"><Download :size="18" :stroke-width="1.5" /> Download as Markdown</button>
                    <button class="btn btn-ghost" role="menuitem" type="button" @click="download('html')"><Download :size="18" :stroke-width="1.5" /> Download as HTML</button>
                    <template v-if="doc.permissions.delete">
                      <hr />
                      <button class="btn btn-ghost" role="menuitem" type="button" style="color: var(--color-critical)" @click="(menuOpen = false), (confirmDelete = true)">
                        <Trash2 :size="18" :stroke-width="1.5" /> Delete document
                      </button>
                    </template>
                  </div>
                </Transition>
              </div>
              <input ref="importInput" class="sr-only" type="file" :accept="SUPPORTED_IMPORTS" tabindex="-1" aria-hidden="true" @change="importInto(($event.target as HTMLInputElement).files?.[0])" />
            </div>
          </div>

          <div class="meta row">
            <AccessTag :role="doc.role" />
            <span v-if="doc.role !== 'owner'" class="spec">Owned by {{ doc.owner.name }}</span>
            <span v-else-if="doc.collaborators.length" class="spec">Shared with {{ doc.collaborators.map((c) => firstName(c.name)).join(', ') }}</span>
            <span class="sep" aria-hidden="true" />
            <SaveStatus :state="state" :last-saved-at="session.lastSavedAt.value" :can-edit="canEdit" :error="session.saveError.value" @retry="session.save()" />
            <span v-if="importing" class="status s-info">Importing…</span>
            <span v-if="doc.source_filename" class="spec hide-sm">Imported from {{ doc.source_filename }}</span>
          </div>
        </template>
      </header>

      <div v-if="doc" class="stack" style="gap: var(--space-4)">
        <!-- Notices -->
        <div v-if="accessRevokedBy" class="alert a-critical" role="alert">
          <div><b>Your access was removed</b>{{ accessRevokedBy }} stopped sharing this document. You can still read what’s on screen, but changes won’t be saved.</div>
          <RouterLink to="/" class="btn btn-sm">All documents</RouterLink>
        </div>

        <Transition name="collapse">
          <div v-if="state === 'conflict' && session.conflict.value" class="alert a-warning" role="alert">
            <div>
              <b>Someone else saved a newer version</b>
              {{ session.conflict.value.document.last_edited_by ?? 'A teammate' }} saved while you were editing. Choose which version to keep; nothing has been overwritten yet.
              <div class="row" style="margin-top: var(--space-3)">
                <button class="btn btn-sm btn-primary" type="button" @click="useTheirs">Load their version</button>
                <button class="btn btn-sm" type="button" @click="keepMine">Keep mine and overwrite</button>
              </div>
            </div>
          </div>
        </Transition>

        <Transition name="collapse">
          <div v-if="remoteNotice && state !== 'conflict'" class="alert a-info" role="status">
            <div>{{ remoteNotice }}</div>
            <button class="btn btn-ghost btn-sm" type="button" style="color: var(--color-text)" @click="remoteNotice = null">Dismiss</button>
          </div>
        </Transition>

        <div v-if="!doc.permissions.edit && !accessRevokedBy" class="alert a-info">
          <div><b>View only</b>You can read and download this document. Ask {{ doc.owner.name }} for edit access to make changes.</div>
        </div>

        <EditorToolbar v-if="canEdit" :editor="editor" :disabled="state === 'conflict'">
          <span class="spec hide-sm" style="margin-left: auto; padding-right: 6px">Autosaves as you type · Ctrl+S to save now</span>
        </EditorToolbar>

        <div class="doc-paper">
          <i class="c tl" aria-hidden="true" /><i class="c tr" aria-hidden="true" /><i class="c bl" aria-hidden="true" /><i class="c br" aria-hidden="true" />
          <EditorContent :editor="editor" />
        </div>
      </div>
    </template>

    <ShareDialog v-if="doc" :open="shareOpen" :doc="doc" @close="shareOpen = false" @updated="onShared" />
    <ConfirmDialog
      :open="confirmDelete"
      :title="`Delete “${doc?.title ?? ''}”?`"
      confirm-label="Delete document"
      danger
      :busy="deleting"
      @confirm="deleteDocument"
      @cancel="confirmDelete = false"
    >
      This removes it for you and everyone it is shared with. It can’t be undone.
    </ConfirmDialog>
  </div>
</template>

<style scoped>
.doc-head { padding: var(--space-5) 0 var(--space-5); display: flex; flex-direction: column; gap: var(--space-3); border-bottom: var(--rule); margin-bottom: var(--space-5); }
.back { display: inline-flex; align-items: center; gap: 6px; font: 600 13px var(--font-body); letter-spacing: 0.04em; text-transform: uppercase; color: var(--color-accent-700); text-decoration: none; width: max-content; }
.back:hover { text-decoration: underline; text-underline-offset: 3px; }
.title-row { display: flex; gap: var(--space-4); align-items: flex-start; flex-wrap: wrap; }
.actions { flex-wrap: nowrap; padding-top: 2px; }
.meta { gap: var(--space-3); }
.sep { width: 1px; height: 16px; background: var(--color-hairline); }
.err { font-size: 13px; color: var(--color-critical); font-weight: 600; }
.doc-paper > .c { position: absolute; width: 11px; height: 11px; pointer-events: none; }
.doc-paper > .c::before, .doc-paper > .c::after { content: ''; position: absolute; background: var(--color-text); }
.doc-paper > .c::before { left: 5px; top: 0; width: 1px; height: 11px; }
.doc-paper > .c::after { top: 5px; left: 0; height: 1px; width: 11px; }
.doc-paper > .c.tl { left: -6px; top: -6px; }
.doc-paper > .c.tr { right: -6px; top: -6px; }
.doc-paper > .c.bl { left: -6px; bottom: -6px; }
.doc-paper > .c.br { right: -6px; bottom: -6px; }
@media (max-width: 800px) { .actions { width: 100%; justify-content: flex-end; } }
</style>
