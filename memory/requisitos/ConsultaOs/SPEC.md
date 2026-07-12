---
module: ConsultaOs
version: "1.0"
last_updated: "2026-07-02"
anchor_format: "v1"
status: rascunho
owners: [W]
related_adrs: [0153-module-grade-rubrica-v1, 0154-module-grade-v2-na-justificado, 0155-module-grade-v3-sub-dimensoes-gate-ci]
na_justified:
  D3.b: "ConsultaOs é módulo público de consulta OS (cliente final consulta status via número) — mock-only hoje. Migrar pra real (US-CONSULTA-001) está em backlog. BRIEFING.md prematuro até migração TODO."
  D6.a: "Portal público client-side state + fetch JSON API (não usa Inertia partial reload). Única `Inertia::render('ConsultaOs/Index')` retorna view sem props (página gerencia estado via React useState; busca via fetch('/consulta-os/buscar') retornando JsonResponse). Sem props paginadas/count()/with() eager — `Inertia::defer` inaplicável (análogo ao Connector REST API backend citado em ADR 0155 §188). Reavaliar após US-CONSULTA-001 (integrar Repair via Service read-only) caso payload passe a vir via Inertia props."
---

<!-- schema-allowlist: US sob "## Roadmap (TODO migrar pra real)"; módulo mock-only/stub sem backlog ativo — as US-CONSULTA-NNN são itens de roadmap (migração futura pra real), não há "## User stories"/"## Backlog ativo" porque o módulo ainda não entrega valor de produto. Heading preservado pra não reestruturar o corpo. -->

# SPEC — Modules/ConsultaOs

## Visão

Módulo público (sem auth) para cliente final consultar o status de uma Ordem de Servico (OS) via número de protocolo + telefone/CPF. Hoje opera em **modo mock-only**: 3 Controllers retornam payload fake pra validar layout/UX antes de cabear no Repair real.

## Arquitetura atual (mock-only)

- 3 Controllers (`ConsultaController`, `StatusController`, `LookupController` — mock payload)
- Sem entidades Eloquent próprias (não toca DB ainda)
- 3 tests Pest cobertura básica (Wave B 2026-05-12 — smoke render + 404 + lookup-by-protocol)
- Sem `business_id` scope (consulta pública por protocolo único globally)

## Roadmap (TODO migrar pra real)

### US-CONSULTA-001 · Substituir mock por query real em Modules/Repair `pendente`

Substituir mock por query real em `Modules/Repair` via Service read-only, com rate limit por IP + captcha.

**Implementado em:** _pendente_ — portal opera mock-only (`ConsultaOsMockService` + `MockConsultaOsRepository` bind no Provider); a query real em `Modules/Repair` + captcha é backlog. Rate-limit já existe parcialmente (`throttle:30,1` nas rotas), mas a substituição da fonte de dados (mock→real) — o cerne desta US — não foi construída.

### US-CONSULTA-002 · Canary 7d em ROTA LIVRE `pendente`

Canary 7d em ROTA LIVRE antes de outros tenants.

**Implementado em:** _pendente_ — rollout operacional que só faz sentido após US-CONSULTA-001 entregar a busca real; nenhum código correspondente hoje.

### US-CONSULTA-003 · Criar BRIEFING.md após migração real `pendente`

Criar `BRIEFING.md` após migração real (`na_justified D3.b` cai).

**Implementado em:** _pendente_ — a precondição (migração real US-CONSULTA-001) não ocorreu; o `na_justified.D3.b` segue vigente até a busca real entregar valor de produto. (Existe um `BRIEFING.md` de estado mock no módulo, mas esta US pede o briefing pós-migração-real, ainda não cabível.)

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
