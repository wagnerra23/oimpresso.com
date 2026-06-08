---
name: Officeimpresso — módulo Laravel licença desktop (3.7 restaurado, evolução 6.7)
description: Módulo superadmin pra gestão de licenças de desktop (Licenca_Computador) restaurado da 3.7→6.7 em 2026-04-23. Inclui contrato API Delphi (Connector), tela licenca_log v3 machine-centric, armadilha master user e diff técnico 3.7 vs 6.7.
type: project
---

# Officeimpresso — módulo Laravel atual

> **Módulo interno da WR2** pra autenticação/licenciamento dos desktops clientes Delphi. **2º item do menu** (`->order(2)`, logo após Superadmin). Aparece **só pra superadmin** (`auth()->user()->can('superadmin')`).
>
> **Why:** a migração 3.7→6.7 perdeu todo o CRUD de licença de desktop; só sobrou catálogo QR (resíduo do ProductCatalogue). Restaurado de `origin/3.7-com-nfe` em 2026-04-23.

## Estrutura do módulo

- Menu (5 sub-items): Empresas Licenciadas (`businessall`), Computadores (`computadores`), Licenças (`licenca_computador`), Clientes OAuth (`client`), Documentação (`docs`). Catalogue QR REMOVIDO do submenu.
- Rotas em `Modules/Officeimpresso/Routes/web.php` usam names **sem prefix** (`business.update`, `business.bloqueado`, `empresa.licencas`, `computadores`, `licenca_computador.*`, `client.*`) — views 3.7 chamam assim.
- Entity: `Modules\Officeimpresso\Entities\Licenca_Computador` (underscore no meio), tabela `licenca_computador` (singular), FK → `business`.
- Migrations em `Database/Migrations/` — 2 arquivos (create 2024-11-05, update 2024-11-07 com `descricao`, `sistema`, `dt_cadastro`).
- **Tabela `licenca_computador` já existe em produção** — preservada desde 3.7. Não rodar migrations lá; só staging/local novo.

## Rotas principais

- `GET /officeimpresso/computadores` — view do business logado
- `GET /officeimpresso/licenca_computador` — index + CRUD + `{id}/toggle-block`
- `POST /officeimpresso/licenca_computador/businessupdate/{id}` — update empresa
- `GET /officeimpresso/businessall` — superadmin, todas empresas licenciadas
- `GET /officeimpresso/client` — CRUD clientes OAuth (Passport password grant)
- `GET /officeimpresso/licenca_log?business_id=X&licenca_id=Y` — audit log com filtros
- `GET /api/officeimpresso` (Bearer) — devolve user autenticado
- `POST /api/officeimpresso/audit` (Bearer) — Delphi futuro pode postar eventos opt-in

## Design system

- `Modules/Officeimpresso/Resources/views/layouts/partials/design-system.blade.php` — CSS compartilhado
- Nav blade: `layouts/nav.blade.php` (AdminLTE skin override fundo branco)
- Nav React/Inertia: `Resources/menus/topnav.php` (declarativo)

## Diff 3.7 vs 6.7 — controllers Connector + Officeimpresso

**TL;DR:** controllers/endpoints que Delphi consome estão **funcionalmente iguais ao 3.7**. Adicionamos infraestrutura (logging, enforcement, observability) POR CIMA, sem alterar a wire.

### Controllers restaurados em `Modules/Connector/`

| Arquivo | Status | Adaptações L13 |
|---|---|---|
| `Api/LicencaComputadorController.php` | idêntico | `App\Models\Busines` → `App\Business`. Match `hd + business_id + user_win` preservado |
| `Api/BusinessController.php` | lógica preservada | `User::createOwnerUser()` (não existe 6.7) → `User::create_user([...])` payload completo. Fix typo `;` linha 237 |
| `Api/BaseApiController.php` | mínimo | `$callback = null` → `?callable $callback = null` (PHP 8.1+) |

