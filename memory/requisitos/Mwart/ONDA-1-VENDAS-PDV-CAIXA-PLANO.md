---
module: Mwart
doc_type: plano-onda
onda: 1
title: "Onda 1 — Vendas, PDV & Caixa · plano de migração (MWART Fase 1 · PLAN)"
status: f1-plan
owner: wagner
author: "[CC]"
created: "2026-06-13"
adversario_cd: "Square POS + Stripe Checkout"
parent_roadmap: "memory/requisitos/Mwart/ROADMAP-ONDAS-BLADE-ADVERSARIOS.md"
related_adrs: [0104, 0107, 0114, 0093, 0143]
---

# Onda 1 — Vendas, PDV & Caixa · PLANO (MWART Fase 1)

> **Escopo deste doc:** a **Fase 1 (PLAN)** do ciclo MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md))
> aplicada à Onda 1 do [roadmap](ROADMAP-ONDAS-BLADE-ADVERSARIOS.md). Entrega o **inventário de rotas**, o
> **estado React atual**, o **critério de desligamento** e o **scorecard do adversário** — NÃO o código.
> As Fases 2–5 (Backend baseline → Frontend → QA → Cutover) são gated: cada tela passa pelo gate visual
> F1.5/F3 ([ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)/[0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)) e [W] aprova o **screenshot** antes do Edit.

## 1. Por que esta onda é a primeira

Coração do balcão da Larissa (1280px) e **já meio migrada** — o trabalho aqui é majoritariamente **fechar e desligar**, não construir do zero. Domínios **E (Vendas/PDV)** + **H (Caixa)**, ≈66 funções.

## 2. Inventário — o que ainda responde Blade (a fechar)

Lido de `routes/web.php@main` nesta sessão. Os `resource()` abaixo são os **routes Blade vivos** que o contrato de completude exige matar:

| Route legado | Controller | Estado | Ação de fecho |
|---|---|---|---|
| `Route::resource('pos')` (l.474) | `SellPosController` | PDV puro Blade — `index()` Blade (l.120); porém `create` já cai em `Sells/Create` Inertia (l.283) | migrar `pos/index` (lista PDV) + checkout puro → Inertia; 302/remover resource |
| `Route::resource('sell-return')` (l.615) | `SellReturnController` | 100% Blade | nova tela devolução Inertia; 302/remover resource |
| `Route::resource('cash-register')` (l.585) | `CashRegisterController` | Blade; `/vendas/caixa` (l.390) já é o gêmeo Inertia (`Sells/Caixa/Index`) | redirecionar `cash-register` → `/vendas/caixa`; remover resource |
| `/sells/drafts`, `/sells/subscriptions`, `/sells/duplicate`, convert-to-* (l.377-381) | `Sell/SellPos` | drafts/subscriptions **já em Inertia** | confirmar paridade e desligar quaisquer views Blade residuais |
| `pos/payment/{id}` (l.164), `confirm-payment` (l.150), `get-types-of-service-details` (l.376) | `SellPosController` | endpoints AJAX (não-tela) | manter como API; garantir consumo pela tela Inertia |

> **Famílias adjacentes da Onda 1** (do censo §1-E): `sells`, quotations, shipments, types-of-service, commission-agents. Quotations já tem `Sells/Quotations.tsx`. types-of-service/commission-agents podem cair na Onda 7 (config) se forem baixa-frequência — **decisão [W] no kickoff da onda**.

## 3. Estado React atual (já vivo — não rebuildar)

`resources/js/Pages/Sells/` já contém, **com charter**:
- `Index.tsx` (+ `.charter.md` + `.casos.md`) — lista de vendas (`SellController@inertiaList`, render l.661)
- `Create.tsx` (+ charter + `.design-spec.json`) — venda/PDV (render por `SellController` l.1001 **e** `SellPosController` l.283)
- `Edit.tsx`, `Show.tsx`, `Drafts.tsx`, `Quotations.tsx`, `Subscriptions.tsx` — todos com charter
- `Caixa/Index` — caixa Inertia (`SellController@inertiaCaixa`, render l.822, rota `/vendas/caixa`)

**Lacunas reais da Onda 1:** (a) **PDV puro** (`pos/index` — a tela de balcão rápida, distinta de `Create`), (b) **devoluções** (`sell-return`), (c) desligar os 3 `resource()` legados.

## 4. Critério de desligamento (o fecho honesto da onda)

A Onda 1 **só fecha** quando:
1. `Route::resource('pos')` → removido ou 302 → tela Inertia de PDV.
2. `Route::resource('sell-return')` → removido ou 302 → tela Inertia de devolução.
3. `Route::resource('cash-register')` → 302 → `/vendas/caixa` e resource removido.
4. `sell/*.blade` correspondentes viram lápide (sem rota autenticada servindo AdminLTE).
5. Gate [CX] (Onda 10) confirma: **0 view Blade viva** na família Vendas/PDV/Caixa.

## 5. Adversário [CD] — scorecard F1.5

**Régua:** Square POS (velocidade de venda: 1 mão, teclado, zero recarga) + Stripe Checkout (recibo/pagamento sem fricção). Pontuar a tela migrada nas **15 dimensões** do gate visual; **trava em nota ≥80** (ou ≥9 no método KB-9.75) antes de [W] aprovar o screenshot.
- Falha-âncora: *"se a Larissa fizer uma venda mais devagar que num Square, a onda falhou."*
- Foco de medição: latência teclado→item, atalho de pagamento, fecho de caixa em ≤2 telas, devolução rastreável.

## 6. Backend baseline (Fase 2) — antes de qualquer Edit no front

- **Pest 5+** cobrindo o caminho de venda atual (criar venda, pagamento, fecho de caixa, devolução) **antes** de tocar a tela — sem baseline = regressão silenciosa garantida (gap conhecido do BRIEFING MWART).
- Congelar contrato de props dos Controllers que já renderizam Inertia (não quebrar `Sells/Create`/`Caixa`).

## 7. Risk register — Tier 0 e específicos

- **Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)):** toda query de venda/caixa filtra `business_id` (global scope). PDV e fecho de caixa lêem dados sensíveis de tenant — vazamento aqui é o pior bug do projeto.
- **FSM da venda ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)):** o drawer FSM de Sells já existe; o PDV novo não pode criar caminho paralelo de transição de status.
- **Caixa append-only:** abertura/fecho e movimentos preservam auditoria; não permitir edição retroativa.
- **Pagamento:** `confirm-payment`/`pos/payment` continuam consumidos pela tela nova; não duplicar lógica de baixa de título (bridge Financeiro).

## 8. Próximo passo (gated — [W] decide)

1. [W] confirma escopo da onda (incluir/adiar types-of-service + commission-agents).
2. [CC] produz **F1 (PDV puro + Devolução)** no Cowork → [CD] roda **F1.5** contra Square/Stripe → [W] aprova **screenshot**.
3. [CL] porta pro repo seguindo as 5 fases MWART; Pest baseline primeiro.
4. Desligar os 3 `resource()` legados → Onda 1 marcada concluída **só** quando o gate [CX] confirmar zero-Blade na família.

> **Nada deste plano vira código sem o gate visual + aprovação [W].** É a Fase 1 (PLAN) — o mapa, não a obra.
