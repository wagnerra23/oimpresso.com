# App — Movimentações de um item (tela 29) — só leitura

> Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6).

`GET /api/app/estoque/{id}?pagina=N` (`id` = a linha da 05) →
`{ item:<a mesma linha da [§9.2](tela-05-estoque.md)>, historico:[{id, tipo, rotulo, referencia, quando, qtd, saldo}], pagina, tem_mais }`,
30 por página, do mais novo ao mais velho.

- O histórico é o mesmo da tela web de histórico de estoque (`ProductUtil::getVariationStockHistory`):
  `tipo` = tipo da transação (`purchase`, `sell`, `stock_adjustment`, `opening_stock`, `sell_transfer`,
  `purchase_transfer`, `production_*`, devoluções); `rotulo` = o texto da web; `qtd` com sinal; `saldo` = saldo
  acumulado depois do movimento; `referencia` = nº da nota/pedido · fornecedor ou cliente (só o nome), ou `null`.
- Mesmas regras da [§9.2](tela-05-estoque.md) (`product.view`, lojas permitidas); linha de outra empresa ou de loja não permitida → 404.
- **Registrar movimento fica na web** (decisão [W] 2026-10-02): no ERP cada tipo é uma transação contábil
  (entrada = compra/estoque inicial com custo; saída/perda = ajuste com valor e custeio FIFO), não um "+N" avulso.
