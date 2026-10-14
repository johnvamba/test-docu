import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.ts'],
      refresh: true,
    }),
    vue({
      template: { transformAssetUrls: { base: null, includeAbsolute: false } },
    }),
    tailwindcss(),
  ],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) },
  },
  server: {
    host: '0.0.0.0', // listen inside the container
    port: 5173,
    strictPort: true,
    hmr: { host: 'localhost' }, // the URL the browser uses
    watch: { usePolling: true }, // reliable file watching on Docker Desktop / WSL mounts
  },
});
