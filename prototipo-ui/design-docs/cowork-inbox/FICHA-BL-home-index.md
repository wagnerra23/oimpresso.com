# FICHA — BL-home-index · Dashboard legado (`/dashboard-legacy`)

> **Proposta do [CC] lida do `main` (`6b923a26d904`) neste turno.** Você corrige o que estiver errado; só depois vira charter/contrato.
> **Fontes lidas:** `routes/web.php` (309–317), `app/Http/Controllers/HomeController.php` (index / indexLegacy / getTotals / getProductStockAlert / getPurchasePaymentDues / getSalesPaymentDues / getCalendar), `resources/views/home/index.blade.php` (1.4k linhas / 91.5 KB), `home/partials/*` (8), `public/js/home.js`.
> **Cópia local pra trabalhar:** `resources/views/home/**` + `public/js/home.js` já estão neste projeto (só leitura de apoio — o canon é o `main`).

## ⚠️ Achado que muda a Ficha (confirmar com [W])
`/dashboard-legacy` **não cai no Blade por padrão**. `HomeController@index` decide:
- `?legacy=1` → `indexLegacy()` → **Blade** `home.index` (charts ECharts + widgets pluggable) ← **é esta a tela desta Ficha**
- sem query → `Inertia::render('Home/Index')` (shell React: saudação + 4 KPI + filtro de loja) — F6 Soft, US-DASH-001 (2026-05-21)
- `user_type == 'user_customer'` → redirect pro dashboard do Crm

Logo a URL real da tela legada é **`/dashboard-legacy?legacy=1`** (ou `/home?legacy=1`). Se você quis dizer a **Inertia** `Home/Index`, é outra tela (BL-home-inertia) e a reconstrução é outra conversa.

---

- **Rota / URL:** `/dashboard-legacy?legacy=1` · name `home.legacy`
- **Blade:** `resources/views/home/index.blade.php` (+ 8 partials · + `calendar`, `notification_modal`, `todays_profit_modal`)
- **Controller@método:** `HomeController@index` → `indexLegacy($business_id, $is_admin)`
- **Arquétipo:** dashboard → **PT-05**
- **Persona dona:** Wagner (escritório, 1440px, dashboards). Larissa não usa esta tela.
- **JS legado:** `public/js/home.js` (jQuery + DataTables serverSide + daterangepicker + ECharts)
- **Multi-tenant:** `business_id` de session (ADR 0093, irrevogável)

## Dados que a tela recebe (`compact` do `indexLegacy`)
| campo | origem | formato | obs |
|---|---|---|---|
| `sells_chart_1` | `TransactionUtil::getSellsCurrentFy` → `CommonChart` | série por dia × localização | **últimos 30 dias**; + série "todas as lojas" se >1 loja |
| `sells_chart_2` | mesma query agrupada `yearmonth` | série mês × localização | **ano fiscal corrente** (`getCurrentFinancialYear`) |
| `all_locations` | `BusinessLocation::forDropdown` | `{id: nome}` | dropdown só aparece se `count > 1` |
| `common_settings` | `session('business.common_settings')` | array | liga/desliga blocos: `enable_purchase_order`, `enable_purchase_requisition` |
| `widgets` | `ModuleUtil::getModuleData('dashboard_widget')` | por posição | **hoje 100% comentado no Blade** (4 posições mortas: `after_sale_purchase_totals`, `after_sales_last_30_days`, `after_sales_current_fy`, `after_dashboard_reports`) → decidir: ressuscitar ou aposentar |
| `is_admin` | `BusinessUtil::is_admin` | bool | gate visual: filtro de data e KPIs só pra admin |
| — | `session('business.stock_expiry_alert_days', 30)` | int | default 30 dias |
| — | `config('constants.show_payments_recovered_today')` | bool | libera o bloco Fluxo de caixa |

**Sem `dashboard.data`** → o controller devolve `view('home.index')` **sem nenhuma variável**: a tela renderiza só a casca. Estado de primeira classe, não erro.

