---
slug: modules-accounting-spec
title: "Modules/Accounting — SPEC"
type: spec
module: Accounting
owner: wagner
version: "1.0"
last_updated: "2026-05-16"
status: arquivado
authority: canonical
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0153-module-grade-rubrica-v1
  - 0154-module-grade-v2-na-justificado
  - 0156-module-grade-v3-errata-otel-helper-na-justified
na_justified:
  D5: "Módulo herdado UltimatePOS — 12 Controllers Blade legacy compartilhados cross-business sem cliente alvo isolado (Budget/Journal/COA genéricos contabilidade). Sem onboarding Larissa/ROTA LIVRE direto, ferramenta backoffice transversal ([memory/proibicoes.md](../../proibicoes.md) §Multi-tenant Tier 0)."
  D6.a: "Stack legacy UltimatePOS — 12 Controllers Blade puros, ZERO Inertia::render por design. Migração MWART [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) ainda não iniciada (sem cliente sinal qualificado — [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)). Inertia::defer N/A enquanto Blade."
  D1.b: "Accounting tem 70 Entities (50 duplicatas de classes core UltimatePOS App\\<X> + 13 reference data global + 7 com business_id direto cobertas Wave 12/13/15/17). Formula naive D1.b exige crossTenantFiles >= 35 (=entCount*0.5) → matematicamente insaturável sem inflar artificialmente. Auditável real: 6+ cross-tenant Pest files (MultiTenantIsolationTest, EntityBusinessIdConsistencyTest, HasBusinessScopeAdoptionTest, MultiTenantTraitDeclarationTest, JournalEntryServiceTest, SmokeRoutesTest) cobrindo Account, ChartOfAccount, Budget, BranchCapital, JournalEntry (JOIN business_locations), AccountTransaction/Transfer (JOIN parent.business_id), 13 Entities Wave 13 trait HasBusinessScope adoption. Defesa-em-profundidade Tier 0 ADR 0093 cumprida — N/A justificado preserva integridade do indicador."
pii: false
updated_at: 2026-05-16
---

# Especificação funcional

## 3. User stories

> Convenção do ID: `US-ACCO-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

### US-ACCO-001 · Listar Budget

> **Área:** Budget  
> **Rota:** `GET /`  
> **Controller/ação:** `BudgetController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Budget  
**Para** ter visão geral e filtrar o que importa

**Implementado em:** _pendente_ — tela não construída (módulo Blade legacy, ZERO Inertia; migração MWART não iniciada)

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-002 · Listar Chart Of Account

> **Área:** Chart Of Account  
> **Rota:** `GET /`  
> **Controller/ação:** `ChartOfAccountController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Chart Of Account  
**Para** ter visão geral e filtrar o que importa

**Implementado em:** _pendente_ — tela não construída (módulo Blade legacy, ZERO Inertia; migração MWART não iniciada)

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-003 · Criar Chart Of Account

> **Área:** Chart Of Account  
> **Rota:** `POST store`  
> **Controller/ação:** `ChartOfAccountController@store`

**Como** usuário autorizado  
**Quero** criar um novo item em Chart Of Account  
**Para** alimentar o sistema com os dados operacionais

**Implementado em:** _pendente_ — tela não construída (módulo Blade legacy, ZERO Inertia; migração MWART não iniciada)

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-004 · Ver detalhe de Chart Of Account

> **Área:** Chart Of Account  
> **Rota:** `GET {id}/show`  
> **Controller/ação:** `ChartOfAccountController@show`

**Como** usuário com acesso ao item  
**Quero** consultar informação completa de um item específico  
**Para** tomar decisão com base em contexto completo

**Implementado em:** _pendente_ — tela não construída (módulo Blade legacy, ZERO Inertia; migração MWART não iniciada)

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-005 · Listar Core

> **Área:** Core  
> **Rota:** `GET /`  
> **Controller/ação:** `DashboardController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Core  
**Para** ter visão geral e filtrar o que importa

**Implementado em:** _pendente_ — tela não construída (módulo Blade legacy, ZERO Inertia; migração MWART não iniciada)

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-006 · Listar Journal Entry

