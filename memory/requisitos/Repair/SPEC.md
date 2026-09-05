---
id: requisitos-repair-spec
module: Repair
owner: wagner
version: "1.0"
last_updated: "2026-06-13"
---

# Especificação funcional

## 3. User stories

> Convenção do ID: `US-REPA-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

_[TODO — escrever user stories no formato abaixo.]_

### US-REPA-001 · [TODO — título]

**Como** [papel]  
**Quero** [ação]  
**Para** [objetivo de negócio]

**Implementado em:** _pendente_ — US-REPA-001 não escrita (placeholder TODO)

**Definition of Done:**
- [ ] [critério]

### US-REPA-002 · 3 testes do Wave18 quebram com `base_path()` fora do bootstrap do app

> owner: — · priority: p2 · status: todo · type: story
> blocked_by: —

**Implementado em:** _pendente_ — o defeito está diagnosticado; o conserto exige rodar no CT 100.

Achado da sessão 2026-08-05, ao diagnosticar a falha `Pest Repair` no [PR #5327](https://github.com/wagnerra23/oimpresso.com/pull/5327) — que **não tocou** `Modules/Repair/` (zero diff, medido).

**Sintoma:** 3 testes de `Modules/Repair/Tests/Feature/Wave18RepairSaturationTest.php` falham com `Call to undefined method Illuminate\Container\Container::basePath()`, em `vendor/laravel/framework/.../Foundation/helpers.php:206` (`base_path()` → `app()->basePath()`). Linhas do teste: **19** e **47**.

Afetados: `D2 Code Quality FormRequests → StartFsmActionRequest existe e tem rules` · `D7 retention canonica → Config/retention.php declara repair_job_sheets` · `D7 retention canonica → default enabled=false (gate manual ADR 0105)`.

**Hipótese (NÃO medida — exige CT 100):** o `app()` resolvido é o Container puro, sem `basePath()` — o grupo não está associado ao `TestCase` do Laravel (falta `uses(TestCase::class)`, ou o arquivo caiu fora do escopo do `uses()` do `Pest.php`). Os arquivos procurados **existem** (`Config/retention.php` declara `enabled`, `tabelas`, `repair_job_sheets => 1825`), logo não é ausência de arquivo: o helper falha **antes** de checar.

**Por que ninguém viu:** o job vem do `modules-pest.yml`, que é **matrix** — roda Arquivos, ComunicacaoVisual, Fiscal, NfeBrasil, Repair e Vestuario juntos sempre que **qualquer** path da lista muda. O #5327 tocou `Modules/NfeBrasil/Resources/lang/` e acordou a lane do Repair. E `Pest Repair` **não** está no `governance/required-checks-baseline.json` — é advisory, então o vermelho não bloqueia merge e passa despercebido. Mesma família da lápide §5 2026-07-28 em [`proibicoes.md`](../../proibicoes.md): *"defeito em teste que não roda é invisível até a lane ligar"*.

**Recibo:** [run 31040822015](https://github.com/wagnerra23/oimpresso.com/actions/runs/31040822015) — `Tests: 3 failed, 65 skipped, 80 passed (274 assertions)`.

**Definition of Done:**
- [ ] Os 3 voltam a verde **no CT 100**, com a causa escrita — e por conserto, não por `skip`.
- [ ] Se a correção for `uses(TestCase::class)`, conferir se outros describes do mesmo arquivo passavam **por acidente** dependendo do bootstrap ausente.

### US-REPA-003 · Configurar os padrões da folha de OS e o que sai impresso

> owner: — · priority: p2 · status: doing · type: story
> blocked_by: —

**Como** admin do negócio
**Quero** definir num lugar só os padrões que toda nova folha de OS assume e o que sai impresso na folha e na etiqueta
**Para** não repetir digitação a cada OS nem depender de quem lembra o padrão da casa

**Implementado em:** `resources/js/Pages/Repair/Settings/Index.tsx` · `Modules/Repair/Http/Controllers/RepairSettingsController.php` · `Modules/Repair/Tests/Feature/RepairSettingsContratoTest.php` · verificado@a2a5cb4 (2026-09-05)

**Testado em:** `Modules/Repair/Tests/Feature/RepairSettingsContratoTest.php` (declara `@covers-us US-REPA-003`) — lane **Verticais · Pest (MySQL)**, allowlist. Veredito medido em 2026-09-05: 6 `pass` (17 assertions) · 2 `skip` (UC-RSET-07/08, por `system.repair_version` ausente no seed).

Escopo derivado do F1 PLAN em [`RUNBOOK-repair-settings.md`](RUNBOOK-repair-settings.md) (Onda 1 do export do Repair, autorizado por [W] em 2026-09-04) — **não inventado aqui**. A US registra o escopo que já foi decidido; o contrato executável vive em [`Index.casos.md`](../../../resources/js/Pages/Repair/Settings/Index.casos.md) (UC-RSET-01..08).

**Recorte:** cobre **2 das 5 abas** do hub Blade legado — `repair_settings_tab` (padrões da folha) e `jobsheet_settings_tab` (impressão/etiqueta). As abas de **Status de OS** e **Modelos de dispositivo** já têm Page própria e viva; esta tela **aponta** para elas em vez de reimplementar. A taxonomia de dispositivos fica para onda própria.

**Contrato que a tela não pode quebrar:** são **dois endpoints com colunas disjuntas** — `store()` grava `business.repair_settings`, `updateJobsheetSettings()` grava `business.repair_jobsheet_settings` — e ambos fazem `$request->only()` + `json_encode()`, isto é, **substituem o JSON inteiro**: chave ausente no POST some do banco. Daí os dois `<form>` separados, cada um enviando o conjunto completo do seu endpoint.

**Definition of Done:**
- [x] Contrato de gravação provado por Pest em MySQL real, tenant 98 — 6 `pass` (17 assertions) na lane `verticais-pest`, medido em 2026-09-05.
- [x] `casos.md` com UC citado por teste e Status derivado do manifesto (gate G-7).
- [ ] Smoke autenticado, dark, 1280px, com screenshot — pendente por ambiente (ver charter §Pendências).
- [ ] Flag `MWART_REPAIR_SETTINGS_INDEX` ligada por [W] após o smoke (F5 CUTOVER).
- [ ] Header migrado para o canon `@/Components/PageHeader` (ADR 0189/0190) — hoje a tela usa `shared/PageHeader`, como as 6 Pages irmãs do módulo.

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-REPA-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Repair
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** _lacuna — Modules/Repair/Tests/Feature/PermissionsTest não existe (stub pendente; reconciliação 2026-07-01, cobertura a criar)_

### R-REPA-002 · Autorização Spatie `repair.create`

```gherkin
Dado que um usuário **não** tem a permissão `repair.create`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair.create')`  
**Testado em:** _lacuna — Modules/Repair/Tests/Feature/PermissionsTest não existe (stub pendente; reconciliação 2026-07-01, cobertura a criar)_