## Eventos
| # | gatilho | o que acontece | endpoint | resposta na UI |
|---|---|---|---|---|
| E1 | load | `update_statistics(hoje, hoje)` | `GET /home/get-totals?start&end&location_id` | 8 valores trocam loader ⟳ por moeda formatada |
| E2 | escolher período em `#dashboard_date_filter` (daterangepicker) | reexecuta E1 com o range + recarrega orçamentos | `/home/get-totals` · `quotation_datatable.ajax.reload()` | label vira `dd/mm/aaaa ~ dd/mm/aaaa` |
| E3 | trocar `#dashboard_location` | reexecuta E1 mantendo o range atual | `/home/get-totals` | KPIs recalculam por loja |
| E4 | load das tabelas (DataTables serverSide) | 4 grades independentes | `/home/product-stock-alert` · `/home/purchase-payment-dues` · `/home/sales-payment-dues` · `/reports/stock-expiry` | paginação/processing próprios; **sem busca e sem ordenação** (`searching:false`, `ordering:false`) |
| E5 | trocar filtro local de uma grade (`#stock_alert_location`, `#purchase_payment_dues_location`, `#sales_payment_dues_location`) | `ajax.reload()` só daquela grade | mesmo endpoint | resto da tela não pisca |
| E6 | grades do topo de fluxo | pedidos de venda / ordens de compra / requisições / expedições | `SellController@index?sale_type=sales_order&for_dashboard_sales_order=1` · `PurchaseOrderController@index?from_dashboard=1` · `PurchaseRequisitionController@index?from_dashboard=1` · `SellController@index?only_pending_shipments=1` | 4 DataTables extras |
| E7 | Fluxo de caixa | grade de crédito | `AccountController@cashFlow` (`d.type='credit'`) | só com `account.access` + flag de config |
| E8 | clique no nº da nota / ref | abre **modal de detalhe** (`.btn-modal` → `.view_modal`) | `SellController@show` / `PurchaseController@show` | modal legado (no React vira **drawer PT-02**) |
| E9 | clique em "Adicionar pagamento" numa linha vencida | modal de pagamento | `TransactionPaymentController@addPayment/{id}` | `.add_payment_modal` |
| E10 | excluir linha (requisição) | confirmação → `DELETE href` | ajax `method: 'DELETE'` | linha some, grade recarrega |
| E11 | popover ⓘ em Devolução de venda/compra | monta tooltip com total vs. pago | — (dados já vindos de E1) | `#total_srp` / `#total_prp` `data-content` reescrito |
| E12 | notificação com `show_popup` | modal ao entrar | `/get-total-unread` → `home.notification_modal` | modal por cima do dashboard |

## Funções
| nome | entrada → saída | regra | onde vive hoje |
|---|---|---|---|
| `update_statistics(start, end)` | período + loja → 8 KPIs | pinta loader, chama `/home/get-totals`, formata moeda | `public/js/home.js:170` |
| `getTotals()` | `start`,`end`,`location_id`,`user_id` → json | **NET = total de vendas − faturas a receber − despesas** | `HomeController@getTotals` |
| devolução líquida (front) | `total_sell_return − total_sell_return_paid` | o card mostra o **devido**, não o bruto; o bruto vai no popover | `home.js` success |
| desconto de razão | `invoice_due − total_sell_discount` · `purchase_due − total_purchase_discount` | `getTotalLedgerDiscount` abate antes de exibir | controller |
| `getProductStockAlert()` | locais permitidos → produtos | `stock < alert_quantity`; nome = `produto (sku)` ou `produto - variação (sub_sku)` | controller + `ProductUtil::getProductAlert` |
| dues (compra/venda) | → vencendo | `payment_status != 'paid'` **E** vencimento (`pay_term_number/type`, default 30 dias) **≤ 7 dias** | 2 métodos do controller |
| `permitted_locations()` | usuário → escopo | `'all'` ou lista; filtra toda grade | `User` |
| `__currency_trans_from_en` / `__currency_convert_recursively` | número → `R$ 12.480,00` | formatação de moeda global | `public/js` comum |
| janela do gráfico 1 | FY start − 30 dias | busca 30 dias a mais pra fechar a série | `indexLegacy` |