> **Área:** Journal Entry  
> **Rota:** `GET /`  
> **Controller/ação:** `JournalEntryController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Journal Entry  
**Para** ter visão geral e filtrar o que importa

**Implementado em:** _pendente_ — tela não construída (módulo Blade legacy, ZERO Inertia; migração MWART não iniciada)

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-007 · Criar Journal Entry

> **Área:** Journal Entry  
> **Rota:** `POST store`  
> **Controller/ação:** `JournalEntryController@store`

**Como** usuário autorizado  
**Quero** criar um novo item em Journal Entry  
**Para** alimentar o sistema com os dados operacionais

**Implementado em:** _pendente_ — tela não construída (módulo Blade legacy, ZERO Inertia; migração MWART não iniciada)

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-008 · Ver detalhe de Journal Entry

> **Área:** Journal Entry  
> **Rota:** `GET {id}/show`  
> **Controller/ação:** `JournalEntryController@show`

**Como** usuário com acesso ao item  
**Quero** consultar informação completa de um item específico  
**Para** tomar decisão com base em contexto completo

**Implementado em:** _pendente_ — tela não construída (módulo Blade legacy, ZERO Inertia; migração MWART não iniciada)

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-009 · Listar Reconcile

> **Área:** Reconcile  
> **Rota:** `GET /`  
> **Controller/ação:** `ReconcileController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Reconcile  
**Para** ter visão geral e filtrar o que importa

**Implementado em:** _pendente_ — tela não construída (módulo Blade legacy, ZERO Inertia; migração MWART não iniciada)

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-010 · Listar Report

