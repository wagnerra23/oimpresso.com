---
id: requisitos-manufacturing-settings-visual-comparison
title: "Comparacao design x producao — Manufacturing/Settings (Configuracoes)"
module: Manufacturing
tela: Manufacturing/Settings
owner: W
status: rascunho
inertia_target: resources/js/Pages/Manufacturing/Settings.tsx
last_updated: "2026-09-22"
---

# Comparação design × produção — `Manufacturing/Settings` (Configurações)

> Um dos 5 primeiros registros do módulo (ver [Index-visual-comparison.md](Index-visual-comparison.md)).

## Âncora — computada, não escolhida no olho

```
node scripts/design/ancora.mjs Manufacturing/Settings --staging prototipo-ui/cowork
  âncora ✓: [related_prototype (charter)] prototipo-ui/cowork/Felipe/manufacturing-producao.jsx
```

O componente é `MfgConfig` (`:330` do protótipo). Passou de `Wagner/` para `Felipe/` em 2026-09-22
([PR #7696](https://github.com/wagnerra23/oimpresso.com/pull/7696), decisão [W]).

⚠️ **Três telas dividem este arquivo** — `Index` → `MfgProducaoView` (`:31`), `Report` →
`MfgRelatorio` (`:268`), `Settings` → `MfgConfig` (`:330`). A âncora aponta o **arquivo**; o
componente é o que separa as três.

> ⚠️ **Saída medida com o [#7696](https://github.com/wagnerra23/oimpresso.com/pull/7696)
> aplicado** (branch `claude/fabricacao-prototype-production-8fa4a8`, 2026-09-22). Enquanto ele
> não mergear, `main` ainda resolve esta tela em `Wagner/` — o que este bloco registra é a
> medição daquela branch, não o estado de `main`.

⚠️ **Sem selo de frescor** — `ancora.mjs:93` fixa `LUGAR_FIXO = 'prototipo-ui/cowork/Wagner'`.

## ⚠️ Esta tela NUNCA foi medida

Não existe artefato em `governance/design/targets/medidas/Manufacturing--Settings/`. A rodada de
[#7503](https://github.com/wagnerra23/oimpresso.com/pull/7503) (18/09) cobriu **2 das 5** telas
(`Index` e `Recipes`); esta ficou de fora. **Ausência de medição não é ausência de divergência** —
e nenhum gate que bloqueia merge mede fidelidade.

## Primeira medicao — 2026-09-22 · `IGUAL`, cobrindo 4 celulas de 19

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
| ancora | `prototipo-ui/cowork/Felipe/manufacturing-producao.jsx` (componente `MfgConfig`) |
| token do shell | **`mfg-config`** (via override) · `urlFinal` `.../manufacturing/settings` |
| celulas | **4 IGUAL · 12 SEM-DADO · 2 DIVERGE (a classificar) · 1 NAO MEDI** (19) |

Esta tela **nunca tinha sido medida**. O `IGUAL` cobre `layout`, `tipografia`, `cor` e `kpi align`;
`cabecalho` e `form` saem `SEM-DADO`.

O token precisou de override
([`Manufacturing--Settings.json`](../../../governance/design/targets/roles/Manufacturing--Settings.json))
pela mesma razao do Relatorio: a ancora nao e um `-page.jsx`.

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
| charter | ✓ `Settings.charter.md` — **`status: draft`** |
| casos | ✓ **4 UCs** em `Settings.casos.md` — a menor cobertura da família |
| medição design×prod | ✗ **nunca rodou** |
| e2e / browser | ✗ nenhum |
| scorecard | ✗ nenhum |
| baseline de pixel | ✗ **0 `.snap`** |
| contrato de tela | ✗ nenhum |

## Pendências

1. ~~Primeira medicao~~ — **FEITA em 2026-09-22** (secao acima): `IGUAL`, cobrindo 4 de 19.
2. **Calibrar o `__DD_ROLES`** desta familia (override em [`governance/design/targets/roles/`](../../../governance/design/targets/roles/)). E o que tira as regioes de `SEM-DADO` e faz o veredito falar da tela, nao de 4 celulas. O molde pronto e o [`Compras--Index.json`](../../../governance/design/targets/roles/Compras--Index.json), que ja mede papel no DOM dos dois lados em vez de adivinhar.
2. ⚠️ **Tela de configuração governa o cálculo das outras.** Se a subida do protótipo alterar
   qualquer chave que entre em custo, margem ou baixa de estoque, vale a **regra mestre de
   valor/estoque** ([proibicoes.md](../../proibicoes.md)): dois caminhos + antes→depois + [W] antes
   do merge — aqui com o agravante de que o efeito aparece em tela **alheia**.
3. Visibilidade — o módulo só aparece com `manufacturing_module` no pacote do business **e** as
   permissões no perfil (`manufacturing.access_recipe`, `manufacturing.access_production`).
   Habilitar/desabilitar é pela UI canônica, **nunca** por `if ($business_id === N)`
   ([proibicoes.md §Multi-tenant](../../proibicoes.md)).
4. `status: draft` → `live` só depois de (1).

## Refs

- [`Settings.charter.md`](../../../resources/js/Pages/Manufacturing/Settings.charter.md) · [`Settings.casos.md`](../../../resources/js/Pages/Manufacturing/Settings.casos.md) · [RUNBOOK-settings.md](RUNBOOK-settings.md)
- [README do handoff](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/README.md) — **normativo** · [SDD](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/contexto/SDD-tela-fabricacao-v1.0.md)
