# Índice — Requisitos funcionais por módulo

> Documentação viva, complementa `memory/modulos/` (spec técnica)
> com foco no **valor de negócio** — user stories, regras Gherkin, DoD.
>
> **Atualizado em 2026-04-22 16:35**

## Resumo

| Categoria | Módulos | % |
|---|---:|---:|
| 🟢 Ativos | 14 | 48% |
| ⚪ Inativos (presentes) | 5 | 17% |
| ⚠️ Legados (ausentes) | 10 | 34% |
| **Total** | **29** | 100% |

## Módulos ativos

Clique para ver requisitos funcionais.

- [Accounting](Accounting.md)
- [AssetManagement](AssetManagement.md)
- [Cms](Cms.md)
- [Connector](Connector.md)
- [Crm](Crm.md)
- [Essentials](Essentials.md)
- [Manufacturing](Manufacturing.md)
- [PontoWr2](PontoWr2.md)
- [ProductCatalogue](ProductCatalogue.md)
- [Project](Project.md)
- [Repair](Repair.md)
- [Spreadsheet](Spreadsheet.md)
- [Superadmin](Superadmin.md)
- [Woocommerce](Woocommerce.md)

## Módulos inativos (presentes no branch atual)

- [AiAssistance](AiAssistance.md)
- [Grow](Grow.md)
- [IProduction](IProduction.md)
- [Officeimpresso](Officeimpresso.md)
- [Writebot](Writebot.md)

## Módulos legados (ausentes — decidir ressuscitar/deprecar)

- [BI](BI.md) ⚠️
- [Boleto](Boleto.md) ⚠️
- [Chat](Chat.md) ⚠️
- [Dashboard](Dashboard.md) ⚠️
- [Fiscal](Fiscal.md) ⚠️
- [Help](Help.md) ⚠️
- [Jana](Jana.md) ⚠️
- [Knowledgebase](Knowledgebase.md) ⚠️
- [Officeimpresso1](Officeimpresso1.md) ⚠️
- [codecanyon-32094844-perfect-support-ticketing-document-management-system](codecanyon-32094844-perfect-support-ticketing-document-management-system.md) ⚠️

## Como trabalhar com estes arquivos

1. **Formato estruturado** — cada arquivo tem frontmatter YAML + user stories (`US-XXX-NNN`)
   + regras Gherkin (`R-XXX-NNN`) + DoD rastreável com a tela React.
2. **Fonte única da verdade funcional** — quando o código muda, atualizar o requisito.
3. **Regerar** — `php artisan module:requirements` gera arquivos faltantes
   sem sobrescrever edições manuais. Use `--force` com cuidado.
4. **Módulo DocVault** (`/docs`) consome esses arquivos e linka com evidências
   (screenshots de bug, chat logs, erros reportados).

---
_Regerar índice: `php artisan module:requirements`_
