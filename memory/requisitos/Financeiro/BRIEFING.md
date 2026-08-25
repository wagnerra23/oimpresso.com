---
id: requisitos-financeiro-briefing
module: Financeiro
status: producao
updated_at: "2026-08-18"
distilled_at: "2026-08-18"
distilled_by: "manual [C] — redestilação PARCIAL: re-lidos os 3 commits que tocaram SPEC/SCOPE/CHANGELOG/RUNBOOK-index desde 2026-08-05 (#5686 telas-no-módulo-dono, #5568 SCOPE fora de Modules, #5547 fusão dos CHANGELOG) — todos estruturais, nenhum muda capacidade. Seções Capacidades e Gaps re-conferidas e mantidas; acrescentada nota de localização das telas. Contrato de tela permanece do PR #4867."
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
- **Testes do módulo em quarentena na lane** — parte da suíte não roda no CI e portanto não produz veredito. A lista viva (com o motivo de cada arquivo) é o dono do número: [`.github/financeiro-pest-quarantine.list`](../../../.github/financeiro-pest-quarantine.list); a lane que a consome é `.github/workflows/financeiro-pest.yml`. _Recibo: em 2026-08-05 a triagem dos 13 do grupo C ("defeito real") apontou 2 bugs de produto — US-FIN-068 (fechada) e US-FIN-055 (`total_remaining_amount`, aberta) —, 1 suspeita não fechada em `aprovacao_status` (toca US-FIN-027/028) e 5 testes desatualizados. Re-rode a lista, não edite este parágrafo._

## Última mudança
Desde 2026-08-05 o módulo recebeu **3 mudanças estruturais, nenhuma de capacidade**: as telas Inertia passaram a morar no módulo dono ([#5686](https://github.com/wagnerra23/oimpresso.com/pull/5686), 5 ondas, 73 de 445) — **o Financeiro ainda NÃO migrou**, suas 59 `.tsx` seguem em `resources/js/Pages/Financeiro/` (já migraram Cms, Forja, KB, PaymentGateway, Superadmin, Whatsapp); o `SCOPE.md` saiu de `Modules/` ([#5568](https://github.com/wagnerra23/oimpresso.com/pull/5568), ADR 0375); e os 15 CHANGELOG duplicados foram fundidos em `memory/requisitos/` ([#5547](https://github.com/wagnerra23/oimpresso.com/pull/5547)). Quem for editar tela do Financeiro **confirme o path antes** — ele muda quando a onda alcançar o módulo.

Antes disso, o comando `financeiro:bridge-expense-to-titulos` (bridge despesa do core → título AP) estava **quebrado em produção** e voltou a funcionar (US-FIN-068). Ele filtrava por `transactions.deleted_at`, coluna que não existe — medido em 3 fontes, incluindo produção — e falhava com `SQLSTATE[42S22]` em toda execução. O comando **não é agendado**, então a falha era silenciosa: só aparecia para quem o rodasse à mão. Corrigido removendo o filtro; o teste saiu de `6 failed / 2 assertions` para `8 passed / 26 assertions` e deixou a quarentena da lane (25 → 24).

Antes disso: ações em lote na Visão Unificada (`POST /unificado/bulk`, ≤500 títulos por chamada, com audit trail) entregues pela US-FIN-031 (PR #3905, 2026-07-06). A cobertura de 87% já havia sido atingida antes, nas Ondas 12-21 (2026-05-19) — sem relação causal com a emissão de boleto.

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
