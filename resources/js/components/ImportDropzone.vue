<script setup lang="ts">
import { ref } from 'vue';
import { Upload } from '@lucide/vue';
import BlueprintFrame from './BlueprintFrame.vue';
import { checkImportFile, documentsApi, SUPPORTED_IMPORTS, SUPPORTED_IMPORTS_LABEL } from '@/composables/useDocumentsApi';
import { errorMessage, fieldErrors } from '@/lib/http';
import type { DocumentDetail } from '@/types/api';

const emit = defineEmits<{ imported: [doc: DocumentDetail] }>();

const input = ref<HTMLInputElement | null>(null);
const dragging = ref(false);
const progress = ref<number | null>(null);
const error = ref<string | null>(null);
const fileName = ref<string | null>(null);

defineExpose({ choose: () => input.value?.click() });

async function upload(file: File | undefined): Promise<void> {
  if (!file || progress.value !== null) return;
  error.value = checkImportFile(file);
  fileName.value = file.name;
  if (error.value) return;

  progress.value = 0;
  try {
    const doc = await documentsApi.importFile(file, (p) => (progress.value = p));
    emit('imported', doc);
  } catch (e) {
    error.value = fieldErrors(e).file?.[0] ?? errorMessage(e, 'That file could not be imported.');
  } finally {
    progress.value = null;
    if (input.value) input.value.value = '';
  }
}

function onDrop(e: DragEvent): void {
  dragging.value = false;
  void upload(e.dataTransfer?.files?.[0]);
}
</script>

<template>
  <BlueprintFrame solid class="drop" :class="{ 'dropzone-active': dragging }" @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="onDrop">
    <span class="card-kicker">Import a file</span>
    <p class="card-title" style="font-size: 19px; margin-top: 6px">Turn a file into a document</p>

    <label class="ph drop-target" :aria-busy="progress !== null">
      <input ref="input" class="sr-only" type="file" :accept="SUPPORTED_IMPORTS" :disabled="progress !== null" @change="upload(($event.target as HTMLInputElement).files?.[0])" />
      <Upload :size="20" :stroke-width="1.5" aria-hidden="true" />
      <span v-if="progress === null"><u>Choose a file</u> or drop it here</span>
      <span v-else>Importing {{ fileName }}…</span>
    </label>

    <div v-if="progress !== null" class="bar" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100" aria-label="Upload progress">
      <div :style="{ width: `${Math.max(progress, 8)}%` }" />
    </div>
    <p v-if="error" class="err" role="alert">{{ error }}</p>
    <p class="spec">{{ SUPPORTED_IMPORTS_LABEL }}. Headings, bold, italic, underline and lists are kept.</p>
  </BlueprintFrame>
</template>

<style scoped>
.drop { display: flex; flex-direction: column; gap: var(--space-3); transition: background 160ms linear; }
.drop-target { min-height: 112px; cursor: pointer; display: flex; flex-direction: column; gap: 6px; text-align: center; padding: var(--space-3); font-size: 14px; }
.drop-target:focus-within { outline: 2px solid var(--color-accent); outline-offset: 2px; }
.bar { height: 6px; background: var(--color-accent-100); }
.bar div { height: 100%; background: var(--color-accent-700); transition: width 200ms var(--ease-out); }
.err { font-size: 13px; color: var(--color-critical); font-weight: 600; }
</style>
