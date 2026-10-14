import { computed, ref } from 'vue';
import { csrf, http } from '@/lib/http';
import type { ApiResource, Credentials, Registration, User } from '@/types/api';

// Module-level state: shared by every component that calls useAuth()
const user = ref<User | null>(null);
const loaded = ref(false);

export function useAuth() {
  const isAuthenticated = computed(() => user.value !== null);

  async function fetchUser(): Promise<void> {
    try {
      const { data } = await http.get<ApiResource<User>>('/api/user');
      user.value = data.data;
    } catch {
      user.value = null;
    } finally {
      loaded.value = true;
    }
  }

  async function login(credentials: Credentials): Promise<void> {
    await csrf();
    const { data } = await http.post<ApiResource<User>>('/api/login', credentials);
    user.value = data.data;
  }

  async function register(payload: Registration): Promise<void> {
    await csrf();
    const { data } = await http.post<ApiResource<User>>('/api/register', payload);
    user.value = data.data;
  }

  async function logout(): Promise<void> {
    try {
      await http.post('/api/logout');
    } finally {
      user.value = null;
    }
  }

  return { user, loaded, isAuthenticated, fetchUser, login, register, logout };
}
