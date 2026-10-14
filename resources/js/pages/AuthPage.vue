<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowRight } from '@lucide/vue';
import BlueprintFrame from '@/components/BlueprintFrame.vue';
import { useAuth } from '@/composables/useAuth';
import { errorMessage, fieldErrors } from '@/lib/http';

const props = defineProps<{ mode: 'login' | 'register' }>();

const { login, register } = useAuth();
const router = useRouter();
const route = useRoute();

const form = reactive({ name: '', email: '', password: '', password_confirmation: '' });
const errors = ref<Record<string, string[]>>({});
const generalError = ref<string | null>(null);
const submitting = ref(false);

const isRegister = computed(() => props.mode === 'register');

/** Seeded demo accounts (see DatabaseSeeder). Password for all: "password". */
const demoAccounts = [
  { name: 'Alice Moreno', email: 'alice@ajaia.test', note: 'Owns 2 docs, shares one with Bob' },
  { name: 'Bob Tan', email: 'bob@ajaia.test', note: 'Owns 1 doc, can edit Alice’s plan' },
  { name: 'Carol Reyes', email: 'carol@ajaia.test', note: 'Starts with nothing shared' },
];

async function submit(): Promise<void> {
  submitting.value = true;
  errors.value = {};
  generalError.value = null;
  try {
    if (isRegister.value) {
      await register({ ...form });
    } else {
      await login({ email: form.email, password: form.password, remember: true });
    }
    const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : '/';
    await router.push(redirect);
  } catch (error) {
    errors.value = fieldErrors(error);
    if (!Object.keys(errors.value).length) generalError.value = errorMessage(error);
  } finally {
    submitting.value = false;
  }
}

function useDemo(email: string): void {
  form.email = email;
  form.password = 'password';
  void submit();
}
</script>

<template>
  <div class="page">
    <header class="page-head" style="max-width: 760px">
      <span class="eyebrow">Ajaia internal tools</span>
      <h1>{{ isRegister ? 'Create your account' : 'Sign in to Docs' }}</h1>
      <p class="lead">Write, import and share working documents with your team. Formatting, owners and access are kept for you.</p>
    </header>

    <section class="section">
      <header>
        <h2 style="font-size: 24px">{{ isRegister ? 'Your details' : 'Account' }}</h2>
        <p class="spec">Session cookie sign-in. No third-party services.</p>
      </header>

      <div class="auth-grid">
        <BlueprintFrame solid>
          <form class="stack" style="gap: var(--space-4)" novalidate @submit.prevent="submit">
            <div v-if="isRegister" class="field">
              <label for="name">Full name</label>
              <input id="name" v-model="form.name" class="input" :class="{ invalid: errors.name }" type="text" autocomplete="name" required />
              <p v-if="errors.name" class="err">{{ errors.name[0] }}</p>
            </div>
            <div class="field">
              <label for="email">Work email</label>
              <input id="email" v-model="form.email" class="input" :class="{ invalid: errors.email }" type="email" autocomplete="email" required />
              <p v-if="errors.email" class="err">{{ errors.email[0] }}</p>
            </div>
            <div class="field">
              <label for="password">Password</label>
              <input
                id="password"
                v-model="form.password"
                class="input"
                :class="{ invalid: errors.password }"
                type="password"
                :autocomplete="isRegister ? 'new-password' : 'current-password'"
                required
              />
              <p v-if="isRegister && !errors.password" class="hint">At least 8 characters.</p>
              <p v-if="errors.password" class="err">{{ errors.password[0] }}</p>
            </div>
            <div v-if="isRegister" class="field">
              <label for="password_confirmation">Confirm password</label>
              <input id="password_confirmation" v-model="form.password_confirmation" class="input" type="password" autocomplete="new-password" required />
            </div>

            <div v-if="generalError" class="alert a-critical" role="alert"><div>{{ generalError }}</div></div>

            <div class="row" style="justify-content: space-between">
              <button type="submit" class="btn btn-primary" :disabled="submitting" style="min-width: 160px">
                <span v-if="submitting" class="spin" style="width: 16px; height: 16px; border-color: rgba(255, 255, 255, 0.4); border-top-color: #fff" />
                {{ submitting ? 'Please wait…' : isRegister ? 'Create account' : 'Sign in' }}
              </button>
              <RouterLink v-if="isRegister" to="/login">I already have an account</RouterLink>
              <RouterLink v-else to="/register">Create an account</RouterLink>
            </div>
          </form>
        </BlueprintFrame>

        <aside v-if="!isRegister" class="stack" style="gap: var(--space-3)" aria-labelledby="demo-title">
          <div>
            <span id="demo-title" class="card-kicker">Reviewer accounts</span>
            <p class="muted" style="font-size: 15px; margin-top: 6px">Seeded teammates for trying the sharing flow. Password: <code>password</code></p>
          </div>
          <ul class="list" style="border-top: var(--rule)">
            <li v-for="account in demoAccounts" :key="account.email">
              <span class="avatar avatar-sm" aria-hidden="true">{{ account.name.slice(0, 1) }}</span>
              <span class="grow">
                <span class="name">{{ account.name }}</span><br />
                <span class="spec">{{ account.note }}</span>
              </span>
              <button class="btn btn-sm" type="button" :disabled="submitting" :aria-label="`Sign in as ${account.name}`" @click="useDemo(account.email)">
                Use <ArrowRight :size="16" :stroke-width="1.5" aria-hidden="true" />
              </button>
            </li>
          </ul>
        </aside>
      </div>
    </section>
  </div>
</template>

<style scoped>
.auth-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: var(--space-7); align-items: start; }
code { font-size: 14px; background: var(--color-neutral-100); padding: 1px 6px; }
@media (max-width: 900px) { .auth-grid { grid-template-columns: 1fr; gap: var(--space-6); } }
</style>
