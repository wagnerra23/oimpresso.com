---
id: requisitos-manufacturing-spec
module: Manufacturing
owner: wagner
version: "1.0"
last_updated: "2026-09-02"
na_justified:
  D6.a: "Manufacturing usa pattern Blade legacy + 1 página Inertia v2 (Wave J Onda 1) — Inertia::defer aplicado parcialmente."
---

# Especificação funcional

## 3. User stories

> Convenção do ID: `US-MANU-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

### US-MANU-001 · Quanto custa produzir, com o preço de insumo de hoje

**Como** Larissa (balcão/orçamento) e Wagner (dono)
**Quero** ver, numa tela só, o custo de cada receita recalculado a partir do preço de compra
atual dos ingredientes — com margem, desperdício e a ficha técnica imprimível
**Para** formar preço sem consultar planilha, e enxergar qual receita ficou com margem magra
depois que um insumo subiu

> Escrita em 2026-09-02 a partir do handoff normativo **"PROTÓTIPO OFICIAL - FABRICAÇÃO V1"**
> (§2 *"O que a tela faz"* e §17 *Requisitos com teste de aceite*) e do pedido de [W] nomeando o
> endereço. Antes disso era placeholder `[TODO]` — não havia US escrita neste módulo.

**Implementado em:** `resources/js/Pages/Manufacturing/Recipes.tsx` ·
`Modules/Manufacturing/Http/Controllers/RecipeController.php` (`@index`) ·
`Modules/Manufacturing/Services/RecipeBomService.php` (`listRecipesWithCost`)

**Testado em:** `Modules/Manufacturing/Tests/Feature/Wave29RecipeInertiaTest.php` (@covers-us US-MANU-001)

**Definition of Done:**
- [x] O custo é **recalculado na leitura** a partir de `variations.dpp_inc_tax` — nunca servido de
      `mfg_recipes.ingredients_cost`, coluna que envelhece (§9 do handoff)
- [x] O custo unitário divide por `total_quantity`, **não** pelo rendimento (R-11)
- [x] As três fórmulas de `production_cost_type` dão três resultados distintos (R-12)
- [x] Divisão por zero devolve `0`, nunca `NaN`/`Infinity` (R-13)
- [x] Isolamento de tenant pela cadeia `mfg_recipes → variations → products.business_id` — o JOIN
      é a única barreira, porque o módulo não tem global scope ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- [x] Ficha técnica PT-07 em duas variantes; a **via de produção** não mostra nenhum valor de
      compra (R-22)
- [x] Nenhuma rota Blade legada removida; `?legacy=1` devolve a tela antiga no mesmo endereço
- [x] Smoke real em prod (`curl` + screenshot) — feito 2026-09-03: `curl -sv` nas 4 rotas do
      §5 (sem cookie) devolve `302→/login` nas quatro; screenshot autenticado (Chrome MCP,
      sessão WR2 Sistemas) confirma `/manufacturing/recipe` e `?legacy=1` renderizando a tela
      certa, 0 erro de console, e as rotas Blade adjacentes (`production`/`settings`)
      inalteradas. Registrado em `Recipes.charter.md` (`smoke:`)

**Fora do escopo desta US** (declarado, com a razão):
- Atualizar preço de venda em massa — §18.1 do handoff proíbe o `custo × 2` do protótipo e a regra
  de markup real não foi decidida; escrever em N preços é Tier 0 de valor
- Aba Insumos — §18.3: `usosDoInsumo` é cálculo novo sem backend
- Editor de ingredientes, ordem de produção, relatório e configurações — têm tela própria


---

> ## Ondas 2–7 · o resto da família de Fabricação
>
> A US-MANU-001 entregou **uma** das sete telas do handoff **"PROTÓTIPO OFICIAL - FABRICAÇÃO V1"**.
> As seis abaixo são o resto, na ordem de **custo crescente** — decisão [M] 2026-09-02:
> *"vamos precisar fazer todas (…) mas começaremos com as que gastam menos"*.
>
> **Uma onda = um PR** (`commit-discipline`). O custo abaixo é estimativa minha; a ordem é dela.
> O que dirige o custo é sempre a mesma coisa: **o backend já existe?** e **a tela escreve?**

### US-MANU-002 · Relatório de produção do período

**Como** Wagner
**Quero** ver quanto custou produzir no período, agrupado por produto
**Para** saber para onde o dinheiro de produção foi antes de fechar o mês

**Fonte:** handoff §4.6. **Backend original: `ProductionController@getManufacturingReport`** —
achado ao construir (2026-09-03): esse método legado **não agrupa por produto** (só 3 totais
soltos: `total_production`/`total_production_cost`/`total_sold`). O agrupamento por produto é
capacidade **nova**: `ProductionService::reportByProduct()`, que REUSA
`RecipeBomService::calculateUnitCost()` (já testado em US-MANU-001) — não reimplementa a
fórmula de custo. Prova algébrica em `RUNBOOK-report.md §1`.
**Custo: o menor da fila** — leitura pura, uma tabela agrupada + barra proporcional, zero escrita.

**Implementado em:** `resources/js/Pages/Manufacturing/Report.tsx` ·
`Modules/Manufacturing/Http/Controllers/ProductionController.php` (`@reportV2`) ·
`Modules/Manufacturing/Services/ProductionService.php` (`reportByProduct`) — rota ADITIVA
`/manufacturing/v2/report` (`/manufacturing/report` Blade segue intocado; sem decisão [W] sobre
tomar o endereço legado, como aconteceu em Recipes)

**Testado em:** `Modules/Manufacturing/Tests/Feature/Wave30ReportInertiaTest.php`
(`@covers-us US-MANU-002`) — ⚠️ escrito e com `php -l` limpo, **ainda não rodou** (Pest só roda
no CT 100, proibicoes.md). Verificação real fica pendente até a próxima sessão rodar lá.

**Definition of Done:**
- [x] Período (De/Até) + `Só finalizadas` com default **ligado** (`ProductionController::reportV2`)
- [x] Agrupa por produto: ordens · quantidade · custo total · custo médio · `% do período` com barra
- [x] Ordenado por custo desc
- [x] Rodapé verbatim: `Custo de produção do período R$ X · lançado como entrada de estoque no Financeiro`
- [x] Divisão por zero no `% do período` devolve 0 (§7.3)
- [ ] Pest rodado no CT 100 (verde) — pendente
- [ ] Smoke real em prod (`curl` + screenshot) — pendente, receita em `RUNBOOK-report.md §3`

### US-MANU-003 · Configurações do módulo

**Como** Wagner
**Quero** ajustar prefixo da referência e as duas travas de produção sem abrir a tela antiga
**Para** não sair do cockpit no meio da operação

**Fonte:** handoff §4.7 (cartões 1 e 3 — o cartão 2 é ferramenta do protótipo e **não existe** no app).
**Backend: já existe** — `SettingsController@index/@store`. **Custo: baixo** — 3 campos, mas **escreve**.
Confirmado ao construir (2026-09-03): o `store()` já lê exatamente as 3 chaves e já grava
scoped (`Business::where('id', $business_id)`), e o `redirect()->back()` dele já é compatível
com Inertia — **nenhuma linha do backend de escrita mudou**. Só o `index()` ganhou variante
Inertia (`indexV2`).

**Implementado em:** `resources/js/Pages/Manufacturing/Settings.tsx` ·
`Modules/Manufacturing/Http/Controllers/SettingsController.php` (`@indexV2` — o `@store` é
reusado sem alteração) — rota ADITIVA `/manufacturing/v2/settings` (`/manufacturing/settings`
Blade segue intocado; o POST continua no endereço legado)

**Testado em:** `Modules/Manufacturing/Tests/Feature/Wave31SettingsInertiaTest.php`
(`@covers-us US-MANU-003`) — ⚠️ escrito, `php -l` limpo, **ainda não rodou** (Pest roda na lane
de CI da PR).

**Definition of Done:**
- [x] As 3 chaves reais de `business.manufacturing_settings` (§16): `ref_no_prefix` ·
      `disable_editing_ingredient_qty` · `enable_updating_product_price`
- [x] Botão `Atualizar` **desabilitado enquanto nada mudou** (R-24) — e durante o envio
- [x] Rodapé com a versão do módulo (`System::getProperty('manufacturing_version')`)
- [x] Cartão "Integrações" (3 links reais: `/products` · `/purchases` · `/manufacturing/v2/production`) ·
      o cartão de permissões simuladas **não** entra
- [x] Escrita scoped por `business_id` (herdada do `store()` legado, com UC-CFG-04 travando)
- [ ] Pest verde na lane de CI — pendente
- [ ] Smoke real em prod (`curl` + screenshot + **submit de verdade**) — pendente, receita em
      `RUNBOOK-settings.md §3`

### US-MANU-004 · Ordens de produção — as 8 colunas e as duas marcas do §4.5

**Como** Eliana (produção)
**Quero** ver o produto, a quantidade e o custo unitário de cada ordem, e saber quando o custo está congelado
**Para** conferir o lote sem abrir uma a uma

**Fonte:** handoff §4.5 + o diff do §15.1. **A tela JÁ EXISTE** (`Pages/Manufacturing/Index.tsx`,
Wave J) — esta onda é **emenda**, não tela nova. **Custo: médio-baixo.**

**Implementado em:** `resources/js/Pages/Manufacturing/Index.tsx` (emenda) ·
`Modules/Manufacturing/Services/ProductionService.php` (`enrichProductionRows` — enriquecimento
em LOTE) · `resources/js/Components/shared/StatusBadge.tsx` (domínio `producao` novo)

**Testado em:** `Modules/Manufacturing/Tests/Feature/Wave32ProducaoColunasTest.php`
(`@covers-us US-MANU-004`) — 5 UC; Pest roda na lane de CI da PR.

> ⚠️ **O "congelado" desta tela, com o dado que EXISTE hoje:** o protótipo distingue custo
> `vivo` de `congelado` (`op.custoSnap`), mas **`custoSnap` não existe no banco** — conferido:
> `transactions` só tem `mfg_production_cost`/`mfg_production_cost_type`/`mfg_wasted_units`/
> `mfg_is_final`. Quem o introduz é a US-MANU-007. Então esta tela mostra
> `transactions.final_total` (o valor GRAVADO na criação) e usa o `fix` para marcar a ordem
> finalizada. Consequência declarada: o rodapé soma valor gravado, **diferente** do Relatório
> (US-MANU-002), que recalcula pelo preço de hoje. Detalhe em `RUNBOOK-producao.md §1`.

**Definition of Done:**
- [x] 8 colunas do §4.5 (hoje são 5): + Produto com `N ingredientes · quem lançou`, Qtd, Custo unit.
- [x] Sufixo `fix` em ordem finalizada, com `title="custo congelado na data da produção"` (R-21)
- [x] Rodapé: `N ordens · custo do período R$ X · ordens finalizadas mostram o custo congelado na data`
- [x] `Só finalizadas` como checkbox (hoje só existe como KPI clicável) — os dois governam o mesmo filtro
- [x] `StatusPill` local → `StatusBadge kind="producao"` (domínio adicionado ao componente compartilhado)
- [x] Os 4 KPIs atuais **não** mudam — os de §4.2 são da aba Receitas
- [x] **Achado ao construir:** o `optional($p->location)->name` do map era **N+1 desde a Wave J**
      (uma query por linha). Corrigido com eager-load de `location`; UC-OP-03 trava a regressão.
- [ ] Pest verde na lane de CI — pendente
- [ ] Smoke real em prod (`curl` + screenshot da tabela de 8 colunas) — pendente

### US-MANU-005 · Insumos — impacto reverso e simulador de preço

**Como** Wagner
**Quero** ver quais receitas usam um insumo e quanto o custo delas sobe se ele reajustar
**Para** decidir compra e preço antes do fornecedor mandar a tabela nova

**Fonte:** handoff §4.4. **Backend: NÃO existe** — o §18.3 é explícito: *"`usosDoInsumo()` é
cálculo novo; precisa de um método no `RecipeBomService` com o JOIN de tenant e teste.
Sem isso, a aba não sai."* **Custo: médio** — leitura pura, mas nasce com backend novo e Tier 0.

**Implementado em:** _pendente_ — bloqueada pelo backend

**Definition of Done:**
- [ ] `RecipeBomService::usosDoInsumo($variationId, $businessId)` com o JOIN de tenant
      (`mfg_recipes → variations → products.business_id`) e teste que prova o isolamento
- [ ] Tabela: nome · código · custo/unidade · estoque · **nº de receitas que o usam** · **maior peso**
- [ ] Insumo sem receita mostra `—` e `sem receita`, e **não é clicável**
- [ ] Drawer com simulador `-30%..+60%`, passo 5, default `+10%`
- [ ] Nota verbatim sobre o consumo convertido à unidade base
- [ ] A aba só aparece quando o backend existir — nada de aba vazia

### US-MANU-006 · Editor de ingredientes

**Como** Wagner
**Quero** montar e corrigir a ficha técnica com o custo se atualizando na tela
**Para** fechar o preço sem exportar pra planilha

**Fonte:** handoff §5 (6 regras numeradas `[FECHADA]`). **Backend: já existe** —
`RecipeController@addIngredients/@store/@update/getIngredientRow`.
**Custo: alto** — é a maior UI da família (tela dentro da tela) e **escreve a receita**.

**Implementado em:** _pendente_

**Definition of Done:**
- [ ] As 6 regras `[FECHADA]` do §5, incluindo: salvar exige ≥1 ingrediente ·
      `disable_editing_ingredient_qty` vira texto **nos dois lugares** · trocar sub-unidade troca o
      multiplicador junto · o editor trabalha numa **cópia** e cancelar descarta
- [ ] Excluir receita só com confirmação que diz o que se perde (ficha + N ingredientes) e que
      **ordens já lançadas continuam com o custo registrado**
- [ ] Sem permissão de editar: campos desabilitados + aviso citando `manufacturing.access_recipe`
- [ ] Servidor **recalcula** o custo antes de gravar (§9) — nunca confia no total do cliente

### US-MANU-007 · Formulário de ordem de produção

**Como** Eliana (produção)
**Quero** lançar o lote com o consumo já calculado e ver o que falta de estoque
**Para** decidir produzir sabendo o que vou ficar devendo

**Fonte:** handoff §6 (6 regras numeradas `[FECHADA]`). **Backend: já existe** —
`ProductionController@create/@store/@update`. **Custo: o maior da fila, e o mais arriscado** —
escreve **e movimenta estoque**, e o §18.7 avisa que **não há FSM**: *"rascunho→finalizada precisa
de caminho explícito antes de ir a produção"*.

**Implementado em:** _pendente_ — ver o pré-requisito de FSM abaixo

**Definition of Done:**
- [ ] As 6 regras `[FECHADA]` do §6, incluindo: trocar receita/quantidade **zera os overrides** ·
      estoque insuficiente **avisa e não bloqueia** · rascunho **não** movimenta estoque ·
      finalizar **congela** o custo do dia
- [ ] Ordem finalizada mostra os DOIS números (congelado × mesma receita hoje) + a variação %
- [ ] Finalizar é **transação atômica** (ordem + entrada do produto + baixa dos ingredientes) — §9
- [ ] `custoSnap` gravado **no servidor**, nunca vindo do cliente
- [ ] **Pré-requisito de decisão [W]:** o caminho rascunho→finalizada. O charter atual proíbe
      `UPDATE` direto em `transactions` e o trait de FSM de Sells/Repair não cobre Manufacturing.
      Isto é Tier 0 de ESTOQUE — a REGRA MESTRE de valor/estoque se aplica inteira: dupla prova +
      tabela antes→depois + aprovação explícita antes de qualquer escrita em prod.

> **O que NÃO vira onda** (declarado pra não voltar como "esqueceram"):
> - **Ficha técnica PT-07 (§8)** — já entregue na US-MANU-001, no cliente, nas duas variantes.
>   O §15.2 propunha uma rota de servidor (`/ficha?sem_custo=`); não é necessária.
> - **"Atualizar preço de venda" em massa** — §18.1 proíbe o `custo × 2` do protótipo e a regra
>   real de markup **não foi decidida**. Só entra depois que [W] decidir a regra, e aí sob a
>   REGRA MESTRE de valor.

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-MANU-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Manufacturing
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** `Modules/Manufacturing/Tests/Feature/MultiTenantIsolationTest.php` (cadeia `mfg_recipes.variation_id → variations.product_id → products.business_id`) — corrigido 2026-09-03, o teste já existia e a linha estava desatualizada.

### R-MANU-002 · Autorização Spatie `manufacturing.access_recipe`

```gherkin
Dado que um usuário **não** tem a permissão `manufacturing.access_recipe`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('manufacturing.access_recipe')`  
**Testado em:** `Modules/Manufacturing/Tests/Feature/PermissionsTest.php` (dataset `manufacturing_permission_routes` — GET `/manufacturing/recipe`), criado 2026-09-03. Lane MySQL only (auto-skip em sqlite).

### R-MANU-003 · Autorização Spatie `manufacturing.add_recipe`

```gherkin
Dado que um usuário **não** tem a permissão `manufacturing.add_recipe`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('manufacturing.add_recipe')`  
**Testado em:** `Modules/Manufacturing/Tests/Feature/PermissionsTest.php` (dataset `manufacturing_permission_routes` — GET `/manufacturing/recipe/create`), criado 2026-09-03.

### R-MANU-004 · Autorização Spatie `manufacturing.edit_recipe`

```gherkin
Dado que um usuário **não** tem a permissão `manufacturing.edit_recipe`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

