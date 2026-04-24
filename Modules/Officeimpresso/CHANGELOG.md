# Officeimpresso — Changelog

## [1.2.0] — 2026-04-23 — UI refinada + business_id filter + Officeimpresso1 dedup

### Corrigido
- **Fundo preto no topnav** — override `skin-purple` com `nav.bg-white` branco + pills + azul suave no item ativo
- **Inputs dark** — `background: #fff !important` nas `.form-control` dentro de `.oi-page`
- **KPI Bloqueadas menor que outros** — flexbox `.oi-kpi-row` equaliza altura (min-height: 76px)
- **Link "Documentação"** removido do menu (apontava pra docs comercial, não admin)
- **Translations não resolviam** (user_win, processador, memoria…) — causa: `Modules/Officeimpresso1/` no servidor era backup 3.7 com mesmo `name: Officeimpresso` no `module.json` causando conflito de namespace nwidart. Movido pra `~/Officeimpresso1-3.7-BACKUP/`

### Adicionado
- **Filtro `?business_id=`** no `/licenca_log` — superadmin pode clicar "Ver log da empresa" em businessall
- **ADR 0019** — investigação do Delphi não autenticando pós-upgrade
- **`bin/test-delphi-auth.sh`** — script pra testar grant password via curl, isolar problema entre server e client

## [1.1.0] — 2026-04-23 — Log de Acesso Fase 2 + UI polish

### Adicionado
- **Command `php artisan licenca-log:parse`** — parser passivo do
  `storage/logs/laravel.log` que extrai erros de OAuth/Passport e grava
  em `licenca_log` com `source=log_parser`. Dedup por hash da linha.
  Agendar: `$schedule->command('licenca-log:parse')->everyFiveMinutes();`
- **Endpoint opcional `POST /api/officeimpresso/audit`** — Delphi futuro
  pode postar eventos observados localmente (timeout, erro de conexão
  interno). **Não obrigatório** — Delphi atual ignora.
- **UI da tela `/officeimpresso/licenca_log` repaginada:**
  - KPIs com cards modernos (sem bordas AdminLTE)
  - Badges coloridos por tipo de evento
  - Filtro deep-link `?licenca_id=X` com alerta visual
  - Mensagem de tabela vazia explicando os triggers
  - Error messages destacados em vermelho
  - Botão "Limpar filtros"
  - Duração em ms sem coluna "erro" redundante (mostra no endpoint)

### Não tocado
- `/oauth/token` — fluxo Passport intacto
- `/api/officeimpresso` GET — ping existente preservado
- Delphi legado continua funcionando 100%

---

## [1.0.0] — 2026-04-23 — Restauração da 3.7

### Restaurado (a partir de `origin/3.7-com-nfe`)
- `Entities/Licenca_Computador.php`
- Controllers: `LicencaComputadorController`, `ClientController`, `DataController`, `OfficeimpressoController`
- `Http/Middleware/CheckDemo.php`
- 9 Transformers (BusinessResource, ProductResource, etc.)
- 6 views blade (licenca_computador/*, licencas_log/*, clients/*)
- Migrations: `create_licenca_computador_table`, `update_licenca_computador_table`
- `Resources/lang/pt/lang.php` com 50+ chaves

### Adicionado
- **Topnav horizontal** (`Resources/views/layouts/nav.blade.php`) — barra
  com 6 itens estilo Superadmin
- **Topnav declarativo** (`Resources/menus/topnav.php`) — espelho para
  React/Inertia futuro
- **Menu sidebar** como 2º item (ordem 2, logo após Superadmin) — só
  superadmin
- **Tabela `licenca_log`** com 17 colunas, 4 indexes compostos
- **Triggers MySQL passivos** em `oauth_access_tokens` e
  `oauth_refresh_tokens` → gravam `login_success` e `token_refresh` sem
  tocar no hot path do Delphi
- **Indexes em `licenca_computador`** (hd, dt_ultimo_acesso, composite
  business_id+dt_ultimo_acesso, composite business_id+bloqueado) pra
  melhorar performance das listagens
- **View `/officeimpresso/licenca_log`** com KPIs 24h + DataTable AJAX +
  filtros (event, from, to, licenca_id)
- **Botão "Log" por computador** na tela `/officeimpresso/computadores`
  — deep-link para log filtrado por máquina

### Corrigido
- Namespace `RouteServiceProvider.php` estava `Modules\ProductCatalogue`
  (copy-paste bug) → `Modules\Officeimpresso`
- Views com typos de route name (`licencas_computador.create` → singular)
- Views referenciando `LicencaController` (classe inexistente) →
  `ClientController`
- Bug do `@lang('key', [], 'fallback')` — 3º arg é locale, não fallback
  — removido em `index.blade.php`
- `LicencaLogController` com namespace errado + model `App\Models\LicencaLog`
  inexistente → corrigido

### Documentação
- `memory/decisions/0017-officeimpresso-restaurado-superadmin-exclusivo.md`
- `memory/decisions/0018-officeimpresso-log-acesso-passivo.md`
- `memory/officeimpresso-spec.md` — especificação do módulo
