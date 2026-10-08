---
page: /manufacturing/add-ingredient
component: resources/js/Pages/Manufacturing/IngredientesEditor.tsx
owner: wagner
status: draft
parent_module: Manufacturing
related_prototype: prototipo-ui/cowork/Wagner/manufacturing-recipe.jsx
runbook: memory/requisitos/Manufacturing/RUNBOOK-ingredientes-editor.md
related_us: [US-MANU-006]
alcance:
  rota: /manufacturing/add-ingredient
  rota_nome: null                     # rota legada sem name(); a tela nova vive atrás de ?tela=nova
  permission: manufacturing.access_recipe   # abrir (leitura); gravar = manufacturing.add_recipe no store()
  menu_hook: null                     # não tem item de menu: chega-se pelo "Editar ingredientes" da lista de Receitas
  pacote: manufacturing_module
tier: B
charter_version: 1
---

# Page Charter — Manufacturing/IngredientesEditor (DRAFT)

> Carimbado do **PT-02 Form** por `criar-tela.mjs` (UI-0013). Forma: protótipo
> `manufacturing-recipe.jsx::MfgIngredientesEditor`. Regras: handoff Fabricação §5, §7 e §9.
> Plano por etapas: [RUNBOOK](../../../../memory/requisitos/Manufacturing/RUNBOOK-ingredientes-editor.md).
> Casos: [IngredientesEditor.casos.md](IngredientesEditor.casos.md).

## Mission

Montar e corrigir a ficha técnica de um produto vendo o custo se atualizar na tela, para fechar o
preço sem exportar para planilha (SPEC US-MANU-006).

## Goals — Features (faz)

- Abre a ficha do produto: grupos, ingredientes, quantidade, unidade/sub-unidade, custo de hoje e
  subtotal por linha e por grupo (handoff §5)
- "Custo ao vivo": ingredientes, custo extra e custo por unidade, com as fórmulas do §7
- Quem só tem `manufacturing.access_recipe` vê a ficha em leitura, com aviso (regra 3)
- Com `disable_editing_ingredient_qty` ligado, avisa que a quantidade está travada (regra 2)
- Exclui a receita já gravada, sempre com confirmação que diz o que se perde: a ficha, os N
  ingredientes, e que ordens já lançadas continuam com o custo registrado (regra 6). Só para quem
  grava (`manufacturing.add_recipe`); o servidor só acha receita da própria empresa (caso 19 de `Recipes.casos.md`)
- PT-BR em todo rótulo e mensagem

## Non-Goals — Features (NÃO faz)

> Todos vêm de fonte canônica, citada. Nenhum foi inferido do código.

- ❌ Gravar o custo calculado no navegador — o custo gravado é o do servidor (handoff §9)
- ❌ "Atualizar preço de venda" com `custo × 2` — §7 ponto 7 e §18.1 do handoff; a regra de markup
  não foi decidida (SPEC US-MANU-007, "O que NÃO vira onda")
- ❌ Copiar o preço de venda ao copiar uma receita (decisão [W] 2026-10-06 (b))

## UX Targets

- Cabe em 1280px sem scroll horizontal
- Mesmo número de "Custo por <unidade>" que a lista de Receitas mostra para a mesma receita

## Refs

- Padrão de Tela: PT-02 Form (useForm + FormSection + FormGrid) · Constituição UI v2: UI-0013
- SPEC `memory/requisitos/Manufacturing/SPEC.md` US-MANU-006
