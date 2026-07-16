---
page: /fiscal/eventos
component: resources/js/Pages/Fiscal/Eventos.tsx
related_prototype: "n/a (herda PT-07 Feed/Timeline; segue o DS) · origem: bundle Cowork fiscal §11 FiscalEventosPage (mockup não versionado no repo)"
page_id: fiscal-eventos
url: /fiscal/eventos
module: Fiscal
status: draft
created: 2026-05-20
owner: wagner
related_us: [US-FISCAL-007]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0101-tests-business-id-1-nunca-cliente, 0104-processo-mwart-canonico-unico-caminho]
prototypes:
  - "prototipo-ui/.../fiscal-page.jsx §11 FiscalEventosPage"
---

# Charter — `Fiscal/Eventos`

## Mission

**Timeline append-only** de eventos SEFAZ aplicados a notas — CC-e + Cancelamento + EPEC + Manifestação destinatário. Acesso rápido pra auditoria LGPD Art. 37 + revisão fiscal.

## Goals (DoD PR #2)

1. **Lista timeline** NfeEvento via HasBusinessScope (ADR 0093)
2. **Filtros por tipo**: Todos, CC-e (110110), Cancelamento (110111), EPEC (110140), Manifesto (210200/210/220/240)
3. **Seletor período**: 7d / 30d / 90d (default 30d)
4. **Eager join** com NfeEmissao (numero, modelo, chave) — link clickável pra Fiscal/Nfe?focus=N
5. **Inertia::defer** em rows
6. **Permissão** `fiscal.access`
7. **Pest biz=1**: isolation + filtro por kind

## Non-Goals (PR #2)

- ❌ Drill-down drawer pro evento (apenas timeline horizontal — payload_json fica em audit log)
- ❌ Emitir novo evento (CC-e/Cancelar) — flow via /fiscal/nfe drawer (PR #4 mutations)
- ❌ Inutilização (vive em NfeInutilizacao — Model separado, sub-página futura)
- ❌ Export CSV (backlog)

## Anti-hooks

- 🚫 Eventos são **append-only** (NfeEvento::UPDATED_AT = null) — UI não permite edit
- 🚫 Não mostrar `payload_json` completo na timeline (pode ter PII em xMotivo do XML)
- 🚫 Justificativa truncada em 200 chars no Controller (já feito) — frontend não re-expande
- 🚫 Eager `with('emissao')` é OK (1 join) — não fazer 4-5 joins extras
