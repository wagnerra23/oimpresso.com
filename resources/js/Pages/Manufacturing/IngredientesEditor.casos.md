---
casos: Manufacturing/IngredientesEditor — editor de ingredientes da receita (ficha técnica)
irmaos: IngredientesEditor.charter.md (lei) · memory/requisitos/Manufacturing/RUNBOOK-ingredientes-editor.md (F1)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
fonte: handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1" §5 e §9 (prototipo-ui/cowork/Felipe/handoff_fabricacao/README.md) — os UC DERIVAM dele
owner: wagner
last_run: "2026-10-09"
---

# Casos de Uso & Aceite — Manufacturing/IngredientesEditor

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão.
>
> Etapas 1 e 2 (RUNBOOK §1): abrir a ficha (UC-01..05) e salvar pela tela nova (UC-06, 07).
> O salvar em si (casos 14 a 18 de RECIPE) mora em `Recipes.casos.md`, porque o endpoint é o mesmo. Os ids não são citados aqui de propósito: o gerador de status leria a menção como caso desta tela.

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

## UC-INGRED-06 · O que digito na tela é o que fica gravado
- **Persona:** Eliana (produção) — digita 0,5 de lona e 1.234,56 de tinta.
- **Aceite:** Dado o salvar no formato que a tela gera (`paraNumUf`: vírgula decimal, sem milhar, com
  as casas exibidas no campo — `0,5`, `1234,56`) · Quando salvo · Então a receita grava 0,5 e 1234,56 de quantidade, 2 de
  rendimento, 10% de desperdício e 2,50 de custo extra, e o grupo novo é criado.
- **Fonte:** REGRA MESTRE de valor (`proibicoes.md`, incidente 2026-06-05: ponto lido como milhar) ·
  handoff §5.
- **Teste:** `Modules/Manufacturing/Tests/Feature/EditorIngredientesTest.php`
- **Regressão que defende:** a tela mandar número cru (`0.5`) e o `num_uf` ler o ponto como milhar.
- **Status: 🧪**

## UC-INGRED-07 · Ingrediente sem grupo continua sem grupo
- **Persona:** Wagner — corrige a quantidade de uma ficha antiga, que tem ingrediente sem grupo.
- **Aceite:** Dado uma receita com uma linha sem grupo · Quando abro o editor · Então ela aparece no
  balde "Sem grupo", marcado como balde · Quando salvo · Então a quantidade muda, a linha continua sem
  grupo, e nenhum grupo chamado "Sem grupo" é criado.
- **Fonte:** handoff §5 regra 5 ("salvar devolve o objeto inteiro") — sem inventar dado que não existia.
- **Teste:** `Modules/Manufacturing/Tests/Feature/EditorIngredientesTest.php`
- **Status: 🧪**

> **UC-INGRED-08..13 — as 6 regras de TELA do handoff §5.** Saíram do backlog em 2026-10-09: o que
> faltava era teste de navegador, e eles agora rodam em vitest/jsdom no componente real, na lane
> `manufacturing-jsdom-gate`. O servidor de cada regra segue nos casos de `Recipes.casos.md`.

## UC-INGRED-08 · Salvar exige pelo menos 1 ingrediente
- **Persona:** Eliana (produção) — tira o último insumo da ficha por engano.
- **Aceite:** Dado uma receita com ingrediente · Então "Salvar receita" está habilitado · Quando
  removo o último · Então o botão fica desabilitado e a tela escreve "A receita precisa de pelo menos
  1 ingrediente." · Dado uma receita que já abre vazia · Então o mesmo.
- **Fonte:** handoff §5 regra 1. O servidor também recusa (caso 15 de `Recipes.casos.md`).
- **Teste:** `tests/js/manufacturing-ingredientes-editor.test.tsx` (vitest/jsdom, lane
  `manufacturing-jsdom-gate`). Mutação sem o motivo → 2 failed.
- **Regressão que defende:** botão desabilitado mudo, ou salvar ficha vazia.
- **Status: 🧪**

## UC-INGRED-09 · Quantidade travada em Configurações vira texto
- **Persona:** Wagner — travou a quantidade para a produção não mexer na ficha.
- **Aceite:** Dado `disable_editing_ingredient_qty` ligado · Quando abro o editor · Então a
  quantidade aparece como texto (sem campo) e a tela avisa "Edição de quantidade de ingrediente está
  bloqueada em Configurações." · Sem a trava · Então é campo editável e não há aviso.
- **Fonte:** handoff §5 regra 2.
- **Teste:** `tests/js/manufacturing-ingredientes-editor.test.tsx`. Mutação ignorando a trava → 1 failed.
- **Status: 🧪**

