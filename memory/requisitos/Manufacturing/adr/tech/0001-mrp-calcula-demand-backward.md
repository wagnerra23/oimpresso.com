# ADR TECH-0001 (Manufacturing) · MRP calcula demand backward

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner
- **Categoria**: tech

## Contexto

MRP (Material Requirements Planning) precisa decidir o que produzir/comprar pra atender ordens futuras. Calcular forward (do insumo pra produto) é O(n²). Backward (do produto pra insumo) é O(n log n).

## Decisão

`MrpService::calculate($period)` roda backward:
1. Lista ordens de venda + previsão no período.
2. Pra cada produto demandado, resolve recipe tree recursivamente.
3. Soma necessidades por insumo.
4. Subtrai estoque atual + ordens de compra já colocadas.
5. Output: lista de "comprar X kg de farinha em data Y" + "produzir Z de bolo em data W".

## Consequências

**Positivas:**
- Performance aceitável pra catálogos de 10k produtos.
- Lead time respeitado (comprar com antecedência).

**Negativas:**
- Algoritmo complexo — bugs difíceis de reproduzir.

## Alternativas consideradas

- **MRP forward (push)**: rejeitado, não responde a demanda real.
- **Lib externa (OpenBoxes)**: rejeitado — integração custa mais que escrever.
