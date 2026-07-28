---
slug: 0355-doneness-consolidada-ancora-fecha-dod-veta
number: 355
title: "Done-ness consolidada — a âncora fecha, o DoD aberto veta, o `status:` morre de vez (supersede 0302 + 0337, que se contradiziam)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-28"
accepted_via: "Wagner 2026-07-28, textual: 'remova as duas e consolide uma nova se for melhor, só quero que consiga indexar melhor resolva isso' — depois de eu apresentar a contradição 0302×0337 verificada na fonte e de DUAS propostas minhas de conserto por lint terem sido rejeitadas em review adversarial. O aceite cobre a POLÍTICA (fonte única + gatilho do forward-close + forward-only). O código em TaskParserService e qualquer promoção de gate vão em PR próprio, com evidência."
module: governance
quarter: 2026-Q3
tags: [governance, tasks, mcp, taskregistry, doneness, ancora, forward-close, dod, indexacao, contradicao-de-canon]
supersedes: [0302-fonte-unica-doneness-anchor-aposenta-status-spec, 0337-emenda-0144-forward-close-por-ancora-verificada]
superseded_by: []
related: [0144-tasks-db-canonico-spec-template, 0273-anchor-spec-codigo-formato-canonico-fluxo-novo, 0070-jira-style-task-management-current-md-removed, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes, 0264-governanca-executavel-trio-dominio-e2e]
---

# ADR 0355 — Done-ness consolidada: a âncora fecha, o DoD aberto veta

## Contexto

Duas ADRs **aceitas** mandavam o contrário uma da outra, e o efeito era mensurável.

A [0302](0302-fonte-unica-doneness-anchor-aposenta-status-spec.md) §2 elegeu a âncora como fonte única de done-ness e **aboliu o campo**, textual:

> **`status: done` deixa de existir** no fluxo novo. Done-ness não se digita — lê-se da âncora.
> **US nova nasce sem `status:`.** O `_TEMPLATE_SPEC.md` … passa a omitir o token `status:`

Conferido no template: o bloco de US tem `**Implementado em:** _pendente_` e **zero** `status:`.

A [0337](0337-emenda-0144-forward-close-por-ancora-verificada.md), três meses depois, fez o forward-close exigir **duas** condições — âncora `anchored_ok` **e** `status: done` declarado no SPEC.

**A consequência é aritmética, não opinião: toda US nascida CORRETAMENTE sob a 0302 é, por construção, incapaz de ser fechada pelo forward-close da 0337** — a condição #2 lê um campo que o template canônico omite de propósito.

### O que isso custou (medido 2026-07-28)

| | |
|---|---|
| SPECs vivos (fora `historical`/`arquivado`) | 51 |
| US com âncora `anchored_ok` | 302 |
| já declaram done | 202 |
| **paradas por causa da contradição** | **85** — sendo **72 sem DoD escrito** |

E o custo de descoberta: a contradição ficou **duas semanas** em pé sem detector nenhum. O `memory-health` tem checagem de contradição (`fact-anchor`), mas ancora fato-de-doc em `package.json`/`composer.json`/`Modules` — fonte de **código**, nunca outra decisão. Ninguém compara ADR × ADR.

### Duas tentativas de consertar por lint, ambas rejeitadas

Registradas em [`memory/sessions/2026-07-28-grade-ciclo-de-vida-da-tarefa.md`](../sessions/2026-07-28-grade-ciclo-de-vida-da-tarefa.md):

1. **Denominador do `doneness-lint` pela âncora** → morreu na contradição acima: flagaria como dívida toda US nascida certa, e a remediação seria escrever o campo que a 0302 aboliu.
2. **DoD 100% marcado como 2º sinal** → morreu com prova literal. Commit `7ebe9ea5d7` (2026-07-28, autor `[C]`) gravou âncora e virou 5 checkboxes no **mesmo commit**, um deles: `- [x] Impressão direta … — **parcial**: … **não há autoprint**`. Um `[x]` cujo texto diz *parcial*. Checkbox é **auto-certificação** — família morta no §5 (`last_validated` · `verificado_em`).

O problema nunca foi de detector. Era de canon.

## Decisão

