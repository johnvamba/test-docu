import { onBeforeUnmount, ref } from 'vue';
import { documentChannel, echo } from '@/lib/echo';
import type { DocumentSavedEvent, PresenceMember } from '@/types/api';

/**
 * Who else has this document open, plus a hook for "someone saved".
 * Degrades to an empty list when realtime isn't configured or reachable.
 */
export function usePresence(documentId: number, onSaved: (event: DocumentSavedEvent) => void) {
  const members = ref<PresenceMember[]>([]);
  const connected = ref(false);

  if (echo) {
    echo
      .join(documentChannel(documentId))
      .here((list: PresenceMember[]) => {
        members.value = list;
        connected.value = true;
      })
      .joining((member: PresenceMember) => {
        members.value = [...members.value.filter((m) => m.id !== member.id), member];
      })
      .leaving((member: PresenceMember) => {
        members.value = members.value.filter((m) => m.id !== member.id);
      })
      .listen('.document.saved', onSaved)
      .error(() => {
        connected.value = false;
      });
  }

  onBeforeUnmount(() => echo?.leave(documentChannel(documentId)));

  return { members, connected, enabled: echo !== null };
}
