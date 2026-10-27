import { defineConfig } from 'vite'

// lightningcss minifier rejects a pseudo-element rule from the default theme
export default defineConfig({ build: { cssMinify: false } })
