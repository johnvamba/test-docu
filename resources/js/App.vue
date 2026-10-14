<script setup lang="ts">
import { watch } from 'vue';
import { useRouter } from 'vue-router';
import { LogOut } from '@lucide/vue';
import ToastStack from '@/components/ToastStack.vue';
import { useAuth } from '@/composables/useAuth';
import { useAccessEvents } from '@/composables/useAccessEvents';
import { useToasts } from '@/composables/useToasts';
import { firstName, roleLabel } from '@/lib/format';

const { user, logout } = useAuth();
const router = useRouter();
const toast = useToasts();
const access = useAccessEvents();

// One subscription per signed-in user: share / unshare notifications.
watch(
  () => user.value?.id,
  (id) => {
    if (!id) return access.unsubscribe();
    access.subscribe(id, (event) => {
      const who = firstName(event.changed_by.name);
      if (event.change === 'granted' && event.role) {
        toast.info(`${who} shared “${event.title}”`, `${roleLabel(event.role)}. Find it under Shared with me.`);
      } else if (event.role) {
        toast.info(`${who} changed your access`, `You ${event.role === 'editor' ? 'can now edit' : 'can now only view'} “${event.title}”.`);
      } else {
        toast.warning(`${who} removed your access`, `“${event.title}” is no longer shared with you.`);
      }
    });
  },
  { immediate: true },
);

async function onLogout(): Promise<void> {
  access.unsubscribe();
  await logout();
  await router.push({ name: 'login' });
}
</script>

<template>
  <div class="min-h-screen flex flex-col">
    <div class="page w-full">
      <nav class="nav" aria-label="Main">
        <RouterLink to="/" class="nav-brand" aria-label="Ajaia Docs home">Ajaia <b>Docs</b></RouterLink>
        <template v-if="user">
          <RouterLink to="/" class="link hide-sm" exact-active-class="router-link-active">Documents</RouterLink>
          <span class="row" style="gap: var(--space-2)">
            <span class="avatar avatar-sm" aria-hidden="true">{{ user.name.slice(0, 1).toUpperCase() }}</span>
            <span class="hide-sm" style="font-weight: 500">{{ user.name }}</span>
          </span>
          <button class="btn btn-sm" type="button" @click="onLogout">
            <LogOut :size="16" :stroke-width="1.5" aria-hidden="true" /> Sign out
          </button>
        </template>
      </nav>
    </div>

    <main class="flex-1">
      <RouterView />
    </main>

    <footer class="page w-full">
      <div class="foot row" style="justify-content: space-between">
        <span>Ajaia Docs · internal preview</span>
        <span>Imports: .txt, .md, .docx</span>
      </div>
    </footer>

    <ToastStack />
  </div>
</template>
