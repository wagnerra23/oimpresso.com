---
id: requisitos-essentials-spec
slug: modules-essentials-spec
title: "Modules/Essentials — SPEC"
type: spec
module: Essentials
owner: wagner
version: "1.0"
last_updated: "2026-06-13"
status: rascunho
authority: canonical
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0153-module-grade-rubrica-v1
  - 0154-module-grade-v2-na-justificado
  - 0155-module-grade-v3-sub-dimensoes-gate-ci
  - 0156-module-grade-v3-errata-otel-helper-na-justified
na_justified:
  D5: "Utilitários backend compartilhados HRM/legado UltimatePOS — sem cliente direto Larissa/biz=4. Módulo transversal cross-business (helpers, traits, mailables compartilhados) seguindo proibições Tier 0 [memory/proibicoes.md](../../proibicoes.md) §Multi-tenant + Constituição v2 [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)."
  D4.b: "Essentials é utilitário compartilhado backend HRM/Todo herdado UltimatePOS v6 — usa enum status simples em `essentials_leaves.status` (pending/approved/rejected) e `essentials_to_dos.status` (sem máquina de estados canônica FSM). Pipeline FSM canônico ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) aplica a Sells/Repair (vendas + OS), não a HRM legacy. Migração futura possível via ADR de feature wish ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) §cliente sinal qualificado: dormente sem dor real)."
pii: false
updated_at: 2026-05-16
---

# Especificação funcional

## 3. User stories

