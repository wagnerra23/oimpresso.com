---
date: "2026-07-28"
hour: "17:47 BRT"
duration: "1.5h"
topic: "Implementação da raiz observável do chat SSE da Jana sem emissores paralelos"
authors: [W, C]
outcomes:
  - "O chat SSE ganhou raiz OTel e Langfuse durante todo o consumo do generator"
  - "Listener LLM e retrieval passaram a reutilizar o trace raiz existente"
  - "Cache, erro parcial, abandono e PII ganharam cobertura na lane SQLite já existente"
  - "A Etapa 1 permaneceu honestamente parcial quanto aos subspans internos de retrieval"
prs: [4985]
us:
  - "US-COPI-137"
  - "US-COPI-138"
related_adrs:
  - "0035-stack-ai-canonica-wagner-2026-04-26"
  - "0093-multi-tenant-isolation-tier-0"
  - "0132-langfuse-self-host-ct100"
  - "0318-ragas-eval-real-mata-tautologia-ct100-staging"
---

# Session log 2026-07-28 — implementação da observabilidade do chat SSE

## TL;DR

As Etapas 1 e 2 do plano canônico [`Jana/OBSERVABILITY.md`](../requisitos/Jana/OBSERVABILITY.md) avançaram sem criar cliente, listener, job, workflow ou documento paralelo. O fluxo real do chat passou a compartilhar uma raiz entre OTel, Langfuse, generation do modelo e retrieval; cache, erro parcial e abandono ficaram observáveis e cobertos na lane de PR.

A etapa não foi declarada operacional em produção: a prova do CT 100 continuou na Etapa 0, e os subspans internos de Hyde/BM25/rerank continuaram aguardando hooks reais do driver.

## Contexto

O plano anterior havia medido que o listener global já emitia a chamada LLM do streaming, mas cache, clarificação, persistência, erro e abandono não compartilhavam lifecycle. O pedido desta sessão foi executar sem duplicar informações ou mecanismos.

## Entregas

- `ChatController::sendStream()` abriu a raiz depois do ownership e antes da primeira persistência, mantendo-a ativa durante os chunks.
- `LangfuseAgentTelemetryListener` anexou uma única generation ao trace do chat e preservou o fallback global fora dele.
- `RetrievalTelemetryDecorator` reutilizou o trace publicado no request scope.
- `AiAdapter::ultimoResultadoStream()` passou a expor somente o desfecho estrutural que o controller não inferia dos chunks.
- `OtelHelper::annotateCurrent()` atualizou o span já ativo; não criou outro exporter.
- `.github/ci-sqlite-pest.list` recebeu os testes nos donos existentes.
- PR [#4985](https://github.com/wagnerra23/oimpresso.com/pull/4985) reuniu o incremento.

## Evidência

- 48 testes passaram com 214 assertions nos seis arquivos relevantes;
- PHPStan dos oito arquivos de produção alterados passou sem erros;
- `plans-index --check` passou;
- `plan-health` permaneceu com 0 vermelho e 16 amarelos preexistentes;
- teste contrafactual da Etapa 2 já havia provado que remover `AgentStreamed → onEnd` derrubava a catraca;
- cache hit produziu raiz sem `generation-create`;
- erro depois do primeiro chunk terminou como `partial_error`;
- abandono terminou como `cancelled` e preservou o trecho entregue;
- CPF de fixture e mensagem interna do provider não apareceram nos eventos exportados.

## Sem duplicação

- nenhuma segunda implementação de Langfuse;
- nenhum segundo listener do Laravel AI;
- nenhum segundo mapa ou plano;
- nenhum workflow novo;
- modelo/tokens/duração permaneceram com o listener e o provider;
- o controller recebeu apenas `path`, cache, recall, jobs e estado terminal.

## Limites e próximos passos

1. Executar a Etapa 0 no CT 100 e guardar recibo sem conteúdo bruto.
2. Expor hooks reais do retrieval antes de declarar Hyde/BM25/rerank como filhos observados.
3. Não ativar `online_eval` sem autorização [W] e calibração humana da Etapa 3.

## Referências

- Handoff: [2026-07-28-1747-chat-sse-raiz-observavel.md](../handoffs/2026-07-28-1747-chat-sse-raiz-observavel.md)
- Plano: [OBSERVABILITY.md](../requisitos/Jana/OBSERVABILITY.md)
- PR: [#4985](https://github.com/wagnerra23/oimpresso.com/pull/4985)
