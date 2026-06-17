---
page: /financeiro/cobranca
component: resources/js/Pages/Financeiro/Cobranca/Index.tsx
owner: wagner
status: live
last_validated: "2026-05-19"
parent_module: Financeiro
sister_module: PaymentGateway
related_adrs: [93, 94, 104, 114, 144, 170]
related_us: [US-PG-F3-COBRANCA]
related_prototype: prototipo Cowork "payment-gateway-ui" F1+F1.5 aprovado [W] 2026-05-19 (Chat Cowork live)
related_decisions: COWORK_HANDOFF.paymentgateway-ui.md (F1 score 96/93/93)
tier: A
charter_version: 1
supersedes: /financeiro/boletos
---

# Page Charter — `/financeiro/cobranca`

> **Status:** F3 PaymentGateway UI Tela 1 — Cowork F1+F1.5 aprovado [W] 2026-05-19 score 96/100. Origem ADR 0144 (extração PaymentGateway). Persona: **Eliana [E]** (financeiro escritório) + **Larissa [Cliente Piloto]** (via Sells drawer chip → PR-3).

---

## Mission (1 frase)

Eliana acompanha **toda a cobrança gerada (boleto · pix · pix automático · cartão) em uma view única** com funil 5 etapas + 4 KPIs (3 fixos + 1 contextual) + tabela rica chip composto gateway+tipo + drawer detalhe por tipo + wizard nova cobrança + AI ✦ Resumir mês, pra responder em <30s "quem pagou hoje?", "quais vencem amanhã?", "tenho mandato PIX Automático ativo?".

---

## Goals — Features (faz)

- **Funil de cobrança 5 etapas** (Em aberto → Lembrete → Cobrança ativa → Vencidos +5d → Protesto) + chip lateral "mandato(s) cancelado(s)" quando aplicável
- **4 KPI cards**: Pago no mês (emerald) · Vencido (rose) · Em aberto (default) · 1 contextual (PIX Aut. mandatos / MRR SaaS / próx. janela remessa C6 — alterna)
- **Tabs filtro status**: Todos / Em aberto / Pagas / Vencidas / Canceladas / Erro
- **Filtros linha 2**: chips Tipo (Todos/Boleto/PIX/PIX Aut./Cartão) + dropdown Gateway + dropdown Conta destino + chips Origem (Venda/Recorrente/SaaS Oimpresso)
- **Busca**: cliente · doc · nosso nº · origem — atalho `/`
- **Tabela densa** (text-[12.5px] tabular-nums): vencimento + relativo · Pagador + doc mascarado + origem · chip composto gateway+tipo · Conta destino · nosso nº · valor · status badge com ícone lucide · ações row hover
- **Drawer detalhe condicional por tipo** (520px): boleto (linha digitável + cód barras) · PIX cob (BR Code + QR fake) · PIX recv (mandato BCB) · Cartão (last4 + 3DS)
- **Sheet "Nova cobrança"** wizard 4 steps (Tipo → Pagador → Valores → Revisar) — dispara `PaymentGatewayContract::emitirX()` (Onda 4 backend)
- **Sheet "Remessa/Retorno"** CNAB 240 — C6 driver (Onda 5 backlog)
- **AI panel ✦ "Resumir mês"** — narrativa MRR + inadimplência + distribuição gateway + insights
- **Cheat sheet** overlay `?` — atalhos teclado documentados
- **KB-9.75 atalhos teclado**: `/` foco busca · `J/K/↓↑` nav rows · `Enter` abre drawer · `Esc` fecha overlays · `?` cheat
- **Persistência localStorage**: namespace `oimpresso.financeiro.cobranca.*` (tab, tipo, gateway, account, origem)
- **Multi-tenant Tier 0** ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)): `Cobranca` global scope via `HasBusinessScope`

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD.

- ❌ Mutação no GET (read-only) — emitir cobrança vai pra rota dedicada POST
- ❌ Pagination clicável (limit 100 atual; cursor pagination em backlog se exceder 1k/biz)
- ❌ Export CSV/PDF (botão Exportar UI; backend em backlog Onda 5)
- ❌ Auto-refresh real-time via Centrifugo (F2; depende webhook Inter LIVE)
- ❌ Edição inline da cobrança (drawer leva pra rota dedicada de edit)
- ❌ Mobile responsive (cards stack) — desktop only (Persona Eliana)
- ❌ Histórico de comentários inline por linha — backlog (P2)
- ❌ Sort clicável colunas — backlog (P2)
- ❌ Estorno real botão (UI só; depende driver `refund()` Onda 4 mergeada parcial)
- ❌ Funil "Protesto" job real — derivação UI-only (Onda 5)

---

## UX Targets

