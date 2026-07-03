---
date: "2026-07-03"
topic: Onda standalone Produto — Passo 1 (CAPTERRA-FICHA) + Passo 2 (INVENTARIO + SPEC) + 7 US materializadas no MCP
authors: [C]
prs: [3729]
---

# Session log — Onda Produto (Passos 1+2)

## TL;DR

- **Passo 1 (adversário):** rodei a metodologia `capterra-senior` (Opus) sobre o módulo **Produto** (core UltimatePOS, não nWidart). Nota de capacidade **61/100** vs topo BR ~78-80 (Tiny/Bling/Linx moda) e teto global ~85 (Shopify/VTEX/Akeneo, sem fiscal BR/não Tier 0).
- **Passo 2 (comparativo):** gerei `CAPTERRA-INVENTARIO.md` (buckets ✅6/🟡11/❌1) + criei o **`SPEC.md` que faltava** (G-04) com 7 US de backlog.
- **PR [#3729](https://github.com/wagnerra23/oimpresso.com/pull/3729) MERGED** (commit `6c08d97`). Read-only research — zero código de produto tocado.
- **7 US-PROD (020-026) materializadas no `mcp_tasks`** via `mcp:tasks:sync` (cron 10min + webhook) — confirmado.
- Descobri que a **onda Cliente já estava pronta** (parallel session, #3732 FICHA + #3742 INVENTARIO) — não dupliquei.

## O ponto da onda (§8 "o que a nota esconde")

A `module-grade 71` (UX/DS das 8 telas) fica **acima** da capacidade real (61). 5 achados verificados no código:

1. **Kardex de fachada** — `StockHistory.tsx` (grade 47) não recebe `movements` no render Inertia; a timeline real só existe no path Blade legacy. Larissa não audita estoque na UI nova.
2. **Multiplicador de preço por tabela oco** — `SellingPriceGroup.mult` hardcoded `1.00` (ADR ARQ-0001); "preço por tabela" é 1:1.
3. **8 telas draft, 0 live** — nenhuma promovida/validada em prod.
4. **Sem SPEC** — o core-dos-cores (alimenta Sells+Compras) era o mais fraco em governança do programa.
5. **Zero prova de correção de valor** — `num_uf` (mesmo parser do incidente ×100k de Sells) roda em preços sem teste E2E.

## Refinamentos do Wagner (baked nas US)

- **US-PROD-024 (custo médio):** começa por **SPIKE de descoberta** — "muita coisa já tem pronta" no UltimatePOS (custo por compra: `default_purchase_price`, `VariationLocationDetails`, fluxo da compra). Mapeia antes de codar. NÃO é greenfield.
- **US-PROD-023 (React):** "o produto ainda precisa ser feito o react" — a US é **finalizar + promover as 8 telas draft→live**, priorizando as de nota baixa (StockHistory 47, Unificado 56).

## Batch (7 US, todas todo, blocked_by US-PROD-020)

| US | Prio | Gap | Tema |
|---|---|---|---|
| US-PROD-020 | p0 | G-04 | Governança: casos.md + revisar SPEC (pré-req dos demais) |
| US-PROD-021 | p0 | G-01 | Kardex real na tela React StockHistory |
| US-PROD-022 | p1 | G-02 | ⚠️Tier0 Multiplicador/markup por tabela de preço |
| US-PROD-023 | p1 | G-05 | Finalizar+promover 8 telas React draft→live + can:product.view |
| US-PROD-024 | p2 | G-03 | ⚠️Tier0 Custo médio + valor/custo em estoque (SPIKE primeiro) |
| US-PROD-025 | p2 | G-06 | UI de BOM drag-drop + baixa componente kit |
| US-PROD-026 | p3 | C18 | Fornecedores/cotação por produto |

## Percalços de CI (todos resolvidos honestamente)

Criar um SPEC novo é pesadamente gated. Bati e corrigi, sem tamper de baseline nem path/teste inventado:

1. **Session-log schema** — frontmatter exige `date`+`topic` (inglês) + `date` quotado (senão YAML parseia Date → `/date must be string`).
2. **SDD ghost-ratchet (GT-G3)** — escrever o literal `Modules/Produto` (mesmo na frase "não existe") casou o regex `MOD_REF_RE` → ghost_count 8→9. Reformulei pra "pasta própria em `Modules/` com esse nome".
3. **anchor-lint / entry-covers / doneness (ADR 0273/0302)** — âncoras `**Implementado em:**` ricas (class names, globs, parentheticals) viraram paths mortos + US "done" exigem DoD+teste-que-cobre. Solução: capacidades prontas viraram **prose de contexto** (§2), e as 7 US ficaram `todo` sem âncora (backlog = zona-cinza advisory, não morde). Validei os 3 gates localmente (EXIT 0) antes de cada push.

## Materialização SPEC→MCP (item que Wagner pediu pra verificar)

Pipeline: `mcp:tasks:sync` itera **todos** `memory/requisitos/*/SPEC.md` → upsert `mcp_tasks` (não precisa registro em `mcp_jira_projects`). Disparado por (a) webhook on push que toca SPEC.md (US-TR-004) + (b) cron `everyTenMinutes` em `live` (rede de proteção US-FIN-043). Dependência: SPEC no filesystem do servidor (via deploy). Meu `tasks-create` inicial falhou só porque o SPEC ainda não estava mergeado (caía no modo ad-hoc que exige `project`). Pós-merge + deploy + cron → 7 US no DB. **Confirmado.**

## Pointers

- Ficha: [CAPTERRA-FICHA.md](../requisitos/Produto/CAPTERRA-FICHA.md) · Inventário: [CAPTERRA-INVENTARIO.md](../requisitos/Produto/CAPTERRA-INVENTARIO.md) · SPEC: [SPEC.md](../requisitos/Produto/SPEC.md)
- Plano da onda: [template-onda-modulo.md](../requisitos/_Governanca/programa-ondas/template-onda-modulo.md)
- Ficha-modelo: [Sells/CAPTERRA-FICHA.md](../requisitos/Sells/CAPTERRA-FICHA.md)