> **Área:** Report  
> **Rota:** `GET accounting`  
> **Controller/ação:** `ReportController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Report  
**Para** ter visão geral e filtrar o que importa

**Implementado em:** _pendente_ — tela não construída (módulo Blade legacy, ZERO Inertia; migração MWART não iniciada)

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-ACCO-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Accounting
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** _lacuna — teste de permissão não existe no módulo (2026-06-22)_

### R-ACCO-002 · Autorização Spatie `accounting.chart_of_accounts.index`

```gherkin
Dado que um usuário **não** tem a permissão `accounting.chart_of_accounts.index`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('accounting.chart_of_accounts.index')`  
**Testado em:** _lacuna — teste de permissão não existe no módulo (2026-06-22)_

### R-ACCO-003 · Autorização Spatie `accounting.chart_of_accounts.create`

```gherkin
Dado que um usuário **não** tem a permissão `accounting.chart_of_accounts.create`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('accounting.chart_of_accounts.create')`  
**Testado em:** _lacuna — teste de permissão não existe no módulo (2026-06-22)_

### R-ACCO-004 · Autorização Spatie `accounting.chart_of_accounts.edit`

```gherkin
Dado que um usuário **não** tem a permissão `accounting.chart_of_accounts.edit`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('accounting.chart_of_accounts.edit')`  
**Testado em:** _lacuna — teste de permissão não existe no módulo (2026-06-22)_

### R-ACCO-005 · Autorização Spatie `accounting.chart_of_accounts.destroy`

```gherkin
Dado que um usuário **não** tem a permissão `accounting.chart_of_accounts.destroy`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('accounting.chart_of_accounts.destroy')`  
**Testado em:** _lacuna — teste de permissão não existe no módulo (2026-06-22)_

### R-ACCO-006 · Autorização Spatie `accounting.journal_entries.index`

```gherkin
Dado que um usuário **não** tem a permissão `accounting.journal_entries.index`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('accounting.journal_entries.index')`  
**Testado em:** _lacuna — teste de permissão não existe no módulo (2026-06-22)_

### R-ACCO-007 · Autorização Spatie `accounting.journal_entries.create`

```gherkin
Dado que um usuário **não** tem a permissão `accounting.journal_entries.create`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('accounting.journal_entries.create')`  
**Testado em:** _lacuna — teste de permissão não existe no módulo (2026-06-22)_

---

## Deprecação programada — Modules/Accounting (ADR 0172)

> Tasks de deprecação canon (Wagner aprovou 2026-05-20 — [ADR 0172](../../decisions/0172-deprecar-modulo-accounting-fundir-financeiro.md)). Plano completo em [DEPRECATION-PLAN.md](DEPRECATION-PLAN.md). 7 ondas, ~26 semanas corridas, ~18d trabalho ativo. Onda 0 (audit DB) + Onda 1 (ADRs accepted) já concluídas em 2026-05-20. Restam 6 tasks abaixo.

### US-ACCO-011 · DEPRECATION Onda 1.3: Errata BRIEFING Accounting (claims falsos linhas 21+25)

> owner: claude · priority: p1 · estimate: 1h · status: todo · type: story
> blocked_by: —

Atualizar `memory/requisitos/Accounting/BRIEFING.md` pra refletir realidade pós-inspeção forense 2026-05-20.

**Acceptance:**
- [ ] Linha 21 ("espinha dorsal pra Vestuario/NfeBrasil/RecurringBilling") removida ou anotada como FALSA — inspeção confirmou ZERO cross-imports
- [ ] Linha 25 ("JournalEntry gerado automaticamente em vendas/compras pagas") removida ou anotada como FALSA — ZERO Listeners/Observers no módulo
- [ ] Header BRIEFING declara `lifecycle: deprecating` (decreto ADR 0172)
- [ ] Link pra ADR 0172 + DEPRECATION-PLAN.md no topo
- [ ] PR aberto + mergeado

**Ref:** [ADR 0172](../../decisions/0172-deprecar-modulo-accounting-fundir-financeiro.md), [INSPECAO-FORENSE-2026-05-20.md](INSPECAO-FORENSE-2026-05-20.md)

### US-ACCO-012 · DEPRECATION Onda 2: UI freeze (sidebar hide + routes 410 Gone)

> owner: claude · priority: p0 · estimate: 3h · status: todo · type: story
> blocked_by: US-ACCO-011

Esconder Accounting do user-facing UI antes do drop de código.

**Acceptance:**
- [ ] `Modules/Accounting/Http/Controllers/DataController.php` — `modifyAdminMenu()` faz early return (sidebar não renderiza Accounting pra nenhum business)
- [ ] `Modules/Accounting/Http/routes.php` — todas as 82 routes `/accounting/*` retornam HTTP 410 Gone com mensagem "Módulo Accounting deprecated em 2026-05-20 — use /financeiro/* (ADR 0172)"
- [ ] Exceto rotas API que outros módulos chamem (verificar nenhuma existe — inspeção forense já mostrou zero cross-imports)
- [ ] `modules_statuses.json`: Accounting permanece `true` ainda (Onda 5 troca pra false)
- [ ] Pest test: GET `/accounting/chart-of-accounts` retorna 410
- [ ] Pest test: sidebar de admin biz=1 NÃO contém entry Accounting
- [ ] PR + canary deploy + monitor 14 dias antes de Onda 3

**Ref:** [ADR 0172 §Roadmap E3 UI freeze](../../decisions/0172-deprecar-modulo-accounting-fundir-financeiro.md)

### US-ACCO-013 · DEPRECATION Onda 3: Migration accounts_legacy_map → fin_planos_conta (idempotente)

> owner: claude · priority: p1 · estimate: 4h · status: todo · type: story
> blocked_by: US-ACCO-012

Migrar dados úteis das tabelas Accounting pras correspondentes Financeiro, business_id por business_id.

**Acceptance:**
- [ ] Script `Modules/Accounting/Database/Migrations/{ts}_migrate_accounting_data_to_financeiro.php`
- [ ] Maps `chart_of_accounts` (49 rows × N biz) → `fin_planos_conta` skip se já existir (idempotente)
- [ ] Maps `journal_entries` (se houver dados úteis) → `fin_titulos` com origem='migracao_accounting'
- [ ] `accounts_legacy_map` (Financeiro 2026-05-09 — bridge infra já existente) populada pra cada par accounting_id → fin_planos_conta_id
- [ ] Pest test: rerun migration NÃO duplica linhas
- [ ] Pest test: business_id preservado em cada linha
- [ ] Multi-tenant Tier 0: zero leak cross-business no script
- [ ] PR + dry-run em staging + canary biz=1 + monitor 7d

**Ref:** [ADR 0172 §Roadmap E4 Migration paths](../../decisions/0172-deprecar-modulo-accounting-fundir-financeiro.md)

### US-ACCO-014 · DEPRECATION Onda 4: View bridge tabelas (60d rollback window)

> owner: claude · priority: p1 · estimate: 3h · status: todo · type: story
> blocked_by: US-ACCO-013

Substituir tabelas Accounting por VIEWS apontando pra Financeiro, mantendo append-only de queries legadas que possam existir em dashboards externos / clientes Power BI.

**Acceptance:**
- [ ] DROP TABLE `chart_of_accounts` + CREATE VIEW `chart_of_accounts` AS SELECT ... FROM `fin_planos_conta` JOIN `accounts_legacy_map`
- [ ] Idem `journal_entries`, `accounts`, `acc_trans_mappings`, `budget`
- [ ] Backup pre-drop: mysqldump das 5 tabelas pra `/tmp/accounting-pre-bridge-{date}.sql` + S3 (retention 90d)
- [ ] Pest test: SELECT * FROM `chart_of_accounts` WHERE business_id=1 retorna 49 linhas (paridade pré/pós)
- [ ] Monitor 60d sem regressão antes de Onda 5
- [ ] Critério rollback: se cliente reportar dashboard quebrado, drop view + restore mysqldump

**Ref:** [ADR 0172 §Roadmap E5 View bridge](../../decisions/0172-deprecar-modulo-accounting-fundir-financeiro.md)

### US-ACCO-015 · DEPRECATION Onda 5: Drop Modules/Accounting/ + modules_statuses=false

> owner: claude · priority: p0 · estimate: 3h · status: todo · type: story
> blocked_by: US-ACCO-014

Remover código do módulo após 60d de view bridge sem regressão.

**Acceptance:**
- [ ] `git rm -r Modules/Accounting/` (todos Controllers, Services, Entities, Migrations, Views, Routes, Tests, Resources/lang)
- [ ] `modules_statuses.json`: Accounting → false
- [ ] Permissions Spatie `accounting.*` removidas (ou marcadas deprecated)
- [ ] Composer autoload-dump
- [ ] Pest: `php artisan test` passa sem erros (zero referência órfã)
- [ ] Smoke prod: `curl /accounting/chart-of-accounts` → 404 (não 500)
- [ ] `memory/requisitos/Accounting/`: mover pra `memory/requisitos/_deprecated/Accounting/` preservando inspecao + plano + ADRs como histórico
- [ ] PR + canary deploy + monitor 7d
- [ ] CHANGELOG.md entry + post-mortem session log

**Ref:** [ADR 0172 §Roadmap E6 Drop código](../../decisions/0172-deprecar-modulo-accounting-fundir-financeiro.md)

### US-ACCO-016 · DEPRECATION Onda 6: Drop tabelas (90d após Onda 5) — IRREVERSÍVEL

> owner: wagner · priority: p2 · estimate: 2h · status: todo · type: story
> blocked_by: US-ACCO-015

Última fase: DROP VIEW + DROP backup tables após 90d de monitor. **Gate manual Wagner irreversível.**

**Acceptance:**
- [ ] DROP VIEW `chart_of_accounts`, `journal_entries`, `accounts`, `acc_trans_mappings`, `budget`
- [ ] DROP `accounts_legacy_map` (não mais necessário — código gone, dados em fin_*)
- [ ] Confirmação Wagner explícita por chat antes de DROP (ponto sem retorno)
- [ ] Backup final mysqldump preservado em S3 (retention 5 anos LGPD audit)
- [ ] Migration `{ts}_drop_accounting_views_and_legacy_map.php` (rollback NÃO possível — declarar irreversible no header)
- [ ] Pest test: `SHOW TABLES LIKE 'chart_of_accounts'` retorna vazio
- [ ] PR + Wagner aprova explícito (gate manual irreversível)

**Ref:** [ADR 0172 §Roadmap E7 Drop tabelas (irreversível)](../../decisions/0172-deprecar-modulo-accounting-fundir-financeiro.md)
