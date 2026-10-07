---
thread: "00"
titulo: Mapa Blade ↔ protótipo das 21 telas (cfg-*, usuarios/funcoes/comissionados, rel-*)
dono: "[CC]"
base: wagnerra23/oimpresso.com@main 836619f64d (lido 2026-10-07)
veredito: "entregue — 21 de 21 telas mapeadas; 3 já são Inertia (Comissionados, Apuração de comissão, Assinatura); 2 sem tela Blade própria (Atendentes) ou fora do menu do protótipo (Contas)."
---

# _saida-00 · Sistema — mapa Blade ↔ protótipo

**Como foi medido (máquina, não olho):**
- Menu do protótipo: `data.jsx` (grupos `usuarios`, `cfg-empresa`, `relatorios` e seus `ghosts`) + roteamento em `app.jsx`. Dá **21** rotas: Usuários 4 · Configurações 12 · Relatórios 5.
- Menu Blade: `app/Http/Middleware/AdminSidebarMenu.php` (bloco `business.settings`) + `return view(…)` / `Inertia::render(…)` de cada controller no `main`.
- Relatórios: script cruzando os `blade: "report/…"` de `relatorios-data.jsx` com `git ls-files resources/views/report` → **27 views de topo, o protótipo cita 26**; as 2 não citadas (`stock_report`, `stock_details`) são cobertas pelo partial `partials/stock_report_table` e pelo drill-down AJAX. Nenhuma view citada inexiste.
- Âncora: `node scripts/design/ancora.mjs <Tela>` nas 3 Pages que já existem. Cobertura: `screen-coverage:report` (Comissionados 1 tela · 0 E2E · 0 scorecard; Report 1 · 0 · 0; User/Perfil 1 · 0 · 1).

## Usuários (4)

| rota protótipo | arquivo protótipo | Blade/Page no `main` | o que falta no protótipo |
|---|---|---|---|
| `usuarios` | `usuarios-page.jsx` | `manage_user/{index,create,edit,show}` · `ManageUserController@index` | nada medido; comparação campo a campo é da thread 01 |
| `funcoes` | `funcoes-page.jsx` + `funcoes-perms.jsx` | `role/{index,create,edit}` · `RoleController@index` | nada medido; thread 02 |
| `comissionados` | `comissionados-page.jsx` | **já Inertia**: `Comissionados/Index` (#8817) · âncora ✓ `comissionados-page.jsx` | — (charter declara as divergências) |
| `comissoes` | `comissoes-page.jsx` (tela nova, sem Blade) | **já Inertia**: `Report/SalesRepresentative/Index` (#8877) · âncora ✓ `comissoes-page.jsx`; no vivo fica em Relatórios | — (charter declara: sem fechamento/regra por agente) |

## Configurações (12)

| rota protótipo | arquivo protótipo | Blade no `main` | o que falta no protótipo |
|---|---|---|---|
| `cfg-empresa` | `configuracoes-page.jsx` (abas do `settings.blade`) | `business/settings` · `BusinessController@getBusinessSettings` | fora das threads 01-07 (índice §4) |
| `cfg-locais` | `configuracoes-cadastros.jsx` | `business_location/{index,create,edit}` | nada medido; thread 04 |
| `cfg-fatura` | `configuracoes-cadastros.jsx` + `configuracoes-fatura.jsx` | `invoice_scheme/*` + `invoice_layout/{create,edit}` | nada medido; thread 05 |
| `cfg-barras` | `configuracoes-cadastros.jsx` | `barcode/{index,create,edit}` | nada medido; thread 04 |
| `notificacoes` | `notificacoes-page.jsx` | `notification_template/index` (rota só `index`+`store`) | nada medido; thread 06 |
| `cfg-impressoras` | `configuracoes-cadastros.jsx` | `printer/{index,create,edit}` | nada medido; thread 04 |
| `cfg-impostos` | `configuracoes-cadastros.jsx` | `tax_rate/*` + `tax_group` | nada medido; thread 05 |
| `cfg-modificadores` | `configuracoes-cadastros.jsx` | `restaurant/modifier_sets/*` · `Restaurant\ModifierSetsController` | **sem thread no índice** |
| `cfg-servicos` | `configuracoes-cadastros.jsx` | `types_of_service/*` | nada medido; thread 05 |
| `cfg-mesas` | `integra-extras.jsx` (`RestauranteExtrasPage`) | `restaurant/table/*` · `Restaurant\TableController` | **sem thread no índice** |
| `cfg-atendentes` | `integra-extras.jsx` | **não há tela Blade própria** — atendente é usuário com função de serviço; o vivo só tem o modal `sale_pos/partials/service_staff_availability_modal` | o protótipo inventa a tela; decidir se fica |
| `cfg-pacote` | `configuracoes-cadastros.jsx` | **já Inertia**: `/subscription` → `superadmin/MinhaAssinatura/Index` · `related_prototype: n/a (herda PT-01)` | fora das threads (índice §4) |

## Relatórios (5)

| rota protótipo | arquivo protótipo | Blade no `main` | o que falta no protótipo |
|---|---|---|---|
| `relatorios` (hub) | `relatorios-page.jsx` + `relatorios-data.jsx` | menu Relatórios do Blade; `ReportController` com 30 `return view('report.*')` | nenhuma view de topo ausente (26/27; a 27ª é partial) |
| `rel-financeiro` | idem, grupo Financeiro | `profit_loss`, `purchase_sell`, `sell_payment_report`, `purchase_payment_report`, `expense_report`, `register_report` | — |
| `rel-comercial` | idem, grupo Comercial | `contact`, `customer_group`, `sale_report`, `purchase_report`, `sales_representative`, `service_staff_report` | — |
| `rel-estoque` | idem, grupo Estoque | `stock_report`, `product_stock_details`, `lot_report`, `stock_expiry_report`, `stock_adjustment_report`, `product_purchase_report`, `product_sell_report`, `items_report`, `trending_products` | — |
| `rel-fiscal` | idem, grupo Fiscal | `tax_report` | — |

Fora dos 4 grupos de menu, o protótipo declara: `activity_log` (grupo Sistema), 4 relatórios **novos** sem Blade (grupo Gráfica — pendentes de [W]) e 3 Blade **fora de escopo** com motivo (`gst_sales_report`, `gst_purchase_report`, `table_report`).

## Achados para o índice (não editei o índice — é do Cowork)
1. **Thread 03 já entregue** antes desta 00: `Comissionados/Index` em React no #8817 (`SalesCommissionAgentController:57` → `Inertia::render`). A prova dela já passa; falta o `_saida-03`.
2. **Contas** (`AccountController@index` → `account/index`, alvo da thread 06) **não está entre as 21 do menu Sistema** do protótipo: o desenho dela é `fin-bancos` em `financeiro-legado.jsx`. A thread 06 deve ancorar lá.
3. **Modificadores e Mesas** têm Blade e protótipo mas **nenhuma thread**. **Atendentes** existe só no protótipo.
4. O §1 do índice diz "Zero `Inertia::render` de … Relatório"; no `main` de hoje há `Report/SalesRepresentative/Index` (#8877).

## Não fiz
- Não comparei campo a campo nenhuma tela — "nada medido" acima quer dizer isso, não "igual". É o trabalho de cada thread.
- Não toquei `app/` nem `resources/` (`nao_toca` da thread).
