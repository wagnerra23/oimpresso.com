---
date: "2026-07-28"
time: "1747 BRT"
slug: "chat-sse-raiz-observavel"
tldr: "O PR #4985 fechou a raiz e a correlação do chat SSE sem emissores paralelos. Retomar pela prova de runtime da Etapa 0; online_eval continuou dark e depende de autorização [W]."
decided_by: [W]
cycle: null
prs: [4985]
us:
  - "US-COPI-137"
  - "US-COPI-138"
next_steps:
  - "Executar baseline real da Etapa 0 no CT 100"
  - "Medir se hooks internos de retrieval pagam a granularidade residual da Etapa 1"
  - "Só iniciar Etapa 3 após autorização e calibração humana"
related_adrs:
  - "0093-multi-tenant-isolation-tier-0"
  - "0132-langfuse-self-host-ct100"
  - "0318-ragas-eval-real-mata-tautologia-ct100-staging"
---

# Handoff 2026-07-28 17:47 BRT — chat SSE com raiz observável

## TL;DR

O código do PR #4985 passou a manter uma raiz OTel/Langfuse durante o streaming real e a reutilizá-la na generation e no retrieval. Os testes locais passaram; produção não foi inferida. A próxima prova pertence à Etapa 0 no CT 100.

## Estado atual dos artefatos

| Artefato | Estado medido em 2026-07-28 |
|---|---|
| `OBSERVABILITY.md` Etapa 1 | raiz/correlação implementadas; subspans internos de retrieval ainda parciais |
| `OBSERVABILITY.md` Etapa 2 | implementada e na lane SQLite de PR |
| `jana.chat.stream` | coberto com lifecycle completo, erro parcial e cancelamento |
| Langfuse listener | uma generation na raiz existente; fallback global preservado |
| Retrieval | `jana.retrieval.query` ligado à raiz; Hyde/BM25/rerank ainda sem hooks do driver |
| Online eval | construído, default OFF; nenhuma ativação nesta sessão |

## PR

| PR | Estado no momento deste snapshot | Conteúdo |
|---|---|---|
| [#4985](https://github.com/wagnerra23/oimpresso.com/pull/4985) | aberto | raiz SSE, correlação, resultados estruturais e catracas |

## Validação local

- Pest: 48 passed, 214 assertions;
- PHPStan: sem erros nos oito arquivos de produção alterados;
- plan-health: 0 vermelho, 16 amarelos preexistentes;
- plans-index: arquivo gerado em dia;
- diff-check: sem whitespace inválido.

## Decisões preservadas

- reutilizar `OtelHelper`, `LangfuseClient`, listener, decorator e allowlist existentes;
- não duplicar telemetria do provider no controller;
- exportar classe do erro, nunca a mensagem potencialmente PII;
- persistir trecho parcial já entregue;
- cache hit não inventar generation;
- não ligar avaliação online sem [W].

## Pendências

- [ ] Prova operacional do CT 100 — owner: W/C, Etapa 0.
- [ ] Decidir se hooks de Hyde/BM25/rerank pagam o custo — owner: W.
- [ ] Calibrar juiz com amostra humana antes de qualquer flip — owner: W.

## Estado MCP no momento do fechamento

As tools MCP do oimpresso não estavam disponíveis nesta sessão após a busca de capacidades. Foi usado o fallback autorizado pelos arquivos canônicos locais:

### cycle/task

```text
Não medido — não foi fabricado cycle nem task local.
O plano permaneceu status=proposto em memory/requisitos/Jana/OBSERVABILITY.md.
```

### sessões e decisões

```text
Lidos localmente: memory/08-handoff.md, a sessão anterior do plano,
SPEC Jana e ADRs 0035, 0093, 0132 e 0318.
```

### atividade paralela

```text
origin/main avançou 1 commit durante a sessão (#4983).
Rebase concluído sem conflito antes da abertura do PR #4985.
```

## Referências

- Session log: [2026-07-28-implementacao-observabilidade-chat-sse.md](../sessions/2026-07-28-implementacao-observabilidade-chat-sse.md)
- Plano: [OBSERVABILITY.md](../requisitos/Jana/OBSERVABILITY.md)
- ADR 0130: [Handoff append-only + MCP-first](../decisions/0130-handoff-append-only-mcp-first.md)
