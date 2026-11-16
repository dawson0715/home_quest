import { defineConfig } from '@playwright/test';

/**
 * E2E against the real backend (docker compose up + `php yii seed/demo`).
 * HQ_PARENT_TOKEN = access token of demo-anna. Not run in CI (no API there).
 */
export default defineConfig({
  testDir: 'src/e2e-live',
  testMatch: '**/*.e2e.ts',
  webServer: {
    command: 'npm run dev -- --port 5180 --strictPort',
    port: 5180,
    reuseExistingServer: false
  },
  use: { baseURL: 'http://localhost:5180', serviceWorkers: 'block' }
});
