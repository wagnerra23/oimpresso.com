---
casos: Manufacturing/Recipes — consulta de receitas (ficha técnica / BOM)
irmaos: Recipes.charter.md (lei) · memory/requisitos/Manufacturing/RUNBOOK-recipes.md (F1)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
fonte: handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1" §17 (R-01..R-24) — os UC abaixo DERIVAM dele
owner: wagner
last_run: "2026-10-07"
---

> ℹ️ **`last_run` 2026-10-06 → 2026-10-07 (G-6): forma, não comportamento.** A lista de produtos da janela
> "Nova receita" crescia até o nome mais comprido (medido em produção: 740px numa janela de 520px, botões
> pra fora). A coluna da janela passou a caber nela e o nome corta com "…". Nenhum UC foi reexecutado.

> ℹ️ **`last_run` 2026-10-02 → 2026-10-06 (G-6): a "Nova receita" virou janela da própria tela.**
> O botão levava a `recipe/create`, que devolve só o miolo de um modal Bootstrap sem os scripts do layout —
> em produção a busca de produto não carregava. Agora abre a `NovaReceitaDialog` (decisões [W] de
> 2026-10-06 no SPEC, US-MANU-006), que continua só LENDO e entrega para o mesmo editor legado. Nasceram
> os UC-RECIPE-12 e 13; os UC anteriores não foram tocados nem reexecutados.

> ℹ️ **`last_run` 2026-09-30 → 2026-10-02 (G-6): a lista passou a paginar no SERVIDOR.** A grade virou o
> `shared/DataTable` na anatomia `grid` (o par React do `DataGrid` do DS) e busca, categoria, KPI-filtro,
> ordenação e as 10 por página saíram do navegador pra `RecipeController@index` +
> `RecipeBomService::filtrarOrdenar`. **As regras são as mesmas** — e por isso três itens do backlog viraram
> UC com teste (09 busca · 10 KPI-filtro · 11 ordenação). O custo de cada receita continua o mesmo
> `presentRecipe`: muda onde a lista é cortada, não a conta. Os UC-03/04/05 não foram tocados.

> ℹ️ **`last_run` 2026-09-11 → 2026-09-30 (G-6): forma, não comportamento.** A promoção do protótipo
> trocou os cartões pelo `KpiCard` do DS, a margem pelo `StatusBadge`, e o cabeçalho e o alinhamento da
> tabela pelos do `DataGrid` ([matriz](../../../../memory/requisitos/Manufacturing/Recipes-visual-comparison.md)).
> Filtro por KPI (R-05), ordenação (R-06), faixas de margem (R-10) e seleção seguem com a mesma lógica;
> nenhum UC foi reexecutado nesta data.

