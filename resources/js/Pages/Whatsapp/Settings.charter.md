---
page: /whatsapp/settings
component: resources/js/Pages/Whatsapp/Settings.tsx
owner: wagner
status: live
last_validated: "2026-05-27"
parent_module: Whatsapp
parent_adr: memory/decisions/0202-whatsapp-profissionalizacao-baileys-out.md
related_adrs: [93, 94, 96, 117, 135, 202]
related_adrs_ui: [UI-0013]
tier: A
charter_version: 2
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
- Cor botão Facebook blue oficial via token semântico `--color-brand-meta` (`hsl(215 89% 53%)`) — reconhecimento visual instantâneo, cross-theme constant
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

## Tokens semânticos (ADR UI-0013 · Constituição UI v2)

### Charter version 2 (2026-05-27 · pós-#1768 cleanup)

A v1 do Settings.tsx nasceu com cores Tailwind hardcoded (`bg-green-50`,
`bg-yellow-50`, `bg-red-50`, hex literal `#1877F2`) gerando 18 violações
R1 do `ui:lint`. Wagner aceitou tech debt como condição pra mergear
ADR 0202 Fase 2 (PR consolidado), com refator pra tokens semânticos
agendado pro PR seguinte ("pode arrumar os debitos eu aceito" 2026-05-27).

### Tokens aplicados (após cleanup)

| Antes (hardcoded) | Depois (token semântico) | Token CSS canon |
|---|---|---|
| `bg-green-50/100` (Conectado healthy) | `bg-success/10` `bg-success/20` | `--color-success` em `inertia.css` |
| `border-green-200` | `border-success/40` | derivado de `--color-success` |
| `text-green-600` (ícone CheckCircle) | `text-success` | `--color-success` |
| `text-green-700/800/900` (textos card) | `text-success-foreground` + variants `/90/80` | `--color-success-foreground` |
| `bg-yellow-50` (warning Meta App) | `bg-warning/10` | `--color-warning` |
| `border-yellow-300` | `border-warning/40` | derivado |
| `text-yellow-700` (ícone Alert) | `text-warning` | `--color-warning` |
| `text-yellow-900` (textos warning) | `text-warning-foreground` | `--color-warning-foreground` |
| `bg-red-50` (errorMessage card) | `bg-destructive/10` | `--color-destructive` (já existia shadcn) |
| `border-red-300` | `border-destructive/40` | shadcn |
| `text-red-900` | `text-destructive` | shadcn |
| `bg-[#1877F2] hover:bg-[#166FE5]` (botão Meta) | `bg-brand-meta hover:bg-brand-meta-hover` | `--color-brand-meta` (novo, cross-theme constant) |

### Anti-pattern (proibido daqui pra frente)

- ⛔ Cores Tailwind nomeadas (`bg-green-50`, `text-yellow-700`, etc) — quebram dark mode + violam UI lint R1
- ⛔ Hex literais (`#1877F2`, `#FF0000`, etc) — exceto puros `#fff`/`#000`
- ⛔ Inline styles com cor (`style={{ color: 'red' }}`) — invisível ao UI lint, igualmente proibido

### Atalhos canônicos (Constituição UI v2 · sliver pra PR UI Judge)

- **Cmd+K / Ctrl+K** — abre Command Palette global (já provido por `AppShellV2` via `CommandPalette` component — PMG-002, ADR 0100). Settings.tsx herda automático, **sem duplicar**.
- **Esc** — fecha popup OAuth Meta se aberto (handler local `useEffect` listener `keydown`). Limpa estado `connecting` + seta `errorMessage` honesto.
- **Enter** no botão "Conectar com Meta" focused — dispara `onClick` (default HTML5, sem JS extra).
- **j/k navigation** — N/A nesta tela (form simples sem lista navegável). Aplicável em `/whatsapp/conversations` (PT-01 Lista).
