# INVENTÁRIO L1 — Vendas / PDV (telas Blade)

> Lido no `main` (`6b923a26d904`) neste turno: árvore `resources/views/{sell,sale_pos,sales_order,sell_return,sells,import_sales,cash_register}` + `routes/web.php`. Granularidade ratificada: **1 Blade = 1 tela React**.
> Método: **eu leio o Blade e proponho a Ficha; você corrige.** Ficha fica só aqui no Cowork por enquanto.
> Coluna **Frescor**: 🟠 produção atrás · 🔵 vivo à frente (documentar, não repintar) · ⚪ não avaliado — roda frescor antes de assumir.

## A. Vendas (`sell/`) — SellController

| ID | Tela | Blade | Rota / controller | Arquétipo | Frescor |
|---|---|---|---|---|---|
| BL-sell-index | Lista de vendas | `sell/index.blade.php` (17.8 KB) | `/sells` → `Route::resource('sells', SellController)` · JSON vivo `/sells-list-json` (`inertiaList`) | lista PT-01 | 🔵 tem `Sells/Index.tsx` + drawer (US-SELL-008) |
| BL-sell-create | Nova venda (faturamento) | `sell/create.blade.php` (46.9 KB — a maior do lote) | `/sells/create` → `SellController@create` · V3 em `/sells/create-v3` (`SellsV3Controller`) | form PT-03 | 🔵 `Sells/Create.tsx` é a viva; V3 coexiste |
| BL-sell-edit | Editar venda | `sell/edit.blade.php` (44.2 KB) | `/sells/{id}/edit` → `SellController@edit` | form PT-03 | ⚪ |
| BL-sell-shipments | Expedições / remessas | `sell/shipments.blade.php` (11.1 KB) | `/shipments` → `SellController@shipments` | lista PT-01 | ⚪ |
| BL-sell-viewmedia | Anexos da venda (modal) | `sell/view_media.blade.php` | fragmento de `sell/index` | drawer/modal PT-04 | ⚪ |
| BL-sells-transcript | Transcrição da venda | `sells/transcript.blade.php` (10.7 KB) | `/sells/{sale}/transcript.pdf` → `SellTranscriptPdfController@show` | saída/PDF | ⚪ |

## B. PDV (`sale_pos/`) — SellPosController

| ID | Tela | Blade | Rota / controller | Arquétipo | Frescor |
|---|---|---|---|---|---|
| BL-pos-create | **PDV — venda no balcão** | `sale_pos/create.blade.php` | `/pos/create` → `Route::resource('pos', SellPosController)` | form/POS | 🔵 tela viva (`Sells/Create.tsx`) — não pode mudar |
| BL-pos-edit | PDV — editar venda | `sale_pos/edit.blade.php` | `/pos/{id}/edit` · pagamento em `/pos/payment/{id}` (`edit-pos-payment`) | form/POS | ⚪ |
| BL-pos-index | PDV — lista | `sale_pos/index.blade.php` | `/pos` → `SellPosController@index` | lista PT-01 | ⚪ |
| BL-pos-show | Recibo / detalhe da venda | `sale_pos/show.blade.php` (20.3 KB) | `SellPosController@show` · público `/invoice/{token}`, `/quote/{token}` | detalhe PT-07 | ⚪ |
| BL-pos-draft | Rascunhos | `sale_pos/draft.blade.php` | `/sells/drafts` → `SellController@getDrafts` · dt `/sells/draft-dt` | lista PT-01 | ⚪ |
| BL-pos-quotations | Orçamentos (quotations) | `sale_pos/quotations.blade.php` | `/sells/quotations` → `SellController@getQuotations` · copiar `/sells/copy-quotation/{id}` | lista PT-01 | ⚪ |
| BL-pos-subscriptions | Assinaturas / faturas recorrentes | `sale_pos/subscriptions.blade.php` | `/sells/subscriptions` → `SellPosController@listSubscriptions` · toggle `/toggle-subscription/{id}` | lista PT-01 | ⚪ |
| BL-pos-pay-public | Pagamento público do link | — (`invoicePayment`/`confirmPayment`) | `/pay/{token}`, `POST /confirm-payment/{id}` | form público | ⚪ |

