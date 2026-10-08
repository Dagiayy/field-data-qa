import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    host: true,
    port: 5173,
    // Docker Desktop on Windows doesn't reliably forward native filesystem
    // change events from a bind-mounted host directory into the Linux
    // container, so chokidar's default watcher never fires and HMR/rebuilds
    // silently stop working. Polling works around that at the cost of a
    // small fixed CPU overhead.
    watch: {
      usePolling: true,
      interval: 300,
    },
  },
})
