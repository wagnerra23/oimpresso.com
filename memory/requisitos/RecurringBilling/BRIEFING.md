---
id: requisitos-recurring-billing-briefing
module: RecurringBilling
status: producao
updated_at: "2026-09-06"
distilled_at: "2026-09-06"
distilled_by: jana:distill-module-truth
---

# BRIEFING — RecurringBilling (verdade destilada)

## Estado atual
Assinaturas e faturas recorrentes com múltiplos gateways, em produção (uso medido pelo ledger `governance/route-hits.json` via `anchor-lint --json`). Maturidade de features: eixo 1 do `CAPTERRA-INVENTARIO-v2.md` (snapshot datado, não porta viva — não copie o número). O contrato anti-regressão deste módulo é o SPEC + o trio charter/casos/teste — não há Blade legada nem `ANTI-REGRESSAO-*` Delphi como fonte de paridade (o WR Comercial não tinha recorrência). Governança: `SDD-cobranca-recorrente-v1.0.md` (SDD do domínio criado do zero pelo `sdd-from-source`, ADR 0351, #4914 — a ordem de criação entre os SDDs é derivada: `gh api repos/wagnerra23/oimpresso.com/commits?path=<sdd>`; §5 fluxos, §6 `CU-RB-01..15`) + trio charter/casos/teste nas telas (cobertura de UC: `node scripts/governance/requisitos-status.mjs RecurringBilling`, painel derivado em `_STATUS-GENERATED.md`; a fila de CU sem UC é derivada — sai da mesma porta, não se copia aqui); a view Blade era "Hello World" e o cutover da Onda 10 deixou `/recurringbilling` → `/recurring-billing` em 301 (SDD §Fontes). Sinal de uso: US wired sem hits na janela de 30 dias do ledger `governance/route-hits.json` — veredito advisory `nao_servido` do `anchor-lint --json` (chave = Page da US, não rota). Dono: SDD §9.4.

## Capacidades
- Planos: CRUD de `rb_plans` com ciclo e trial.
- Contratos: estados de `rb_subscriptions`.
- Cobrança via `BoletoService` com driver por gateway (Inter, Asaas, C6).
- Cancelamento: `POST /financeiro/rb-invoices/{invoice}/cancelar` (UI `Faturas/Index.tsx`) — Inter e Asaas por API; C6 exige cancelamento manual no portal (driver lança `BadMethodCallException`, US-RB-042 `_parcial_`).
- Webhooks idempotentes por `(provider, event_id)` em `pg_webhook_events`.
- NFe automática após pagamento: listener `EmitirNFeAoReceberPagamento` (NfeBrasil) wired ao evento de fatura paga, atrás da flag `nfebrasil.auto_emission_on_invoice_paid` (default `false`) — ligar em prod é US-RB-044, SDD §10.
- Sync bancário de saldos e extratos.
- Página Inertia de assinaturas, com drawer "Nova assinatura" (PR #2369).

## Gaps
- 🔴 Assinatura com valor negociado sem plano: `plan_id` null → `InvoiceGeneratorService` descarta com `Log::error`, sem alarme (`PlanoSemFaturaContratoTest`, `UC-RBSUB-05`, failing-first; contradiz a DoD do override de plano da US-RB-002 — correção pendente de decisão [W], SDD §9.1).
- Dunning/retry é régua hardcoded, não motor; PIX Automático, cartão tokenizado e proração ausentes.

## Última mudança
2026-08-31 — a tela passou a usar Input/Chart do DS (#6464). Antes: i18n `pt` (#6307, 2026-08-26), bypass de scope declarado no sync bancário (#5711, 2026-08-12), gates com permissão inexistente em assinatura e fatura (#5369, 2026-08-07), `CU-RB-15` fixando no SDD o contrato da US-RB-052 (2026-08-05) e, em 2026-08-03, os UC alcançáveis pelo manifesto G-7 com nove arquivos de teste entrando na lane sqlite do `ci.yml` (#5194; os outros três vieram no #5222); `PlanoSemFaturaContratoTest` segue fora por ser failing-first; `Wave21NewSubscriptionTest` e `Wave23EditarAssinaturaTest` ficaram de fora do #5194 por `AuthorizationException` e entraram na lane no mesmo dia, pelo #5222. Ligar a lane revelou dívida pré-existente, corrigida no teste, não no produto (o `skipped` denunciava um `UPDATE` no-op sobre instância stale; corrigido, o teste passou a provar idempotência com pré-condição anti-vácuo). A lista viva é `.github/ci-sqlite-pest.list`.

## Proveniência (destilado de)

- audit `requisitos/RecurringBilling/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/RecurringBilling/CAPTERRA-INVENTARIO-v2.md` — CAPTERRA-INVENTARIO-v2.md
- audit `requisitos/RecurringBilling/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- session `sessions/2026-09-06-refutacao-gt-g5-lote-6897-r6.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-lote-6897-r6.md
- session `sessions/2026-09-06-refutacao-gt-g5-lote-6897-r7.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-lote-6897-r7.md
