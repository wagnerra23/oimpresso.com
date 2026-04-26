# SPEC — Modulo Officeimpresso

> Status: superadmin-only, restaurado da 3.7 em 2026-04-23. **Contrato Delphi IMUTAVEL.** Recomendacao: **manter**.

## Proposito

Modulo interno da WR2 para autenticacao/licenciamento dos desktops clientes
(Delphi). Tela 2 do menu (`->order(2)`, logo apos Superadmin). Visivel
**somente para superadmin** via `auth()->user()->can('superadmin')`.

Vincula `business` (cliente UltimatePOS) com `licenca_computador` (maquinas
fisicas via serial de HD), audita acessos via event listeners + middleware,
e expoe endpoints OAuth (Passport password grant) consumidos pelo Delphi.

## Restricao critica — Delphi nao recompilavel

Conforme `memory/claude/feedback_delphi_contrato_imutavel.md` + ADR 0021:

- **NAO** alterar request/response shape de:
  - `POST /oauth/token`
  - `POST /connector/api/processa-dados-cliente` (resposta string `'S;msg'`/`'N;msg'`, NAO JSON)
  - `POST /connector/api/salvar-cliente`
  - `POST /connector/api/salvar-equipamento/{business_id}` (match por `hd + business_id + user_win`)
  - `GET/POST /connector/api/{tabela}/sync-(get|post)`
- Mudancas **aditivas** (campos opcionais, novos endpoints, hardening em rejeicao) sao OK.

## Rotas (web, prefix `/officeimpresso`)

Stack: `web, auth, SetSessionData, language, timezone, AdminSidebarMenu`. Sem
middleware de superadmin no router — **enforcement esta nos controllers**.

| Rota | Controller@metodo | Guarda |
|---|---|---|
| `catalogue-qr` | `Officeimpresso@generateQr` | `abort(403)` se nao superadmin + sem subscription |
| `client.*` (resource) | `ClientController@*` | `abort(403)` se nao superadmin |
| `licenca_computador.*` (resource + extras) | `LicencaComputador@*` | scope por `business_id` da sessao |
| `licenca_log.*` (resource + timeline) | `LicencaLog@index/show/timeline` | `abort_unless` business proprio |
| `businessall` | `LicencaComputador@businessall` | superadmin (lista todas empresas) |
| `docs` | view iframe | superadmin via `AdminSidebarMenu` |
| `install`, `install/update`, `install/uninstall` | `Install@*` (extends Base) | superadmin (ADR 0024) |

## Rotas API (`api.php`, prefix `/api/officeimpresso`)

Stack: `auth:api, log.desktop`.

- `GET /api/officeimpresso` — ping autenticado, retorna user
- `POST /api/officeimpresso/audit` — Delphi opt-in (audit events)

## Log de acesso (ADR 0018 v2)

- `LogPassportAccessToken` listener -> `licenca_log` event=`login_success`
- `LogDelphiAccess` middleware em `/connector/api/processa-dados-cliente` etc.
- Snapshot de bloqueio (`business_blocked`, `licenca_blocked`, `was_blocked`) no `metadata`

## Testes (este PR)

- `Modules/Officeimpresso/Tests/Feature/SuperadminGuardTest.php` — non-superadmin recebe 403 nas rotas protegidas; `/api/officeimpresso` exige Bearer.

## Pendencias

- Delphi enviar `hd` no body do `/oauth/token` (ADR 0019)
- Grupo economico (`business.matriz_id`, ADR 0020)
- Restaurar 147 controllers Connector da 3.7 (ADR 0021)
