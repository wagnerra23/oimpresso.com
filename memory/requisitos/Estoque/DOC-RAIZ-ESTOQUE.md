---
title: "Documento Raiz de Estoque — arquitetura canônica, fluxos e auditoria"
date: 2026-06-04
type: reference
status: ativo
scope_modulos: [core-UltimatePOS, Sells, Compras, OficinaAuto, Repair, Vestuario, Woocommerce, Fsm]
related_adrs: [0093, 0129, 0101, 0137, 0192]
owner: [W]
metodo: auditoria 3-frentes (core + fluxos + módulos/reserva) + verificação manual dos achados críticos
sem_alteracao: este_doc_e_so_registro_auditoria_nenhuma_mudanca_de_codigo
---

# Documento Raiz de Estoque (2026-06-04)

> **Por que existe:** centralizar como o estoque funciona HOJE em todos os módulos antes de qualquer
> alteração (pedido Wagner: "faça auditoria e registre antes de sair alterando tudo"). É a fonte da
> verdade pra qualquer mudança futura em estoque — entrada, saída, devolução, transferência, ajuste,
> reserva, POS e OS.
>
> **Regra de ouro:** mudança em estoque é Tier 0 (afeta dinheiro e multi-tenant). Toda alteração aqui
> passa por: ler este doc → ADR se mudar invariante → teste Pest antes do código.
>
> **Correção 2026-07-02 (Onda 0 · doc `type: reference`, editável com data):** o achado **R2** estava
> **stale**. A baixa de peça da OS **não** foi "revertida" — está **LIVE** em `origin/main`
> (`ServiceOrderItemService::baixarEstoqueConclusao()` via `ServiceOrderObserver` bloco P0-2). Corrigidos
> §4 (linha da OS OficinaAuto) e §8 (R2); o que resta é **refino** (location default + `BomResolver` p/ kits),
> não implementação do zero. Nenhuma mudança de código — só correção do registro.

---

## 1. Modelo de dados (tabelas)

| Tabela | Papel | Campos-chave | business_id? |
|---|---|---|---|
| `variation_location_details` (VLD) | **Saldo atual** de estoque por (variação × local) | `product_id`, `variation_id`, `location_id`, `qty_available` (decimal 22,4) | ❌ **NÃO** (FK `location_id`→`business_locations`, `variation_id`→variação) |
| `transaction_sell_lines` | Linhas de venda | `quantity`, `quantity_returned` | via transaction |
| `purchase_lines` | Linhas de compra / opening_stock | `quantity`, `quantity_sold`, `quantity_adjusted`, `quantity_returned`, `mfg_quantity_used` | via transaction |
| `stock_adjustment_lines` | Linhas de ajuste | `quantity` | via transaction |
| `transaction_sell_lines_purchase_lines` | Mapping venda↔compra (rastreabilidade FIFO/lote) | `sell_line_id`, `purchase_line_id`, `quantity` | lógico (NÃO afeta saldo) |
| `stock_reservations` | **Reserva** (hold) FSM, separada do saldo | `business_id`, `transaction_id`, `product_id`, `variation_id`, `location_id`, `qty_reserved`, `status`, `expires_at` | ✅ **SIM** (HasBusinessScope) |
| `product_bom` | Kits / BOM (componente→folha) | — | ✅ |

**Saldo vs reserva:** `qty_available` é o saldo físico. Reserva (`stock_reservations`) é um *hold lógico*
que NÃO decrementa `qty_available` até o **consumo** (ADR 0129 · US-SELL-013).

Migration VLD: [`database/migrations/2017_12_25_163227_create_variation_location_details_table.php`](../../../database/migrations/2017_12_25_163227_create_variation_location_details_table.php) — confirma ausência de `business_id`.

---

## 2. Mecanismo canônico (ProductUtil = fonte da verdade do saldo legacy)

Arquivo: [`app/Utils/ProductUtil.php`](../../../app/Utils/ProductUtil.php). **3 mutadores + 1 orquestrador**:

| Método | Linha | O que faz | Respeita `enable_stock`? |
|---|---|---|---|
| `updateProductQuantity($location_id,$product_id,$variation_id,$new,$old=0,...)` | 350 | Aplica **delta** `qty_available += (new-old)` (entra OU sai) | ✅ |
| `decreaseProductQuantity($product_id,$variation_id,$location_id,$new,$old=0)` | 399 | **Decrementa** `qty_available -= (new-old)` | ✅ |
| `decreaseProductQuantityCombo($combo_details,$location_id)` | 438 | Decompõe combo → decrementa cada filho | ✅ (indireto) |
| `adjustProductStockForInvoice($status_before,$transaction,$input,...)` | 826 | Orquestra venda: draft→final decrementa; final→draft devolve; final→final só linhas novas | ✅ (indireto) |

