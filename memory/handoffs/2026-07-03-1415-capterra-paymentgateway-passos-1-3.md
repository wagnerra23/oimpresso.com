---
date: "2026-07-03"
time: "14:15 BRT"
slug: capterra-paymentgateway-passos-1-3
tldr: "capterra-senior PaymentGateway (nota 67/100) — FICHA + INVENTARIO + 4 US (US-PG-010..013) + régua das 2 telas (re-grade CnabRetorno 58→76). 2 PRs merged (#3736, #3752), read-only."
decided_by: ["W"]
cycle: null
prs: [3736, 3752]
us: ["US-PG-010", "US-PG-011", "US-PG-012", "US-PG-013"]
next_steps:
  - "Passo 4: promover cada UC-PG-NN citando o id no teste Feature existente (1 linha/caso)"
  - "P0 de valor: preview antes→depois no CnabRetorno (upload quita título sem preview)"
  - "Resolver Non-Goals do charter CnabRetorno (draft) com Wagner"
  - "Executar US-PG-010 (refund uniforme nos 6 drivers)"
related_adrs:
  - "0089-capterra-driven-module-evolution"
  - "0093-multi-tenant-isolation-tier-0"
  - "0105-cliente-como-sinal-guiar-sem-mandar"
  - "0170-paymentgateway-extracao-camada-cobranca"
  - "0264-governanca-executavel-trio-dominio-e2e"
---

# Handoff — PaymentGateway adversário de mercado (Passos 1-3) · 2026-07-03 14:15

> Sessão `musing-roentgen-575883`. Tarefa: `capterra-senior` PaymentGateway (aprovado [W] 2026-07-03) + continuação "vai/todos/merge".
> **2 PRs mergeados em main.** Read-only sobre código.

## O que foi feito (3 passos do template-onda-modulo)

**Passo 1 — Adversário (FICHA)** · PR #3736
- [`memory/requisitos/PaymentGateway/CAPTERRA-FICHA.md`](../requisitos/PaymentGateway/CAPTERRA-FICHA.md) — 10 seções, 6 concorrentes (Asaas/Iugu/Pagar.me/Stripe/MercadoPago/Cielo) × 8 dimensões, 19 capacidades P0-P3, **nota 67/100 (Médio)**.
- 2 diferenciais que nenhum PSP tem: CNAB 240/400 multi-banco (11 drivers) + multi-gateway por `business_id`.

**Passo 2 — Gaps + backlog** · PR #3736
- [`CAPTERRA-INVENTARIO.md`](../requisitos/PaymentGateway/CAPTERRA-INVENTARIO.md) — buckets 6✅/9🟡/4❌.
- **4 US criadas via `tasks-create` MCP** (aprovação [W] "todos") + apendidas ao SPEC:
  - **US-PG-010** Refund uniforme nos 6 drivers (P0, REGRA MESTRE valor)
  - **US-PG-011** Boleto híbrido boleto+QR Pix (P1)
  - **US-PG-012** Extrato/saldo + conciliação contábil (P2)
  - **US-PG-013** Split de pagamento (P2, **feature-wish dormente** — ADR 0105, sem sinal)
- Gaps já rastreados (não recriados): US-PG-002/003 webhook hardening, 006/007 Inter, 008 cutover, 009 smokes.

**Passo 3 — Régua por tela** · PR #3752
- 2 `casos.md` (Index + CnabRetorno): UCs backlog mapeados aos testes Feature existentes. Débito real = **rastreabilidade G-2** (0 UC-id), não ausência de teste.
- Re-grade **CnabRetorno 58→76** (scorecard 30/mai stale — os 3 gaps DS-v4 já resolvidos). Index mantido 80.
- **Exposição Tier-0 nº1:** upload CNAB quita títulos **sem preview antes→depois** (D1 REGRA MESTRE, candidato P0).

## Próximo passo sugerido (quem pegar)

- **Passo 4** (catraca+sentinela): promover cada UC-PG-NN citando o id no teste Feature que já existe (1 linha/caso) → `casos-gate` passa a defender.
- **P0 de valor:** preview antes→depois no CnabRetorno (US candidata).
- Charter CnabRetorno ainda `draft` (Non-Goals abertos — reprocessar/download/lote/AuditLog): resolver com Wagner.
- Executar US-PG-010 (refund uniforme) — maior gap P0 de paridade.

## Estado MCP no momento do fechamento

- **cycles-active:** nenhum cycle ATIVO em COPI.
- **my-work (@wagner):** 30 tasks (8 review incl. US-PG-008; 8 blocked; 14 todo). US-PG-010..013 entraram como todo/unowned (sync pós-merge confirmado: US-PG-010 aparece em `tasks-list module:PaymentGateway priority:p0`).
- **decisions recentes:** sem ADR novo nesta sessão (ficha/inventário/casos são docs, não decisões).
- **PRs mergeados:** #3736 (Passos 1+2, commit 647f62c2), #3752 (Passo 3, commit 725b0088).

## Session logs

- [`sessions/2026-07-03-capterra-paymentgateway.md`](../sessions/2026-07-03-capterra-paymentgateway.md) (Passos 1+2)
- [`sessions/2026-07-03-pg-passo3-regua-telas.md`](../sessions/2026-07-03-pg-passo3-regua-telas.md) (Passo 3)
