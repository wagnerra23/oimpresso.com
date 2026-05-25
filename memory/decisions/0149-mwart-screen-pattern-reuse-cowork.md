---
slug: 0149-mwart-screen-pattern-reuse-cowork
number: 149
title: "Screen-Pattern Reuse no MWART — Index Cowork blueprint pra Show/Edit/Detail da mesma entidade"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-15"
quarter: 2026-Q2
module: design-system
tags: [mwart, cowork, design-system, pattern-reuse, governance, wave-massiva]
supersedes: []
related: ["0104-processo-mwart-canonico-unico-caminho", "0114-prototipo-ui-cowork-loop-formalizado", "0107-emendation-0104-visual-comparison-gate-f3", "0109-claude-design-plugin-integrado-processo-mwart"]
amended-by: []
---

# ADR 0149 — Screen-Pattern Reuse no MWART: Index Cowork blueprint pra Show/Edit/Detail da mesma entidade

## Contexto

[ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md) formaliza o loop Cowork ↔ Claude Code: cada tela Inertia exige um protótipo Cowork (`prototipos/<tela>/`) aprovado em F1.5 antes do F3 (code Inertia). [ADR 0107](0107-emendation-0104-visual-comparison-gate-f3.md) reforça: SCREENSHOT Wagner aprova, não tabela.

Plano de migração massiva de 35 telas core-vendas/ROTA LIVRE em 7 dias (2026-05-15 → 22) mapeou:

| Bucket | Telas | Cowork prototype blueprint disponível |
|---|---|---|
| B1 Sells | 5 (Show/Edit/Drafts/Quotations/Subscriptions) | `vendas-cockpit/` cobre Index canon |
| B2 POS | 3 (Index/Create/Edit) | **❌ ausente — Wagner gera 2026-05-15** |
| B3 Cliente | 7 (Index/Create/Show/Edit/Import/Ledger/Map) | `clientes/cowork-app.jsx` cobre Index canon |
| B4 Produto | 7 (Index/Create/Show/Edit/BulkEdit/StockHistory/SellingPrices) | `produto-cockpit/` cobre Index canon |
| B5 Stock/Purchase | 6 (Purchase Create/Edit + StockTransfer/Adjustment) | `compras/` + `inventario-migracao/` cobrem |
| B6 Repair OS | 7 (JobSheet + Repair principal) | `os/` + `producao-oficina/` cobrem |

Exigir 1 Cowork prototype distinto pra cada uma das 35 telas (= ~24 prototypes faltantes) inviabiliza o cronograma 7 dias. Mas pular F1.5 viola ADR 0114 + 0104.

## Decisão

**Telas Show/Edit/Detail/Form-secundário** da MESMA entidade do Index aprovado em F1.5 podem **reusar o pattern visual do Index** sem novo round Cowork, registrando no PR/charter:

```yaml
mwart_pattern_reuse:
  blueprint_cowork: "prototipos/<bucket-cockpit>/"
  blueprint_screenshot_approval: "<SYNC_LOG ref [W2]: approved YYYY-MM-DD>"
  derived_screens: [Show, Edit, Detail]
  divergence_from_blueprint: "none | <justificativa curta>"
```

Wagner aprova **1 screenshot do Index** (F2 do ADR 0114) e essa aprovação se estende às telas derivadas da MESMA entidade do MESMO bucket. Se Show/Edit precisarem de pattern visual **divergente do Index** (ex: tela técnica como Print/PDF, ou layout split-pane que Index não tem), exige Cowork próprio + F1.5 novo.

## Critérios objetivos pra qualificar como "screen-pattern reuse"

1. **Mesma entidade** que Index Cowork aprovado (Sells = sale_pos.* + sell.*, Cliente = contact.*, Produto = product.*, etc)
2. **Mesma família visual**: usa AppShellV2, mesmo design system tokens (OKLCH Cowork), mesma header bar pattern
3. **Sem split-pane/drawer/modal complexo novo** (esses exigem Cowork próprio)
4. **Charter da tela derivada cita blueprint** explicitamente

## Casos que NÃO se qualificam (exigem Cowork próprio + F1.5)

- Print/PDF view (layout publishing diferente)
- Bulk-edit datatable (interação multi-row distinta de Index)
- Wizard multi-step (Create simples ≠ Wizard)
- Mobile-first view (UI inversa de desktop Index)
- Embed/Iframe externo

## Consequências

✅ Reduz Cowork necessário de 35 → ~6-8 prototypes blueprint
✅ Mantém qualidade visual (1 pattern revisto por bucket > 5 patterns inconsistentes)
✅ Permite cronograma 7 dias com qualidade
⚠️ Risco: agents podem reusar pattern em tela que NÃO se qualifica (ex: Print herdou layout do Index → quebra impressão). Mitigação: critérios objetivos acima + Pest screenshot diff + Wagner spot-check em 3 telas aleatórias

## Histórico

- 2026-05-15: Proposta gerada durante planejamento migração massiva 35 telas. Re-mapeamento mostrou 5 dos 6 buckets já têm Cowork blueprint adequado.
- 2026-05-15: Wagner aprovou accept ("Aprovar accept agora") + decidiu gerar Cowork B2 POS hoje pra entrar Wave 2.

## Pontos de re-revisão

- Após Wave 1 fechada (~2026-05-17), Wagner valida amostra de 3 telas derivadas pra confirmar critério bate.
- Se ≥1 quebra visível identificada em smoke, ADR vira `historical` e exige Cowork por tela.
