---
slug: 0288-slo-sli-saude-canal-whatsapp
number: 288
title: "SLO/SLI de saúde de canal WhatsApp — uptime%, time-to-detect e alerta canal-down (observabilidade que não depende de olhar a tela)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-18"
module: whatsapp
tags: [whatsapp, observability, slo, sli, channel-health, alerting]
supersedes: []
superseded_by: []
related:
  - 0286-channel-health-corroborado-por-mensagem-real
  - 0058-reverb-substituido-por-centrifugo-frankenphp
---

# ADR 0288 — SLO/SLI de saúde de canal WhatsApp

## Contexto

O dossiê de profissionalização (2026-06-18, [channel-reliability](../sessions/2026-06-18-arte-whatsapp-channel-reliability.md)) marcou **observabilidade em 20%** — o pilar mais atrás. Hoje o app **detecta** queda (probe 3min + `LoggedOut` nativo + corroboração por inbound do [ADR 0286](0286-channel-health-corroborado-por-mensagem-real.md)) e agora **reflete na tela** (realtime, Centrifugo). MAS não **mede** nem **alerta**: não há uptime% por canal, time-to-detect, nem alerta de canal-down. Resultado: um canal pode ficar caído e ninguém é avisado fora de quem está olhando a Caixa.

## Decisão

Definir SLIs + SLOs de disponibilidade de canal e um alerta — todos **por canal, por business** (Tier 0, [ADR 0093](0093-multi-tenant-isolation-tier-0.md)).

**SLIs:**
1. **uptime%** = tempo em `channel_health=healthy` / tempo total (janela rolante 24h/7d/30d).
2. **time-to-detect** = intervalo entre a queda real e o flip de `channel_health` (com `LoggedOut` nativo deve ser **segundos**; antes era até 3min+10min).
3. **webhook delivery success rate** = entregas 2xx / total.

**SLOs (alvo — [W] calibra):** uptime ≥ **99%**/30d · time-to-detect **p95 < 1 min** · webhook delivery ≥ **99,5%**.

**Alerta:** canal `status=active` com `channel_health` caído (`disconnected`/`banned`/`logged_out`) por **> N min** (default **10**, via env) → **notifica** (canal de alerta do projeto: `mcp_alertas` / log `ALERT` / Centrifugo), não só banner.

**Onde:** snapshot periódico (padrão `MetricsAggregateCommand` + `WhatsappObservabilityHealthCommand`) gravando série temporal de `channel_health` (tabela append-only) pra computar uptime%/time-to-detect; OTel span no ciclo de sessão (estende o OTel GenAI do Jana).

## Consequências

- ✅ Saber a confiabilidade real de cada canal **sem depender de alguém olhar a tela**.
- ✅ Base pro dashboard de saúde de canal **e** pro gatilho de failover ([ADR 0289](0289-failover-saude-canal-cloud-api-tenants-criticos.md)).
- ⚠️ Requer tabela de snapshot (append-only) + cron — **implementação após o aceite** (sequência: aceitar SLI/SLO aqui → codar a métrica).
- 📝 `time-to-detect` só é honesto depois do `LoggedOut` nativo (#2994) + probe corrigido (#3003 / ADR 0287).

## Implementação

**Fase 1 (PR #3005):** `ChannelHealthSnapshotCommand` (`whatsapp:channel-health-snapshot`, cron 5min) grava série append-only em `channel_health_snapshots` e **alerta** canal `active` caído por > N min (`whatsapp.whatsmeow.health_alert_after_minutes`, default 10), **1×/streak** (decisão pura `shouldAlert()`). Limite: o alerta saía **só** como `Log::error` no `laravel.log` do Hostinger — não chegava em ninguém nem era verificável sem SSH.

**Fase 2 (este PR):** no **mesmo ponto** do alerta (sem duplicar a dedup `shouldAlert`), além do Log, dois sinks best-effort:
1. **Centrifugo** — publica em `whatsapp:business:{business_id}` com `event: whatsmeow.channel_alert` + `{channel_id, channel_health, down_minutes, threshold_minutes}` (espelha `WhatsmeowWebhookController::publish`). Surfacea na Caixa em realtime.
2. **`mcp_alertas_eventos`** — a notificação disparada que **chega no humano** (distinta de `mcp_alertas`, que é só a regra/config). Reusa o store + padrão de insert idempotente de `DetectDriftCommand`/`WebhookCanaryCommand` — guard de schema, `chave_idempotencia` ancorada na origem da streak, `tipo=whatsapp_channel_down`, `severidade=high`, `business_id` real (Tier 0), sem PII. **Zero store novo** (ADR 0270).

Efeito: o alerta deixa de "viver só no log do Hostinger" → avisa de verdade + **smoke verificável sem SSH**. Frontend que consome `whatsmeow.channel_alert` na UI fica como follow-up opcional (a Caixa já reage à saúde de canal via #3002).

## Anchor

**Implementado em:** `Modules/Whatsapp/Console/Commands/ChannelHealthSnapshotCommand.php` (snapshot + alerta 3-sinks: Log + Centrifugo + `mcp_alertas_eventos`), tabela `channel_health_snapshots`. Fase 1 PR #3005; Fase 2 publica Centrifugo + grava `mcp_alertas_eventos`.
