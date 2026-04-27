# Modules/Essentials — Cobertura de testes (lote 5)

> Acompanha [`SPEC.md`](./SPEC.md) com a lista canônica de testes Feature.
> Complementa o lote 5 (Grow/BI/Dashboard/Essentials) — branch
> `claude/tests-batch-5-grow-bi-dash-v2`.

---

## Tests Feature em `Modules/Essentials/Tests/Feature/`

| Arquivo                                  | Cobre                                            | Estado     |
|-----------------------------------------|--------------------------------------------------|------------|
| `EssentialsTestCase.php`                | base (sessão, permissões, Inertia version)       | pré-existente |
| `TodoTest.php`                          | `ToDoController` (Inertia)                       | pré-existente |
| `EssentialsControllerTest.php`          | `EssentialsController::index`, `Dashboard` auth, ADR 0024 reflexão | **novo (lote 5)** |
| `EssentialsHolidayControllerTest.php`   | `EssentialsHolidayController::index` (Inertia, filtros, scope) | **novo (lote 5)** |
| `DocumentControllerTest.php`            | `DocumentController` (Inertia, tabs, auth)       | **novo (lote 5)** |
| `AttendanceControllerTest.php`          | `AttendanceController` (auth nas rotas AJAX)     | **novo (lote 5)** |
| `DashboardControllerTest.php`           | `DashboardController` (hrm + essentials + sales targets) | **novo (lote 5)** |

## Padrão a respeitar

1. **Sempre** estender `EssentialsTestCase` em testes novos. Ela já trata:
   - `actAsAdmin()` — marca skipped se Business/User ausentes (DB sem seed).
   - `inertiaGet()` — pega a versão real do Inertia via `HandleInertiaRequests::version()`
     para evitar 409 (mismatch de manifest hash).
   - `assertInertiaComponent()` — valida 200 + header + path do componente.
   - `ensureEssentialsPermissions()` — popula permissões `essentials.*` no Admin role.

2. **Nomenclatura** dos testes em PT-BR (`feedback_testes_com_nova_feature`):
   - `index_exige_autenticacao`
   - `index_retorna_inertia_com_estrutura_esperada`
   - `index_respeita_business_id_scope`
   - `store_*` / `update_*` / `destroy_*` quando aplicável.

3. **Não usar** `RefreshDatabase` — as 100+ migrations + triggers MySQL do
   UltimatePOS quebram em SQLite. Roda contra DB local; cleanup manual.

4. **Sem mock**: queremos contrato real. Quando o controller depender de
   features externas (storage, Carbon timezone) o teste tem que tolerar
   ausência (markTestSkipped) ou cobrir só a porta pública.

## Execução

```bash
composer install --optimize-autoloader
vendor/bin/pest --filter Essentials
```

> Lote 5 deixou os testes em estado **compilável** mas dependentes de DB real
> seedado (Business + User). Em CI sem DB são **skipped**, não falsos
> positivos.
