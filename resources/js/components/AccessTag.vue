<script setup lang="ts">
import type { DocumentRole } from '@/types/api';

defineProps<{ role: DocumentRole }>();
</script>

<template>
  <span v-if="role === 'owner'" class="tag tag-accent">Owner</span>
  <span v-else class="tag">Shared · {{ role === 'editor' ? 'Editor' : 'Viewer' }}</span>
</template>