### Controllers `Modules/Officeimpresso/Http/Controllers/`

- `LicencaComputadorController.php` — 3.7 tinha 5 métodos; `Route::resource` espera 7 → stubs `create()`/`edit()`
- `LicencaLogController.php` — 3.7 era stub 501; 6.7 CRUD completo + DataTables AJAX + KPIs + agregação por máquina

### Endpoints API — contrato Delphi

| Endpoint | 3.7 | 6.7 | Mudança |
|---|---|---|---|
| `POST /oauth/token` | Passport v5 | Passport v13 | **Nenhuma** (mesmo payload). Fixes server-side: `enablePasswordGrant()`, `provider='users'`, rehash secrets — ADR 0019 |
| `POST /connector/api/processa-dados-cliente` | ok | restaurado | **Nenhuma** — response STRING `'S;msg'`/`'N;motivo'` preservado |
| `POST /connector/api/salvar-cliente` | ok | restaurado | **Nenhuma** |
| `POST /connector/api/salvar-equipamento/{id}` | ok | restaurado | **Nenhuma** |
| `POST /connector/api/{tabela}/sync-post` | vários | parciais | 6.7 tem: business-location, contactapi, product, sell, expense, cash-register. **Faltam:** equipamento_impressora/sync-*, historico_impressoes/sync-* |
| `GET /api/officeimpresso` | — | novo | Bearer, retorna user — aditivo |
| `POST /api/officeimpresso/audit` | — | novo | Opt-in pro Delphi futuro |

### Infraestrutura nova (zero impacto contrato Delphi)

1. `Listeners/LogPassportAccessToken` — escuta `AccessTokenCreated`, grava `licenca_log.event=login_success`
2. `Http/Middleware/LogDesktopAccess` — `/api/officeimpresso/*`
3. `Http/Middleware/LogDelphiAccess` — `/connector/api/processa-dados-cliente`, `salvar-cliente`, `salvar-equipamento/{id}`. Extrai `HD` da estrutura JSON Delphi (3 formatos)
4. `Console/ParseLicencaLogCommand` — parseia `storage/logs/laravel.log` atrás de OAuth errors
5. `Http/Controllers/AuditController` — endpoint opt-in `/api/officeimpresso/audit`
6. Tabela `licenca_log` + model + UI `/officeimpresso/licenca_log`
7. `User::validateForPassportPasswordGrant` override — rejeita `/oauth/token` quando `business.officeimpresso_bloqueado=1`, só clients desktop (39, 107). Response 400 invalid_grant, idêntico ao "não autenticou"
8. Pest regression guards (9 passes) em `tests/Feature/Connector/DelphiOImpressoContractTest.php`

### Route names cosmético

- 3.7 sem `->name()` | 6.7 prefixos `connector.*` (resolver colisão `route:cache`). URLs/endpoints **idênticos** — só label interno.

## Armadilha CRÍTICA — master user shared

O Delphi **compartilha 1 master user** entre todas instalações. Consequências:

- `/oauth/token` **NÃO identifica o cliente** — `access_token` pertence ao master (ex: WR2). Usar `user->business_id` em log/enforcement daria sempre o mesmo business, errado.
- Identidade **REAL** vem do body `/connector/api/processa-dados-cliente` (CNPJ em `NOME_TABELA=EMPRESA`, HD em `NOME_TABELA=LICENCIAMENTO`).
- Middleware `LogDelphiAccess` extrai CNPJ+HD do body; controller `LicencaLogController` agrega sobre `source IN ('delphi_middleware','desktop_audit')` — nunca sobre `login_success/login_error` (esses seriam por user_id = master).
- **Regra prática:** em qualquer enforcement/log novo **derivar cliente do body**, nunca de `request()->user()->business_id`.

## Log de acesso (ADR 0018 v2)

Arquitetura: **event listener + middleware** (não triggers MySQL).

