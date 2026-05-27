---
page: /whatsapp/settings
component: resources/js/Pages/Whatsapp/Settings.tsx
owner: wagner
status: live
last_validated: 2026-05-27
parent_module: Whatsapp
parent_adr: memory/decisions/0202-whatsapp-profissionalizacao-baileys-out.md
related_adrs: [0093, 0094, 0096, 0117, 0135, 0202]
tier: A
charter_version: 1
---

# Page Charter — `/whatsapp/settings`

> Define invariantes da página de conexão WhatsApp Business via Meta Cloud
> Embedded Signup v4. Mudanças que violem este charter exigem PR + bump
> `charter_version`.

## Mission

Permitir admin de business **conectar/reconectar** número WhatsApp Business via
Meta Cloud em 5-15 min usando popup OAuth oficial Facebook for Business v4
(Embedded Signup). Substitui o redirect 301 legacy de US-WA-070 — templates HSM
continuam na tela separada `/atendimento/canais/jana-templates`.

## Goals

- Mostrar estado atual da conexão Meta Cloud do business (driver + display_phone + waba_id + driver_health)
- Botão único "Conectar com Meta" abre popup OAuth Facebook em < 1 click
- Capturar `code` OAuth via `postMessage` validando `event.origin` (defesa XSS)
- Postar `{code, state}` ao backend que troca por access_token + auto-subscribe webhook
- Reconectar quando `driver_health` ∈ {degraded, disconnected, banned}
- Empty state honesto se `META_APP_ID` ausente no servidor (instrui Wagner ler runbook)

## Non-Goals

- ❌ NÃO suporta criação de WABA do zero — usuário deve ter Meta Business Manager + WABA criado
- ❌ NÃO gerencia templates HSM — vai pra `/atendimento/canais/jana-templates`
- ❌ NÃO cadastra Z-API/Baileys — Baileys foi descontinuado (ADR 0202); Z-API via `/atendimento/canais`
- ❌ NÃO mostra mensagens / conversas — responsabilidade da Inbox/Caixa Unificada
- ❌ NÃO expõe `meta_access_token` em props nem em DOM — encrypted cast no backend, JAMAIS pro frontend
- ❌ NÃO loga telefone completo — backend redacta pra 5 primeiros chars (`+5548...`)

## UX targets

- Tempo do click "Conectar" até config persistida: **< 15 segundos** (4 chamadas Graph + UI feedback)
- Estado conectado em < 500ms via Inertia `router.reload({ only: ['currentConfig'] })`
- Empty state honesto ("Meta App não configurado") apontando runbook canônico
- Popup OAuth 600x750 (padrão Facebook for Business)
- Cor botão `#1877F2` (Facebook blue oficial — reconhecimento visual instantâneo)
- Texto microcopy não-técnico (persona Larissa biz=4 vestuário)

## Automation hooks

- Audit log estruturado `Log::info('whatsapp.embedded_signup.success')` com PII redacted
- OTel span `whatsapp.meta_cloud.embedded_signup` (4 sub-spans Graph)
- Centrifugo publish `driver.healthy.{business_uuid}` (futuro — não implementado nesta Fase 2)
- CSRF state stored em `session('whatsapp_oauth_state')` + `pull` no callback (1-shot)

## Anti-hooks (não fazer)

- ⛔ Polling client-side `setInterval` direto pra status — usar Inertia reload pós-success
- ⛔ Mutar config alheio (`business_id != session('user.business_id')`) — Tier 0 IRREVOGÁVEL (ADR 0093)
- ⛔ Hardcode META_APP_ID no frontend — vem via Inertia props da Controller
- ⛔ Expor access_token em props/state/DOM — cifrado em DB, JAMAIS sai pro frontend
- ⛔ Aceitar `postMessage` de origin ≠ `https://www.facebook.com` (XSS)
- ⛔ Reutilizar `state` CSRF — Controller faz `session->pull()` (1-shot, anti-replay)
- ⛔ Log telefone completo — sempre redacted (primeiros 5 chars apenas)

## Multi-tenant Tier 0 (ADR 0093)

- `WhatsappBusinessConfig::firstOrNew(['business_id' => $businessId])` com `business_id` explícito
- `HasBusinessScope` trait aplica global scope (defesa em profundidade)
- Audit log inclui `business_id` em toda entrada
- Tests cobrem isolamento cross-tenant (business A nunca enxerga config B)

## Custo IA / latência

- **Zero custo IA** — apenas chamadas Graph API Meta (free quota oficial)
- Latência típica conexão completa: 2-4s (4 roundtrips Graph paralelos seriam menos, mas Meta exige order)
- Centrifugo publish futuro custa < 1ms (não afeta latência percebida)
