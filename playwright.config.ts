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

// Site PÚBLICO da Uniafibro (Route::domain('uniafibro.com.br') em routes/web.php). Sem login.
// Default: o mesmo `php artisan serve` do CI, alcançado pelo NOME do domínio — o Chromium
// resolve uniafibro.com.br para 127.0.0.1 (--host-resolver-rules), então o Laravel casa a rota
// de domínio. Efeito colateral desejado: com o mapa ativo, um redirect para
// https://uniafibro.com.br/ cai em 127.0.0.1:443 e falha, em vez de chegar ao site real.
// O job noturno aponta UNIAFIBRO_BASE_URL para https://uniafibro.com.br e aí NÃO há mapa
// (só specs de leitura rodam lá — ver e2e/publico/README.md).
const publicoBaseURL = process.env.UNIAFIBRO_BASE_URL ?? 'http://uniafibro.com.br:8000';
const publicoLocal = !process.env.UNIAFIBRO_BASE_URL;
const publicoLaunch = publicoLocal
  ? { args: ['--host-resolver-rules=MAP uniafibro.com.br 127.0.0.1, MAP www.uniafibro.com.br 127.0.0.1'] }
  : {};
// Páginas públicas não herdam a sessão logada do ERP.
const semSessao = { cookies: [], origins: [] };

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
    ? [['github'], ['list'], ['junit', { outputFile: 'test-results/playwright-junit.xml' }], ['json', { outputFile: 'test-results/playwright.json' }]]
    : [['list'], ['junit', { outputFile: 'test-results/playwright-junit.xml' }], ['json', { outputFile: 'test-results/playwright.json' }]],
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
      testIgnore: ['publico/**', 'portal-associada-limite/**'],
      use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 } },
    },
    {
      name: 'publico-desktop',
      testMatch: 'publico/**/*.spec.ts',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1280, height: 800 },
        baseURL: publicoBaseURL,
        storageState: semSessao,
        launchOptions: publicoLaunch,
      },
    },
    {
      // iPhone 12 (390x844, touch, mobile) no Chromium: o CI só instala chromium.
      name: 'publico-mobile',
      testMatch: 'publico/**/*.spec.ts',
      use: {
        ...devices['iPhone 12'],
        browserName: 'chromium',
        baseURL: publicoBaseURL,
        storageState: semSessao,
        launchOptions: publicoLaunch,
      },
    },
    {
      // Rajada que estoura o `throttle:60,1` da área da associada. Para visitante o Laravel conta por
      // domínio + IP, então ela bloquearia por um minuto os outros testes que saem do mesmo runner:
      // só começa depois que todos os outros projetos terminaram.
      name: 'portal-associada-limite',
      testMatch: 'portal-associada-limite/**/*.spec.ts',
      dependencies: ['chromium-1280', 'publico-desktop', 'publico-mobile'],
      use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 }, storageState: semSessao },
    },
  ],
});