- p95 first-paint < 500ms (com `Inertia::defer` em `cobrancas`, `kpis`, `funil`)
- Cabe em monitor 1280px sem scroll horizontal (Persona Eliana)
- 0 erros JS console
- Tabular nums em TODOS valores monetários
- Drawer abre em <100ms (lazy via `useState`)
- Cores semânticas: emerald · rose · amber · sky · violet · stone
- WCAG 2.1 AA: ESC fecha drawer/sheet/cheat, focus trap, scroll lock, aria-labels

---

## UX Anti-patterns

- ❌ Status badge sem ícone (canon = ícone lucide + label + cor semântica)
- ❌ Chip gateway só texto (canon = quadrado colorido sigla + chip tipo)
- ❌ Botão "Emitir cobrança" sem wizard (T-AP-13 mutação NO-OP)
- ❌ Drawer modal centralizado (canon = lateral 520px com slide animation)
- ❌ Atalhos teclado ausentes (canon KB-9.75 = J/K/Esc/?/Enter/`/`)
- ❌ Filtros estourando 1280px (canon = 2 linhas)

---

## Automation Hooks

- `CobrancaController::index(Request, CobrancaQuery): Response`
  - lê `session('user.business_id')` + filtros querystring
  - `Inertia::defer(fn () => $query->buildShape($bizId, $filters))` em `cobrancas`/`kpis`/`funil`
  - retorna `Inertia::render('Financeiro/Cobranca/Index', $shape)`
- Multi-tenant: `HasBusinessScope` trait em `Cobranca` + `PaymentGatewayCredential`
- Permission canon: `financeiro.dashboard.view` (granularidade `paymentgateway.cobranca.view` em backlog F2)

---

## Automation Anti-hooks

> O que essa tela NUNCA dispara. Vira Pest GUARD.

- ❌ Não dispara emails ao abrir
- ❌ Não dispara WhatsApp / SMS
- ❌ Não escreve no banco no render (read-only — emissão via POST dedicado)
- ❌ Não chama Service externo (Inter/Asaas/C6 API) no render
- ❌ Não acessa `Cobranca` de outro `business_id` (Tier 0 ADR 0093)
- ❌ Não loga PII em plain text (payer_* via `LogsActivity` configurado no Model)
- ❌ Não gera PDF do boleto no render (lazy — rota dedicada)

---

## Métricas vivas (Pest GUARDs)

```php
it('renderiza Inertia component Financeiro/Cobranca/Index')
it('expõe Props no shape esperado (cobrancas, kpis, funil, contas, gateways, filtros)')
it('expõe 4 KPIs (pago_mes, vencido, aberto, contextual)')
it('expõe funil 5 etapas (aberto, lembrete, cobranca_ativa, vencido_5d, protesto)')
it('filtra por status/tipo/gateway/account/origem via querystring')
it('Tier 0 IRREVOGÁVEL: Cobranca respeita business_id global scope')
it('redirect 301 /financeiro/boletos → /financeiro/cobranca')
it('não dispara mutação em GET /cobranca (read-only puro)')
```

---

## Comparáveis canônicos

- **Stripe Dashboard "Payments"** — densidade tabular + chip composto + drawer
- **Asaas Painel "Cobranças"** — funil + tipo+gateway chip + AI hint
- **Inter Empresas "Cobranças"** — KPIs no topo + linha clicável
- **Excluir:** Conta Azul Cobranças (UI 2020, sem chip composto)

---

## Refs

- [RUNBOOK Cobrança](../../../../memory/requisitos/Financeiro/RUNBOOK-cobranca.md)
- [Cowork F1 components](../../../../prototipo-ui/prototipos/payment-gateway-ui/components/)
- [Cowork F1.5 critique](../../../../prototipo-ui/prototipos/payment-gateway-ui/critiques/REPORT.md)
- [Handoff F2 Cowork](../../../../COWORK_HANDOFF.paymentgateway-ui.md)
- [ADR 0144 — PaymentGateway extração](../../../../memory/decisions/0144-paymentgateway-extracao-camada-cobranca.md)
- [ADR 0170 — PaymentGateway module](../../../../memory/decisions/0170-paymentgateway-module-extraction.md)
- [LICOES F3 Financeiro rejeitado](../../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md)
- Charter irmão histórico — [/financeiro/boletos](../Boletos/Index.charter.md) (superseded)

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-19 | Wagner [W] + Claude Code [CL] | F3 PaymentGateway UI Tela 1 entregue — substitui /financeiro/boletos. Escopo expandido pix+card+pix_recv+subscription_license. Cowork F1.5 score 96/100. Bundle CSS canon `cowork-payment-gateway-bundle.css` aplicado inteiro (regra Wagner 2026-05-18). |
