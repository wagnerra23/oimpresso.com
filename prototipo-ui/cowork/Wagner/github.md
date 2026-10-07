repo: wagnerra23/oimpresso.com
branch: main
path: prototipo-ui/cowork/Wagner

## Last sync
date: 2026-10-07T12:32:12Z
### Updated in this project
- Decisões [W] do main (24 `_DECISOES-W-*`) aplicadas nos índices, na ordem das rodadas
- Telas soltas entram em produção: aviso de exploração removido; lista da Voz do Cliente e colunas da OS corrigidas
- Patrimônio 05 (retenção LGPD) reaberta, com a 05a antes
- Orçamentos: rota única `venda-cotacoes`; conversão de cotação em venda liberada

## Screen map
| tela (rota) | arquivos do repo |
|---|---|
| Voz do Cliente (`voz`) | Modules/VozDoCliente/Routes/web.php · Resources/views/caixa.blade.php |
| Planilhas (`planilhas`) | Modules/Spreadsheet/Routes/web.php · Http/Controllers/SpreadsheetController.php |
| Orçamentos (`venda-cotacoes`) | resources/js/Pages/Sells/Quotations.tsx · app/Http/Controllers/SellPosController.php |
| Módulos (`modulos`) | resources/js/Pages/Modules/Index.tsx · app/Services/ModuleManagerService.php |
