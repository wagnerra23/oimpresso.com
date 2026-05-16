# BRIEFING — Modules/Essentials

> Estado consolidado 1-pager · canon vivo (atualizado a cada PR mergeado que toque o módulo via skill `brief-update`) · Wave Massive 2026-05-16

## O que é

**Modules/Essentials** é o módulo base UltimatePOS herdado, oferecendo capacidades horizontais HRM (Human Resource Management) + Todo + Project legacy + Calendar Reminder + Document Sharing pro núcleo oimpresso. **Em uso ativo por biz=4 (ROTA LIVRE)** como base operacional pra controle de tarefas, ausências e documentos compartilhados.

Origem: módulo opcional UltimatePOS v6 — preservado intencionalmente como **shared infrastructure** entre verticais (Vestuario, ComunicacaoVisual, OficinaAuto). Não é foco de competitividade vs mercado; é fundação que outros módulos vertical-específicos consomem.

## Capacidades (alto nível)

| Capacidade | Status | Notas |
|---|---|---|
| Todo (gestão de tarefas) | ✅ em prod | Status (new/in_progress/on_hold/completed) + priority (low/medium/high/urgent) + assignees + comments + media |
| Leave Request (ausências) | ✅ em prod | Workflow pending → approved/rejected, ActivityLog Spatie |
| Documents + Share | ✅ em prod | Upload + share por usuário |
| Reminder | ✅ em prod | CRUD calendário pessoal |
| Attendance (ponto) | 🟡 legacy | Coexiste com Modules/PontoWr2 canônico (Portaria 671/2021) — em PontoWr2 fica trabalho novo |
| HRM (allowance/deduction/shift/payroll) | 🟡 legacy | Usado biz=4 minimal; backlog ADR feature-wish para evolução |
| Sales Target | 🟡 legacy | Pouco uso; possível candidato a desativar |
| Holiday | ✅ em prod | Gestão de feriados por business |
| KnowledgeBase | 🟡 legacy | Sobreposição com Modules/Kb candidato a migração |
| Message | 🟡 legacy | Mensagens internas — sobreposição com Modules/Jana ConvSidePanel |

## Arquitetura

- **19 Controllers** em `Modules/Essentials/Http/Controllers/`
- **18 Entities** em `Modules/Essentials/Entities/` — Models **sem global scope** (legado UltimatePOS); isolamento via `where('business_id', auth user business_id)` no Controller
- **2 arquivos rota** em `Modules/Essentials/Routes/` (web.php, api.php) — ⚠️ ainda usa strings legacy `'ToDoController'` em alguns lugares (technical debt; ver [rules/routes.md](../../../.claude/rules/routes.md))
- **Stack middleware UltimatePOS canônico:** `['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu']`
- **Tables:** `essentials_to_dos`, `essentials_leaves`, `essentials_documents`, `essentials_document_shares`, `essentials_reminders`, `essentials_attendances`, `essentials_messages`, `essentials_user_shifts`, `essentials_holidays`, `essentials_payroll_groups`, `essentials_user_sales_targets`, `essentials_knowledge_bases` etc — todas com `business_id` indexado

## Risco multi-tenant Tier 0

**Models sem global scope** = isolamento depende inteiramente do Controller filtrar `business_id` corretamente. Wave Massive 2026-05-16 adicionou suíte Pest cobrindo 5 cenários (list/show/edit/delete/complete) pra Todo + Leave, garantindo regressão = CI quebra.

**Refs:** [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) (Tier 0 IRREVOGÁVEL) · [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) (tests biz=1, nunca biz=4)

## Cobertura de testes (2026-05-16)

| Suite | Casos | Cobertura |
|---|---|---|
| `TodoTest.php` | 10 | CRUD Todo + comments + shared docs |
| `MultiTenantTodoTest.php` 🆕 | 6 | Isolamento biz=1 vs biz=99 (5 cenários + smoke) |
| `MultiTenantLeaveTest.php` 🆕 | 5 | Isolamento leave_request (list/show/edit/delete + smoke) |
| `SmokeRoutesEssentialsTest.php` 🆕 | 11 | Rotas registradas + auth required |
| `ScaffoldEssentialsTest.php` 🆕 | 6 | Module::find + module.json + ServiceProvider |

**Total: 38 casos** (era 10; +280% pós-Wave Massive).

## Nota CAPTERRA atual

**35/100 (Crítico)** pre-Wave Massive. Gap detectado:

| Dimensão | Atual | Target | Gap principal |
|---|---|---|---|
| D1 Multi-tenant | 5/30 | 25/30 | Testes Pest biz vs biz faltavam → ✅ resolvido nesta Wave (MultiTenant{Todo,Leave}Test) |
| D2 Governança | 5/20 | 15/20 | SPEC sem US-NNN → ✅ adicionado US-ESS-001..010 |
| D3 Charter pages | 5/15 | 10/15 | Nenhuma `.charter.md` (pre-MWART) |
| D4 Smoke routes | 5/20 | 18/20 | ✅ resolvido (SmokeRoutesEssentialsTest 11 casos) |

**Estimativa pós-Wave (sob Wagner review):** 35 → 70+ (Bom).

## Próximos passos (sugestões — não tasks ainda)

1. Backfill SPEC.md com R-ESSE-008..020 cobrindo `attendance`, `shift`, `payroll`, `holiday`, `reminder` (similar ao formato R-ESSE-001..007)
2. Charter `.charter.md` em telas Todo/Leave quando migrarem pra Inertia/React (skill `mwart-process`)
3. Auditar duplicação Message vs Jana ConvSidePanel — possível candidato a deprecation
4. Refatorar `Modules/Essentials/Routes/web.php` linhas 23, 16, 17, 20 (strings legacy `'ToDoController'`) pra FQCN (lição PR #843 — rule [routes.md](../../../.claude/rules/routes.md))

## Cliente piloto

`biz=4` ROTA LIVRE — usa Todo, Leave, Reminder, Documents diariamente. NUNCA usar biz=4 em testes ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)). Suite Pest sempre biz=1 (Wagner) + biz=99 (fictício).

## Refs

- [SPEC.md](SPEC.md) — User Stories + R-ESSE-NNN
- [ARCHITECTURE.md](ARCHITECTURE.md), [GLOSSARY.md](GLOSSARY.md), [RUNBOOK.md](RUNBOOK.md)
- [audits/](audits/) — auditorias históricas
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0
- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) Tests biz=1
- [ADR 0011](../../decisions/0011-alinhamento-padrao-jana.md) Padrão módulos referência
