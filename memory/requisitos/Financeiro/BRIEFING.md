---
id: requisitos-financeiro-briefing
module: Financeiro
status: producao
updated_at: "2026-07-23"
distilled_at: "2026-07-27"
distilled_by: "manual [C] — redestilação PARCIAL: o módulo ganhou SDD + contratos de tela no chip do passo 5 (PR #4867). O resto do corpo NÃO foi re-lido; só a seção Contrato de tela é nova."
---

# BRIEFING — Financeiro (verdade destilada)

O módulo "Financeiro" fornece uma visão unificada de Contas a Receber (AR), Contas a Pagar (AP), Fluxo de Caixa, Boletos, Conciliação OFX e um workflow de aprovação. Está em operação com 87% de cobertura funcional e paridade visual de 9.5/10 em relação ao canon.

## Capacidades
- Emissão de boletos real via Banco Inter com integração completa.
- Conciliação automatizada de pagamentos através de eventos de cobrança.
- Workflow para aprovação de transações com visualização integrada de AR/AP.
- Integração de bulk actions para operações em lote com confirmação e audit trail.
- Ações em lote na Visão Unificada para até 500 títulos por chamada.

## Gaps
- Sicoob aguarda credenciais sandbox do cliente (Inter/C6/Asaas/BcbPix já ativos; flags OFF em prod — ADR 0170).
- Mobile/PWA e notificações de vencimento (bucket ❌ do inventário).
- Import CSV (bucket ❌ do inventário); parser de retorno CNAB pendente (🟡 P6 — sem parser em `Services/`).

## Última mudança
As ações em lote na Visão Unificada (`POST /unificado/bulk`, ≤500 títulos por chamada, com audit trail) foram entregues pela US-FIN-031 (PR #3905, 2026-07-06). A cobertura de 87% já havia sido atingida antes, nas Ondas 12-21 (2026-05-19) — sem relação causal com a emissão de boleto.

## Proveniência (destilado de)

- audit `requisitos/Financeiro/AUDIT-FUNCOES-2026-05-19.md` — AUDIT-FUNCOES-2026-05-19.md
- audit `requisitos/Financeiro/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- handoff `handoffs/2026-07-16-1730-smoke-financeiro-15-dimensoes-verde-vazio-ziggy.md` (2026-07-16) — 2026-07-16-1730-smoke-financeiro-15-dimensoes-verde-vazio-ziggy.md
- session `sessions/2026-07-13-financeiro-visreg-enforcing.md` (2026-07-13) — 2026-07-13-financeiro-visreg-enforcing.md
- handoff `handoffs/2026-07-13-1719-financeiro-visreg-enforcing.md` (2026-07-13) — 2026-07-13-1719-financeiro-visreg-enforcing.md
- session `sessions/2026-07-08-financeiro-borda-dark-token.md` (2026-07-08) — 2026-07-08-financeiro-borda-dark-token.md
- session `sessions/2026-07-08-financeiro-fidelidade-fingerprint-protocolo.md` (2026-07-08) — 2026-07-08-financeiro-fidelidade-fingerprint-protocolo.md
- handoff `handoffs/2026-07-08-1044-financeiro-fidelidade-fingerprint-furos.md` (2026-07-08) — 2026-07-08-1044-financeiro-fidelidade-fingerprint-furos.md
- handoff `handoffs/2026-07-08-1431-financeiro-borda-dark-token-ui0022.md` (2026-07-08) — 2026-07-08-1431-financeiro-borda-dark-token-ui0022.md
- handoff `handoffs/2026-07-07-1746-financeiro-fidelidade-dark-mecanismos-comparacao.md` (2026-07-07) — 2026-07-07-1746-financeiro-fidelidade-dark-mecanismos-comparacao.md

## Contrato de tela (SDD)

O módulo passou a ter **SDD** em [`SDD-tela-financeiro-v1.0.md`](SDD-tela-financeiro-v1.0.md) — §5 fluxos + §6 casos de uso — e `casos.md` por tela,
gerados pelo chip `sdd-from-source` ([ADR 0351](../../decisions/0351-sdd-from-source.md), PR #4867).

> **Contagem viva — não copiada aqui** (CU · UC · telas cobertas · onde a cadeia quebra):
> `node scripts/governance/requisitos-status.mjs Financeiro`
>
> O painel derivado fica em [`_STATUS-GENERATED.md`](_STATUS-GENERATED.md). Número escrito à mão apodrece —
> este doc aponta para o dono, não restateia (proibições §5, 2026-07-17).
