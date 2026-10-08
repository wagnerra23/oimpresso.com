---
casos: Manufacturing/IngredientesEditor — editor de ingredientes da receita (ficha técnica)
irmaos: IngredientesEditor.charter.md (lei) · memory/requisitos/Manufacturing/RUNBOOK-ingredientes-editor.md (F1)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
fonte: handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1" §5 e §9 (prototipo-ui/cowork/Felipe/handoff_fabricacao/README.md) — os UC DERIVAM dele
owner: wagner
last_run: "2026-10-08"
---

# Casos de Uso & Aceite — Manufacturing/IngredientesEditor

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão.
>
> Etapa 1 (RUNBOOK §1): a ficha abre em **leitura**. Os casos de edição e salvar entram na etapa 2.
> O salvar em si (UC-RECIPE-14..18) mora em `Recipes.casos.md`, porque o endpoint é o mesmo.

---

## UC-INGRED-01 · Abro a ficha e vejo cada ingrediente com o custo de hoje
- **Persona:** Wagner — confere a ficha do banner antes de fechar o preço.
- **Aceite:** Dado uma receita com 1 ingrediente de 4,00 em "caixa com 5", quantidade 0,5, no grupo
  "Impressão", rendimento 2, desperdício 10% e 2,50 fixo · Quando abro o editor · Então vejo o grupo, a
  linha com o custo unitário de hoje (4,00), a quantidade, a caixa escolhida, as unidades que o insumo
  aceita (a dele ×1 e a caixa ×5) e os campos da receita como estão gravados.
- **Fonte:** handoff §5 (coluna principal e lateral).
- **Teste:** `Modules/Manufacturing/Tests/Feature/EditorIngredientesTest.php`
- **Status: 🧪**

## UC-INGRED-02 · Ficha e insumos de outra empresa não aparecem
- **Persona:** qualquer usuário da empresa.
- **Aceite:** Dado um produto e um insumo de outra empresa · Quando abro o editor com o produto dela ·
  Então 404 · Quando busco insumo · Então só vêm os da minha empresa.
- **Fonte:** handoff §9 (`business_id` em toda query) · ADR 0093.
- **Teste:** `Modules/Manufacturing/Tests/Feature/EditorIngredientesTest.php`
- **Status: 🧪**

## UC-INGRED-03 · Quem só consulta abre em leitura
- **Persona:** Eliana (produção) — consulta a ficha, não altera.
- **Aceite:** Dado usuário só com `manufacturing.access_recipe` · Quando abre o editor · Então a tela
  abre sem poder gravar (e mostra "Sua permissão é apenas de leitura (manufacturing.access_recipe).") e
  a busca de insumo responde 403 · Dado usuário sem essa permissão · Então 403.
- **Fonte:** handoff §5 regra 3 · SPEC R-MANU-002/003.
- **Teste:** `Modules/Manufacturing/Tests/Feature/EditorIngredientesTest.php`
- **Status: 🧪**

## UC-INGRED-04 · Copiar de outra receita traz a ficha, não as linhas nem os grupos dela
- **Persona:** Wagner — monta o banner novo a partir de um parecido.
- **Aceite:** Dado um produto sem receita · Quando abro o editor copiando de outra · Então vêm o custo
  extra, as instruções, os grupos pelo nome e os ingredientes, **sem** id de linha, **sem** id de grupo
  e sem a sub-unidade de saída da original.
- **Fonte:** decisão [W] 2026-10-06 (b) e a "correção junto" dos grupos (SPEC US-MANU-006).
- **Teste:** `Modules/Manufacturing/Tests/Feature/EditorIngredientesTest.php`
- **Status: 🧪**

## UC-INGRED-05 · A busca de insumo traz no máximo 7
- **Persona:** Eliana (produção).
- **Aceite:** Dado 9 insumos que casam com a busca · Quando busco · Então vêm 7.
- **Fonte:** handoff §5 ("máx. 7 resultados").
- **Teste:** `Modules/Manufacturing/Tests/Feature/EditorIngredientesTest.php`
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Chego ao editor pelo "Editar ingredientes" da lista de Receitas, sem digitar URL —
  entra na etapa 3, quando o link trocar para a tela nova (RUNBOOK §1).
- **[BACKLOG]** Etapa 2: trocar a sub-unidade troca o multiplicador junto (regra 4); quantidade travada
  vira texto (regra 2); salvar exige ≥1 ingrediente (regra 1); cancelar descarta (regra 5).
- **[BACKLOG]** O "Custo por <unidade>" do editor é o mesmo da lista de Receitas para a mesma receita.
  Precisa de teste de navegador (o número sai do `_lib/custo.ts`).

## Trilha do tempo
- 2026-10-08 · carimbado por criar-tela.mjs e preenchido com os UC da etapa 1. Refs: UI-0013 · ADR 0264.
