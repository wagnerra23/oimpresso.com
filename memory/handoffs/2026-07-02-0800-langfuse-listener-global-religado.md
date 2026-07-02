---
date: "2026-07-02"
time: "08:00"
slug: "langfuse-listener-global-religado"
tldr: "Item #4 loop IA-OS: telemetria LLM morta desde 2026-05-11 (emissão vivia só no responderChat blocking; tráfego migrou pra Agents ADR 0141 + streaming). Implementado LangfuseAgentTelemetryListener global nos events laravel/ai, smoke E2E staging VERDE (trace real na API, $0.0003135). PR aberto aguardando merge Wagner + LANGFUSE_ENABLED em prod. Colateral: 32k jobs represados na fila default prod."
prs: []
us: ["US-INFRA-016"]
next_steps:
  - "Wagner: merge do PR do listener (checks verdes)"
  - "Pós-merge: LANGFUSE_ENABLED=true + LANGFUSE_DISPATCH=sync no .env Hostinger + php artisan config:cache (fila default NÃO tem worker — sync obrigatório)"
  - "Smoke prod: 1 chamada Jana → trace environment=production na API → flip done:true no tracker item #4"
  - "tasks-update COPI-44 (MCP indisponível nesta sessão)"
  - "Chip spawnado: investigar 32.320 jobs represados fila default prod (384 são NFe!)"
related_adrs:
  - 0132-langfuse-self-host-ct100
  - 0141-agents-tool-use-pattern-claude-code
---

# Handoff — 2026-07-02 08:00 · Langfuse religado (listener global laravel/ai)

## Estado

- **Diagnóstico fechado**: zero traces desde 2026-05-11 porque (1) emissão só
  existia em `LaravelAiSdkDriver::responderChat` blocking — Agents ADR 0141 e
  chat streaming NUNCA emitiram (log otel-gen-ai prod para em 10/mai, prova);
  (2) `LANGFUSE_ENABLED` ausente em prod/staging; (3) fila default prod sem worker.
- **Fix implementado + testado**: `LangfuseAgentTelemetryListener` (subscriber
  em `PromptingAgent`/`StreamingAgent`/`AgentPrompted`/`AgentStreamed`) +
  `LangfuseClient::traceComGeneration()` (1 batch) + dedup no responderChat.
  Pest CT 100: 5/5 novos + 10/10 client. E2E staging: trace real
  `brief-diario-agent` biz=1 visível na API Langfuse (custo/latência/tokens ok).
- **Staging**: `.env` ganhou `LANGFUSE_ENABLED=true` + `LANGFUSE_DISPATCH=sync`
  (mantido — estado desejado); working tree restaurado pra HEAD (zero drift).
- **Falha pré-existente detectada** (não deste PR): `OtelGenAiEmissionTest`
  1 fail em main (business_id 1≠4).

## Estado MCP no momento do fechamento

Tools MCP oimpresso **indisponíveis nesta sessão** (agente desktop sem servidor
MCP conectado). Snapshot do SessionStart hook (brief #297, gerado ~07:20):
cycle —, HITL pending Wagner 2, Brain B 0/50, 0 incidentes, loop IA-OS pendente
#3 (drift sentinel) e #4 (este — agora só falta prod flip). `tasks-update
COPI-44` fica pra próxima sessão com MCP.

## Detalhe completo

Ver session log: [2026-07-02-langfuse-emissao-religada-listener-global.md](../sessions/2026-07-02-langfuse-emissao-religada-listener-global.md)
