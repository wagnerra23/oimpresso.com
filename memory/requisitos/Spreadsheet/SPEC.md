---
module: Spreadsheet
version: "1.0"
last_updated: "2026-06-13"
owners: [W]
status: arquivado
na_justified:
  D5: "Utilitário backend de import/export planilhas cross-business. Não tem cliente externo direto — serve todos businesses como infraestrutura compartilhada (mesma natureza Brief/MCP tools — ADR 0094 §SoC brutal). ADR 0105 (cliente como sinal qualificado): função é infraestrutura interna, não produto cliente-facing."
  D9.b: "Spreadsheet é módulo CRUD síncrono — sem jobs assíncronos. CRUD de sheets + shares opera direto via Controller. failed_jobs N/A por design (sem owner ativo, manutenção bug-fix only)."
related_adrs: [0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0105-cliente-como-sinal-guiar-sem-mandar, 0153-module-grade-rubrica-v1, 0154-module-grade-v2-na-justificado, 0155-module-grade-v3-sub-dimensoes-gate-ci, 0156-module-grade-v3-errata-otel-helper-na-justified]
---

# SPEC — Modules/Spreadsheet

> Módulo legado UltimatePOS v6 — planilhas web colaborativas inline com share via link público. Pouco uso real hoje (Wagner prefere Google Sheets pra colaboração externa). Mantido por compat de scaffold + alguns clientes legacy que ainda têm dados.

## Contexto

- **Stack:** Laravel 13.6 + Blade legacy + biblioteca JS de planilha (Handsontable/x-spreadsheet legado)
- **Tabelas:** `sheet_spreadsheets`, `sheet_spreadsheet_shares` (M2M sheet ↔ token)
- **Owner:** sem owner ativo — manutenção bug-fix only
- **Pré-requisito Tier 0:** todas tabelas têm `business_id` ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

## User Stories

### US-SHEET-001 — Criar planilha nova
**Implementado em:** `Modules/Spreadsheet/Http/Controllers/SpreadsheetController.php` · `Modules/Spreadsheet/Entities/Spreadsheet.php` · `Modules/Spreadsheet/Resources/views/sheet/create.blade.php` · verificado@8af585a (2026-07-02) — create/store via Route::resource sheets em Modules/Spreadsheet/Routes/web.php
**Como** usuário, **quero** clicar "Nova planilha", informar nome e (opcional) folder_id, **pra** começar a editar células num grid web.
- Entity: `Spreadsheet` (`name`, `data` JSON ou TEXT, `folder_id?`, `business_id`)
- Controller: `SpreadsheetController::create|store`
- Tela: `sheet/create.blade.php`
- Aceite: planilha nasce vazia; `data` aceita até ~1MB JSON; `business_id` injetado automaticamente

### US-SHEET-002 — Listar e editar planilhas do business
**Implementado em:** `Modules/Spreadsheet/Http/Controllers/SpreadsheetController.php` · `Modules/Spreadsheet/Resources/views/sheet/index.blade.php` · `Modules/Spreadsheet/Resources/views/sheet/show.blade.php` · verificado@8af585a (2026-07-02) — index/show/update
**Como** usuário, **quero** ver lista de planilhas do meu business e abrir uma pra editar inline, **pra** trabalhar nos dados.
- Controller: `SpreadsheetController::index|show|update`
- Tela: `sheet/index.blade.php` (DataTable), `sheet/show.blade.php` (editor inline)
- Aceite: DataTable respeita global scope; editor salva via autosave AJAX

### US-SHEET-003 — Share via link (público read-only)
**Implementado em:** _parcial_ · `Modules/Spreadsheet/Http/Controllers/SpreadsheetController.php` · `Modules/Spreadsheet/Entities/SpreadsheetShare.php` · `Modules/Spreadsheet/Resources/views/sheet/partials/share_sheet.blade.php` · `Modules/Spreadsheet/Notifications/SpreadsheetShared.php` · verificado@8af585a (2026-07-02) — falta o link público: share real é interno (shared_with user/role/todo em sheet_spreadsheet_shares, sem colunas token/expires_at/permissions) e a rota pública /sheet/shared/{token} não existe
**Como** dono da planilha, **quero** gerar um link compartilhável com expiração opcional, **pra** mostrar a planilha pra alguém sem login.
- Entity: `SpreadsheetShare` (`token` UUID, `expires_at?`, `permissions` enum read|edit)
- Tela: `sheet/partials/share_sheet.blade.php`
- Controller: `SpreadsheetController::share|revokeShare`
- Aceite: token único de 32 chars; rota pública `/sheet/shared/{token}` valida expiração + dispara `SpreadsheetShared` notification ao convidado se email informado

### US-SHEET-004 — Post-share (revogar / listar shares ativos)
**Implementado em:** _parcial_ · `Modules/Spreadsheet/Http/Controllers/SpreadsheetController.php` · verificado@8af585a (2026-07-02) — falta listShares/revokeShare dedicados: listagem vive em getShareSpreadsheet (modal) e revogação é implícita em postShareSpreadsheet (delete dos desmarcados); invalidação de cache de token não aplica (token não existe — ver US-SHEET-003)
**Como** dono, **quero** ver lista de shares ativos da planilha e revogar individualmente, **pra** controlar acesso depois da publicação inicial.
- Controller: `SpreadsheetController::listShares|revokeShare`
- Aceite: revogar deleta linha em `sheet_spreadsheet_shares` E invalida cache do token

### US-SHEET-005 — Embed view (iframe-friendly)
**Implementado em:** _pendente_ — rota GET /sheet/embed/{token} e ajuste CSP frame-ancestors não construídos (nenhuma rota embed em Modules/Spreadsheet/Routes/web.php); depende do token público da US-SHEET-003
**Como** usuário avançado, **quero** embedar a planilha shareada num site/portal externo via iframe, **pra** exibir dados ao vivo sem custom dev.
- Route: GET `/sheet/embed/{token}` (sem chrome do oimpresso, X-Frame-Options ajustado)
- Aceite: render minimalista (sem nav/sidebar); `Content-Security-Policy` permite `frame-ancestors '*'` apenas pra rota embed

## Anti-padrões (NÃO fazer)

- ❌ Salvar `data` da planilha sem limite de tamanho (DoS via JSON gigante)
- ❌ `withoutGlobalScopes()` em listagem — vaza planilha cross-tenant
- ❌ Token de share previsível (não usar `auto_increment`; usar UUID v4 ou random_bytes)
- ❌ Embed sem CSP correto — risco clickjacking

## Testes existentes (Wave B)

- `Tests/Feature/MultiTenantIsolationTest.php`
- `Tests/Feature/ScaffoldTest.php`
- `Tests/Feature/SmokeRoutesTest.php`