- `LogPassportAccessToken` escuta `AccessTokenCreated` — grava login_success com IP/UA/user/client (master, NÃO identifica cliente real)
- `LogDelphiAccess` middleware em `/connector/api/{processa-dados-cliente,salvar-cliente,salvar-equipamento/{id}}` — extrai CNPJ+HD do body (fonte REAL)
- `LogDesktopAccess` middleware em `/api/officeimpresso/*` — grava api_call opt-in
- Triggers dropados (rasos)
- Tudo try/catch — log nunca quebra Delphi
- Regra: **só loga quando Delphi envia `hd`** (serial disco)
- Snapshot de bloqueio em `metadata` (`business_blocked`, `licenca_blocked`, `was_blocked`)
- Listener guardado por `static $listenerRegistered` — evita duplicata se boot() rodar 2x

## Tela `/officeimpresso/licenca_log` (2026-04-24 v3 machine-centric)

- **Fonte primária: `licenca_computador`** (registry de máquinas), NÃO `licenca_log`. Rotina `processa-dados-cliente` + `saveEquipamento` popula esse registry; logs só enriquecem com "Último Login".
- 9 colunas: Empresa · Máquina · HD · Versão (versao_exe/versao_banco) · IP · Último Login · Estado no Último Login · Estado Atual · Ações
- DataTables client-side, default sort Último Login desc
- Filtros: busca livre (q) + estado_atual (ativa/bloqueada). Empresa/Máquina viram **hyperlinks** que filtram por business_id/licenca_id
- KPIs globais: total máquinas, máquinas bloqueadas, empresas bloqueadas, chamadas processa-dados-cliente 24h
- Drill-down `/licenca_log/timeline/{licenca_id}` existe mas **não linkado do grid** (a tela inteira já é timeline)

## Campos úteis `licenca_computador` (registry)

Fonte de verdade pra registry de máquinas (parte do contrato Delphi via `saveEquipamento` e `processa-dados-cliente`):

- `hd` — serial do disco, **chave única por máquina** (+ business_id + user_win)
- `user_win` — hostname Windows
- `hostname` — hostname alternativo
- `ip_interno` — IP rede local
- `versao_exe` — versão executável Delphi
- `versao_banco` — versão banco local
- `sistema_operacional` — Windows XP/7/10/11
- `sistema` — sistema/licença (descritivo)
- `bloqueado` — bit por máquina (separado do bloqueio de empresa)
- `dt_ultimo_acesso` — atualizado em saveEquipamento
- `dt_validade` — fim licença
- `serial` — legado pré-HD

Tela `licenca_log` usa esse registry como fonte primária e enriquece com `MAX(created_at)` do `licenca_log` onde `source=delphi_middleware AND endpoint LIKE '%processa-dados-cliente%'`.

## Chave de hardware

- `licenca_computador.hd` é **chave única por máquina**
- Delphi atual (2026-04-24) **não envia hd** em `/oauth/token` — ADR futuro: incluir como extra param ou header `X-OI-HD`
- Listener já lê `$request->input('hd')` ou `header('X-OI-HD')` — quando Delphi atualizar, match automático

## Builds Delphi em produção (2026-04-24)

- Source atual em `D:/Programas/WR Comercial/` tem `TControllerPrincipal.AfterLogin` chamando `TServicesRegistroSistema.RegistrarSistema(True)` após login → POST `/connector/api/processa-dados-cliente` com body array (~3KB). EXTREMA LED (biz=196), biz=169 e biz=177 enviam — clients com build atualizado.
- **Vargas (biz=164) e outros NÃO enviam** — build anterior só autentica em `/oauth/token`. Recompilar `.exe` em `D:/Programas/WR Comercial/` e redistribuir resolve.
- Middleware `log.delphi` roda ANTES de `auth:api` no group → captura inclusive 401s. Comando `php artisan officeimpresso:inspect-api` mostra body completo pra audit.

## Gotchas pós-upgrade 3.7→6.7

