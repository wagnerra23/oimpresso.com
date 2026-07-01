---
date: "2026-07-01"
hour: "20:06 BRT"
topic: "Suite tests/Feature/Support (Modo Suporte) era cobertura FALSA — fixtures biz=99 inválidos + bug de casing js/pages no config Inertia; suite passa a 40/40 no CT100"
authors: [C]
related_adrs: [0305-modo-suporte-cross-tenant-exceto-operador, 0308-modo-suporte-fase-a-acessar-como-login-as-guardado, 0101-tests-business-id-1-nunca-cliente, 0062-separacao-runtime-hostinger-ct100]
prs: [3562, 3563]
---

# Suite Support: fixtures biz=99 válidos + casing js/Pages → 40/40

**TL;DR:** A suite `tests/Feature/Support/` (Modo Suporte, ADR 0305/0308/0309) era **cobertura FALSA**: nunca executava (loader do Pest morria no double-`uses(Tests\TestCase::class)` desde 2026-06-24, corrigido no #3554) e, rodando no CT100/MySQL, falhava **32/40** por fixture — não por bug de produto. Dois bugs distintos, dois PRs mergeados; suite agora **40/40 (121 assertions)** no CT100.

## O que aconteceu

Comecei gated no #3554 (loader) — que já havia mergeado em `main` durante a sessão, junto do #3555 (`APP_ENV=testing` → CSRF). Rebasei em `origin/main` (ambos já presentes) e ataquei o alvo.

**Causa-raiz #1 (fixture — o alvo da tarefa):** os testes precisam de uma empresa-cliente **não-operadora** (biz=99, ADR 0101 — nunca biz=4 real) pra exercitar grant/revoke/impersonation cross-tenant. Mas biz=99 era criado cru com só `name`+`currency_id` → viola as 7 colunas NOT NULL sem default de `business` + o chicken-and-egg `users`↔`business` (`owner_id` FK→users, `users.business_id` FK→business). `SuporteConcederCommandTest` era pior: criava user biz=99 sem sequer tentar criar o business.

**Causa-raiz #2 (config — descoberta ao rodar):** as 2 últimas falhas (`assertInertia()->component('Suporte/Empresas'|'Suporte/Visao')`) NÃO eram fixture. `config/inertia.php` (adicionado em #3092, 2026-06-20) aponta `pages.paths` pra `resource_path('js/pages')` **minúsculo**, mas o diretório real é `js/Pages`. Em FS case-sensitive (Linux CT100/CI) o `FileViewFinder` não acha nenhum componente → toda asserção de existência de componente falha **na suite inteira** (confirmado: `HomeIndexInertiaTest` também quebra). Windows dev (case-insensitive) mascarava. Só impacta teste (em runtime o finder só roda com `pages.ensure_pages_exist=true`, que é false).

## Fix

- **Helper `seededSupportClientTenant(): Business`** no trait `tests/Support/WithSeededTenant.php` — biz=99 válido + owner na ordem correta, idempotente, espelhando `database/seeders/FullSuiteMinimalTenantSeeder`. Disponível via `Tests\TestCase` como `$this->seededSupportClientTenant()`.
- Substituídos os 7 `Business::firstOrCreate(['id'=>99],...)` quebrados; semeado biz=99 **antes** de cada criação de usuário biz=99 (order-safe sob `executionOrder="random"`). Removidos imports `App\Business` órfãos.
- Corrigido `SupportClientViewServiceTest`: o "não-agente" era criado em biz=1 (operadora), que **é** agente por membership (ADR 0309) → `makeViewAgent` ganhou `businessId` e o não-agente virou cliente (biz=99).
- `config/inertia.php`: `js/pages` → `js/Pages` (PR separado, 1 intent).

## Validação (CT100 · `oimpresso-staging` · MySQL real · ADR 0062)

| Estado | Resultado |
|---|---|
| main + loader fix | 32 failed / 8 passed |
| #3563 só | 38 passed / 2 failed |
| #3563 + #3562 | **40 passed / 0 failed (121 assertions)** |
| **`main` mergeado (smoke final)** | **40 passed / 0 failed** |

Também confirmado #3562 isolado: erro "component does not exist" do `HomeIndexInertiaTest` foi a **0** ocorrências.

## Artefatos

- [tests/Support/WithSeededTenant.php](../../tests/Support/WithSeededTenant.php) — +helper (~50 linhas)
- 7 arquivos em [tests/Feature/Support/](../../tests/Feature/Support/) — fixtures corrigidos
- [config/inertia.php](../../config/inertia.php) — casing pages.paths

## Lições catalogadas

1. **Cobertura FALSA tem 2 vetores neste caso:** (a) loader morto (double-`uses`) → suite nunca roda; (b) suite roda mas **skipa no CI (SQLite)** via guards `xxxSchemaReady()` — por isso os fixtures quebrados nunca apareceram no CI. **Só o CT100/MySQL prova** (ADR 0062 na prática).
2. **`resource_path('js/pages')` vs `js/Pages`:** casing de path é bug invisível no Windows, fatal no Linux. Qualquer `config('...paths')` novo deve casar o case real do diretório trackeado no git.
3. **DB persistente do staging envenena `firstOrCreate`:** rodei o código velho 1× → `view_nao_agente` ficou gravado como biz=1; `firstOrCreate` casa por username e **não corrige** o business_id. Deletei a linha órfã pra revalidar. Em CI (migrate:fresh) não acontece; no staging dogfooding sim.

## Pointers

- Handoff: [2026-07-01-2006-fixtures-support-biz99-inertia-casing.md](../handoffs/2026-07-01-2006-fixtures-support-biz99-inertia-casing.md)
- PRs: #3562 (casing Inertia), #3563 (fixtures Support) — ambos mergeados.
- Sessão de origem do diagnóstico: `claude/strange-allen-8478b8` (ImpostosGuardTest já corrigido no #3555).
