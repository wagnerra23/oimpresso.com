# ADR ARQ-0001 (Manufacturing) · Recipes como BoM hierárquico

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner
- **Categoria**: arq

## Contexto

Produto manufaturado pode ter sub-produtos que também são manufaturados (ex: bolo → massa (que é recipe também) + cobertura). Modelar como lista simples de ingredientes quebra quando sub-produto existe.

## Decisão

`manufacturing_recipes` tem FK `product_id` → produto final. Cada `manufacturing_recipe_ingredient` aponta pra outro `product_id` (pode ser raw material OU produto que também tem recipe). Engine calcula recursivamente.

## Consequências

**Positivas:**
- Estrutura natural de BoM (Bill of Materials).
- Custo calculado recursivamente — mudança em insumo propaga pra tudo que depende.

**Negativas:**
- Dependência circular possível (produto A usa B que usa A). Detectar em save.

## Alternativas consideradas

- **Lista flat de insumos**: rejeitado, não escala.
- **EAV generalista**: rejeitado, overkill.
