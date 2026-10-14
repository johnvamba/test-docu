<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { FilePlus, FileText, Search, Trash2, Users } from '@lucide/vue';
import AccessTag from '@/components/AccessTag.vue';
import BlueprintFrame from '@/components/BlueprintFrame.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import ImportDropzone from '@/components/ImportDropzone.vue';
import { useAccessEvents } from '@/composables/useAccessEvents';
import { useAuth } from '@/composables/useAuth';
import { documentsApi } from '@/composables/useDocumentsApi';
import { useToasts } from '@/composables/useToasts';
import { errorMessage } from '@/lib/http';
import { timeAgo } from '@/lib/format';
import type { DocumentDetail, DocumentFilter, DocumentSummary } from '@/types/api';

const router = useRouter();
const toast = useToasts();
const { user } = useAuth();
const { lastEvent } = useAccessEvents();

const filter = ref<DocumentFilter>('all');
const query = ref('');
const documents = ref<DocumentSummary[]>([]);
const counts = ref<Record<DocumentFilter, number>>({ all: 0, owned: 0, shared: 0 });
const loading = ref(true);
const loadError = ref<string | null>(null);
const creating = ref(false);
const toDelete = ref<DocumentSummary | null>(null);
const deleting = ref(false);
const dropzone = ref<InstanceType<typeof ImportDropzone> | null>(null);

const filters: { value: DocumentFilter; label: string }[] = [
  { value: 'all', label: 'All documents' },
  { value: 'owned', label: 'Owned by me' },
  { value: 'shared', label: 'Shared with me' },
];

const emptyCopy = computed(() => {
  if (query.value) return { title: 'No titles match', body: `Nothing called “${query.value}” in this view.` };
  if (filter.value === 'shared') return { title: 'Nothing shared with you yet', body: 'When a teammate shares a document with you, it appears here.' };
  return { title: 'No documents yet', body: 'Start a blank document, or import a .txt, .md or .docx file.' };
});

let requestId = 0;
async function load(showSpinner = true): Promise<void> {
  const id = ++requestId;
  if (showSpinner) loading.value = true;
  loadError.value = null;
  try {
    const result = await documentsApi.list(filter.value, query.value.trim());
    if (id !== requestId) return; // a newer request superseded this one
    documents.value = result.data;
    counts.value = result.meta.counts;
  } catch (error) {
    if (id === requestId) loadError.value = errorMessage(error, 'Your documents could not be loaded.');
  } finally {
    if (id === requestId) loading.value = false;
  }
}

let searchTimer: number | undefined;
watch(query, () => {
  window.clearTimeout(searchTimer);
  searchTimer = window.setTimeout(() => void load(false), 250);
});
watch(filter, () => void load());
watch(lastEvent, () => void load(false));
onMounted(() => void load());

async function createDocument(): Promise<void> {
  creating.value = true;
  try {
    const doc = await documentsApi.create();
    await router.push({ name: 'document', params: { id: doc.id }, query: { new: '1' } });
  } catch (error) {
    toast.error('Could not create a document', errorMessage(error));
  } finally {
    creating.value = false;
  }
}

function onImported(doc: DocumentDetail): void {
  toast.success('File imported', `“${doc.title}” is ready to edit.`);
  void router.push({ name: 'document', params: { id: doc.id } });
}

async function confirmDelete(): Promise<void> {
  if (!toDelete.value) return;
  deleting.value = true;
  const doc = toDelete.value;
  try {
    await documentsApi.remove(doc.id);
    toast.success('Document deleted', `“${doc.title}” was removed for everyone it was shared with.`);
    toDelete.value = null;
    await load(false);
  } catch (error) {
    toast.error('Could not delete the document', errorMessage(error));
  } finally {
    deleting.value = false;
  }
}

const ownerLabel = (doc: DocumentSummary) => (doc.owner.id === user.value?.id ? 'You' : doc.owner.name);
const open = (doc: DocumentSummary) => router.push({ name: 'document', params: { id: doc.id } });
</script>

