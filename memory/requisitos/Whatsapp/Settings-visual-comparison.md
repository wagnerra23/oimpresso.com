---
id: requisitos-whatsapp-settings-visual-comparison
---

# Visual comparison F1.5 · `/whatsapp/settings` (US-WA-310)

> Skill `mwart-comparative` V4 — Wagner aprova **SCREENSHOT** (não tabela)
> antes do merge. Esta análise serve como base pra screenshot final.
> Criada 2026-05-27 durante implementação Fase 2 ADR 0202.

## Contexto

US-WA-310 substitui o redirect 301 legacy de US-WA-070 por wizard "Conectar
com Meta" via Embedded Signup v4. É a primeira tela do oimpresso a usar
OAuth popup proprietário (Facebook for Business) — referências fora do
ecossistema Laravel-Inertia são abundantes.

## Benchmarks 2026 pesquisados

### 1. Stripe Dashboard — "Connect new account" (Connect API)

- **Layout:** card central centrado, max-width ~600px
- **Hero:** título "Connect your Stripe account" + descrição 2-3 linhas
- **CTA:** botão único violet `#635BFF` (Stripe brand) com ícone external-link
- **Estados:** loading skeleton + success com check verde + error com retry inline
- **Microcopy:** "It takes about 5 minutes. We'll never see your account password."
- **Trust signals:** logo Stripe oficial + "Secured by OAuth 2.0"

### 2. Linear — "Connect GitHub" (Integrations settings)

- **Layout:** card branco shadow-sm, padding generoso (p-6+)
- **Hero:** ícone GitHub 32x32 + título "Connect GitHub" + sub-descrição
- **CTA:** botão dark `bg-gray-900 text-white` (cor da plataforma)
- **Estado conectado:** ícone check-circle verde + "Connected as @username" + botão "Disconnect" right-aligned
- **Microcopy:** "Sync issues, PRs and commits automatically"
- **Anti-pattern observado:** Linear NÃO mostra token/credenciais — só metadata

### 3. Notion — "Add new integration" (Slack OAuth)

- **Layout:** modal centralizado quando ação ativa; card listagem quando lista
- **Estado idle:** badge "Available" cinza + botão "Connect"
- **Popup:** abre janela 600x800 padrão OAuth
- **Pós-conexão:** badge "Connected" verde + workspace name + scope summary
- **Microcopy:** "Notion will be able to: Read messages, Post messages"

### 4. Mailchimp — "Connect WhatsApp Business" (mais próximo do caso)

- **Layout:** wizard 2-step: (1) escolha de WABA dropdown · (2) phone selection
- **CTA Step 1:** botão "Continue with Meta" cor `#1877F2` (Facebook blue) — referência canônica
- **Pós-success:** card com phone display + business name + WABA id + ícone Meta
- **Microcopy:** "We'll use your Meta Business account to send WhatsApp messages"
- **Trust signals:** logo "Powered by Meta Cloud API"

### 5. Chatwoot Cloud — "Add WhatsApp Channel" (OSS reference)

- **Layout:** sidebar form 60% + preview card 40%
- **Estado:** form fields manuais (Phone Number ID, Access Token, Business Account ID)
- **CTA:** "Save channel" cinza padrão
- **Limitação:** Chatwoot OSS NÃO usa Embedded Signup (form manual). Oimpresso vai além.

### 6. Zapier — "Connect WhatsApp" (Embedded Signup nativo Meta)

- **Layout:** modal 720x600 com loading spinner durante popup OAuth
- **CTA:** botão "Connect with Meta" cor `#1877F2`
- **Pós-success:** display phone + "Connected to Zapier WhatsApp" + 3 microactions (Test, Disconnect, Settings)
- **Microcopy:** "We'll never spam your contacts — connection is read-only by default"

### 7. n8n self-hosted — "Add WhatsApp Cloud credential"

- **Layout:** form manual estilo Chatwoot — Phone Number ID + Access Token + WABA ID
- **NÃO usa Embedded Signup** — caminho self-host (ok pra dev, fricção pra biz user)

## 15 dimensões mwart-comparative V4

