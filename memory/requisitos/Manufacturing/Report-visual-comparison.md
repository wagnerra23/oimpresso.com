---
id: requisitos-manufacturing-report-visual-comparison
title: "Comparacao design x producao — Manufacturing/Report (Relatorio do periodo)"
module: Manufacturing
tela: Manufacturing/Report
owner: W
status: rascunho
inertia_target: resources/js/Pages/Manufacturing/Report.tsx
last_updated: "2026-10-05"
---

# Comparação design × produção — `Manufacturing/Report` (Relatório)

> Um dos 5 primeiros registros do módulo (ver [Index-visual-comparison.md](Index-visual-comparison.md)).

## Âncora — computada, não escolhida no olho

```
node scripts/design/ancora.mjs Manufacturing/Report --staging prototipo-ui/cowork
  âncora ✓: [related_prototype (charter)] prototipo-ui/cowork/Felipe/manufacturing-producao.jsx
```

O componente é `MfgRelatorio` (`:268` do protótipo). Passou de `Wagner/` para `Felipe/` em
2026-09-22 ([PR #7696](https://github.com/wagnerra23/oimpresso.com/pull/7696), decisão [W]).

⚠️ **Três telas dividem este arquivo, em componentes diferentes** — `Index` → `MfgProducaoView`
(`:31`), `Report` → `MfgRelatorio` (`:268`), `Settings` → `MfgConfig` (`:330`). A âncora aponta o
**arquivo**; quem diz qual pedaço é esta tela é esta linha. Não leia "mesma âncora" como "mesma
tela".

> ⚠️ **Saída medida com o [#7696](https://github.com/wagnerra23/oimpresso.com/pull/7696)
> aplicado** (branch `claude/fabricacao-prototype-production-8fa4a8`, 2026-09-22). Enquanto ele
> não mergear, `main` ainda resolve esta tela em `Wagner/` — o que este bloco registra é a
> medição daquela branch, não o estado de `main`.

⚠️ **Sem selo de frescor** — `ancora.mjs:93` fixa `LUGAR_FIXO = 'prototipo-ui/cowork/Wagner'`.

## ⚠️ Esta tela NUNCA foi medida

Não existe artefato em `governance/design/targets/medidas/Manufacturing--Report/`. A rodada de
[#7503](https://github.com/wagnerra23/oimpresso.com/pull/7503) (18/09) cobriu **2 das 5** telas da
família (`Index` e `Recipes`); esta ficou de fora. **Ausência de medição não é ausência de
divergência** — e nenhum gate que bloqueia merge mede fidelidade.

## Primeira medicao — 2026-09-22 · `IGUAL`, cobrindo 4 celulas de 20

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
> a unica da familia que tem).


| | |
|---|---|
| veredito | **`IGUAL`** · `rc=0` · 0 bugs |
| `sameTheme` | **`true`** (`--tema light`) |
| ancora | `prototipo-ui/cowork/Felipe/manufacturing-producao.jsx` (componente `MfgRelatorio`) |
| token do shell | **`mfg-relatorio`** (via override) · `urlFinal` `.../manufacturing/report` |
| celulas | **4 IGUAL · 13 SEM-DADO · 2 DIVERGE (a classificar) · 1 NAO MEDI** (20) |

Esta tela **nunca tinha sido medida**. O `IGUAL` aqui cobre `layout`, `tipografia`, `cor` e
`kpi align` — e mais nada: `cabecalho`, `filtros` e `lista` saem `SEM-DADO`.

O token precisou de override
([`Manufacturing--Report.json`](../../../governance/design/targets/roles/Manufacturing--Report.json)):
a ancora e o `manufacturing-producao.jsx`, que **nao e um `-page.jsx`** e por isso nao tem rota
derivavel no shell. Tres telas dividem esse arquivo; o token e o que as separa.

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

## Grade nova 2026-10-04

Na medição de 2026-10-01 (empresa 1, tema escuro, 1440px) a produção mostrou **0 linhas** com os
filtros padrão da tela, então a **grade** não pôde ser comparada célula a célula. Do protótipo ficaram medidas: `<table>`, linhas de 29px, listradas, números à direita em mono
12px. O PR de 2026-10-04 troca a div-grid local pelo `shared/DataTable` (`density="grid"`), que
reproduz essa anatomia (o mono 12px e a altura de linha 1,45 entraram no mesmo PR, medidos na
Receitas). A barra de "% do período" segue a local do bundle: o `Progress` do DS não tem par React.

**Medido em produção em 2026-10-05** (deploy `37270930132`, commit `f79b5f3f27`, que contém o #8628; biz=1, tema escuro, 1440 px; mesmas sondas do protótipo — `style-fingerprint --snippet` e a complementar de células/controles — e arquivos conferidos por hash). Com os filtros padrão (só finalizadas) a produção
segue sem linhas; com "Só finalizadas" desmarcado (`?is_final=0`, só leitura) aparece 1 linha, e foi ela a medida:

| item | protótipo | produção 2026-10-05 | veredito |
|---|---|---|---|
| estrutura | `<table>` | `<table>`, nenhuma `.mfg-table` | IGUAL |
| cabeçalho | 10px, 27px de altura, fundo `--bg-2` | 10px, 27px, mesmo fundo | IGUAL |
| linha | 29px | 29px | IGUAL |
| produto | 12,5px/400 à esquerda | igual | IGUAL |
| ordens, quantidade, custo total, custo médio | mono 12px à direita | igual | IGUAL |
| % do período | 12,5px à direita | igual | IGUAL |
| cor do texto do cabeçalho | `--text-mute` (0,58) | `--text-dim` (0,72) | troca de contraste AA (ADR 0410) — a mesma da Receitas |
| listras | linhas alternadas | — | não verificável: 1 linha só |

**Fora da grade:** os filtros. O protótipo mostra a caixa "Só finalizadas"; a produção tem também os campos De/Até
(140×32) e a caixa como `Checkbox` do DS. Fica para o item "filtros do Relatório" da fila.

## Cobertura desta tela hoje

| camada | estado |
|---|---|
| charter | ✓ `Report.charter.md` — **`status: draft`** |
| casos | ✓ **6 UCs** em `Report.casos.md` |
| medição design×prod | ✓ 2026-09-22, 2026-10-01 e 2026-10-05 (seções acima) |
| e2e / browser | ✗ nenhum |
| scorecard | ✗ nenhum |
| baseline de pixel | ✗ **0 `.snap`** |
| contrato de tela | ✗ nenhum |

## Pendências

1. ~~Primeira medicao~~ — **FEITA em 2026-09-22** (secao acima): `IGUAL`, cobrindo 4 de 20.
2. **Calibrar o `__DD_ROLES`** desta familia (override em [`governance/design/targets/roles/`](../../../governance/design/targets/roles/)). E o que tira as regioes de `SEM-DADO` e faz o veredito falar da tela, nao de 4 celulas. O molde pronto e o [`Compras--Index.json`](../../../governance/design/targets/roles/Compras--Index.json), que ja mede papel no DOM dos dois lados em vez de adivinhar.
2. ⚠️ **Relatório mostra dinheiro.** Se a subida do protótipo mexer em qualquer cálculo exibido
   aqui (custo, custo unitário, margem, perda), vale a **regra mestre de valor/estoque**
   ([proibicoes.md](../../proibicoes.md)): prova por dois caminhos independentes + tabela
   antes→depois + aprovação [W] **antes** do merge. Não é opcional nesta tela.
3. Achados do [LAUDO](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/design/LAUDO-conferencia-fabricacao.md):
   `--text-mute` nomeia a coluna de dinheiro e reprova AA; `--accent` mede **2,64:1** no escuro.
   Token do DS (ADRs 0410/0411) — decisão [W].
4. `status: draft` → `live` só depois de (1).

## Refs

- [`Report.charter.md`](../../../resources/js/Pages/Manufacturing/Report.charter.md) · [`Report.casos.md`](../../../resources/js/Pages/Manufacturing/Report.casos.md) · [RUNBOOK-report.md](RUNBOOK-report.md)
- [README do handoff](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/README.md) — **normativo** · [SDD](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/contexto/SDD-tela-fabricacao-v1.0.md)
