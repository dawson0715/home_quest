import { defineConfig } from '@playwright/test';

export default defineConfig({
  webServer: { command: 'npm run build && npm run preview', port: 4173 },
  testMatch: '**/*.e2e.{ts,js}',
  // the service worker's own fetches would bypass the page.route() API mocks
  use: { serviceWorkers: 'block' }
});
