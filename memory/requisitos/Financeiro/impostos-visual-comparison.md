---
id: requisitos-financeiro-impostos-visual-comparison
slug: impostos-visual-comparison
title: "Financeiro — Comparativo visual da tela Impostos e obrigações"
type: visual-comparison
module: Financeiro
status: draft
date: 2026-09-23
canon_reference: prototipo-ui/cowork/Wagner/financeiro-telas-extras.jsx (TelaImpostos, ~L677; IMP_STATUS L671-675)
---

# Impostos e obrigações — comparativo visual (FIN-3, 2026-09-23 [CL])

Parte do plano de paridade do Financeiro ([RUNBOOK-paridade-ondas.md §12](RUNBOOK-paridade-ondas.md)).
Primeira medição de paridade desta tela: o plano registrava "sem medição Onda 7".

## Como foi medido

- **Protótipo:** rota `fin-impostos` do shell, servido por `servirEspelho`
  (`scripts/design/design-diff-lote.mjs`, ADR 0401 — resolve `_ds/<slug>/` para o Design System).
  O `python -m http.server` dos presets não resolve o DS e dá render incompleto.
- **Produção:** `/financeiro/impostos`, empresa 1, tema escuro.
- Mesmo script de medição nos dois lados (`getComputedStyle`), DOM estável em 3 leituras seguidas.
- A tabela de guias difere em **dado** (protótipo com mock de 4 guias; produção com 6 meses de
  guias da empresa 1, já lançadas no caixa). Isso não é diferença de forma.

## Veredito por dimensão

| Elemento | Protótipo | Produção (antes) | Veredito |
|---|---|---|---|
| Estrutura (3 KPIs · guias · calendário · NF↔título · aviso de estimativa) | — | igual | **IGUAL** |
| Selo de status "a vencer" | `--warn` sobre `--warn-soft` · contraste 5,96:1 | `text-warning-foreground` sobre `warning/10` · contraste **1,57:1** | **DÍVIDA A FECHAR** (e defeito de legibilidade) |
| Valor dos KPIs | 22 px (`.os-stat b`) | 28 px | **DÍVIDA A FECHAR** |
| Rótulo dos KPIs | `--text-mute` (0,58) · peso 600 | 0,74 · peso 500 | **DÍVIDA A FECHAR** |
| Cartão dos KPIs | `--surface`, borda, raio 12, **sombra `--sh-1`** | igual, sem sombra | **DÍVIDA A FECHAR** |
| Seções (guias, calendário) | raio 8 px, sem borda externa | raio 12 px, borda 1 px | **DÍVIDA A FECHAR** |
| Cabeçalho da tabela e linha de detalhe | `--text-mute` (0,58) | 0,74 | **DÍVIDA A FECHAR** |
| Título | "Impostos e obrigações" | "Impostos & obrigações" | **DÍVIDA A FECHAR** |
| Primário "Novo título" | presente | ausente | **DÍVIDA A FECHAR** |
| Rótulo do KPI com o mês ("A recolher · junho") | presente | sem o mês | não tratado — o mês do protótipo é mock; semântica a confirmar com o Design |

Contraste medido com as cores convertidas para sRGB no navegador (canvas) e fórmula WCAG 2.
Com os tokens do protótipo: "paga" 5,57:1 e "atrasada" **4,28:1** (abaixo de 4,5:1 para texto de
10,5 px — é como o protótipo desenha; registrado para o Design).

## FIN-3 — o que foi aplicado

Todas as linhas "DÍVIDA A FECHAR" acima, só em `className`/rótulo. Nenhuma conta, formatador ou
rota mudou; o aviso de estimativa (UC-IMP-07) está intacto. Prova de valor: 109 números e datas
lidos em produção antes do merge (hash `aa7fc807`, guardados fora do git), a comparar depois do deploy.
