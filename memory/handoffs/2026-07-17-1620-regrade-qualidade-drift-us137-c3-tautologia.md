---
date: "2026-07-17"
time: "16:20"
slug: regrade-qualidade-drift-us137-c3-tautologia
tldr: Re-grade pontuada de qualidade-drift-ia-producao + "vai faz". US-137 (eval online 5%) construída atrás de flag OFF, prova LGPD (PII antes do juiz). Chip C3 NÃO feito como pedido — provei que o drift-sentinel é TAUTOLÓGICO (gt-vs-gt=1.0); regravar seria pior, então tornei honesto + guard + US-143 (deprecação [W]). US-138 já estava done. Itens meus-de-construir completos; resta decisão [W] (135/143/ligar-137) e US-133 (subir o recall de verdade).
owners: [W]
prs: [4457, 4460]
us: [US-COPI-136, US-COPI-137, US-COPI-138, US-COPI-140, US-COPI-143, US-COPI-135, US-COPI-133]
related_adrs: [0318-ragas-eval-real-mata-tautologia-ct100-staging, 0093-multi-tenant-isolation-tier-0, 0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio]
---

# Re-grade qualidade-drift-ia-producao + US-137 + C3 (tautologia do sentinel)

Continuação da sessão de melhoria da dimensão. Turn 1 (handoff [2026-07-17 13:30](2026-07-17-1330-piso-recall-schedule-fantasma.md)) entregou US-136 (piso) + US-140 (invocador). Este turn: re-grade pontuada + "vai faz" dos 2 itens de maior alavanca.

## A re-grade (pontuada, medida contra main+runtime)

O 4,0/10 original era **otimista** — creditava um eval offline que (turn 1 provou) nunca rodava. Corrigido, o estado real era ~2,0. Eixos:

| Eixo | Peso | Antes(real) | Agora | Nota |
|---|---|---|---|---|
| A piso anti-regressão | 20% | 3 | **8** | US-136 na main, mordendo (11:27 recall 0→fail) |
| B eval offline roda sozinho | 15% | 1 | **6** | US-140 cron instalado, prova domingo |
| C eval online tráfego real | 25% | 0 | **~4** | US-137 mecanismo pronto+testado, flag OFF; ~6 quando [W] ligar |
| D alarme de drift | 25% | ~~3~~ | **honesto** | provado ~1/10 tautológico; parou de mentir |
| E observabilidade | 15% | 3 | **~5** | US-138 heartbeat done (sessão paralela) |

## O que foi feito ("vai faz")

### PR #4460 — US-137 eval online 5% (MERGED, flag OFF)
Mecanismo: amostra determinística por traceId (`shouldSample` pura) na `LangfuseAgentTelemetryListener::onEnd` → Job async (`JudgeTraceOnlineJob`) → **PiiRedactor ANTES do juiz** → `LangfuseClient::recordScore`. **2 gates OFF (LGPD Tier 0):** `enabled=false` + `judge=local` (zero egress; local não-implementado → job SKIPa). Ligar = `enabled=true` + `judge=openai` (aceite LGPD [W]). Só faithfulness (sem gt no tráfego real). **6 testes verdes CT 100** incl. prova LGPD (CPF/email não chegam crus ao juiz). Config hardcoded (não env) — regra larastan noEnvCallsOutsideOfConfig, padrão do próprio arquivo (ui_judge). status `doing`/`_pendente_` (DoD = eval no tráfego real, depende de [W] ligar).

### PR #4457 — chip C3 NÃO feito como pedido; sentinel honesto (MERGED)
O chip pedia "regravar o baseline real do drift-sentinel". **Rodei o sentinel real no CT 100: as 51 perguntas dão Current=1.0.** O código chama `scoreFaithfulness(q, gt, gt)` — answer=context=ground_truth = **tautologia** (mesma que a ADR 0318 matou no ci-eval, sobrevivente porque a 0318 só tocou aquele). Regravar setaria baseline=1.0 → Δ=0 pra sempre → **pior**. Então: docblock honesto + `caveat` no report + **guard no --update-baseline** (fecha a armadilha) + bite-test + §5 + **US-143** (deprecação formal, decisão [W]). Correção da grade: eixo D não é "baseline cego 3/10", é ~1/10 **tautológico**.

### US-138 (heartbeat) — já estava DONE
Sessão paralela construiu (`commit e7f6090`, status done): check `langfuse_trace_uptime_24h` no HealthCheckCommand lendo `meta.totalItems` da API do Langfuse (fonte real, não flag), registrado (`checkLangfuseTraceUptime24h`), 9 testes verdes. Eixo E subiu sem eu tocar.

## Aberto pro próximo (nada é meu-de-construir sem decisão)

1. **Decisão [W] — US-143:** aposentar o sentinel tautológico (recomendação A: sinal real = ragas-real-eval + piso). Não-required, deprecação precisa de ADR.
2. **Decisão [W] — ligar US-137:** escolher o juiz (`local` zero-egress a construir, ou `openai` com aceite LGPD). Sem isso o eval online não flui.
3. **Decisão [W] — US-135:** modelo frontier (conta de fornecedor, não engenharia).
4. **DoD domingo 19/07:** confirmar que a órfã `ragas-real-trend` ganhou semana nova sem run manual (fecha US-140).
5. **US-133 (o projeto grande):** subir `context_recall` 0,3839→≥0,60 de verdade (descongelar hybrid, ADR 0334). É o único item que MELHORA a qualidade (o piso só trava a queda). Multi-hora, merece sessão focada + confirmação de escopo.

## Lições da sessão (CI fez o trabalho)

A US-137 custou **4 rodadas de CI**, todas reais: PHPStan (env fora de config de módulo fura o baseline larastan) · PII scan (CPF sintético de teste — resolvido com `// pii-allowlist`) · 2× **base-fantasma** (PR #4456 normalizou 140 ADRs na main depois do meu fork → append-only via diff-aware acusava minha branch de "reverter" ADRs; fix = merge da main na branch, NÃO force-push que o hook bloqueia). E este próprio handoff bateu 2× (base-fantasma + `tldr>500`). Nenhuma foi teatro; o de base-fantasma exigiu diagnosticar a causa antes de rebasear às cegas.

## Estado MCP no momento do fechamento

- `cycles-active`: **nenhum cycle ativo** (brief #372).
- `my-work` (@wagner): 30+ tasks (triage órfãs, Produto V0/G-06, Financeiro 4d.6.2, PaymentGateway linkage). As US-COPI-13x vivem no SPEC (git).
- Decisões 24h (brief): ADRs 0340/0341/0342 (tema-colapso, memory-schema required, adr-slug legacy).
