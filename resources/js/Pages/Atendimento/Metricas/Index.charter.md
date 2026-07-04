---
page: /atendimento/metricas
component: resources/js/Pages/Atendimento/Metricas/Index.tsx
related_us: [US-WA-041]
owner: wagner
status: live
last_validated: "2026-05-16"
parent_module: Whatsapp
parent_adr: memory/decisions/0135-omnichannel-inbox-arquitetura.md
related_adrs: [52, 93, 94]
tier: B
charter_version: 1
---

# Page Charter — `/atendimento/metricas`

> Define invariantes do dashboard de métricas do Atendimento (custo HSM,
> deflection bot Jana, SLA, tempo resposta). Mudanças que violem este
> charter exigem PR + bump charter.

## Mission

Dashboard executivo do líder de atendimento — responde "quanto tô gastando
em WhatsApp?", "Jana tá resolvendo quanto sozinha?", "estou batendo SLA?".
Snapshot daily agregado (cron 02:30 BRT) + ad-hoc tempo real do dia atual.

## Goals

- KPI cards: msgs/dia · custo HSM/dia · deflection bot % · 1ª resposta médio
- Série temporal 30d (line chart) — volume + custo
- Breakdown por canal (Baileys / Z-API / Meta Cloud) — barras empilhadas
- Tabela top-10 conversas mais longas / mais caras
- Filtro período (7d / 30d / 90d / custom) sem reload (partial reload Inertia)

## Non-Goals

- ❌ NÃO mostra conteúdo de mensagens (apenas agregados — LGPD)
- ❌ NÃO permite drill-down direto pra conversa (botão "ver" sim, mas
  redireciona pra Inbox com filtro pré-aplicado)
- ❌ NÃO substitui dashboard CSAT (separado em `/atendimento/csat`)

## UX targets

- TTFB inicial ≤ 100ms (snapshot pre-agregado em `whatsapp_conversation_metricas`)
- Switch de período ≤ 200ms (partial reload `only:[...]`)
- Empty state se biz sem 7d histórico ("Aguardando dados — volte amanhã")
- Print-friendly (CSS `@media print` simples — gerente imprime pra reunião)

## Automation hooks

- Cron `whatsapp:metrics-aggregate` daily 02:30 BRT alimenta snapshot
- `MetricsAggregator` service consolida via SQL bruta (não Eloquent — perf)
- Alert Prometheus se `custo_hsm_24h > R$ [redacted Tier 0]` ou `deflection < 30%`

## Anti-hooks

- ⛔ Query real-time agregando `whatsapp_messages` cru (1M+ rows biz=1) — usar snapshot
- ⛔ Mostrar custo de biz alheio (multi-tenant Tier 0)
- ⛔ Hardcode preço HSM Meta (vem de `config('whatsapp.meta_pricing')`)
