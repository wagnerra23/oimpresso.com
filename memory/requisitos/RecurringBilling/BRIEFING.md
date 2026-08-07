---
id: requisitos-recurring-billing-briefing
module: RecurringBilling
status: producao
updated_at: "2026-08-05"
distilled_at: "2026-08-05"
distilled_by: "jana:distill-module-truth (2026-07-23) + redestilações PARCIAIS manuais: agent sdd-from-source (2026-07-28) e fluxo SDD (2026-08-05) — só as seções Governança/Última mudança/Gaps; o resto segue da destilação automática de 23/07"
---

# BRIEFING — RecurringBilling (verdade destilada)

## Estado atual
O módulo de assinaturas recorrentes, `RecurringBilling`, gerencia planos, assinaturas e faturas com suporte a múltiplos gateways de pagamento. Está em produção (assinaturas + invoices live em biz=1). A maturidade de features (Eixo 1, 14 capacidades) é baixa (7% aprovado / 29% parcial / 64% ausente — CAPTERRA-INVENTARIO-v2).

## Capacidades
- **Planos**: CRUD completo para `rb_plans` com configurações de ciclo e trial.
- **Contratos**: Gerenciamento de `rb_subscriptions` em vários estados.
- **Cobrança**: Integração com gateways de pagamento via `BoletoService::driver()`.
- **Cancelamento**: Inter/Asaas via endpoint `/financeiro/rb-invoices/{id}/cancelar` (UI `Faturas/Index.tsx`); C6 exige cancelamento manual no portal (driver stub, US-RB-042 `_parcial_`).
- **Webhooks**: Idempotência via tabela `pg_webhook_events` `(provider, event_id)`.
- **NFe**: Emissão automática de NFe após pagamento via `NfeBrasil`.
- **Sync bancário**: Funcionalidade de sincronização de saldos bancários e extratos.
- **Interface**: Página Inertia completa para gestão de assinaturas.
- **Nova assinatura**: Criação simplificada com interface intuitiva (PR #2369).

## Governança (redestilação parcial 2026-08-05)
- **SDD do domínio:** [`SDD-cobranca-recorrente-v1.0.md`](SDD-cobranca-recorrente-v1.0.md) — §5 com 9
  fluxos críticos, §6 com `CU-RB-01..15`. **Primeiro SDD do repo criado do zero** pelo agent
  `sdd-from-source` ([ADR 0351](../../decisions/0351-sdd-from-source.md)).
- **Trio completo nas 6 telas** — `casos.md` de 0 → 6; **36 UC declarados, 36 com teste que os cita,
  0 órfãos** (recibo: `node scripts/governance/requisitos-status.mjs RecurringBilling`, 2026-07-28).
- **Fontes de paridade ausentes, declaradas:** não há Blade legada (cutover feito na Onda 10 — a view
  era "Hello World") **nem** `ANTI-REGRESSAO-*` Delphi (o WR Comercial não tinha recorrência). O
  contrato anti-regressão deste módulo é o SPEC + os charters + os testes, e nada mais.
- **Mordida (atualizado 2026-08-03 — a redação anterior dizia "falta 1 linha em
  `.github/ci-sqlite-pest.list`"; deixou de ser verdade em [PR #5194](https://github.com/wagnerra23/oimpresso.com/pull/5194)):**
  **9 dos 13** arquivos de teste do módulo entraram na lane sqlite do `ci.yml` — rodam no PR e
  bloqueiam merge. Recibo do run verde de PR `30779531388`: **9 suites · 49 tests · 280 assertions ·
  0 fail · 0 skip**, e **20 UC-ids chegam ao `name` do `<testcase>`** (`UC-RBCFG-01..08` ·
  `UC-RBFAT-01..07` · `-12` · `-13` · `UC-RBSUB-06/07/08`), que é o que o manifesto G-7 consegue
  carimbar. Antes disso, **nenhum** UC do módulo era alcançável: o UC-id vivia só no docblock, e o
  coletor lê o atributo `name`.
- **4 arquivos seguem fora da lane, com motivo medido** (run `30778559754`, quando a lane os rodou
  pela primeira vez — dívida **pré-existente**, nenhum deles jamais tinha rodado em CI):
  `Wave21NewSubscriptionTest` (`UC-RBSUB-01/02/03`, 4 errors) e `Wave23EditarAssinaturaTest`
  (`UC-RBSUB-04`, 1 failure + 2 errors) morrem em `AuthorizationException` — a lane sqlite não semeia
  as permissions Spatie do `FormRequest::authorize()`; `InvoiceGeneratorServiceTest`
  (`UC-RBFAT-08..11`) falha o **contador** de idempotência (ver Gaps); `PlanoSemFaturaContratoTest`
  (`UC-RBSUB-05`) é failing-first por desenho e travaria `main` numa lane required.

## Gaps
- 🔴 **`[V0]` Assinatura com valor negociado nunca gera fatura** — sem plano casado (`ciclo` **E**
  `valor` exatos), `plan_id` fica `null` e o gerador a descarta com uma linha de log, **sem alarme**.
  Contradiz a DoD da US-RB-002 (*"override do plano"*). Teste failing-first: `PlanoSemFaturaContratoTest`
  (`UC-RBSUB-05`). **Correção pendente de decisão [W]** — SDD §9.1.
- 🟡 **`[V0]` O contador `skipped` do gerador não bate com o contrato** _(achado novo 2026-08-03, run
  `30778559754` — apareceu na 1ª vez que o arquivo rodou em CI)_ — `UC-RBFAT-08` promete que a 2ª
  execução de `InvoiceGeneratorService::run()` na mesma competência **conta `skipped`**; medido, veio
  `skipped == 0` (`InvoiceGeneratorServiceTest` linha 226). **Leia com cuidado antes de concluir:** a
  asserção de **não-duplicação PASSOU** (linha 225, `generated == 0`) — **não há indício de cobrança
  dupla**; o que mente é o contador, não a guarda. Ainda assim é `[V0]`: quem lê o retorno do job pra
  saber se algo foi pulado recebe informação errada. Diagnóstico e remédio são **decisão [W]** (REGRA
  MESTRE valor) — não consertado de propósito. Enquanto isso, `UC-RBFAT-08..11` ficam sem lane de PR.
- **Dunning / retry scheduler**: régua de cobrança e reprocessamento automático `_pendente_` (SPEC).
  A régua exibida em `/recurring-billing/configuracoes` é **texto hardcoded**, não motor.
- **PIX Automático e cartão tokenizado**: meios de pagamento recorrente `_pendente_` (SPEC).
- **Proração**: cálculo de proporcional em troca de plano `_pendente_` (SPEC).
- **Uso real:** 5 US wired com **0 hits** na janela de 30d do `route-hits.json` — telas existem,
  roteadas, ninguém abriu.

## Última mudança
**2026-08-05** — `CU-RB-15` tornou explícito no SDD o contrato operacional da US-RB-052 para ativar
gateway em assinaturas dormentes e ligou o trio `gateway-ativacao` à cadeia SDD; sem tela por desenho.
Anterior: **2026-08-03** — os UC do módulo passaram a ser **alcançáveis pelo manifesto G-7**: UC-id no título
dos `it()` (antes só no docblock, o que os deixava fora por construção) + 9 arquivos na lane sqlite
do `ci.yml` ([PR #5194](https://github.com/wagnerra23/oimpresso.com/pull/5194)). De **0 → 20** UC
carimbáveis. Ligar a lane revelou dívida pré-existente em 3 arquivos (nunca tinham rodado em CI),
incluindo o achado `[V0]` do contador `skipped` — ver Gaps.
Anterior: **2026-07-28** — SDD do domínio + trio das 6 telas + achado `[V0]` do §9.1 (chip Onda 5 do passo 5).
Anterior: materialização da US-RB-056 e "dente de cálculo" test-only (PR #3737), 2026-07-03 — janela
da destilação automática. (O backfill de gateway US-RB-052 / PR #4045, de 2026-07-09, é posterior.)

## Proveniência (destilado de)

- audit `requisitos/RecurringBilling/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/RecurringBilling/CAPTERRA-INVENTARIO-v2.md` — CAPTERRA-INVENTARIO-v2.md
- audit `requisitos/RecurringBilling/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- handoff `handoffs/2026-07-03-1215-dente-calculo-recurringbilling.md` (2026-07-03) — 2026-07-03-1215-dente-calculo-recurringbilling.md
- handoff `handoffs/2026-07-03-1245-us-rb-056-materializada.md` (2026-07-03) — 2026-07-03-1245-us-rb-056-materializada.md
