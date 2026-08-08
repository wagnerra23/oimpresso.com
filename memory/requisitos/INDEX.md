---
id: requisitos-index
---

# Índice — Requisitos funcionais por módulo

> Documentação viva, complementa `memory/modulos/` (spec técnica)
> com foco no **valor de negócio** — user stories, regras Gherkin, DoD.
>
> **Atualizado em 2026-04-24** — snapshot datado. Para o estado vivo por módulo use a porta viva (`npm run screen-coverage:report` / `casos:report`) e o retrato gerado em [`../reference/PAINEL-SISTEMA.md`](../reference/PAINEL-SISTEMA.md).

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

- **[Financeiro](Financeiro/)** — Tier 1A · Foundational · 5-6 sem · R$ [redacted Tier 0]-599 + take rate 0,5%
- **[NfeBrasil](NfeBrasil/)** — Tier 1B · Compliance-forced · 5-6 sem · R$ [redacted Tier 0]-599
- **[RecurringBilling](RecurringBilling/)** — Tier 2 · Take rate volume · 12-14 sem · R$ [redacted Tier 0]-999 + take rate 0,8%
- **[LaravelAI](LaravelAI/)** — Tier 3 · Multiplier · 6-8 sem · R$ [redacted Tier 0]-599 (add-on)

## 🟢 Módulos ativos

Clique para ver requisitos funcionais.

- [Accounting](Accounting/)
- [AssetManagement](AssetManagement/) — spec plana `AssetManagement.md` ⚰️ subtraída (B6, 2026-08-02); dono vivo é a pasta
- [Cms](Cms/) — spec plana `Cms.md` ⚰️ subtraída (B6)
- [Connector](Connector/) — spec plana `Connector.md` ⚰️ subtraída (B6)
- [Crm](Crm/)
- [Essentials](Essentials/)
- [Manufacturing](Manufacturing/)
- [PontoWr2](PontoWr2/) — pasta completa
- [MemCofre](MemCofre/) ⚰️→SRS — histórico. O módulo (renomeado `Modules/SRS`) foi **removido em 2026-07-29** ([ADR 0357](../decisions/0357-deprecar-srs-sucessor-kb-jana-governance.md)); sucessores: KB (acervo) · Jana (chat) · Governance (validação). Destino da pasta em triagem — 22 dos 33 docs ainda são servidos pelo RAG.
- [ProductCatalogue](ProductCatalogue/) — spec plana `ProductCatalogue.md` ⚰️ subtraída (B6)
- [ProjectMgmt](ProjectMgmt/) — (era `Project.md`, legado UltimatePOS removido)
- [Repair](Repair/)
- [Spreadsheet](Spreadsheet/) — spec plana `Spreadsheet.md` ⚰️ subtraída (B6)
- [Superadmin](Superadmin/) — spec plana `Superadmin.md` ⚰️ subtraída (B6)
- [Woocommerce](Woocommerce/) — spec plana `Woocommerce.md` ⚰️ subtraída (B6)

## ⚪ Módulos inativos (presentes no branch atual)

- AiAssistance — ⚰️ `AiAssistance.md` subtraída (B6); substituído por [LaravelAI](LaravelAI/)
- [Grow](Grow/) — spec plana `Grow.md` ⚰️ subtraída (B6)
- IProduction — ⚰️ `IProduction.md` subtraída (B6); produção industrial vive em [Manufacturing](Manufacturing/)
- [Officeimpresso](Officeimpresso/) — spec plana `Officeimpresso.md` ⚰️ subtraída (B6); Superadmin only
- Writebot — ⚰️ `Writebot.md` subtraída (B6); sem sucessor ativo

## ⚠️ Módulos legados (ausentes — decidir ressuscitar/deprecar)

Boleto e Fiscal foram **superados** pelos novos spec-ready (RecurringBilling/Boleto sub-módulo + NfeBrasil). Manter pra histórico.

> **B6 (2026-08-02):** as specs planas ⚰️ abaixo foram subtraídas (lápide+relink) — o conteúdo original está no git; cada uma redireciona pro dono vivo. `Officeimpresso1.md` **não é fóssil** (ref. histórica ADR 0017) e fica intacta.

- [BI](BI/) ⚠️ — spec plana `BI.md` ⚰️ subtraída (B6)
- Boleto ⚠️ — ⚰️ `Boleto.md` subtraída (B6); **superado por** [RecurringBilling](RecurringBilling/) (sub-módulo Boleto) e [Financeiro](Financeiro/) (boleto avulso)
- [Chat](Chat/) ⚠️ — spec plana `Chat.md` ⚰️ subtraída (B6); sucessor conversacional é a Jana
- [Dashboard](Dashboard/) ⚠️ — spec plana `Dashboard.md` ⚰️ subtraída (B6)
- [Fiscal](Fiscal/) ⚠️ — spec plana `Fiscal.md` ⚰️ subtraída (B6); **superado por** `NfeBrasil/`
- Help ⚠️ — ⚰️ `Help.md` subtraída (B6); sem sucessor ativo
- [Jana](Jana/) ⚠️ — spec plana `Jana.md` ⚰️ subtraída (B6); dono vivo é a pasta
- Knowledgebase ⚠️ — ⚰️ `Knowledgebase.md` subtraída (B6); sucessor é [KB](KB/)
- [Officeimpresso1](Officeimpresso1.md) ⚠️ — **preservada** (ref. histórica ADR 0017, não é fóssil)
- codecanyon-32094844-… ⚠️ — ⚰️ subtraída (B6); doc de produto externo (CodeCanyon), não é módulo

## Como trabalhar com estes arquivos

1. **Formato estruturado** — cada arquivo tem frontmatter YAML + user stories (`US-XXX-NNN`)
   + regras Gherkin (`R-XXX-NNN`) + DoD rastreável com a tela React.
2. **Pasta vs arquivo plano** — Módulos spec-ready/grandes (PontoWr2, MemCofre, Financeiro,
   NfeBrasil, RecurringBilling, LaravelAI) têm pasta com README/SPEC/ARCHITECTURE/GLOSSARY/
   CHANGELOG/adr/. Módulos legados têm um único `<Modulo>.md` plano.
3. **Fonte única da verdade funcional** — quando o código muda, atualizar o requisito.
4. **Regerar** — `php artisan module:requirements` gera arquivos faltantes
   sem sobrescrever edições manuais. Use `--force` com cuidado.
5. ~~**Módulo MemCofre** (`/memcofre`) consome esses arquivos e linka com evidências
   (screenshots de bug, chat logs, erros reportados).~~ ⚰️ **Não vale mais desde 2026-07-29**
   ([ADR 0357](../decisions/0357-deprecar-srs-sucessor-kb-jana-governance.md)): o módulo foi
   removido e nada consome estes arquivos por essa via. As URLs `/memcofre/*` seguem como
   redirect 301 para `/ia`, `/kb` e `/governance` (`routes/web.php`), só para preservar bookmarks.
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
