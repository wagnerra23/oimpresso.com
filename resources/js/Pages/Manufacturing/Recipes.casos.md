---
casos: Manufacturing/Recipes — consulta de receitas (ficha técnica / BOM)
irmaos: Recipes.charter.md (lei) · memory/requisitos/Manufacturing/RUNBOOK-recipes.md (F1)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
fonte: handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1" §17 (R-01..R-24) — os UC abaixo DERIVAM dele
owner: wagner
last_run: "2026-09-02"
---

# Casos de Uso & Aceite — Manufacturing/Recipes

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão.
>
> Os casos abaixo **não foram derivados do `.tsx`** (§5 tautológico das proibições). Cada um
> aponta o requisito do handoff normativo (`R-NN`) de onde saiu.

---

## UC-RECIPE-00 · Chego na tela pelo menu, sem digitar URL
- **Persona:** Larissa — abre o sistema e encontra Fabricação no sidebar.
- **Aceite:** Dado usuário com `manufacturing.access_recipe` e o pacote `manufacturing_module` ·
  Quando abre o sistema · Então o item "Manufacturing" existe no sidebar e o ghost "Receitas"
  leva a `/manufacturing/recipe` com 200.
- **Regressão que defende:** a tela responder 200 e ninguém alcançar. Aqui o alcance **já existia**
  — `DataController::modifyAdminMenu` (Modules/Manufacturing) aponta o ghost `recipe` para
  `/manufacturing/recipe` desde a ADR 0180; este PR não mexeu no menu, mudou o que a rota serve.
- **Teste:** `Modules/Manufacturing/Tests/Feature/Wave29RecipeInertiaTest.php` — cobre a
  **metade verificável**: que o ghost aponta pra esta rota e que o menu segue atrás do pacote
  `manufacturing_module` + da permissão `manufacturing.access_recipe`.
- **Status: ⬜** — a outra metade (a permissão de fato LIGADA numa função em `/roles/{id}/edit`)
  é dado de runtime; nenhum gate cobre, e fingir ✅ aqui seria afirmar o que não foi medido.

---

## UC-RECIPE-01 · A rota `/manufacturing/recipe` serve a tela nova de Fabricação
- **Persona:** Larissa · Wagner — o endereço que [W] pediu abre a tela nova, não a antiga.
- **Aceite:** Dado usuário com permissão · Quando abre `/manufacturing/recipe` ·
  Então a resposta é Inertia com `component = "Manufacturing/Recipes"` e traz `recipes`,
  `permissions`, `producao` e `settings` no payload.
- **Fonte:** decisão [W] 2026-09-02 (o endereço) + §15.2 do handoff (o conteúdo da tela).
- **Teste:** `Modules/Manufacturing/Tests/Feature/Wave29RecipeInertiaTest.php`
- **Regressão que defende:** alguém "consertar" o controller devolvendo a view Blade e a tela
  nova sumir sem erro nenhum.
- **Status: 🧪**

---

## UC-RECIPE-02 · A lista NÃO vaza receita de outro business
- **Persona:** qualquer tenant — Tier 0.
- **Aceite:** Dado dois businesses com receitas · Quando o usuário do business A abre a tela ·
  Então o payload traz **somente** receitas cuja cadeia `mfg_recipes.variation_id →
  variations.product_id → products.business_id` bate com A.
- **Fonte:** §2.1 e §9 do handoff · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Teste:** `Wave29RecipeInertiaTest.php`
- **Regressão que defende:** Manufacturing **não tem global scope** — quem trocar o JOIN por um
  `where` ingênuo abre vazamento cross-tenant sem quebrar nada visível.
- **Status: 🧪**

---

## UC-RECIPE-03 · O custo unitário divide pela quantidade produzida, não pelo rendimento
- **Persona:** Wagner — forma preço e precisa que o número bata com o legado.
- **Aceite:** Dado receita com `total_quantity = 10` e `waste_percent = 4` · Quando a tela carrega ·
  Então `custos.unit = custos.total / 10` (nunca `/ 9,6`), e `custos.qtd_liq = 9,6`.
- **Fonte:** **R-11** + §7.1 — *"É assim no legado; manter."*
- **Teste:** `Wave29RecipeInertiaTest.php`
- **Regressão que defende:** o desperdício ser embutido no custo unitário, inflando todo preço
  formado a partir desta tela.
- **Status: 🧪**

---

