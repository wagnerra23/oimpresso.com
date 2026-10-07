---
id: requisitos-configuracoes-locais-parity
titulo: Paridade de migração — /business-location (Locais comerciais) Blade↔React
tipo: parity
status: active
owner: W
criado: '2026-10-07'
tela: /business-location
related:
  - ../_DesignSystem/PARITY-TEMPLATE.md
  - ./RUNBOOK-locais.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0320-programa-ondas-regua-correcao'
---

# Paridade — Locais comerciais (`/business-location`)

## Metadados

- **Tela React:** `resources/js/Pages/Configuracoes/Locais/Index.tsx` (F3 — ainda não existe)
- **Blade legado:** `resources/views/business_location/{index,create,edit}.blade.php`
- **Controller (persistência):** `App\Http\Controllers\BusinessLocationController` — `store()`, `update()`, `activateDeactivateLocation()`
- **Rota nova / legada:** a mesma `Route::resource('business-location')` + `activate-deactivate/{id}`; a Blade segue com a flag desligada
- **Auditado em:** `2026-10-07` · **por:** [CL]

## Mapa campo-a-campo

| # | Feature do Blade | Está no React? | Evidência | Severidade se perdido | Defendido por |
|---|---|---|---|---|---|
| 1 | Lista só os locais do negócio | ⬜ F3 | `index()` | **alta** | `LocaisBaselineTest` |
| 2 | Sem `access_all_locations`, só os locais com `location.<id>` direto | ⬜ F3 | `User::permitted_locations()` | **alta** | `LocaisBaselineTest` |
| 3 | Cadastrar grava no negócio e cria a permissão `location.<id>` | ⬜ F3 | `store()` | **alta** | `LocaisBaselineTest` |
| 4 | Esquema e layout de fatura obrigatórios e do próprio negócio | ⬜ F3 | `validateInvoiceRefs()` | **alta** | `LocaisBaselineTest` |
| 5 | Quota de locais do pacote barra o cadastro | ⬜ F3 | `isQuotaAvailable('locations')` | **alta** | UC da F3 |
| 6 | Editar só altera local do negócio | ⬜ F3 | `update()` | **alta** | `LocaisBaselineTest` |
| 7 | Ativar/desativar só alcança local do negócio | ⬜ F3 | `activateDeactivateLocation()` | **alta** | `LocaisBaselineTest` |
| 8 | CNPJ, razão social, fantasia, IE, IM por local | ⬜ F3 | `store()`/`update()` | **alta** | UC da F3 |
| 9 | Tabela de preço, layouts de PDV e venda, esquema de venda | ⬜ F3 | `store()`/`update()` | média | UC da F3 |
| 10 | Formas de pagamento → conta (`default_payment_accounts`) | ⬜ F3 | `store()`/`update()` (`json_encode`) | média | UC da F3 |
| 11 | Ref. do local gerada quando vazia; única no negócio | ⬜ F3 | `generateReferenceNumber` · `checkLocationId` | média | — |
| 12 | Sem `business_settings.access` → 403 | ⬜ F3 | todas as ações | **alta** | `LocaisBaselineTest` |

`⬜ F3` = a coluna React é preenchida no PR da F3, que cita o UC de cada linha `alta`.
