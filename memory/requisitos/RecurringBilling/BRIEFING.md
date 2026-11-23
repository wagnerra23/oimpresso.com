---
id: requisitos-recurring-billing-briefing
module: RecurringBilling
status: producao
updated_at: "2026-11-23"
distilled_at: "2026-09-06"
distilled_by: jana:distill-module-truth
---

# BRIEFING — RecurringBilling (verdade destilada)

## Estado atual
Assinaturas e faturas recorrentes com múltiplos gateways, em produção (uso medido pelo ledger `governance/route-hits.json` via `anchor-lint --json`). Maturidade de features: eixo 1 do `CAPTERRA-INVENTARIO-v2.md` (snapshot datado, não porta viva — não copie o número). O contrato anti-regressão deste módulo é o SPEC + o trio charter/casos/teste — não há Blade legada nem `ANTI-REGRESSAO-*` Delphi como fonte de paridade (o WR Comercial não tinha recorrência). Governança: `SDD-cobranca-recorrente-v1.0.md` (SDD do domínio criado do zero pelo `sdd-from-source`, ADR 0351, #4914 — a ordem de criação entre os SDDs é derivada: `gh api repos/wagnerra23/oimpresso.com/commits?path=<sdd>`; §5 fluxos, §6 `CU-RB-01..15`) + trio charter/casos/teste nas telas (cobertura de UC: `node scripts/governance/requisitos-status.mjs RecurringBilling`, painel derivado em `_STATUS-GENERATED.md`; a fila de CU sem UC é derivada — sai da mesma porta, não se copia aqui); a view Blade era "Hello World" e o cutover da Onda 10 deixou `/recurringbilling` → `/recurring-billing` em 301 (SDD §Fontes). Sinal de uso: US wired sem hits na janela de 30 dias do ledger `governance/route-hits.json` — veredito advisory `nao_servido` do `anchor-lint --json` (chave = Page da US, não rota). Dono: SDD §9.4.

## Capacidades
- Planos: CRUD de `rb_plans` com ciclo e trial.
- Contratos: estados de `rb_subscriptions`.
- Cobrança via `BoletoService` com driver por gateway (Inter, Asaas, C6) — pilha própria do RB (`Services/Boleto/Drivers` + `rb_boleto_credentials`). O dono dos drivers é o `Modules/PaymentGateway` (ADR 0456, [#11347](https://github.com/wagnerra23/oimpresso.com/pull/11347), proposta mergeada em 2026-11-23; decisão b1); a migração do boleto está em andamento (sessão `rb-l2b-gateway`).
- Assinatura sem plano: fatura pelo valor negociado (`metadata.valor`/`metadata.ciclo`) desde 2026-10-19 (#9606); `plan_id` é nulável (migration `2026_10_26_130000`).
- Geração de faturas: `rb:generate-invoices`.
- Pix Automático (US-RB-020/021, #9870): mandato na **Jornada 2** do BCB (ADR 0455, mesmo PR), cobrado por `rb:cobrar-pix-automatico`, driver e credencial no PaymentGateway. Atrás de `RB_COBRAR_PIX_AUTOMATICO` (padrão `false`).
- Cartão salvo (US-RB-011, #9951): o token do cartão pago na página do Asaas fica ligado à assinatura; número de cartão nunca é gravado. Atrás de `RB_SALVAR_CARTAO` (padrão `false`). Cobrar com o token e mostrar na tela (b5): em andamento (`rb-b5-cartao`).
- Cobrança paga no PaymentGateway → fatura paga (US-RB-057, #9844). Atrás de `RB_MARCAR_FATURA_PAGA_POR_COBRANCA` (padrão `false`).
- Cancelamento: `POST /financeiro/rb-invoices/{invoice}/cancelar` (UI `Faturas/Index.tsx`) — Inter e Asaas por API; C6 exige cancelamento manual no portal (driver lança `BadMethodCallException`, US-RB-042 `_parcial_`).
- Webhook Asaas em `POST /api/webhooks/asaas/{businessId}`: autenticado por segredo cifrado (#11227), idempotente por `(business_id, provider, event_id)` em `pg_webhook_events` (#11242), processado pela fila `rb_webhooks` (worker no `Kernel` desde #11230). Credita uma vez e só na quitação, e dispara `InvoicePaid` só na transição (#11223). O Inter (`/webhooks/inter/pix/{id}`) ainda usa `insertOrIgnore`; crédito único em andamento em [#11352](https://github.com/wagnerra23/oimpresso.com/pull/11352).
- Fatura paga **não** vira título no Financeiro: a ponte (consumidor de `InvoicePaid`) é do Financeiro (decisão b2). Hoje o efeito é crédito em `account_transactions`. Fluxo completo: SDD F13.
- Acesso: pacote `recurringbilling_module` em todas as rotas web (#11205) e permissão própria por rota (#11247).
- NFe automática após pagamento: listener `EmitirNFeAoReceberPagamento` (NfeBrasil) wired ao evento de fatura paga, atrás da flag `nfebrasil.auto_emission_on_invoice_paid` (default `false`) — ligar em prod é US-RB-044, SDD §10.
- Sync bancário de saldos e extratos.
- Página Inertia de assinaturas, com drawer "Nova assinatura" (PR #2369).

## Gaps
- ~~🔴 Assinatura com valor negociado sem plano descartada pelo gerador~~ — resolvido em 2026-10-19 (#9606, remédio "a" de [W]; SDD §9.1 Desfecho).
- Duas pilhas de driver para o mesmo banco (boleto no RB, Pix/cartão no PaymentGateway) até a migração terminar (SDD D9).
- Fatura paga não gera título/baixa no Financeiro; a ponte é do Financeiro (SDD D10).
- Inter ainda sem crédito único na quitação — em andamento em [#11352](https://github.com/wagnerra23/oimpresso.com/pull/11352).
- Apagar contato ou plano leva assinaturas e faturas junto (`cascadeOnDelete`); a decisão b6 é que não leve — em andamento em [#11346](https://github.com/wagnerra23/oimpresso.com/pull/11346).
- Dunning/retry é régua hardcoded, não motor; proração ausente. A régua por e-mail e WhatsApp sai da tela (decisão b7) — em andamento em [#11350](https://github.com/wagnerra23/oimpresso.com/pull/11350).
- Flags desligadas em produção: `RB_COBRAR_PIX_AUTOMATICO`, `RB_SALVAR_CARTAO`, `RB_MARCAR_FATURA_PAGA_POR_COBRANCA`, `nfebrasil.auto_emission_on_invoice_paid`. Ligar é do [W] (US-RB-044 e US-RB-057 ficam "parcial (flag desligada)", decisão b8).

## Última mudança
2026-11-19 a 2026-11-23 — auditoria do módulo (lotes L0/L1): contato só da empresa da sessão (#11196), pacote (#11205) e permissão por rota (#11247), worker `rb_webhooks` (#11230), crédito único do Asaas (#11223), idempotência e FK por empresa (#11242), segredo do webhook cifrado (#11227), timeline imutável (#11233), reenviar NF-e só pela nota vinculada (#11209). Decisões b1–b8 registradas pelo coordenador em 2026-11-23 a pedido do [W]. Em outubro: assinatura sem plano faturando (#9606), Pix Automático (#9870), US-RB-057 (#9844), cartão salvo (#9951).

Antes disso:
2026-08-31 — a tela passou a usar Input/Chart do DS (#6464). Antes: i18n `pt` (#6307, 2026-08-26), bypass de scope declarado no sync bancário (#5711, 2026-08-12), gates com permissão inexistente em assinatura e fatura (#5369, 2026-08-07), `CU-RB-15` fixando no SDD o contrato da US-RB-052 (2026-08-05) e, em 2026-08-03, os UC alcançáveis pelo manifesto G-7 com nove arquivos de teste entrando na lane sqlite do `ci.yml` (#5194; os outros três vieram no #5222); `PlanoSemFaturaContratoTest` segue fora por ser failing-first; `Wave21NewSubscriptionTest` e `Wave23EditarAssinaturaTest` ficaram de fora do #5194 por `AuthorizationException` e entraram na lane no mesmo dia, pelo #5222. Ligar a lane revelou dívida pré-existente, corrigida no teste, não no produto (o `skipped` denunciava um `UPDATE` no-op sobre instância stale; corrigido, o teste passou a provar idempotência com pré-condição anti-vácuo). A lista viva é `.github/ci-sqlite-pest.list`.

## Proveniência (destilado de)

- audit `requisitos/RecurringBilling/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/RecurringBilling/CAPTERRA-INVENTARIO-v2.md` — CAPTERRA-INVENTARIO-v2.md
- audit `requisitos/RecurringBilling/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- session `sessions/2026-09-06-refutacao-gt-g5-lote-6897-r6.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-lote-6897-r6.md
- session `sessions/2026-09-06-refutacao-gt-g5-lote-6897-r7.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-lote-6897-r7.md
