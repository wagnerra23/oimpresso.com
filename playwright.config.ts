import { defineConfig, devices } from '@playwright/test';

// Harness E2E de comportamento — Gate G-3 da Governança executável (ADR 0264).
// Navegador real (headless no CI). Locators RESILIENTES (getByRole/getByLabel/getByText
// — NUNCA classe CSS, lição L-24 "presença ≠ correção"). Viewport 1280 = monitor da
// Larissa (casos.md UC-09). baseURL e credenciais via env (default dev local).
//
// Primeiro run verde PENDENTE de validação no stack do app (o agente desktop não tem
// PHP/serve). Por isso o workflow e2e-gate.yml entra NÃO-required até verde-estável
// (lição dura ADR 0261: gate flaky required trava todo merge).

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';

export default defineConfig({
  testDir: './e2e',
  testMatch: '**/*.spec.ts',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: process.env.CI ? 1 : undefined,
  // JUnit alimenta o coletor casos:results (Salto #2 / G-7 Status derivado): o UC-id no
  // título do teste vira o <testcase name> que o coletor lê → manifesto por-UC.
  reporter: process.env.CI
    ? [['github'], ['list'], ['junit', { outputFile: 'test-results/playwright-junit.xml' }]]
    : [['list'], ['junit', { outputFile: 'test-results/playwright-junit.xml' }]],
  // Login uma vez → storageState reusado pelos specs (rota é auth-gated).
  globalSetup: './e2e/global-setup.ts',
  use: {
    baseURL,
    storageState: 'e2e/.auth/state.json',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    locale: 'pt-BR',
  },
  projects: [
    {
      name: 'chromium-1280',
      use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 } },
    },
  ],
});
