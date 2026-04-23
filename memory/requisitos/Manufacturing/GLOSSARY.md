# Glossário · Manufacturing

## BoM (Bill of Materials)
Lista de insumos + quantidades pra produzir 1 unidade do produto final. Equivalente a Recipe no módulo.

## Byproduct
Subproduto gerado durante a produção (ex: serragem ao cortar madeira). Incrementa estoque junto com produto final.

## Cost of Goods Sold (CoGS)
Custo dos produtos vendidos. Calculado a partir do custo do Recipe + mão de obra + overhead.

## Lead time
Tempo entre pedir um insumo e ele chegar. Usado pelo MRP pra antecipar compras.

## MRP (Material Requirements Planning)
Algoritmo que, dado demanda prevista, calcula quando e quanto comprar/produzir. Ver ADR TECH-0001.

## Production Order
Ordem de produção. Consome insumos do estoque, gera produto final. Status: `planned → in_progress → completed | cancelled`.

## Recipe
Receita/fórmula de produção. Define: produto final, lista de insumos com quantidades, rendimento esperado.

## Raw Material
Insumo puro — matéria-prima que não tem recipe própria (ex: farinha de trigo comprada).

## Waste
Perda esperada na produção (ex: 5% de farinha vira pó). Configurável por recipe.

## Yield
Rendimento real da produção. Se recipe espera 10kg de bolo mas sai 9.8kg, yield = 98%.
