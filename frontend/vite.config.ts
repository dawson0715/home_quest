import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vitest/config';
import { playwright } from '@vitest/browser-playwright';
import adapter from '@sveltejs/adapter-static';
import { sveltekit } from '@sveltejs/kit/vite';

export default defineConfig({
  server: {
    // API served by the hq_httpd container (docker compose, port 8080)
    proxy: { '/api': 'http://localhost:8080', '/uploads': 'http://localhost:8080' }
  },
  preview: {
    // API served by the hq_httpd container (docker compose, port 8080)
    proxy: { '/api': 'http://localhost:8080', '/uploads': 'http://localhost:8080' }
  },
  plugins: [
    tailwindcss(),
    sveltekit({
      compilerOptions: {
        // Force runes mode for the project, except for libraries. Can be removed in svelte 6.
        runes: ({ filename }) =>
          filename.split(/[/\\]/).includes('node_modules') ? undefined : true
      },
      adapter: adapter({ fallback: 'index.html' }),
      // CSP as <meta> with hashes of SvelteKit's inline boot script (no 'unsafe-inline' scripts)
      csp: {
        mode: 'hash',
        directives: {
          'default-src': ['self'],
          'script-src': ['self'],
          'style-src': ['self', 'unsafe-inline'],
          'img-src': ['self', 'data:', 'blob:'],
          'connect-src': ['self'],
          'font-src': ['self'],
          'manifest-src': ['self'],
          'worker-src': ['self'],
          'object-src': ['none'],
          'base-uri': ['self'],
          'form-action': ['self']
        }
      }
    })
  ],
  test: {
    expect: { requireAssertions: true },
    projects: [
      {
        extends: './vite.config.ts',
        test: {
          name: 'client',
          browser: {
            enabled: true,
            provider: playwright(),
            instances: [{ browser: 'chromium', headless: true }]
          },
          include: ['src/**/*.svelte.{test,spec}.{js,ts}'],
          exclude: ['src/lib/server/**']
        }
      },

      {
        extends: './vite.config.ts',
        test: {
          name: 'server',
          environment: 'node',
          include: ['src/**/*.{test,spec}.{js,ts}'],
          exclude: ['src/**/*.svelte.{test,spec}.{js,ts}']
        }
      }
    ]
  }
});
