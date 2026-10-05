---
sessao: "06"
titulo: Produto/AtualizarPreco
dono: "[CL]"
base: 4fa39eb8f007
---
# 06 · Atualizar preço por planilha

`update-product-price` (web.php:1032, `SellingPriceGroupController@updateProductPrice`). Dois passos (exportar → devolver) + conferência + 4 regras (UC-PRC-01..04 em `Importacao.casos.md`).

Export com uma coluna por grupo **ativo**; import recusa linha com SKU/variação alterados. Não confundir com `Produto/SellingPrices.tsx` (preço por produto, já em produção).

## Prova
No JSON do índice. Recibo `_saida-06.md`.