> ℹ️ **`last_run` 2026-09-04 → 2026-09-11 (G-6), e o que mudou na tela NÃO foi comportamento.**
> O único toque em `Recipes.tsx` no [#7224](https://github.com/wagnerra23/oimpresso.com/pull/7224) foi **2 linha(s) de COMENTÁRIO** —
> o path do protótipo (`prototipo-ui/cowork/…` → `prototipo-ui/cowork/Wagner/…`, topologia por dono da ADR 0397).
> Zero JSX estrutural, zero handler, zero prop, zero copy alterada (verificado: `git diff origin/main...HEAD -- resources/js/Pages/Manufacturing/Recipes.tsx`
> só tem linhas iniciadas por `//`, `*` ou `{/*`). **Nenhum UC desta tela foi reexecutado nem revalidado**; o bump é o que o campo
> significa na prática (*trio reconciliado com a tela nesta data*), não afirmação de re-run — mesmo tratamento do #6913.

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
  Quando abre o sistema · Então o item "Fabricação" existe no sidebar e o ghost "Receitas"
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

## UC-RECIPE-09 · A busca acha a receita pelo nome, código, categoria ou subcategoria
- **Persona:** Larissa — no balcão, digita o que lembra: às vezes o nome, às vezes o código.
- **Aceite:** Dado as receitas "Banner Lona" (`BN-01`, Impressos / Lona), "Adesivo" (`AD-02`, Adesivos /
  Vinil) e "Placa" (`PL-03`, Placas / ACM) · Quando busco `LONA`, `ad-02`, `placas` ou `acm` · Então
  aparece só a receita que tem aquilo no nome, no código, na categoria ou na subcategoria, sem
  diferenciar maiúscula. E a categoria escolhida nos botões é **exata**: "Adesivo" não traz "Adesivos".
- **Fonte:** **R-03** + §4.2.
- **Teste:** `Wave29RecipeInertiaTest.php`
- **Regressão que defende:** a busca voltar a olhar só o nome, ou a categoria virar "começa com".
- **Status: 🧪**

---

## UC-RECIPE-10 · Os cartões de margem e desperdício filtram a lista; os números do topo contam todas
- **Persona:** Wagner — quer achar as receitas com margem magra sem perder o tamanho do cadastro.
- **Aceite:** Dado margens 44,9 · 45 · 60 e desperdícios 7,99 · 8 · 0 · Quando ligo "Margem abaixo de
  45%" · Então só aparece a de 44,9 (45 fica fora) · Quando ligo "Desperdício ≥ 8%" · Então só a de 8
  (7,99 fica fora). E os 4 números do topo seguem contando **todas** as receitas, com ou sem filtro.
- **Fonte:** **R-05** + §4.2 (e a ambiguidade do KPI 1 registrada abaixo).
- **Teste:** `Wave29RecipeInertiaTest.php`
- **Regressão que defende:** trocar `<` por `<=` no limite de 45, ou o KPI passar a contar só a página.
- **Status: 🧪**

---

## UC-RECIPE-11 · Clicar no cabeçalho ordena a lista nos dois sentidos
- **Persona:** Wagner — ordena por custo unitário pra ver as receitas mais caras primeiro.
- **Aceite:** Dado receitas com custo unitário 5 · 1 · 5 · Quando ordeno por "Custo unitário" · Então a
  de 1 vem primeiro e as duas de 5 mantêm a ordem em que estavam · Quando clico de novo · Então inverte.
  Nomes comparam por caractere, como o navegador comparava: "10" vem antes de "9".
- **Fonte:** **R-06** + §4.2. A volta pra página 1 ao ordenar é do pedido: o `DataTable` não manda `page`.
- **Teste:** `Wave29RecipeInertiaTest.php`
- **Regressão que defende:** o servidor comparar nome como número (o `<=>` do PHP faz isso com
  "10" e "9") e a ordem mudar de um dia pro outro sem ninguém ter mexido na tela.
- **Status: 🧪**

---

## UC-RECIPE-12 · A janela "Nova receita" acha o produto e avisa se ele já tem receita
- **Persona:** Eliana (produção) — vai cadastrar a ficha técnica de um produto novo e não lembra se
  alguém já fez a dele.
- **Aceite:** Dado um produto da empresa com categoria "Categoria da busca" / "Subcategoria da busca" e
  receita, outro sem receita, e um produto de outra empresa com nome parecido · Quando busco pelo nome na
  janela · Então aparecem os dois da empresa, cada um com a categoria e a subcategoria **do produto** e a
  marca de quem já tem receita, e o da outra empresa não aparece. Escolhido o que já tem receita, a janela
  avisa na hora e o botão passa a ser "Abrir receita existente".
- **Fonte:** SPEC US-MANU-006, decisões [W] 2026-10-06 (a) e (c).
- **Teste:** `NovaReceitaTest.php`
- **Regressão que defende:** a busca voltar a depender do select2 do layout Blade (não carrega na tela
  React), ou trazer produto de outra empresa.
- **Status: 🧪**

---

## UC-RECIPE-13 · Copiar de outra receita leva a ficha, não o preço, e cria grupos próprios
- **Persona:** Wagner — monta a receita de um banner novo a partir da do banner parecido.
- **Aceite:** Dado uma receita com 5% de desperdício, custo extra de 12 **percentual**, instruções e um
  ingrediente no grupo "Grupo da original" · Quando abro o editor copiando dela · Então ele vem com o
  ingrediente, o desperdício, o custo extra **com o mesmo tipo** e as instruções · Quando salvo a cópia
  com o grupo renomeado · Então a cópia ganha um grupo próprio com o novo nome, o grupo da original
  continua "Grupo da original", e o preço de venda do produto de destino não muda. Editar a própria
  receita continua renomeando o grupo dela no lugar.
- **Fonte:** SPEC US-MANU-006, decisões [W] 2026-10-06 (b) e a "correção junto" dos grupos.
- **Teste:** `NovaReceitaTest.php`
- **Regressão que defende:** a cópia voltar a reaproveitar o `mfg_ingredient_group_id` da original
  (renomear numa renomeava na outra), ou o custo extra ser copiado sem o tipo.
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** `?legacy=1` responde **200 com a tela Blade** (não só "o ramo existe") e uma
  requisição **ajax real** recebe o JSON do DataTables. É a metade comportamental que os
  UC-RECIPE-06/07 não alcançam: precisa de fixture autenticada (user + business + permissão)
  que a suíte deste módulo ainda não tem — hoje **todos** os testes dela pulam HTTP. Enquanto
  não existir, a prova é o `curl` do RUNBOOK §5. Vira UC quando o teste existir.
- **[BACKLOG]** `/` foca o campo de busca (R-04). A metade de BUSCA virou o UC-RECIPE-09.
- **[BACKLOG]** 10 por página com `a–b de N receitas` (R-07) — o corte vive no `RecipeController@index`,
  que só se prova com a fixture HTTP que a suíte ainda não tem (mesma limitação do item acima).
- **[BACKLOG]** "Selecionar todas" marca as **filtradas** de todas as páginas, não só as visíveis
  (R-08). Desde 2026-10-02 o servidor manda `ids_filtrados` e a tela marca todos eles; a prova é de
  navegador.
- **[BACKLOG]** A coluna Quantidade declara a unidade que exibe — sub-unidade com o rótulo dela (R-09).
- **[BACKLOG]** Margem colorida em 3 faixas: ≥55 · 45–54,9 · <45 (R-10).
- **[BACKLOG]** Drawer fecha com `esc` e com clique no scrim (R-14).
- **[BACKLOG]** A "via de produção" não mostra nenhum valor de compra (R-22); lote gera uma folha
  A4 por receita (R-23).

> Fora o da paginação (Pest com fixture HTTP) e o primeiro, os seis acima são comportamento de
> **navegador** (eram oito até 2026-10-02; busca, KPI-filtro e ordenação viraram UC) — o lugar deles é o spec Playwright
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

> 2026-10-02 · a linha a trocar mudou de lugar: com a lista paginando no servidor, a média sai de
> `kpis($todas)` no `RecipeController@index` — a segunda leitura seria `kpis($filtradas)`. A decisão
> continua em aberto e a tela continua na primeira leitura.

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
- 2026-10-06 · [M+C] "Nova receita" (US-MANU-006, decisões [W] 2026-10-06): janela React no lugar do
  modal Blade quebrado, busca `GET /manufacturing/nova-receita/produtos`, cópia com desperdício/custo
  extra/instruções e grupos próprios. UC-RECIPE-12/13 + `NovaReceitaTest.php`.
