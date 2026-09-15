# Product/Importacao + AtualizarPreco — charter (F1 [CC], 2026-08-21)

**Norte.** Importação é a operação mais destrutiva do catálogo: uma coluna fora de ordem cria 400 produtos errados. A tela tem uma obrigação antes de qualquer estética — **dizer o que vai acontecer antes de acontecer**.

**Origem.** `import_products/index.blade.php`, `import_opening_stock/index.blade.php`, `selling_price_group/update_product_price.blade.php`. Rotas: `prod-importar`, `prod-importar-estoque`, `prod-atualizar-preco`.

## Regras
- **R1** Ordem das colunas é contrato: 37 na importação de produtos, 6 na de estoque inicial. A primeira linha é cabeçalho e é ignorada.
- **R2** Arquivo escolhido é **lido no navegador** quando é CSV: contagem de colunas por linha + obrigatórios em branco, com número da linha do erro.
- **R3** Com qualquer erro o botão **Enviar planilha** fica desabilitado. Não existe importação parcial — e a tela diz isso antes, não depois.
- **R4** .xls/.xlsx não é lido no navegador. A tela avisa que a conferência é do servidor e sugere salvar como .csv pra conferir antes.
- **R5** Sem erro, a tela mostra as primeiras 5 linhas com as 7 primeiras colunas — o operador confere se o arquivo é o que ele pensa que é.
- **R6** A conferência do navegador **não substitui** a do servidor: unidade, imposto e local existentes só o backend valida. A tela nomeia essa divisão.
- **R7** Atualizar preço é ciclo de 3 passos na ordem visível: exportar → editar → devolver. Sem exportar antes, não há planilha válida.
- **R8** Grupo de preço desativado não sai na exportação e não é atualizado — a tela linka pro cadastro pra ativar antes.
- **R9** Vazio ≠ zero na planilha de preço: vazio usa o preço padrão, zero é preço zero. Está escrito na instrução.

## Non-goals
- Não converte arquivo (xlsx→csv), não corrige planilha, não faz de-para de coluna.
- Não importa venda, cliente nem fornecedor (outros módulos, outros contratos).
- Não faz agendamento nem importação recorrente.

## Achados do legado
- **A1** `import_products` não tem preview nenhum: você envia e descobre. O `import_sales` do mesmo sistema **tem** `preview.blade.php` — inconsistência do legado que esta tela corrige do lado do cliente.
- **A2** Não existe relatório pós-importação (criados × atualizados × recusados). Proposta nova, precisa de [W].
- **A3** `update_product_price` só oferece exportar/importar; a coluna por grupo depende de `SellingPriceGroupController@export` — a exportação real é do backend.
