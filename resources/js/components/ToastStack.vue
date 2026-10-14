<script setup lang="ts">
import { CircleCheck, Info, TriangleAlert, OctagonAlert, X } from '@lucide/vue';
import { useToasts, type ToastKind } from '@/composables/useToasts';

const { toasts, dismiss } = useToasts();

const icons = { success: CircleCheck, info: Info, warning: TriangleAlert, critical: OctagonAlert } as const;
const tone = (kind: ToastKind) => `var(--color-${kind})`;
const toneBg = (kind: ToastKind) => `var(--color-${kind}-bg)`;
</script>

<template>
  <div class="toasts" role="region" aria-label="Notifications" aria-live="polite">
    <TransitionGroup name="toast">
      <div
        v-for="t in toasts"
        :key="t.id"
        class="toast"
        :role="t.kind === 'critical' ? 'alert' : 'status'"
        :style="{ background: toneBg(t.kind), color: tone(t.kind), borderColor: tone(t.kind) }"
      >
        <component :is="icons[t.kind]" :size="20" :stroke-width="1.5" aria-hidden="true" />
        <div class="toast-body">
          <b>{{ t.title }}</b>
          <span v-if="t.message">{{ t.message }}</span>
        </div>
        <button class="btn btn-ghost btn-icon btn-sm" type="button" aria-label="Dismiss" style="color: var(--color-text)" @click="dismiss(t.id)">
          <X :size="16" :stroke-width="1.5" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toasts { position: fixed; right: 24px; bottom: 24px; z-index: 60; display: flex; flex-direction: column; gap: 8px; width: 360px; max-width: calc(100vw - 32px); }
.toast { display: flex; gap: 12px; align-items: flex-start; padding: 12px 8px 12px 16px; border: 1px solid; border-left-width: 4px; box-shadow: var(--shadow-lg); font-size: 15px; }
.toast-body { flex: 1; color: var(--color-text); display: flex; flex-direction: column; gap: 2px; padding-top: 1px; }
.toast-body b { font-family: var(--font-heading); font-size: 16px; }
@media (max-width: 800px) { .toasts { right: 16px; bottom: 16px; } }
</style>
