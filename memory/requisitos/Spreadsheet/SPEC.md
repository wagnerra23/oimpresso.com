# Especificação funcional — Spreadsheet

> Pattern espelha `memory/requisitos/Project/SPEC.md` e `Accounting/SPEC.md`.
> Documentação viva — consumida pelo MemCofre em `/docs/modulos/Spreadsheet`.

## 1. Objetivo

Permitir que usuários criem planilhas (sheets) e compartilhem com colaboradores, roles ou todos. Sheet tem nome, conteúdo (JSON serializado), folder de organização e múltiplos tipos de share (user/role/todo).

## 2. Áreas funcionais

| Área | Controller | Ações públicas |
|---|---|---|
| Core | `SpreadsheetController` | `index`, `create`, `store`, `show`, `update`, `destroy`, `getShareSpreadsheet`, `postShareSpreadsheet`, `addFolder`, `moveToFolder` |

> Observação: `edit` é explicitamente excluído (`->except(['edit'])` na rota resource — ver `Modules/Spreadsheet/Routes/web.php:17`). Edição acontece em `show`.

## 3. User stories

> Convenção: `US-SPRE-NNN`.

### US-SPRE-001 · Listar planilhas próprias e compartilhadas

**Como** usuário com permissão `access.spreadsheet`
**Quero** ver minhas planilhas + as compartilhadas comigo (via user, role ou todo)
**Para** acessar o trabalho colaborativo

**Implementado em:** `Modules/Spreadsheet/Resources/views/index.blade.php`
**Testado em:** `tests/Feature/Modules/Spreadsheet/SpreadsheetRoutesAuthTest.php::test_admin_acessa_sheets_index_sem_500`

**Definition of Done:**
- [ ] Rota `/spreadsheet/sheets` exige autenticação
- [ ] Scope por `business_id` (R-SPRE-001)
- [ ] Query retorna sheets onde:
  - `created_by = user.id`, OU
  - existe `sheet_spreadsheet_shares` com `shared_id = user.role.id` e `shared_with = role`, OU
  - existe share com `shared_id = user.id` e `shared_with = user`, OU
  - existe share com `shared_id IN [todo_ids]` e `shared_with = todo`
- [ ] Superadmin vê todas

### US-SPRE-002 · Criar nova planilha

**Como** usuário com `create.spreadsheet`
**Quero** criar uma planilha em branco com nome + folder
**Para** começar uma nova análise

**Implementado em:** `Modules/Spreadsheet/Resources/views/create.blade.php`
**Testado em:** `tests/Feature/Modules/Spreadsheet/SpreadsheetCrudTest.php::test_store_sem_dados_retorna_validacao`

**Definition of Done:**
- [ ] FormRequest valida `name` obrigatório
- [ ] `business_id` e `created_by` injetados a partir da session
- [ ] Permissão `create.spreadsheet` ou `superadmin`

### US-SPRE-003 · Compartilhar planilha

**Como** dono da planilha
**Quero** compartilhar com um user, role ou todo (com perm read/write)
**Para** colaborar

**Implementado em:** `Modules/Spreadsheet/Resources/views/share.blade.php`
**Testado em:** `tests/Feature/Modules/Spreadsheet/SpreadsheetShareTest.php`

**Definition of Done:**
- [ ] Endpoint `POST /spreadsheet/post-share-sheet` aceita `sheet_id`, `shared_with` (user|role|todo), `shared_id`, `permission` (read|write)
- [ ] Notifica usuários afetados via `SpreadsheetShared` notification
- [ ] Apenas dono ou superadmin pode compartilhar

### US-SPRE-004 · Organizar em folders

**Como** usuário
**Quero** criar folders e mover sheets entre eles
**Para** organizar visualmente

**Implementado em:** Endpoint AJAX no Index page
**Testado em:** `tests/Feature/Modules/Spreadsheet/SpreadsheetCrudTest.php::test_add_folder_endpoint_existe` + `test_move_to_folder_endpoint_existe`

**Definition of Done:**
- [ ] `POST /spreadsheet/add-folder` cria folder com nome + `business_id`
- [ ] `POST /spreadsheet/move-to-folder` atualiza `sheet_spreadsheets.folder_id`

