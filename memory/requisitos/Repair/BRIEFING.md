---
id: requisitos-repair-briefing
module: Repair
status: shared-infra
updated_at: "2026-07-23"
distilled_at: "2026-07-23"
distilled_by: jana:distill-module-truth
---

# BRIEFING — Repair (verdade destilada)

> Última atualização: 2026-07-02

## Estado atual
O módulo "Repair" gerencia ordens de serviço em infraestrutura compartilhada, usado por verticais como `OficinaAuto`, `ComunicacaoVisual` e `Vestuario`. O Kanban `ProducaoOficina` e o FSM Pipeline de OS (13 estágios) rodam em prod; o SPEC do próprio módulo é um placeholder (US-REPA-001 `_pendente_`) — as capacidades vivas foram construídas fora dele.

## Capacidades
- **JobSheet** para gerenciamento de ordens de serviço.
- Kanban com 5 colunas fixas (Recepção, Diagnóstico, Aguardando peças, Em execução, Pronto); os `repair_statuses` em si são configuráveis por business.
- Interface **ProducaoOficina** com recursos de drag-and-drop para organização visual das ordens.
- Integração com automação de faturamento ao término do serviço.
- Implementação de **FSM Pipeline** para orquestramento dos estágios de ordem de serviço.
- Suporte a múltiplas verticais, permitindo vocabulário genérico e ajustes específicos por negócio.

## Gaps
- Top-5 da FICHA (US-REP-005..009): KPIs/dashboard, app mobile, comissão, catálogo, retention-purge.
- Bulk-start de OS legadas pro FSM: comando `repair:fsm:bulk-start` inexistente (US-REP-FSM-006).

## Última mudança
Perf D-14 partial reload no Kanban `ProducaoOficina` (PR #3901, 2026-07-06) e draft de charter da OS (PR #4123, 2026-07-12).

## Proveniência (destilado de)

- audit `requisitos/Repair/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- session `sessions/2026-07-02-dossie-triagem-onda4-revisao-adr.md` (2026-07-02) — 2026-07-02-dossie-triagem-onda4-revisao-adr.md
