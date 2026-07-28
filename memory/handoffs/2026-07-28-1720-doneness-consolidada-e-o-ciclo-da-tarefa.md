---
date: "2026-07-28"
time: "17:20"
slug: doneness-consolidada-e-o-ciclo-da-tarefa
tldr: "Duas ADRs aceitas se contradiziam sobre o campo `status:` e travavam 85 US com âncora verificada. ADR 0355 consolida (âncora fecha · DoD aberto veta · forward-only), 0356 corrige um limiar que eu inventei, e o código do forward-close já está em prod. 5 PRs mergeados. Fica aberto: dono no fechamento (2/10), changelog (`done_at` em 1/59) e a agregação `reopen_rate`."
topic: "Done-ness consolidada: 0302×0337 se contradiziam e travavam 85 US. ADR 0355 + errata 0356 + código do forward-close + SPEC morto fora dos gates"
authors: [C, W]
type: handoff
module: Governance
pii: false
related_adrs:
  - 0355-doneness-consolidada-ancora-fecha-dod-veta
  - 0356-errata-0355-limiar-de-reversao-derivado-nao-escolhido
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0144-tasks-db-canonico-spec-template
  - 0070-jira-style-task-management-current-md-removed
---

# 2026-07-28 17:20 — done-ness consolidada e o ciclo de vida da tarefa

> Tudo que estava em voo **mergeou**. Nenhum PR meu aberto. A sessão fechou por tamanho, não por bloqueio.

## O que entrou no `main` (5 PRs)

| PR | o que |
|---|---|
| [#4934](https://github.com/wagnerra23/oimpresso.com/pull/4934) | ownership: `✅` da matriz é **plural** (co-donos) + §3.1 desarmando `owner:` de SPEC como falsa fonte |
| [#4944](https://github.com/wagnerra23/oimpresso.com/pull/4944) | blockquote `status:` em 2 SPECs → **17 US fecharam** por `webhook-sync` (verificado em prod 13:08) |
| [#4950](https://github.com/wagnerra23/oimpresso.com/pull/4950) | grade do ciclo de vida da tarefa — **5,0/10** |
| [#4959](https://github.com/wagnerra23/oimpresso.com/pull/4959) | **ADR 0355** (supersede 0302+0337) + SPEC morto fora dos gates |
| [#4970](https://github.com/wagnerra23/oimpresso.com/pull/4970) | **ADR 0356** (errata) + **código** do forward-close |

## A descoberta que organizou tudo

**Duas ADRs aceitas se contradiziam.** A [0302](../decisions/0302-fonte-unica-doneness-anchor-aposenta-status-spec.md) §2 aboliu o campo (*"`status: done` deixa de existir"* · *"US nova nasce sem `status:`"*, confirmado no `_TEMPLATE_SPEC.md`); a [0337](../decisions/0337-emenda-0144-forward-close-por-ancora-verificada.md) o **exigia** como condição #2 do forward-close.

Consequência aritmética: **toda US nascida corretamente sob a 0302 era incapaz, por construção, de fechar sob a 0337**. Medido: **85 US** com âncora verificada paradas (72 sem DoD escrito).

A contradição viveu **2 semanas** sem detector — o `fact-anchor` do `memory-health` ancora fato-de-doc em **código** (`package.json`/`Modules`), nunca em outra decisão.

## A regra hoje ([ADR 0355](../decisions/0355-doneness-consolidada-ancora-fecha-dod-veta.md))

| # | condição | papel |
|---|---|---|
| 1 | card ativo | nunca reabre |
| 2 | âncora `anchored_ok` + sha | **único sinal positivo** — verificável no disco |
| 3 | `- [ ]` aberto | **VETA** |
| 4 | data ≥ `2026-07-28` | **forward-only** |

`$specStatus` **removido da assinatura**; um teste trava a aridade exata. Verde na lane `PHP / Pest (Jana · MySQL)`.

**Por que o DoD só veta:** `[x]` é ato de 1 caractere sem revisor — provado no commit `7ebe9ea5d7`, que marcou `[x]` numa linha cujo texto diz *"**parcial** … **não há autoprint**"*. Desprovar é barato; provar não.

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work owner:wagner` → **15** (14 review + 1 blocked). Destaque: `US-COPI-123` p0 · `US-TR-309`/`US-TR-310` (a tela de triagem que destrava as órfãs)
- `decisions-search "done-ness âncora forward-close"` → **0355** no topo, indexada
- `triage` → **≥100** (teto da consulta; o acervo real sai do comando em prod)

## O que fica aberto — nomeado dentro das ADRs, não em rodapé

1. **Dono no fechamento** — pior nota da grade (**2/10**). Card fechado segue `unowned`.
2. **Changelog** — gerador existe e está **desligado**; `done_at` em **1 de 59** SPECs há 6 semanas.
3. **Agregação `reopen_rate`** — a 0356 declara o gate de reversão como **procedimento manual** até alguém construí-la. Fórmula pronta: `done→review` por `author` vs a mesma razão para humano (`mcp_task_events` já tem `from_value`/`to_value`/`author`).
4. **Detector de contradição ADR × ADR** — o buraco que deixou 0302×0337 viver 2 semanas.
5. **114 US** com âncora e sem declaração — triagem é decisão de [W]; `US-TR-309`/`310` existem pra isso.

## Erros meus registrados (não apagados)

- **Denominador inventado**: disse "91 SPECs", são **59** — `rg` sem âncora `^` casou 32 fixtures. Classe LC-08.
- **`~5%` inventado** dentro da 0355 — corrigido pela 0356; a forma certa é comparação com o incumbente, não número escolhido.
- **Reportei "92 pass, pronto pro merge" de um PR já mergeado** — `gh pr checks` responde igual pra aberto e fechado. Consequência real: a errata ficou órfã 1h32 e só entrou no #4970.
- **Duas propostas rejeitadas** em review adversarial antes de acertar o caminho.

## Nota de método

**Sete máquinas** pegaram erro meu nesta sessão — `gate-selftest`, `memory-schema`, `deadlink-gate`, `Append-only canon`, `PHPStan`, `casos-gate`, `block-memory-drift`. Nenhum apareceu por reler código; todos por rodar.

Os **dois** que máquina nenhuma pegou (número inventado em canon · verde de PR mergeado) foram achados porque [W] perguntou. Essas duas superfícies continuam cegas.
