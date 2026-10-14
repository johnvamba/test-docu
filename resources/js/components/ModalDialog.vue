<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

/** Backdrop fades, dialog rises 16 px. Escape and backdrop click close it; focus is trapped. */
const props = defineProps<{ open: boolean; labelledby: string; width?: string }>();
const emit = defineEmits<{ close: [] }>();

const panel = ref<HTMLElement | null>(null);
let returnFocus: HTMLElement | null = null;

const focusable = () =>
  Array.from(
    panel.value?.querySelectorAll<HTMLElement>('button:not([disabled]), [href], input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])') ?? [],
  );

function onKey(e: KeyboardEvent): void {
  if (e.key === 'Escape') {
    e.stopPropagation();
    emit('close');
  } else if (e.key === 'Tab') {
    const items = focusable();
    if (!items.length) return;
    const first = items[0];
    const last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }
}

watch(
  () => props.open,
  async (open) => {
    if (open) {
      returnFocus = document.activeElement as HTMLElement | null;
      document.addEventListener('keydown', onKey);
      await nextTick();
      (panel.value?.querySelector<HTMLElement>('[autofocus]') ?? focusable()[0])?.focus();
    } else {
      document.removeEventListener('keydown', onKey);
      returnFocus?.focus?.();
    }
  },
  { immediate: true },
);
onBeforeUnmount(() => document.removeEventListener('keydown', onKey));
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="open" class="dialog-backdrop" @mousedown.self="emit('close')">
        <Transition name="rise" appear>
          <div ref="panel" class="dialog" role="dialog" aria-modal="true" :aria-labelledby="labelledby" :style="width ? { width } : undefined">
            <slot />
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
