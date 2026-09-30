# 00–07 · Vendas — telas vivas (puxar) e 7 telas legadas (Blade → Page)

> Emitida pelo [CC] em 2026-09-29. Base: `main` a71c2f2d052f. **Rotas conferidas em `routes/web.php` neste turno** (regra da thread 28 do Ponto: nome de rota vem do router, não da memória).

## Rotas reais (medidas)
| tela | rota de produção | controller | linha |
|---|---|---|---|
| Lista de POS | `GET /pos` (resource) | `SellPosController@index` | web.php:880 |
| Remessas | `GET /shipments` | `SellController@shipments` | web.php:1113 |
| Devolução | `/sell-return` (resource) · `GET /sell-return/add/{id}` | `SellReturnController` | web.php:1021 · :1024 |
| Descontos | `/discount` (resource) | `DiscountController` | web.php:1058 |
| Importação | `GET /import-sales` · `POST /import-sales/preview` · `POST /import-sales` | `ImportSalesController` | web.php:867–869 |
| Pedido de venda | `GET /sales-order` (só index) + status em `edit-sales-orders/{id}/status` | `SalesOrderController` | web.php:1137–1141 |
| Caixa do dia | `GET /vendas/caixa` → `Sells/Caixa/Index` (já React) | `SellController@inertiaCaixa` | web.php:794 |

## 00 · PUXAR (dono [CC])
Para cada Page viva (`Sells/{Index,Create,CreateV3,Show,Edit,Drafts,Quotations,Subscriptions,Caixa/Index}`): ler o `.tsx` + charter, diff nos dois sentidos contra o símbolo do protótipo (`venda-*.jsx`). O que o vivo tem e o protótipo não → **entra no build**. O que o protótipo tem e o vivo não → vira pedido **só** se for comportamento. Saída: `_saida-00.md` por tela.

## A1 · A2 · ALVO em lote (read-only)
Medir as rotas do protótipo `venda-pos · venda-remessas · venda-devolucoes · venda-descontos` (A1) e `venda-importar · venda-pedidos · venda-caixa` (A2). Mesmo rito da `_saida-32` do Ponto: `alvo:mapa` → `.secoes.json` → `alvo:medir` 2× byte-idêntico, dark, após `__oiLazyDone`. Slugs `vendas--<tela>--index`.

## 01–07 · Blade → Page (1 thread = 1 tela)
Para cada uma: `criar-tela.mjs` gera tsx+charter+casos+contrato juntos; **substituir pelos textos já revisados desta pasta** (`ListaPos · Remessas · Devolucao · Descontos · Importacao · PedidoVenda · Caixa` `.charter.md`/`.casos.md`); controller troca `view(...)` por `Inertia::render`; payload traz os totais que hoje o DataTable soma no cliente (01).
- **02:** o modal de status da remessa vira **drawer PT-02** (proibido modal full-screen para detalhe).
- **03:** o `Sells/Index` vivo já linka `/sell-return/add/{id}` (charter v7) — o link não muda.
- **04:** junto, FormRequest (achado A2) e permissão da view `brand.*` → `discount.access` (achado A1). Ver × editar depende de **D1**.
- **05:** preview antes do import; fila e reversão dependem de **D2/D3**. Tamanho: 2 PRs (backend → tela).
- **06:** só aparece com `enable_sales_order` ligado; status continua pelos endpoints `edit-/update-sales-orders/{id}/status`.
- **07:** fecha o pendente "Onda 6+1" que `Sells/Caixa/Index.tsx` declara em tela (movimentos + conferência física). Depende da 00 ter puxado o Caixa vivo.

## PARAR SE
- a rota exigir endpoint novo no `main` → parar; rota de produção é [W].
- o PR passar de 300 linhas → partir em backend → tela.
- a tela tocar valor (Caixa, Descontos, Importação) sem teste que prove o mesmo total antes e depois → parar (proibicoes.md VALOR).
