# Índice — Requisitos funcionais por módulo

> Documentação viva, complementa `memory/modulos/` (spec técnica)
> com foco no **valor de negócio** — user stories, regras Gherkin, DoD.
>
> **Atualizado em 2026-04-24**

## Resumo

| Categoria | Módulos | % |
|---|---:|---:|
| 🚀 Spec-ready (futuros) | 4 | 12% |
| 🟢 Ativos | 14 | 42% |
| ⚪ Inativos (presentes) | 5 | 15% |
| ⚠️ Legados (ausentes) | 10 | 30% |
| **Total** | **33** | 100% |

## 🚀 Módulos spec-ready (promovidos de `_Ideias/` em 2026-04-24)

Espinha dorsal do roadmap de faturamento (2 anos). Ver [_Roadmap_Faturamento.md](_Roadmap_Faturamento.md).

- **[Financeiro](Financeiro/)** — Tier 1A · Foundational · 5-6 sem · R$ 199-599 + take rate 0,5%
- **[NfeBrasil](NfeBrasil/)** — Tier 1B · Compliance-forced · 5-6 sem · R$ 99-599
- **[RecurringBilling](RecurringBilling/)** — Tier 2 · Take rate volume · 12-14 sem · R$ 149-999 + take rate 0,8%
- **[LaravelAI](LaravelAI/)** — Tier 3 · Multiplier · 6-8 sem · R$ 199-599 (add-on)

## 🟢 Módulos ativos

Clique para ver requisitos funcionais.

- [Accounting](Accounting.md)
- [AssetManagement](AssetManagement.md)
- [Cms](Cms.md)
- [Connector](Connector.md)
- [Crm](Crm.md)
- [Essentials](Essentials.md)
- [Manufacturing](Manufacturing.md)
- [PontoWr2](PontoWr2.md) — pasta completa em `PontoWr2/`
- [MemCofre](MemCofre/) — Cofre de Memórias / docs vivos
- [ProductCatalogue](ProductCatalogue.md)
- [Project](Project.md)
- [Repair](Repair.md)
- [Spreadsheet](Spreadsheet.md)
- [Superadmin](Superadmin.md)
- [Woocommerce](Woocommerce.md)

## ⚪ Módulos inativos (presentes no branch atual)

- [AiAssistance](AiAssistance.md) — descartar (substituído por LaravelAI)
- [Grow](Grow.md) — prioridade
- [IProduction](IProduction.md)
- [Officeimpresso](Officeimpresso.md) — Superadmin only
- [Writebot](Writebot.md)

## ⚠️ Módulos legados (ausentes — decidir ressuscitar/deprecar)

Boleto e Fiscal foram **superados** pelos novos spec-ready (RecurringBilling/Boleto sub-módulo + NfeBrasil). Manter pra histórico.

- [BI](BI.md) ⚠️
- [Boleto](Boleto.md) ⚠️ — **superado por** `RecurringBilling/` (sub-módulo Boleto) e `Financeiro/` (boleto avulso)
- [Chat](Chat.md) ⚠️
- [Dashboard](Dashboard.md) ⚠️
- [Fiscal](Fiscal.md) ⚠️ — **superado por** `NfeBrasil/`
- [Help](Help.md) ⚠️
- [Jana](Jana.md) ⚠️
- [Knowledgebase](Knowledgebase.md) ⚠️
- [Officeimpresso1](Officeimpresso1.md) ⚠️
- [codecanyon-32094844-perfect-support-ticketing-document-management-system](codecanyon-32094844-perfect-support-ticketing-document-management-system.md) ⚠️

## Como trabalhar com estes arquivos

1. **Formato estruturado** — cada arquivo tem frontmatter YAML + user stories (`US-XXX-NNN`)
   + regras Gherkin (`R-XXX-NNN`) + DoD rastreável com a tela React.
2. **Pasta vs arquivo plano** — Módulos spec-ready/grandes (PontoWr2, MemCofre, Financeiro,
   NfeBrasil, RecurringBilling, LaravelAI) têm pasta com README/SPEC/ARCHITECTURE/GLOSSARY/
   CHANGELOG/adr/. Módulos legados têm um único `<Modulo>.md` plano.
3. **Fonte única da verdade funcional** — quando o código muda, atualizar o requisito.
4. **Regerar** — `php artisan module:requirements` gera arquivos faltantes
   sem sobrescrever edições manuais. Use `--force` com cuidado.
5. **Módulo MemCofre** (`/memcofre`) consome esses arquivos e linka com evidências
   (screenshots de bug, chat logs, erros reportados).
6. **Pasta `_Ideias/`** — incubadora de módulos novos antes de promover (ver [_Ideias/README.md](_Ideias/README.md))

## Padrão de ADRs (separação por categoria)

Cada módulo spec-ready tem ADRs separados por **assunto** (não monolíticos):

```
adr/
├── arq/   # decisões de arquitetura (módulo, eventos, integração)
├── tech/  # decisões técnicas (idempotência, lockForUpdate, embeddings)
└── ui/    # decisões de interface (layout, fluxo, componente)
```

Numeração separada por categoria: `ARQ-0001`, `TECH-0001`, `UI-0001`.

---
_Regerar índice: `php artisan module:requirements`_
