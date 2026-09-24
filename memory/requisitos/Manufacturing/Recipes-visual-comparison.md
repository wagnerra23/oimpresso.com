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

## Re-medicao 2026-09-22 — `IGUAL`, e o denominador PIOROU

> **D0 — identidade da view PROVADA aqui, e so aqui.** Esta e a unica das 5 com contrato de tela
> ([`manufacturing-recipes.contract.json`](../../../governance/design/contracts/manufacturing-recipes.contract.json)),
> entao a pre-condicao que o `design-diff` exige ([`design-diff.mjs:1216`](../../../scripts/design/design-diff.mjs))
> esta satisfeita: o que foi renderizado do lado design **e** esta tela. Nas outras tres medidas
> (`Index`, `Report`, `Settings`) o `contrato` e `null` e o veredito vale so como indicio — ver a
> ressalva no topo da secao de medicao de cada uma.


| | |
|---|---|
| veredito | **`IGUAL`** · `rc=0` · 0 bugs |
| `sameTheme` | **`true`** (`--tema light`) — o vicio de 18/09 esta corrigido |
| ancora | `prototipo-ui/cowork/Felipe/manufacturing-page.jsx` (a nova, do #7696) |
| token do shell | `manufacturing` (aba padrao = receitas — **derivacao correta**, sem override) |
| celulas | **4 IGUAL · 14 SEM-DADO · 2 DIVERGE (a classificar) · 1 NAO MEDI** (21) |

Na rodada de 18/09 o `IGUAL` cobria **4 de 13**. Agora cobre **4 de 21** — o instrumento passou a
olhar mais coisa e encontrou mais buraco, nao mais paridade. As 4 regioes principais
(`cabecalho`, `filtros`, `kpis`, `lista`) **seguem** `SEM-DADO`.

### O que ficou SEM-DADO, e por que importa

A causa dominante e o **mapa de papeis (`__DD_ROLES`) nao calibrado** para esta familia, e ele
erra nas DUAS direcoes:

- **regioes de conteudo** (`cabecalho`, `filtros`, `kpis`, `lista`, `form`) devolvem
  `prod: presente / design: ausente` — o seletor do lado **design** nao casa;
- **sidebar** (`sb-*` e as 4 linhas `SHELL`) devolvem `prod: 0 el` contra `design: 3/1/9/34 el` —
  aqui e o seletor do lado **prod** que nao casa, porque usa as classes `.sb-*` do prototipo.

Enquanto isso nao for calibrado, o veredito agregado fala de **4 celulas**, nao da tela.

### Dois achados que NAO sao desta familia

- **`DIVERGE (a classificar)` no atalho de topo do shell** — `IA`, `Visao geral` e `Atendimento`
  so existem no design. E do **shell**, nao das telas da Fabricacao; classificar
  (DECIDIDA / DERIVA / DESIGN-ANDOU) e trabalho do dono do shell.
- **`NAO MEDI / SAUDE / tokens.prod`** — `--accent --pos --neg --warn --text-dim --sunken
  --border` **nao resolvem na raiz** em producao. Isso conversa diretamente com os dois achados
  ALTA de contraste do LAUDO, e merece medicao propria.

### Frescor: `STALE 2026-09-21`

Agora o frescor e **medido**: antes da parametrizacao do espelho, `frescorDaFonte` devolvia
`fora do espelho` para ancora fora de `Wagner/`. O `STALE` e porque a ultima rodada do ledger de
frescor nao cobriu o espelho do Felipe — nao e defeito da tela.

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

1. ~~Re-medir com a âncora nova e `sameTheme: true`~~ — **FEITO em 2026-09-22** (secao acima).
   O `sameTheme` esta corrigido; o buraco das regioes **continua**, e agora esta medido: 4 de 21.
2. **Calibrar o `__DD_ROLES`** desta familia (override em [`governance/design/targets/roles/`](../../../governance/design/targets/roles/)). E o que tira as regioes de `SEM-DADO` e faz o veredito falar da tela, nao de 4 celulas. O molde pronto e o [`Compras--Index.json`](../../../governance/design/targets/roles/Compras--Index.json), que ja mede papel no DOM dos dois lados em vez de adivinhar.
2. Achados do [LAUDO](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/design/LAUDO-conferencia-fabricacao.md)
   que caem aqui: `--text-mute` nomeia a **coluna de dinheiro** e o **SKU do insumo** e reprova AA
   nos dois temas; `--accent` como texto mede **2,64:1** no escuro, e cai no *"Custo por unidade"*.
   Token do DS (ADRs 0410/0411) — decisão [W], não se conserta nesta tela.
3. `status: draft` → `live` só depois de (1).

## Refs

- [`Recipes.charter.md`](../../../resources/js/Pages/Manufacturing/Recipes.charter.md) · [`Recipes.casos.md`](../../../resources/js/Pages/Manufacturing/Recipes.casos.md) · [RUNBOOK-recipes.md](RUNBOOK-recipes.md)
- [README do handoff](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/README.md) — **normativo** · [SDD](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/contexto/SDD-tela-fabricacao-v1.0.md)
