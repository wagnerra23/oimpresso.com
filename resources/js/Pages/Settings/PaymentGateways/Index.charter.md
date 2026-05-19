---
page: /settings/payment-gateways
component: resources/js/Pages/Settings/PaymentGateways/Index.tsx
owner: wagner
status: live
last_validated: 2026-05-19
parent_module: PaymentGateway
related_adrs: [0093, 0094, 0104, 0144, 0170]
related_us: [US-PG-F3-SETTINGS]
related_prototype: prototipo Cowork "payment-gateway-ui" F1+F1.5 aprovado [W] 2026-05-19
related_decisions: COWORK_HANDOFF.paymentgateway-ui.md (F1 score 93/100)
tier: A
charter_version: 1
---

# Page Charter — `/settings/payment-gateways`

> **Status:** F3 PaymentGateway UI Tela 2 — Cowork F1+F1.5 aprovado [W] 2026-05-19 score 93/100. Persona: **Wagner** (superadmin / dono / config inicial).

---

## Mission (1 frase)

Wagner gerencia credenciais dos 5 drivers (Inter + C6 + Asaas + BCB Pix + PesaPal) — config inicial, ativar/desativar com confirmação Trust L3, health check real on-demand, link webhook por driver, rotação de mTLS pra credencial expirando, em uma view única estado-da-arte.

---

## Goals — Features (faz)

- **3 KPIs**: Credenciais ativas / Health check (% OK) / Cobranças hoje (deeplink pra `/financeiro/cobranca`)
- **Tabela de credenciais configuradas**: Apelido + DriverChip + Ambiente + Conta destino + Health badge + Toggle + Ações row hover
- **Drivers disponíveis** (não configurados ainda): cards laterais clicáveis abrem `SheetNovoGateway`
- **Alerta mTLS vencendo** ≤30d (warn amber)
- **DrawerGateway 4 tabs**:
  - Identificação (apelido + driver + ambiente + conta destino)
  - Credenciais (campos dinâmicos por driver: Inter mTLS + cert / C6 ag+conta+código / Asaas api_key / BCB CNPJ+mTLS + senha / PesaPal deprecated alert)
  - Webhook (URL pública + HMAC secret + eventos 24h)
  - Health (últ check + latência + histórico 7d sparkline + botão "Rodar agora")
- **SheetNovoGateway wizard 3 steps**: Driver → Credenciais → Vínculo (FK account)
- **ConfirmToggleModal (Trust L3)**: ao desativar, mostra N cobranças em aberto afetadas + log audit; ao ativar, info imediato
- **Health check on-demand**: botão "Rodar agora" + "Testar todos"
- **CheatSheet** overlay `?` — atalhos teclado (N nova, J/K nav, Enter abre)
- **KB-9.75 atalhos**: `N` nova, `J/K/↓↑` nav rows, `Enter` abre drawer, `Esc` fecha, `?` cheat
- **Multi-tenant Tier 0** ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD.

- ❌ Editar credencial sem reentrada de senha mTLS (segurança — backlog edit flow)
- ❌ Download de cert mTLS .crt/.key da UI (segurança — só upload)
- ❌ Renovação automática de mTLS (manual via Renovar mTLS botão)
- ❌ Migração PesaPal → Asaas automática (link manual "Iniciar migração")
- ❌ Webhook signature rotation sem rotação no painel do gateway (UI mostra mas Wagner faz manual no Inter/Asaas)
- ❌ Estatísticas históricas (>7d) — backlog Onda 5
- ❌ Multi-businesses bulk activate (1 biz por vez)
- ❌ Mobile responsive — desktop only (Persona Wagner)

---

## UX Targets

- p95 first-paint < 500ms (Inertia::defer em gateways/kpis/accounts)
- Cabe em monitor 1280px sem scroll horizontal
- Tabular nums + chip composto driver
- Trust L3 modal de toggle (zero ação destrutiva sem confirm)
- WCAG 2.1 AA: ESC + focus trap + scroll lock + aria-labels

---

## UX Anti-patterns

- ❌ Toggle ativo sem confirm modal (Trust L3 fail-fast)
- ❌ Driver chip emoji ou ícone customizado (canon = quadrado colorido sigla)
- ❌ Health badge sem ícone status
- ❌ Modal centralizado em vez de drawer lateral 640px

---

## Automation Hooks

- `PaymentGatewaysController::index(Request): Response` — defer gateways/accounts/kpis
- `PaymentGatewaysController::healthCheck(Request, ?int): JsonResponse` — endpoint async fetch
- `PaymentGatewaysController::toggle(Request, int): JsonResponse` — flip ativo
- Multi-tenant: `HasBusinessScope` trait em `PaymentGatewayCredential`
- Permission canon: `paymentgateway.credenciais.*` (granular em backlog, hoje `auth` global)

---

## Automation Anti-hooks

> O que essa tela NUNCA dispara. Vira Pest GUARD.

- ❌ Não exibe `config_json` decifrado no Inertia payload (sempre cifrado/mascarado)
- ❌ Não acessa `PaymentGatewayCredential` de outro `business_id` (Tier 0 ADR 0093)
- ❌ Não loga PII em plain text (`LogsActivity` configurado no Model logOnly campos não-sensíveis)
- ❌ Não dispara cobrança ao abrir (read-only no GET — emissão é em `/financeiro/cobranca`)
- ❌ Não cria credencial no GET (só POST via SheetNovoGateway)

---

## Métricas vivas (Pest GUARDs)

```php
it('renderiza Inertia component Settings/PaymentGateways/Index')
it('expõe Props no shape esperado (gateways, accounts, kpis, today)')
it('expõe 3 KPIs (ativos, fail, cobs_hoje)')
it('lista gateways do business + warn pra drivers deprecated')
it('health-check endpoint atualiza health_status no DB')
it('toggle endpoint inverte ativo do credential')
it('Tier 0 IRREVOGÁVEL: PaymentGatewayCredential respeita business_id global scope')
it('não dispara mutação em GET /settings/payment-gateways (read-only puro)')
```

---

## Comparáveis canônicos

- **Stripe Dashboard "Connect / Settings"** — drawer detalhe + 4 tabs canon
- **Asaas "Configurações > Integrações"** — driver chip + health badge
- **Inter Empresas "API/Integrações"** — webhook secret rotation

---

## Refs

- [RUNBOOK Settings Gateways](../../../../memory/requisitos/PaymentGateway/RUNBOOK-settings-gateways.md)
- [Cowork F1 components](../../../../prototipo-ui/prototipos/payment-gateway-ui/components/)
- [Cowork F1.5 critique](../../../../prototipo-ui/prototipos/payment-gateway-ui/critiques/payment-gateways-critique-score.json)
- [Charter irmão — /financeiro/cobranca](../../Financeiro/Cobranca/Index.charter.md)
- [ADR 0170 — PaymentGateway module](../../../../memory/decisions/0170-paymentgateway-module-extraction.md)

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-19 | Wagner [W] + Claude Code [CL] | F3 PaymentGateway UI Tela 2 entregue — Settings de credenciais 5 drivers com drawer 4 tabs + sheet wizard 3 steps + Trust L3 confirm toggle + health check on-demand. Cowork F1.5 score 93/100. |
