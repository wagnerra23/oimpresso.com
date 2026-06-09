import { chromium, type FullConfig } from '@playwright/test';
import { mkdirSync } from 'node:fs';

// Login uma vez e salva o storageState pra os specs (a tela ProducaoOficina é auth-gated,
// stack UltimatePOS). Credenciais via env (E2E_USER / E2E_PASS) — no CI vêm do seed de
// biz=1 dev (ADR 0101: biz=1 nunca é cliente real). Locators resilientes (label/role).
export default async function globalSetup(config: FullConfig) {
  const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';
  const user = process.env.E2E_USER ?? 'admin@oimpresso.test';
  const pass = process.env.E2E_PASS ?? 'password';

  mkdirSync('e2e/.auth', { recursive: true });

  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto(`${baseURL}/login`);

  // UltimatePOS login: campos username/password (fallback por type/name).
  const userField = page.getByLabel(/usu[aá]rio|username|e-?mail/i).or(page.locator('input[name="username"], input[name="email"]')).first();
  const passField = page.getByLabel(/senha|password/i).or(page.locator('input[type="password"]')).first();
  await userField.fill(user);
  await passField.fill(pass);
  await page.getByRole('button', { name: /entrar|login|sign in/i }).click();

  await page.waitForLoadState('networkidle');
  await page.context().storageState({ path: 'e2e/.auth/state.json' });
  await browser.close();
}
