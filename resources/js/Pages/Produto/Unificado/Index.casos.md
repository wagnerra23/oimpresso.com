---
id: resources-js-pages-produto-unificado-index-casos
casos: Catálogo Unificado · /products/unificado
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela reúne, numa rota só, tudo que as outras telas do Produto gateiam separadamente — custo, preço de venda, tabelas de preço e composição. Sem casos, ela vira o caminho por onde tudo isso sai sem permissão.
owner: wagner
last_run: "2026-08-19"
last_run_ci: "10/10 UC verdes na lane Estoque · MySQL (run 32246398030, workflow_dispatch sobre main d4ad0804): revalidação após o #5920 reescrever a barra de filtros da tela (161 linhas em Index.tsx). ProdutoUnificadoContratoTest 7/7 (40 assertions) + ProdutoUnificadoIndiceContratoTest 4/4 (38 assertions), 0 skipped; suíte da lane 77/77 com provou_algo=true (guard LC-13). Os 10 ids UC-PUNI-01..10 conferidos um a um no JUnit da run (artifact pest-estoque-junit), não declarados à mão. Run anterior: 32141318494 (PR #5906)."
---

# Casos de Uso & Aceite — Catálogo Unificado (`/products/unificado`)

> **Âncora:** `CU-PROD-15` (consultar/listar o catálogo) e `CU-PROD-10` `[T0]` (multi-tenant) do
> [SDD §6.1](../../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md), cruzados com
> o **contrato de paridade Delphi** (`AR-PROD-015` — custo e margem **somem** da tela sem permissão)
> e com os **irmãos já contratados** desta mesma família: `UC-PIDX-03`
> ([Index.casos.md](../Index.casos.md)) e `UC-PSHOW-01` ([Show.casos.md](../Show.casos.md)).
> Os UCs derivam do **contrato**, nunca do `Index.tsx` nem do controller — teste derivado do código
> é tautológico (`proibicoes.md` §5).
>
> **Por que este arquivo nasce agora (2026-08-11):** pedido de [M] — *"a informação da tabela de
> preço que foi aplicada ao produto e o seu custo são informações que podem ou não aparecer no
> cadastro do produto, vai de como o administrador preferir. Da mesma forma, as matérias-primas
> dentro da composição. Isso deve constar na documentação e também existir testes para evitar a
> regressão dessa configuração."* Esta tela era a **única** da família Produto sem `.casos.md` — o
> trio (`.tsx` + `.charter` + `.casos`) estava incompleto justamente onde tudo converge.

## O que foi MEDIDO no controller em 2026-08-11 — o estado ANTES do gate

