# App — Estoque (tela 05) — só leitura

> Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6).

`GET /api/app/estoque?filtro=todos|baixo&q=<texto>&pagina=N` →
`{ itens:[{id, produto_id, nome, codigo, qtd, minimo, unidade, local, prateleira}], contadores:{todos, baixo}, pagina, tem_mais }`,
30 por página, por nome.

- Uma linha por **variação × loja** (`id` = `variation_location_details.id`), só nas lojas que o usuário
  pode ver e só de produto que **controla estoque** (sem estoque fica fora; na 19 ele aparece "sob demanda").
- `nome` traz a variação quando o produto é variável ("Caneca · Azul"); `codigo` = SKU da variação, ou do produto.
- `minimo` = `alert_quantity` (ou `null`); `baixo` = a regra do alerta da web (`qtd ≤ minimo`).
- `local` = nome da loja; `prateleira` = "rack · fileira · posição" de `product_racks`, ou `null`.
- Permissão `product.view` (403 sem ela); a área `estoque` entra em `areas` (§6) com a mesma regra.
