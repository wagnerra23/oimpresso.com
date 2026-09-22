---
id: requisitos-manufacturing-recipes-visual-comparison
title: "Comparacao design x producao — Manufacturing/Recipes (Receitas)"
module: Manufacturing
tela: Manufacturing/Recipes
owner: W
status: rascunho
inertia_target: resources/js/Pages/Manufacturing/Recipes.tsx
last_updated: "2026-09-22"
---

# Comparação design × produção — `Manufacturing/Recipes` (Receitas)

> Um dos 5 primeiros registros do módulo (ver [Index-visual-comparison.md](Index-visual-comparison.md)).
> É a **única** tela da família com contrato de tela e com teste de navegador.

## Âncora — computada, não escolhida no olho

```
node scripts/design/ancora.mjs Manufacturing/Recipes --staging prototipo-ui/cowork
  âncora ✓: [related_prototype (charter)] prototipo-ui/cowork/Felipe/manufacturing-page.jsx
```

O desenho é a aba `receitas` do hub (`ABAS` em `:37`, lista em `:200`), declarado em
`related_prototype` do [`Recipes.charter.md`](../../../resources/js/Pages/Manufacturing/Recipes.charter.md).
Passou de `Wagner/` para `Felipe/` em 2026-09-22 ([PR #7696](https://github.com/wagnerra23/oimpresso.com/pull/7696), decisão [W]).

> ⚠️ **Saída medida com o [#7696](https://github.com/wagnerra23/oimpresso.com/pull/7696)
> aplicado** (branch `claude/fabricacao-prototype-production-8fa4a8`, 2026-09-22). Enquanto ele
> não mergear, `main` ainda resolve esta tela em `Wagner/` — o que este bloco registra é a
> medição daquela branch, não o estado de `main`.

⚠️ **Sem selo de frescor** — `ancora.mjs:93` fixa `LUGAR_FIXO = 'prototipo-ui/cowork/Wagner'`, então
o `✓ frescor: verificado contra o Cowork vivo` deixa de ser emitido para fonte do Felipe.

## O que este documento NÃO é

**Não é veredito de paridade visual.** Nenhuma sonda de DOM foi injetada nesta sessão — abaixo está
o inventário do que já foi medido por outros, com recibo, e do que nunca foi.

## O que JÁ foi medido — e por que o "IGUAL" é FRACO

[`governance/design/targets/medidas/Manufacturing--Recipes/`](../../../governance/design/targets/medidas/Manufacturing--Recipes/resultado.json),
rodada de [#7503](https://github.com/wagnerra23/oimpresso.com/pull/7503):

| | |
|---|---|
| `medidoEm` | `2026-09-18T12:39:58Z` · `urlFinal` `https://staging.oimpresso.com/manufacturing/recipe` |
| `source` | `manufacturing-page.jsx` (basename — antes do #7696 havia 3 cópias homônimas) |
| veredito | **IGUAL**, `rc=0`, 0 bugs |
| `contrato` | `governance/design/contracts/manufacturing-recipes.contract.json` |
| `sameTheme` | **`false`** |

| dim | campo | prod | design | veredito |
|---|---|---|---|---|
| D2 · D4 · D6 · D8 | layout · tipografia · cor · kpi align | ok | ok | ✓ IGUAL |
| D9 | cabeçalho · filtros · kpis · lista | **presente** | **ausente** | SEM-DADO — *"região só existe de um lado"* |
| D4 | linha da tabela | — | — | SEM-DADO |
| SHELL ×4 | atalhoTopo · containerMenu · grupoHeader · itemGrupo | — | — | SEM-DADO |

**O rótulo diz IGUAL; o denominador diz outra coisa.** De **13** células, **4 bateram** e **9 não
tinham dado** — incluindo as 4 regiões principais da tela (cabeçalho, filtros, KPIs, lista), que o
lado do design **não renderizou**. "IGUAL" aqui significa *"nada do que foi medido divergiu"*, não
*"a tela está igual ao protótipo"*.

⚠️ Some-se: medição contra a fonte **antiga** e com **`sameTheme: false`** — tema diferente nos dois
lados invalida o veredito pelo
[PROTOCOLO-COMPARACAO-RUNTIME](../_DesignSystem/PROTOCOLO-COMPARACAO-RUNTIME.md). **Refazer.**

## Cobertura desta tela hoje

| camada | estado |
|---|---|
| charter | ✓ `Recipes.charter.md` — **`status: draft`** |
| casos | ✓ **9 UCs** (a maior cobertura da família) |
| contrato de tela | ✓ `manufacturing-recipes.contract.json` — **único do módulo** |
| e2e / browser | ✓ `e2e/manufacturing-recipes.spec.ts` + `tests/Browser/Manufacturing/RecipesIndexTest.php` — **os dois advisory** (`visual-regression.yml:1032` diz literal *"NASCE ADVISORY"*) |
| scorecard | ✗ nenhum |
| baseline de pixel | ✗ **0 `.snap`** |

O que o teste de navegador confere é que a tela **abre** e que não há erro grave de acessibilidade
— **não** que ela se pareça com o protótipo.

## Pendências

1. **Re-medir** com a âncora nova, `sameTheme: true` e o mapa de papéis (`__DD_ROLES`) corrigido —
   sem isso as 4 regiões principais continuam SEM-DADO, que é o buraco real desta tela.
2. Achados do [LAUDO](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/design/LAUDO-conferencia-fabricacao.md)
   que caem aqui: `--text-mute` nomeia a **coluna de dinheiro** e o **SKU do insumo** e reprova AA
   nos dois temas; `--accent` como texto mede **2,64:1** no escuro, e cai no *"Custo por unidade"*.
   Token do DS (ADRs 0410/0411) — decisão [W], não se conserta nesta tela.
3. `status: draft` → `live` só depois de (1).

## Refs

- [`Recipes.charter.md`](../../../resources/js/Pages/Manufacturing/Recipes.charter.md) · [`Recipes.casos.md`](../../../resources/js/Pages/Manufacturing/Recipes.casos.md) · [RUNBOOK-recipes.md](RUNBOOK-recipes.md)
- [README do handoff](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/README.md) — **normativo** · [SDD](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/contexto/SDD-tela-fabricacao-v1.0.md)