## Estados da tela
`carregando` (loader ⟳ por KPI + `processing` por grade) · `sem permissão` (casca sem KPI, sem grade) · `vazio` (grade sem linhas — hoje texto padrão do DataTables em inglês) · `erro de ajax` (**hoje falha silenciosa: o loader fica girando pra sempre** — anti-padrão a corrigir no React) · `1 loja` (dropdown some) · `sem PO/requisição habilitados` (blocos não renderizam)

## Casos de uso
- **UC-01** — Wagner abre o dia: vê vendas/líquido/a receber de **hoje** sem clicar em nada.
- **UC-02** — Wagner compara: escolhe "mês passado" e uma loja → 8 KPIs recalculam.
- **UC-03** — Eliana caça inadimplência: lê "Vencimentos de venda" (≤7 dias) e lança pagamento pelo modal sem sair da tela.
- **UC-04** — Compras: vê contas a pagar vencendo e ordens/requisições abertas em pé de igualdade.
- **UC-05** — Estoque: vê itens abaixo do mínimo e lotes a vencer (janela `stock_expiry_alert_days`).
- **UC-06** — Vendedor sem `dashboard.data` entra e vê a tela vazia sem 403 (decisão registrada no comentário da rota).
- **UC-07** — Expedição: vê só as remessas pendentes (`only_pending_shipments`).

## Testes — Dado / Quando / Então
- **T-01** — Dado admin com vendas hoje, Quando abre a tela, Então os 8 KPIs saem do loader com valor em `R$` e NET = vendas − a receber − despesas.
- **T-02** — Dado 3 lojas, Quando troca a loja mantendo o período, Então os KPIs mudam **e** o período **não** volta pra hoje.
- **T-03** — Dado usuário sem `dashboard.data`, Quando abre, Então **200** (não 403) e nenhum KPI/grade aparece.
- **T-04** — Dado `permitted_locations = [2]`, Quando as grades carregam, Então nenhuma linha da loja 1 aparece (teste de vazamento multi-tenant).
- **T-05** — Dado venda com `pay_term_number = 10 dias` e 3 dias corridos, Quando abre, Então **não** aparece em vencimentos (>7 dias); com 25 dias corridos, aparece.
- **T-06** — Dado venda `payment_status = 'paid'`, Quando abre, Então nunca aparece em vencimentos.
- **T-07** — Dado devolução de venda de 1.000 com 400 pagos, Quando abre, Então o card mostra 600 e o popover mostra 1.000 / 400.
- **T-08** — Dado 1 loja só, Quando abre, Então o dropdown de loja não é renderizado (e a chamada vai sem `location_id`).
- **T-09** — Dado `/home/get-totals` responde 500, Quando abre, Então a UI mostra erro (**hoje: loader infinito — teste que hoje falha, e é de propósito**).
- **T-10** — Dado `enable_purchase_order` desligado, Quando abre, Então o bloco de ordens de compra não existe no DOM.

## Validação por campo
| campo | regra | erro | borda |
|---|---|---|---|
| período (`start`/`end`) | `Y-m-d`, `start ≤ end`, default hoje~hoje | período inválido → não dispara | ano fiscal virando no meio do range; fuso do servidor |
| `location_id` | id de loja do business, ou vazio = todas | id de outro business → **deve** 403/vazio, hoje só não filtra | usuário com 1 loja permitida e dropdown com 3 |
| `user_id` (aceito por `getTotals`) | só admin pode filtrar por outro usuário | — | **hoje não há gate no método** → risco: não-admin passando `user_id` |
| `stock_expiry_alert_days` | int ≥ 0, default 30 | — | 0 dias = só o que já venceu |
| valores monetários | 2 casas, `R$ 1.234,56` | — | negativo (NET negativo deve aparecer em tom `danger`) |

