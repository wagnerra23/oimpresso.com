import { test, expect } from '@playwright/test';

// E2E de Jana/Plataforma — contrato em resources/js/Pages/Jana/Plataforma.casos.md.
//
// `test.fixme` = PENDENTE (não executa, não quebra o CI). Fica pendente de propósito e o
// motivo está declarado, não esquecido: este caso precisa de uma sessão autenticada que
// passe nas DUAS portas do gate (`hasPermissionTo('jana.superadmin')` OU `user_type` em
// superadmin/user_oimpresso — ver Plataforma.charter.md §Gate). Um e2e que logue como
// usuário comum só provaria o 403, que já é do Pest (UC-PLATAF-06), e um que "quase" logue
// passaria verde sem exercer nada.
//
// O que o Pest JÁ cobre e este arquivo NÃO precisa repetir: payload, deferred props,
// cross-business, agregação ausente e o acoplamento ghost↔rota
// (Modules/Jana/Tests/Feature/SuperadminPlataformaContratoTest.php).
//
// O que só o e2e alcança é o UC-PLATAF-00: que a aba **navega** — a regressão real, porque
// enquanto o controller devolvia Blade o `<Link>` do PageHeaderTabs silenciava (click no-op),
// e foi por isso que o ghost `metas` teve de sair do DataController.
//
// Locators RESILIENTES (role/label/text), nunca classe CSS (L-24).
// NÃO edite a tela viva sem charter + gate visual.

test.fixme('UC-PLATAF-00: a aba Plataforma navega (não é click no-op)', async ({ page }) => {
  // Dado: sessão de quem administra a plataforma (ver nota acima sobre o gate).
  await page.goto('/ia');

  // Quando: clico na aba.
  await page.getByRole('link', { name: 'Plataforma' }).click();

  // Então: a URL mudou e a tela é a de plataforma — as duas asserções juntas, porque
  // "a URL mudou" sozinha não distingue navegação de redirect.
  await expect(page).toHaveURL(/\/ia\/superadmin\/metas$/);
  await expect(page.getByText('Metas da plataforma (business_id NULL)')).toBeVisible();
  await expect(page.getByText('Metas de clientes (cross-business)')).toBeVisible();
});
