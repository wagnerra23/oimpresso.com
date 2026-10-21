repo: wagnerra23/oimpresso.com
branch: main
path: prototipo-ui/cowork/Wagner

## Last sync
date: 2026-10-15T13:06:09Z
### Updated in this project
- Módulo Produção (comunicação visual) novo, padrão "OS como projeto": painel, quadro, linha do tempo, custo real × orçado, instalação, apontamento, roteiros
- Desenhado contra a grade de 22 funções × 10 concorrentes do main (33-grade-producao-concorrentes.md) e o SPEC/ADR-PROPOSAL do Pcp

## Screen map
| tela (rota) | arquivos do repo |
|---|---|
| Reservas (`cfg-reservas`) | resources/views/restaurant/booking/create.blade.php · show.blade.php · app/Http/Controllers/Restaurant/BookingController.php |
| Cozinha (`cfg-cozinha`) | resources/views/restaurant/kitchen/index.blade.php · partials/show_orders.blade.php · Restaurant/KitchenController.php |
| Pedidos do restaurante (`cfg-pedidos-rest`) | resources/views/restaurant/orders/index.blade.php · partials/line_orders.blade.php · Restaurant/OrderController.php |
| Voz do Cliente (`voz`) | Modules/VozDoCliente/Routes/web.php · Resources/views/caixa.blade.php |
| Planilhas (`planilhas`) | Modules/Spreadsheet/Routes/web.php · Http/Controllers/SpreadsheetController.php |
| Orçamentos (`venda-cotacoes`) | resources/js/Pages/Sells/Quotations.tsx · app/Http/Controllers/SellPosController.php |
| Máquinas Cadastradas (`oi-log`) | Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Logs/Index.tsx · _components/MaquinasTable.tsx · Http/Controllers/LicencaLogController.php |
| Produto · Cadastros (`prod-cadastros`) | resources/js/Pages/Produto/Cadastros/_components/CadastroDrawer.tsx |
| Portal do contato (`crm-portal-*`) | Modules/Crm/Routes/web.php (grupo contact) · Http/Controllers/{Dashboard,ManageProfile,Purchase,Sell,Ledger,ContactBooking,OrderRequest}Controller.php |
| Repair · Portal público (`rep-portal`) | Modules/ConsultaOs/Routes/web.php · Http/Controllers/ConsultaOsController.php · resources/js/Pages/ConsultaOs/Index.tsx · _components/OsResultCard.tsx |
| Produção CV (`pcv`, `pcv-*`) | memory/research/2026-05-prospeccao/33-grade-producao-concorrentes.md · memory/requisitos/Pcp/SPEC.md · ADR-PROPOSAL.md · ROADMAP.md |
| Módulos (`modulos`) | resources/js/Pages/Modules/Index.tsx · app/Services/ModuleManagerService.php |

## Sync history
- 2026-10-15T11:39:32Z — acompanhamento de OS por link; portal do contato (Assistência); casos e testes
- 2026-10-15T11:22:17Z — portal do contato em casca própria; lote 3 do CRM; decisões 15/10
- 2026-10-15T10:47:00Z — oi-log máquinas; Produto/Cadastros drawer; erratas 14/10
- 2026-10-07T21:50:11Z — Restaurante (P1); levantamento do menu em threads; recibos/erratas de 07/10
- 2026-10-07T12:32:12Z — decisões [W] (24 `_DECISOES-W-*`); telas soltas em produção; Patrimônio 05/05a; Orçamentos rota única
