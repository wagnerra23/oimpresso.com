# SPEC — Modulo Writebot

> Status: legado, **quebrado** (routes.php aponta pra namespace errado). Recomendacao: **deprecar** (ou consertar+reescrever em Inertia/React).

## Proposito original

Aparentemente um gerador de texto/posts assistido por IA — heranca do
codecanyon UltimatePOS upstream. Sem documentacao oficial encontrada.

## Estado atual

- **2 controllers** apenas:
  - `Modules/Writebot/Http/Controllers/InstallController.php` — refatorado para
    `extends BaseModuleInstallController` (ADR 0024 aplicada).
  - `Modules/Writebot/Http/Controllers/DataController.php` — feature
    flag/permissions handler (`superadmin_package`, `user_permissions`,
    `modifyAdminMenu` retornando vazio).
- **routes.php quebrado**: `Modules/Writebot/Http/routes.php` tem
  `namespace => 'Modules\Boleto\Http\Controllers'` + `prefix => 'boleto'`
  (copy-paste error legado da versao Codecanyon original — confirmado em
  ADR 0024 e em `feedback_pattern_install_modulos`). Resultado: nenhuma
  rota Writebot funcional no app — tudo cai em `/boleto/*` no namespace
  errado.
- **Sem views proprias visiveis** — diretorio Resources/views nao explorado
  no PR (pendencia: investigar antes de deprecar de vez).
- **Sem testes** ate este PR.

## Bugs conhecidos (consertados parcialmente)

| Bug | ADR | Status |
|---|---|---|
| `InstallController` com namespace `Modules\Boleto` | 0024 | corrigido |
| System property `boleto_version` em vez de `writebot_version` | 0024 | corrigido (`moduleSystemKey()` retorna `writebot`) |
| `Http/routes.php` namespace `Modules\Boleto\Http\Controllers` | — | **AINDA QUEBRADO** |

## Auth/middleware

Stack pretendida (do route file): `web, authh, SetSessionData, auth, language, timezone, AdminSidebarMenu`. Como o namespace esta errado, nada disso roda.

## Permissions

`writebot.access` — declarada em `DataController::user_permissions()` para role-based gating (sem efeito ate as rotas voltarem).

## Testes (este PR)

- `Modules/Writebot/Tests/Feature/WritebotInstallTest.php` — guarda contra regressao do pattern install (namespace correto, `extends BaseModuleInstallController`, `moduleName='Writebot'`, system key conem `writebot`); documenta que `/writebot/install` ainda nao existe (404).

## Recomendacao

**Deprecar** salvo se Wagner identificar use-case ativo. Custo de consertar
o routes.php + reescrever views eh maior que beneficio aparente; ja temos
Modulos `Copiloto`, `LaravelAI` e plano Vizra ADK + Prisma cobrindo IA.

Se manter:
1. Corrigir `Modules/Writebot/Http/routes.php` (namespace + prefix).
2. Auditar views em `Resources/views`.
3. Reescrever em Inertia/React (vide ADR 0023 + 0025).