## Permissões por papel
| papel | vê | pode | não pode |
|---|---|---|---|
| Admin do business | tudo (KPIs, filtro de data, todas as grades) | filtrar por loja/usuário, lançar pagamento | — |
| `dashboard.data` sem admin | KPIs e grades permitidas; **sem** filtro de data (`@if($is_admin)`) | — | filtrar período |
| `sell.view` / `direct_sell.view` | gráficos de venda + vencimentos de venda | — | grades de compra |
| `purchase.view` | vencimentos de compra | `purchase.create` → lançar pagamento | — |
| `stock_report.view` | estoque mínimo + lotes a vencer | — | — |
| `so.view_all` / `so.view_own` | pedidos de venda (todos vs. próprios) | — | — |
| `access_shipping` / `access_own_shipping` / `access_pending_shipments_only` | expedições | — | — |
| `account.access` (+ flag de config) | fluxo de caixa | — | — |
| sem `dashboard.data` | casca vazia | — | qualquer dado |
| `user_type = user_customer` | — | — | redirecionado pro Crm |

## Saídas
`no-print` nos ⓘ (a tela é imprimível hoje) · modais de detalhe venda/compra · modal de pagamento · modal de notificação · `todays_profit_modal` (lucro do dia) · `home/calendar` (agenda, rota `/calendar`, eventos vindos dos módulos)

## Anti-padrões desta tela (não repetir no React)
1. **Loader infinito** em erro de ajax (T-09).
2. **Grades sem busca nem ordenação** (`searching:false`, `ordering:false`) — no React use `DataTablePro`.
3. **HTML montado no controller** (`<span class="display_currency">`, `<a class="btn-modal">`) — formatação e ação são da view.
4. **Modal para detalhe** — o canon é **drawer PT-02** (proibição do CLAUDE.md).
5. **Widgets pluggable comentados** — código morto no Blade; decidir antes de portar.
6. **`getTotals` sem gate de `user_id`** — corrigir no port, não copiar.
7. **9 endpoints separados** pra uma tela — o React deve consolidar (1 payload de KPIs + grades por demanda).
8. `rounded-xl+`, azul cru e Title Case do Blade legado: tudo sai; tokens do DS e sentence case entram.

## Mapa pra reconstrução React (proposta)
`PageHeader` (título + `PeriodBar` + select de loja) → faixa de **8 `KpiCard`** (hero em NET) → 2 `Chart` (30 dias / ano fiscal) → grid de `DataTablePro`: vencimentos de venda · vencimentos de compra · estoque mínimo · lotes a vencer · pedidos de venda · ordens de compra · requisições · expedições · fluxo de caixa. Detalhe = `Drawer`. Pagamento = `Modal` (PT-04). Blocos por permissão = seções que **não renderizam**, nunca desabilitadas.

## O que mais deve entrar nesta Ficha (e você decide)
1. **Ranking de importância dos KPIs** — 8 cards é muito pro roxo/hero; qual é o número que o Wagner olha primeiro?
2. **Cortar ou manter** cada uma das 9 grades. Minha proposta: manter 4 no topo (vencimentos venda/compra, estoque mínimo, expedições) e mandar o resto pro "ver mais".
3. **Widgets de módulo** — ressuscitar a extensão ou aposentar de vez?
4. **Período default** — hoje é "hoje"; para dono de gráfica "mês corrente" costuma servir melhor.
5. **i18n** — a tela mistura `lang_v1` inglês com PT; a reconstrução fixa PT-BR sentence case.
6. **Meta/comparativo** — nenhum KPI tem delta nem meta hoje. Entra (`+12%` vs. período anterior) ou fica fora do escopo?
