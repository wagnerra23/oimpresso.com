---
name: Diff 3.7 vs 6.7 — controllers Officeimpresso + Connector (API Delphi)
description: O que mudou e o que foi mantido idêntico entre a API legada 3.7 e a restaurada 6.7. Base pra decisões de manutenção e troubleshooting.
type: reference
originSessionId: 0922b4af-6c32-45e6-ae30-5d09580ae4ca
---
**TL;DR:** Os controllers e endpoints que o Delphi consome estão **funcionalmente iguais ao 3.7**. Só adicionamos infraestrutura (logging, enforcement, observability) POR CIMA, sem alterar a wire.

## Controllers restaurados do 3.7 em `Modules/Connector/` — idênticos + adaptações L13

### `Connector/Http/Controllers/Api/LicencaComputadorController.php`
- Métodos: `ProcessaDadosCliente`, `saveEquipamento`, CRUD — **zero mudança de lógica**
- Única adaptação: `use App\Models\Busines` (namespace errado + typo no 3.7) → `use App\Business`
- Match por `hd + business_id + user_win` **preservado** (crítico pro Delphi)

### `Connector/Http/Controllers/Api/BusinessController.php`
- `saveBusiness`: **lógica preservada** (Package + Subscription + Business + User)
- Adaptação obrigatória: `User::createOwnerUser()` não existe no 6.7 → substituído por `User::create_user([...])` com payload completo (username temp + email + senha random)
- Fix typo linha 237 (faltava `;`)

### `Connector/Http/Controllers/Api/BaseApiController.php`
- Só corrigido deprecation PHP 8.1+: `$callback = null` → `?callable $callback = null`

## Controllers no `Modules/Officeimpresso/` (já existiam no 6.7 v1.0.0)

### `Officeimpresso/Http/Controllers/LicencaComputadorController.php`
- 3.7 tinha 5 métodos; `Route::resource` espera 7 → adicionei stubs `create()` e `edit()`

### `Officeimpresso/Http/Controllers/LicencaLogController.php`
- 3.7: stub 501 (model `LicencaLog` nem existia)
- 6.7: CRUD completo com DataTables AJAX + KPIs + agregação por máquina

## API endpoints — o que o Delphi vê

| Endpoint | 3.7 | 6.7 | Mudança contrato |
|---|---|---|---|
| `POST /oauth/token` | ✓ Passport v5 | ✓ Passport v13 | **Nenhuma** (mesmo payload e response). 3 fixes server-side invisíveis: `enablePasswordGrant()`, `provider='users'`, rehash secrets — ADR 0019 |
| `POST /connector/api/processa-dados-cliente` | ✓ | ✓ restaurado | **Nenhuma** — response STRING `'S;msg'` ou `'N;motivo'` preservado |
| `POST /connector/api/salvar-cliente` | ✓ | ✓ restaurado | **Nenhuma** |
| `POST /connector/api/salvar-equipamento/{id}` | ✓ | ✓ restaurado | **Nenhuma** |
| `POST /connector/api/{tabela}/sync-post` | ✓ vários | ✓ parciais | Existem no 6.7: business-location, contactapi, product, sell, expense, cash-register. **Faltam:** equipamento_impressora/sync-*, historico_impressoes/sync-* |
| `GET /api/officeimpresso` | ✗ | ✓ novo | Bearer, retorna user — aditivo, Delphi ignora |
| `POST /api/officeimpresso/audit` | ✗ | ✓ novo | Opt-in pro Delphi futuro — aditivo |

## Infraestrutura NOVA (não existia no 3.7)

Zero impacto no contrato Delphi — tudo observation + enforcement layer:

1. **`Listeners/LogPassportAccessToken`** — escuta `AccessTokenCreated`, grava `licenca_log.event=login_success`
2. **`Http/Middleware/LogDesktopAccess`** — aplica em `/api/officeimpresso/*`
3. **`Http/Middleware/LogDelphiAccess`** — aplica em `/connector/api/processa-dados-cliente`, `salvar-cliente`, `salvar-equipamento/{id}` — extrai `HD` da estrutura JSON Delphi (3 formatos)
4. **`Console/ParseLicencaLogCommand`** — parseia `storage/logs/laravel.log` atrás de OAuth errors
5. **`Http/Controllers/AuditController`** — endpoint opt-in `/api/officeimpresso/audit`
6. **Tabela `licenca_log`** + model + UI `/officeimpresso/licenca_log` (Status de Login por Máquina)
7. **`User::validateForPassportPasswordGrant`** override — rejeita `/oauth/token` quando `business.officeimpresso_bloqueado=1`, só pra clients desktop (39, 107). Response OAuth padrão (400 invalid_grant), idêntico ao que Delphi já trata como "não autenticou".
8. **Testes Pest regression guards** (9 passes) em `tests/Feature/Connector/DelphiOImpressoContractTest.php` — protegem mudanças acidentais nos controllers restaurados (regex no source).

