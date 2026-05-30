---
slug: design-request-ledger-incremental
title: "Design Request Ledger — camada incremental (idempotency key + delta manifest + checkpoint) no MCP"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
proposed_at: "2026-05-30"
module: governance
quarter: 2026-Q2
tags: [governance, design-system, incremental, idempotency, checkpoint, cdc, mcp, claude-design]
related:
  - 0236-governanca-evolucao-doc-design
  - 0233-ativacao-memoria-momento-decisao
  - 0053-mcp-server-governanca-como-produto
authors: [W, C]
---

# Proposta — Design Request Ledger (camada incremental)

> **Origem:** Wagner 2026-05-30, fim de sessão. *"se adicionar requisitos técnicos, campos novos, a estrutura deve entender o que mudou e o design não precisar ler tudo... cada pedido deve ter um número e o claude entender que já processou. use o mcp pra melhorar o contexto."*
> **Continuar com MCP ATIVADO** (esta sessão estava sem MCP conectado — só docs).

## Problema
O sistema de governança de design ([ADR 0236](../0236-governanca-evolucao-doc-design.md) + índice + pré-flight + golden + grade) é o **"o quê"**, mas falta a **incrementalidade**: hoje o Claude Design releria a tela inteira a cada pedido, sem saber o que mudou nem até onde já fez nem se já processou aquele pedido.

## Proposta — tabela `mcp_design_requests` no MCP (CT 100)
Cada pedido de design = registro governado idempotente:
```
REQ-NNN  (idempotency key — Stripe pattern)
  status: received → processing → done      # "já processei?" — re-ver REQ done = pula
  delta_manifest:                            # "o que mudou?" — CDC + traceability matrix
    - tela / seção / campo afetado + âncora por LINHA real (TraceLLM: localized rewrite)
  checkpoint:                                # "até onde fiz?" — Temporal/LangGraph
    - seção: done | untouched (granularidade = 1 seção, nunca no meio)
  resultado: <hash do diff aplicado>         # retorna igual no retry (Stripe: guarda resultado, não só flag)
  ttl: 24h pra dedup efêmero (design-state durável é append-only à parte)
```
**Fluxo:** pedido → REQ-NNN → consulta ledger MCP → se `done`, pula (idempotente) → senão lê **só o delta ancorado** (não a tela toda) → aplica regra do golden do arquétipo → grava checkpoint + resultado no MCP.

## Estado-da-arte (validação + grade da ideia do Wagner)
| Componente | Padrão SOTA | Nota |
|---|---|---:|
| nº de pedido / "já processei" | Idempotency Key (Stripe) — guarda resultado, não flag; TTL | 8/10 |
| estrutura entende o que mudou | CDC + Requirements Traceability Matrix (TraceLLM/IncreRTL) — delta ancorado por linha | 7/10 |
| resume de onde parou | Durable execution / checkpoint (Temporal/LangGraph) — entre unidades, não no meio | 8/10 |
| usar o MCP como contexto | State backend durável (DynamoDB/Redis) — projeto já tem `mcp_*` | 9/10 |
| **Geral** | a intuição está no nível dos melhores; 20% = mecânica precisa | **80/100** |

## Próximos passos (sessão com MCP)
1. Validar com `decisions-search` se já há schema `mcp_design_requests` (anti-dup).
2. Emenda ao ADR 0236 OU ADR novo + spec da tabela (DDL idempotente, multi-tenant `business_id`).
3. Skill `design-memoria-reprocess` (gatilhos G1/G2/G3) passa a consumir o ledger.
4. Time MCP implementa a tabela no CT 100 (mcp.oimpresso.com).

## Sources (pesquisa 2026-05-30)
Stripe idempotent requests · LangGraph durable execution · Diagrid "checkpoints ≠ durable execution" · TraceLLM/IncreRTL (arXiv) traceability incremental.
