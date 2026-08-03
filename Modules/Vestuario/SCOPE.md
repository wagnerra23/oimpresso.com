---
module: Vestuario
purpose: "Vertical lojas de vestuário e moda BR (CNAE 4781-4/00): capacidades que o núcleo não tem por serem específicas do ramo — etiqueta TAG térmica (ZPL e PDF, tamanho x cor x coleção) e devolução/troca CDC com crédito-ficha — mais os settings por business do vertical."
migracao_ui: "concluido — 0 Blade servido"
contains:
  - "EtiquetaTagController — geração lote etiquetas TAG (ZPL Argox/Zebra + PDF DomPDF) per business (US-VEST-020, RUNBOOK-etiqueta-tag.md)"
  - "EtiquetaTagService — ZPL 50×30mm + EAN-13 GS1 + QR Code opcional + settings configurable per business (Wave 27 + US-VEST-020)"
  - "GradeCurvaService — matriz tamanho × cor + proporção curva BR (Wave 27, ADR 0121 §P7)"
  - "VestuarioSettingsResolver — settings JSON per business cache 5min (Sprint 2 ADR 0121)"
  - "DevolucaoService — CDC art. 49 7d devolução + crédito vest_creditos_cliente (US-VEST-021, scaffold)"
not_contains:
  - "Núcleo transactions/contacts → UltimatePOS core (compartilhado entre verticais)"
  - "NFe/NFC-e → Modules/NfeBrasil (backbone)"
  - "Kanban shared infra → Modules/Repair (consumido opcional via repair_settings JSON)"
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
trust_required: L2
owner: wagner
permission_prefix: vestuario.*
charter_adr: 0121
related_adrs:
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0066-format-date-shift-3h-preservado-legacy-clientes
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0093-multi-tenant-isolation-tier-0
url_prefixes:
  - /vestuario/*
drift_alerts: []
---

# Modules/Vestuario — vertical lojas de vestuário/moda BR

> ADR mãe: [0121](../../memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md) §P7
> SPEC: [memory/requisitos/Vestuario/SPEC.md](../../memory/requisitos/Vestuario/SPEC.md)
> Cliente piloto: ROTA LIVRE biz=4 (Larissa, Termas do Gravatal/SC) em prod desde 2024-Q1
> CNAE: 4781-4/00

## Estado Sprint 1

Scaffold formal nWidart — módulo nasce vazio porque **ROTA LIVRE já usa o produto há 2+ anos** via núcleo UltimatePOS + Modules/{Financeiro, NfeBrasil, Copiloto} com customizações pontuais. Esta pasta:

1. **Habilita revenda** do módulo pra outras lojas vestuário CNAE 4781
2. **Encapsula customizações ROTA LIVRE** progressivamente (format_date shift +3h [ADR 0066](../../memory/decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md))
3. **Consome shared Modules/Repair** opcional (kanban fluxo costureira→revisão→finalização) com vocabulário shared via `RepairSettingsSeeder`

## Sprint 2+ — adoção progressiva

| US | Descrição | Sinal qualificado |
|---|---|---|
| US-VEST-001 | Scaffold módulo (este PR) | ADR 0121 §P7 (já confirmado) |
| US-VEST-002 | RepairSettingsSeeder + apply em biz=4 | Felipe roda local |
| US-VEST-003 | Migrar customizações ROTA LIVRE pro módulo | Cliente reporta drift |
| US-VEST-004 | Pages Inertia próprias (variação tamanho/cor/estação) | Sinal de revenda outro cliente CNAE 4781 |

> **Política:** zero código novo sem sinal qualificado [ADR 0105]. Backlog de hipóteses não-validadas fica em `memory/requisitos/Vestuario/SPEC.md` §Backlog.

## Não-goals

- ❌ NÃO substitui núcleo UltimatePOS (continua compartilhado entre verticais)
- ❌ NÃO duplica Modules/NfeBrasil (vertical consome backbone)
- ❌ NÃO impõe Repair (kanban opcional via repair_settings JSON)
