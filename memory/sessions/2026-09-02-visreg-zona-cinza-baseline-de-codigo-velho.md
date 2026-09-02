---
tipo: session
data: "2026-09-02"
titulo: "Zona cinza do visual-regression: a baseline de main era foto de código velho"
autor: "[C]"
modulo: Governance
tags: [visual-regression, baseline, gate, ci, medicao]
---

# Zona cinza do `visual-regression` — não era render não-determinístico

**Pedido [W]:** *"apenas faça"* — zerar a zona cinza herdada (rebake das determinísticas + quarentena das não-determinísticas).

**Resultado:** o rebake foi feito; **a quarentena não teve população** — nenhuma tela é não-determinística. A premissa que sustentava a quarentena (e a demoção do gate em 2026-08-26) estava invertida, e a medição a derrubou.

## 1. O que foi medido primeiro (28 runs · 16 branches)

Extraí das linhas que o próprio gate imprime (`- <tela> -> <n>% diff-view: ...`) em **todos** os 28 runs de falha do `visual-regression` na janela 2026-09-02 12:09→18:00Z, cobrindo **16 branches independentes**:

| tela | runs | branches | ratios distintos | spread | veredito |
|---|---|---|---|---|---|
| `financeiro-unificado · selecionar-lote · wide` | 27 | 16 | `0.1228` | **0.0000%** | determinístico |
| `financeiro-unificado · selecionar-lote · desktop` | 27 | 16 | `0.1555` | **0.0000%** | determinístico |
| `financeiro-unificado · selecionar-lote · compact` | 27 | 16 | `0.2024` | **0.0000%** | determinístico |
| `Jana` | 20 | 13 | `0.2870` | **0.0000%** | determinístico |
| `Ponto/Dashboard` | 20 | 13 | `0.2115` | **0.0000%** | determinístico |
| `Forja/Aprovacoes` | 12 | 8 | `0.1815` | **0.0000%** | determinístico |
| `Governance/Drift` | 2 | 2 | `0.7057` | **0.0000%** | determinístico |
| `Fiscal/Cockpit` | 13 | 7 | `0.849` · `1.2738` | 0.4248 | o `1.2738` sai só na branch que edita o cockpit |
| `Fiscal/Eventos` · `Fiscal/Dfe` · `Fiscal/Config` | 1 cada | 1 | — | — | 1 observação, sem base p/ veredito |
| `jana · estado=default` · `ponto-dashboard · estado=default` | 1 cada | 1 | — | — | idem |

**Zero variância entre 16 branches não é flake.** Ratio bit-idêntico é a assinatura de um render **determinístico** — não-determinismo produziria variância. A única tela com dois valores (`Fiscal/Cockpit`) muda exatamente na branch que mexe no cockpit: é a mudança própria do PR.

## 2. A premissa invertida

A entrada de demoção de 2026-08-26 em [`governance/required-checks-baseline.json`](../../governance/required-checks-baseline.json) tirou o `visual-regression` de required com esta justificativa:

> *"o numero NAO muda depois de regenerar a baseline (...) Baseline nova nao fecha o gap: o render dessas telas nao e deterministico, entao o gate nao podia ficar verde."*

As duas observações são verdadeiras; a conclusão não segue. O número não mudar depois do rebake **refuta** "a foto envelheceu", mas **não** implica não-determinismo — implica que o rebake fotografou outra coisa.

## 3. A causa real (e ela já estava escrita no workflow)

O classificador de impacto do próprio `visual-regression.yml` (L118-129) documentava metade do problema:

- `pull_request` → checkout do **MERGE REF** (`merge(head, tip de main)`)
- `workflow_dispatch` → checkout do **ref cru**, sem merge

Logo o **update fotografa `ref`** e o **verify compara contra `merge(ref, main)`**. E o step que abre o PR cria `vrt/baselines-<runId>` **a partir do ref do dispatch** e o manda `--base main` — então a foto de UI velha **aterrissa em main** e passa a reprovar todo PR seguinte, com o mesmo ratio para todos. É exatamente o spread 0.

Os 6 rebakes do dia falharam por isso, todos dispatchados de branch de feature atrasada — nenhum de `main`:
`33628252031` · `33634200726` · `33637277208` · `33638655483` · `33642824480` · `33653333470`.

## 4. Decodificado, não deduzido

Com [`scripts/tests/snap-diff.mjs`](../../scripts/tests/snap-diff.mjs) + os diff-views do run `33653847851` (PR `vrt/baselines-33653333470`, dispatch em `claude/forja-onda2-recibo-prod`):

| | baseline (modo *update*) | atual (modo *verify*) |
|---|---|---|
| sidebar | `CADASTRO` · `COMERCIAL` · `SISTEMA` **sem contador** | `CADASTRO 2` · `COMERCIAL 1` · `SISTEMA 2` |
| KPIs da Jana | **4 cards** (com `PIX HOJE`) | **3 cards** |

O contador `sb-group-n` entrou em `main` no [#6444](https://github.com/wagnerra23/oimpresso.com/pull/6444) (2026-08-29), antes dos dispatches. Diferença de **código**, não de dado.

## 5. O conserto

**Mecanismo** — o modo update passou a alinhar o ref com `origin/main` antes de fotografar (`fetch-depth: 0` só no dispatch; merge com falha alta em conflito). Assim update e verify fotografam a **mesma árvore por construção**. Roda só em `workflow_dispatch`; `pull_request` intocado.

**Rebake** — dispatch [33669306245](https://github.com/wagnerra23/oimpresso.com/actions/runs/33669306245) a partir de um ref alinhado. Recibo do step novo: `distância p/ origin/main: 0 atrás · 1 à frente → ✅ Já alinhado — merge dispensado`. **90 baselines** regeneradas.

**Prova de que a foto nova pousou no lugar certo** — `snap-diff` antes×depois do rebake: `Jana` 293082 px / Δmax=253 e `Ponto/Dashboard` 269768 px / Δmax=194, **exatamente** o delta baseline→atual que o gate vinha acusando. O PNG novo da Jana foi decodificado e aberto: contadores presentes, 3 cards — o render atual.

## 6. Quarentena: não aplicada, e por quê

O plano previa quarentena para as não-determinísticas. **A população é vazia** (§1). Declarar quarentena aqui isentaria telas sadias e esvaziaria o gate — a forma exata que o §5 2026-08-04 proíbe (isenção que casa com a população). `Fiscal/Cockpit` e `Forja/Aprovacoes` saíram da zona cinza sozinhos após o hotfix [#6559](https://github.com/wagnerra23/oimpresso.com/pull/6559) (merge 15:33): eram bug real de tela, já consertado upstream.

## 7. Registro

- §5 (fonte `licoes-rejeitadas.md` → derivado `proibicoes.md` via `sec5-derive.mjs --write`, 157 limites / 0 perdidos)
- Ledger **LC-08** 124 → 125, recibo datado; frontier anda para 2026-09-02
- O recibo pendurado de 08-31 que o hook aponta é **pré-existente** (conferido em `HEAD`)

## 8. O que fica em aberto

- **Decisão [W]:** repromover `visual-regression` a required. A demoção de 08-26 se apoiava numa premissa agora refutada — mas repromover é flip de branch protection, soberania [W] (ADR 0275 §5 / R10). **Não foi feito nesta sessão.**
- **Race residual:** o alinhamento fecha o skew ref↔main, mas se um PR de UI mergear entre o dispatch e o verify, a baseline nasce atrás de novo. A janela caiu de dias para minutos; fechá-la de vez exigiria regenerar no merge, o que é outro desenho.