1. **Fonte única de done-ness = a âncora.** `**Implementado em:** \`path\`(· \`path\`)* · verificado@<sha7> (<AAAA-MM-DD>)` com **todos os paths existentes no disco** (`anchored_ok`, gramática da [ADR 0273](0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md)). Herdado da 0302, sem mudança.

2. **O `status:` do blockquote não tem leitor.** Nenhum mecanismo o consulta pra decidir done-ness. A 0302 já dizia; a 0337 abriu exceção; a exceção acaba aqui. O campo pode existir em SPEC legado — é inerte.

3. **O forward-close dispara quando, e só quando:**
   - o card no DB está **ativo** (não `done`/`cancelled`) — nunca reabre;
   - a âncora é **`anchored_ok`** com sha;
   - **não há item de DoD aberto** (`- [ ]`) na US.

   O DoD entra como **falsificador — veta o fechamento**, nunca como confirmador. Ele **não prova** aceite (um `[x]` é ato de um caractere, sem revisor); ele **desprova**, e desprovar é epistemicamente barato e honesto. É exatamente a direção que o `doneness-lint` já usa (`if (DONE.has(status) && dodOpen > 0)`) e que a revisão adversarial validou como não sendo presence-gate.

4. **Forward-only, pela data da âncora.** O fechamento automático só alcança US cuja âncora carrega data **≥ `2026-07-28`**. O legado medido (**85**) **não** fecha em massa: vira backlog enumerado, visível, que [W] tria quando quiser. Fechar 85 de uma vez — 72 sem critério escrito — trocaria invisibilidade-por-aberto por invisibilidade-por-fechado.

5. **O aceite é garantido na ENTRADA, não no fechamento.** `anchor-lint --check-entry` e `--check-covers` (**já required**) barram US que se declara implementada sem aceite/DoD definido ou sem teste que a cobre. Guarda-se a porta de entrada; o fechamento vira mecânico. Isso substitui o 2º sinal da 0337 sem ressuscitar campo nenhum.

6. **Nunca reabre.** Estado terminal do DB é canon (herdado da 0144 via 0337). Reabertura é ato humano por `tasks-update` (`done→review` é transição legal na FSM).

## Consequências

**Assumidas:**

- **US nova com âncora e sem DoD fecha automaticamente.** Mitigação: o gate de entrada (required) impede que uma US se declare implementada sem aceite/DoD — então "sem DoD" e "anchored_ok" não deveriam coexistir em US nova. Se coexistirem, o buraco é no gate de entrada, e é lá que se conserta.
- **O legado 85 continua aberto** até triagem. Número declarado, não escondido.
- **Os 17 `> status: done` adicionados em 2026-07-28** (PR #4944, pra destravar Auditoria + AssetManagement sob a 0337) ficam **inertes**: os cards já fecharam e o estado é terminal. Não quebram nada; só deixam de ter leitor. Registro honesto — foram escritos sob a regra que esta ADR revoga.

**Ganhas:**

- Existe **uma** regra de done-ness, não duas em conflito.
- US nova indexa certo desde o primeiro dia, sem ninguém digitar campo nenhum.
- O sinal que fecha é **verificável** (paths no disco), não declarado.

## Gate de reversão

Se o forward-close fechar card errado: `tasks-update <id> status:review` reabre (transição legal), e o caso vira emenda a esta ADR — nunca conserto silencioso no script. Se a taxa de fechamento errado passar de ~5% numa janela de 30d, a condição 3 volta a exigir um 2º sinal — e o candidato honesto já está identificado: **`**Testado em:**`**, que é verificável no disco, já é lintado (`TESTADO_RE`) e já é required via `entry/covers`. Não `status:`, não `[x]`.

## O que esta ADR NÃO decide

- **Dono no fechamento.** Card fechado segue `unowned`. É a régua de pior nota da grade (2/10) e continua aberta.
- **Changelog.** O gerador segue desligado por `done_at` existir em 1 de 59 SPECs — condição real, não resolvida aqui.
- **Detector de contradição ADR × ADR.** O buraco que deixou 0302 × 0337 viver duas semanas continua lá. Fica nomeado, sem máquina.
