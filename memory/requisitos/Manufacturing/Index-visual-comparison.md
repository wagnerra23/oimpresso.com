---
id: requisitos-manufacturing-index-visual-comparison
title: "Comparacao design x producao — Manufacturing/Index (Ordens de producao)"
module: Manufacturing
tela: Manufacturing/Index
owner: W
status: rascunho
# `inertia_target` + `last_updated` sao o que poe este doc SOB VIGILANCIA: o
# `visual-comparison-staleness.mjs` compara a data declarada aqui com a data-git da tela
# apontada (regex `^inertia_target:\s*["']?(\S+?\.tsx)`, datas em last_updated/date/updated_at).
# Sem os dois campos, o doc cai no balde "nao-avaliado" e envelhece calado.
inertia_target: resources/js/Pages/Manufacturing/Index.tsx
last_updated: "2026-10-05"
---

# Comparação design × produção — `Manufacturing/Index` (Ordens de produção)

> **Primeiro registro desta tela, e o primeiro do módulo.** Até 2026-09-22 o
> `memory/requisitos/Manufacturing/` tinha **0** arquivos `*visual-comparison*`, contra **87** no
> resto do repo. As 5 telas da família nasceram sem ele — cada sessão que perguntava "o que já foi
> comparado aqui?" redescobria do zero.

## Âncora — computada, não escolhida no olho

```
node scripts/design/ancora.mjs Manufacturing/Index --staging prototipo-ui/cowork
  âncora ✓: [related_prototype (charter)] prototipo-ui/cowork/Felipe/manufacturing-producao.jsx
```

O desenho desta tela é o `MfgProducaoView` (`:31` do protótipo), declarado em `related_prototype`
do [`Index.charter.md`](../../../resources/js/Pages/Manufacturing/Index.charter.md).