> ⚠️ **Achado 2026-09-03 (medido, não suposto):** hoje **não existe rota** que exercite este
> gate. `Routes/web.php` faz `Route::resource('/recipe', ...)->except('edit', 'update')`, e
> nenhuma rota manual referencia `UpdateRecipeRequest` (onde o `can('manufacturing.edit_recipe')`
> vive). O gate é código real, só sem porta HTTP que o alcance — o gherkin acima descreve um
> cenário hoje inalcançável via navegador.

**Implementação:** `UpdateRecipeRequest::authorize()` checa `$user->can('manufacturing.edit_recipe')` — classe **não wired** a nenhuma rota  
**Testado em:** `Modules/Manufacturing/Tests/Feature/PermissionsTest.php` (par permission+`can()` + trava que quebra se uma rota PUT/PATCH `/manufacturing/recipe/{id}` reaparecer), criado 2026-09-03. Não é teste HTTP — não há rota pra testar.

### R-MANU-005 · Autorização Spatie `manufacturing.access_production`

```gherkin
Dado que um usuário **não** tem a permissão `manufacturing.access_production`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('manufacturing.access_production')`  
**Testado em:** `Modules/Manufacturing/Tests/Feature/PermissionsTest.php` (dataset `manufacturing_permission_routes` — GET `/manufacturing/production`), criado 2026-09-03.
