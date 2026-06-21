---
module: Mwart
doc_type: cutover-ledger
onda: 1
title: "Onda 1 — Cutover Ledger verificado (Vendas/PDV/Caixa) + emenda [CX] ao contrato de completude"
status: f1-verificado
owner: wagner
author: "[CC] + workflow onda1-cutover-ledger (7 agentes)"
created: "2026-06-13"
parent_plan: "memory/requisitos/Mwart/ONDA-1-VENDAS-PDV-CAIXA-PLANO.md"
related_adrs: [0277, 0104, 0093, 0101, 0143, 0192]
---

# Onda 1 — Cutover Ledger verificado

> **Como nasceu:** workflow adversarial `onda1-cutover-ledger` (7 agentes — 5 mapeadores Explore em paralelo + 1 QA baseline + 1 red-team [CX]), lido de `@main` nesta sessão. Mapeou ~70 rotas da família Vendas/PDV/Caixa contra os 4 controllers (`SellController` 3610L, `SellPosController` 3378L, `SellReturnController` 594L, `CashRegisterController` 234L) e ~85 views Blade.
> **Por que existe:** a Fase 1 (PLAN) original dizia "302/remover `resource('pos'/'sell-return'/'cash-register')`". O [CX] provou que isso é **desonesto e perigoso** em vários pontos. Este ledger é a versão verificada — com os destinos **corrigidos** pelo red-team.

## §1 · Emenda [CX] ao contrato de completude — o medidor REAL

O contrato do [ADR 0277](../../decisions/0277-rota-migracao-blade-ondas-completude.md) diz: *"migrado = route Blade morto ou 302, não 'React existe'."* O [CX] mostrou que **a própria existência do gêmeo React não desliga o Blade** — há dois mecanismos que mantêm Blade vivo escondido:

1. **Fallback por header `X-Inertia`.** `SellController::show` (l.2431), `edit` (l.2916), `getDrafts` (l.3052), `getQuotations` (l.3103) e `index` (l.107) fazem dual-render: **com** `X-Inertia` → Inertia; **sem** o header → `return view('sale_pos.show' / 'sell.edit' / 'sale_pos.draft' / 'sale_pos.quotations')` **Blade**. Toda URL **colada, bookmark, refresh hard de deep-link, link de e-mail/WhatsApp ou crawler** chega sem `X-Inertia` → **cai no Blade**. Um cutover que só testa navegação interna (que sempre manda o header) **nunca vê o Blade — mas o usuário que cola a URL vê**.
2. **Fallback por feature flag.** `SellController::create` (l.999-1000) e `SellPosController::create` (l.281-282) caem em `view('sell.create' / 'sale_pos.create')` sempre que `useV2SellsCreate` estiver **OFF** para aquele `business_id`. O cutover **não é binário no código — depende do estado runtime do GrowthBook por tenant.**

> **Emenda operacional ao critério de desligamento (Onda 1):** uma rota dual-render só conta como migrada quando **(a)** o branch `return view(...)` Blade é **removido do controller** *e* **(b)** a flag `useV2SellsCreate` está **100% rollout** (sem tenant em fallback). "Inertia existe" ≠ "Blade morto". O gate da Onda 10 ([CX]) precisa de uma probe **sem `X-Inertia`** pra flagrar isso.

## §2 · Ledger por família — destino CORRIGIDO

Legenda fate: `die` (Blade puro, já há twin React → remover) · `302` (redirecionar pro gêmeo) · `keep-api` (endpoint não-tela consumido pela tela React — **não matar**) · `already-inertia` (já renderiza Inertia; resta remover o fallback Blade) · `open` (**sem twin — precisa construir React ANTES de matar**).

### E. pos / PDV (`SellPosController`) — 34 rotas
| rota | serves | fate (corrigido) | nota |
|---|---|---|---|
| `GET pos` (index) | blade | **open** ⚠️ | PDV balcão 3-col. [CX]: Sells/Create é venda direta, **não** o POS de balcão — verificar cobertura antes de matar. |
| `POST pos` (store) | json | **keep-api** ⚠️ | submit handler do form (Sells/Create.tsx posta aqui). Vira 302→/sells preservando flash. **Não die.** |
| `GET pos/{id}/edit` | blade | open | editar venda; depende de twin de edição cobrir o caso PDV. |
| `GET /pos/payment/{id}` | blade | open | modal "editar só pagamento" — sem twin dedicado. |
| `GET /invoice/{token}`, `/quote/{token}`, `/pay/{token}` | blade | **open** 🔴 | **links PÚBLICOS guest** (token, throttle:30,1) enviados a clientes por WhatsApp/e-mail. Matar quebra faturas/cotações/pagamentos **já distribuídos no mundo real**. Precisam de twin público próprio. |
| `POST /confirm-payment/{id}` | json | keep-api | gateway (Razorpay). Consumido pela tela. |
| `get_product_row`, `get_payment_row`, `get-reward-details`, `get-recent-transactions`, `get-product-suggestion`, `get-featured-products`, `get-types-of-service-details` | json | **keep-api** | AJAX que alimenta o PDV. **Não são telas.** |
| `toggle-subscription`, `convert-to-draft`, `convert-to-proforma`, `copy-quotation` | json | **keep-api** ⚠️ | ações FSM consumidas por botões. **Duplicadas** (375/448, 380/453, 381/454) — resolver antes do cutover. |
| `service-staff-*`, `pause-resume-*`, `mark-as-available` | blade/json | open/keep-api | fluxo de staff de serviço (oficina). |
| `print`/`downloadPdf`/`download-quotation`/`download-packing-list`/`invoice-url` | blade | **keep-api** ⚠️ | geradores de **PDF/print (mPDF)**, referenciados por `Sells/Show.tsx` (`urls.print`). Matar quebra o botão Imprimir do React. |