**Mudou em 2026-09-22** ([PR #7696](https://github.com/wagnerra23/oimpresso.com/pull/7696), decisão
[W]): a família passou a ancorar no `handoff_fabricacao` do Felipe. Antes desse PR esta tela era a
**única** das 5 a resolver pelo `bundle_source` — que casa por **basename** (`ancora.mjs:512`,
`mockupJsx` extrai só `*-page.jsx`) — e havia 3 cópias de `manufacturing-page.jsx` no staging, de
modo que a âncora era o primeiro da varredura.

> ⚠️ **Saída medida com o [#7696](https://github.com/wagnerra23/oimpresso.com/pull/7696)
> aplicado** (branch `claude/fabricacao-prototype-production-8fa4a8`, 2026-09-22). Enquanto ele
> não mergear, `main` ainda resolve esta tela em `Wagner/` — o que este bloco registra é a
> medição daquela branch, não o estado de `main`.

⚠️ **Sem selo de frescor, e é estrutural.** `ancora.mjs:93` tem
`LUGAR_FIXO = 'prototipo-ui/cowork/Wagner'`; apontando para `Felipe/`, a linha
`✓ frescor: verificado contra o Cowork vivo` **não é emitida**. Medido antes e depois do #7696.

## O que este documento NÃO é

**Não é veredito de paridade visual.** Nenhuma sonda de DOM foi injetada nesta sessão. O que está
abaixo é o **inventário do que já foi medido por outros**, com data e recibo — e do que nunca foi.

## O que JÁ foi medido

Rodada de [#7503](https://github.com/wagnerra23/oimpresso.com/pull/7503) (*"as 77 telas executáveis
medidas contra o staging"*), artefato
[`governance/design/targets/medidas/Manufacturing--Index/`](../../../governance/design/targets/medidas/Manufacturing--Index/resultado.json):

| | |
|---|---|
| `medidoEm` | `2026-09-18T12:39:49Z` · `urlFinal` `https://staging.oimpresso.com/manufacturing/production` |
| `source` | `manufacturing-page.jsx` — **o hub do módulo, não o desenho desta tela** |
| veredito | **DIVERGE (bug)**, `rc=1`, 1 bug |
| `sameTheme` | **`false`** |

| dim | campo | prod | design | veredito |
|---|---|---|---|---|
| D4 | título font-size | **24px** | **22px** | ❌ **DIVERGE (bug)** — Δ2px, banda ±1px |
| D2 · D6 · D8 · D9 | layout · cor · kpi align · texto | ok | ok | IGUAL |
| D4 | linha da tabela | — | — | SEM-DADO (`tableRow` ausente no `__DD_ROLES`) |
| SHELL ×4 | atalhoTopo · containerMenu · grupoHeader · itemGrupo | — | — | SEM-DADO (seletor não casou) |

**Leia o denominador, não o rótulo:** de **11** células, **5 IGUAL · 1 DIVERGE · 5 SEM-DADO**.

⚠️ **Essa medição CADUCOU em dois eixos** e precisa ser refeita: mediu contra a fonte **antiga**
(`manufacturing-page.jsx`, e ainda por basename), e rodou com **`sameTheme: false`** — tema
diferente nos dois lados, o que pelo [PROTOCOLO-COMPARACAO-RUNTIME](../_DesignSystem/PROTOCOLO-COMPARACAO-RUNTIME.md)
invalida o veredito. O Δ2px do título pode ser real ou artefato; hoje não dá para dizer qual.

## Re-medicao 2026-09-22 — `DIVERGE (bug)`, e o delta de 2px do titulo e REAL

> **D0 — identidade da view NAO provada nesta medicao.** O `resultado.json` desta tela tem
> `contrato: null`, e o `design-diff` trata D0 como **pre-condicao, nao dimensao**
> ([`design-diff.mjs:1216`](../../../scripts/design/design-diff.mjs) — *"IDENTIDADE DA VIEW
> (pre-condicao, nao dimensao)"*). Sem contrato, nada garante que o que foi renderizado do lado
> design e **esta** tela. O `--dry` avisou, com estas palavras: *"D0 sem contrato — identidade da
> view NAO provada (ancora pode servir outra tela)"* — e a rodada seguiu assim mesmo.
>
> **Consequencia honesta:** o veredito abaixo vale como **indicio**, nao como paridade provada.
> Fecha-se criando o contrato de tela (o molde e
> [`manufacturing-recipes.contract.json`](../../../governance/design/contracts/manufacturing-recipes.contract.json),
> a unica da familia que tem).>
> Esta tela e a **prova viva** do aviso: a primeira rodada de hoje, com D0 nao provado, mediu a
> aba **Receitas** contra a producao de **Ordens de producao** e devolveu um veredito de aparencia
> valida. O token foi corrigido por override e a medicao refeita — mas o D0 **continua** nao
> provado, e por isso esta ressalva fica.


| | |
|---|---|
| veredito | **`DIVERGE (bug)`** · `rc=1` · 1 bug |
| `sameTheme` | **`true`** (`--tema light`) — o vicio metodologico da rodada de 18/09 esta corrigido |
| ancora | `prototipo-ui/cowork/Felipe/manufacturing-producao.jsx` (a nova, do #7696) |
| token do shell | **`mfg-producao`** · `urlFinal` `https://staging.oimpresso.com/manufacturing/production` |
| celulas | **3 IGUAL · 1 DIVERGE (bug) · 10 SEM-DADO · 2 DIVERGE (a classificar) · 1 NAO MEDI** (17) |

| dim | campo | prod | design | veredito |
|---|---|---|---|---|
| D4 | titulo font-size | **24px** | **22px** | **DIVERGE (bug)** — delta 2px, banda +-1px |
| D2 · D6 · D8 | layout · cor · kpi align | ok | ok | IGUAL |

**O delta sobreviveu a todas as objecoes.** A rodada de 18/09 ja o registrava, mas com dois vicios
que permitiam descarta-lo: `sameTheme: false` e fonte antiga. Agora foi medido com **tema igual nos
dois lados**, contra a **ancora nova** e — o que so se descobriu aqui — contra a **aba certa**.

**A primeira rodada de hoje mediu a TELA ERRADA, e o veredito parecia valido.** Ela gravou
`token: manufacturing`, que no `app.jsx` do espelho monta `<window.ManufacturingPage />` **sem**
`initialView`, ou seja a aba **Receitas**, enquanto a producao mostrava Ordens de producao. A causa
e que `tokenDoMockup` deriva do `source` do `application-report` (`manufacturing-page.jsx`, o hub),
**nao** da ancora resolvida. Corrigido por override
([`Manufacturing--Index.json`](../../../governance/design/targets/roles/Manufacturing--Index.json)),
com a razao escrita la. O delta persiste nas duas rodadas.

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

## Grade nova 2026-10-05

Na medição de 2026-10-01 (empresa 1, tema escuro, 1440 px; sonda complementar de células) a lista de
ordens ainda era a tabela local: linha de 53px, texto 14px, data em cor apagada, números sem mono e
sem linhas listradas. O protótipo (`MfgProducaoView`, `DataGrid` do DS) tem linha de 45px, 12,5px,
data/referência/números em mono 12px e linhas listradas. O PR de 2026-10-05 troca a tabela pelo
`shared/DataTable` (`density="grid"`), a mesma das abas Receitas, Insumos e Relatório:

| item | protótipo | produção em 10-01 | depois deste PR |
|---|---|---|---|
| estrutura | `<table>` do `DataGrid`, listrada | tabela local | `<table>` do `shared/DataTable`, listrada |
| texto | 12,5px | 14px | 12,5px |
| data, referência | mono 12px, cor do texto | data 14px apagada; ref. mono 14px | mono 12px, cor do texto |
| qtd, custos | mono 12px à direita | 14px à direita | mono 12px à direita |
| produto | nome 600 + 2ª linha 11px | nome 500 + 2ª linha 12px | nome 600 + 2ª linha 11px (`--text-dim`, AA) |
| situação "Rascunho" | contorno, sem cor | âmbar | contorno (mapa `producao` do `StatusBadge`) |

**Medido em produção em 2026-10-05** (deploy `37311669756`, com o #8651; empresa 1, tema escuro,
1440 px; sonda complementar de células, a mesma do protótipo, conferida por hash): as 8 células batem
com o protótipo — linha 45px, data/referência/números em mono 12px, texto 12,5px, mesma cor e
alinhamento, 2ª linha listrada. "Rascunho" em contorno (fundo transparente, borda `--border`). Única
diferença: o selo tem 12px contra 11,5px do protótipo — a versão do `StatusBadge`, como nos Insumos.

### Filtros 2026-10-05

| item | protótipo | produção em 10-01 | depois deste PR |
|---|---|---|---|
| faixa | sem cartão, controles pela base, gap 12px | dentro de cartão com borda | sem cartão, gap 12px |
| rótulos Local/De/Até | 10,5px/600 caixa alta, `--text-mute` | 10px/400, apagado | 10,5px/600, `--text-dim` (AA, ADR 0410) |
| Local | 180×34, canto 8, 13px, fundo `--surface` | 180×36, canto 6, 14px | 180×34, canto 8, 13px, `--surface` |
| De / Até | `DatePicker` 150×36, canto 8, 13,5px | `<input type=date>` 133×30, canto 5 | `<input type=date>` 150×36, canto 8, 13,5px |
| "Só finalizadas" | 12,5px/500, cor do texto | 14px/400, apagado | 12,5px/500, cor do texto |

**Medido em produção em 2026-10-05** (deploy `37349124454`, com o #8690; empresa 1, tema escuro, 1440 px,
computed style): faixa sem cartão; rótulos 10,5px/600 caixa alta; Local 180×34 e 13px; datas 150×36 e
13,5px; fundo `--surface` e borda `--border` nos três — como o protótipo. **Duas diferenças achadas pela
medição e corrigidas no PR seguinte:** o canto saiu **12px** (o `rounded-lg` deste projeto vale 12, e o
#8690 supôs 8) e "Só finalizadas" usava `text-foreground` em vez do `--text` da tabela.

**Ficam diferentes, de propósito:** "Todos os locais" (o protótipo diz "Todos") é copy do contrato
da tela (`manufacturing-index.contract.json`) — decisão [W]; e o campo de data continua o nativo,
porque o `DatePicker` do DS não tem par React. **Ficam para depois:** as descrições dos 4
indicadores e o painel lateral da ordem (`MfgProducaoDrawer`), que precisa dos ingredientes
consumidos vindos do servidor.

### Indicadores 2026-10-05

O protótipo dá a cada um dos 4 cartões uma linha de apoio e o tamanho padrão do `KpiCard`. A razão,
declarada nele: sem a linha, a faixa media 75px nas Ordens e 98px na Receitas, e trocar de aba fazia a
faixa pular. O PR de 2026-10-05 faz o mesmo, com o tamanho padrão no lugar do `compact`.

| cartão | protótipo | produção depois do PR | por quê |
|---|---|---|---|
| Total | "ordens cadastradas" / "no filtro de local e data" | "ordens cadastradas" | os números aqui não seguem o filtro (abaixo) |
| Finalizadas | "estoque já movimentado" | igual | — |
| Pendentes | "rascunhos, sem movimentar estoque" | igual | — |
| Valor total | "ordens do período · rascunho a preço de hoje" | "todas as ordens cadastradas" | sem filtro aqui, e o valor é o gravado, nunca recalculado |

**DÍVIDA A FECHAR — os indicadores não seguem o filtro.** No protótipo os 4 números são calculados sobre
local + período. Aqui `ProductionController@indexV2` chama `ProductionService::summary($business_id)`
sem os filtros, e os números são de todas as ordens da empresa. Fechar muda o valor somado que aparece
na tela, então segue a regra de valor (prova por 2 caminhos + antes→depois) e é PR próprio.

**Medido em produção depois do #8707 (2026-10-05, 1440px, tema escuro, empresa 1):** as 4 linhas de
apoio chegaram, mas a faixa **ainda pulava**: cada cartão das Ordens media **125px**, contra **105px** na
Receitas. Causa: os 4 cartões tinham ícone de 36px no título (a linha do título ia de 17 para 36px), e o
protótipo não desenha assim — os cartões de leitura não têm ícone (o `KpiCard` de leitura do DS não o
desenha) e só "Finalizadas" é `variant="filter"`, com o ícone ao lado, igual à Receitas. Consertado no PR
seguinte: mesma forma do protótipo e vão de 10px (o do `.mfg-kpis`). Na mesma medição, os filtros do
#8715 conferem: datas 150×36 com canto 8px, Local 180×34 com canto 8px e texto 13px, e "Só finalizadas"
na cor do `--text`.

**Depois do deploy do #8745 (2026-10-06 01:48 UTC, mesma medição):** os 4 cartões medem **105px**, igual à
Receitas; "Total", "Pendentes" e "Valor total" sem ícone; "Finalizadas" é o cartão-filtro com ícone, e o
clique liga `?is_final=1` (marca o "Só finalizadas", lista de 2 para 1 ordem) e desliga de volta. Vão de 10px.

## Cobertura desta tela hoje

| camada | estado |
|---|---|
| charter | ✓ `Index.charter.md` — **`status: draft`** |
| casos | ✓ **5 UCs** em `Index.casos.md` |
| scorecard | ✓ `memory/governance/scorecards/screens/manufacturing-index.yaml` |
| e2e / browser | ✗ nenhum |
| baseline de pixel | ✗ **0 `.snap`** (104 no repo, nenhum do módulo) |
| contrato de tela | ✗ nenhum |

## Pendências

1. ~~Re-medir com a âncora nova e `sameTheme: true`~~ — **FEITO em 2026-09-22** (secao acima).
   O veredito: o delta de 2px do titulo e **real**.
2. **Decidir o titulo**: alinhar producao ao prototipo (24px -> 22px) ou registrar decisao [W]
   de manter 24px e reescrever a banda. E decisao de forma, logo cadeia da [UI-0029](../_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md) — prototipo soberano.
3. **Calibrar o `__DD_ROLES`** desta familia (override em [`governance/design/targets/roles/`](../../../governance/design/targets/roles/)). E o que tira as regioes de `SEM-DADO` e faz o veredito falar da tela, nao de 4 celulas. O molde pronto e o [`Compras--Index.json`](../../../governance/design/targets/roles/Compras--Index.json), que ja mede papel no DOM dos dois lados em vez de adivinhar.
2. Os 3 achados do [LAUDO do handoff](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/design/LAUDO-conferencia-fabricacao.md)
   valem para a família: 2 ALTA de contraste (`--text-mute` reprova AA; `--accent` como texto mede
   **2,64:1** no tema escuro) + 1 MÉDIA de layout. As duas ALTA são **token do DS** (ADRs 0410 e
   0411), decisão [W] — não se conserta nesta tela.
3. `status: draft` → `live` só depois de (1).

## Refs

- [`Index.charter.md`](../../../resources/js/Pages/Manufacturing/Index.charter.md) · [`Index.casos.md`](../../../resources/js/Pages/Manufacturing/Index.casos.md) · [RUNBOOK-producao.md](RUNBOOK-producao.md)
- [README do handoff](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/README.md) — **normativo** (§0: *"o protótipo é ilustrativo"*)
- [SDD-tela-fabricacao-v1.0.md](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/contexto/SDD-tela-fabricacao-v1.0.md) · [CHECKLIST-15D](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/design/CHECKLIST-15D-fabricacao.md)