## UC-RECIPE-04 · As três fórmulas de custo extra dão três resultados diferentes
- **Persona:** Wagner — o mesmo `18` significa coisas diferentes conforme o tipo.
- **Aceite:** Dado o mesmo `extra_cost = 18` · Quando `production_cost_type` é `percentage`,
  `per_unit` e `fixed` · Então o custo total é, respectivamente, `ingredientes × 0,18` somado,
  `18 × total_quantity` somado, e `18` somado.
- **Fonte:** **R-12** + §7 (fórmulas) — espelha `RecipeBomService::calculateCost`.
- **Teste:** `Wave29RecipeInertiaTest.php`
- **Regressão que defende:** unificar os três casos numa fórmula só "porque é mais limpo".
- **Status: 🧪**

---

## UC-RECIPE-05 · Divisão por zero devolve 0, nunca NaN
- **Persona:** qualquer — receita recém-criada tem `total_quantity = 0`.
- **Aceite:** Dado receita com `total_quantity = 0` e `final_price = 0` · Quando a tela carrega ·
  Então `custos.unit = 0` e `custos.margem = 0`, e a tela mostra `R$ 0,00` / `0%`.
- **Fonte:** **R-13** + §7.3.
- **Teste:** `Wave29RecipeInertiaTest.php`
- **Regressão que defende:** `NaN`/`Infinity` chegando ao `toLocaleString` e imprimindo lixo na
  coluna de dinheiro.
- **Status: 🧪**

---

## UC-RECIPE-06 · A tela Blade legada continua alcançável no mesmo endereço
- **Persona:** Wagner — rede de segurança do cutover.
- **Aceite:** Dado a tela nova em produção · Quando abre `/manufacturing/recipe?legacy=1` ·
  Então a resposta é a view Blade `manufacturing::recipe.index` (não-Inertia), com 200.
- **Fonte:** proibição do §15.2 (*"não remover nenhuma rota Blade legacy"*) + §6 do RUNBOOK (rollback).
- **Teste:** `Wave29RecipeInertiaTest.php`
- **Regressão que defende:** o escape ser anunciado no comentário do controller e não existir —
  é a classe LC-15 (mecanismo que anuncia saída que não honra), e por isso ele tem teste.
- **Status: 🧪**

---

## UC-RECIPE-07 · O DataTables legado do módulo continua respondendo
- **Persona:** Wagner — o ramo ajax é o que a tela Blade consome.
- **Aceite:** Dado requisição ajax a `/manufacturing/recipe` · Quando o cliente pede JSON ·
  Então a resposta é o payload DataTables (`data`), não Inertia.
- **Fonte:** §15.2 (coexistência) — o ramo `request()->ajax()` do controller não foi tocado.
- **Teste:** `Wave29RecipeInertiaTest.php`
- **Regressão que defende:** mover o `return Inertia::render` para antes do `if (ajax)` e matar
  a tabela legada em silêncio.
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** A busca casa nome, SKU, categoria e subcategoria; `/` foca o campo (R-03 · R-04).
- **[BACKLOG]** KPI 2 e 3 filtram a lista; KPI 1 e 4 não (R-05).
- **[BACKLOG]** Ordenar alterna asc/desc e volta pra página 1 (R-06); 10 por página com `a–b de N` (R-07).
- **[BACKLOG]** "Selecionar todas" marca as **filtradas**, não só as visíveis (R-08).
- **[BACKLOG]** A coluna Quantidade declara a unidade que exibe — sub-unidade com o rótulo dela (R-09).
- **[BACKLOG]** Margem colorida em 3 faixas: ≥55 · 45–54,9 · <45 (R-10).
- **[BACKLOG]** Drawer fecha com `esc` e com clique no scrim (R-14).
- **[BACKLOG]** A "via de produção" não mostra nenhum valor de compra (R-22); lote gera uma folha
  A4 por receita (R-23).

> Os oito acima são comportamento de **navegador** — o lugar deles é o spec Playwright
> (`e2e/manufacturing-recipes.spec.ts`), não Pest. Entram como UC quando o teste existir e citar o id.

## Trilha do tempo
- 2026-09-02 · [CC] carimbado por `criar-tela.mjs` e preenchido a partir do handoff
  "PROTÓTIPO OFICIAL - FABRICAÇÃO V1" §17. 7 UC com teste Pest; 8 no backlog aguardando e2e.
  Refs: UI-0013 · ADR 0264 G-1/G-2 · ADR 0104.
