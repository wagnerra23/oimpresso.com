# App — Produtos (tela 19) — só leitura

> Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6).

`GET /api/app/produtos?categoria=<id|todas>&q=<texto>&pagina=N` →
`{ itens:[{id, nome, codigo, categoria, calculo, preco, variacoes, estoque:{controla, qtd, unidade}, baixo}],
categorias:[{id, nome, total}], total, baixo_estoque, pagina, tem_mais }`, 30 por página, por nome.

- Permissão `product.view` (a da lista web); sem ela, 403. Produtos ativos do business, sem os `modifier`.
  A área `produtos` entra em `areas` do Início (§6) com essa mesma regra.
- Busca `q` em nome, código (SKU) e categoria. `categorias` e `total` respeitam a busca, não o filtro de categoria.
- `calculo` = "por " + unidade curta do produto ("por m²", "por un"); `null` sem unidade.
- `preco` = preço de venda com imposto (`sell_price_inc_tax`); produto com variação traz o menor e
  `variacoes` = quantas. Só exibição.
- `estoque.qtd` = soma nos locais que o usuário pode ver; `null` quando o produto não controla estoque
  ("sob demanda"). Usuário sem nenhum local permitido vê 0, nunca o estoque de todos.
- `baixo` / `baixo_estoque` = a mesma regra do alerta da web (`ProductUtil::getProductAlert`): alguma
  variação × local com quantidade ≤ `alert_quantity`.
