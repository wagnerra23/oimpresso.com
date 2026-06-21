import { chromium, type FullConfig } from '@playwright/test';
import { mkdirSync } from 'node:fs';

// Login uma vez e salva o storageState pra os specs (a tela ProducaoOficina é auth-gated,
// stack UltimatePOS). Dois caminhos (harness, NÃO assert — não afrouxa spec, L-24):
//
//   (A) CI / bypass: E2E_BYPASS_LOGIN_ID setado → rota env-guarded `/_visreg-login/{id}`
//       (Auth::loginUsingId, NUNCA em produção — routes/web.php). Pula os gates de
//       subscription/trial do form UltimatePOS, que falhariam num tenant mínimo seedado.
//       É o caminho ROBUSTO cross-process (mesma rota do visual-regression, US-GOV-013).
//   (B) Local: form de login real com E2E_USER / E2E_PASS (dev biz=1, ADR 0101).
//
// Locators resilientes (label/role) no caminho (B).
export default async function globalSetup(_config: FullConfig) {
  const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';
  const bypassId = process.env.E2E_BYPASS_LOGIN_ID;

  mkdirSync('e2e/.auth', { recursive: true });

  const browser = await chromium.launch();
  const page = await browser.newPage();

  if (bypassId) {
    // (A) bypass env-guarded — loga o user por id no subprocesso do server → cookie de sessão.
    await page.goto(`${baseURL}/_visreg-login/${bypassId}?to=/`);
  } else {
    // (B) form de login real (local).
    const user = process.env.E2E_USER ?? 'admin@oimpresso.test';
    const pass = process.env.E2E_PASS ?? 'password';
    await page.goto(`${baseURL}/login`);
    const userField = page.getByLabel(/usu[aá]rio|username|e-?mail/i).or(page.locator('input[name="username"], input[name="email"]')).first();
    const passField = page.getByLabel(/senha|password/i).or(page.locator('input[type="password"]')).first();
    await userField.fill(user);
    await passField.fill(pass);
    await page.getByRole('button', { name: /entrar|login|sign in/i }).click();
  }

  await page.waitForLoadState('networkidle');
  await page.context().storageState({ path: 'e2e/.auth/state.json' });
  await browser.close();
}
