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

## Anchor

**Implementado em:** (pendente — segue o aceite desta proposta) `Modules/Whatsapp/Console/Commands/` (snapshot + alerta), tabela `channel_health_snapshots`, `Modules/Whatsapp/Console/Commands/WhatsappObservabilityHealthCommand.php`.
