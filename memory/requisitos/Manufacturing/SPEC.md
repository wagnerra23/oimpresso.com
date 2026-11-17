---
id: requisitos-manufacturing-spec
module: Manufacturing
owner: wagner
version: "1.1"
last_updated: "2026-11-17"
na_justified:
  D6.a: "Manufacturing usa pattern Blade legacy + 1 página Inertia v2 (Wave J Onda 1) — Inertia::defer aplicado parcialmente."
---

# Especificação funcional

## 3. User stories

> Convenção do ID: `US-MANU-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

> **Conferência de 2026-11-17 (lote 4.1 da auditoria do módulo).** Status de cada US medido no
> `origin/main` `18fd05d4bf`, depois dos lotes 0 a 3 da auditoria. Lane `PHP / Pest
> (Manufacturing · MySQL)` no main: run 44074565768, **281 passed, 0 skipped (1223 assertions)**.
> Os itens "Pest pendente" abaixo foram fechados por essa run; o smoke em produção das telas
> continua pendente onde estava. A linha `> owner: … status: …` de cada US é o formato que o
> parser do MCP lê (`TaskParserService`); o estado vivo segue no MCP (ADR 0144).

### US-MANU-001 · Quanto custa produzir, com o preço de insumo de hoje

> owner: — · priority: p2 · status: done

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
- [x] Nenhuma rota Blade legada removida; `?legacy=1` devolve a tela antiga no mesmo endereço —
      verdade até o cutover de 2026-10-27, que apagou a Blade de receitas: desde então
      `/manufacturing/recipe` só serve a tela React e o `?legacy=1` não existe mais nesse endereço
      (`RecipeController.php`, comentário do `@index`). Segue valendo em production, report e settings
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

> owner: — · priority: p2 · status: review

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
`Modules/Manufacturing/Services/ProductionService.php` (`reportByProduct`) — endereço canônico
`/manufacturing/report` desde o **cutover de 2026-09-04** (pedido [F], "sem rotas alternativas";
nasceu aditiva em `/v2/report`, que virou 301). O Blade responde no MESMO endereço com
`?legacy=1` — nenhuma rota removida.

**Testado em:** `Modules/Manufacturing/Tests/Feature/Wave30ReportInertiaTest.php`
(`@covers-us US-MANU-002`) — verde na lane `PHP / Pest (Manufacturing · MySQL)` (run 44074565768,
2026-11-17). O gate de acesso nos três ramos (tela, AJAX e `?legacy=1`) é o UC-REPORT-06,
em `GateDoModuloTest.php` (lote 2.1).

**Definition of Done:**
- [x] Período (De/Até) + `Só finalizadas` com default **ligado** (`ProductionController::reportV2`)
- [x] Agrupa por produto: ordens · quantidade · custo total · custo médio · `% do período` com barra
- [x] Ordenado por custo desc
- [x] Rodapé verbatim: `Custo de produção do período R$ X · lançado como entrada de estoque no Financeiro`
- [x] Divisão por zero no `% do período` devolve 0 (§7.3)
- [x] Pest verde — lane `PHP / Pest (Manufacturing · MySQL)`, run 44074565768
- [ ] Smoke real em prod (`curl` + screenshot) — pendente, receita em `RUNBOOK-report.md §3`.
      É o único item aberto, e por isso o status é `review`, não `done`

### US-MANU-003 · Configurações do módulo

> owner: — · priority: p2 · status: review

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
reusado sem alteração) — endereço canônico `/manufacturing/settings` desde o **cutover de
2026-09-04** (nasceu em `/v2/settings`, que virou 301). O Blade responde no MESMO endereço com
`?legacy=1`; o POST nunca mudou.

**Testado em:** `Modules/Manufacturing/Tests/Feature/Wave31SettingsInertiaTest.php`
(`@covers-us US-MANU-003`) — verde na lane `PHP / Pest (Manufacturing · MySQL)` (run
44074565768, 2026-11-17). Desde o lote 2.1 da auditoria, abrir e gravar exigem
`manufacturing.access_production` (decisão B3 do [W], 2026-11-09), travado pelo UC-MCFG-05 em
`GateDoModuloTest.php`; a escrita só na empresa da sessão é o UC-MCFG-04, por HTTP, em
`IsolamentoEscritasEmpresaTest.php`.

**Definition of Done:**
- [x] As 3 chaves reais de `business.manufacturing_settings` (§16): `ref_no_prefix` ·
      `disable_editing_ingredient_qty` · `enable_updating_product_price`
- [x] Botão `Atualizar` **desabilitado enquanto nada mudou** (R-24) — e durante o envio
- [x] Rodapé com a versão do módulo (`System::getProperty('manufacturing_version')`)
- [x] Cartão "Integrações" (3 links reais: `/products` · `/purchases` · `/manufacturing/production`) ·
      o cartão de permissões simuladas **não** entra
- [x] Escrita scoped por `business_id` (herdada do `store()` legado, com UC-MCFG-04 travando)
- [x] Pest verde na lane de CI — run 44074565768
- [x] Gravar exige permissão do módulo (`access_production`, B3) — UC-MCFG-05
- [ ] Smoke real em prod (`curl` + screenshot + **submit de verdade**) — pendente, receita em
      `RUNBOOK-settings.md §3`. Único item aberto: status `review`

### US-MANU-004 · Ordens de produção — as 8 colunas e as duas marcas do §4.5

> owner: — · priority: p2 · status: done

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

> 🟢 **Decisão [W] 2026-10-06 (supera o item acima nos 4 KPIs):** os indicadores da aba Ordens —
> Total · Finalizadas · Pendentes · **Valor total** — passam a **seguir o filtro de local e
> período**, como no protótipo. Hoje `ProductionService::summary` conta todas as ordens da empresa.
> Como o Valor total é dinheiro, vale a REGRA MESTRE ([proibicoes.md](../../proibicoes.md)): antes
> do merge, mostrar a [W] um filtro com o Valor total **antes→depois** e conferir que ele bate com a
> soma das ordens listadas (dois caminhos).

- [x] KPIs seguem o filtro de local e período (decisão [W] 2026-10-06) — teste que prove que ordem
      fora do filtro não entra em nenhum dos 4, e que o Valor total = soma das linhas listadas.
      **Feito 2026-10-07 (UC-OP-10):** `ProductionService::summary($biz, $filters)` + prop `kpis` na
      tela; `IndicadoresSeguemFiltroTest.php` confere por 2 caminhos (conta à mão × soma da lista).
      O contador da barra de abas segue com todas as ordens. ⚠️ "= soma das linhas listadas" só vale
      até 25 ordens: a lista para em 25 e os cartões contam todas as do recorte no banco.
- [x] Antes→depois do Valor total num filtro apresentado a [W] antes do merge (REGRA MESTRE) —
      medido na empresa 1 por dois caminhos (lista da tela nova × tela antiga `?legacy=1`), aprovado
      pelo [W] e mergeado no #8991 (2026-10-07). Os valores ficaram fora do git.
- [x] **Achado ao construir:** o `optional($p->location)->name` do map era **N+1 desde a Wave J**
      (uma query por linha). Corrigido com eager-load de `location`; UC-OP-03 trava a regressão.
- [x] Pest verde na lane de CI — `PHP / Pest (Manufacturing · MySQL)` verde no #8991
      (run 37681668353), com o `IndicadoresSeguemFiltroTest` (UC-OP-10).
- [x] Smoke real em prod — 2026-10-08, empresa 1, logado: tabela de 8 colunas e os 4 cartões
      medidos pelas props da tela (`kpis` × `summary`) com e sem filtro + screenshot. Os cartões
      recortam por período, o contador da aba segue com todas as ordens.

### US-MANU-005 · Insumos — impacto reverso e simulador de preço

> owner: — · priority: p2 · status: review

**Como** Wagner
**Quero** ver quais receitas usam um insumo e quanto o custo delas sobe se ele reajustar
**Para** decidir compra e preço antes do fornecedor mandar a tabela nova

**Fonte:** handoff §4.4. **Backend: NÃO existe** — o §18.3 é explícito: *"`usosDoInsumo()` é
cálculo novo; precisa de um método no `RecipeBomService` com o JOIN de tenant e teste.
Sem isso, a aba não sai."* **Custo: médio** — leitura pura, mas nasce com backend novo e Tier 0.

**Implementado em:** `resources/js/Pages/Manufacturing/Insumos.tsx` ·
`Modules/Manufacturing/Http/Controllers/RecipeController.php` (`@insumos`) ·
`Modules/Manufacturing/Services/RecipeBomService.php` (`usosDoInsumo` + `listInsumosComUso`) —
endereço canônico `/manufacturing/insumos` desde o **cutover de 2026-09-04** (nasceu em
`/v2/insumos`, que virou 301). **O backend que o §18.3 declarava faltar agora existe.**

**Testado em:** `Modules/Manufacturing/Tests/Feature/Wave33InsumosTest.php`
(`@covers-us US-MANU-005`) — verde na lane `PHP / Pest (Manufacturing · MySQL)` (run
44074565768, 2026-11-17).

> ⚠️ **Desvio DECLARADO do protótipo (a simulação):** o protótipo aproxima o custo simulado
> somando um delta (`total + qtd × preço × pct`). Esta tela **recalcula** com
> `calculateCost()` e o preço novo, porque o atalho **subestima** quando
> `production_cost_type = percentage` (o extra é % dos ingredientes e sobe junto). Medido:
> receita de 92,00 + 18% com insumo +10% dá **119,416** recalculando e **117,76** pelo atalho.
> Razão completa em `RUNBOOK-insumos.md §1`; travado por UC-INS-02.

> ⚠️ **"Insumo" aqui é derivado**, não entidade: é toda variação que aparece como ingrediente
> nas receitas do tenant (o app não tem flag de matéria-prima). Efeito: o estado "sem receita"
> do protótipo não ocorre. Declarado em `RUNBOOK-insumos.md §2` — se [W] quiser o catálogo
> inteiro, é outra decisão de escopo.

**Definition of Done:**
- [x] `RecipeBomService::usosDoInsumo($variationId, $businessId)` com o JOIN de tenant
      (`mfg_recipes → variations → products.business_id`) e teste que prova o isolamento (UC-INS-01)
- [x] Tabela: nome · código · custo/unidade · estoque · **nº de receitas que o usam** · **maior peso**
- [~] Insumo sem receita mostra `—` e `sem receita`, e **não é clicável** — caminho de render
      presente, mas **inalcançável** com a lista derivada (RUNBOOK §2, declarado)
- [x] Drawer com simulador `-30%..+60%`, passo 5, default `+10%` — faixa clampada no SERVIDOR (UC-INS-05)
- [x] Nota verbatim sobre o consumo convertido à unidade base
- [x] A aba só aparece quando o backend existir — nada de aba vazia
- [x] Pest verde na lane de CI — run 44074565768
- [ ] Smoke real em prod (`curl` + screenshot + mexer no slider) — pendente. Único item aberto:
      status `review`

### US-MANU-006 · Editor de ingredientes

> owner: — · priority: p2 · status: done

**Como** Wagner
**Quero** montar e corrigir a ficha técnica com o custo se atualizando na tela
**Para** fechar o preço sem exportar pra planilha

**Fonte:** handoff §5 (6 regras numeradas `[FECHADA]`). **Backend: já existe** —
`RecipeController@addIngredients/@store/@update/getIngredientRow`.
**Custo: alto** — é a maior UI da família (tela dentro da tela) e **escreve a receita**.

**Implementado em:** `resources/js/Pages/Manufacturing/IngredientesEditor.tsx` · `resources/js/Pages/Manufacturing/_lib/custo.ts` · `resources/js/Pages/Manufacturing/_components/BuscaInsumo.tsx` · `resources/js/Pages/Manufacturing/_components/ExcluirReceitaDialog.tsx` · `Modules/Manufacturing/Http/Controllers/RecipeController.php` · `Modules/Manufacturing/Services/RecipeBomService.php` · verificado@ff67c83 (2026-10-09) — etapas 1-3 (#9059 · #9068 · #9072); o editor abre em `/manufacturing/add-ingredient?variation_id=N&tela=nova`, e sem `tela=nova` o endereço seguia servindo a janela Blade até o cutover de 2026-10-27, que apagou a Blade (desde então o endereço só serve este editor)

**Testado em:** `Modules/Manufacturing/Tests/Feature/EditorIngredientesTest.php` (servidor, UC-INGRED-01..07, lane `Manufacturing · Pest (MySQL)`) · `tests/js/manufacturing-ingredientes-editor.test.tsx` (tela, UC-INGRED-08..13, lane `manufacturing-jsdom-gate`)

**Definition of Done:**
- [x] As 6 regras `[FECHADA]` do §5 **no editor**: salvar exige ≥1 ingrediente (UC-INGRED-08) ·
      `disable_editing_ingredient_qty` vira texto (UC-INGRED-09) · trocar sub-unidade troca o
      multiplicador junto (UC-INGRED-11) · o editor trabalha numa **cópia** e cancelar descarta
      (UC-INGRED-12) — vitest/jsdom no componente real, 19/19 verde local em 2026-10-09, mordida
      provada por 8 mutações. O veredito que conta é o da lane no PR.
- [x] ~~Regra 2 no **segundo lugar** (formulário de ordem de produção)~~ — **sai do escopo desta
      US.** O item dizia "entra com a US-MANU-007", que foi cancelada em 2026-10-13 (D1, ADR 0448):
      o formulário de ordem não será construído na Fabricação. O formulário Blade que ainda existe
      segue com o campo `readonly`; a regra no formulário novo é da ordem na Produção (ADR 0448 §5).
- [x] Excluir receita só com confirmação que diz o que se perde (ficha + N ingredientes **gravados**)
      e que **ordens já lançadas continuam com o custo registrado** — UC-INGRED-13 (tela) + caso 19
      de `Recipes.casos.md` (servidor só apaga receita da própria empresa, #9071).
- [x] Sem permissão de editar: campos desabilitados + aviso citando `manufacturing.access_recipe` —
      UC-INGRED-10 (tela) + UC-INGRED-03 (servidor recusa a gravação e a busca de insumo).
- [x] Servidor **recalcula** o custo antes de gravar (§9) — nunca confia no total do cliente.
      Junto, no mesmo salvar: recusa receita sem ingrediente (regra 1 no servidor), quantidade ≤ 0
      e sub-unidade que não é do insumo; trocar todos os ingredientes não deixa os antigos —
      #9051, UC-RECIPE-14..18 em `SalvarReceitaServidorTest.php` (lane `Manufacturing · Pest (MySQL)`
      run 37799242847: 185 passed / 604 assertions, os 7 casos novos com ✓). A tela do editor
      era a Blade na data deste item; o editor React foi entregue depois (#9059 · #9068 · #9072) e as regras do §5 entraram com ele.

> 🟢 **Decisões [W] 2026-10-06 — janela "Nova receita"** (hoje o botão está quebrado em produção:
> a busca de produto não carrega):
> - **(a)** Categoria e Subcategoria só **exibidas**, vindas do produto, com link para editar no
>   cadastro. Elas pertencem ao produto, não à receita.
> - **(b)** A **cópia de receita** leva ingredientes, quantidades, desperdício, custo extra e
>   instruções — **não** o preço de venda. Preço só muda pelo "Atualizar preço de venda". A política
>   de preço que acompanha o custo é outra US: [US-PROD-030](../Produto/SPEC.md).
> - **(c)** Produto que **já tem receita**: avisa **antes** de a pessoa começar a preencher e abre a
>   receita existente, em vez de bloquear.
> - **Correção junto:** a cópia hoje **compartilha os grupos** de ingredientes com a receita original
>   (renomear numa renomeia na outra). A cópia passa a criar grupos próprios.

- [x] "Nova receita": busca de produto carregando (o defeito em produção) + (a)/(b)/(c) acima —
      #8853 (2026-10-07), com os ajustes #8914 (lista cabe na janela) e #8956 (trocar produto limpa
      a busca). UC-RECIPE-12/13 em `NovaReceitaTest.php`.
- [x] Cópia de receita com grupos próprios — teste que renomeia o grupo da cópia e prova que o da
      original não muda, e que o preço de venda do destino fica intocado — #8853,
      `NovaReceitaTest.php` (UC-RECIPE-13).

### US-MANU-007 · Formulário de ordem de produção

> owner: — · priority: p2 · status: cancelled

> ⛔ **Cancelada em 2026-10-13** (D1 do [W], ratificada na [ADR 0448](../../decisions/0448-fabricacao-cadastro-producao-execucao-product-bom-fonte-unica.md)).
> O MCP a marcava `done` desde 2026-10-08, antes do cancelamento; corrigido para `cancelled` em
> 2026-11-17. O texto abaixo fica como registro do que foi pedido.
>
> **O que esta US carregava e onde foi parar:**
> - Custo da ordem gravado pelo navegador (`final_total` do request, contra o §9 do handoff):
>   ficou sem dono com o cancelamento e foi fechado no lote 1.5 da auditoria — o servidor calcula
>   o total no `store` e no `update` (UC-OP-19, `OrdemTotalServidorTest.php`).
> - Finalizar baixa o insumo e dá entrada no acabado: travado pelo UC-EST-08B
>   (`OrdemFinalizarEstoqueTest.php`, lote 1.1) no formulário Blade que ainda existe.
> - Formulário novo, FSM rascunho→finalizada, `custoSnap`, os dois números: ficam com a ordem
>   na Produção (ADR 0448 §5, etapa 6).

**Como** Eliana (produção)
**Quero** lançar o lote com o consumo já calculado e ver o que falta de estoque
**Para** decidir produzir sabendo o que vou ficar devendo

**Fonte:** handoff §6 (6 regras numeradas `[FECHADA]`). **Backend: já existe** —
`ProductionController@create/@store/@update`. **Custo: o maior da fila, e o mais arriscado** —
escreve **e movimenta estoque**, e o §18.7 avisa que **não há FSM**: *"rascunho→finalizada precisa
de caminho explícito antes de ir a produção"*.

**Implementado em:** _pendente_ — ver o pré-requisito de FSM abaixo

**Paridade com a Blade:** [PARIDADE-ordem-producao-blade-vs-prototipo.md](PARIDADE-ordem-producao-blade-vs-prototipo.md)
— 26 itens comparados (2026-10-09). 4 mudam custo ou estoque se a tela for construída igual ao
protótipo (desperdício da ordem, custo extra fixo, custo gravado pelo navegador, bloqueio por
estoque) e são decisões [W] **antes** de construir.

> 🔁 **Revisão do [W] (2026-10-09, no #9086):** Fabricação e Produção ficam em lugares diferentes.
> A proposta em decisão recomenda **cancelar** esta US: a ordem nasce no módulo de Produção (a
> criar) e a aba da Fabricação fica só para consulta —
> [2026-10-09-fabricacao-vs-producao-decisao-w.md](../../decisions/proposals/2026-10-09-fabricacao-vs-producao-decisao-w.md).
> Até o [W] decidir, a US segue travada. Tem também 2 hipóteses
de segurança no servidor (H1 `store`, H2 `get-recipe-details`), ainda sem teste.

> ✅ **Decidido pelo [W] em 2026-10-13** (D1 sim · D2 `product_bom` · D3 a D6 recomendado): Fabricação é cadastro e a
> ordem nasce no módulo Produção. **Esta US está cancelada** — o formulário de ordem não será construído aqui. A trava de
> FSM acima deixa de ser pré-requisito desta tela. As hipóteses H1/H2 seguem valendo para o `store` legado enquanto ele existir
> (ver US-MANU-008).
>
> 🔁 **Revisão de 2026-10-21, aprovada pelo [W]:** a prévia da Fabricação foi repartida item a item
> entre Fabricação, Produção e Estoque/Compras, e a ADR do §4 foi autorizada com 4 perguntas
> obrigatórias (regra de consumo no `product_bom`, contrato `InsumosDaReceita` lendo do `product_bom`
> e recebendo as medidas da peça, tamanho físico da mídia num lugar só, chave única para opcional) —
> [§6 da proposta](../../decisions/proposals/2026-10-09-fabricacao-vs-producao-decisao-w.md).

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

### US-MANU-008 · Rotas legadas só tocam dado da própria empresa

> owner: — · priority: p2 · status: done

**Como** Wagner
**Quero** que nenhuma rota legada da Fabricação leia ou grave dado de outra empresa
**Para** não vazar receita nem alterar preço ou estoque alheio

**Fonte:** leitura de código em 2026-10-20 (acompanhamento da Fabricação). Os itens H1/H2 vêm do
PARIDADE; H3 e o último são leitura nova. Escritos como hipótese; viraram defeito provado por teste
vermelho e foram consertados no lote 0 (H1, H2, H3) e no lote 2.1 (Settings) da auditoria.

> ⚠️ O MCP marcou esta US `done` em 2026-10-20, no dia em que foi escrita, com os 5 itens abertos.
> A auditoria de 2026-11-09 previu reabri-la; quando o lote 4 conferiu (2026-11-17), os 5 itens já
> estavam fechados no main, com teste. O `done` do MCP ficou certo, só que por outra razão; a prova
> agora está nas linhas abaixo.

**Implementado em:** `Modules/Manufacturing/Http/Controllers/ProductionController.php` (`@store`,
`@update`) · `Modules/Manufacturing/Http/Controllers/RecipeController.php` (`@getRecipeDetails`,
`@updateRecipeProductPrices`) · `Modules/Manufacturing/Http/Controllers/SettingsController.php`
(`@store`) · verificado@18fd05d4bf (2026-11-17)

**Testado em:** `Modules/Manufacturing/Tests/Feature/OrdemReceitaEmpresaTest.php` (UC-OP-12,
UC-OP-13) · `Modules/Manufacturing/Tests/Feature/OrdemProdutoLocalEmpresaTest.php` (UC-OP-14) ·
`Modules/Manufacturing/Tests/Feature/AtualizarPrecoReceitaEmpresaTest.php` (UC-RECIPE-21) ·
`Modules/Manufacturing/Tests/Feature/GateDoModuloTest.php` (UC-MCFG-05) — todos verdes na run
44074565768

**Definition of Done:**
- [x] H1 — `ProductionController::store` recusa `variation_id` e `location_id` de outra empresa e não
      cria transação nem baixa estoque — variação: UC-OP-13; local, no POST e no PUT: UC-OP-14
- [x] H2 — `RecipeController::getRecipeDetails` devolve 404 para receita de outra empresa — UC-OP-12
- [x] H3 — `RecipeController::updateRecipeProductPrices` só altera variação de receita da própria
      empresa — UC-RECIPE-21 (receita alheia não muda o preço; chamada mista só altera a própria)
- [x] `SettingsController::store` exige permissão do módulo — `manufacturing.access_production`,
      decisão B3 do [W] em 2026-11-09 — UC-MCFG-05
- [x] Teste de cada item com a empresa fictícia 98 contra a 99, na lane `Manufacturing · Pest (MySQL)`

### US-MANU-009 · Painel lateral da ordem de produção

> owner: — · priority: p2 · status: done

**Como** Eliana (produção)
**Quero** abrir uma ordem da lista e ver, ao lado, os ingredientes, o consumo e o custo dela
**Para** conferir o lote sem sair da tela de Ordens

**Fonte:** handoff §4.5 (drawer da ordem). Registrada em 2026-11-17: o código existia sem US
(achado M3 da auditoria de 2026-11-09). O caso é o UC-OP-07 de `Index.casos.md`.

**Implementado em:** `resources/js/Pages/Manufacturing/_components/OrdemDrawer.tsx` ·
`Modules/Manufacturing/Http/Controllers/ProductionController.php` (`@indexV2`, prop opcional
`ordem_detalhe`) · `Modules/Manufacturing/Services/ProductionService.php` (`detalheOrdem`) ·
verificado@18fd05d4bf (2026-11-17)

**Testado em:** `Modules/Manufacturing/Tests/Feature/DetalheOrdemProducaoTest.php` (UC-OP-07) — verde
na run 44074565768

**Definition of Done:**
- [x] O detalhe usa as mesmas contas do formulário da tela antiga nas três formas de custo extra
      (percentual, por unidade com as perdidas, fixo)
- [x] Ordem de outra empresa não tem detalhe
- [x] O detalhe só é calculado quando a tela o pede (`Inertia::optional`): a carga da lista não paga
- [x] Ordem fora dos locais permitidos ao usuário: o detalhe dá 403 (UC-OP-22, lote 2.5)
- [x] Sem `view_purchase_price`, o custo sai do detalhe (lote 2.6, `Support/OcultaCusto::detalheOrdem`;
      o caso UC-OP-24 cobre a lista, não o painel)

> Pendente fora desta US: o painel ainda tem o botão "Editar ordem" no rascunho
> (`OrdemDrawer.tsx`, abre o formulário Blade). Pela D1 a aba vira só consulta; pela B1 do [W]
> (2026-11-09) a ordem Blade segue consertada enquanto existir. Os botões da ordem nas telas são
> do lote 5 da auditoria.

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-MANU-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Manufacturing
Então só vê registros com `business_id = A`
```

