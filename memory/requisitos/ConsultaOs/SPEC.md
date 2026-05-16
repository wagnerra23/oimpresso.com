---
modulo: ConsultaOs
status: mock-only
related_adrs: [0153, 0154, 0155]
na_justified:
  D3.b: "ConsultaOs é módulo público de consulta OS (cliente final consulta status via número) — mock-only hoje. Migrar pra real (US-CONSULTA-001) está em backlog. BRIEFING.md prematuro até migração TODO."
  D6.a: "Portal público client-side state + fetch JSON API (não usa Inertia partial reload). Única `Inertia::render('ConsultaOs/Index')` retorna view sem props (página gerencia estado via React useState; busca via fetch('/consulta-os/buscar') retornando JsonResponse). Sem props paginadas/count()/with() eager — `Inertia::defer` inaplicável (análogo ao Connector REST API backend citado em ADR 0155 §188). Reavaliar após US-CONSULTA-001 (integrar Repair via Service read-only) caso payload passe a vir via Inertia props."
---

# SPEC — Modules/ConsultaOs

## Visão

Módulo público (sem auth) para cliente final consultar o status de uma Ordem de Servico (OS) via número de protocolo + telefone/CPF. Hoje opera em **modo mock-only**: 3 Controllers retornam payload fake pra validar layout/UX antes de cabear no Repair real.

## Arquitetura atual (mock-only)

- 3 Controllers (`ConsultaController`, `StatusController`, `LookupController` — mock payload)
- Sem entidades Eloquent próprias (não toca DB ainda)
- 3 tests Pest cobertura básica (Wave B 2026-05-12 — smoke render + 404 + lookup-by-protocol)
- Sem `business_id` scope (consulta pública por protocolo único globally)

## Roadmap (TODO migrar pra real)

- **US-CONSULTA-001** (backlog): substituir mock por query real em `Modules/Repair` via Service read-only, com rate limit por IP + captcha
- **US-CONSULTA-002** (backlog): canary 7d em ROTA LIVRE antes de outros tenants
- **US-CONSULTA-003** (backlog): criar `BRIEFING.md` após migração real (`na_justified D3.b` cai)

## N/A justificado

- **D3.b BRIEFING.md** — prematuro enquanto mock-only. Briefing canônico (1 página executiva) pressupõe capacidade de produto real entregando valor; mock não atende esse critério. Quando US-CONSULTA-001 for done, criar BRIEFING e remover N/A.
- **D6.a Inertia::defer** — portal público com client-side state + fetch JSON API. `index()` retorna `Inertia::render('ConsultaOs/Index')` SEM props (página React gerencia tudo via `useState`, busca dispara `fetch('/consulta-os/buscar')` retornando `JsonResponse`). Sem props pesadas pra deferir (sem `paginate()`, `count()`, `with()` eager-load, Service-DB call). Pattern análogo ao Connector REST API backend documentado em [ADR 0155 §188](../../decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md). Reavaliar após US-CONSULTA-001 caso payload passe a vir via Inertia props (em vez de JSON).

## Arquitetura — separation of concerns

| Camada | Arquivo | Responsabilidade |
|---|---|---|
| Inertia render (single page) | `ConsultaOsController@index` | Boot página React vazia (zero props — client-state) |
| Busca pública (JSON) | `ConsultaOsController@buscar` | Recebe `ConsultaPublicaRequest` validado, retorna mock OS ou 404 |
| Validação anti-enumeration | `Http/Requests/ConsultaPublicaRequest` | `alpha_num` + `max:20` + lista controlada de estágios |
| Hooks UltimatePOS | `DataController` | `superadmin_package` + `user_permissions` + `modifyAdminMenu` (sidebar opt-out) |
| Boot módulo | `InstallController` extends `BaseModuleInstallController` | Install flow standard nWidart |
| Front-end (client-state) | `resources/js/Pages/ConsultaOs/Index.tsx` | `useState<Estado>` + `fetch` API; sem Inertia partial reload |

## Referências

- ADR 0153 — Module grade rubric v1
- ADR 0154 — Module grade v2 N/A justificado
- ADR 0155 — Module grade v3 sub-dimensões + N/A backward-compat (D6.a Inertia::defer)