| # | Dimensão | Stripe | Linear | Notion | Mailchimp | Chatwoot | Zapier | Oimpresso v1 (proposto) |
|---|---|---|---|---|---|---|---|---|
| 1 | **Clareza de intenção** | ★★★★★ | ★★★★★ | ★★★★☆ | ★★★★★ | ★★★☆☆ | ★★★★★ | ★★★★★ |
| 2 | **Trust signals (security/oficial)** | ★★★★★ | ★★★★☆ | ★★★★☆ | ★★★★★ | ★★☆☆☆ | ★★★★★ | ★★★★☆ (Badge Meta + microcopy) |
| 3 | **Microcopy não-técnico** | ★★★★★ | ★★★★☆ | ★★★★★ | ★★★★★ | ★★☆☆☆ | ★★★★★ | ★★★★★ (Larissa-friendly) |
| 4 | **Quantidade de cliques pra success** | 1 | 1 | 1 | 2 | 5+ (form) | 1 | **1** |
| 5 | **Estado conectado visível** | ✓ | ✓ | ✓ | ✓ | parcial | ✓ | ✓ (card verde + check + WABA) |
| 6 | **Loading feedback** | spinner | ✓ | ✓ | ✓ | botão grey | ✓ | ✓ (`<Loader2 animate-spin />`) |
| 7 | **Erro feedback** | inline | inline | toast | inline | inline | inline | inline (red card) |
| 8 | **Acessibilidade (a11y)** | AA | AA+ | AA | AA | A | AA | AA (lucide icons + aria) |
| 9 | **Mobile responsive** | ✓ | ✓ | ✓ | ✓ | parcial | ✓ | ✓ (`max-w-3xl mx-auto`) |
| 10 | **Tempo end-to-end** | ~3min | ~2min | ~3min | ~5-15min | ~10min (manual) | ~3min | ~5-15min |
| 11 | **Reconnect/refresh path** | ✓ | ✓ | ✓ | ✓ | manual | ✓ | ✓ (card yellow "Reconectar") |
| 12 | **Empty state se App não configurado** | n/a (multi-tenant SaaS) | n/a | n/a | n/a | erro genérico | n/a | ✓ (aponta runbook) |
| 13 | **Custo cognitivo (Hick's law)** | baixo | baixo | baixo | médio (2 steps) | alto (5 fields) | baixo | **baixo** (1 botão) |
| 14 | **Legacy escape hatch** | "Manual API key" hidden | n/a | n/a | n/a | n/a | n/a | ✓ (Z-API em `<details>`) |
| 15 | **Anti-patterns evitados** | n/a expõe token | n/a expõe token | n/a expõe token | n/a expõe token | **expõe** token | n/a expõe token | NÃO expõe token (encrypted cast) |

## Decisões de design oimpresso

1. **Cor CTA = `#1877F2`** (Facebook blue oficial) — alinhada com Mailchimp + Zapier, máximo reconhecimento visual instantâneo
2. **1 botão único** (não wizard 2-step Mailchimp) — Meta popup permite escolha de WABA dentro do próprio popup (não precisamos pre-step)
3. **Card verde pós-success** com WABA id + display_phone + última verificação — padrão Stripe/Linear
4. **Z-API escondido em `<details>` collapsed** — escape hatch sem competir com CTA principal (anti-pattern Chatwoot)
5. **Empty state apontando runbook** — único oimpresso entre benchmarks (multi-tenant self-host com config global Wagner)
6. **PII redaction logs** — único oimpresso entre benchmarks (LGPD-aware, ADR 0094)

## Trade-offs reconhecidos

- **Não temos dropdown multi-WABA** — pegamos `businesses[0]` automaticamente. 99% dos clientes têm 1 WABA. Caso edge (cliente enterprise com N WABAs) vira US separada — não bloqueia Fase 2.
- **Não temos preview phone E.164 pre-conexão** — Meta retorna `display_phone` apenas pós-success. Microcopy explicita "vamos abrir popup do Meta pra você autorizar".
- **Popup 600x750** segue padrão Facebook for Business — se cliente tem zoom alto pode cortar conteúdo. Aceito pra Fase 2 (Meta dimensiona internamente).

## Aprovação Wagner

- Screenshot final será gerado pós-merge em ambiente local (Wagner + Maiara revisam visual)
- Esta análise documenta benchmarks 2026 + 15 dimensões pra contextualizar a decisão
- Aprovação visual via `prototipo-ui/PROTOCOL.md` loop Cowork ↔ Claude Code (ADR 0114)

## Referências

- ADR 0202 — Meta Cloud default universal + Baileys OUT
- ADR UI-0013 — Constituição UI v2 (4 camadas)
- ADR 0114 — prototipo-ui Cowork loop formalizado
- ADR 0107 — emendation 0104 visual comparison gate F3
- Embedded Signup v4 docs: https://developers.facebook.com/docs/whatsapp/embedded-signup
