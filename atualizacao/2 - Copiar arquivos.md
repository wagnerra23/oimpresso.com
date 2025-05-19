# Copiar novos arquivos

Este documento lista as ações necessárias para atualizar os arquivos do projeto oimpresso.com. A tabela abaixo detalha os arquivos/diretórios, ações, caminhos de destino e resumo das atualizações.

## Tabela de Ações


| Arquivo/Diretório                     | Ação      | Caminho de Destino                      | Resumo da Atualização                                    |
| -------------------------------------- | ----------- | --------------------------------------- | ---------------------------------------------------------- |
| tailwind.config.js                     | Copiar      | ./                                      | Copiar o arquivo de configuração do Tailwind.            |
| modules\_statuses.json                 | Copiar      | ./                                      | Verificar se há novos módulos a serem liberados.         |
| vite.config.js                         | Verificar   | ./                                      | Confirmar que apenas app.css e main.js são incluídos.    |
| resources/css/app.css                  | Copiar      | resources/css/app.css                   | Copiar o arquivo de estilos principal.                     |
| resources/js/main.js                   | Copiar      | resources/js/main.js                    | Copiar o arquivo JavaScript principal.                     |
| resources/js/core/devextreme-config.js | Verificar   | resources/js/core/devextreme-config.js  | Confirmar a configuração do DevExtreme.                  |
| resources/js/components/DataGrid.vue   | Copiar      | resources/js/components/DataGrid.vue    | Copiar o componente Vue para DataGrid.                     |
| public/js/common.js                    | Copiar para | resources/js/common.js                  | Mover para resources/js/common.js para gerenciar com Vite. |
| public/js/documents\_and\_note.js      | Copiar      | resources/js/documents\_and\_note.js    | Copiar o arquivo JavaScript.                               |
| public/js/functions.js                 | Copiar      | resources/js/functions.js               | Copiar o arquivo de funções JavaScript.                  |
| public/js/help-tour.js                 | Copiar      | resources/js/help-tour.js               | Copiar o arquivo de tour de ajuda.                         |
| public/js/home.js                      | Copiar      | resources/js/home.js                    | Copiar o arquivo JavaScript da página inicial.            |
| public/js/labels.js                    | Copiar      | resources/js/labels.js                  | Copiar o arquivo de rótulos JavaScript.                   |
| public/js/login.js                     | Copiar      | resources/js/login.js                   | Copiar o arquivo JavaScript de login.                      |
| public/js/opening\_stock.js            | Copiar      | resources/js/opening\_stock.js          | Copiar o arquivo de estoque inicial.                       |
| public/js/payment.js                   | Copiar      | resources/js/payment.js                 | Copiar o arquivo de pagamentos.                            |
| public/js/pos.js                       | Copiar      | resources/js/pos.js                     | Copiar o arquivo do ponto de venda.                        |
| public/js/printer.js                   | Copiar      | resources/js/printer.js                 | Copiar o arquivo de impressão.                            |
| public/js/product.js                   | Copiar      | resources/js/product.js                 | Copiar o arquivo de produtos.                              |
| public/js/purchase.js                  | Copiar      | resources/js/purchase.js                | Copiar o arquivo de compras.                               |
| public/js/purchase\_return.js          | Copiar      | resources/js/purchase\_return.js        | Copiar o arquivo de devolução de compras.                |
| public/js/report.js                    | Copiar      | resources/js/report.js                  | Copiar o arquivo de relatórios.                           |
| public/js/restaurant.js                | Copiar      | resources/js/restaurant.js              | Copiar o arquivo de restaurante.                           |
| public/js/sell\_return.js              | Copiar      | resources/js/sell\_return.js            | Copiar o arquivo de devolução de vendas.                 |
| public/js/stock\_adjustment.js         | Copiar      | resources/js/stock\_adjustment.js       | Copiar o arquivo de ajuste de estoque.                     |
| public/js/stock\_transfer.js           | Copiar      | resources/js/stock\_transfer.js         | Copiar o arquivo de transferência de estoque.             |
| public/js/icons/default/icons.js       | Copiar      | resources/js/icons/default/icons.js     | Copiar o arquivo de ícones JavaScript.                    |
| public/js/icons/default/icons.min.js   | Copiar      | resources/js/icons/default/icons.min.js | Copiar o arquivo minificado de ícones.                    |
| public/js/icons/default/index.js       | Copiar      | resources/js/icons/default/index.js     | Copiar o arquivo de índice de ícones.                    |
| public/js/calculator.js                | Copiar      | resources/js/calculator.js              | Copiar o arquivo de calculadora.                           |
| init.js                                | Apagar      | -                                       | Remover o arquivo init.js.                                 |
| vendor.js                              | Apagar      | -                                       | Remover o arquivo vendor.js.                               |
| init.js.LICENSE.txt                    | Apagar      | -                                       | Remover o arquivo de licença init.js.LICENSE.txt.         |
