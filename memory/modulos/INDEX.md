# Índice de Specs dos Módulos

Gerado por `php artisan module:specs` em 2026-04-22 14:14.

**Total:** 29 módulos únicos encontrados em todas as branches conhecidas (atual, `main-wip-2026-04-22`, `origin/3.7-com-nfe`, `origin/6.7-bootstrap`).

## 🟢 Ativos (15)

| # | Módulo | Prioridade | Risco | Rotas | Views | Migrations | Permissões | Hooks |
|--:|---|---|---|--:|--:|--:|--:|--:|
| 1 | [AiAssistance](AiAssistance.md) | alta (pequeno, ganho rápido) | baixo | 8 | 6 | 2 | 1 | 3 |
| 2 | [ProductCatalogue](ProductCatalogue.md) | média | médio | 7 | 8 | 1 | 0 | 2 |
| 3 | [Spreadsheet](Spreadsheet.md) | média | médio | 9 | 7 | 4 | 2 | 3 |
| 4 | [AssetManagement](AssetManagement.md) | média | médio | 10 | 17 | 7 | 6 | 4 |
| 5 | [Woocommerce](Woocommerce.md) | média | médio | 19 | 13 | 13 | 5 | 3 |
| 6 | [Manufacturing](Manufacturing.md) | média | médio | 14 | 20 | 13 | 4 | 3 |
| 7 | [Cms](Cms.md) | baixa (grande, fazer por último ou dividir) | alto | 12 | 45 | 5 | 0 | 1 |
| 8 | [Connector](Connector.md) | baixa (grande, fazer por último ou dividir) | alto | 55 | 2 | 1 | 0 | 2 |
| 9 | [Project](Project.md) | baixa (grande, fazer por último ou dividir) | alto | 20 | 43 | 11 | 3 | 4 |
| 10 | [PontoWr2](PontoWr2.md) | baixa (grande, fazer por último ou dividir) | alto | 40 | 26 | 8 | 5 | 3 |
| 11 | [Superadmin](Superadmin.md) | baixa (grande, fazer por último ou dividir) | alto | 33 | 45 | 12 | 1 | 2 |
| 12 | [Repair](Repair.md) | baixa (grande, fazer por último ou dividir) | alto | 30 | 52 | 16 | 12 | 7 |
| 13 | [Crm](Crm.md) | baixa (grande, fazer por último ou dividir) | alto | 52 | 68 | 26 | 13 | 3 |
| 14 | [Essentials](Essentials.md) | baixa (grande, fazer por último ou dividir) | alto | 53 | 87 | 36 | 23 | 5 |
| 15 | [Accounting](Accounting.md) | baixa (grande, fazer por último ou dividir) | alto | 69 | 91 | 21 | 12 | 3 |

## ⚪ Inativos no branch atual (5)

_Existem em `Modules/` mas com flag `false` em `modules_statuses.json`._

| # | Módulo | Prioridade | Risco | Rotas | Views | Migrations | Permissões | Hooks |
|--:|---|---|---|--:|--:|--:|--:|--:|
| 1 | [Officeimpresso](Officeimpresso.md) | baixa (desativado) | baixo | 7 | 8 | 1 | 0 | 2 |
| 2 | [Officeimpresso1](Officeimpresso1.md) | baixa (desativado) | baixo | 16 | 8 | 2 | 0 | 2 |
| 3 | [IProduction](IProduction.md) | baixa (desativado) | baixo | 14 | 20 | 0 | 0 | 0 |
| 4 | [Writebot](Writebot.md) | baixa (desativado) | baixo | 14 | 20 | 0 | 0 | 0 |
| 5 | [Grow](Grow.md) | baixa (desativado) | baixo | 797 | 957 | 1 | 1 | 3 |

## ❌ Perdidos na migração 3.7 → 6.7 (9)

_**Existem em branches antigas** (`main-wip-2026-04-22` ou `origin/3.7-com-nfe`) **mas não na branch atual 6.7-react.**_
_Potenciais funcionalidades que ficaram para trás. Decidir se trazer de volta ou abandonar._

| Módulo | main-wip | 3.7 | 6.7-bootstrap | Ação sugerida |
|---|:-:|:-:|:-:|---|
| [BI](BI.md) | ✅ | ✅ | — | (definir) |
| [Boleto](Boleto.md) | ✅ | ✅ | — | (definir) |
| [Chat](Chat.md) | ✅ | ✅ | — | (definir) |
| [Dashboard](Dashboard.md) | ✅ | ✅ | — | (definir) |
| [Fiscal](Fiscal.md) | ✅ | ✅ | — | (definir) |
| [Help](Help.md) | ✅ | ✅ | — | (definir) |
| [Jana](Jana.md) | ✅ | ✅ | — | (definir) |
| [Knowledgebase](Knowledgebase.md) | ✅ | ✅ | — | (definir) |
| [codecanyon-32094844-perfect-support-ticketing-document-management-system](codecanyon-32094844-perfect-support-ticketing-document-management-system.md) | ✅ | ✅ | — | (definir) |

## Como usar

1. Abra o spec de um módulo (coluna 'Módulo' é link).
2. Na seção **'Gaps & próximos passos'**, preencha customizações suas conhecidas.
3. Compare com o código original do UltimatePOS 6.7 para identificar o diff (seção automática).
4. Use 'Prioridade' e 'Risco' para definir ordem de migração.

## Regenerar

```bash
php artisan module:specs              # todos
php artisan module:specs PontoWr2     # um só
php artisan module:specs --stdout     # ver sem salvar
```
