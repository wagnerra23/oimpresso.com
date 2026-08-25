---
id: reference-gov-conhecimento-indexado
name: Governança — Como o conhecimento é indexado
description: O repositório é a fonte, o índice é cache governado — as cinco etapas do caminho do git até o recall, e por que a qualidade é medida contra gabarito real.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-08-03"
nav_group: governanca
nav_order: 20
lente: [operar, construir]
---

# Governança — Como o conhecimento é indexado

> **O repositório é a fonte; o índice é cache governado.** Nunca o contrário
> ([ADR 0061](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)).

## As cinco etapas

| # | Etapa | Por que é assim |
|---|---|---|
| 1 | **Nasce em `memory/`, no git** | versionado, revisável, com histórico. Zero memória privada do agente — o que o agente "lembra" e ninguém pode ler não existe. |
| 2 | **Webhook empurra pro corpus** | `mcp_memory_documents`, com PII redigida no caminho. O slug é determinístico, derivado do path. |
| 3 | **Dois índices convivem, de propósito** | léxico pra termo raro e código (`NF-e`, `REP-P`); vetorial pra pergunta em linguagem natural. Um cobre o buraco do outro. |
| 4 | **O recall é reordenado** | reranking + decaimento no tempo. Sem isso, a verdade de seis meses atrás volta com a mesma confiança da de ontem. |
| 5 | **A qualidade é medida contra gabarito real** | a eval que comparava o gabarito consigo mesmo passava sempre — e foi morta por isso ([ADR 0318](../decisions/0318-ragas-eval-real-mata-tautologia-ct100-staging.md)). |

## Onde cada documento pode morar

Não é gosto: é registro **declarado** em
[`document-placement.json`](../../scripts/governance/document-placement.json), consultado pelo
classificador de realocação. Área não declarada vira `review` — a máquina **não adivinha**.
Algumas pastas são `protected` porque o path é contrato de código (ex: os dicionários de enum
lidos pelo guard de domínio).

## Identidade e link-rot

Todo documento ganha um `id` derivado do path
([`doc-id-stamp`](../../scripts/governance/doc-id-stamp.mjs)), e o índice `id → path` é gerado
([`doc-id-index`](../../scripts/governance/doc-id-index.mjs)). Quando um arquivo se move, o
[`doc-auto-relink`](../../scripts/governance/doc-auto-relink.mjs) compara índice commitado ⇔
corpus atual e reescreve os links — **menos** nos referrers append-only, que recebem um stub no
path antigo em vez de serem reescritos.

## A doutrina por trás disto tudo

**Derivado e enforçado sobrevive; escrito e lembrado apodrece**
([ADR 0256](../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)). É por isso que
o rail desta documentação é derivado do disco, que o índice de ADRs é gerado, e que esta página
aponta pros scripts em vez de descrever o que eles fazem linha a linha.
