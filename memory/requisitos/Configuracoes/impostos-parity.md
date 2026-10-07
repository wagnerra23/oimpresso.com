---
id: requisitos-configuracoes-impostos-parity
titulo: Paridade de migração — /tax-rates (Impostos) Blade↔React
tipo: parity
status: active
owner: W
criado: '2026-10-07'
tela: /tax-rates
related:
  - ../_DesignSystem/PARITY-TEMPLATE.md
  - ./RUNBOOK-impostos.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0320-programa-ondas-regua-correcao'
---

# Paridade — Impostos (`/tax-rates`)

## Metadados

- **Tela React:** `resources/js/Pages/Configuracoes/Impostos/Index.tsx` (F3 — ainda não existe)
- **Blade legado:** `resources/views/tax_rate/{index,create,edit}.blade.php` + `tax_group/*` (modais)
- **Controller (persistência):** `App\Http\Controllers\TaxRateController` — `store()`, `update()`, `destroy()`; grupos em `GroupTaxController` (fora do prefixo)
- **Rota nova / legada:** a mesma `Route::resource('tax-rates')`; a Blade segue com a flag desligada
- **Auditado em:** `2026-10-07` · **por:** [CL]

## Mapa campo-a-campo

| # | Feature do Blade | Está no React? | Evidência | Severidade se perdido | Defendido por |
|---|---|---|---|---|---|
| 1 | Lista só as alíquotas do negócio | ⬜ F3 | `index()` | **alta** | `ImpostosBaselineTest` |
| 2 | Alíquota gravada pelo `num_uf` do texto pt-BR | ⬜ F3 | `store()`/`update()` | **alta** (valor) | `ImpostosBaselineTest` (dois caminhos) |
| 3 | Cadastrar grava no negócio, com `created_by` e "só em grupo" | ⬜ F3 | `store()` | **alta** | `ImpostosBaselineTest` |
| 4 | Editar recalcula a alíquota dos grupos que usam a alíquota | ⬜ F3 | `update()` + `TaxUtil::updateGroupTaxAmount` | **alta** (valor) | `ImpostosBaselineTest` |
| 5 | Excluir é recusado se a alíquota está em grupo | ⬜ F3 | `destroy()` | **alta** | `ImpostosBaselineTest` |
| 6 | Editar/excluir só alcançam alíquota do negócio | ⬜ F3 | `update()`/`destroy()` | **alta** | `ImpostosBaselineTest` |
| 7 | Permissões `tax_rate.view/create/update/delete` por ação | ⬜ F3 | controller + `@can` da Blade | **alta** | `ImpostosBaselineTest` (403) · UC da F3 |
| 8 | Tabela de grupos (nome, alíquota, sub-impostos) | ⬜ F3 | `GroupTaxController::index` | média | UC da F3 |
| 9 | Aviso "Configuração Fiscal Avançada" com NF-e ativa | ⬜ F3 | `tax_rate/index.blade.php` | baixa | — |

`⬜ F3` = a coluna React é preenchida no PR da F3, que cita o UC de cada linha `alta`.