<template>
  <div class="page">
    <header class="page-head">
      <span class="eyebrow">Workspace</span>
      <div class="row" style="justify-content: space-between; align-items: flex-end; gap: var(--space-5)">
        <div class="stack" style="gap: var(--space-3)">
          <h1>Documents</h1>
          <p class="lead">Everything you own or that teammates have shared with you, newest first.</p>
        </div>
        <div class="row">
          <button class="btn btn-primary" type="button" :disabled="creating" @click="createDocument">
            <span v-if="creating" class="spin" style="width: 16px; height: 16px; border-color: rgba(255, 255, 255, 0.4); border-top-color: #fff" />
            <FilePlus v-else :size="20" :stroke-width="1.5" aria-hidden="true" />
            New document
          </button>
        </div>
      </div>
    </header>

    <section class="section">
      <header>
        <nav class="filters" aria-label="Filter documents">
          <button
            v-for="f in filters"
            :key="f.value"
            type="button"
            class="filter"
            :aria-pressed="filter === f.value"
            @click="filter = f.value"
          >
            <span>{{ f.label }}</span>
            <span class="count">{{ counts[f.value] }}</span>
          </button>
        </nav>
        <ImportDropzone ref="dropzone" class="drop-col" @imported="onImported" />
      </header>

      <div class="stack list-col" style="gap: var(--space-4)">
        <div class="field" style="max-width: 420px">
          <label for="doc-search" class="sr-only">Search titles</label>
          <div class="search">
            <Search :size="18" :stroke-width="1.5" aria-hidden="true" />
            <input id="doc-search" v-model="query" class="input" type="search" placeholder="Search titles" autocomplete="off" />
          </div>
        </div>

        <div v-if="loadError" class="alert a-critical" role="alert">
          <div><b>Couldn’t load documents</b>{{ loadError }}</div>
          <button class="btn btn-sm" type="button" @click="load()">Try again</button>
        </div>

        <!-- Skeleton while the first page loads -->
        <div v-else-if="loading" aria-busy="true" aria-label="Loading documents">
          <div v-for="n in 4" :key="n" class="sk-row">
            <i class="sk" style="width: 40px; height: 40px" />
            <div style="flex: 1; display: flex; flex-direction: column; gap: 8px">
              <i class="sk" :style="{ height: '14px', width: `${60 - n * 6}%` }" />
              <i class="sk" :style="{ height: '12px', width: `${34 - n * 3}%` }" />
            </div>
          </div>
        </div>

        <BlueprintFrame v-else-if="!documents.length" class="empty">
          <FileText :size="28" :stroke-width="1.5" aria-hidden="true" style="color: var(--color-accent-700)" />
          <h3>{{ emptyCopy.title }}</h3>
          <p class="muted">{{ emptyCopy.body }}</p>
          <div v-if="!query && filter !== 'shared'" class="row" style="justify-content: center">
            <button class="btn btn-primary" type="button" @click="createDocument"><FilePlus :size="20" :stroke-width="1.5" /> New document</button>
            <button class="btn" type="button" @click="dropzone?.choose()">Import a file</button>
          </div>
        </BlueprintFrame>

        <div v-else class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th scope="col">Title</th>
                <th scope="col">Access</th>
                <th scope="col" class="hide-sm">Owner</th>
                <th scope="col" class="hide-sm">Last edited</th>
                <th scope="col"><span class="sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="doc in documents" :key="doc.id" class="doc-row" @click="open(doc)">
                <td class="title-cell">
                  <RouterLink :to="{ name: 'document', params: { id: doc.id } }" class="doc-link" @click.stop>
                    <FileText :size="20" :stroke-width="1.5" aria-hidden="true" />
                    <span>{{ doc.title }}</span>
                  </RouterLink>
                  <p v-if="doc.excerpt" class="excerpt">{{ doc.excerpt }}</p>
                </td>
                <td>
                  <div class="row" style="gap: 6px">
                    <AccessTag :role="doc.role" />
                    <span v-if="doc.role === 'owner' && doc.collaborators_count" class="spec row" style="gap: 4px" :title="`Shared with ${doc.collaborators_count}`">
                      <Users :size="14" :stroke-width="1.5" aria-hidden="true" />{{ doc.collaborators_count }}
                    </span>
                  </div>
                </td>
                <td class="hide-sm">{{ ownerLabel(doc) }}</td>
                <td class="hide-sm">
                  <span :title="new Date(doc.updated_at).toLocaleString()">{{ timeAgo(doc.updated_at) }}</span>
                  <span v-if="doc.last_edited_by" class="spec" style="display: block">by {{ doc.last_edited_by }}</span>
                </td>
                <td style="text-align: right; width: 56px">
                  <button
                    v-if="doc.role === 'owner'"
                    class="btn btn-ghost btn-icon btn-sm"
                    type="button"
                    :aria-label="`Delete ${doc.title}`"
                    style="color: var(--color-text-muted)"
                    @click.stop="toDelete = doc"
                  >
                    <Trash2 :size="18" :stroke-width="1.5" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <ConfirmDialog
      :open="toDelete !== null"
      :title="`Delete “${toDelete?.title ?? ''}”?`"
      confirm-label="Delete document"
      danger
      :busy="deleting"
      @confirm="confirmDelete"
      @cancel="toDelete = null"
    >
      This removes it for you and everyone it is shared with. It can’t be undone.
    </ConfirmDialog>
  </div>
</template>

<style scoped>
.filters { display: flex; flex-direction: column; border-top: var(--rule); }
.filter { display: flex; justify-content: space-between; align-items: center; min-height: 44px; padding: 0 var(--space-3); background: transparent; border: 0; border-bottom: var(--hair); border-left: 4px solid transparent; font: 500 15px var(--font-body); color: var(--color-text); text-align: left; }
.filter:hover { background: var(--color-accent-100); }
.filter[aria-pressed='true'] { border-left-color: var(--color-accent-700); font-weight: 600; background: var(--color-surface); }
.filter .count { font: 600 13px var(--font-body); color: var(--color-text-muted); }
.search { position: relative; }
.search svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--color-text-muted); pointer-events: none; }
.search .input { padding-left: 40px; }
.sk-row { display: flex; gap: 12px; align-items: center; padding: 12px 0; border-bottom: var(--hair); }
.empty { display: flex; flex-direction: column; align-items: center; text-align: center; gap: var(--space-3); padding: var(--space-7) var(--space-5); }
.table-wrap { overflow-x: auto; border-top: 0; }
.doc-row { cursor: pointer; }
.title-cell { max-width: 0; width: 46%; }
.doc-link { display: inline-flex; align-items: center; gap: var(--space-2); font-weight: 600; color: var(--color-text); text-decoration: none; max-width: 100%; }
.doc-link span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.doc-link svg { color: var(--color-accent-700); }
.doc-link:hover span { text-decoration: underline; text-underline-offset: 3px; }
.excerpt { font-size: 13px; color: var(--color-text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; padding-left: 28px; }
.drop-col { margin-top: var(--space-5); }
@media (max-width: 800px) {
  .title-cell { width: auto; max-width: none; min-width: 150px; }
  .doc-link span { white-space: normal; }
  .excerpt { display: none; }
  /* Small screens: filters, then the list, then the import panel. */
  .section > header { display: contents; }
  .filters { order: 1; }
  .list-col { order: 2; }
  .drop-col { order: 3; margin-top: var(--space-4); }
}
</style>
