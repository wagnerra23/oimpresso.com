---
date: "2026-07-28"
hour: "16:37 BRT"
duration: "0.5h"
topic: "Plano detalhado do ciclo de observação e aprendizado da Jana"
authors: [W, C]
outcomes:
  - "OBSERVABILITY.md tornou-se o dono do plano vivo, sem roadmap paralelo"
  - "O plano ganhou 10 etapas com entradas, ações, aceite, rollback, métricas e responsáveis"
  - "O estado real separa mecanismo construído, runtime provado e lacunas abertas"
  - "O ciclo exige revisão humana, golden set e comparação com o incumbente antes do rollout"
prs: []
us:
  - "US-COPI-137"
  - "US-COPI-138"
related_adrs:
  - "0035-stack-ai-canonica-wagner-2026-04-26"
  - "0093-multi-tenant-isolation-tier-0"
  - "0132-langfuse-self-host-ct100"
  - "0318-ragas-eval-real-mata-tautologia-ct100-staging"
---

# Session log 2026-07-28 — plano do ciclo observar → aprender

## TL;DR

O plano foi salvo no documento dono [`Jana/OBSERVABILITY.md`](../requisitos/Jana/OBSERVABILITY.md). Não nasceu outro arquivo de plano: o catálogo de spans, exportadores e lacunas agora contém o caminho de execução que fecha o ciclo de observação e aprendizado.

O ciclo não foi declarado fechado. O código já possui listener Langfuse para streaming, heartbeat do destino e avaliação online local, mas ainda faltam prova operacional, trace raiz do generator, calibração humana, feedback correlacionado, promoção revisada ao golden set e validação pós-deploy.

## Correções de estado

- “chat streaming não emite rastro nenhum” ficou registrado como afirmação vencida: o listener global trata `StreamingAgent`/`AgentStreamed`.
- O residual verdadeiro é trace parcial: a chamada ao modelo tem emissor Langfuse, mas cache, clarificação, persistência e erro SSE não vivem sob um span raiz comum.
- `JudgeTraceOnlineJob` e `OllamaRagasJudge` já foram construídos; o fluxo permanece dark porque `copiloto.online_eval.enabled=false`.
- HTTP 200 do Langfuse prova a via, não prova ingestão; `jana:health-check` continua sendo o dono do heartbeat de fluxo.

## Estrutura salva

O plano possui dez etapas:

0. provar o baseline;
1. criar trace raiz generator-aware;
2. proteger a fiação SDK → listener → job no CI;
3. calibrar, ativar e observar a avaliação local;
4. capturar feedback humano por mensagem;
5. transformar sinais em candidatos revisáveis;
6. promover defeito confirmado ao golden set existente;
7. corrigir por PR e comparar com o incumbente;
8. fazer rollout gradual e medir produção;
9. registrar o aprendizado durável e a recorrência.

Cada etapa declara pergunta, ações, aceite e rollback. A matriz de responsabilidades reserva ao [W] autorização LGPD, aposta do plano, revisão de trade-offs e rollout.

## Barreiras contra ciclo circular

- score automático nunca altera prompt, retrieval, configuração ou código;
- faithfulness não é tratado como correção;
- juiz indisponível vira “não medido”;
- caso só entra no golden set após revisão humana e remoção de PII;
- mudança só promove quando comparada ao incumbente;
- o score online terá consumidor advisory no `jana:health-check`, não gate autônomo.

## Estado de governança

- status do plano: `proposto`;
- cycle: não apostado;
- próxima revisão: 2026-08-04;
- slug MCP reservado: `jana-ciclo-observar-aprender`;
- execução não foi fabricada no índice: não há `parent_plan` até tasks reais existirem.

## Validação

- `node scripts/governance/plans-index.mjs --write`
- `node scripts/governance/plans-index.mjs --check`
- `node scripts/governance/plan-health.mjs`
- `node scripts/governance/system-map.mjs`
- `node scripts/governance/system-map.mjs --check`
- `node scripts/governance/memory-health.mjs --json --warn-only`
- `git diff --check`