**Fragmentos (dependências, não telas):** `sale_pos/product_row.blade.php` (18.3 KB — a linha de item: preço, desconto, m², impostos), `sale_pos/partials/*` (41 arquivos), `sale_pos/receipts/*` (10). **Excluídos:** `create_old`, `edit_old`.

## C. Pedidos de venda (`sales_order/`) — SalesOrderController

| ID | Tela | Blade | Rota / controller | Arquétipo |
|---|---|---|---|---|
| BL-so-index | Lista de pedidos | `sales_order/index.blade.php` | `/sales-order` → `resource(...)->only(['index'])` · linhas `get-sales-order-lines` (`SellPosController`) | lista PT-01 |
| BL-so-status | Mudar status do pedido | `sales_order/edit_status_modal.blade.php` | `GET edit-sales-orders/{id}/status` · `PUT update-sales-orders/{id}/status` | modal PT-04 |

## D. Devoluções (`sell_return/`) — SellReturnController

| ID | Tela | Blade | Rota / controller | Arquétipo |
|---|---|---|---|---|
| BL-sr-index | Lista de devoluções | `sell_return/index.blade.php` | `/sell-return` → `resource('sell-return', SellReturnController)` | lista PT-01 |
| BL-sr-add | Lançar devolução da venda | `sell_return/add.blade.php` (8.6 KB) | `/sell-return/add/{id}` · linha `sell-return/get-product-row` | form PT-03 |
| BL-sr-show | Detalhe da devolução | `sell_return/show.blade.php` | `SellReturnController@show` | detalhe |
| BL-sr-receipt | Recibo de devolução | `sell_return/receipt.blade.php` (10.7 KB) | `/sell-return/print/{id}` → `printInvoice` | saída/impressão |
| BL-sr-tmpcreate | Criar devolução (variante) | `sell_return/tmp_create.blade.php` | `SellReturnController@create` | form PT-03 |

## E. Anexos do lote (dependem de Vendas — confirmar se entram no L1)

| ID | Tela | Blade | Rota / controller |
|---|---|---|---|
| BL-imps-index | Importar vendas | `import_sales/index.blade.php` | `/import-sales` → `ImportSalesController@index` |
| BL-imps-preview | Prévia da importação | `import_sales/preview.blade.php` | `POST /import-sales/preview` |
| BL-cr-index | Registros de caixa | `cash_register/index.blade.php` | `/cash-register` → `resource(..., CashRegisterController)` — **coexiste** com o Blade legado por decisão [W] 2026-05-25 |
| BL-cr-create | Abrir caixa | `cash_register/create.blade.php` | `CashRegisterController@create` |
| BL-cr-close | Fechar caixa | `cash_register/close_register_modal.blade.php` | `/cash-register/close-register/{id?}` + `POST close-register` |
| BL-cr-details | Detalhe do caixa | `cash_register/register_details.blade.php` · `payment_details` · `register_product_details` | `getRegisterDetails` |

---

## Contagem do lote
**24 telas** (A6 + B8 + C2 + D5 + fragmentos fora) + **6 anexos** (E) = 30 itens. Fragmentos/partials contados separado: ~52 arquivos de apoio.

## Ordem de ataque proposta (E2 → E4)
1. **BL-pos-create** (PDV) — a tela do dinheiro, persona Larissa. É 🔵: a Ficha documenta o vivo.
2. **BL-sell-index** — índice; o golden PT-01 já existe, valida o formato da Ficha rápido.
3. **BL-sell-create** — a maior (46.9 KB); depois das duas primeiras, com o formato já calibrado.
4. **BL-pos-quotations + BL-pos-draft** — mesmo arquétipo, saem em par.
5. **BL-sr-add + BL-sr-index** — devolução fecha o ciclo de venda.

## Próximo passo
Eu leio `sale_pos/create.blade.php` + `sale_pos/product_row.blade.php` no `main` e escrevo a **Ficha BL-pos-create** completa (dados, eventos, funções, estados, UC, testes Dado/Quando/Então + borda/validação por campo + permissões por papel). Você corrige. Diga só "vai" — ou troque a tela de partida.
