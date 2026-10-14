import Echo from 'laravel-echo';
import Pusher, { type ChannelAuthorizationCallback } from 'pusher-js';
import { http } from './http';

declare global {
  interface Window {
    Pusher: typeof Pusher;
  }
}

interface RealtimeConfig {
  enabled: boolean;
  key?: string | null;
  host?: string | null;
  port?: string | number | null;
  scheme?: string | null;
}

/** Read from <meta name="realtime"> (rendered by app.blade.php from config/services.php). */
function readConfig(): RealtimeConfig {
  try {
    return JSON.parse(document.querySelector<HTMLMetaElement>('meta[name="realtime"]')?.content ?? '') as RealtimeConfig;
  } catch {
    return { enabled: false };
  }
}

function createEcho(): Echo<'reverb'> | null {
  const config = readConfig();
  // Realtime is an enhancement: without Reverb configured the app still works, minus live presence.
  if (!config.enabled || !config.key) return null;

  window.Pusher = Pusher;
  const scheme = config.scheme || window.location.protocol.replace(':', '');
  const port = Number(config.port || window.location.port || (scheme === 'https' ? 443 : 80));

  return new Echo<'reverb'>({
    broadcaster: 'reverb',
    key: config.key,
    wsHost: config.host || window.location.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    // Authorize private and presence channels with the Sanctum session cookie
    authorizer: (channel: { name: string }) => ({
      authorize: (socketId: string, callback: ChannelAuthorizationCallback) => {
        http
          .post('/api/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
          .then((response) => callback(null, response.data))
          .catch((error: Error) => callback(error, null));
      },
    }),
  });
}

export const echo = createEcho();

// Lets the server skip the sender when broadcasting with ->toOthers().
http.interceptors.request.use((config) => {
  const socketId = echo?.socketId();
  if (socketId) config.headers.set('X-Socket-ID', socketId);
  return config;
});

export const userChannel = (userId: number) => `App.Models.User.${userId}`;
export const documentChannel = (documentId: number) => `documents.${documentId}`;
