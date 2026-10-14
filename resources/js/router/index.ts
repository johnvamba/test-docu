import { createRouter, createWebHistory } from 'vue-router';
import { useAuth } from '@/composables/useAuth';
import { setPageTitle } from '@/lib/app';

declare module 'vue-router' {
  interface RouteMeta {
    auth?: boolean;
    guest?: boolean;
    title?: string;
  }
}

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/pages/AuthPage.vue'),
      props: { mode: 'login' },
      meta: { guest: true, title: 'Sign in' },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/pages/AuthPage.vue'),
      props: { mode: 'register' },
      meta: { guest: true, title: 'Create account' },
    },
    {
      path: '/',
      name: 'documents',
      component: () => import('@/pages/DocumentsPage.vue'),
      meta: { auth: true, title: 'Documents' },
    },
    {
      path: '/documents/:id(\\d+)',
      name: 'document',
      component: () => import('@/pages/EditorPage.vue'),
      props: (route) => ({ id: Number(route.params.id) }),
      meta: { auth: true },
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
});

router.beforeEach(async (to) => {
  const auth = useAuth();
  if (!auth.loaded.value) await auth.fetchUser();

  if (to.meta.auth && !auth.isAuthenticated.value) {
    return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} };
  }
  if (to.meta.guest && auth.isAuthenticated.value) return { name: 'documents' };
});

router.afterEach((to) => {
  if (to.meta.title) setPageTitle(to.meta.title);
});