> Convenção do ID: `US-ESSE-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

_[TODO — escrever user stories no formato abaixo.]_

### US-ESSE-001 · [TODO — título]

**Como** [papel]  
**Quero** [ação]  
**Para** [objetivo de negócio]

**Implementado em:** _pendente_ — US TODO, tela não construída

**Definition of Done:**
- [ ] [critério]

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-ESSE-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Essentials
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** _lacuna — Modules/Essentials/Tests/Feature/PermissionsTest não existe (stub pendente; reconciliação 2026-07-01, cobertura a criar)_

### R-ESSE-002 · Autorização Spatie `essentials.crud_leave_type`

```gherkin
Dado que um usuário **não** tem a permissão `essentials.crud_leave_type`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('essentials.crud_leave_type')`  
**Testado em:** _lacuna — Modules/Essentials/Tests/Feature/PermissionsTest não existe (stub pendente; reconciliação 2026-07-01, cobertura a criar)_

### R-ESSE-003 · Autorização Spatie `essentials.crud_all_leave`

```gherkin
Dado que um usuário **não** tem a permissão `essentials.crud_all_leave`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('essentials.crud_all_leave')`  
**Testado em:** _lacuna — Modules/Essentials/Tests/Feature/PermissionsTest não existe (stub pendente; reconciliação 2026-07-01, cobertura a criar)_

### R-ESSE-004 · Autorização Spatie `essentials.crud_own_leave`

```gherkin
Dado que um usuário **não** tem a permissão `essentials.crud_own_leave`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('essentials.crud_own_leave')`  
**Testado em:** _lacuna — Modules/Essentials/Tests/Feature/PermissionsTest não existe (stub pendente; reconciliação 2026-07-01, cobertura a criar)_

### R-ESSE-005 · Autorização Spatie `essentials.approve_leave`

```gherkin
Dado que um usuário **não** tem a permissão `essentials.approve_leave`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('essentials.approve_leave')`  
**Testado em:** _lacuna — Modules/Essentials/Tests/Feature/PermissionsTest não existe (stub pendente; reconciliação 2026-07-01, cobertura a criar)_

### R-ESSE-006 · Autorização Spatie `essentials.crud_all_attendance`

```gherkin
Dado que um usuário **não** tem a permissão `essentials.crud_all_attendance`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('essentials.crud_all_attendance')`  
**Testado em:** _lacuna — Modules/Essentials/Tests/Feature/PermissionsTest não existe (stub pendente; reconciliação 2026-07-01, cobertura a criar)_

### R-ESSE-007 · Autorização Spatie `essentials.view_own_attendance`

```gherkin
Dado que um usuário **não** tem a permissão `essentials.view_own_attendance`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('essentials.view_own_attendance')`  
**Testado em:** _lacuna — Modules/Essentials/Tests/Feature/PermissionsTest não existe (stub pendente; reconciliação 2026-07-01, cobertura a criar)_

---

## User Stories canônicas (US-ESS-NNN)

> Adicionadas Wave Massive 2026-05-16 — formaliza escopo do módulo Essentials (base UltimatePOS HRM + Todo + Project legacy) em US numeradas pra rastreio em `tasks-list module:Essentials` e cobertura de testes.

### US-ESS-001 · Listar tarefas (Todo) por business

**Como** colaborador autenticado em business X
**Quero** acessar `/essentials/todo` e ver apenas tarefas do meu business
**Para** isolar dados multi-tenant

**Aceitação:** Controller filtra `where('business_id', auth()->user()->business_id)`. Paginator Laravel padrão. Teste: `MultiTenantTodoTest::Todo biz=1 não aparece em listagem scoped biz=99`.

### US-ESS-002 · Criar tarefa Todo

**Como** usuário com permissão `essentials.add_todos`
**Quero** criar tarefa com task/date/priority/status
**Para** acompanhar trabalho pessoal e atribuído

**Aceitação:** `POST /essentials/todo` valida `task` + `date` obrigatórios. Persiste com `business_id` da session. `task_id` único auto-gerado. Teste: `TodoTest::store_cria_tarefa_e_redireciona_para_show`.

### US-ESS-003 · Editar tarefa (status/priority)

**Como** owner ou assignee de tarefa do meu business
**Quero** PATCH em `/essentials/todo/{id}` mudando status
**Para** marcar progress (new → in_progress → completed)

**Aceitação:** Update escopado por business_id. Tarefa de outro business = 404. Teste: `MultiTenantTodoTest::Todo biz=1 NÃO pode ser editada via update scoped biz=99`.

### US-ESS-004 · Deletar tarefa

**Como** owner de tarefa com permissão `essentials.delete_todos`
**Quero** DELETE em `/essentials/todo/{id}` no meu business
**Para** remover tarefas concluídas/erradas

**Aceitação:** Delete escopado. Cross-tenant retorna 0 affected rows. Teste: `MultiTenantTodoTest::Todo biz=1 NÃO pode ser deletada via destroy scoped biz=99`.

### US-ESS-005 · Solicitação de Leave (ausência)

**Como** colaborador
**Quero** criar `EssentialsLeave` (atestado/férias/folga) com start_date/end_date/reason
**Para** registrar afastamento formal

**Aceitação:** Persistência scoped business_id. Status default `pending`. Workflow approve/reject via `changed_by`. Teste: `MultiTenantLeaveTest::Leave biz=1 não aparece em listagem scoped biz=99`.

### US-ESS-006 · Aprovar/Rejeitar Leave

**Como** gestor com permissão `essentials.crud_all_leave`
**Quero** mudar `status` de leave (pending → approved/rejected)
**Para** controlar ausências

**Aceitação:** Update escopado. Audit via Spatie ActivityLog. Cross-tenant bloqueado. Teste: `MultiTenantLeaveTest::Leave biz=1 NÃO pode ter status mudado via update scoped biz=99`.

### US-ESS-007 · Compartilhar documentos via DocumentShare

**Como** owner de documento
**Quero** registrar `DocumentShare` linkando documento a usuários
**Para** controlar quem pode baixar/visualizar

**Aceitação:** Resource `document-share` (edit/update). Owner business == document business_id. Bloqueio implícito de share cross-tenant.

### US-ESS-008 · Calendário Reminder

**Como** usuário do meu business
**Quero** CRUD em `/essentials/reminder`
**Para** lembrar de tarefas/eventos

**Aceitação:** Resource padrão com scope business_id. Teste smoke em `SmokeRoutesEssentialsTest::rota /essentials/reminder (resource) está registrada`.

### US-ESS-009 · Module install/uninstall por business

**Como** admin do business
**Quero** ativar/desativar Essentials via `/essentials/install`
**Para** opt-in da capacidade

**Aceitação:** Rotas `install`, `install/update`, `install/uninstall` registradas. Teste: `ScaffoldEssentialsTest::módulo Essentials está registrado no nWidart` + `SmokeRoutesEssentialsTest::rota /essentials/install/uninstall está registrada`.

### US-ESS-010 · Isolamento multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

**Como** plataforma multi-tenant
**Quero** que TODA query em Todo/Leave/Document/Reminder filtre por business_id
**Para** garantir zero vazamento de dados entre clientes (princípio Tier 0)

**Aceitação:** Testes Pest biz=1 vs biz=99 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)) cobrem list/show/edit/delete/complete. Falha em qualquer cenário cross-tenant = incident. Suite: `MultiTenantTodoTest` + `MultiTenantLeaveTest`.

### US-ESS-011 · Metas de venda por colaborador (tela Inertia)

**Implementado em:** `Modules/Essentials/Http/Controllers/SalesTargetController.php` (`index`, `paginarColaboradores`) · `resources/js/Pages/Essentials/Metas.tsx` (+ `Metas.charter.md`, `Metas.casos.md`) · rota `/hrm/sales-target` em `Modules/Essentials/Routes/web.php` · `Modules/Essentials/Tests/Feature/HrmMetasTest.php` · verificado@16fec05f7d (2026-09-05)

**Como** admin do business
**Quero** ver numa tela só quem tem meta de venda cadastrada e qual faixa paga qual comissão, e editar essas faixas
**Para** não precisar abrir um colaborador por vez no modal (a tela Blade mostrava só nome + botão)

**Regras:**

- A tela **não calcula** comissão. As faixas são exibidas como estão em `essentials_user_sales_targets`; ao salvar, voltam como texto pt-BR de 2 casas (`formatDecimalPtBR`) — a mesma forma do `@num_format` do Blade. `Util::num_uf` e `SalesTargetFaixaValidator` seguem sendo os únicos a interpretar e validar.
- A **apuração do realizado** (vendido no mês, faixa atingida, comissão em dinheiro) fica **fora** desta US: o produtor é `DashboardController::getUserSalesTargets`, admin-only e DataTables. É caminho de valor e exige US própria com a dupla prova da regra mestre.
- O ramo `request()->ajax()` do `index` permanece enquanto `sales_targets/index.blade.php` existir (sai na HRM-O8).

**Testado em:** `Modules/Essentials/Tests/Feature/HrmMetasTest.php`

**DoD:** os 7 UCs de `Metas.casos.md` verdes na lane `essentials-pest` (MySQL real, tenant 98) — render Inertia com as props, faixas exibidas como estão no banco, colaborador sem faixa como ausência (não zero fabricado), busca server-side sem vazar outro tenant, equivalência **medida** entre texto pt-BR e número cru na gravação, leitura do `num_uf` com controle negativo que pode ficar vermelho, e o ramo DataTables da Blade legada preservado. Nenhum valor gravado muda: `saveSalesTarget`, `montarFaixas`, `SalesTargetFaixaValidator` e `num_uf` ficam intactos.

---

## Cobertura de testes Pest (2026-05-16 Wave Massive)

| Arquivo | Casos | Pré-req |
|---|---|---|
| `TodoTest.php` (existente) | 10 | MySQL UltimatePOS + business + admin user |
| `MultiTenantTodoTest.php` | 6 | MySQL UltimatePOS — pula em SQLite |
| `MultiTenantLeaveTest.php` | 5 | MySQL UltimatePOS — pula em SQLite |
| `SmokeRoutesEssentialsTest.php` | 11 | Sem banco — só Route container |
| `ScaffoldEssentialsTest.php` | 6 | Sem banco — Module::find + autoload |

**Total:** 38 casos cobrindo R-ESSE-001..007 + US-ESS-001..010.
