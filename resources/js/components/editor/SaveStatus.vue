<script setup lang="ts">
import { computed } from 'vue';
import type { SaveState } from '@/composables/useDocumentSession';
import { clockTime } from '@/lib/format';

const props = defineProps<{ state: SaveState; lastSavedAt: Date | null; canEdit: boolean; error: string | null }>();
const emit = defineEmits<{ retry: [] }>();

const view = computed(() => {
  if (!props.canEdit) return { cls: 's-neutral', text: 'View only' };
  switch (props.state) {
    case 'saving':
      return { cls: 's-info', text: 'Saving…' };
    case 'dirty':
      return { cls: 's-neutral', text: 'Unsaved changes' };
    case 'error':
      return { cls: 's-critical', text: 'Not saved' };
    case 'conflict':
      return { cls: 's-warning', text: 'Edit conflict' };
    case 'saved':
      return { cls: 's-success', text: props.lastSavedAt ? `Saved ${clockTime(props.lastSavedAt)}` : 'Saved' };
    default:
      return { cls: 's-success', text: 'All changes saved' };
  }
});
</script>

<template>
  <span class="row" style="gap: var(--space-2)">
    <span class="status" :class="view.cls" role="status" aria-live="polite" :title="error ?? undefined">{{ view.text }}</span>
    <button v-if="state === 'error'" class="btn btn-ghost btn-sm" type="button" style="min-height: 28px; padding: 0 6px" @click="emit('retry')">Retry</button>
  </span>
</template>