## UC-INGRED-10 · Quem só consulta não vê salvar, excluir nem campo ativo
- **Persona:** Eliana (produção) — só com `manufacturing.access_recipe`.
- **Aceite:** Dado usuário sem permissão de gravar · Quando abro o editor · Então não existem "Salvar
  receita", "Excluir receita" nem "Remover"; Quantidade produzida, Desperdício e Instruções estão
  desabilitados; a quantidade dos ingredientes é texto; aparece "Sua permissão é apenas de leitura
  (manufacturing.access_recipe)." e o botão de saída diz "Voltar para Receitas".
- **Fonte:** handoff §5 regra 3. A recusa no servidor é o UC-INGRED-03.
- **Teste:** `tests/js/manufacturing-ingredientes-editor.test.tsx`. Mutação com campo ativo → 1 failed.
- **Status: 🧪**

## UC-INGRED-11 · Trocar a sub-unidade troca o multiplicador junto
- **Persona:** Eliana (produção) — compra tinta em galão de 5 L e a ficha está em litro.
- **Aceite:** Dado 2 de um insumo de custo 10 por L · Então o subtotal é 20,00 · Quando escolho
  "galão (5 L)" · Então o subtotal vira 100,00 e a linha mostra "equivale a 10,000 L" · Quando volto
  para L · Então 20,00 de novo. Não existe campo para digitar o multiplicador.
- **Fonte:** handoff §5 regra 4 · fórmula do §7 (quantidade × custo × multiplicador).
- **Teste:** `tests/js/manufacturing-ingredientes-editor.test.tsx`. Mutação com multiplicador 1 → 2 failed.
- **Status: 🧪**

## UC-INGRED-12 · O editor trabalha numa cópia e cancelar não grava nada
- **Persona:** Wagner — mexe na ficha para simular e desiste.
- **Aceite:** Dado a ficha aberta · Quando mudo a quantidade e removo um ingrediente · Então a tela
  muda, mas a ficha recebida continua igual e nada é enviado ao servidor · "Cancelar" é só um link
  para a lista de Receitas · "Salvar receita" é o único caminho que envia.
- **Fonte:** handoff §5 regra 5.
- **Teste:** `tests/js/manufacturing-ingredientes-editor.test.tsx`. Mutações: gravar ao editar →
  1 failed; alterar a ficha recebida em vez da cópia → 3 failed.
- **Status: 🧪**

## UC-INGRED-13 · Excluir receita pede confirmação que diz o que se perde
- **Persona:** Wagner — vai apagar uma receita antiga.
- **Aceite:** Dado uma receita ainda não gravada · Então não há "Excluir receita" · Dado uma receita
  gravada com 2 ingredientes · Quando removo 1 na tela e clico em excluir · Então a confirmação diz o
  nome, "os 2 ingredientes" (os **gravados**, não os da cópia) e "Ordens de produção já lançadas
  continuam com o custo registrado." · Quando confirmo · Então vai `DELETE` desta receita e volto para
  a lista · Se o servidor recusa · Então a tela mostra o motivo e não sai · "Cancelar" fecha sem apagar.
- **Fonte:** handoff §5 regra 6. O servidor (só apaga receita da própria empresa) é o caso 19 de
  `Recipes.casos.md`.
- **Teste:** `tests/js/manufacturing-ingredientes-editor.test.tsx`. Mutações: contar a cópia →
  1 failed; não voltar para a lista depois de excluir → 1 failed.
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Chego ao editor pelo "Editar ingredientes" da lista de Receitas, sem digitar URL.
  Desde a etapa 3 (2026-10-08) o link leva `&tela=nova`; a prova é de navegador.
- **[BACKLOG]** O "Custo por <unidade>" do editor é o mesmo da lista de Receitas para a mesma receita.
  Precisa de teste de navegador (o número sai do `_lib/custo.ts`).
- **[BACKLOG]** O Radix Select de verdade (abrir, teclado) na troca de sub-unidade. O UC-INGRED-11
  troca o widget por `<select>` nativo no teste: prova a regra, não o componente visual.

## Trilha do tempo
- 2026-10-08 · carimbado por criar-tela.mjs e preenchido com os UC da etapa 1. Refs: UI-0013 · ADR 0264.
- 2026-10-08 · [M+C] Etapa 3: botão "Excluir receita" com a confirmação da regra 6 e o link da lista
  passando a abrir esta tela. Sem UC novo: o comportamento de tela vai para o backlog (navegador); o
  servidor é o caso 19 de `Recipes.casos.md`.
- 2026-10-09 · [M+C] As 6 regras de tela do §5 saem do backlog como UC-INGRED-08..13, com
  `tests/js/manufacturing-ingredientes-editor.test.tsx` (19 testes) na lane `manufacturing-jsdom-gate`.
  Cada bloco tem controle positivo; mordida provada por 8 mutações, restauro conferido por hash.
