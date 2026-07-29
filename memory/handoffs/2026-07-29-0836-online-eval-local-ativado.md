---
date: "2026-07-29"
time: "08:36 BRT"
slug: online-eval-local-ativado
tldr: "A avaliação online local da Jana foi ativada sem mecanismo paralelo: worker dedicado no CT 100, score sintético recebido pelo Langfuse em 25 s e heartbeat advisory vivo."
decided_by: [W]
cycle: null
prs: [5005]
us:
  - "US-COPI-137"
next_steps:
  - "Calibrar o juiz contra amostra humana repetida antes de usar limiar como verdade"
  - "Definir owner/cadência da revisão humana da Etapa 4"
  - "Manter OTel da Hostinger desligado enquanto vigorar a separação de runtime"
related_adrs:
  - "0062-separacao-runtime-hostinger-ct100"
  - "0093-multi-tenant-isolation-tier-0"
  - "0132-langfuse-self-host-ct100"
---

# Online eval local ativado

## TL;DR

O PR #5005 foi mergeado e implantado na Hostinger e no CT 100. A amostra de 5% usa `OllamaRagasJudge` local, PII redigida e fila `database:jana-online-eval`; não há juiz externo nem painel novo.

## Recibo mínimo

- merge/runtime: `f77fe1d9d`;
- worker: `oimpresso-jana-online-eval-worker` ativo no CT 100;
- trace sintético sem PII: `0595548c-5686-4519-8c5e-b8b4ad34eb5d`;
- processamento: 25 s, fila voltou a zero;
- Langfuse: `ragas_faithfulness_online=1`;
- `jana:health-check`: `online_eval_score_uptime_7d=1`, `ok`, advisory;
- baseline de qualidade separado: faithfulness `0,50` em 1 caso — ainda abaixo do piso `0,65`, portanto não vira verdade automática.

O recibo detalhado, validações e rollback estão no [session log](../sessions/2026-07-29-ativacao-online-eval-local-ct100.md). A arquitetura e as próximas etapas continuam no [plano vivo](../requisitos/Jana/OBSERVABILITY.md); este handoff não as recopia.

## Estado MCP no momento do fechamento

As tools MCP de ciclo/tasks não estavam disponíveis. Foram usados os fallbacks canônicos Git/GitHub, SSH Hostinger e `tailscale ssh` CT 100. Nenhum cycle ou task foi inventado; o trabalho permaneceu off-cycle.
