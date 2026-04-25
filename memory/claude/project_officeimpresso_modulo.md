---
name: Officeimpresso — módulo de licença desktop (3.7 restaurado)
description: Módulo superadmin pra gestão de licenças de desktop (Licenca_Computador); restaurado da 3.7 para o 6.7 em 2026-04-23
type: project
originSessionId: 0a2fc9e1-a031-4636-a1f3-71622c27daa8
---
**Módulo interno da WR2** pra autenticação/licenciamento dos desktops clientes. **É o 2º item do menu** (ordem `->order(2)`, logo após Superadmin que é ordem 1). Aparece **só pra superadmin** (`auth()->user()->can('superadmin')`).

**Why:** a migração 3.7→6.7 perdeu todo o CRUD de licença de desktop; só sobrou o catálogo QR (resíduo do ProductCatalogue). Restaurado de `origin/3.7-com-nfe` em 2026-04-23.

**How to apply:**
- Menu tem 5 sub-items: Empresas Licenciadas (`businessall`), Computadores (`computadores`), Licenças (`licenca_computador`), Clientes OAuth (`client`), Documentação (`docs`). Catalogue QR foi REMOVIDO do submenu (não pertence a esse módulo).
- Rotas em `Modules/Officeimpresso/Routes/web.php` usam names **sem prefix** (`business.update`, `business.bloqueado`, `empresa.licencas`, `computadores`, `licenca_computador.*`, `client.*`) — as views do 3.7 chamam assim.
- Entity: `Modules\Officeimpresso\Entities\Licenca_Computador` (underscore no meio), tabela `licenca_computador` (singular), FK → `business`.
- Migrations em `Database/Migrations/` — 2 arquivos (create 2024-11-05, update 2024-11-07 com `descricao`, `sistema`, `dt_cadastro`).
- **Tabela licenca_computador já existe em produção** — preservada desde o 3.7. Não rodar as migrations lá, só em staging/local novo.

**Rotas principais:**
- `GET /officeimpresso/computadores` — view do business logado (dados empresa + tabela computadores)
- `GET /officeimpresso/licenca_computador` — index (lista licenças)
- `GET /officeimpresso/licenca_computador/create` + `POST store` — CRUD
- `GET /officeimpresso/licenca_computador/{id}/toggle-block` — alterna bloqueio
- `POST /officeimpresso/licenca_computador/businessupdate/{id}` — update da empresa
- `GET /officeimpresso/businessall` — superadmin, todas empresas licenciadas
- `GET /officeimpresso/client` — CRUD clientes OAuth (Passport password grant)
- `GET /officeimpresso/licenca_log?business_id=X&licenca_id=Y` — audit log com filtros
- `GET /api/officeimpresso` — Bearer auth, devolve user autenticado
- `POST /api/officeimpresso/audit` — Bearer, Delphi futuro pode postar eventos opt-in

**Design system:**
- `Modules/Officeimpresso/Resources/views/layouts/partials/design-system.blade.php` — CSS compartilhado
- Nav blade: `layouts/nav.blade.php` (AdminLTE skin override pra fundo branco)
- Nav React/Inertia: `Resources/menus/topnav.php` (declarativo)

**Gotchas pós-upgrade 3.7→6.7:**
- `Modules/Officeimpresso1/` no servidor era lixo da migração com mesmo `name: Officeimpresso` → conflito de namespace impedia load de traduções novas. Movido pra `~/Officeimpresso1-3.7-BACKUP/`.
- `@lang('key', [], 'fallback')` — 3º arg é LOCALE, não fallback. Laravel retorna key literal.
- `oauth_clients.id` continua INT no DB (Passport v13 espera UUID, mas usa o que existe).
- `Event::listen()` em `ServiceProvider::boot()` de módulo nwidart pode **duplicar listener** (boot roda 2x em condições específicas) — guard com `static $listenerRegistered`.
- `css/1E202D.css` + `css/app.all.css` referenciados em `resources/views/layouts/partials/css.blade.php` **mas arquivos nao existem** — 404 + tema escuro quebrado globalmente. Removidas as refs.

**Passport v10→v13 upgrade (ADR 0019 RESOLVIDO):**
- `grant_type=password` desabilitado por padrão a partir de v11 → `Passport::enablePasswordGrant()` em AuthServiceProvider
- `oauth_clients.secret` passou a ser hashed automaticamente via Eloquent cast → re-save existing clients pra hash (sem precisar saber o plain)
- `oauth_clients.provider` passou a ser obrigatório → `UPDATE oauth_clients SET provider='users' WHERE password_client=1 AND provider IS NULL`

