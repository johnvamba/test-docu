import { ref } from 'vue';

export type ToastKind = 'success' | 'warning' | 'info' | 'critical';

export interface Toast {
  id: number;
  kind: ToastKind;
  title: string;
  message?: string;
}

const toasts = ref<Toast[]>([]);
let nextId = 1;

/** Toasts slide in from the right and auto-dismiss after 4 s. Critical ones stay until closed. */
export function useToasts() {
  function dismiss(id: number): void {
    toasts.value = toasts.value.filter((t) => t.id !== id);
  }

  function push(kind: ToastKind, title: string, message?: string): void {
    const id = nextId++;
    toasts.value = [...toasts.value.slice(-3), { id, kind, title, message }];
    if (kind !== 'critical') window.setTimeout(() => dismiss(id), 4000);
  }

  return {
    toasts,
    dismiss,
    success: (title: string, message?: string) => push('success', title, message),
    info: (title: string, message?: string) => push('info', title, message),
    warning: (title: string, message?: string) => push('warning', title, message),
    error: (title: string, message?: string) => push('critical', title, message),
  };
}
