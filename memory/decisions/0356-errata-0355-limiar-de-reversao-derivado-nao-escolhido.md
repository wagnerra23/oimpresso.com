---
slug: 0356-errata-0355-limiar-de-reversao-derivado-nao-escolhido
number: 356
title: "Errata à 0355 — o limiar do gate de reversão era um número inventado; troca por comparação com o incumbente, e assume que hoje é procedimento manual"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: errata
decided_by: [W]
decided_at: "2026-07-28"
accepted_via: "Wagner 2026-07-28 'ok' à recomendação de corrigir o limiar ANTES do merge, depois de perguntar 'qual nível para tentar de novo, como calcula?' — pergunta que expôs o defeito. Correção entra como ADR de errata (não edição inline) porque ADR canon é append-only e o override do block-memory-drift é env var do processo, indisponível ao agente. Ambas mergeiam no MESMO PR (#4959), então o número inventado nunca chega a valer."
module: governance
quarter: 2026-Q3
tags: [governance, errata, doneness, forward-close, limiar, medicao, gate-de-reversao]
supersedes: []
supersedes_partially: [0355-doneness-consolidada-ancora-fecha-dod-veta]
superseded_by: []
related: [0355-doneness-consolidada-ancora-fecha-dod-veta, 0337-emenda-0144-forward-close-por-ancora-verificada, 0070-jira-style-task-management-current-md-removed]
---

# ADR 0356 — Errata à 0355: o limiar de reversão é derivado, não escolhido

## O erro

A [ADR 0355](0355-doneness-consolidada-ancora-fecha-dod-veta.md) §"Gate de reversão" escreveu:

> Se a taxa de fechamento errado passar de **~5%** numa janela de 30d, a condição 3 volta a exigir um 2º sinal

**O `~5%` foi inventado pelo autor.** Nenhuma medição atrás dele. E pior: **nada no repo computa essa taxa** — varredura contada em `Modules/` e `scripts/`: a única agregação de "reabertura" que existe é do `Modules/Repair` (reabertura de OS), outro domínio.

Um limiar sem derivação e sem máquina é a classe que o próprio corpus condena — catraca sobre número que ninguém mede ([`proibicoes.md`](../proibicoes.md) §5, família `last_validated`/`verificado_em`). Foi escrito na mesma sessão em que essa classe era catalogada.

O defeito só apareceu porque [W] perguntou *"qual nível para tentar de novo, como calcula?"*. Nenhum gate pegaria — não existe checagem de "número afirmado sem fonte" dentro de ADR.

## A correção

A §"Gate de reversão" da 0355 fica **substituída** por:

### O limiar não é um número escolhido — é comparação com o incumbente

A pergunta certa não é *"qual porcentagem?"*, é ***"a máquina erra mais que o humano que ela substitui?"***. Isso é **derivável**, e o dado bruto já existe: `mcp_task_events` grava `status_changed` com `from_value`, `to_value` e `author`.

| termo | de onde sai |
|---|---|
| **denominador** | cards fechados por `author: webhook-sync` na janela — o forward-close se identifica |
| **numerador** | quantos desses receberam depois um `status_changed` `done → review` |
| **linha de base** | a MESMA razão para cards fechados por **humano** (`author ≠ webhook-sync`), mesma janela |
| **dispara a reversão** | taxa da máquina **materialmente acima** da do humano |

A máquina não precisa ser perfeita — precisa **não ser pior que quem ela substituiu**. Se o humano reabre 12% e a máquina 13%, não há regressão; há ruído do processo. Um limiar absoluto (5%, 10%, o que for) mediria a dificuldade do domínio, não a qualidade do mecanismo.

### Estado honesto: hoje é procedimento, não gate

Essa razão **não é computada**. Enquanto não for, o "gate de reversão" da 0355 é um **procedimento manual**: alguém percebe fechamento errado, reabre com `tasks-update <id> status:review` (transição legal), e o caso vira emenda. **Chamar isso de "gate" seria inflar** — está declarado como procedimento, e assim fica até existir a agregação.

O resto da §"Gate de reversão" da 0355 permanece: se a reversão disparar, o 2º sinal volta, e o candidato é **`**Testado em:**`** (verificável no disco, já lintado por `TESTADO_RE`, já required via `entry/covers`) — nunca `status:`, nunca `[x]`.

## O que esta errata NÃO faz

- **Não muda a decisão da 0355.** As 6 cláusulas (âncora fecha · `status:` sem leitor · DoD veta · forward-only · aceite na entrada · nunca reabre) seguem intactas. Só o critério de reversão muda.
- **Não constrói a agregação.** Nomear ≠ entregar. A métrica `reopen_rate` por autor continua sem dono e sem máquina — declarada aqui pra não virar dívida invisível.
- **Não inventa outro número.** Se alguém quiser um limiar absoluto no futuro, que o derive de uma janela medida — e cite a medição.

## Lição perene

Número dentro de ADR canon é lido pela próxima sessão como **derivado**, não como palpite — é exatamente por isso que inventar um é pior que omitir. Onde não há medição, o texto honesto é *"não computado"*, e não uma cifra plausível.
