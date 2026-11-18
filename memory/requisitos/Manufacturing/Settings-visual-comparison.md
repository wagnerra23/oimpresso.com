---
id: requisitos-manufacturing-settings-visual-comparison
title: "Comparacao design x producao — Manufacturing/Settings (Configuracoes)"
module: Manufacturing
tela: Manufacturing/Settings
owner: W
status: rascunho
inertia_target: resources/js/Pages/Manufacturing/Settings.tsx
last_updated: "2026-11-17"
---

# Comparação design × produção — `Manufacturing/Settings` (Configurações)

> Um dos 5 primeiros registros do módulo (ver [Index-visual-comparison.md](Index-visual-comparison.md)).

## Âncora — computada, não escolhida no olho

```
node scripts/design/ancora.mjs Manufacturing/Settings --staging prototipo-ui/cowork
  âncora ✓: [related_prototype (charter)] prototipo-ui/cowork/Wagner/manufacturing-producao.jsx
```

Saída medida em 2026-11-17 contra `main`. O componente desta tela é o `MfgConfig`. A âncora voltou de `Felipe/` para `Wagner/` em 2026-09-25 ([#7984](https://github.com/wagnerra23/oimpresso.com/pull/7984)). As medições de 2026-09-22 abaixo foram feitas contra `Felipe/` ([#7696](https://github.com/wagnerra23/oimpresso.com/pull/7696)) e ficam como registro datado.

⚠️ **Três telas dividem este arquivo** — `Index` → `MfgProducaoView`, `Report` → `MfgRelatorio`, `Settings` → `MfgConfig`. A âncora aponta o **arquivo**; o componente é o que separa as três.

## Medição 2026-11-17 (auditoria, lote 5.3)

Produção em oimpresso.com (empresa 1, tema claro, 1600×900 num iframe), protótipo no espelho `Wagner/` gerado por `design-diff-lote.mjs --lado design --tema light`, comparação por `design-diff.mjs --compare --check`. Papéis do override [`roles/Manufacturing--Settings.json`](../../../governance/design/targets/roles/Manufacturing--Settings.json) (#11117). Os snapshots não foram commitados: o lado produção saiu sem shell e sem a `assinatura` (texto visível é dado real), e por isso o D0 (identidade da view) continua sem prova nesta rodada.

| dim | prod | design | veredito |
|---|---|---|---|
| D4 tipografia (título 22px/600) | ok | ok | IGUAL |
| D6 cor (primário "Atualizar") | ok | ok | IGUAL |
| D2 · D8 | — | — | sem KPI e sem filtros nos dois lados |
| D9 texto por região | presente | sem `[data-contract]` | SEM-DADO |

**DIVERGE (bug): 0.**

## Até 2026-09-22 esta tela nunca tinha sido medida

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
| medição design×prod | ✓ 2026-09-22 e 2026-11-17 (seções acima) |
| e2e / browser | ✗ nenhum |
| scorecard | ✗ nenhum |
| baseline de pixel | ✗ **0 `.snap`** |
| contrato de tela | ✗ nenhum |

## Pendências

1. ~~Primeira medicao~~ — **FEITA em 2026-09-22** (secao acima): `IGUAL`, cobrindo 4 de 19.
2. ~~Calibrar o `__DD_ROLES`~~ — **FEITO em 2026-11-17** ([#11117](https://github.com/wagnerra23/oimpresso.com/pull/11117), seção *Medição 2026-11-17*). O que segue SEM-DADO é D9 (o protótipo não tem `[data-contract]`) e o shell.
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
