---
date: "2026-07-02"
hour: "08:00"
topic: "Langfuse religado — diagnóstico da telemetria morta há 2 meses + listener global nos events laravel/ai (item #4 loop IA-OS / COPI-44)"
authors: [C]
prs: []
us: ["US-INFRA-016"]
related_adrs:
  - 0132-langfuse-self-host-ct100
  - 0035-stack-ai-canonica-wagner-2026-04-26
  - 0141-agents-tool-use-pattern-claude-code
  - 0051-schema-proprio-adapter-otel-genai
---

# Langfuse religado — listener global de telemetria LLM

## Contexto

Handoff da sessão anterior: stack Langfuse CT 100 está UP (6 containers healthy),
staging tem `LANGFUSE_HOST`/KEYS no env, mas a API retorna só **2 traces, ambos de
2026-05-11**. Infra ligada, ZERO emissão há ~2 meses. Item #4 do loop IA-OS +
task COPI-44 (parte OTEL no driver).

## Diagnóstico (por que a telemetria morreu em 2026-05-11)

A cadeia tinha **3 gates**, e o furo real era arquitetural:

1. **Emissão vivia só em `LaravelAiSdkDriver::responderChat` (blocking)** —
   `emitirOtelGenAi` + `emitirLangfuseTrace`. Mas o tráfego real migrou pra:
   - **Agents Promptable (ADR 0141)** — BriefDiarioAgent, KbAnswerAgent, etc
     chamam o LLM direto via laravel/ai, sem passar pelo driver;
   - **chat streaming** (`responderChatStream`, caminho vivo da UI) — NUNCA
     teve emissão de telemetria.
   Prova: `storage/logs/otel-gen-ai*.log` em prod para em **2026-05-10**.
2. **`LANGFUSE_ENABLED` ausente** em prod E staging (default false) — o caminho
   `LangfuseClient` estava morto mesmo onde instrumentado. (Os 2 traces de
   11/mai vieram do channel OTLP `otel-gen-ai-langfuse`, que só exige HOST+PK.)
3. **Fila `default` de prod NÃO tem worker** (`QUEUE_CONNECTION=database`;
   Kernel só agenda `queue:work` pra `whatsapp`/`whatsapp-history`) — o
   `LangfuseTraceJob` (dispatch default `queue`) apodreceria. Achado colateral
   grave: **32.320 jobs represados na fila default** (+15k customer-memory,
   384 nfe) — task separada spawnada pra investigar.

## O que foi feito (PR desta sessão)

- **`Modules/Jana/Listeners/Telemetry/LangfuseAgentTelemetryListener`** (novo):
  ponto ÚNICO de observabilidade — subscriber nos events nativos do laravel/ai.
  `PromptingAgent`/`StreamingAgent` guardam clock por `invocationId`;
  `AgentPrompted`/`AgentStreamed` emitem trace + generation com `business_id`
  (extraído do Agent — convenção `businessId` público ADR 0141, ou
  `conversa->business_id` no ChatCopilotoAgent), tokens (incl. prompt cache),
  modelo, provider e duração. Cobre TODOS os Agents + chat blocking/streaming.
  PII: input/output passam pelo PiiRedactor dentro do LangfuseClient
  (`langfuse.redact_pii` default ON). Fail-open em toda borda.
- **`LangfuseClient::traceComGeneration()`**: trace-create + generation-create
  num ÚNICO batch HTTP (metade do overhead em dispatch=sync). Refactor extraiu
  `traceEvent()`/`generationEvent()` dos métodos existentes (sem mudança de
  comportamento — LangfuseClientTest 10/10 verde).
- **`responderChat`**: emissão Langfuse do caminho de SUCESSO removida (o
  listener cobre via AgentPrompted; evita trace duplicado). Caminho de ERRO
  mantido (exception aborta o SDK antes do event — listener nunca vê a falha).
- **5 Pest novos** (`LangfuseAgentTelemetryListenerTest`): batch shape +
  business_id, subclass AgentStreamed (dispatcher Laravel não propaga por
  herança — registro explícito), duração via invocationId, flag off = zero
  side-effect, fail-open.
- **Tracker** `.claude/loop-fechar-o-loop.json` item #4 atualizado com
  progresso + critério de done endurecido (trace real de PROD na API).

## Evidência (R1 — smoke real)

- **Pest no CT 100** (`oimpresso-staging`, MySQL real): 5/5 novos verdes,
  LangfuseClientTest 10/10 verde. `OtelGenAiEmissionTest` tem 1 fail
  **pré-existente em main** (business_id 1≠4 — reproduzido com código de main
  intocado; não é regressão deste PR).
- **E2E staging**: `LANGFUSE_ENABLED=true` + `LANGFUSE_DISPATCH=sync` setados
  no `.env` do staging → `BriefDiarioAgent(1)->prompt(...)` real (1690 tokens
  in / 100 out, gpt-4o-mini) → **trace visível na API Langfuse**:
  `name=brief-diario-agent`, `environment=staging`, `metadata.business_id=1`,
  `totalCost=$0.0003135` (custo calculado automaticamente pelo Langfuse),
  `latency=7s`, 1 generation anexada. Primeiro trace desde 2026-05-11.
- Working tree do staging restaurado pra HEAD após o smoke (zero drift;
  código chega lá via deploy pós-merge). Flags LANGFUSE no `.env` staging
  mantidas (estado desejado).

## Pendências (próxima sessão / pós-merge Wagner)

1. Merge do PR (R10 Wagner) → deploy Hostinger.
2. Prod `.env`: adicionar `LANGFUSE_ENABLED=true` + `LANGFUSE_DISPATCH=sync`
   (fila default sem worker!) + `php artisan config:cache`.
3. Smoke prod: 1 chamada Jana real → trace `environment=production` na API →
   aí sim flip `done: true` no tracker item #4.
4. `tasks-update COPI-44` — tools MCP indisponíveis nesta sessão desktop;
   registrar na próxima sessão com MCP conectado.
5. Task spawnada (chip): investigar 32k jobs represados fila default prod
   (inclui 384 jobs NFe — potencialmente emissões fiscais pendentes).
