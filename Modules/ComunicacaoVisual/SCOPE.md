---
module: ComunicacaoVisual
purpose: "Vertical gráfica rápida BR (CNAE 1813-0/01). Diferencial: cálculo m² + PCP gráfico + apontamento + NFe-de-boleto-pago + IA conversacional. Greenfield aguardando piloto Q3/2026 entre OfficeImpresso clients."
contains:
  - "ApontamentoController"
  - "DataController"
  - "InstallController"
  - "OrcamentoController"
not_contains:
  - "Kanban shared infra → Modules/Repair (consumido via repair_settings)"
  - "Núcleo transactions/contacts → UltimatePOS core"
  - "NFe backbone → Modules/NfeBrasil (consumido)"
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

## Estado Sprint 1

Scaffold formal nWidart **vazio** — vertical greenfield. Pasta nasce pra:

1. **Habilitar piloto Q3/2026** entre 6 saudáveis OfficeImpresso (Vargas, Extreme, Gold, Zoom, Fixar, Mhundo, Produart — Wagner escolhe 1)
2. **Consumir shared Modules/Repair** com vocabulário gráfico via `RepairSettingsSeeder` (OS / Trabalho / Operador / Máquina Plotter+ACM+Lona / Setor Arte+Impressão+Acabamento)
3. **Diferencial vs concorrentes:** cálculo m² + PCP gráfico + apontamento + NFe-de-boleto-pago + IA conversacional (5 capacidades juntas, nenhum concorrente tem todas)

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