**Implementação:** `mfg_recipes` e `mfg_recipe_ingredients` não têm `business_id` nem global scope;
a barreira é a cadeia até `products.business_id` (receita) e o `business_id` da `transactions`
(ordem). Só `mfg_ingredient_groups` tem `business_id` próprio, com `HasBusinessScope`. Conferidos
em cada consulta e gravação dos controllers e services. Corrigido em 2026-11-17: a linha anterior
dizia `where('business_id', …)` nos controllers, que não descreve as receitas.  
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
**Testado em:** `Modules/Manufacturing/Tests/Feature/PermissionsTest.php` (dataset `manufacturing_permission_routes` — GET `/manufacturing/recipe/create` de 2026-09-03 a 2026-10-26; desde o cutover de 2026-10-27, que apagou essa rota, GET `/manufacturing/nova-receita/produtos`).

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

**Implementação:** nenhuma. Até 2026-11-16 o `UpdateRecipeRequest::authorize()` checava a
permissão, sem rota que o alcançasse; a classe saiu no lote 2.8 da auditoria (código morto, medido).
A permissão segue cadastrada e exibida na tela de papéis (`DataController`); quem grava receita é o
`store()`, que exige `manufacturing.add_recipe`  
**Testado em:** `Modules/Manufacturing/Tests/Feature/PermissionsTest.php` (par permission+`can()` + trava que quebra se uma rota PUT/PATCH `/manufacturing/recipe/{id}` reaparecer), criado 2026-09-03. Não é teste HTTP — não há rota pra testar.

### R-MANU-005 · Autorização Spatie `manufacturing.access_production`

```gherkin
Dado que um usuário **não** tem a permissão `manufacturing.access_production`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('manufacturing.access_production')`  
**Testado em:** `Modules/Manufacturing/Tests/Feature/PermissionsTest.php` (dataset `manufacturing_permission_routes` — GET `/manufacturing/production`), criado 2026-09-03.
