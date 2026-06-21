---
slug: 0289-failover-saude-canal-cloud-api-tenants-criticos
number: 289
title: "failover automático por saúde de canal: tenant crítico cai pro Cloud API (oficial, ban-zero) quando o canal não-oficial cai — emenda 0096"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-18"
module: whatsapp
tags: [whatsapp, resilience, failover, meta-cloud-api, channel-health, tier-0]
supersedes: []
superseded_by: []
related:
  - 0096-modulo-whatsapp-meta-cloud-api-direto
  - 0288-slo-sli-saude-canal-whatsapp
---

# ADR 0289 — failover automático saúde→Cloud API pra tenants críticos

## Contexto

A [ADR 0096](0096-modulo-whatsapp-meta-cloud-api-direto.md) põe a Meta Cloud API como default universal + não-oficial (whatsmeow) como opção por custo/flexibilidade; o `MetaCloudDriver` já existe (705 linhas). MAS **não há failover automático por saúde**: se um canal não-oficial cai (logout/ban), o envio do tenant **não** migra sozinho pro Cloud API. O dossiê (2026-06-18) marca hedge oficial em **60%** (driver existe, failover não automatiza). Pra um tenant **crítico**, isso é perda de mensageria numa queda.

## Decisão

Pra canais/tenants marcados **críticos**, quando `channel_health` estiver caído além do limiar (sinal do [ADR 0288](0288-slo-sli-saude-canal-whatsapp.md)), o **envio outbound** faz **failover automático** pro canal Cloud API (oficial, único ban-zero) do **mesmo business**, se configurado:

- **Escopo:** outbound (envio). Inbound segue por-canal (não há o que rotear se a sessão caiu).
- **Gatilho:** `channel_health` caído > limiar (0288) **E** flag `critical` no canal/tenant **E** existe canal Cloud API ativo no business.
- **Reversível + idempotente:** quando o não-oficial recupera (`healthy`), o roteamento volta. **Histerese** (só volta após X min healthy) pra evitar flap.
- **Transparência:** registra no `AuditLog` + sinaliza na UI qual canal está servindo (não-oficial vs failover oficial).
- **Tier 0:** failover **nunca** cruza business ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)) — só usa o Cloud API do mesmo tenant.

## Consequências

- ✅ Tenant crítico **não perde envio** quando o canal não-oficial cai/bane — o caminho ban-zero assume.
- ✅ Materializa o modelo "não-oficial por custo + oficial por criticidade" (0096) de forma **automática**, não manual.
- ⚠️ Custo: Cloud API cobra por conversa → failover só pra `critical` (não universal), pra não estourar custo.
- ⚠️ Depende do sinal de saúde do [ADR 0288](0288-slo-sli-saude-canal-whatsapp.md) (sequência: 0288 → 0289).
- 📝 Inbound durante o failover + reconciliação de histórico pós-recuperação ficam fora de escopo deste ADR.

## Anchor

**Implementado em:** (pendente — segue aceite + ADR 0288) seletor de driver de outbound por saúde (`Modules/Whatsapp/Services/`), flag `critical` em `channels`/`business`.
