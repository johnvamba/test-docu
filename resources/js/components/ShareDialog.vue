<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { UserMinus, X } from '@lucide/vue';
import ModalDialog from './ModalDialog.vue';
import { documentsApi } from '@/composables/useDocumentsApi';
import { useToasts } from '@/composables/useToasts';
import { errorMessage, fieldErrors } from '@/lib/http';
import { initials } from '@/lib/format';
import type { DocumentDetail, ShareRole, User } from '@/types/api';

const props = defineProps<{ open: boolean; doc: DocumentDetail }>();
const emit = defineEmits<{ close: []; updated: [doc: DocumentDetail] }>();
const toast = useToasts();

const email = ref('');
const role = ref<ShareRole>('editor');
const errors = ref<Record<string, string[]>>({});
const submitting = ref(false);
const busyUser = ref<number | null>(null);
const directory = ref<User[]>([]);

const suggestions = computed(() => {
  const taken = new Set([props.doc.owner.id, ...props.doc.collaborators.map((c) => c.id)]);
  return directory.value.filter((u) => !taken.has(u.id));
});

watch(
  () => props.open,
  async (open) => {
    if (!open) return;
    email.value = '';
    errors.value = {};
    try {
      directory.value = await documentsApi.searchUsers('');
    } catch {
      directory.value = []; // suggestions are a convenience; typing an email still works
    }
  },
);

async function share(): Promise<void> {
  if (!email.value.trim()) {
    errors.value = { email: ['Enter a teammate’s email address.'] };
    return;
  }
  submitting.value = true;
  errors.value = {};
  try {
    const updated = await documentsApi.share(props.doc.id, email.value.trim(), role.value);
    const person = updated.collaborators.find((c) => c.email === email.value.trim().toLowerCase());
    toast.success('Shared', `${person?.name ?? email.value} ${role.value === 'editor' ? 'can now edit' : 'can now view'} this document.`);
    email.value = '';
    emit('updated', updated);
  } catch (error) {
    errors.value = fieldErrors(error);
    if (!Object.keys(errors.value).length) errors.value = { email: [errorMessage(error)] };
  } finally {
    submitting.value = false;
  }
}

async function changeRole(userId: number, next: ShareRole): Promise<void> {
  busyUser.value = userId;
  try {
    emit('updated', await documentsApi.changeRole(props.doc.id, userId, next));
  } catch (error) {
    toast.error('Could not change access', errorMessage(error));
  } finally {
    busyUser.value = null;
  }
}

async function remove(user: User): Promise<void> {
  busyUser.value = user.id;
  try {
    emit('updated', await documentsApi.unshare(props.doc.id, user.id));
    toast.info('Access removed', `${user.name} can no longer open this document.`);
  } catch (error) {
    toast.error('Could not remove access', errorMessage(error));
  } finally {
    busyUser.value = null;
  }
}
</script>

<template>
  <ModalDialog :open="open" labelledby="share-title" width="min(560px, 100%)" @close="emit('close')">
    <div class="row" style="justify-content: space-between; flex-wrap: nowrap; align-items: flex-start">
      <div class="stack" style="gap: 4px; min-width: 0">
        <span class="card-kicker">Share</span>
        <h3 id="share-title" class="truncate">{{ doc.title }}</h3>
      </div>
      <button class="btn btn-ghost btn-icon btn-sm" type="button" aria-label="Close" style="color: var(--color-text)" @click="emit('close')"><X :size="18" :stroke-width="1.5" /></button>
    </div>

    <form class="stack" style="gap: var(--space-3)" novalidate @submit.prevent="share">
      <div class="field">
        <label for="share-email">Add people by email</label>
        <input
          id="share-email"
          v-model="email"
          class="input"
          :class="{ invalid: errors.email }"
          type="email"
          list="share-directory"
          placeholder="name@ajaia.test"
          autocomplete="off"
          autofocus
        />
        <datalist id="share-directory">
          <option v-for="u in suggestions" :key="u.id" :value="u.email">{{ u.name }}</option>
        </datalist>
        <p v-if="errors.email" class="err" role="alert">{{ errors.email[0] }}</p>
        <p v-else-if="errors.role" class="err" role="alert">{{ errors.role[0] }}</p>
      </div>
      <div class="row" style="justify-content: space-between">
        <div class="seg" role="group" aria-label="Access level">
          <button type="button" :aria-pressed="role === 'editor'" @click="role = 'editor'">Can edit</button>
          <button type="button" :aria-pressed="role === 'viewer'" @click="role = 'viewer'">Can view</button>
        </div>
        <button class="btn btn-primary" type="submit" :disabled="submitting">
          <span v-if="submitting" class="spin" style="width: 16px; height: 16px; border-color: rgba(255, 255, 255, 0.4); border-top-color: #fff" />
          Share
        </button>
      </div>
    </form>

    <div>
      <p class="label-row">People with access</p>
      <ul class="list" style="border-top: var(--rule)">
        <li>
          <span class="avatar avatar-sm" aria-hidden="true">{{ initials(doc.owner.name) }}</span>
          <span class="grow">
            <span class="name">{{ doc.owner.name }}</span><br />
            <span class="spec">{{ doc.owner.email }}</span>
          </span>
          <span class="tag tag-accent">Owner</span>
        </li>
        <li v-for="c in doc.collaborators" :key="c.id" :aria-busy="busyUser === c.id">
          <span class="avatar avatar-sm" aria-hidden="true" style="background: var(--color-neutral-700)">{{ initials(c.name) }}</span>
          <span class="grow">
            <span class="name">{{ c.name }}</span><br />
            <span class="spec">{{ c.email }}</span>
          </span>
          <div class="seg seg-sm" role="group" :aria-label="`Access for ${c.name}`">
            <button type="button" :aria-pressed="c.role === 'editor'" :disabled="busyUser === c.id" @click="c.role !== 'editor' && changeRole(c.id, 'editor')">Edit</button>
            <button type="button" :aria-pressed="c.role === 'viewer'" :disabled="busyUser === c.id" @click="c.role !== 'viewer' && changeRole(c.id, 'viewer')">View</button>
          </div>
          <button class="btn btn-ghost btn-icon btn-sm" type="button" :aria-label="`Remove ${c.name}`" :disabled="busyUser === c.id" style="color: var(--color-critical)" @click="remove(c)">
            <UserMinus :size="18" :stroke-width="1.5" />
          </button>
        </li>
      </ul>
      <p v-if="!doc.collaborators.length" class="spec" style="padding-top: var(--space-2)">Only you can open this document right now.</p>
    </div>
  </ModalDialog>
</template>

<style scoped>
.label-row { font: 600 12px var(--font-body); letter-spacing: 0.08em; text-transform: uppercase; color: var(--color-text-muted); padding-bottom: var(--space-2); }
.truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