- `Modules/Officeimpresso1/` no servidor era lixo da migração com mesmo `name: Officeimpresso` → conflito namespace impedia load de traduções. Movido pra `~/Officeimpresso1-3.7-BACKUP/`.
- `@lang('key', [], 'fallback')` — 3º arg é LOCALE, não fallback. Laravel retorna key literal.
- `oauth_clients.id` continua INT no DB (Passport v13 espera UUID, mas usa o que existe).
- `Event::listen()` em `ServiceProvider::boot()` de módulo nwidart pode **duplicar listener** (boot roda 2x) — guard com `static $listenerRegistered`.
- `css/1E202D.css` + `css/app.all.css` referenciados em `resources/views/layouts/partials/css.blade.php` **mas arquivos nao existem** — 404 + tema escuro quebrado globalmente. Removidas as refs.

## Passport v10→v13 upgrade (ADR 0019 RESOLVIDO)

- `grant_type=password` desabilitado por padrão a partir de v11 → `Passport::enablePasswordGrant()` em AuthServiceProvider
- `oauth_clients.secret` passou a ser hashed automaticamente via Eloquent cast → re-save existing clients pra hash (sem precisar saber plain)
- `oauth_clients.provider` passou a ser obrigatório → `UPDATE oauth_clients SET provider='users' WHERE password_client=1 AND provider IS NULL`

## Roadmap (`Modules/Officeimpresso/CHANGELOG.md`)

- Delphi enviar `hd` em `/oauth/token` (desbloqueia identificação máquina)
- **Grupo econômico** (ADR 0020): filial não acha CNPJ matriz → add `business.matriz_id`, resolver `effective_business_id = matriz_id ?? id`
- **Restaurar endpoints Connector 3.7** (ADR 0021): Delphi usa `/connector/api/{processa-dados-cliente,salvar-equipamento/{id},{tabela}/sync-post|sync-get}` — **147 controllers perdidos na migração 3.7→6.7**. Prioridade alta.

## Descoberta 2026-04-24 — Delphi real (ADR 0021)

Delphi usa **3 gerações de endpoints em Connector**, todos sob `/connector/api/`:

- **Gen 1 (prod):** `processa-dados-cliente` — JSON com EMPRESA+LICENCIAMENTO, resposta `S;msg`/`N;motivo`
- **Gen 2 (prod):** `/{tabela}/sync-post` + `sync-get` — chunks 100 registros modificados
- **Gen 3 (em dev):** `/api/oimpresso/registrar` — não usado ainda

Auth: `X-API-Key=<client_id>` + `X-API-Secret=<client_secret>` + `Authorization: Bearer <token>` (dual). `hd` vem no body do `/registrar`, não em `/oauth/token`.

## Como usar esta nota

- Modificar controller Connector → **ler primeiro** "adaptações L13" pra não reintroduzir bugs removidos
- Adicionar endpoint novo → criar **aditivo** (não substituir); Delphi ignora rotas/campos desconhecidos
- Debug Delphi não conectando → checar ordem: (1) `/oauth/token` 200? (2) grant=password habilitado? (3) provider='users' no client? (4) secret hashed? (5) business bloqueado?
- Restaurar outro endpoint Connector 3.7 → `git show origin/3.7-com-nfe:Modules/Connector/Http/Controllers/Api/X.php` e copiar; atenção em refs `App\Models\Busines` e `User::createOwnerUser`

## Relacionado

- ADR 0017 — Restauração Officeimpresso 3.7 → 6.7
- ADR 0018 — Log de acesso v2 (event listener + middleware)
- ADR 0019 — Passport v10→v13 auth Delphi (RESOLVIDO)
- ADR 0020 — Grupo econômico (matriz_id)
- ADR 0021 — Contrato real da API Delphi (3 gerações)
- legacy-delphi-firebird.md — código fonte Delphi + 50 bancos Firebird + creds SYSDBA
- `tests/Feature/Connector/DelphiOImpressoContractTest.php` — regression guards
