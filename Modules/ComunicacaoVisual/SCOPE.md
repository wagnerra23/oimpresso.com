---
module: ComunicacaoVisual
purpose: "Vertical gráfica rápida BR (CNAE 1813-0/01). Diferencial: cálculo m² + PCP gráfico + apontamento + NFe-de-boleto-pago + IA conversacional. Greenfield aguardando piloto Q3/2026 entre OfficeImpresso clients."
contains:
  - "ApontamentoController"
  - "DataController"
  - "InstallController"
  - "OrcamentoController"
  - "Entities/Substrato (cv_substratos)"
  - "Entities/Acabamento (cv_acabamentos)"
  - "Entities/InstalacaoCatalogo (cv_instalacoes_catalogo)"
  - "Entities/OrdemProducao (cv_ordens_producao + FSM canon ADR 0143)"
  - "Entities/Instalacao (cv_instalacoes)"
  - "FsmProcessoComunicacaoVisualSeeder (16 stages + 10 roles per-business)"
not_contains:
  - "Kanban shared infra → Modules/Repair (consumido via repair_settings)"
  - "Núcleo transactions/contacts → UltimatePOS core"
  - "NFe backbone → Modules/NfeBrasil (consumido)"
  - "FSM canon (sale_processes/stages/actions) → app/Domain/Fsm/ (shared infra ADR 0143)"
trust_required: L2
owner: wagner
permission_prefix: com_visual.*
charter_adr: 0121
related_adrs:
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0093-multi-tenant-isolation-tier-0
url_prefixes:
  - /com-visual/*
drift_alerts: []
---

# Modules/ComunicacaoVisual — vertical gráfica rápida BR

> ADR mãe: [0121](../../memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md) §P7
> SPEC: [memory/requisitos/ComunicacaoVisual/SPEC.md](../../memory/requisitos/ComunicacaoVisual/SPEC.md)
> Status: 🟡 em construção · Piloto previsto 2026-Q3
> CNAE: 1813-0/01 · Concorrentes: Mubisys, Zênite, Calcgraf

## Estado Fase 1 V0 scaffold (2026-05-12)

Scaffold nWidart **completo** + 5 migrations canon `cv_*` SPEC §12.1 + 5 Entities com BusinessIdScope + FsmProcessoComunicacaoVisualSeeder (16 stages × 30+ actions × 10 roles per-business).

Coexiste com legacy Sprint 1 (`comvis_*` tables — Material/Orcamento/Os/Apontamento) — Migration Factory US-COMVIS-NEW-014 (Sprint 2+) decide caminho de unificação.

1. **Habilitar piloto Q3/2026** entre 6 saudáveis OfficeImpresso (Gold confirmado vertical comvis; Extreme/Zoom/Fixar/Mhundo/Produart a confirmar — ROADMAP Fase 2)
2. **Consumir FSM canon shared** `app/Domain/Fsm/` (ADR 0143 LIVE prod biz=1 desde 2026-05-12) via `current_stage_id` em `cv_ordens_producao` + trait `GuardsFsmTransitions`
3. **Diferencial vs concorrentes:** cálculo m² + PCP gráfico FSM + dual-doc fiscal NFe55+NFSe56 paralelo + NFe-de-boleto-pago + IA conversacional (5 capacidades juntas, nenhum concorrente tem todas — Mubisys/Zênite/Calcgraf SPEC §4-5)

## Sprint 2+ — adoção pós-piloto

| US | Descrição | Sinal qualificado |
|---|---|---|
| US-COMVIS-001 | Scaffold módulo (este PR) | ADR 0121 §P7 |
| US-COMVIS-002 | Migration `comvis_orcamentos` (cálculo m² + multi-tier price) | Piloto escolhido + assina contrato |
| US-COMVIS-003 | Pages Inertia próprias (orçamento, PCP, apontamento) | US-COMVIS-002 done |
| US-COMVIS-004 | Integração Modules/NfeBrasil (NFe-de-boleto-pago) | US-COMVIS-003 done |

## Não-goals

- ❌ NÃO codificar features sem cliente piloto pagante [ADR 0105]
- ❌ NÃO duplicar shared Modules/Repair (consome via repair_settings)
- ❌ NÃO substituir núcleo UltimatePOS
