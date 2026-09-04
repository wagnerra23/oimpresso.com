---
casos: Manufacturing/Recipes — consulta de receitas (ficha técnica / BOM)
irmaos: Recipes.charter.md (lei) · memory/requisitos/Manufacturing/RUNBOOK-recipes.md (F1)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
fonte: handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1" §17 (R-01..R-24) — os UC abaixo DERIVAM dele
owner: wagner
last_run: "2026-09-04"
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
- **Aceite:** Dado o app carregado · Quando se pergunta ao **registry de rotas** (não ao arquivo)
  quem serve `GET manufacturing/recipe` · Então existe uma rota, e a ação dela é o `RecipeController`.
  O `Inertia::render('Manufacturing/Recipes'` com `recipes`/`permissions`/`producao`/`settings`
  está no mesmo teste, pelo assert estrutural do UC-RECIPE-06.
- ⚠️ **O que este teste NÃO prova:** que a resposta HTTP real traz o payload. Ver UC-RECIPE-08.
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
- **Aceite:** Dado o comentário do controller que ANUNCIA `?legacy=1` · Quando se lê o `index()` ·
  Então o ramo `request()->boolean('legacy')` existe e devolve `view('manufacturing::recipe.index')`.
- **Fonte:** proibição do §15.2 (*"não remover nenhuma rota Blade legacy"*) + §6 do RUNBOOK (rollback).
- **Teste:** `Wave29RecipeInertiaTest.php`
- **Regressão que defende:** o escape ser anunciado no comentário e **não existir** — a classe LC-15
  (mecanismo que anuncia saída que não honra).
- ⚠️ **O que este teste NÃO prova:** que a rota responde **200 com a view** pra um usuário real.
  O assert é sobre a ESTRUTURA do controller, não sobre a resposta HTTP — chamá-lo de prova de
  comportamento seria presence-gate (LC-11). O 200 é smoke (`curl` do RUNBOOK §5), não Pest.
- **Status: 🧪** — do que ele mede: o ramo existe.

---

## UC-RECIPE-07 · O DataTables legado do módulo continua respondendo
- **Persona:** Wagner — o ramo ajax é o que a tela Blade consome.
- **Aceite:** Dado o `index()` do controller · Quando se compara a posição dos dois ramos ·
  Então `if (request()->ajax())` aparece **antes** de `Inertia::render('Manufacturing/Recipes'`.
- **Fonte:** §15.2 (coexistência) — o ramo `request()->ajax()` do controller não foi tocado.
- **Teste:** `Wave29RecipeInertiaTest.php`
- **Regressão que defende:** mover o `return Inertia::render` para antes do `if (ajax)` e matar
  a tabela legada em silêncio — o defeito é de ORDEM, e ordem é o que o assert mede.
- ⚠️ **O que este teste NÃO prova:** que uma requisição ajax real recebe o JSON do DataTables.
  Mesma limitação de fixture do UC-RECIPE-08.
- **Status: 🧪** — do que ele mede: a ordem dos ramos.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** `?legacy=1` responde **200 com a tela Blade** (não só "o ramo existe") e uma
  requisição **ajax real** recebe o JSON do DataTables. É a metade comportamental que os
  UC-RECIPE-06/07 não alcançam: precisa de fixture autenticada (user + business + permissão)
  que a suíte deste módulo ainda não tem — hoje **todos** os testes dela pulam HTTP. Enquanto
  não existir, a prova é o `curl` do RUNBOOK §5. Vira UC quando o teste existir.
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

## Ambiguidade declarada (não inventei desempate)

O §4.2 do handoff descreve o KPI 1 como *"média aritmética do custo unitário das receitas
**exibidas**"*, e a palavra admite duas leituras: as receitas do módulo, ou as que sobraram do
filtro/busca. O protótipo (`manufacturing-page.jsx`) implementa a **primeira** — a média é sobre
`linhas` (todas), não sobre `filtradas` — e o próprio sub-rótulo dele diz *"média das N receitas"*
com N = total. A tela seguiu o protótipo **e mantém o sub-rótulo**, então ela não mente sobre o
que está somando.

Se a intenção era a segunda leitura, é troca de uma linha (`recipes` → `filtradas`) — mas é
decisão de quem escreveu o §4.2, não minha. Fica registrado em vez de silenciado.

## Trilha do tempo
- 2026-09-02 · [CC] carimbado por `criar-tela.mjs` e preenchido a partir do handoff
  "PROTÓTIPO OFICIAL - FABRICAÇÃO V1" §17. 7 UC com teste Pest; 8 no backlog aguardando e2e.
  Refs: UI-0013 · ADR 0264 G-1/G-2 · ADR 0104.
- 2026-09-03 · [F+C] `Recipes.tsx` tocado (aba "Relatório" passou a apontar pra
  `/manufacturing/v2/report`, a tela nova de US-MANU-002, em vez do Blade legado
  `/manufacturing/report`). Revalidado: nenhum dos 7 UC acima cita a aba de navegação —
  o comportamento que eles defendem (consulta/custo/tenant/legacy) não mudou. `last_run`
  bumped por G-6 (a tela mudou), não por regressão encontrada.
- 2026-09-04 · [F+C] Barra de abas corrigida: "Configurações" apontava pra rota Blade legada
  (âncora crua, saía do SPA) e a aba "Insumos" não existia. [F] reportou clicando na aba e
  caindo na tela antiga. **Segunda ocorrência do mesmo defeito** — em 2026-09-03 a aba
  "Relatório" foi corrigida do mesmo jeito e a "Configurações" ficou pra trás na mesma leva,
  porque **nenhum UC cobre a barra de navegação** e nada guardava isso. A guarda agora existe:
  `Modules/Manufacturing/Tests/Feature/AbasTelasV2Test.php` (4 asserts; 3 provados por bite
  test contra cópia adulterada, o 4º usa o registry de rotas em runtime). Nenhum UC acima
  mudou de comportamento. ⚠️ O cutover da rota legada segue PENDENTE e é decisão [W].
- 2026-09-04 · [F+C] **CUTOVER**: a tela passou a ser servida no endereço CANÔNICO do módulo
  (nasceu em `/manufacturing/v2/*`, que virou 301). Pedido [F]: *"módulo inteiro em produção,
  com os links e vínculos reais, sem rotas alternativas"*, sobre a aprovação [W] da família.
  Pré-condição medida: a regra F5 (cutover exige aviso a cliente) nomeia a ROTA LIVRE, e [F]
  confirmou que ela não usa Fabricação. **Nenhuma rota removida ou renomeada** — `?legacy=1`
  devolve o Blade no MESMO endereço e o ramo AJAX do DataTables segue intacto. Guarda nova:
  `Modules/Manufacturing/Tests/Feature/CutoverRotasCanonicasTest.php` (9 asserts). Nenhum UC
  acima mudou de comportamento; os asserts de ROTA de Wave30/31/33 foram reapontados pro
  canônico porque o `/v2/` agora responde `RedirectController`, não o controller da tela.
