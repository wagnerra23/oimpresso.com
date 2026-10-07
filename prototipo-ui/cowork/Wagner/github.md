repo: wagnerra23/oimpresso.com
branch: main
path: prototipo-ui/cowork/Wagner

## Last sync
date: 2026-10-07T21:50:11Z
### Updated in this project
- Restaurante: Reservas, Cozinha e Pedidos no protótipo (P1), lidos das Blades `restaurant/*`
- Levantamento do menu (Blade × React) virou threads: cutover-menu, Compras, CRM lote 2, Despesas, Sistema, Officeimpresso, Superadmin, Woocommerce
- Recibos e erratas do Code de 07/10 aplicados nos índices; decisões [W] gravadas

## Screen map
| tela (rota) | arquivos do repo |
|---|---|
| Reservas (`cfg-reservas`) | resources/views/restaurant/booking/create.blade.php · show.blade.php · app/Http/Controllers/Restaurant/BookingController.php |
| Cozinha (`cfg-cozinha`) | resources/views/restaurant/kitchen/index.blade.php · partials/show_orders.blade.php · Restaurant/KitchenController.php |
| Pedidos do restaurante (`cfg-pedidos-rest`) | resources/views/restaurant/orders/index.blade.php · partials/line_orders.blade.php · Restaurant/OrderController.php |
| Voz do Cliente (`voz`) | Modules/VozDoCliente/Routes/web.php · Resources/views/caixa.blade.php |
| Planilhas (`planilhas`) | Modules/Spreadsheet/Routes/web.php · Http/Controllers/SpreadsheetController.php |
| Orçamentos (`venda-cotacoes`) | resources/js/Pages/Sells/Quotations.tsx · app/Http/Controllers/SellPosController.php |
| Módulos (`modulos`) | resources/js/Pages/Modules/Index.tsx · app/Services/ModuleManagerService.php |

## Sync history
- 2026-10-07T12:32:12Z — decisões [W] (24 `_DECISOES-W-*`); telas soltas em produção; Patrimônio 05/05a; Orçamentos rota única
