import path from 'path'
import { fileURLToPath } from 'url'
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

const redirectToBasePath = () => ({
  name: 'redirect-dataweaver-base',
  configureServer(server) {
    server.middlewares.use((req, res, next) => {
      const url = req.url || '/'
      if (url === '/' || url === '/dataweaver') {
        res.statusCode = 302
        res.setHeader('Location', '/dataweaver/')
        res.end()
        return
      }
      next()
    })
  },
  configurePreviewServer(server) {
    server.middlewares.use((req, res, next) => {
      const url = req.url || '/'
      if (url === '/' || url === '/dataweaver') {
        res.statusCode = 302
        res.setHeader('Location', '/dataweaver/')
        res.end()
        return
      }
      next()
    })
  },
})

export default defineConfig({
  base: '/dataweaver/',
  plugins: [react(), redirectToBasePath()],
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
  build: {
    minify: false,
  },
  server: {
    host: '127.0.0.1',
    proxy: {
      '/dataweaver/api': {
        target: 'http://localhost:8888',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/dataweaver/, '')
      }
    }
  },
  preview: {
    host: '127.0.0.1',
  }
})