### R-REPA-003 · Autorização Spatie `repair.update`

```gherkin
Dado que um usuário **não** tem a permissão `repair.update`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair.update')`  
**Testado em:** _lacuna — Modules/Repair/Tests/Feature/PermissionsTest não existe (stub pendente; reconciliação 2026-07-01, cobertura a criar)_

### R-REPA-004 · Autorização Spatie `repair.view`

```gherkin
Dado que um usuário **não** tem a permissão `repair.view`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair.view')`  
**Testado em:** _lacuna — Modules/Repair/Tests/Feature/PermissionsTest não existe (stub pendente; reconciliação 2026-07-01, cobertura a criar)_

### R-REPA-005 · Autorização Spatie `repair.view_own`

```gherkin
Dado que um usuário **não** tem a permissão `repair.view_own`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair.view_own')`  
**Testado em:** _lacuna — Modules/Repair/Tests/Feature/PermissionsTest não existe (stub pendente; reconciliação 2026-07-01, cobertura a criar)_

### R-REPA-006 · Autorização Spatie `repair.delete`

```gherkin
Dado que um usuário **não** tem a permissão `repair.delete`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair.delete')`  
**Testado em:** _lacuna — Modules/Repair/Tests/Feature/PermissionsTest não existe (stub pendente; reconciliação 2026-07-01, cobertura a criar)_

