---
page: /ia/pro
component: resources/js/Pages/Jana/Pro.tsx
owner: wagner
status: live
last_validated: "2026-06-01"
parent_module: Jana
related_adrs: [140, 110, 190, 93]
tier: B
charter_version: 1
---

# Page Charter — /ia/pro

> **Status:** novo. Tradução F3 (Cowork → Inertia/React) do design aprovado
> `Jana Pro - Paywall CC.html` (gate F1.5 **PASS 90** — ver
> `prototipos/jana-pro/critique-score.json` + `COMPARISON.md` no bundle Cowork).
> Charter criado junto com a tela pra fixar escopo e evitar virar Christmas tree.

---

## Mission

Converter o usuário do plano **Grátis** pro **Jana Pro** numa única tela de
decisão (estilo checkout Stripe): mostrar o valor com **prova ao vivo** (a Jana
lendo dados reais do ERP), comparar Grátis vs Pro, preço honesto + sinais de
confiança, e uma só ação primária — **Ativar Jana Pro**. Persona-alvo: Larissa
(ROTA LIVRE, biz=4, 1280px), decisão rápida.

---

## Goals — Features (faz)

- Shell `AppShellV2` (sidebar dark Cockpit V2) — herança de fundações/shell.
- **Modo FOCO** (sem `JanaSubNav` de ghosts): página de decisão, não de navegação
  — análoga a Edit/Create do `pageheader-canon`. Header só: breadcrumb
  `Jana · Plano` + título `Jana Pro` (tag `UPGRADE`) + `Voltar ao chat`.
- **Hero** 2 colunas: pitch ("Ela conhece o seu negócio. O Pro tira as amarras.")
  + **card de prova** dark com bolhas de chat e 3 ângulos de faturamento
  (Bruto/Líquido/Caixa) — diferencial não-replicável "ERP nativo" (BRIEFING §4.1).
- **Comparação Grátis vs Pro** (6 linhas, lidera com vitórias Pro: Brief 06h /
  Análises / Cockpit Saúde; "chat dados reais" fecha como base dos dois planos).
- **Preço honesto**: R$ [redacted Tier 0]/mês posicionado vs Numia R$ [redacted Tier 0] / Copilot R$ [redacted Tier 0]
- **Confiança**: isolamento por empresa (Tier 0), LGPD por padrão, hospedado no BR.
- Footer sticky com resumo + ação secundária "Falar com a Jana" + CTA primária.
- CTA `Ativar`: estado `idle → Ativando… → Pro ativo · 14 dias grátis` (fecha o
  loop de feedback, dim. 5 Estados).
- Atalhos teclado (Larissa = teclado): `⌘/Ctrl+Enter` ativa · `Esc` volta ao chat.
- Tokens canon: `bg-primary` roxo `oklch(0.55 0.15 295)` (ADR 0190), `text-success`
  pra "incluído/positivo", zero `blue-*`/emoji, `rounded-md/lg`, `shadow-sm`.

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item viraria GUARD se houvesse Pest desta tela.

- ❌ Billing real (assinatura Asaas, gateway, cobrança) — é **Sprint JANA-B**
  (ADR 0140, US-COPI-211/212). A CTA hoje é mock client-side fiel ao protótipo.
- ❌ Downgrade/gestão de assinatura (cancelar, trocar cartão) — backlog Sprint B.
- ❌ WhatsApp como canal de contato ("Falar com a Jana" → `/ia`, nunca WA — proibição).
- ❌ Modal full-screen, emoji, cor crua de status (`text-emerald/rose`) — usa tokens.
- ❌ Comparar mais de 2 planos (Enterprise R$ [redacted Tier 0] entra só em GA — Sprint JANA-C).
- ❌ Escrever no banco no render (tela de leitura + 1 ação; sem efeito colateral).
- ❌ Mostrar dados de outro `business_id` (Tier 0 — businessId sempre da sessão).

---

## UX Targets

- Cabe em 1280px (Larissa) com a comparação + preço + confiança **sem rolar muito**.
- 1 única ação primária roxa (footer); secundárias nunca competem em cor.
- p95 first-paint < 800ms (render leve — props pequenos, sem query pesada).
- 0 erros JS console · 0 erros TS/ESLint (`text-success` evita lint R1).
- Foco visível (`:focus-visible` outline) em todo interativo; CTA tabável.
- Card de prova legível a ~1m (números mono grandes, `tabular-nums`).

---

## Automation Hooks

- `ProController::index()` (rota `jana.pro.index`, grupo `/ia` auth) renderiza
  `Jana/Pro` com `plan`, `pricing`, `proof`, `business` (businessId da sessão).
- `proof` hoje = valores representativos; **Onda B** liga em
  `BriefDiarioService::snapshot()` (faturamento real do mês corrente).

## Automation Anti-hooks

- ❌ Não dispara email/SMS/WhatsApp ao abrir nem ao clicar (CTA é mock até Sprint B).
- ❌ Não chama LLM/Brain B no render.
- ❌ Não persiste nada no client além do estado efêmero da CTA (sem localStorage).
- ❌ Não cobra nem cria assinatura (billing é Sprint JANA-B, gated por Wagner).

---

## Refs

- Design Cowork aprovado: `Jana Pro - Paywall CC.html` + `prototipos/jana-pro/`
  (`COMPARISON.md` 15 dimensões, `critique-score.json` gate 90).
- [ADR 0140](../../../memory/decisions/0140-jana-pro-produto-comercial-saas.md) — Jana Pro SaaS / pricing / roadmap (Sprint A-D).
- [ADR 0110](../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md) — Cockpit V2.
- [ADR 0190](../../../memory/decisions/0190-primary-button-roxo-universal-295.md) — primary roxo universal.
- [ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0.

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-06-01 | [CL] (Claude Code) | Tela criada — F3 do design `Jana Pro - Paywall CC` (gate Cowork PASS 90). Controller + rota + página + charter. Billing real fica pra Sprint JANA-B. |
