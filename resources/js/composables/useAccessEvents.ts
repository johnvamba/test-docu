import { shallowRef } from 'vue';
import { echo, userChannel } from '@/lib/echo';
import type { DocumentAccessEvent } from '@/types/api';

/** The latest "your access changed" event for the signed-in user; pages watch it. */
const lastEvent = shallowRef<DocumentAccessEvent | null>(null);
let subscribedTo: number | null = null;

export function useAccessEvents() {
  function subscribe(userId: number, onEvent?: (event: DocumentAccessEvent) => void): void {
    if (!echo || subscribedTo === userId) return;
    unsubscribe();
    subscribedTo = userId;
    echo.private(userChannel(userId)).listen('.document.access', (event: DocumentAccessEvent) => {
      lastEvent.value = event;
      onEvent?.(event);
    });
  }

  function unsubscribe(): void {
    if (echo && subscribedTo !== null) echo.leave(userChannel(subscribedTo));
    subscribedTo = null;
  }

  return { lastEvent, subscribe, unsubscribe };
}
