---
date: "2026-07-20"
topic: "Loop de aprendizado two-strikes passa a cobrir PROCESSO (não só código) — raio-X + ADR 0344 + auto-feed spawnado"
authors: [C]
prs: [4589, 4591]
related_adrs:
  - 0344-two-strikes-cobre-processo
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0094-constituicao-v2-7-camadas-8-principios
---

# Sessão 2026-07-20 — o loop de aprendizado deixa de ser cego a "esquecimentos"

> **TL;DR:** [W] relatou "sistema instável, erros recorrentes, esquecimentos — tem teste pra ver se viraram aprendizado?". **Raio-X medido** (não narrado): o loop de CÓDIGO está fechado (gate-selftest 68/68, CI 39/40 verde, ~0 revert), mas o loop de PROCESSO (§5 do `proibicoes.md`) **não tinha contador de reincidência** — a família "afirmar/medir da fonte errada" reincidiu **5× em 3 dias** como prosa, sem alarme. Construí o **two-strikes-cobre-processo** (o hook `licoes-code-two-strikes.mjs` + `LICOES_CODE.md` agora cobrem processo; cobertura só-advisory conta como "sem defesa mecânica"), ratifiquei como **ADR 0344** via workflow adversarial de 3 lentes, e **mergeei** (2 PRs — houve corrida: [W] mergeou o mecanismo antes de eu terminar a promoção). Follow-up #1 (o **auto-feed** que fecha o loop 100%) foi spawnado numa **sessão exclusiva** (task local dedicada).

## O pedido e o diagnóstico

Pergunta de [W]: existe teste pra ler os últimos erros e ver se viraram aprendizado? Rodei os mecanismos vivos e medi:

| Sinal | Medido |
|---|---|
| `gate-selftest.mjs` (as catracas mordem, GT-G6, required) | **68/68 mordem** |
| CI vermelho (últimos 40 runs) | 1 falha (39/40 verde) |
| reverts/hotfix/incidentes prod (60d, marcador git) | 0 |
| aprendizado registrado no §5 do `proibicoes.md` | 1 (mai) → 5 (jun) → **28 (jul)** |

**Diagnóstico:** a instabilidade sentida **não é bug de código escapando** — é o loop de PROCESSO (os "esquecimentos"). O lado código tinha contador+alarme (two-strikes lê `Ocorrências`); o lado processo (§5) só tinha prosa. Assimetria fechada.

## O que foi construído + mergeado

- **Mecanismo** (PR [#4589](https://github.com/wagnerra23/oimpresso.com/pull/4589), mergeado por [W]): `semGate()` do hook trata `Gate: advisory|parcial|insuficiente` como "sem defesa mecânica" → segue alarmando; `LICOES_CODE.md` ganhou escopo +processo + **LC-08 `afirmar-sem-medir-fonte-certa` (Ocorr 5)** que acende o alarme no SessionStart. Selftest 15/15.
- **Promoção a ADR 0344** (PR [#4591](https://github.com/wagnerra23/oimpresso.com/pull/4591), mergeado): a proposal virou ADR canônica aceita (`decided_by: [W]`). Precedeu-a um **workflow adversarial** (3 lentes: procedimento do gate · redação Nygard · cético — veredito `proceed_with_changes`, `governance_violations: []`). O cético exigiu 6 ajustes, todos incorporados: opt-out `advisory-terminal (0224)` no `semGate` (fecha o furo "advisory-terminal alarmaria pra sempre"), crédito ao `block-ancora-no-olho` (bloqueia comportamento ADJACENTE), reconciliação com a 0224 (ortogonais design-time × evidence-time), guarda-corpos ("nunca virar gate required sem auto-feed") e recibo honesto (promoção antes da validação prática).

## A corrida (registro honesto)

[W] mergeou o #4589 (ato R10) enquanto eu preparava a promoção → meu commit da ratificação ficou órfão. Verifiquei o estado real contra `origin/main` (não assumi): mecanismo + proposal em main, ADR 0344 fora. Reapliquei a promoção em branch nova de main fresco (cherry-pick), abri o #4591, verifiquei verde (86 pass / 0 fail), [W] mandou "merge" → mergeei (squash). Confirmado em `origin/main`: **ADR 0344 em main, proposal removida, `semGate` v2 em main**.

## Lição em tempo real (o próprio LC-08 me pegando)

Ao confirmar o hook v2 em main, quase reportei "❌ não está em main" — mas era o bug **MSYS colon-mangling** (`git show origin/main:<path>` com `:` mangled) + `grep` sem `-E`. Se eu tivesse concluído do sinal torto, teria sido exatamente a classe `afirmar-sem-medir-fonte-certa` (oráculo errado) que o LC-08 cataloga. Re-verifiquei com `MSYS_NO_PATHCONV=1` → confirmado de verdade.

## Follow-up spawnado (sessão exclusiva)

O único elo que **continua manual** é o feed: alguém registra a `Ocorrência` à mão. Spawnei uma **sessão local dedicada** (`task_d2c3d9be`) com prompt auto-contido + guarda-corpos duros (não presence-gate, não auto-declarar frescor, não duplicar régua, prefere honesto-e-parcial a mágico-e-frágil, workflow adversarial antes de codar) pra construir o **auto-feed**: ler CI-vermelho/incidentes/degradação + §5 e cruzar com o ledger automaticamente. Está rodando em paralelo.

## Estado final (verificado em `origin/main`)

ADR 0344 aceita e **já searchável via `decisions-search`** (webhook GitHub→MCP sincronizou). Loop de processo saiu do zero: ganhou contador + alarme + lei (ADR). Auto-feed = sessão exclusiva em voo.