### R-REPA-007 · Autorização Spatie `repair_status.update`

```gherkin
Dado que um usuário **não** tem a permissão `repair_status.update`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair_status.update')`  
**Testado em:** _lacuna — Modules/Repair/Tests/Feature/PermissionsTest não existe (stub pendente; reconciliação 2026-07-01, cobertura a criar)_

### R-REPA-008 · Throttle endpoint público `/repair-status` (Wave 3 D8.a)

```gherkin
Dado que `/repair-status` é endpoint PÚBLICO (sem auth) que recebe número OS + telefone últimos dígitos
Quando um IP faz mais de N requests/minuto
Então recebe 429 Too Many Requests + log estruturado `repair.public_status.checked`
```

**Implementação proposta (backlog Wave 4):** middleware `throttle:30,1` no grupo top-level que envolve `Route::get('/repair-status', ...)` e `Route::post('/post-repair-status', ...)` em [Modules/Repair/Routes/web.php](../../../Modules/Repair/Routes/web.php) linhas 3-4. Hoje SEM throttle explícito — apenas throttle global Laravel via `RouteServiceProvider` (60 req/min padrão).
**Risco:** scraping massivo de OS expõe pattern de numeração + telefone redact incompleto.
**Ver:** [PII-LGPD.md §"Pontos críticos"](PII-LGPD.md), [OBSERVABILITY.md §"repair_public_status_abuse"](OBSERVABILITY.md).
**Testado em:** _(pendente — Pest test 31 requests retorna 429)_

---

## 5. Notas técnicas Wave 3 v3 booster (2026-05-16)

### D6.a · Inertia::defer já adotado em JobSheetController

Auditoria confirma `JobSheetController` já usa `Inertia::defer()` em props pesadas — refactor adicional desnecessário:

| Action | Linha | Defer aplicado em |
|---|---|---|
| `create()` | 376 | `options` (statuses + devices + brands + technicians + customers + groups) |
| `show()` | 550-552 | `parts`, `activities`, `anexos` |
| `edit()` | 706 | `options` (via `buildJobSheetEditOptions`) |

Pattern alinhado com skill `inertia-defer-default` (Tier B) e [RUNBOOK-inertia-defer-pattern.md](../_DesignSystem/RUNBOOK-inertia-defer-pattern.md). Confirmado por Wave L L2 + auditoria Wave 3.

### D7.a · PiiRedactor herdado (sem código próprio)

Repair NÃO duplica `PiiRedactor` — herda do core (`App\Services\PiiRedactor`). Detalhe em [PII-LGPD.md §2](PII-LGPD.md).

### D7.b · LogsActivity trait em JobSheet

[Entities/JobSheet.php](../../../Modules/Repair/Entities/JobSheet.php) recebeu trait `LogsActivity` Spatie + método `getActivitylogOptions()` listando 8 campos críticos (`status_id`, `service_staff`, `device_id`, `brand_id`, `device_model_id`, `defects`, `completed_on`, `current_stage_id`), com `logOnlyDirty()` + `dontSubmitEmptyLogs()` + `useLogName('repair_job_sheet')`.

Complementa, NÃO substitui, `sale_stage_history` FSM ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)).

### D9.a · OTel herdado

Repair NÃO emite traces/metrics próprios. Herda OTel auto-instrumentação + logs estruturados + health checks do core. Detalhe completo em [OBSERVABILITY.md §"Herança Core"](OBSERVABILITY.md).