## Route names — mudança técnica cosmética

- **3.7:** rotas sem `->name()`
- **6.7:** adicionados prefixos `connector.*` (ex: `connector.business-location.index`) pra resolver colisão em `php artisan route:cache`
- **URLs / endpoints permanecem idênticos** — só label interno pra `route()` helper

## Como usar esta nota

- Modificar controller do Connector → **ler primeiro** a seção "adaptações L13" pra não reintroduzir bugs removidos
- Adicionar endpoint novo → criar **aditivo** (não substituir); Delphi ignora rotas/campos desconhecidos
- Debug Delphi não conectando → checar ordem: (1) `/oauth/token` responde 200? (2) grant=password habilitado? (3) provider='users' no client? (4) secret hashed? (5) business bloqueado?
- Restaurar outro endpoint Connector do 3.7 → `git show origin/3.7-com-nfe:Modules/Connector/Http/Controllers/Api/X.php` e copiar; atenção em refs `App\Models\Busines` e `User::createOwnerUser`

## Armadilha crítica — identificação de cliente (multi-tenant)

O Delphi **compartilha 1 master user** entre todas as instalações. Consequências:

- `/oauth/token` **NÃO identifica o cliente** — o `access_token` pertence ao master user (ex: WR2). Usar `user->business_id` em log/enforcement daria sempre o mesmo business, errado.
- Identidade **REAL** vem do body de `/connector/api/processa-dados-cliente` (CNPJ em `NOME_TABELA=EMPRESA`, HD em `NOME_TABELA=LICENCIAMENTO`).
- Middleware `LogDelphiAccess` extrai CNPJ+HD do body; controller `LicencaLogController` agrega sobre `source IN ('delphi_middleware','desktop_audit')` — nunca sobre `login_success/login_error` (esses seriam por user_id = master).
- Regra prática: em qualquer novo enforcement/log **derivar o cliente do body**, nunca de `request()->user()->business_id`.

## Campos úteis de `licenca_computador` (registry)

Esta tabela é a **fonte de verdade** pra registry de máquinas (fazem parte do contrato que Delphi preserva via `saveEquipamento` e `processa-dados-cliente`):

- `hd` — serial do disco, chave única por máquina (+ business_id + user_win)
- `user_win` — hostname Windows (amigável pro user identificar)
- `hostname` — hostname alternativo
- `ip_interno` — IP na rede local da empresa
- `versao_exe` — versão do executável Delphi (Office Impresso)
- `versao_banco` — versão do banco local
- `sistema_operacional` — Windows XP / 7 / 10 / 11
- `sistema` — sistema/licença (descritivo)
- `bloqueado` — bit por máquina (separado do bloqueio de empresa)
- `dt_ultimo_acesso` — timestamp do último acesso (atualizado em saveEquipamento)
- `dt_validade` — fim da licença
- `serial` — legado (antigo sistema de serial por máquina, pré-HD)

A tela `/officeimpresso/licenca_log` usa esse registry como fonte primária e enriquece cada linha com `MAX(created_at)` do `licenca_log` onde `source=delphi_middleware AND endpoint LIKE '%processa-dados-cliente%'`.

## Relacionado

- ADR 0017 — Restauração Officeimpresso 3.7 → 6.7
- ADR 0019 — Passport v10→v13 auth Delphi (RESOLVIDO)
- ADR 0021 — Contrato real da API Delphi (3 gerações)
- `feedback_delphi_contrato_imutavel.md` — regra durável: NÃO alterar contrato Delphi
- `reference_branch_3_7.md` — 147 arquivos Connector ainda não restaurados
- `tests/Feature/Connector/DelphiOImpressoContractTest.php` — regression guards