## 4. Regras de negócio (Gherkin)

### R-SPRE-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa /spreadsheet/sheets
Então só vê sheets com `business_id = A`
```

**Implementação:** `SpreadsheetController@index` (Modules/Spreadsheet/Http/Controllers/SpreadsheetController.php:56) faz `where('business_id', $business_id)`.
**Testado em:** `tests/Feature/Modules/Spreadsheet/SpreadsheetCrudTest.php::test_spreadsheet_model_tabela_tem_business_id`

### R-SPRE-002 · Autorização Spatie `access.spreadsheet`

```gherkin
Dado que um usuário não tem `access.spreadsheet` nem `superadmin`
Quando ele acessa /spreadsheet/sheets
Então recebe 403
```

**Implementação:** `SpreadsheetController@index:45` checa `superadmin || (subscription assetmanagement_module && can('access.spreadsheet'))`.
**Testado em:** `tests/Feature/Modules/Spreadsheet/SpreadsheetRoutesAuthTest.php`

### R-SPRE-003 · Sheets compartilhadas via shared_with discriminator

```gherkin
Dado uma sheet S compartilhada com role R
Quando user U pertence a R
Então U vê S na listagem
```

**Implementação:** `SpreadsheetController@index:60-76` faz LEFT JOIN com `sheet_spreadsheet_shares` filtrando por `shared_with` enum (user|role|todo).

### R-SPRE-004 · Edição direta sem rota /edit

```gherkin
Dado que o resource declara ->except(['edit'])
Quando user acessa /spreadsheet/sheets/{id}/edit
Então recebe 404 ou 405
```

**Implementação:** `Routes/web.php:17`.
**Testado em:** `tests/Feature/Modules/Spreadsheet/SpreadsheetRoutesAuthTest.php::test_sheets_edit_nao_existe`

## 5. Integrações

### 5.1. Hooks UltimatePOS registrados

- `modifyAdminMenu()` — sub-menu "Spreadsheet" na sidebar admin
- `superadmin_package()` — pacote `spreadsheet_module` no Superadmin
- `user_permissions()` — permissions Spatie: `access.spreadsheet`, `create.spreadsheet`

### 5.2. Integrações com Essentials (todos)

`SpreadsheetController@index:52` chama `moduleUtil->getModuleData('getAssignedTaskForUser', $user_id)['Essentials']` para obter os IDs de TODOs do usuário e exibir sheets compartilhadas com esses TODOs.

### 5.3. Notifications

- `Modules\Spreadsheet\Notifications\SpreadsheetShared` — disparada via `Notification::send($users, new SpreadsheetShared(...))` em `postShareSpreadsheet`.

## 6. Dados e entidades

| Modelo | Tabela | Finalidade |
|---|---|---|
| `Spreadsheet` | `sheet_spreadsheets` | Sheet com `name`, `body` (JSON), `folder_id`, `business_id`, `created_by` |
| `SpreadsheetShare` | `sheet_spreadsheet_shares` | Share com `shared_id`, `shared_with` (user|role|todo), `permission` (read|write) |

## 7. Decisões em aberto

- [ ] Body grande (>1MB) — guardar em S3 ou continuar em DB?
- [ ] Migrar para React + biblioteca tipo Luckysheet/Univer (alta complexidade)
- [ ] Versionamento de sheets (histórico de edições)?
- [ ] Sync colaborativo em tempo real (Pusher) — viable ou só read-on-share?

## 8. Histórico e notas

- **2026-04-22** — `requisitos/Spreadsheet.md` gerado automaticamente.
- **2026-04-26** — SPEC.md criado junto com testes Pest do batch 6 (cobre auth + share + folders + tenancy).

---
_Tests cobrindo este módulo:_
- `tests/Feature/Modules/Spreadsheet/SpreadsheetRoutesAuthTest.php`
- `tests/Feature/Modules/Spreadsheet/SpreadsheetCrudTest.php`
- `tests/Feature/Modules/Spreadsheet/SpreadsheetShareTest.php`
