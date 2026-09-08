import { test, expect } from '@playwright/test';

// E2E de fumaça — PAINEL DO PONTO (/ponto). Rede mínima da thread 01 do playbook
// prototipo-ui/design-docs/cowork-inbox/ponto/playbook/01-rede-e2e.md.
//
// POR QUE EXISTE: o Ponto é o módulo com obrigação legal (Portaria MTP 671/2021) e tinha
// ZERO spec de browser nesta sha (medido: 15 specs em e2e/, nenhum ponto-*). Sem rede,
// "não mudei o layout" é opinião. O próprio Dashboard/Index.casos.md declara o buraco:
// "Ordem no DOM de verdade exige E2E (Playwright), e não há lane de browser para esta tela".
//
// ESCOPO deliberadamente mínimo — a tela abre autenticada e as âncoras do contrato chegam
// ao DOM. NÃO asserta copy: a copy é lei do contrato `ponto-painel` e quem a confere é o
// contrato-de-tela.mjs; duplicar aqui criaria um segundo dono da mesma régua.
//
// SEM UC-id no título, DE PROPÓSITO: o coletor casos:results (G-7) agrega veredito por
// UC-id lido do <testcase name>. Os UC-PAINEL-01..06 já têm veredito verde pelo
// PontoDashboardContratoTest, que prova copy + ordem; este smoke prova só que a âncora
// existe no DOM renderizado. Citar o UC aqui somaria um "verde" que não corresponde ao
// aceite dele — e, num flake, avermelharia um UC legitimamente verde.
//
// LOCATORS: role (L-24) + `data-contract`, que é a âncora semântica do contrato de tela
// (NÃO é classe CSS — é o mesmo atributo que o gate de contrato confere).
// As duas âncoras vivem sob <Deferred> (Inertia::defer): não estão no primeiro render.
// As web-first assertions do Playwright re-tentam até o timeout — por isso ZERO
// waitForTimeout aqui (espera cega como sincronismo é reprovação).

test('painel do ponto abre autenticado e monta as âncoras do contrato', async ({ page }) => {
  await page.goto('/ponto');

  // Título da página (h1 do PageHeader canon, ADR 0182) — fora do defer.
  await expect(page.getByRole('heading', { level: 1, name: /Dashboard/ })).toBeVisible({ timeout: 15_000 });

  // Seções do contrato `ponto-painel` na ordem declarada
  // (painel-nota-fechamento → painel-kpis → painel-fila-aprovacoes → painel-atividade).
  await expect(page.locator('[data-contract="painel-nota-fechamento"]')).toBeVisible({ timeout: 15_000 });
  await expect(page.locator('[data-contract="painel-kpis"]')).toBeVisible({ timeout: 15_000 });
});