> 🕐 **Retrato datado, não o estado de hoje.** A tabela abaixo é o que o
> `ProdutoUnificadoController` fazia **antes** do [PR #5733](https://github.com/wagnerra23/oimpresso.com/pull/5733)
> (mergeado em 2026-08-13, commit `45d6795`). Ela fica preservada porque é a **razão de existir**
> destes casos — apagá-la esconderia por que o contrato foi escrito. O estado atual é o que os
> `Status: ✅` abaixo declaram, com o run id de recibo.

| Fato | Onde |
|---|---|
| Nada gateia a tela. O TODO pede middleware, mas o padrão canônico do módulo **não é middleware**: a lista irmã aborta 403 **dentro do controller** (`product.view` **ou** `product.create`) | `routes/web.php:449-451` + `ProductController@index:66` |
| `produtos()` monta `price`, `cost` e `margin` para **toda** linha, sem consultar permissão | `:122-124` |
| Varredura contada de `view_purchase_price\|access_default_selling_price` no controller | **0 ocorrências** |
| `historico()` devolve `value` = qty × `unit_price_inc_tax` — preço de venda por linha, sem gate | `:249`, `:260` |
| `tabelas()` devolve `SellingPriceGroup` do business, sem gate | `:216-226` |
| Composição (BOM) ainda **não é servida** — `bomCount` é literal `0` com TODO | `:130` |
| Insumo = produto com `not_for_selling = 1` (convenção com TODO "confirmar com Wagner") | `:193-196` |

> ⚠️ **Failing-first (como nasceu, 2026-08-11):** os asserts saem do contrato, não do código. Se
> nascerem vermelhos, o vermelho **é** o achado — não se ajusta o teste ao código. Por isso o teste
> nasceu na quarentena da lane (`.github/estoque-pest-quarantine.list`, bloco A).
>
> ✅ **Saiu da quarentena em 2026-08-13** ([PR #5733](https://github.com/wagnerra23/oimpresso.com/pull/5733)):
> o gate foi implementado e os 6 UCs viraram verdes rodando na lane — nunca por não-execução. A
> partir daqui o arquivo é **defesa contra regressão**, não inventário de dívida.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-PUNI-01 | Custo não chega ao navegador sem `view_purchase_price` | must | `AR-PROD-015` + `UC-PIDX-03` | `ProdutoUnificadoContratoTest` | ✅ verde — o custo e a margem não são emitidos sem `view_purchase_price` |
| UC-PUNI-02 | Preço de venda não chega sem `access_default_selling_price` — inclusive no Histórico | must | Blade `index.blade.php:294` + `UC-PIDX-03` | `ProdutoUnificadoContratoTest` | ✅ verde — `price` e o `value` do Histórico não são emitidos |
| UC-PUNI-03 | Tabelas de preço seguem o **mesmo** gate do preço de venda | must | decisão 2026-08-11 (abaixo) | `ProdutoUnificadoContratoTest` | ✅ verde — a prop `tabelas` nasce `[]` sem o direito |
| UC-PUNI-04 | Composição (BOM) só aparece com módulo Manufacturing **e** `manufacturing.access_recipe` | must | permissões `Modules/Manufacturing` + camada 1/3 ([feedback-habilitar-modulo-por-business](../../../../../memory/reference/feedback-habilitar-modulo-por-business.md)) | `ProdutoUnificadoContratoTest` | ✅ verde — `insumos` vazio e `bomCount` ausente sem as camadas 1+3 |
| UC-PUNI-05 | Nenhuma prop enxerga outro business | must `[T0]` | `CU-PROD-10.2` + [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) | `ProdutoUnificadoContratoTest` | ✅ verde — guard cross-tenant confirmado |
| UC-PUNI-06 | A tela exige `product.view` **ou** `product.create` | should | `ProductController@index:66` (a lista irmã) + `routes/web.php:449` (TODO) | `ProdutoUnificadoContratoTest` | ✅ verde — 403 sem `product.view` nem `product.create` |
| UC-PUNI-07 | O contador "Margem baixa" segue o gate do custo | must | handoff §9 + `AR-PROD-015` | `ProdutoUnificadoIndiceContratoTest` | ✅ verde — run 32141318494 |
| UC-PUNI-08 | A aba recorta por TIPO derivado e conta só ativos | must | handoff §4.2 + §6 exceção 6 | `ProdutoUnificadoIndiceContratoTest` | ✅ verde — run 32141318494 |
| UC-PUNI-09 | "Não estocável" e "sem estoque" são estados diferentes | must | handoff §4.6 + §6 exceção 6 | `ProdutoUnificadoIndiceContratoTest` | ✅ verde — run 32141318494 |
| UC-PUNI-10 | As agregações (abas · KPIs · total) não contam produto de outro business | must `[T0]` | [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) | `ProdutoUnificadoIndiceContratoTest` | ✅ verde — run 32141318494 |
| UC-PUNI-11 | A resposta é a FATIA da página, e o total continua sendo o do recorte | must | V2 §4.8 · §9 | `ProdutoUnificadoIndiceContratoTest` | 🕐 aguarda run |
| UC-PUNI-11B | Página 2 não repete nenhuma linha da página 1 | must | V2 §9 | `ProdutoUnificadoIndiceContratoTest` | 🕐 aguarda run |
| UC-PUNI-11C | Ordenar por custo é ignorado pra quem não pode ver custo | must `[V0]` | AR-PROD-015 | `ProdutoUnificadoIndiceContratoTest` | 🕐 aguarda run |
| UC-PUNI-11D | `porPagina` e `ordem` fora da lista branca caem no padrão | must | V2 §9 | `ProdutoUnificadoIndiceContratoTest` | 🕐 aguarda run |
| UC-PUNI-12 | Saldo por local so viaja com MAIS DE UM local, e a soma bate com o total | must | V2 §4.6 · §9 | `ProdutoUnificadoIndiceContratoTest` | 🕐 aguarda run |
| UC-PUNI-12B | Local de outro business nao entra no saldo por local | must `[T0]` | [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) | `ProdutoUnificadoIndiceContratoTest` | 🕐 aguarda run |
| UC-PUNI-13 | Observacao chega sem HTML, e a chave so existe quando ha nota | must | V2 §4.7 | `ProdutoUnificadoIndiceContratoTest` | 🕐 aguarda run |
| UC-PUNI-14 | A variacao-fantasma do UltimatePOS nao vira variacao na tela | must | V2 §3.2 | `ProdutoUnificadoIndiceContratoTest` | 🕐 aguarda run |

---

## UC-PUNI-01 · Custo não chega ao navegador sem `view_purchase_price` · `must`

- **Persona:** balconista de biz=1 com `product.view` e **sem** direito de ver custo. Abre o catálogo
  pra conferir SKU, prazo e disponibilidade. Quanto a empresa pagou pelo item **não é dele**.
- **Aceite:** Dado um usuário sem `view_purchase_price` · Quando abre `/products/unificado` · Então
  **nenhuma linha** carrega o custo — o valor **não chega ao navegador**. Não é coluna escondida por
  CSS, nem campo vazio: é ausência (`AR-PROD-015`: *"os dois campos somem da tela"*).
- **`margin` cai junto:** é derivada do custo (`:124`). Entregar a margem com o preço é entregar o
  custo por dedução.
- **Não é `[V0]`:** gateia **exibição**, não altera cálculo. A REGRA MESTRE não se aplica — marcar
  `[V0]` aqui inflaria o ritual sem proteger nada (mesma leitura de `UC-PIDX-03`).
- **Teste:** [`ProdutoUnificadoContratoTest`](../../../../../tests/Feature/Produto/ProdutoUnificadoContratoTest.php) — `UC-PUNI-01`.
- **Status: ✅** — verde na run `31706439580`. O `produtos()` só emite `cost` com `view_purchase_price`, e `margin` exige as DUAS permissões (deriva de custo e preço).

## UC-PUNI-02 · Preço de venda não chega sem `access_default_selling_price` · `must`

- **Persona:** operador de produção que consulta o catálogo pra saber prazo e composição, sem
  participar de negociação comercial.
- **Aceite:** Dado um usuário sem `access_default_selling_price` · Quando abre a tela · Então nem a
  linha do produto (`price`) nem a sub-tela **Histórico de uso** (`value`, derivado de
  `unit_price_inc_tax`) entregam preço de venda.
- **Por que o Histórico entra aqui:** é a porta lateral. Gatear a lista e deixar o histórico aberto
  entrega o mesmo dado por outro caminho, produto a produto.
- **Teste:** [`ProdutoUnificadoContratoTest`](../../../../../tests/Feature/Produto/ProdutoUnificadoContratoTest.php) — `UC-PUNI-02` e `UC-PUNI-02b`.
- **Status: ✅** — verde na run `31706439580`, nas duas pernas: a linha do catálogo e a porta lateral do Histórico.

## UC-PUNI-03 · Tabelas de preço seguem o mesmo gate do preço de venda · `must`

- **Persona:** a mesma do UC-PUNI-02. A sub-tela **Tabelas de preço** lista os `SellingPriceGroup`
  do business (Balcão · Atacado · Agência).
- **Aceite:** Dado um usuário sem `access_default_selling_price` · Quando abre a sub-tela Tabelas ·
  Então a lista de tabelas **não chega** ao navegador.

> **Decisão 2026-08-11 — considerada e adiada.** Foi avaliado criar uma **permissão própria** para
> "ver quais tabelas de preço existem / qual foi aplicada", separada de ver o preço em si. **Adiada**,
> com três motivos: (1) é o **mesmo dado** — preço de venda, agrupado por tabela; (2) permissão nova
> tem custo permanente (260+ já existem na tela de papéis, e cada uma precisa ser marcada em todo
> papel de todo business, pra sempre); (3) ninguém pediu a separação — criar agora é desenhar para
> hipótese, contra a regra de sinal qualificado ([ADR 0105](../../../../../memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)).
>
> **Onde esta decisão falha:** se algum business quiser *"o balconista vê o preço de balcão mas não
> vê que agência paga 22% menos"*, este gate não entrega. Aí a permissão nova nasce — com caso de uso
> real. **Não reabrir sem sinal de cliente.**

- **Teste:** [`ProdutoUnificadoContratoTest`](../../../../../tests/Feature/Produto/ProdutoUnificadoContratoTest.php) — `UC-PUNI-03`.
- **Status: ✅** — verde na run `31706439580`.

## UC-PUNI-04 · Composição (BOM) só aparece com módulo e permissão · `must`

- **Persona:** administrador decide se a composição do produto — as matérias-primas e o quanto cada
  uma custa — fica visível para a equipe. Em gráfica, a composição **é** a estrutura de custo.
- **Aceite:** Dado um business **sem** o módulo Manufacturing habilitado **ou** um usuário sem
  `manufacturing.access_recipe` · Quando abre a ficha de um produto · Então a composição **não chega**
  ao navegador (nem a contagem de itens, nem os insumos, nem o custo por insumo).
- **Mecanismo — não inventar:** a visibilidade usa as **camadas canônicas** que já existem
  ([feedback-habilitar-modulo-por-business](../../../../../memory/reference/feedback-habilitar-modulo-por-business.md)):
  **camada 1** (módulo Manufacturing no pacote do business) + **camada 3** (permissão
  `manufacturing.access_recipe`, que já existe em `Modules/Manufacturing`). **Nenhuma permissão nova.**
- **Estado hoje:** preventivo. O controller ainda não serve BOM (`bomCount` é literal `0`, `:130`).
  O caso existe para que a composição **nasça gated** — não para que alguém a ligue e descubra depois.
- **Insumo é produto:** o ingrediente da receita é `variation_id`
  (`Modules/Manufacturing/Database/Migrations/2019_08_08_110035_create_mfg_recipe_ingredients_table.php:23`),
  ou seja, uma **variação de produto**. Não existe tipo "matéria-prima" no schema: varredura de
  `materia.?prima|uso e consumo|raw_material` em `database/migrations/`, `app/` e
  `Modules/Manufacturing/` devolveu **0**. O `products.type` nasce `enum('single','variable')`
  (`database/migrations/2017_08_08_115903_create_products_table.php:21`). Quem classifica insumo hoje
  é a flag `not_for_selling` (`ProdutoUnificadoController:196`), com TODO de confirmação. A decisão
  sobre migrar (ou não) a "natureza do item" do Delphi está em
  [proposta 2026-08-11](../../../../../memory/decisions/proposals/2026-08-11-natureza-do-item-tipo-de-produto.md).
- **Teste:** [`ProdutoUnificadoContratoTest`](../../../../../tests/Feature/Produto/ProdutoUnificadoContratoTest.php) — `UC-PUNI-04`.
- **Status: ✅** — verde na run `31706439580`. Segue **preventivo**: o BOM ainda **não é servido**, e o caso existe pra a composição **nascer**
  gated, não pra alguém ligá-la e descobrir depois.

## UC-PUNI-05 · Nenhuma prop enxerga outro business · `must` `[T0]`

- **Persona:** qualquer tenant. É o pior bug possível do projeto, e esta tela expõe **seis** props de
  uma vez (`kpis`, `produtos`, `categorias`, `insumos`, `tabelas`, `historico`).
- **Aceite:** Dado um produto de outro business · Quando qualquer sub-tela é pedida · Então ele não
  aparece em prop nenhuma.
- **Residual declarado no próprio código:** `categorias()` não escopa o lado `products` do `leftJoin`
  por `business_id` (`:151-154`, herdado do helper de produção). A contagem pode inflar; o caso mede.
- **Teste:** [`ProdutoUnificadoContratoTest`](../../../../../tests/Feature/Produto/ProdutoUnificadoContratoTest.php) — `UC-PUNI-05`.
- **Status: ✅** — guard verde na run `31706439580`. Se algum dia ficar vermelho, é Tier 0 e vira incidente, não dívida.

## UC-PUNI-06 · A tela exige `product.view` ou `product.create` · `should`

- **Aceite:** Dado um usuário autenticado **sem** `product.view` **nem** `product.create` · Quando pede `/products/unificado` ·
  Então recebe 403 — não a página.
- **Estado hoje:** nada gateia a tela. O TODO em `routes/web.php:449` pede middleware, mas o padrão
  canônico do módulo **não é middleware**: a lista irmã aborta dentro do controller
  (`ProductController@index:66`). Vermelho esperado.
- **Teste:** [`ProdutoUnificadoContratoTest`](../../../../../tests/Feature/Produto/ProdutoUnificadoContratoTest.php) — `UC-PUNI-06`.
- **Status: ✅** — verde na run `31706439580`.

---

# Os casos que nasceram com a Consulta de Produtos (2026-08-18)

> **Por que este bloco existe.** Em 2026-08-18 a tela recebeu o layout do handoff
> **"Consulta de Produtos"** — índice em paridade com a `/contacts` (golden master do padrão de
> índice): abas por **tipo do item**, seis **KPI-filtros** contados sobre a aba, busca em linha
> própria, filtros com contagem, cartão de tabela com rolagem interna e drawer de detalhe. As 4
> sub-telas anteriores (Categorias · Insumos·BOM · Tabelas de preço · Histórico) saíram da barra
> de abas e foram pro **menu de ações do cabeçalho** — mesmos gates, mesmo controller, nenhuma
> capacidade removida.
>
> Os UCs abaixo contratam o que a mudança introduziu. Os UC-PUNI-01..06 continuam valendo
> inteiros: eles contratam **visibilidade**, que o layout não toca — e foram **revalidados sobre
> a tela nova** na mesma run `32141318494`, não herdados do verde antigo.

## UC-PUNI-07 · O contador "Margem baixa" segue o gate do custo · `must`

- **Persona:** a mesma do UC-PUNI-01 — balconista sem direito de ver custo.
- **Aceite:** Dado um usuário sem `view_purchase_price` · Quando a faixa de KPIs é servida · Então
  a chave `margem` **não existe** no payload, e o card não é montado na tela.
- **Por que é o mesmo dado:** "quantos itens estão sob o piso de margem" é uma **leitura agregada
  da estrutura de custo**. Gatear a coluna e deixar o contador entrega a mesma informação, só que
  somada — e o operador que sabe o tamanho do catálogo consegue estreitar por aba até o número
  virar um item. Mesma regra do §5 do handoff (coluna **montada ou não montada**), aplicada ao card.
- **O piso é do NEGÓCIO, não da tela:** ele viaja como prop (`pisoMargem`) e o frontend nunca o
  redeclara (handoff §9 — *"margem calculada com o piso vigente, não com 42% fixo"*).
- **Teste:** [`ProdutoUnificadoIndiceContratoTest`](../../../../../tests/Feature/Produto/ProdutoUnificadoIndiceContratoTest.php) — `UC-PUNI-07`.
- **Status: ✅** — verde na run `32141318494` (lane Estoque · MySQL). O veredito veio do JUnit da run, não desta linha.

## UC-PUNI-08 · A aba recorta por TIPO derivado e conta só ativos · `must`

- **Persona:** Larissa no balcão. Ela pensa em "produto", "serviço", "matéria-prima" e "kit" —
  não em `type`, `not_for_selling` e `enable_stock`.
- **Aceite:** Dado um item **com** controle de estoque e outro **sem** · Quando a aba "Produtos" é
  pedida · Então só o primeiro aparece; e na aba "Serviços", só o segundo. As abas de tipo contam
  **apenas ativos**; `Todos` é o cadastro inteiro e é o teto de todas as outras.
- **A derivação — e por que ela é declarada aqui:** o UltimatePOS **não tem** coluna "tipo de item"
  (medido em `UC-PUNI-04`: varredura de `materia.?prima|raw_material` devolveu **0**). O tipo é
  derivado de três colunas existentes, nesta ordem: `type = 'combo'` → kit · `not_for_selling = 1`
  → matéria-prima · `enable_stock = 0` → serviço · resto → produto. A ordem **é** a regra: um combo
  não-estocável é kit, não serviço.
- **Dívida honesta:** enquanto a "natureza do item" do Delphi não for migrada
  ([proposta 2026-08-11](../../../../../memory/decisions/proposals/2026-08-11-natureza-do-item-tipo-de-produto.md)),
  esta derivação é a melhor aproximação disponível — e é **explícita**, não escondida numa query.
- **Teste:** [`ProdutoUnificadoIndiceContratoTest`](../../../../../tests/Feature/Produto/ProdutoUnificadoIndiceContratoTest.php) — `UC-PUNI-08`.
- **Status: ✅** — verde na run `32141318494`.

## UC-PUNI-09 · "Não estocável" e "sem estoque" são estados diferentes · `must`

- **Persona:** Larissa perguntando "tem?". A resposta tem três formas, e confundi-las custa venda.
- **Aceite:** Dado um serviço (`enable_stock = 0`) · Então `stockQty` é **`null`** e o badge diz
  *Não estocável*. Dado um item estocável · Então `stockQty` é **numérico** — inclusive `0`, que é
  *Sem estoque* e bloqueia venda.
- **Por que não pode colapsar:** imprimir `0` pra um serviço afirma estoque zerado e faz o balcão
  recusar uma venda que não depende de saldo; imprimir `—` pra um item zerado esconde o bloqueio.
  **Os dois erros já aconteceram nesta tela** — o `stockQty` era `null` fixo no controller e a
  coluna imprimia `null UNID` antes de 2026-08-07.
- **Ordenação:** a coluna Disponibilidade ordena por **rank semântico** (sem estoque < baixo < em
  estoque), não pelo texto do badge — alfabético colocaria "Em estoque" antes de "Sem estoque" e
  esconderia o que precisa de ação.
- **Teste:** [`ProdutoUnificadoIndiceContratoTest`](../../../../../tests/Feature/Produto/ProdutoUnificadoIndiceContratoTest.php) — `UC-PUNI-09`.
- **Status: ✅** — verde na run `32141318494`.

## UC-PUNI-10 · As agregações não contam produto de outro business · `must` `[T0]`

- **Persona:** qualquer tenant. O UC-PUNI-05 já cobre as **listas**; este cobre os **números**.
- **Aceite:** Dado três produtos cadastrados em OUTRO business · Quando as abas, os KPIs e o total
  do recorte são servidos · Então nenhum dos contadores sobe.
- **Por que separar de UC-PUNI-05:** vazamento de agregação é o formato **silencioso** — nenhuma
  linha do vizinho aparece na tela, mas o número no topo cresce e entrega o tamanho do catálogo
  dele. Uma lista escopada com um contador não-escopado passa no UC-PUNI-05 e vaza aqui.
- **Mecanismo:** as três leituras (linhas, KPIs, contagem das abas) saem da **mesma subconsulta**
  (`catalogoSub`), que declara `business_id` explicitamente — `App\Product` não tem global scope
  de tenant (ADR 0093). Fonte única também é o que garante que o contador não discorde da lista.
- **Teste:** [`ProdutoUnificadoIndiceContratoTest`](../../../../../tests/Feature/Produto/ProdutoUnificadoIndiceContratoTest.php) — `UC-PUNI-10`.
- **Status: ✅** — verde na run `32141318494`.

## UC-PUNI-11 · A resposta é a fatia da página, e o total é o do recorte · `must`

- **Persona:** Larissa no balcão, com um catálogo que não cabe numa tela.
- **Aceite:** Dado `porPagina = 10` · Quando a listagem é servida · Então `produtos` traz **no
  máximo 10** linhas e `totalDaAba` traz o total do **recorte inteiro**, não o da fatia.
- **Por que os dois números têm de existir:** o rodapé escreve "Mostrando 1–10 de 1.347". O
  "1–10" é da fatia; o "1.347" é do recorte. Se a tela contasse `produtos.length` pra os dois,
  escreveria "Mostrando 1–10 de 10" e o operador concluiria que o catálogo tem 10 itens.
- **O que isto substituiu:** até 18/08 a tela pedia as **500 primeiras** e avisava que tinha
  cortado. Funcionava, mas não havia caminho pra 501ª linha sem o operador inventar um filtro.
- **Teste:** [`ProdutoUnificadoIndiceContratoTest`](../../../../../tests/Feature/Produto/ProdutoUnificadoIndiceContratoTest.php) — `UC-PUNI-11`.
- **Status: 🕐** — escrito nesta PR; veredito na primeira run da lane Estoque · MySQL.

## UC-PUNI-11B · Página 2 não repete nenhuma linha da página 1 · `must`

- **Persona:** quem folheia o catálogo procurando um item que não sabe nomear.
- **Aceite:** Dado 6 itens no recorte e `porPagina = 3` · Quando as páginas 1 e 2 são servidas ·
  Então a interseção dos ids é **vazia**.
- **Mecanismo:** `LIMIT/OFFSET` sem ordem **total** é indeterminado. Ordenando só por preço, duas
  linhas de mesmo preço podem sair em ordens diferentes nas duas consultas — e aí um item aparece
  nas duas páginas enquanto outro não aparece em nenhuma. O desempate por `c.id` fecha isso.
- **Por que é `must` e não detalhe:** o item que some não deixa rastro. O operador conclui que o
  produto não está cadastrado e abre um segundo cadastro do mesmo item.
- **Teste:** [`ProdutoUnificadoIndiceContratoTest`](../../../../../tests/Feature/Produto/ProdutoUnificadoIndiceContratoTest.php) — `UC-PUNI-11B`.
- **Status: 🕐** — escrito nesta PR.

## UC-PUNI-11C · Ordenar por custo é ignorado pra quem não pode ver custo · `must` `[V0]`

- **Persona:** vendedor sem `view_purchase_price`.
- **Aceite:** Dado um perfil sem direito a custo · Quando ele pede `ordem=custo&dir=asc` e depois
  `ordem=custo&dir=desc` · Então as duas respostas vêm na **mesma ordem** (a padrão, por nome).
- **Por que é vazamento:** a coluna Custo não é montada pra esse perfil — mas se a lista pudesse
  ser ordenada por custo, a **posição** entregaria o número. "O primeiro é o mais barato" é a
  estrutura de custo servida por ranking. É o AR-PROD-015 com um passo a mais no meio.
- **Onde mora a regra:** `ProdutoUnificadoController::produtos()` zera `ordem` quando ela é
  `custo`/`margem` e o perfil não tem custo **e** preço — cai no padrão, não devolve erro. Erro
  também informa: diria "existe uma ordenação que você não pode usar".
- **Teste:** [`ProdutoUnificadoIndiceContratoTest`](../../../../../tests/Feature/Produto/ProdutoUnificadoIndiceContratoTest.php) — `UC-PUNI-11C`.
- **Status: 🕐** — escrito nesta PR.

## UC-PUNI-11D · `porPagina` e `ordem` fora da lista branca caem no padrão · `must`

- **Persona:** ninguém — é a URL colada à mão, e o gate contra ela.
- **Aceite:** Dado `?porPagina=99999&ordem=c.id; DROP TABLE products` · Então `filters.porPagina`
  volta **25** e `filters.ordem` volta **`''`**.
- **Por que:** `porPagina` sem teto é a URL virando alavanca pra varrer o catálogo inteiro num
  payload; `ordem` sem lista branca entra **cru** no `ORDER BY`, que é o único lugar da consulta
  onde não dá pra usar bind de parâmetro.
- **Teste:** [`ProdutoUnificadoIndiceContratoTest`](../../../../../tests/Feature/Produto/ProdutoUnificadoIndiceContratoTest.php) — `UC-PUNI-11D`.
- **Status: 🕐** — escrito nesta PR.

## UC-PUNI-12 · Saldo por local só viaja com mais de um local · `must`

- **Persona:** Larissa no balcão, com o cliente na frente. "Tem 7" e "tem 7, mas 0 aqui na loja"
  são respostas **diferentes** — a segunda muda o que ela promete de prazo.
- **Aceite:** Dado um item com saldo em **2 locais** · Então a linha traz `locais` e a **soma** deles
  é exatamente o `stockQty` da coluna. Dado um item com **1 local** · Então a chave `locais`
  **não existe**.
- **Por que a ausência importa:** a tela monta o gatilho de popover quando a chave existe. Um
  popover que lista um local só é affordance que não cumpre — pior que ausência.
- **Por que a soma tem que bater:** divergência entre o total da coluna e a soma do popover é a
  primeira coisa que o operador confere, e é o que destrói a confiança na tela inteira. As duas
  leituras saem das MESMAS variações vivas (`v.deleted_at IS NULL`).
- **Teste:** [`ProdutoUnificadoIndiceContratoTest`](../../../../../tests/Feature/Produto/ProdutoUnificadoIndiceContratoTest.php) — `UC-PUNI-12`.
- **Status: 🕐** — escrito nesta PR; veredito na primeira run da lane Estoque · MySQL.

## UC-PUNI-12B · Local de outro business não entra no saldo por local · `must` `[T0]`

- **Persona:** qualquer tenant.
- **Aceite:** Dada uma linha de `variation_location_details` do MEU produto pendurada num local de
  OUTRO business · Então ela **não** aparece no popover nem entra na soma.
- **Por que existe o cenário:** parece impossível, mas é o resultado de restore parcial ou
  importação mal feita — e o `product_id` sozinho não protege, porque ele já é do meu tenant. O
  escopo tem que vir de `business_locations.business_id`.
- **O que vazaria:** o **nome** do local do vizinho (endereço comercial dele) e um saldo que infla
  a soma até contradizer a coluna.
- **Teste:** [`ProdutoUnificadoIndiceContratoTest`](../../../../../tests/Feature/Produto/ProdutoUnificadoIndiceContratoTest.php) — `UC-PUNI-12B`.
- **Status: 🕐** — escrito nesta PR.

## UC-PUNI-13 · Observação chega sem HTML, e a chave só existe quando há nota · `must`

- **Persona:** quem vende um item com pegadinha — "sob encomenda", "conferir metragem do rolo".
- **Aceite:** Dado um produto com `product_description` preenchido · Então a linha traz `obs` em
  **texto puro**, com os parágrafos separados por espaço. Dado um produto sem descrição · Então a
  chave **não existe**.
- **Por que o HTML morre no servidor:** `product_description` é editado por WYSIWYG no
  UltimatePOS. Renderizar com `dangerouslySetInnerHTML` um campo que o usuário digita é **XSS
  armazenado**; renderizar como texto com a tag dentro mostra `<p>` na tela.
- **O detalhe que parece bobo:** `</p>` vira espaço **antes** do strip. Sem isso, dois parágrafos
  colam numa palavra só (`depósito.Conferir`), e o operador lê como erro de cadastro.
- **O que NÃO é servido, e por quê:** os badges "Sob encomenda" / "Exige aprovação" do pacote.
  Eles não existem no cadastro — no protótipo são campo do dado de mentira. Deduzi-los do texto
  ("se contém 'encomenda' então…") seria adivinhação exibida como fato.
- **Teste:** [`ProdutoUnificadoIndiceContratoTest`](../../../../../tests/Feature/Produto/ProdutoUnificadoIndiceContratoTest.php) — `UC-PUNI-13`.
- **Status: 🕐** — escrito nesta PR.

## UC-PUNI-14 · A variação-fantasma do UltimatePOS não vira "variação" na tela · `must`

- **Persona:** qualquer um lendo a lista. A terceira linha da célula Produto só pode existir
  quando o item **tem** variação.
- **Aceite:** Dado um produto simples · Então a chave `variacoes` **não existe**. Dado um produto
  com 3 valores de variação reais · Então `variacoes` existe e a contagem é 3.
- **O mecanismo:** o UltimatePOS cria uma `product_variations` com `is_dummy = 1` para **todo**
  produto simples, só pra ele ter uma linha em `variations`. Contá-la faria cada item do catálogo
  anunciar uma variação inexistente embaixo do próprio nome.
- **Divergência declarada do pacote:** o protótipo imprime `4 cores · 3 tamanhos`. Aqui sai
  `Cor (4) · Tamanho (3)` — o nome do atributo é **texto livre do tenant**
  (`product_variations.name`), pode ser "Cor", "Cores" ou "Tonalidade", e pluralizar o que o
  cliente digitou daria "4 Cors". O nome vai literal; a contagem entre parênteses.
- **Teste:** [`ProdutoUnificadoIndiceContratoTest`](../../../../../tests/Feature/Produto/ProdutoUnificadoIndiceContratoTest.php) — `UC-PUNI-14`.
- **Status: 🕐** — escrito nesta PR.
