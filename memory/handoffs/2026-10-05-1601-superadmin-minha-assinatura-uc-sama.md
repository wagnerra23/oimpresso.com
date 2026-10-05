---
date: "2026-10-05"
time: "16:01 BRT"
slug: superadmin-minha-assinatura-uc-sama
tldr: "Superadmin Minha assinatura: #8705 semeia system.app_currency_id no CI e conserta o harness do UC-SAMA-04 (main verde, run 37351365699); #8712 lápide §5 do skip que escondia o caso (LC-13). Errata: UC-SAMA-03/05 foram consertados pelo #8700."
prs: [8705, 8712]
---

# Handoff — Superadmin "Minha assinatura": UC-SAMA verdes

## O que foi feito

- **[#8705](https://github.com/wagnerra23/oimpresso.com/pull/8705)** (mergeado por auto-merge, 17:49Z):
  - semeia `system.app_currency_id` em `.github/actions/pest-mysql-setup`;
  - conserta o harness do UC-SAMA-04, que agora liga a sessão antes de chamar `moedaComoBlade()`.
  - Sem código de produção, sem valor exibido.
- **[#8712](https://github.com/wagnerra23/oimpresso.com/pull/8712)** (mergeado por [W], 18:03Z): lápide §5 *"O skip por fixture ausente escondia um defeito do PRÓPRIO teste"* + `rec` na LC-13.

## Fatos medidos

- **Causa da falta da linha:** o baseline `database/schema/mysql-schema.sql` registra a migration `2018_07_17_182021_add_rows_to_system_table` como rodada, mas o dump é só de estrutura. Por isso `system.app_currency_id` não existe no CI.
- **Produção tem a linha:** `{"id":4,"key":"app_currency_id","value":"18"}`, lida via SSH em 2026-10-05.
- **Quem destravou o UC-SAMA-04:** UC-SAMA-03/05 foram consertados pelo [#8700](https://github.com/wagnerra23/oimpresso.com/pull/8700), que semeia a linha no `beforeEach`. Isso destravou o UC-SAMA-04, que pulava, e deixou o `main` vermelho (run 37347865648, `Session store not set on request.`).
- **Main verde de novo:** run 37351365699 no merge do #8705 deu 255 passed, 9 skipped, 0 failed e 1447 assertions; UC-SAMA-01..05 aparecem com ✓ no log.

## Erro meu, registrado

- Primeiro reportei que o meu seed tinha consertado UC-SAMA-03/05. Era falso: foi o #8700.
- O `ciclo-adversary` pegou o erro antes de a lápide virar canon.
- Pus errata no corpo do #8705.

## Pendente

- O seed do `beforeEach` do teste ficou redundante no CI. É inofensivo (o `afterEach` desfaz a linha) e serve fora do CI. Não removi.
- Outras chaves da mesma migration também faltam no CI, como `invoice_business_*` e `superadmin_version`. Não semeei sem sinal de teste que precise delas.

## Estado MCP no momento do fechamento

- `cycles-active`: nenhum cycle ativo em COPI.
- `my-work`: sem tasks ativas.