> ⚠️ **NÃO existe** `increaseProductQuantity`. Entrada de estoque é feita por `updateProductQuantity`
> (delta positivo) ou por `decreaseProductQuantity` com argumentos invertidos (delta negativo → soma).

**Leitura / relatórios** (também em ProductUtil): `getCurrentStock` (1452), `getProductStockDetails` (1742),
`getVariationStockDetails` (1916), `getVariationStockHistory` (1990, rolling balance), `getVariationStockMisMatch` (2190, saldo calculado × real), `fixVariationStockMisMatch` (2287).

---

## 3. Matriz de movimentação por tipo de transação

| Tipo (transactions.type) | Direção | Onde dispara | Método de saldo |
|---|---|---|---|
| `purchase` (compra) | **ENTRA** ao virar `received` | PurchaseController + `createOrUpdatePurchaseLines` (ProductUtil ~1201) | `updateProductQuantity` |
| `sell` (venda) | **SAI** ao virar `final` | SellPosController/SellController + `adjustProductStockForInvoice` | `decreaseProductQuantity` |
| `sell_return` (devol. venda) | **ENTRA** (volta pro estoque) | SellReturnController + `TransactionUtil::addSellReturn` | `decrease/updateProductQuantity` |
| `purchase_return` (devol. compra) | **SAI** | PurchaseReturnController | `decreaseProductQuantity` (criar) / `updateProductQuantity` (reverter) |
| `stock_adjustment` (ajuste) | **SAI** (normal) / reverte ao deletar | StockAdjustmentController | `decreaseProductQuantity` / `updateProductQuantity` |
| `sell_transfer` + `purchase_transfer` (transferência) | **SAI** origem + **ENTRA** destino | StockTransferController | `decreaseProductQuantity` (origem) + `updateProductQuantity` (destino) |
| `opening_stock` (estoque inicial) | **ENTRA** | OpeningStockController / `addSingleProductOpeningStock` (ProductUtil ~1118) | `updateProductQuantity` |

**Regra geral:** rascunho/cotação (`draft`/`quotation`) **não mexem** estoque; só status terminal
(`final` venda, `received` compra) movimenta.

---

## 4. Fluxos por origem (controllers + estado da tela)

| Fluxo | Controller@método | Move estoque via | DB::transaction | Tela |
|---|---|---|---|---|
| **POS venda** | `SellPosController@store/update/destroy` | `decreaseProductQuantity` (641/2696/2920) + combo | ✅ | Inertia `Sells/Create.tsx` (flag `useV2SellsCreate`) · **edit ainda Blade** `sale_pos/edit` |
| **Venda comum** | `SellController` (store delega ao POS) | — | ✅ | Inertia `Sells/Index|Show|Caixa` · create/edit Blade fallback |
| **Devolução venda** | `SellReturnController@store/destroy` | `addSellReturn` / `updateProductQuantity` (435) | ✅ | **Blade** `sell_return/*` |
| **Compra** | `PurchaseController@store/update/destroy` | `createOrUpdatePurchaseLines` (received) | ✅ | Inertia `Purchase/*` (opt-in `?v=2`) · Blade fallback |
| **Devolução compra** | `PurchaseReturnController@store/destroy` | `decreaseProductQuantity` (266) | ✅ | **Blade** `purchase_return/*` |
| **Transferência** | `StockTransferController@store/destroy` | `decrease`+`updateProductQuantity` | ✅ | Inertia `StockTransfer/*` (`?v=2`) · Blade fallback |
| **Ajuste** | `StockAdjustmentController@store/destroy` | `decreaseProductQuantity` (339) | ✅ | Inertia `StockAdjustment/*` (`?v=2`) · Blade fallback |
| **Estoque inicial** | `OpeningStockController@save` | `updateProductQuantity` | ⚠️ ver R3 | **Blade** `opening_stock/*` |
| **OS OficinaAuto** | `ServiceOrderObserver` (status→`concluida`, bloco **P0-2**) → `ServiceOrderItemService::baixarEstoqueConclusao()` — **LIVE** (correção 2026-07-02: antes dizia "revertido/PROPOSTO", stale; ver R2) | `$vld->save()` Eloquent auditável (INV-1) + clamp 0; refino aberto: location default + `BomResolver` p/ kits | — | Inertia `OficinaAuto/ServiceOrders/*` |
| **Repair JobSheet** | `JobSheetObserver` | **NÃO toca estoque** (só fatura venda derivada) | — | Inertia `Repair/*` |
| **Reserva FSM** | `ReservarEstoque`/`ConsumirEstoque`/`LiberarReserva` | reserva separada + `DB::table` direto no consumo | — | n/a (side-effects FSM) |