**Log de acesso (ADR 0018 v2):**
- **Arquitetura atual: event listener + middleware** (não triggers MySQL)
  - `LogPassportAccessToken` escuta `AccessTokenCreated` — grava login_success com IP/UA/user/client (é o master user, NÃO identifica cliente real)
  - `LogDelphiAccess` middleware em `/connector/api/processa-dados-cliente` + `salvar-cliente` + `salvar-equipamento/{id}` — extrai CNPJ+HD do body (fonte REAL de identidade)
  - `LogDesktopAccess` middleware em `/api/officeimpresso/*` — grava api_call opt-in (Delphi futuro)
  - Triggers dropados (dados rasos, sem IP nem endpoint)
  - Tudo em try/catch — log nunca quebra Delphi
- **Regra: só loga quando Delphi envia `hd`** (serial do disco) — sem hd não dá pra identificar máquina
- **Master user shared** — todos os Delphis usam 1 master user, então `user->business_id` sempre aponta pro business do master (ex: WR2). LogDelphiAccess prioriza CNPJ do body sobre user->business_id (ver reference_diff_3_7_vs_6_7_officeimpresso.md "Armadilha crítica")
- **Snapshot de bloqueio** em `metadata` — `business_blocked`, `licenca_blocked`, `was_blocked` capturados NO momento do log write
- **Listener guardado** por `static $listenerRegistered` — evita duplicata se boot() rodar 2x

**Tela `/officeimpresso/licenca_log` (2026-04-24 v3):**
- **Fonte primária: `licenca_computador`** (registry de máquinas), NÃO `licenca_log`. A rotina `processa-dados-cliente` + `saveEquipamento` popula esse registry; os logs só enriquecem cada linha com "Último Login".
- 9 colunas: Empresa · Máquina · HD · Versão (versao_exe/versao_banco) · IP · Último Login · Estado no Último Login · Estado Atual · Ações
- DataTables client-side, default sort Último Login desc
- Filtros: busca livre (q) + estado_atual (ativa/bloqueada). Empresa/Máquina viram **hyperlinks** na linha que filtram por business_id/licenca_id
- KPIs globais: total máquinas, máquinas bloqueadas, empresas bloqueadas, chamadas processa-dados-cliente 24h
- Rota de drill-down `/licenca_log/timeline/{licenca_id}` existe para ver histórico completo de uma máquina, mas **não é linkada do grid** (a tela inteira já é o "timeline")

**Chave de hardware:**
- `licenca_computador.hd` (serial do disco) é **chave única** por máquina
- Delphi atual (2026-04-24) **não envia hd** em `/oauth/token` — ADR futuro: incluir como extra param ou header `X-OI-HD`
- Listener já lê `$request->input('hd')` ou `header('X-OI-HD')` — quando Delphi atualizar, match automático

**Builds Delphi em produção (2026-04-24):**
- Source atual em `D:/Programas/WR Comercial/` tem `TControllerPrincipal.AfterLogin` chamando `TServicesRegistroSistema.RegistrarSistema(True)` após login → POST `/connector/api/processa-dados-cliente` com body array (NOME_TABELA=EMPRESA + LICENCIAMENTO, ~3KB). Isso é o que EXTREMA LED (biz=196), biz=169 e biz=177 enviam — esses clientes estão com build atualizado.
- **Vargas (biz=164) e outros NÃO enviam** — build anterior que só autentica em `/oauth/token` e não tem o `RegistrarSistema` no fluxo. Recompilar o .exe em `D:/Programas/WR Comercial/` e redistribuir resolve.
- Middleware `log.delphi` roda ANTES de `auth:api` no route group pra capturar inclusive 401s (caso algum Delphi bata com token expirado). Comando `php artisan officeimpresso:inspect-api` mostra body completo pra audit.

**Roadmap (ver `Modules/Officeimpresso/CHANGELOG.md`):**
- Delphi enviar `hd` no `/oauth/token` (desbloqueia identificação de máquina)
- **Grupo econômico** (ADR 0020): filial abre sistema e não acha CNPJ matriz → add `business.matriz_id`, resolver `effective_business_id = matriz_id ?? id` no fluxo de auth
- **Restaurar endpoints Connector 3.7** (ADR 0021): Delphi usa `/connector/api/processa-dados-cliente`, `/salvar-equipamento/{id}`, `/{tabela}/sync-post|sync-get` — **147 controllers perdidos na migração 3.7→6.7**. Prioridade alta — sem isso Delphi não sincroniza dados.

**Descoberta 2026-04-24 — Delphi real (ADR 0021):**
O Delphi usa **3 gerações de endpoints em Connector**, todos sob `/connector/api/`:
- Geração 1 (prod): `processa-dados-cliente` — JSON com EMPRESA+LICENCIAMENTO, resposta `S;msg`/`N;motivo`
- Geração 2 (prod): `/{tabela}/sync-post` + `sync-get` — chunks de 100 registros modificados
- Geração 3 (em dev): `/api/oimpresso/registrar` — não usado ainda
Auth: `X-API-Key=<client_id>` + `X-API-Secret=<client_secret>` + `Authorization: Bearer <token>` (dual). `hd` vem no body do `/registrar`, não em `/oauth/token`.
