import { test, expect } from '@playwright/test';

// E2E do ESPELHO MENSAL de um colaborador (/ponto/espelho/{colaborador}). Thread 01.
//
// test.fixme = PENDENTE POR FALTA DE SEED, e a razão está MEDIDA — não é preguiça:
//   • EspelhoController@show resolve o colaborador por findOrFail() → sem registro, 404.
//   • O tenant da lane E2E é o do VisregTenantSeeder (biz=1). Varredura nele: ZERO menção a
//     colaborador de ponto (os 2 hits de "ponto" no arquivo são a expressão "ponto cego").
//   • O `db:seed` geral chama Barcodes/Permissions/Currencies/BusinessLegacyOrigin — nenhum
//     seeder do Ponto. DevPontoSeeder e PontoWr2DatabaseSeeder existem no módulo, mas a lane
//     NÃO os invoca.
// Logo esta rota responde 404 na lane hoje, e um teste executável aqui seria vermelho
// permanente — ruído que se aprende a ignorar, não cobertura.
//
// POR QUE NÃO FABRICO O COLABORADOR AQUI: criar fixture de negócio dentro do teste, no
// tenant que ele trata como real, é o anti-padrão já enterrado em memory/proibicoes.md §5
// (2026-08-24). O dono de "o que existe neste ambiente" é o SEED — e o seeder está fora do
// prefixo desta thread, que escreve somente em e2e/ponto-*.spec.ts.
//
// PARA DESTRAVAR: a lane precisa de 1 colaborador com controle de ponto ativo em biz=1.
// É trabalho do dono do seeder; fica registrado em _saida-01.md como descoberta, não como
// pedido embutido no código.

test.fixme('espelho mensal de um colaborador abre com o cabeçalho legal', async ({ page }) => {
  // A rota exige um {colaborador} do próprio business — o id abaixo é placeholder até o
  // seed da lane existir (ver bloco acima).
  await page.goto('/ponto/espelho/1');

  await expect(page.getByRole('heading', { level: 1, name: /Espelho/ })).toBeVisible();
  // 1ª seção do contrato `ponto-espelho`
  // (espelho-dados-colaborador → espelho-totais → espelho-modo-visao → …).
  await expect(page.locator('[data-contract="espelho-dados-colaborador"]')).toBeVisible();
});