**Telas de estoque ainda em Blade legacy** (candidatas a MWART): `sell_return/*`, `purchase_return/*`,
`opening_stock/*`, e os create/edit de POS/venda/compra/transfer/ajuste como fallback.

---

## 5. Reserva vs Disponível (sistema FSM — ADR 0129)

`app/Domain/Fsm/SideEffects/`:
- **`ReservarEstoque`** — cria `stock_reservations` (status `active`), **NÃO mexe** `qty_available`. Resolve kits via `BomResolver` (1 reserva por folha). Tenant-safe (`business_id` explícito).
- **`ConsumirEstoque`** — marca reservas `consumed` e **baixa `qty_available`** ([`ConsumirEstoque.php:54-57`](../../../app/Domain/Fsm/SideEffects/ConsumirEstoque.php)).
- **`LiberarReserva`** — marca `released`, não mexe saldo.
- **`ExpireStaleReservationsJob`** — `expires_at < now()` → `expired`, não mexe saldo.

Estados: `active → consumed | released | expired`.

---

## 6. Multi-tenant Tier 0 no estoque (ADR 0093)

`variation_location_details` **não tem coluna `business_id`**. O isolamento é **transitivo e seguro** porque:
- `variation_id` é PK **global única** → pertence a 1 produto → 1 business.
- `location_id` é PK **global única** → FK `business_locations` → 1 business.

Logo, query por `variation_id`/`location_id`/`product_id` já fixa o tenant (não há colisão de IDs entre
businesses). **Não é vazamento ativo.** É, porém, **frágil/ilegível** para queries cruas — defesa-em-profundidade
recomendada (ver R4).

`stock_reservations` **tem** `business_id` + `HasBusinessScope` (✅ Tier 0 explícito).

---

## 7. Invariantes (NÃO violar sem ADR)

- **INV-1 — Saldo só muda por caminho auditável.** Hoje há **2 escritores** de `qty_available`:
  (a) `ProductUtil` (Eloquent → dispara `LogsActivity` `inventory.stock` + checa `enable_stock`);
  (b) `ConsumirEstoque` via `DB::table` (**bypassa** LogsActivity e enable_stock). → ver R1.
- **INV-2 — Rascunho não movimenta.** Só `final`/`received` mexem estoque.
- **INV-3 — Toda movimentação dentro de `DB::transaction`.** Exceção conhecida: opening_stock (R3).
- **INV-4 — Reserva ≠ baixa.** `stock_reservations` segura; só `ConsumirEstoque` baixa de fato.
- **INV-5 — `enable_stock=0` não movimenta saldo** (produto sem controle de estoque).
- **INV-6 — Tenant fixado por variation_id/location_id/product_id** (IDs globais únicos).

---

## 8. Riscos / dívidas (severidade corrigida pós-verificação manual)

| # | Achado | Severidade | Evidência |
|---|---|---|---|
| **R1** | ✅ **CORRIGIDO 2026-06-04.** Era: `ConsumirEstoque` usava `DB::table` direto → sem `LogsActivity` + sem check `enable_stock`. Fix aditivo e guardado: quando `activity_log` existe (prod), baixa via modelo Eloquent (dispara `inventory.stock`) + checa `enable_stock`; em env sem audit (teste sqlite) mantém fallback `DB::table` com clamp preservado. Teste: `tests/Feature/Domain/Fsm/ConsumirEstoqueAuditTest.php`. **Falta:** rodar no CT 100. | 🔴→✅ | [`ConsumirEstoque.php`](../../../app/Domain/Fsm/SideEffects/ConsumirEstoque.php) |
| **R2** | **CORRIGIDO 2026-07-02 — claim stale.** A baixa de estoque da OS **está LIVE** em `origin/main`: `ServiceOrderItemService::baixarEstoqueConclusao()` (linha 135) é chamada pelo `ServiceOrderObserver` (bloco P0-2) no `status→concluida`; baixa via `$vld->save()` (Eloquent auditável, INV-1) + clamp em 0. **NÃO foi revertida.** Refino **aberto**: (a) resolve o VLD por `orderByDesc('qty_available')` ("maior saldo", ~linha 172) → trocar por **location default do business** via ProductUtil (auditável); (b) **não** chama `BomResolver` → se o item for kit, baixa o produto-kit direto em vez dos componentes (plugar `BomResolver`). | 🟡 refino | US-OFICINA-043/044 |
| **R3** | ❌ **FALSO ALARME.** A auditoria automática olhou só `ProductUtil::addSingleProductOpeningStock` (sem transação interna), mas o **único caller** `ProductController@store` envolve tudo em `DB::transaction` (`DB::commit()` em [linha 1807](../../../app/Http/Controllers/ProductController.php)); a edição via `OpeningStockController@save` também (`DB::beginTransaction()` linha 169). **Sem ação.** | ✅ Resolvido | `ProductController.php:1807` · `OpeningStockController.php:169` |
| **R4** | **VLD sem `business_id`** → isolamento transitivo. Não é leak hoje, mas qualquer query crua futura sem join é risco. | 🟢 Estrutural | migration 2017_12_25 |
| **R5** | **Oversell concorrente**: sem lock pessimista no draft→final, 2 vendas simultâneas podem furar saldo. Mitigação intencional = sistema de reserva FSM. | 🟢 Pré-existente UPOS | `getDetailsFromVariation` leitura sem `FOR UPDATE` |
| **R6** | **`Woocommerce::getLastStockUpdated`** lê VLD por product+location sem business_id — safe pelo mesmo motivo de INV-6, mas revisar contexto de chamada. | 🟢 Baixo | `WoocommerceUtil.php` ~1510 |

