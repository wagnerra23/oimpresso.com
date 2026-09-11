import { test, expect } from '@playwright/test';

// E2E de fumaça — LISTA DO ESPELHO (/ponto/espelho). Thread 01 do playbook do Ponto.
//
// ESCOPO: a tela abre autenticada e o chassi de seleção está de pé. NÃO asserta copy nem o
// conteúdo da lista — o corpo da tabela vive sob <Deferred> e, no tenant da lane
// (VisregTenantSeeder, biz=1), NÃO há colaborador de ponto seedado: a lista renderiza o
// estado vazio. Assertar linhas aqui seria assertar o seed, não a tela.
//
// ÂNCORA: esta é a única das três telas SEM `data-contract` (medido: 9 ocorrências em
// Pages/Ponto/**, sendo 4 no Dashboard/Index e 5 no Espelho/Show — zero aqui). Por isso o
// locator é o rótulo do seletor de mês, que fica FORA do <Deferred> e é label resiliente
// (L-24), nunca classe CSS.
//
// SEM UC-id no título, DE PROPÓSITO (mesma razão do ponto-dashboard.spec.ts): os
// UC-ESPIDX-01..03 são aceites de SELEÇÃO DE DADOS (quem entra na lista, isolamento entre
// empregadores, mês que viaja), provados pelo EspelhoContratoTest. Este smoke prova só que
// a tela monta — citar o UC somaria um veredito que este teste não sustenta.

test('lista do espelho abre autenticada com o seletor de mês', async ({ page }) => {
  await page.goto('/ponto/espelho');

  // Título da página (h1 do PageHeader canon, ADR 0182) — fora do defer.
  await expect(page.getByRole('heading', { level: 1, name: /Espelho/ })).toBeVisible({ timeout: 15_000 });

  // Seletor de mês de referência: rótulo associado por htmlFor, sempre presente.
  await expect(page.getByLabel(/Mês de referência/)).toBeVisible({ timeout: 15_000 });
});
