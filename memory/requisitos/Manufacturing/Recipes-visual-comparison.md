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

## Matriz de aceite da promoção (2026-09-30)

Promoção do protótipo **atual inteiro** × a tela viva ([RUNBOOK-promocao-prototipo](RUNBOOK-promocao-prototipo.md) §Matriz;
lição §5 2026-09-30). Fonte: `prototipo-ui/cowork/Wagner/manufacturing-page.jsx` (espelho após o #8052) e o
`DataGrid` do DS em `prototipo-ui/design-system/components/DataGrid/`. Diferenças medidas com
`design-diff.mjs --probe` nos dois lados (protótipo local × `/manufacturing/recipe` biz=1, dark, 1440 px);
os JSON da medição não foram versionados — a re-medição pós-deploy entra aqui.

| ID | Área | Item | Classe | Fonte/regra | Aceite observável | Evidência | Situação |
|---|---|---|---|---|---|---|---|
| R01 | Cabeçalho | título "Fabricação" | visual | protótipo (pedido 2026-09-28) | h1 = "Fabricação" | #8239 + smoke prod | ACEITO |
| R02 | Cabeçalho | peso do título | visual | PageHeader do protótipo | h1 600 (era 700) | prod 2026-09-30: h1 `600` | ACEITO |
| R03 | Cabeçalho | linha de contagens (stats do PageHeader) | visual | protótipo | mesma forma do PageHeader | — | NÃO INICIADO |
| R04 | Abas | aba ativa (fundo, peso, selo sólido) | visual | `TabBar` do DS → `PageHeaderTabs` | fundo e selo como o protótipo | prod 2026-09-30 (deploy `36779385207`): nas 5 telas, ativa com fundo `oklch(0.33 0.09 295 / 0.5)`, peso 600, contador `oklch(0.7 0.15 295)` com texto escuro — igual ao protótipo; clique troca de tela sem recarregar | ACEITO |
| R05 | Indicadores | custo médio / produção do mês | visual | `KpiCard` padrão | cartão do DS, valor na cor do texto | prod: `KpiCard` default, valor `oklch(0.965 0.004 240)` 22px | ACEITO (ver R17) |
| R06 | Indicadores | margem < 45% / desperdício ≥ 8% | visual + comportamento existente | `KpiCard variant="filter"`, ícone balança/tesoura, tom âmbar | placa de ícone 36 px, valor branco, filtro liga/desliga (R-05) | prod: placa 36px com ícone, fundo âmbar 15%, valor branco 18px/600; filtro de desperdício 1→0 linhas + vazio, desliga volta a 1 | ACEITO (ver R18) |
| R07 | Filtros | busca | visual | `SearchInput` do DS (sem par React: gap no registry) | 34px, canto 8, texto 13, ícone 15 dentro do campo, largura até 360 | protótipo medido 360×34 · canto 8 · 13px; prod antes 460×32 · canto 6 · 12,5px | PRONTO PARA VALIDAR (onda 2b) |
| R08 | Filtros | categorias | visual | `Segmented` do DS → `@/Components/ui/segmented` | grupo segmentado, ativa com fundo e peso 600 | protótipo medido: grupo h32 canto 8, ativa fundo `oklch(0.3 0.008 240)` 600; prod antes: pílulas soltas, ativa roxa translúcida 400 | PRONTO PARA VALIDAR (onda 2b) |
| R09 | Tabela | indicador de ordenação | visual | `DataGrid`: depois do rótulo, ↕ inativo opaco | ↕/↑/↓ após o rótulo em todas as colunas | prod: 7/7 colunas com o indicador por último; ↕ opacidade 0,4, ativo 1 | ACEITO |
| R10 | Tabela | cor da coluna ordenada | visual | `DataGrid`: cor do texto | não mais o primário | prod: ativa `oklch(0.94 0.005 90)` | ACEITO (ver R19) |
| R11 | Tabela | colunas de número à direita | visual | `DataGrid` `align:'right'` | células e cabeçalhos alinhados à direita | prod: 5 cabeçalhos e 5 células com `justify-self: end` | ACEITO |
| R12 | Tabela | margem como `StatusBadge` | visual | protótipo R-10 (≥55 · ≥45 · abaixo) | badge do DS no tom da faixa | prod: margem 0% → tom `danger` (`oklch(0.26 0.07 18)` fundo), 12px | ACEITO |
| R13 | Tabela | margem com fundo SÓLIDO | visual | `StatusBadge` do DS do Cowork | — | medido: o `StatusBadge` do React é suave com ponto (AP7) | BLOQUEADO — divergência do DS, pedido ao dono do DS |
| R14 | Tabela | estrutura `<table>` + rodapé de paginação | visual | `DataGrid` (sem mapeamento React no registry) | — | — | BLOQUEADO — gap do DS (`DataGrid`/`Pagination` sem par React) |
| R15 | Tabela | vazio (`EmptyState`) · seleção (`BulkBar`) | visual | protótipo | — | — | NÃO INICIADO |
| R16 | Drawer | leitura da receita | visual | protótipo | — | — | NÃO INICIADO (não medido) |
| R17 | Indicadores | peso do valor nos cartões de leitura | visual | protótipo: 700 | — | prod: 600 (padrão do `KpiCard`) | NÃO INICIADO — é o componente do DS, não da tela |
| R18 | Indicadores | canto dos cartões de filtro | visual | protótipo: 8px | — | prod: 12px (`KpiCard variant="filter"`) | NÃO INICIADO — é o componente do DS, não da tela |
| R19 | Tabela | cor das colunas não ordenadas | visual | protótipo: `--text-mute` (0,58) | — | prod: `--text-dim` (0,72), pela regra de contraste AA do bundle (ADR 0410) | FORA DE ESCOPO — a troca foi decisão de acessibilidade; reverter é decisão [W] |
| R20 | Filtros | "(tecla /)" no texto de exemplo da busca | visual + copy | protótipo tirou; o contrato da tela (`manufacturing-recipes.contract.json`, citado do handoff normativo) mantém | — | prod mantém | FORA DE ESCOPO — copy de contrato é decisão [W] |
| R21 | Filtros | mais de 5 categorias | comportamento | protótipo: *"a sexta pede outra peça"*, sem definir qual | — | prod volta às pílulas acima de 5 opções | NÃO DEFINIDO NO PROTÓTIPO |
| R22 | Filtros | medidas internas do `Segmented` | visual | protótipo: canto 8 / item 26px / 11,5px | — | React: canto 5 / padding 6×13 / 12px (`cowork-fields.css`) | NÃO INICIADO — é o componente do DS, não da tela |

**Medição pós-deploy (2026-09-30, deploy `36756853084`, commit `9b211de508`):** `/manufacturing/recipe`, biz=1, tema escuro, 1440 px, sonda JS no DOM (`getComputedStyle`). A lista tem 1 receita, por isso o filtro de margem (1 de 1) não discrimina; o de desperdício (0 de 1) sim, e foi ele o testado.

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
