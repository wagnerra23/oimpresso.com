---
module: OficinaAuto
purpose: "Vertical oficinas automotivas BR (CNAE 4520-0/01). Estado feature-wish (ADR 0105) — aguarda sinal qualificado pra ativar. Candidato piloto: Martinho Caçambas (a confirmar). Scaffold vazio."
contains:
not_contains:
  - "Repair OS shared → Modules/Repair"
  - "Catálogo peças → futuro Modules/Autopecas (ADR 0125 feature-wish)"
trust_required: L1
owner: wagner
permission_prefix: oficinaauto.*
charter_adr: 0121
related_adrs:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0121-oimpresso-modular-especializado-por-vertical
url_prefixes:
  - /oficina-auto/*
drift_alerts: []
---

# Modules/OficinaAuto — vertical oficinas automotivas BR

> ADR mãe: [0121](../../memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md) §P7
> SPEC: [memory/requisitos/OficinaAuto/SPEC.md](../../memory/requisitos/OficinaAuto/SPEC.md)
> Status: ⏸️ feature-wish (aguarda sinal qualificado [ADR 0105])
> Candidato piloto: Martinho Caçambas (a confirmar)
> CNAE: 4520-0/01 · Concorrentes: Mecânico, Auto Manager, Lokoz

## Estado Sprint 1

Scaffold formal nWidart **vazio** + RepairSettingsSeeder com vocabulário automotivo. Vertical é o caso de uso ORIGINAL do Modules/Repair (assistência-técnica/automotiva) — defaults Repair já cobrem (Box+Elevador+Mecânico+Placa).

Esta pasta nasce pra:
1. **Encapsular vocabulário automotivo** quando refactor US-REPA-002 tornou Modules/Repair shared (vocabulário neutro)
2. **Esperar sinal qualificado** Martinho Caçambas confirmar piloto
3. **Não impedir uso atual** — Repair continua funcional sem este módulo

## Sprint 2+ — adoção condicional

| US | Descrição | Sinal qualificado |
|---|---|---|
| US-OFICAUTO-001 | Scaffold módulo (este PR) | ADR 0121 §P7 |
| US-OFICAUTO-002 | RepairSettingsSeeder apply em biz piloto | Martinho confirma |
| US-OFICAUTO-003 | Pages Inertia próprias (busca FIPE, OS-com-OS_pai recall) | Pós-piloto |

## Não-goals

- ❌ NÃO codificar features sem cliente piloto pagante [ADR 0105]
- ❌ NÃO duplicar shared Modules/Repair (vocabulário automotivo via seeder per-vertical)
- ❌ NÃO substituir núcleo UltimatePOS
