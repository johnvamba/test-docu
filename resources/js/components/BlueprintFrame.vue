<script setup lang="ts">
/** Crosshair-cornered frame from the design system, for key objects. */
withDefaults(defineProps<{ tag?: string; solid?: boolean }>(), { tag: 'div', solid: false });
</script>

<template>
  <component :is="tag" class="bp" :class="{ solid }">
    <i class="c tl" aria-hidden="true" /><i class="c tr" aria-hidden="true" />
    <i class="c bl" aria-hidden="true" /><i class="c br" aria-hidden="true" />
    <slot />
  </component>
</template>
