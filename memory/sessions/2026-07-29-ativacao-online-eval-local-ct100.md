---
date: "2026-07-29"
topic: "Ativação e prova ponta a ponta da avaliação online local da Jana"
prs: [5005]
---

# Avaliação online local da Jana — ativação com recibo

**TL;DR:** o PR #5005 ligou a amostra online de 5% com juiz `qwen2.5:3b` local no CT 100, sem criar avaliador, painel ou dono paralelo. Um worker dedicado passou a consumir `database:jana-online-eval`; um caso sintético atravessou Hostinger → MySQL → CT 100 → Ollama → Langfuse em 25 s. O score chegou e o health check existente passou a enxergá-lo.

## O problema que impedia uma ativação real

Mudar apenas `copiloto.online_eval.enabled` para `true` não funcionaria. O job não declarava fila, então seria consumido pelo worker `default` da Hostinger; dali, `http://ollama-embedder:11434` não resolve, pois esse hostname existe somente na rede Docker do CT 100.

A correção manteve os donos existentes:

- `JudgeTraceOnlineJob` passou a declarar conexão `database` e fila `jana-online-eval`;
- o Compose existente ganhou um worker para essa fila, usando a mesma imagem/runtime do MCP;
- `LangfuseClient` continuou sendo o único emissor de score;
- `jana:health-check` continuou sendo o único painel operacional e ganhou o check advisory `online_eval_score_uptime_7d`;
- o juiz continuou sendo `OllamaRagasJudge`, com `PiiRedactor` antes da inferência.

Não foram criados gerador, serviço de avaliação, banco, painel ou documentação arquitetural concorrentes.

## Medições antes do flip

| Medida | Resultado em 2026-07-29 |
|---|---|
| Modelo local | `qwen2.5:3b` disponível; smoke JSON válido em 16,4 s |
| Baseline controlado | 1 caso, faithfulness `0,50`, relevancy `1,00`, context recall `0,50`, zero falha de infra |
| Piso de faithfulness | `0,65`; baseline abaixo do piso |
| Fila Hostinger antes | `database:default` vazia |
| Langfuse antes | 0 scores `ragas_faithfulness_online` |
| OTel Hostinger | permaneceu desligado, conforme separação Hostinger ≠ CT 100 |

O baseline de um caso não calibra o juiz. Por isso o score online foi ativado como **advisory** e não promove memória, altera prompt nem bloqueia chat/deploy.

## Mudança entregue

- config: `enabled=true`, `judge=local`, `sample_rate=0.05`;
- job: `tries=1`, timeout de 180 s, fila dedicada no banco canônico;
- CT 100: container `oimpresso-jana-online-eval-worker`;
- sentinela: `online_eval_score_uptime_7d`, distinguindo desligado, não configurado, inacessível, ilegível, sem score e vivo;
- documentação viva: `memory/requisitos/Jana/OBSERVABILITY.md` aponta para este recibo.

## Verificação de código

- testes focados no CT 100: **30 passed, 131 assertions**;
- PHPStan dos arquivos de produção: **0 errors**;
- Compose renderizado com sucesso;
- schema do briefing e `git diff --check`: verdes;
- CI obrigatório do PR #5005: todos os gates verdes, incluindo Pest Unit em 7m41s e PHPStan em 2m59s;
- merge pelo `scripts/gh/safe-merge.sh`, com SHA do head pinado.

## Recibo de runtime

| Marco | Evidência |
|---|---|
| Merge | PR #5005 → `f77fe1d9d16b25edc4e670da1857d864f8f8a473` |
| Hostinger | deploy workflow `30447485184` concluído com sucesso; config online-eval e Langfuse `true` |
| CT 100 | self-update carregou `f77fe1d9d`; MCP saudável e worker dedicado `Up` |
| Trace sintético | `0595548c-5686-4519-8c5e-b8b4ad34eb5d`, sem dado de cliente |
| Fila | `RUNNING` → `DONE` em **25 s** → 0 pending / 0 reserved |
| Langfuse | `ragas_faithfulness_online=1`, recebido em `2026-07-29T11:34:16.064Z` |
| Health check | `langfuse_trace_uptime_24h=297`; `online_eval_score_uptime_7d=1`, ambos `ok` |

O `1` do smoke prova transporte e execução, não qualidade geral: pergunta, contexto e resposta foram construídos para serem perfeitamente coerentes. A medição honesta de qualidade continua sendo o baseline `0,50` até haver amostra humana suficiente.

## O que ficou deliberadamente fora

- ativar OTel na Hostinger;
- enviar conteúdo a juiz externo;
- converter score automático em verdade ou aprendizado;
- criar outro painel;
- avançar sozinho para feedback humano, fila de candidatos ou golden set.

Esses elos continuam descritos nas Etapas 4–9 de `OBSERVABILITY.md`. A ativação fecha **observação automática**, mas o ciclo de aprendizado só fecha quando houver revisão humana e promoção controlada.

## Rollback

Voltar `copiloto.online_eval.enabled=false` por PR. O worker pode permanecer ocioso; scores históricos continuam como recibo append-only no Langfuse.

## Estado MCP no fechamento

As tools MCP de ciclo/tasks não estavam disponíveis nesta sessão. O fallback canônico foi usado: arquivos Tier 0 locais, Git/GitHub, SSH Hostinger e `tailscale ssh` CT 100. Nenhum cycle/task foi fabricado ou alterado.

## Ponteiros

- Plano vivo: [OBSERVABILITY.md](../requisitos/Jana/OBSERVABILITY.md)
- Handoff: [2026-07-29-0836-online-eval-local-ativado.md](../handoffs/2026-07-29-0836-online-eval-local-ativado.md)
- PR: [#5005](https://github.com/wagnerra23/oimpresso.com/pull/5005)
- ADRs: [0062](../decisions/0062-separacao-runtime-hostinger-ct100.md) · [0093](../decisions/0093-multi-tenant-isolation-tier-0.md) · [0132](../decisions/0132-langfuse-self-host-ct100.md)
