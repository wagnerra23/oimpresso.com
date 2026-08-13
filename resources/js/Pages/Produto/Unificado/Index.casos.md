---
id: resources-js-pages-produto-unificado-index-casos
casos: Catálogo Unificado · /products/unificado
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela reúne, numa rota só, tudo que as outras telas do Produto gateiam separadamente — custo, preço de venda, tabelas de preço e composição. Sem casos, ela vira o caminho por onde tudo isso sai sem permissão.
owner: wagner
last_run: "2026-08-11"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane Estoque · MySQL"
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

## O que foi MEDIDO no controller (`app/Http/Controllers/ProdutoUnificadoController.php`)

| Fato | Onde |
|---|---|
| Nada gateia a tela. O TODO pede middleware, mas o padrão canônico do módulo **não é middleware**: a lista irmã aborta 403 **dentro do controller** (`product.view` **ou** `product.create`) | `routes/web.php:449-451` + `ProductController@index:66` |
| `produtos()` monta `price`, `cost` e `margin` para **toda** linha, sem consultar permissão | `:122-124` |
| Varredura contada de `view_purchase_price\|access_default_selling_price` no controller | **0 ocorrências** |
| `historico()` devolve `value` = qty × `unit_price_inc_tax` — preço de venda por linha, sem gate | `:249`, `:260` |
| `tabelas()` devolve `SellingPriceGroup` do business, sem gate | `:216-226` |
| Composição (BOM) ainda **não é servida** — `bomCount` é literal `0` com TODO | `:130` |
| Insumo = produto com `not_for_selling = 1` (convenção com TODO "confirmar com Wagner") | `:193-196` |

> ⚠️ **Failing-first:** os asserts saem do contrato, não do código. Se nascerem vermelhos, o vermelho
> **é** o achado — não se ajusta o teste ao código. Por isso o teste entra na quarentena da lane
> (`.github/estoque-pest-quarantine.list`, bloco A), como os 6 irmãos já fazem.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-PUNI-01 | Custo não chega ao navegador sem `view_purchase_price` | must | `AR-PROD-015` + `UC-PIDX-03` | `ProdutoUnificadoContratoTest` | ⬜ failing-first — vermelho esperado |
| UC-PUNI-02 | Preço de venda não chega sem `access_default_selling_price` — inclusive no Histórico | must | Blade `index.blade.php:294` + `UC-PIDX-03` | `ProdutoUnificadoContratoTest` | ⬜ failing-first — vermelho esperado |
| UC-PUNI-03 | Tabelas de preço seguem o **mesmo** gate do preço de venda | must | decisão 2026-08-11 (abaixo) | `ProdutoUnificadoContratoTest` | ⬜ failing-first — vermelho esperado |
| UC-PUNI-04 | Composição (BOM) só aparece com módulo Manufacturing **e** `manufacturing.access_recipe` | must | permissões `Modules/Manufacturing` + camada 1/3 ([feedback-habilitar-modulo-por-business](../../../../../memory/reference/feedback-habilitar-modulo-por-business.md)) | `ProdutoUnificadoContratoTest` | ⬜ preventivo — BOM ainda não servido |
| UC-PUNI-05 | Nenhuma prop enxerga outro business | must `[T0]` | `CU-PROD-10.2` + [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) | `ProdutoUnificadoContratoTest` | ⬜ guard — verde esperado |
| UC-PUNI-06 | A tela exige `product.view` **ou** `product.create` | should | `ProductController@index:66` (a lista irmã) + `routes/web.php:449` (TODO) | `ProdutoUnificadoContratoTest` | ⬜ failing-first — nada gateia |

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
- **Status: ⬜** — failing-first; **vermelho esperado** (o controller não consulta permissão nenhuma).

## UC-PUNI-02 · Preço de venda não chega sem `access_default_selling_price` · `must`

- **Persona:** operador de produção que consulta o catálogo pra saber prazo e composição, sem
  participar de negociação comercial.
- **Aceite:** Dado um usuário sem `access_default_selling_price` · Quando abre a tela · Então nem a
  linha do produto (`price`) nem a sub-tela **Histórico de uso** (`value`, derivado de
  `unit_price_inc_tax`) entregam preço de venda.
- **Por que o Histórico entra aqui:** é a porta lateral. Gatear a lista e deixar o histórico aberto
  entrega o mesmo dado por outro caminho, produto a produto.
- **Teste:** [`ProdutoUnificadoContratoTest`](../../../../../tests/Feature/Produto/ProdutoUnificadoContratoTest.php) — `UC-PUNI-02` e `UC-PUNI-02b`.
- **Status: ⬜** — failing-first; **vermelho esperado**.

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
- **Status: ⬜** — failing-first; **vermelho esperado**.

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
- **Status: ⬜** — preventivo; o BOM ainda **não é servido**. O caso existe pra a composição **nascer**
  gated, não pra alguém ligá-la e descobrir depois.

## UC-PUNI-05 · Nenhuma prop enxerga outro business · `must` `[T0]`

- **Persona:** qualquer tenant. É o pior bug possível do projeto, e esta tela expõe **seis** props de
  uma vez (`kpis`, `produtos`, `categorias`, `insumos`, `tabelas`, `historico`).
- **Aceite:** Dado um produto de outro business · Quando qualquer sub-tela é pedida · Então ele não
  aparece em prop nenhuma.
- **Residual declarado no próprio código:** `categorias()` não escopa o lado `products` do `leftJoin`
  por `business_id` (`:151-154`, herdado do helper de produção). A contagem pode inflar; o caso mede.
- **Teste:** [`ProdutoUnificadoContratoTest`](../../../../../tests/Feature/Produto/ProdutoUnificadoContratoTest.php) — `UC-PUNI-05`.
- **Status: ⬜** — guard; **verde esperado**. Se ficar vermelho, é Tier 0 e vira incidente, não dívida.

## UC-PUNI-06 · A tela exige `product.view` ou `product.create` · `should`

- **Aceite:** Dado um usuário autenticado **sem** `product.view` **nem** `product.create` · Quando pede `/products/unificado` ·
  Então recebe 403 — não a página.
- **Estado hoje:** nada gateia a tela. O TODO em `routes/web.php:449` pede middleware, mas o padrão
  canônico do módulo **não é middleware**: a lista irmã aborta dentro do controller
  (`ProductController@index:66`). Vermelho esperado.
- **Teste:** [`ProdutoUnificadoContratoTest`](../../../../../tests/Feature/Produto/ProdutoUnificadoContratoTest.php) — `UC-PUNI-06`.
- **Status: ⬜** — failing-first; **vermelho esperado**.
