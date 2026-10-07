---
id: requisitos-configuracoes-esquemas-fatura-parity
titulo: Paridade de migração — /invoice-schemes (Esquemas de fatura) Blade↔React
tipo: parity
status: active
owner: W
criado: '2026-10-07'
tela: /invoice-schemes
related:
  - ../_DesignSystem/PARITY-TEMPLATE.md
  - ./RUNBOOK-esquemas-fatura.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0320-programa-ondas-regua-correcao'
---

# Paridade — Esquemas de fatura (`/invoice-schemes`)

## Metadados

- **Tela React:** `resources/js/Pages/Configuracoes/EsquemasFatura/Index.tsx` (F3 — ainda não existe)
- **Blade legado:** `resources/views/invoice_scheme/{index,create,edit}.blade.php`
- **Controller (persistência):** `App\Http\Controllers\InvoiceSchemeController` — `store()`, `update()`, `destroy()`, `setDefault()`
- **Rota nova / legada:** a mesma `Route::resource('invoice-schemes')` + `set_default/{id}`; a Blade segue com a flag desligada
- **Auditado em:** `2026-10-07` · **por:** [CL]

## Mapa campo-a-campo

| # | Feature do Blade | Está no React? | Evidência | Severidade se perdido | Defendido por |
|---|---|---|---|---|---|
| 1 | Lista só os esquemas do negócio, padrão marcado | ⬜ F3 | `index()` | **alta** | `EsquemasFaturaBaselineTest` |
| 2 | Cadastrar grava no negócio: nome, tipo (blank/ano), prefixo, número inicial, dígitos, tipo de número | ⬜ F3 | `store()` | **alta** (numeração) | `EsquemasFaturaBaselineTest` |
| 3 | Cadastrar como padrão desmarca o anterior | ⬜ F3 | `store()` | **alta** | `EsquemasFaturaBaselineTest` |
| 4 | Editar/excluir/tornar padrão só alcançam o negócio; padrão não se exclui | ⬜ F3 | #8979 | **alta** | `EsquemaFaturaTenantTest` |
| 5 | Aba de layouts: só os do negócio, com os locais que os usam | ⬜ F3 | `index()` (`InvoiceLayout::with('locations')`) | média | `EsquemasFaturaBaselineTest` |
| 6 | Prefixo com ano no esquema anual | ⬜ F3 | `index()` (`editColumn('prefix')`) | baixa | — |
| 7 | Sem `invoice_settings.access` → 403 | ⬜ F3 | todas as ações | **alta** | `EsquemasFaturaBaselineTest` |

`⬜ F3` = a coluna React é preenchida no PR da F3, que cita o UC de cada linha `alta`.
