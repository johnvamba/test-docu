<script setup lang="ts">
import { computed } from 'vue';
import { initials, roleLabel } from '@/lib/format';
import type { PresenceMember } from '@/types/api';

const props = defineProps<{ members: PresenceMember[]; selfId: number }>();

const others = computed(() => props.members.filter((m) => m.id !== props.selfId));
const shown = computed(() => others.value.slice(0, 4));
const extra = computed(() => others.value.length - shown.value.length);
const label = computed(() => (others.value.length ? `Also viewing: ${others.value.map((m) => m.name).join(', ')}` : ''));
</script>

<template>
  <TransitionGroup v-if="others.length" tag="div" name="pop" class="presence" role="group" :aria-label="label">
    <span v-for="m in shown" :key="m.id" class="avatar avatar-sm" :title="`${m.name} · ${roleLabel(m.role)} · viewing now`">
      {{ initials(m.name) }}
      <i class="live" aria-hidden="true" />
    </span>
    <span v-if="extra > 0" key="extra" class="avatar avatar-sm more" :title="`${extra} more viewing`">+{{ extra }}</span>
  </TransitionGroup>
</template>

<style scoped>
.presence { display: inline-flex; gap: 4px; align-items: center; }
.avatar { position: relative; }
.live { position: absolute; right: -3px; bottom: -3px; width: 10px; height: 10px; border-radius: 50%; background: var(--color-success); border: 2px solid var(--color-bg); }
.more { background: var(--color-neutral-200); color: var(--color-text); }
</style>