### E. sell-return (`SellReturnController`) — 10 rotas · 🔴 ZERO React
| rota | serves | fate (corrigido) | nota |
|---|---|---|---|
| `GET sell-return` (index) | blade | **open** 🔴 | [CX]: marcar `die` é FALSO — **não existe `Pages/SellReturn/*.tsx`**. É a única implementação viva. |
| `GET sell-return/add/{id}` | blade | **open** 🔴 | a tela real de criar/editar devolução, 100% Blade. **Onda 1 precisa CONSTRUIR isto antes de desligar.** |
| `GET sell-return/{id}` (show) | blade | open | detalhe da devolução, 100% Blade. |
| `POST sell-return` (store) | json | keep-api | persiste devolução (`TransactionUtil::addSellReturn`). |
| `DELETE sell-return/{id}`, `print/{id}`, `validate-invoice-to-return` | json | keep-api | ações/validação. |
| `sell-return/{id}/edit`, `update`, `get-product-row` | — | **dead route** | geradas por `resource` mas **sem método no controller** (594L) — remover do roteador. |

### H. cash-register + caixa (`CashRegisterController`) — 10 rotas
| rota | serves | fate (corrigido) | nota |
|---|---|---|---|
| `GET cash-register` (index) | blade | 302 → `/vendas/caixa` | gêmeo Inertia `Sells/Caixa/Index` existe. |
| `GET cash-register/{id}` (show), `register-details` | blade | 302 | detalhe do registro → caixa Inertia. |
| `GET cash-register/create` | blade | open | abre registro → redireciona pro PDV; verificar ponto de entrada. |
| `POST cash-register` (store) | json | keep-api ⚠️ | cria registro. **Tier 0:** falta `where('business_id')` explícito (l.81-87) — **verificar global scope** antes de afirmar bug. |
| `GET/POST cash-register/close-register` | blade/json | **open** 🔴 | **`/vendas/caixa` Inertia ainda NÃO tem "fechar caixa"** — o link "Fechar" navega de volta pro Blade (l.692). Coexistência deliberada (Wagner l.685-687). Onda 1 precisa do **fechar-caixa em React** antes de matar. **Tier 0:** UPDATE em l.219-221 sem `business_id` explícito — verificar. |
| `{id}/edit`, `update`, `destroy` | — | **die** | sem método (append-only, ledger imutável) — remover do roteador. |

