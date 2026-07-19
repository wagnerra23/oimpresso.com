---
module: Essentials
status: shared-infra
status_nota: "shared infrastructure backend HRM (em prod via biz=4 + cross-business)"
piloto: "N/A (utilitário interno cross-business — Todo/Leave/Documents usados por todos)"
last_review: 2026-05-16
updated_at: "2026-07-18"
owner: W
parent_adr: 0011
related_adrs: [0011-alinhamento-padrao-jana, 0093-multi-tenant-isolation-tier-0, 0101-tests-business-id-1-nunca-cliente, 0153-module-grade-rubrica-v1, 0155-module-grade-v3-sub-dimensoes-gate-ci, 0156-module-grade-v3-errata-otel-helper-na-justified]
nota_atual_v2: "~50-55/100 (injusto — D5 penaliza utilitário compartilhado)"
nota_esperada_v3: "~70-80/100 pós-PR4 na_justified D5 declarado"
na_justified: [D5]
---

# BRIEFING — Modules/Essentials

> Estado consolidado 1-pager · canon vivo (atualizado a cada PR mergeado que toque o módulo via skill `brief-update`) · Wave Massive 2026-05-16 + Wave 5 re-try `na_justified` D5 · refresh frescor 2026-07-18 (hardening XSS + tenant-gate write + charters + defer-guard)

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
| Attendance (ponto) | 🟡 legacy | Coexiste com Modules/Ponto canônico (Portaria 671/2021) — em Ponto fica trabalho novo |
| HRM (allowance/deduction/shift/payroll) | 🟡 legacy | Usado biz=4 minimal; backlog ADR feature-wish para evolução |
| Sales Target | 🟡 legacy | Pouco uso; possível candidato a desativar |
| Holiday | ✅ em prod | Gestão de feriados por business |
| KnowledgeBase | 🟡 legacy | Sobreposição com Modules/KB candidato a migração; `content` sanitizado server-side via `App\Util\HtmlSanitizer::clean` (HTMLPurifier) antes do render — #2895 |
| Message | 🟡 legacy | Mensagens internas — sobreposição com Modules/Jana ConvSidePanel; stored-XSS do chat corrigido (render como texto) — #2891 |

## Mudanças materiais desde 2026-06-13 (refresh 2026-07-18)

Módulo estável — **zero feature nova**; a leva desde jun foi **hardening de segurança + robustez de tela** (verificado no código, não só no log):