> Correção importante vs auditoria automática: os agentes marcaram "colisão cross-tenant CRÍTICA" em
> ConsumirEstoque e baixarEstoquePecas. **Isso é falso** — `variation_id`/`location_id`/`product_id` são
> IDs globais únicos, então não há colisão entre businesses. O risco real do ConsumirEstoque é **auditoria
> + bypass de enable_stock** (R1), não vazamento.

---

## 9. Backlog proposto (NÃO criado no MCP — Wagner aprova)

Priorizado do mais essencial pra integridade do estoque:

| Prio | Item | Esforço | Status |
|---|---|---|---|
| ~~P0~~ | ~~R1 — unificar escrita de saldo auditável~~ | 4h | ✅ **feito 2026-06-04** (falta smoke CT 100) |
| ~~P0~~ | ~~R3 — opening_stock em transação~~ | — | ❌ **falso alarme** (caller já envolve) |
| **P1** | **R2 — refino da baixa da OS (baixa já LIVE):** resolver location por default do business (ou location da OS quando schema ganhar `location_id`) em vez de "maior saldo" + plugar `BomResolver` p/ item-kit baixar componentes | 3h | aberto |
| **P1** | **Comando de conciliação:** roda `getVariationStockMisMatch` por business e alerta drift (saldo calculado × real) | 4h | aberto |
| **P1** | **Product picker na UI da OS** (passo 3 do P0-2): setar `product_id` do catálogo no item da OS (hoje texto livre) | 4h | aberto |
| **P2** | **R4 — adicionar `business_id` a VLD** (migration + `HasBusinessScope`) defesa-em-profundidade | 6h | aberto |
| **P2** | **MWART telas Blade de estoque** (sell_return, purchase_return, opening_stock, edits POS) | — | aberto |
| **P3** | **R6 — auditar callers `Woocommerce::getLastStockUpdated`** | 2h | aberto |

---

## 10. Como mexer em estoque daqui pra frente (checklist)

1. Ler este doc + ADR 0093 (Tier 0) + ADR 0129 (reserva FSM).
2. Usar **sempre** `ProductUtil` pra mexer `qty_available` (nunca `DB::table`/`->qty_available =` direto) — respeita INV-1.
3. Toda movimentação dentro de `DB::transaction` (INV-3).
4. Escrever Pest **antes** (red→green), padrão: skip gracioso em sqlite + dado real MySQL (ex: `ServiceOrderItemStockBaixaTest`).
5. Rodar no CT 100: `php artisan test --filter=<Stock|Estoque|...>` (sem PHP local).

---

## Refs

- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0129 — State machine canônica FSM + reserva](../../decisions/0129-state-machine-canonica-fsm-rbac.md)
- [ADR 0192 — Auto-faturar OS→Venda](../../decisions/0192-auto-faturar-os-venda-jobsheet-observer.md)
- Análise tela-venda × oficina: [`memory/sessions/2026-06-04-analise-tela-venda-vs-oficina.md`](../../sessions/2026-06-04-analise-tela-venda-vs-oficina.md)
- Código-chave: `app/Utils/ProductUtil.php`, `app/Utils/TransactionUtil.php`, `app/Domain/Fsm/SideEffects/*`, `app/VariationLocationDetails.php`, `Modules/OficinaAuto/Services/ServiceOrderItemService.php`
</content>