### Núcleo Vendas (`SellController`) — já MWART
| rota | fate | nota |
|---|---|---|
| `GET /sells` (index), `/sells/{id}` (show), `/sells/create`, `/sells/{id}/edit`, `/sells/drafts`, `/sells/quotations`, `/vendas/caixa`, `/sells/subscriptions` | **already-inertia** | dual-render — **resta remover o branch `view(...)` Blade + 100% flag** (§1). |
| `sells-list-json`, `sheet-data`, `draft-dt`, `quick-payment`, `emitir-cobranca`, `ai-ask`, `create-os`, `bulk-print`, `bulk-export`, `fsm-*`, `commission-split`, `history`, `timeline-unified`, `audit` | **keep-api** | endpoints que alimentam Index/Show/drawers. **Não matar.** |
| `transcript.pdf` (`SellTranscriptPdfController`) | keep-api | gerador PDF Browsershot (não-tela). |
| ✅ **9 rotas DUPLICADAS** (l.375-383 vs 448-456) | — | **RESOLVIDO (PR #2720)** — bloco 2 (após o resource, byte-a-byte) removido; bloco 1 (antes do resource) permanece. Era o que `web.php` já admitia no comentário l.457-459. |

### Adjacentes
| rota | fate | onda |
|---|---|---|
| `types-of-service`, `sales-commission-agents` | die | **Onda 7** (config, baixa freq — não Onda 1) |
| `sales-order` (index-only), `shipments`, `edit-shipping`, `view-media` | open | sem twin — decisão [W]: Onda 1 ou deixar Blade? |

## §3 · Achados críticos do [CX] (verdict: "TEM FURO, não está honesto pra virar cutover")

1. 🔴 **`sell-return` e `cash-register/close` NÃO têm twin React.** Marcá-los `die` deletaria as únicas telas vivas. **Onda 1 = CONSTRUIR (devolução + fechar-caixa em React), depois desligar** — não só "remover resource".
2. 🔴 **Links públicos guest** (`/invoice|/quote|/pay/{token}`) vivem fora do app autenticado, distribuídos a clientes. Migração dedicada, não morrem no cutover interno.
3. ⚠️ **Fates `die` super-otimistas:** PDFs/print, `pos.store`, FSM actions são `keep-api` (a tela React os consome). Matar = quebrar botões do React.
4. ⚠️ **Hidden-Blade por `X-Inertia`/flag** (§1) — o maior furo: o medidor "Inertia existe" não enxerga o Blade que ainda responde a deep-links.
5. ✅ **9 rotas duplicadas** no `web.php` — **RESOLVIDO (PR #2720)** (bloco 2 byte-a-byte removido).

## §4 · Tier 0 — VERIFICADO (2 vazamentos confirmados e fechados · PR #2708)

> O workflow flagou; **verifiquei o código** e confirmei **2 vazamentos cross-tenant reais**. `CashRegister` (`app/CashRegister.php`) estende `Model` **sem global scope** — toda query precisa de `business_id` explícito ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Garantia 2).
- ✅ **RESOLVIDO (PR #2708)** — `postCloseRegister()` fechava o caixa por `where('user_id', $request->input('user_id'))` **sem business_id**. Como `user_id` vem do request → **WRITE cross-tenant** (fechar caixa de outro business + disparar `fin_titulo` no tenant errado). Fix: `where('business_id', session('user.business_id'))`.
- ✅ **RESOLVIDO (PR #2708)** — `CashRegisterUtil::getRegisterDetails($id)` lia o caixa e os totais financeiros por `cash_registers.id` **sem business_id** → **READ cross-tenant** via `show()`/`getCloseRegister()`. Fix: `where('cash_registers.business_id', auth)`.
- ✅ `store()` já setava `business_id` da session no create (guard de regressão no teste `CashRegisterTier0ScopeTest`).
- 🟡 **Aberto** — `getProductSuggestion`/`getFeaturedProducts` validam `location_id ∈ business_id`? (não verificado)
- 🟡 **Aberto** — Observer `CashRegisterClosed` → `fin_titulo` ([ADR 0192](../../decisions/0192-auto-faturar-os-venda-jobsheet-observer.md)) lê `cash_register` com escopo? (não verificado)

## §5 · Baseline Pest (Fase 2) — spec dos casos

Suíte sugerida: `tests/Feature/Sells/SellsPosCheckoutBaselineTest.php`. Captura o comportamento ATUAL antes de mexer no front (sem baseline = regressão silenciosa garantida). 5 casos:

1. **PDV Checkout + Pagamento** — `POST /pos`: assert `type='sell'`, `status='final'`, `payment_status='paid'`, `business_id=1` (de session, não input), `SUM(transaction_payments)=final_total`, `cash_register_transactions` criado.
2. **Abrir + Fechar Caixa** — `store`→3 vendas→`close-register`: assert `status open→closed`, `closed_at`, event `CashRegisterClosed` disparado (bridge `fin_titulo`); **Tier 0:** user_id=2 não fecha caixa de user_id=1 (403).
3. **Devolução multi-item** — `add`→`POST /sell-return`: assert `is_return=1`, `final_total<0`, `sell_lines.return_qty` atualizado, `SUM(qty_returned)≤qty_original`, crédito/refund no contato.
4. **Feature flag + permission guards** — flag OFF→Blade, ON→Inertia; sem permissão→403 antes de query.
5. 🔴 **Isolamento Tier 0** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)+[0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)) — 2 businesses: `business_id` SEMPRE de session; `POST /pos` com `business_id:999` no body é ignorado; `getListSells(1)` não vê vendas do biz 2. Fixtures `business_id=1` (nunca cliente real).

## §6 · Honestidade do método + próximo passo

- **Limitação:** o agente [CX] recebeu o inventário dos mapeadores **truncado** (14k chars) — por isso alguns "missed_routes" que ele lista (sells core, cash-register) **estavam** mapeados, só não couberam no input dele. O valor do [CX] aqui são as **correções de fate** e o **achado hidden-Blade** (§1/§3), não a lista de "missed".
- **Próximo passo gated [W]:** Onda 1 deixa de ser "matar 3 resources" e vira **construir 3 telas React faltantes** (PDV-balcão puro · Devolução · Fechar-caixa) + remover fallbacks Blade + tratar links guest — cada uma pelo ciclo MWART (F1 Cowork → F1.5 gate visual Square/Stripe → [W] aprova screenshot → F2-F5). **Nada disso roda sem seu screenshot.**