- **Tenant-gate em writes com id cru (Tier 0):** `SalesTargetController::saveSalesTarget` (`SalesTargetController.php:109`) e `ShiftController::postAssignUsers` (`ShiftController.php:298,301`) recebiam `user_id`/`shift_id` do body sem validar business → `create`/`updateOrCreate` cross-tenant (o backstop de scope só cobre SELECT, não INSERT). Fix: `User::where('business_id',$biz)->findOrFail(...)` + `abort_unless(uid ∈ users do business, 403)` antes do write (#4514, follow-up #4474). Zero delta de valor/estoque — só barra id cross-tenant.
- **Hardening XSS (série):** KB sanitiza `content` via `HtmlSanitizer::clean` (HTMLPurifier) antes do `dangerouslySetInnerHTML` (#2895); chat de Messages corrige stored-XSS renderizando como texto (#2891); Todo + endereço removem `dSIH` de campos de dado (#2893); regressão coberta em `KnowledgeXssSanitizationTest` + `SecurityHardeningTest` (#2897).
- **10 telas ganharam charter DRAFT** (antes sem contrato — #4137); hoje **13 `.charter.md`** sob `Pages/Essentials/**` (Todo/Knowledge × 4, Documents/Holidays/Messages/Reminders/Settings × 1).
- **Defer-crash guard (5 telas):** Documents/Holidays/Messages/Knowledge/Todo Index desreferenciavam props `Inertia::defer` no first render → tela branca (TypeError). Fix: props opcionais + `?./?? []` + `<Deferred fallback>` (#3867). Perf D-14: partial reload `only:` em Holidays/Todo (#3898).

## Arquitetura

- **19 Controllers** em `Modules/Essentials/Http/Controllers/`
- **18 Entities** em `Modules/Essentials/Entities/` — Models **sem global scope** (legado UltimatePOS); isolamento via `where('business_id', auth user business_id)` no Controller
- **2 arquivos rota** em `Modules/Essentials/Routes/` (web.php, api.php) — ⚠️ ainda usa strings legacy `'ToDoController'` em alguns lugares (technical debt; ver [rules/routes.md](../../../.claude/rules/routes.md))
- **Stack middleware UltimatePOS canônico:** `['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu']`
- **Tables:** `essentials_to_dos`, `essentials_leaves`, `essentials_documents`, `essentials_document_shares`, `essentials_reminders`, `essentials_attendances`, `essentials_messages`, `essentials_user_shifts`, `essentials_holidays`, `essentials_payroll_groups`, `essentials_user_sales_targets`, `essentials_knowledge_bases` etc — todas com `business_id` indexado

## Risco multi-tenant Tier 0

**Models sem global scope canônico** = isolamento depende do Controller filtrar `business_id`. Wave Massive 2026-05-16 adicionou suíte Pest cobrindo 5 cenários (list/show/edit/delete/complete) pra Todo + Leave, garantindo regressão = CI quebra.

**Refino 2026-07-18 (#4514):** parte dos models adotou backstop de scope (cobre **SELECT**), mas `create`/`updateOrCreate` com id vindo do body **não** passam pelo backstop → o buraco de write cross-tenant (SalesTarget/Shift) foi fechado com gate explícito de tenant (`findOrFail` scoped + `abort_unless`). Segue a regra: toda nova rota de write que aceite `*_id` do request precisa do gate — o global-scope não substitui.

**Refs:** [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) (Tier 0 IRREVOGÁVEL) · [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) (tests biz=1, nunca biz=4)

## Cobertura de testes (2026-05-16)

| Suite | Casos | Cobertura |
|---|---|---|
| `TodoTest.php` | 10 | CRUD Todo + comments + shared docs |
| `MultiTenantTodoTest.php` 🆕 | 6 | Isolamento biz=1 vs biz=99 (5 cenários + smoke) |
| `MultiTenantLeaveTest.php` 🆕 | 5 | Isolamento leave_request (list/show/edit/delete + smoke) |
| `SmokeRoutesEssentialsTest.php` 🆕 | 11 | Rotas registradas + auth required |
| `ScaffoldEssentialsTest.php` 🆕 | 6 | Module::find + module.json + ServiceProvider |

**Total: 38 casos** (baseline 2026-05-16; era 10; +280% pós-Wave Massive).

**Refresh 2026-07-18:** a suíte cresceu de 5 → **14 arquivos** verificados em `Modules/Essentials/Tests/Feature/` (total de casos **não recontado** nesta passada). Novos desde: `KnowledgeXssSanitizationTest` + `SecurityHardeningTest` (regressão XSS #2895/#2897), `CrossTenantTodoLeaveTest` + `AutoClockOutMultiTenantTest` + `HasBusinessScopeAdoptionTest` (multi-tenant), `Wave18SaturationTest` + `Wave27PolishTest` + `EssentialsBladeT1InertiaSmokeTest`. O `SalesTargetShiftCrossTenantTest` (#4514) valida o gate de write na `main` — o fix de controller está nesta worktree; o arquivo de teste ainda não.

## Score module-grade (v3 pós-PR4)

| Versão | Score | Observação |
|---|---|---|
| v2 (pré-PR4) | ~50-55/100 | Penalizava D5 (cliente externo) — utilitário shared sem piloto único |
| **v3 (pós-PR4)** | **~70-80/100** (esperado) | `na_justified` D5 declarado no SPEC → rubrica v3 redistribui peso (ADR 0156) + ratio Pest ainda médio |

**`na_justified` declarado no SPEC:**
- **D5 (cliente externo):** utilitário HRM compartilhado backend (Todo/Leave/Documents/Reminder) consumido cross-business sem cliente externo único piloto. biz=4 usa diariamente mas não é "cliente paying Essentials" — paga oimpresso ERP completo.

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
2. ✅ ~~Charter em telas Todo/Leave quando migrarem pra Inertia~~ — feito (13 `.charter.md` DRAFT, #4137). Próximo: sair de DRAFT → aceito com contrato visual + `.casos.md`/teste que cite o UC
3. Auditar duplicação Message vs Jana ConvSidePanel — possível candidato a deprecation (Message já teve stored-XSS fechado #2891)
4. Migrar os `Route::resource` restantes (`web.php:28` `'ToDoController'`, `:94` `'EssentialsHolidayController'`) pra FQCN — o grosso já usa `::class` (42 refs), sobram só esses resources (rule [routes.md](../../../.claude/rules/routes.md))

## Cliente piloto

`biz=4` ROTA LIVRE — usa Todo, Leave, Reminder, Documents diariamente. NUNCA usar biz=4 em testes ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)). Suite Pest sempre biz=1 (Wagner) + biz=99 (fictício).

## Refs

- [SPEC.md](SPEC.md) — User Stories + R-ESSE-NNN
- [ARCHITECTURE.md](ARCHITECTURE.md), [GLOSSARY.md](GLOSSARY.md), [RUNBOOK.md](RUNBOOK.md)
- [audits/](audits/) — auditorias históricas
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0
- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) Tests biz=1
- [ADR 0011](../../decisions/0011-alinhamento-padrao-jana.md) Padrão módulos referência
- [ADR 0155](../../decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md) Rubrica v3 sub-dimensões / `na_justified`
- [ADR 0156](../../decisions/0156-module-grade-v3-errata-otel-helper-na-justified.md) Errata v3 (OTel helper `na_justified`)

---

**Atualizado:** 2026-07-18 — refresh de frescor briefing↔código [CC]. Material desde jun/2026 (verificado no código, sem feature nova): **tenant-gate nos writes com id cru** SalesTarget/Shift (#4514, Tier 0 — INSERT cross-tenant que o global-scope não cobre) + **série de hardening XSS** (KB HTMLPurifier #2895, chat stored-XSS #2891, remove `dSIH` Todo/endereço #2893, regressão #2897) + **10 charters DRAFT** (#4137, hoje 13) + **defer-guard 5 telas** (#3867) + **partial reload D-14** (#3898). Frontmatter normalizado ao schema (`status: shared-infra` + `status_nota`, `owner: W`, `related_adrs` em slugs, `updated_at`).
