<script setup lang="ts">
import ModalDialog from './ModalDialog.vue';

withDefaults(
  defineProps<{ open: boolean; title: string; confirmLabel: string; danger?: boolean; busy?: boolean }>(),
  { danger: false, busy: false },
);
const emit = defineEmits<{ confirm: []; cancel: [] }>();
</script>

<template>
  <ModalDialog :open="open" labelledby="confirm-title" @close="emit('cancel')">
    <h3 id="confirm-title">{{ title }}</h3>
    <p class="muted"><slot /></p>
    <div class="dialog-actions">
      <button class="btn" :class="danger ? 'btn-critical' : 'btn-primary'" type="button" :disabled="busy" @click="emit('confirm')">
        <span v-if="busy" class="spin" style="width: 16px; height: 16px; border-color: rgba(255, 255, 255, 0.4); border-top-color: #fff" />
        {{ confirmLabel }}
      </button>
      <button class="btn" type="button" autofocus @click="emit('cancel')">Cancel</button>
    </div>
  </ModalDialog>
</template>
