---
id: resources-js-pages-produto-index-casos
casos: Lista do catálogo · /products
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a lista é a PORTA do catálogo — se ela esconde produto, esconde preço a quem não devia, ou mistura tenant, todo o resto do ERP herda o erro.
owner: wagner
last_run: "2026-10-27"
last_run_ci: "2026-10-26: PR #9897 (natureza fiscal na consulta, UC-PIDX-31, com a requisição aberta e o método de custo do #9896 e do #9898 já no main), head 2c43ba7: lane PHP / Pest (Estoque · MySQL), job 121847889045: 550 passed, 0 pulados (2701 assertions), com ProdutoRequisicaoAbertaContratoTest 4, ProdutoNaturezaFiscalConsultaContratoTest 3 e ProdutoMetodoDeCustoContratoTest 2 verdes; job Frontend / Vite build 121847886774: 114 testes passaram em 14 arquivos. Antes: 2026-10-23: run 39945557255 (lane Estoque · MySQL, main 53e7995, disparada à mão): 497 testes, 0 falhas, 0 pulados, com todos os arquivos Pest da lista; job 120384523098 (Frontend / Vite build, mesmo commit): os 2 arquivos Vitest da lista que rodam em lane passaram; os outros 8 arquivos Vitest da lista não rodam em lane e passaram local (133 de 133). Antes: 2026-10-19, job 118208572139."
---

# Casos de Uso & Aceite — Lista do catálogo (`/products`)

> **Âncora:** `CU-PROD-15` (consultar/listar o catálogo) e `CU-PROD-10` `[T0]` (multi-tenant) do
> [SDD §6.1](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md), cruzados com o
> **contrato de paridade Delphi** ([ANTI-REGRESSAO-cadastro-produto-legacy.md](../../../../memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md),
> Office Comercial 2026.1.1.38 — `AR-PROD-003/015/022/023`) e com a **lista Blade que roda em
> produção hoje** (`resources/views/product/index.blade.php` + o pipeline DataTables de
> `ProductController@index`).
> Os UCs derivam do **contrato**, **nunca** do `Index.tsx` — teste derivado do código é tautológico e
> trava o desvio em vez de pegá-lo (`proibicoes.md` §5, 2026-06-05).
>
> **Como este arquivo nasceu:** agent `sdd-from-source` ([ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md)),
> triangulando as **3 fontes** — React (`Index.tsx` → `GET /products` → `ProductController@index`
> `:64-374` + os 3 builders `:380-494`) + Blade (`index.blade.php`, o **mesmo** método `index()` no
> branch sem `X-Inertia`) + Delphi (âncoras `AR-PROD-*`). Fecha
> `trio:missing-casos:Produto/Index.tsx` do baseline do `casos-gate`.
>
> ⚠️ **Aqui a Blade legada NÃO é um arquivo irmão — é o OUTRO BRANCH DO MESMO MÉTODO.** O
> `index()` serve três consumidores: `request()->ajax()` → DataTables server-side (`:73-312`),
> `X-Inertia` → `Produto/Index` (`:342-359`), e o resto → `view('product.index')` (`:361`). A
> comparação de paridade é **entre os dois branches do mesmo controller**, e é por isso que os
> gaps são tão nítidos: o mesmo autor, no mesmo arquivo, aplica regras diferentes.
>
> ⚖️ **Força do veredito destes UC — `advisory`, não bloqueante.** Os testes rodam em
> `PHP / Pest (Estoque · MySQL)` no PR e no fullsuite nightly do CT 100, mas essa lane **não
> consta** em [`governance/required-checks-baseline.json`](../../../../governance/required-checks-baseline.json)
> (as lanes Pest required são Financeiro, NfeBrasil e Unit). Ou seja: **reprovação aqui é visível
> e não bloqueia merge.** Isso não enfraquece o achado — só evita que a prosa deste arquivo soe
> mais forte que o enforcement real. Promover a required exige mordida provada
> ([ADR 0336](../../../../memory/decisions/0336-gates-design-promocao-por-mordida-provada-emenda-0314.md)) + flip [W].
>
> ⛔ **Escopo honesto (não é incidente de produção).** As telas React do Produto seguem
> inalcançáveis em prod — a sidebar usa `<a href>` puro, sem header `X-Inertia`, então roda o
> Blade (confirmado por [F] em 2026-07-24, `Edit.casos.md` §Trilha). O que está aqui é
> **bloqueador de migração**: define quando a lista React pode ser ligada (MWART F5,
> [ADR 0104](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) · US-PROD-023).
> _(2026-10-10: a lista React foi refeita no desenho do protótipo sobre a consulta da lista Blade
> — #9119 e #9124 — e o menu volta a abri-la no PR do cutover; `?classico=1` mantém o Blade.
> A partir daí estes 6 casos defendem a tela viva da ROTA LIVRE.)_
>
> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado (stub/sem veredito) ·
> 🔶 backlog (achado a verificar / decisão [W]) · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-PIDX-01 | Todo produto do catálogo é alcançável na lista (sem corte silencioso) | must | Blade DataTables server-side + `AR-PROD-022/023` | `ProdutoIndexContratoTest` (Pest) | 🧪 passou 2026-10-23 — Pest na lane (run 39945557255) |
| UC-PIDX-02 | Busca é resolvida no servidor e acha por SKU de variação (`sub_sku`) | must | Blade `filterColumn('products.sku')` + `CU-PROD-02` | `ProdutoIndexContratoTest` (Pest) | 🧪 passou 2026-10-23 — Pest na lane (run 39945557255) |
| UC-PIDX-03 | Preço e custo na lista respeitam a permissão de vê-los | must | Blade `@can` ×2 (`resources/views/product/index.blade.php:287 (verificado@5d5cac0),294`) + `AR-PROD-015` | `ProdutoIndexContratoTest` (Pest) | 🧪 passou 2026-10-23 — Pest na lane (run 39945557255) |
| UC-PIDX-04 | Lista e total só enxergam o business atual | must `[T0]` | `CU-PROD-10` + ADR 0093 + charter §Anti-hooks | `ProdutoIndexContratoTest` (Pest) | 🧪 passou 2026-10-23 — Pest na lane (run 39945557255) |
| UC-PIDX-05 | Abrir a lista não escreve no banco (GET é leitura pura) | must | charter §Anti-hooks + `AR-PROD-064` | `ProdutoIndexContratoTest` (Pest) | 🧪 passou 2026-10-23 — Pest na lane (run 39945557255) |
| UC-PIDX-06 | Filtro de inativos (Situação) é resolvido no servidor | should | `AR-PROD-003/022` + Blade `active_state` | `ProdutoIndexContratoTest` (Pest) | 🧪 passou 2026-10-23 — Pest na lane (run 39945557255) |
| UC-PIDX-07 | Os 4 indicadores contam o catálogo inteiro; o valor é o total do relatório de estoque | must | charter v3 §Goals + decisão [F] 2026-10-10 (opção B) + Blade `stock_report.view`/`view_product_stock_value` | `ProdutoListaKpisTest` (Pest) | 🧪 passou 2026-10-23 — Pest na lane (run 39945557255) |
| UC-PIDX-08 | A aba "Relatório de estoque" mostra as linhas de Relatórios → Estoque com os filtros da tela e totaliza TODAS as linhas | must | charter v3 §Goals + Blade `#product_stock_report` (`@can('stock_report.view')`) | `ProdutoListaEstoqueAbaTest` (Pest) | 🧪 passou 2026-10-23 — Pest na lane (run 39945557255) |
| UC-PIDX-09 | O painel de detalhe mostra o conteúdo da janela "Ver" da Blade, com os mesmos gates, e só do produto da empresa | must `[T0]` | charter v3 §Goals + Blade `ProductController::view` + ADR 0093 | `ProdutoDetalheTest` (Pest) | 🧪 passou 2026-10-23 — Pest na lane (run 39945557255) |
| UC-PIDX-10 | "Exportar filtrados" traz as mesmas linhas da lista, em CSV, com preço só para quem pode ver | should | charter v3 §Goals + protótipo (rodapé, `exportarCsv`) + gate de preço da lista (UC-PIDX-03) | `ProdutoListaExportarTest` (Pest) | 🧪 passou 2026-10-23 — Pest na lane (run 39945557255) |
| UC-PIDX-11 | A consulta não oferece compra (nem a quem pode criar compra) | should | decisão [F] 2026-10-26 + protótipo (`acoesDe`, rodapé do `DetalheDrawer`, #9925) | `ProdutoDetalheTest` (Pest) + `produto-menu-da-linha.test.tsx` + `produto-painel-detalhe.test.tsx` (Vitest) | 🧪 aguarda veredito da lane — Vitest local passou 2026-10-26 |
| UC-PIDX-12 | Os 3 indicadores da aba Estoque do painel são a soma das linhas do relatório de estoque do produto | must | protótipo (`DetalheDrawer`, aba Estoque) + contas de `linhaDoRelatorioEstoque` + Blade `view_product_stock_value` | `ProdutoDetalheTest` (Pest) | 🧪 passou 2026-10-23 — Pest na lane (run 39945557255) |
| UC-PIDX-13 | Clicar no cabeçalho de uma coluna ordena a lista no servidor, nas duas direções; por preço só para quem pode ver o preço | should | protótipo (`ListaProdutos`, colunas `sortable`) + `$ordenaveis` de `buildProdutoListaPaginada` + gate de preço (UC-PIDX-03) | `ProdutoListaOrdenacaoTest` (Pest) + `produto-lista-ordenacao.test.tsx` (Vitest) | 🧪 passou 2026-10-23 — Pest na lane (run 39945557255) + Vitest local |
| UC-PIDX-14 | "Cadastro rápido" cria o produto com os preços que o formulário Blade gravaria, só na empresa e nos locais permitidos | must `[V0]` `[T0]` | charter v3 §Goals ("Cadastro rápido") + protótipo (`ModalCadastroRapido`) + Blade `quick_add_product` / `saveQuickProduct` + REGRA MESTRE valor | `ProdutoListaCadastroRapidoTest` (Pest) + `produto-cadastro-rapido.test.tsx` (Vitest) | 🧪 passou 2026-10-23 — Pest na lane (run 39945557255) + Vitest local |
| UC-PIDX-15 | "Adicionar ou editar estoque inicial" abre em janela e lança o mesmo saldo, linhas e transações que o formulário Blade | must `[V0]` `[T0]` | protótipo (`ModalEstoqueInicial`) + Blade `opening_stock/form-part` / `OpeningStockController@save` + `CU-PROD-04` + REGRA MESTRE estoque | `EstoqueInicialJanelaTest` (Pest) + `produto-estoque-inicial.test.tsx` (Vitest) | 🧪 passou 2026-10-23 — Pest na lane (run 39945557255) + Vitest local |
| UC-PIDX-16 | Arrastar a borda do cabeçalho muda a largura da coluna, sem mexer na ordem nem nas outras colunas | should | protótipo (`ListaProdutos` → `DataGrid` do DS, `startResize`) | `produto-lista-redimensionar.test.tsx` + `produto-lista-encaixe.test.tsx` (Vitest, rodam no `Frontend / Vite build`) + `datatable-redimensionar.test.tsx` + 1 caso em `tests/a11y-primitives.test.tsx` (roda em lane) | 🧪 passou 2026-10-23 — Vitest no Frontend (job 120384523098) |
| UC-PIDX-17 | "Dados fiscais (NCM/CFOP)" no menu da linha abre o painel do produto já na aba Fiscal | should | protótipo (`acoesDe` + aba `fiscal` do `DetalheDrawer`) | `produto-menu-da-linha.test.tsx` + `produto-painel-detalhe.test.tsx` (Vitest) | 🧪 passou 2026-10-23 — Vitest local |
| UC-PIDX-18 | Aba "Relatório de estoque": escolher quantas linhas por página, com os totais iguais em qualquer tamanho; lucro potencial da linha com cor | should `[V0]` | protótipo (`RelatorioEstoque`) + lista Blade (`stock_report_table`, menu de tamanho do DataTable) + REGRA MESTRE valor | `ProdutoListaEstoqueAbaTest` (Pest, roda em lane) + `produto-relatorio-estoque.test.tsx` (Vitest) | 🧪 passou 2026-10-23 — Pest na lane (run 39945557255) + Vitest local |
| UC-PIDX-19 | A aba Fiscal do painel mostra o tipo de imposto na venda (inclusivo ou exclusivo) | could | protótipo (aba `fiscal` do `DetalheDrawer`) | `produto-painel-detalhe.test.tsx` (Vitest) | 🧪 passou 2026-10-23 — Vitest local |
| UC-PIDX-20 | Painel: composição com "Total líquido", preço da composição e preços de cada item; imagens das variações e dos itens | should `[V0]` | Blade (`combo_product_details`, `variation_images`) + protótipo (`DetalheDrawer`, aba `composicao`) + REGRA MESTRE valor | `ProdutoDetalheTest` (Pest, roda em lane) + `produto-painel-detalhe.test.tsx` (Vitest) | 🧪 passou 2026-10-23 — Pest na lane (run 39945557255) + Vitest local |
| UC-PIDX-21 | Produto que não controla estoque tem a aba Estoque no painel, com o aviso de que não gerencia estoque | could | protótipo (aba `estoque` do `DetalheDrawer`, ramo `stockOn` falso) | `produto-painel-detalhe.test.tsx` (Vitest) | 🧪 passou 2026-10-23 — Vitest local |
| UC-PIDX-22 | Produto sem foto mostra "Sem imagem" no painel e "IMG" na lista | could | protótipo (`.pb-thumb` "IMG" e `.pb-img` do `DetalheDrawer`) | `ProdutoDetalheTest` + `ProdutoListaConsultaTest` (Pest, roda em lane) | 🧪 passou 2026-10-23 — Pest na lane (run 39945557255) |
| UC-PIDX-23 | Produto que sincroniza com o WooCommerce tem a marca ao lado do nome na lista | could | Blade (`ProductController@index`, coluna `product`: `fa-wordpress` quando `! woocommerce_disable_sync`) | `produto-lista-woocommerce.test.tsx` (Vitest) | 🧪 passou 2026-10-23 — Vitest local |
| UC-PIDX-24 | Campos personalizados 1 a 7 como colunas opcionais da lista: só os que a empresa rotulou, com o valor do produto | could | Blade (`product/index.blade.php`: colunas `product_custom_field1..7`, `visible` só com rótulo) + protótipo (`PB_COLS_PADRAO.cf1 = false`) | `ProdutoListaConsultaTest` (Pest, roda em lane) + `produto-lista-campos-personalizados.test.tsx` (Vitest) | 🧪 passou 2026-10-23 — Pest na lane (run 39945557255) + Vitest local |
| UC-PIDX-25 | Aba Fiscal do painel: origem da mercadoria, texto de ajuda e "Abrir no módulo Fiscal" | could | protótipo (aba `fiscal` do `DetalheDrawer`) + emissão (`orig` = `products.origem_mercadoria`) | `ProdutoDetalheTest` (Pest, roda em lane) + `produto-painel-detalhe.test.tsx` (Vitest) + `TributacaoIndexContratoTest` (UC-NFTR-29) | 🧪 passou 2026-10-23 — Pest na lane (run 39945557255) + Vitest local |
| UC-PIDX-26 | Em tela estreita as colunas encolhem até caber: em 1366px, com o menu aberto, as 12 colunas de saída aparecem sem rolar para o lado | should | reclamação da ROTA LIVRE (2026-10-17, monitor 1366×768) + `memory/regras-time.md` ("monitor 1280px — designs precisam caber") + charter (UX: "cabe em 1280px sem scroll horizontal da página") | `produto-lista-encaixe.test.tsx` (Vitest, roda no `Frontend / Vite build`) | 🧪 passou 2026-10-23 — Vitest no Frontend (job 120384523098) |
| UC-PIDX-27 | A grade tem altura própria: rola por dentro, com o cabeçalho fixo e a barra de rolar para o lado sempre à vista | should | protótipo (`ListaProdutos`: altura = 72% do corpo, mínimo 260px) + charter (UX: "a tabela rola dentro dela, cabeçalho fixo") | `produto-lista-encaixe.test.tsx` (Vitest, roda no `Frontend / Vite build`) | 🧪 passou 2026-10-23 — Vitest no Frontend (job 120384523098) |
| UC-PIDX-28 | Preço proposto pela compra recebida, aguardando aprovação, na linha e no painel; aprovar e recusar ficam na compra | should `[T0]` | UC-FLX-17 + Compras SPEC US-COM-026/028 + charter §Automation Anti-hooks (a consulta não altera preço) + gate de preço (UC-PIDX-03) | `ProdutoPrecoPropostoERoteiroContratoTest` (Pest, roda em lane) + `produto-preco-proposto-roteiro.test.tsx` (Vitest) | 🧪 passou no job 120005347082 (lane Estoque · MySQL, commit 730f065c, 2026-10-22): 7 testes, 0 pulados, 64 verificações |
| UC-PIDX-29 | Aba Roteiro do painel: as etapas do roteiro do produto, na ordem, com o posto e o setor de cada uma | should `[T0]` | UC-FLX-07 + UC-FLX-08 + Produção SPEC US-PCP-026/030 + ADR 0093 | `ProdutoPrecoPropostoERoteiroContratoTest` (Pest, roda em lane) + `produto-preco-proposto-roteiro.test.tsx` (Vitest) | 🧪 passou no job 120005347082 (lane Estoque · MySQL, commit 730f065c, 2026-10-22): 7 testes, 0 pulados, 64 verificações |
| UC-PIDX-30 | A requisição de compra aberta do produto aparece na linha e na aba Estoque, com número, data e fornecedor | should `[T0]` | UC-FLX-19 + Compras SPEC US-COM-027 + permissões da lista de requisições | `ProdutoRequisicaoAbertaContratoTest` (Pest, roda em lane) + `produto-requisicao-aberta.test.tsx` (Vitest) | 🧪 escrito, sem veredito nesta divisão. Junto com os outros dois casos, no #9785, passou no job 120394269474 (lane Estoque · MySQL, commit 3587864, 2026-10-23) |
| UC-PIDX-31 | Natureza fiscal: selo na linha, filtro no servidor e a natureza na aba Fiscal, com o aviso do pendente | should `[T0]` | UC-FLX-31 + NfeBrasil US-NFE-068/069 (`NfeService` recusa só a emissão separada com pendente) | `ProdutoNaturezaFiscalConsultaContratoTest` (Pest, roda em lane) + `produto-natureza-fiscal-consulta.test.tsx` (Vitest) | 🧪 escrito, sem veredito nesta divisão. Junto com os outros dois casos, no #9785, passou no job 120394269474 (lane Estoque · MySQL, commit 3587864, 2026-10-23) |
| UC-PIDX-32 | Método de custo do insumo na aba Variações, só quando a empresa gravou a regra de custo no Recebido | should | UC-FLX-38 + Compras SPEC US-COM-024/026 (`AplicarCustoDoRecebimento`) + gate de custo (UC-PIDX-03) | `ProdutoMetodoDeCustoContratoTest` (Pest, roda em lane) + `produto-metodo-custo.test.tsx` (Vitest) | 🧪 escrito, sem veredito nesta divisão. Junto com os outros dois casos, no #9785, passou no job 120394269474 (lane Estoque · MySQL, commit 3587864, 2026-10-23) |
| UC-PIDX-33 | Aba Estoque do painel: a tabela de locais mostra quanto foi vendido em cada local | should | protótipo (artefato "Consulta de Produto", 22/10: coluna "Vendido" por local) + pedido da ROTA LIVRE (2026-10-23) | `ProdutoDetalheTest` (lane Estoque · MySQL) + `produto-painel-detalhe.test.tsx` (Vitest, não roda em lane) | ⬜ nasce neste PR — verde esperado |

> 🔄 **Revalidação G-6 — 2026-10-10 (#9124, lista no desenho do protótipo).** Os 6 contratos
> **continuam os mesmos**: derivam do SDD, da lista Blade e do Delphi, não da tela de cards. O que
> mudou foi a tela e a forma de perguntar ao servidor (prop `lista` = paginador; filtros pelos nomes
> do DataTable: `q`, `active_state`, `per_page`). O `ProdutoIndexContratoTest` foi **reescrito** para
> isso; **run 38087431948 (lane Estoque · MySQL, commit 59fe58ae, com o arquivo fora da quarentena naquele commit): os 6 UC-PIDX passaram**. O arquivo continua listado na quarentena da lane até um PR próprio
> tirá-lo de lá (`.github/` exige aprovação do [W] por CODEOWNERS). A consulta é provada também pelo
> `ProdutoListaConsultaTest` (#9119: paridade com o DataTable do Blade filtro a filtro). As notas
> **"Regressão que defende"** abaixo descrevem a tela de cards (`buildProdutoIndexRows`, `limit(200)`,
> busca e inativos ignorados) — ficam como o registro do achado que a tela nova fecha. Status
> voltam a ⬜ até a lane publicar o veredito do arquivo reescrito.

> ⚠️ **Nenhum status aqui é "✅".** Os 6 UCs nasceram agora e **não rodei** — testes são CT 100/CI
> ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).
> "Vermelho esperado" é **predição ancorada em leitura** (a varredura está em cada UC), não
> veredito. O veredito vem da lane `Estoque · MySQL`, e é ele que manda (G-7).

---

> ℹ️ **`last_run` 2026-07-26 → 2026-09-06 (G-6), e o que mudou na tela NÃO foi comportamento.**
> O único toque em `Index.tsx` foi o atributo **`data-contract="<id>"`** no elemento raiz de
> região (header · kpis-totalizadores · filtros-busca · tabela-cards) — a âncora estável que o `<tela>.map.json` declara em `vivo.ancora`
> (`scripts/governance/design-code-map-check.mjs`). Atributo inerte: nenhum CSS o seleciona
> nestas telas (`grep -rn 'data-contract' resources/css/` → só `.arq-page [data-contract=abas|cabecalho]`)
> e nenhum JS de runtime o lê. Zero JSX estrutural, zero handler, zero prop, zero copy alterada.
> **Nenhum UC desta tela foi reexecutado nem revalidado**; o bump é o que o campo significa na
> prática (*trio reconciliado com a tela nesta data*), não afirmação de re-run.
## UC-PIDX-01 · Todo produto do catálogo é alcançável na lista · `must`
- **Persona:** Larissa (biz=4 no mundo real; biz=1 no teste) procurando a camiseta que acabou de cadastrar. Se a lista some com o produto **sem avisar**, ela conclui que o cadastro falhou e cadastra de novo — duplicata no catálogo.
- **Aceite:** Dado um catálogo com mais produtos do que cabe numa página · Quando abro `/products` · Então **todo** produto ativo é alcançável (por paginação, scroll infinito ou busca server-side) — e a quantidade exibida **bate** com o total anunciado no KPI.
- **Teste:** [`tests/Feature/Produto/ProdutoIndexContratoTest.php`](../../../../tests/Feature/Produto/ProdutoIndexContratoTest.php) — `UC-PIDX-01` (Pest, failing-first, lane `Estoque · MySQL`).
- **Contrato (2 fontes):**
  - **Blade** — o branch `request()->ajax()` (`ProductController@index:73-312`) entrega `Datatables::of($products)` **sem `limit`**: o DataTables pagina server-side e alcança o catálogo inteiro. É o comportamento que roda em produção hoje.
  - **Delphi `AR-PROD-022/023`** — *"Consultar — abre busca/listagem de produtos"* e *"o cadastro vira inativo e **some da lista, mas fica acessível por filtro**"*: no legado a lista é o caminho garantido pra chegar em **qualquer** produto; o que tira algo da vista é **filtro declarado**, nunca um corte invisível.
- **Regressão que defende:** `buildProdutoIndexRows` (`:425-448`) fecha com `->orderBy('products.name')->limit(200)` e **não há paginação em lugar nenhum** — nem prop, nem `<Deferred>` incremental, nem link. Varredura contada em `Index.tsx`: **0** ocorrências de `page`/`paginate`/`next`. O KPI "Total de produtos" (`buildProdutoIndexKpis:382-385`) conta o catálogo **inteiro**. Resultado: com 1.000 produtos, a tela anuncia 1.000 e entrega 200 — os 800 que sobram são os do fim do alfabeto, e **nada na tela diz que foram cortados**. O `EmptyState` ("Ajuste filtros ou cadastre o primeiro produto") reforça a leitura errada.
- **Nota de denominador:** os três builders usam recortes **diferentes** — `kpis.total` exclui `type='modifier'` e conta tudo; `rows` exclui `modifier` e corta em 200; `categorias` (`:477-494`) conta **todos** os produtos da categoria (inclusive `modifier` e inativos). Os números da mesma tela não fecham entre si. O UC trava o eixo principal (alcance); a divergência de contadores está no §Backlog.
- **Status: 🧪** — passou (2026-10-23). Pest na lane Estoque · MySQL, run 39945557255 (main 53e7995, 2026-10-23): 497 testes, 0 falhas, 0 pulados.

---

## UC-PIDX-02 · Busca é resolvida no servidor e acha por SKU de variação · `must`
- **Persona:** balconista com a etiqueta do produto na mão. A etiqueta traz o **SKU da variação** (`sub_sku`, ex. `CAM-AZ-M`), não o SKU do produto-pai. Digitar o que está na etiqueta tem que achar o item.
- **Aceite:** Dado um produto variável cujas variações têm `sub_sku` próprio · Quando busco pelo `sub_sku` de uma variação · Então o produto aparece — e produtos que **não** casam com o termo **não** aparecem.
- **Teste:** [`ProdutoIndexContratoTest`](../../../../tests/Feature/Produto/ProdutoIndexContratoTest.php) — `UC-PIDX-02`.
- **Contrato (3 fontes):**
  - **Blade** — `filterColumn('products.sku', …)` (`:296-301`) busca `whereHas('variations', sub_sku like %termo%)` **`orWhere` `products.sku`**: o legado procura explicitamente no SKU da variação. Server-side, sobre o catálogo inteiro.
  - **`CU-PROD-02`** (SDD §6.1) — *"Grade tam×cor gera N variações; cada uma com `sub_sku` auto"*: o `sub_sku` é a identidade operacional da variação; buscar por ele é o uso previsto.
  - **`AR-PROD-023`** (Delphi) — *"Consultar — abre busca/listagem"*.
- **Regressão que defende:** o `filters.busca` chega ao controller (`:345`) e é **devolvido à página sem ser usado em query nenhuma** — `buildProdutoIndexRows` não recebe o termo (`:350` passa só `$business_id`). A filtragem acontece **no cliente** (`Index.tsx:126-131 (verificado@d4afe95)`), sobre `r.name` e `r.sku` do **produto-pai**, dentro do recorte de 200 do UC-PIDX-01. Varredura contada: `sub_sku` aparece **0 vezes** em `Index.tsx` e **0 vezes** nos 3 builders. Logo, buscar pela etiqueta da variação **não acha nada**, e buscar por nome só acha entre os 200 primeiros.
- **Por que o teste precisa do assert negativo:** como o servidor **ignora** o termo, um teste que só pergunte *"o produto buscado veio?"* passaria **por acidente** (ele vem porque vem tudo). O caso assere também que quem **não** casa fica de fora — sem isso o teste mediria não-execução (`proibicoes.md` §5, 2026-07-24).
- **Status: 🧪** — passou (2026-10-23). Pest na lane Estoque · MySQL, run 39945557255 (main 53e7995, 2026-10-23): 497 testes, 0 falhas, 0 pulados.

---

## UC-PIDX-03 · Preço e custo na lista respeitam a permissão de vê-los · `must`
- **Persona:** vendedor de biz=1 com `product.view`, **sem** direito de ver custo. Ele consulta o catálogo pra conferir SKU e disponibilidade; quanto a empresa pagou pelo item **não é dele**.
- **Aceite:** Dado um usuário sem `view_purchase_price` / `access_default_selling_price` · Quando abre a lista · Então **nenhuma linha** carrega o valor correspondente — o dado não chega ao navegador (não é "coluna escondida por CSS").
- **Teste:** [`ProdutoIndexContratoTest`](../../../../tests/Feature/Produto/ProdutoIndexContratoTest.php) — `UC-PIDX-03`.
- **Contrato (3 fontes, todas concordando):**
  - **Blade, na PRÓPRIA lista** — `resources/views/product/index.blade.php:287 (verificado@d4afe95)` e `:294` envolvem as colunas `purchase_price` e `selling_price` em `@can('view_purchase_price')` / `@can('access_default_selling_price')`. Varredura contada: **2** ocorrências no arquivo da lista; **14** arquivos de `resources/views` usam o gate (5 deles do Produto).
  - **Delphi `AR-PROD-015`** — *"Custo e Margem são gated por permissão — os dois campos **somem** da tela"* (`liedtCusto.Visible := GetPodeVerCustos`). Não é read-only: é ausência.
  - **Precedente do módulo** — `UC-PSHOW-01` (mesma família, na ficha) já está em failing-first; este é o mesmo furo na porta de entrada, onde o dado sai em **lote**.
- **Regressão que defende:** `buildProdutoIndexRows` (`:450-471`) monta **`price`**, **`cost`** e **`margin`** para **toda** linha, sem consultar permissão alguma. As únicas permissões que o branch Inertia lê são `create`/`update`/`delete`/`opening_stock` (`:352-357`) — varredura contada de `view_purchase_price|access_default_selling_price` em `ProductController.php`: **0**. O card renderiza `fmtBRL(row.price)` (`Index.tsx:403 (verificado@d4afe95)`) sem gate; `cost` e `margin` não são renderizados mas **viajam no JSON das props** — visíveis no HTML da página. Ligar a lista React hoje **derruba um gate de custo que o Blade e o Delphi têm**, e em escala (todo o catálogo de uma vez, não um produto por vez).
- **Não é `[V0]`:** não altera cálculo de valor — gateia **exibição**. A REGRA MESTRE (dupla-confirmação + antes→depois) não se aplica; marcar `[V0]` aqui inflaria o ritual sem proteger nada.
- **Status: 🧪** — passou (2026-10-23). Pest na lane Estoque · MySQL, run 39945557255 (main 53e7995, 2026-10-23): 497 testes, 0 falhas, 0 pulados.
  _(Antes:)_ 🧪 **corrigido em 2026-09-18**, junto com o irmão `UC-PSHOW-01` (mesmo furo, mesma
  família). `index()` resolve as duas permissões uma vez e passa ao builder; `buildProdutoIndexRows`
  só emite `price` com `access_default_selling_price`, `cost` com `view_purchase_price`, e `margin`
  **só com os dois** — ela deriva de ambos, então entregá-la sabendo um entrega o outro por dedução.
  O `Index.tsx` acompanha: sem o direito, o bloco "Preço" do card some inteiro (rótulo sobre traço
  afirmaria que há um valor escondido); a unidade fica, porque é dado de cadastro.
  **Veredito medido** (CT 100, 2026-09-18): `UC-PIDX-03 ✓`. Nos dois contratos juntos,
  `3 failed, 7 passed (67 assertions)` — antes do fix eram `5 failed / 5 passed`.
- ⚠️ _(2026-09-18, histórico — em 2026-10-10 o teste reescrito passou inteiro na lane, run 38087431948; a saída da quarentena vai em PR próprio:)_ **O arquivo CONTINUA em quarentena de lane, e isso é honesto:** os outros 3 casos
  (`UC-PIDX-01` corte de 200 sem paginação · `UC-PIDX-02` busca · `UC-PIDX-06` filtro de inativos)
  seguem vermelhos. São a **outra metade** — alcance e filtragem server-side —, não têm relação com
  permissão, e sair da lista exigiria fechá-los também.

---

## UC-PIDX-04 · Lista, KPIs e contadores só enxergam o business atual · `must` `[T0]`
- **Persona:** qualquer tenant. O pior bug do projeto é o catálogo de um business vazar pro outro — aqui em lote, na primeira tela que o operador abre.
- **Aceite:** Dado dois businesses com produtos · Quando abro `/products` no business A · Então nenhuma linha, KPI ou contador de categoria reflete produto do business B.
- **Teste:** [`ProdutoIndexContratoTest`](../../../../tests/Feature/Produto/ProdutoIndexContratoTest.php) — `UC-PIDX-04`.
- **Contrato:** `CU-PROD-10` `[T0]` (SDD §6.1) + [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) + o próprio `Index.charter.md` §Automation Anti-hooks (*"❌ Não acessa produto de outro `business_id`"*) e §Pest GUARD, que **promete** `it('isolates products by business_id')`.
- **Regressão que defende:** é um **guard** (espera-se verde — os 3 builders carimbam `where('business_id', $businessId)`), e o valor é travá-lo, porque a contenção aqui é **acidental, não estrutural**: varredura contada em `app/Product.php` — **0** ocorrências de `addGlobalScope`; o model `extends Model` e usa só `LogsActivity`. **`App\Product` não tem global scope de `business_id`.** Todo o isolamento do módulo depende de cada query lembrar do `where` — e a família já reincidiu duas vezes (`UC-PTAB-04`, cross-tenant gravando em `variation_group_prices`, [#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300); `UC-PEDIT-03`, `first()` → 500 em vez de 404).
- **⚠️ Achado de contrato (documentado, não afirmado como vazamento):** `buildProdutoIndexCategorias` (`:479-486`) faz `leftJoin('products', 'products.category_id', '=', 'categories.id')` e conta `COUNT(products.id)` **sem** carimbar `products.business_id`. Hoje isso não vaza porque `category_id` de um produto aponta pra categoria do próprio business — mas a contenção é **coincidência de dado**, não guard: num model sem global scope, a próxima agregação que junte `products` sem `where` vaza. O teste trava o invariante observável (contador não conta o alheio); **não** fabrico dado corrompido pra "provar" vazamento que hoje não ocorre.
- **Status: 🧪** — passou (2026-10-23). Pest na lane Estoque · MySQL, run 39945557255 (main 53e7995, 2026-10-23): 497 testes, 0 falhas, 0 pulados.

---

## UC-PIDX-05 · Abrir a lista não escreve no banco · `must`
- **Persona:** qualquer operador. Consultar o catálogo é o gesto mais frequente do ERP — se ele muta estado, a auditoria vira ruído e o `updated_at` deixa de significar "alguém alterou".
- **Aceite:** Dado um produto com `updated_at` conhecido · Quando abro a lista (inclusive as props deferidas) · Então nada é escrito — `updated_at` intacto e nenhum log de atividade novo.
- **Teste:** [`ProdutoIndexContratoTest`](../../../../tests/Feature/Produto/ProdutoIndexContratoTest.php) — `UC-PIDX-05`.
- **Contrato:** `Index.charter.md` §Automation Anti-hooks — *"❌ Não escreve no banco (read-only)"*, *"❌ Não roda jobs"*, *"❌ Não dispara emails"* — + **`AR-PROD-064`** (movimento é append-only; consulta não muta) + `CU-PROD-15.4`.
- **Regressão que defende:** é **guard**, e o irmão prova que a família existe neste módulo: `productStockHistory` **faz UPDATE dentro de um GET** no branch `ajax()` (documentado em `CU-PROD-11` / `StockHistory.casos.md`). A lista é o GET mais chamado do módulo; se um dia alguém pendurar "atualiza popularidade ao listar" (o `popularity` está hardcoded 0 esperando implementação — §Backlog), o teste morde.
- **Status: 🧪** — passou (2026-10-23). Pest na lane Estoque · MySQL, run 39945557255 (main 53e7995, 2026-10-23): 497 testes, 0 falhas, 0 pulados.

---

## UC-PIDX-06 · "Mostrar inativos" é resolvido no servidor · `should`
- **Persona:** Larissa com catálogo grande. Produto inativo é ruído no dia a dia — e, quando ela **precisa** dele (reativar, consultar preço antigo), o filtro tem que trazê-lo de volta.
- **Aceite:** Dado um produto inativo e um ativo · Quando abro a lista com o toggle **desligado** (default) · Então o inativo **não vem no payload**; quando ligo o toggle · Então ele vem, marcado como inativo.
- **Teste:** [`ProdutoIndexContratoTest`](../../../../tests/Feature/Produto/ProdutoIndexContratoTest.php) — `UC-PIDX-06`.
- **Contrato (3 fontes):**
  - **Blade** — `active_state` chega por request e o servidor aplica `$products->Active()` / `->Inactive()` (`ProductController@index:173-179`). É filtro de query, não de tela.
  - **Delphi `AR-PROD-022`** — *"o cadastro vira inativo e some da lista, mas fica acessível por filtro de excluídos/inativos"*; **`AR-PROD-003`** — *"produto inativo não some do cadastro, só do fluxo de venda"*.
  - **Charter** §Goals — *"Toggle 'Mostrar inativos' (default: oculto)"*.
- **Regressão que defende:** `filters.mostrarInativos` é lido (`:347`) e **devolvido sem uso**; `buildProdutoIndexRows` não filtra por `is_inactive` — manda ativos e inativos sempre. O ocultamento acontece no cliente (`Index.tsx:120-122 (verificado@d4afe95)`), **depois** do corte de 200: um catálogo com muitos inativos gasta as 200 vagas com linhas que a tela vai esconder, e produtos ativos legítimos somem da vista (composição com UC-PIDX-01). Pelo mesmo caminho, o badge do Blade para `not_for_selling` (`:263-264`) não tem par no React — §Backlog.
- **Status: 🧪** — passou (2026-10-23). Pest na lane Estoque · MySQL, run 39945557255 (main 53e7995, 2026-10-23): 497 testes, 0 falhas, 0 pulados.


---

## UC-PIDX-07 · Os 4 indicadores contam o catálogo inteiro; o valor é o total do relatório de estoque · `must`
- **Persona:** Larissa abre o catálogo e quer, num olhar, quantos produtos tem, quantos estão abaixo do alerta, quantos inativos e quanto dinheiro está parado no estoque — e poder conferir esse dinheiro na aba "Relatório de estoque".
- **Aceite:** Dado o catálogo da empresa · Quando abro `/products` · Então "Produtos cadastrados", "Abaixo do alerta" e "Inativos" são as contagens das MESMAS linhas que a lista mostra sem filtro (abaixo do alerta = estoque gerenciado, alerta definido e estoque ≤ alerta, a regra da linha); e "Valor em estoque (custo)" é o total da coluna "valor pelo preço de compra" do relatório de estoque, todas as linhas e sem filtro — e quando entra uma compra recebida de N unidades a C, ele sobe exatamente N × C depois do "Atualizado". Sem `stock_report.view` + `view_product_stock_value` o valor não vem (ausência, não zero).
- **Teste:** [`ProdutoListaKpisTest`](../../../../tests/Feature/Produto/ProdutoListaKpisTest.php) — `UC-PIDX-07`.
- **Contrato:**
  - **Charter v3** §Goals — *"4 indicadores (protótipo): produtos cadastrados (variáveis · composições), abaixo do alerta, valor em estoque pelo custo (o MESMO total do Relatório de estoque — regra mestre de valor: prova dupla antes de subir) e inativos"*.
  - **Decisão [F] 2026-10-10** — opção **B**: o total da aba "Relatório de estoque" (soma das linhas), não o "estoque de fechamento" do topo de Relatórios → Estoque (`getOpeningClosingStock`, outro conjunto de compras, ~0,1% de diferença medida em produção na biz 1).
  - **Blade** — as colunas de valor do relatório só com `view_product_stock_value`; o resumo de valor só com `stock_report.view` + `view_product_stock_value` (`ReportController::getStockValue`).
- **Prova dupla do valor (regra mestre):** (1) igual à soma linha a linha do JSON do DataTable do relatório (o código da lista Blade); (2) a variação com uma compra conhecida é a conta à mão. Em produção (biz 1, 2026-10-10) a soma no banco só da coluna `stock_price` deu diferença 0 contra a soma linha a linha da consulta completa.
- **Status: 🧪** — passou (2026-10-23). Pest na lane Estoque · MySQL, run 39945557255 (main 53e7995, 2026-10-23): 497 testes, 0 falhas, 0 pulados.


---

## UC-PIDX-08 · A aba "Relatório de estoque" mostra as linhas de Relatórios → Estoque e totaliza todas · `must`
- **Persona:** Larissa filtra o catálogo (um local, uma categoria) e quer ver, sem sair da tela, quanto tem de cada item em cada local e quanto isso vale — e conferir o "Valor em estoque (custo)" do topo.
- **Aceite:** Dado um filtro na tela · Quando abro a aba "Relatório de estoque" · Então as linhas são as MESMAS que Relatórios → Estoque mostra para esse filtro (25 por página, SKU e local); o rodapé traz os totais de **todas** as linhas (não só da página), iguais à soma linha a linha do relatório; sem filtro, o "valor pelo custo" do rodapé é o indicador "Valor em estoque (custo)" (UC-PIDX-07). Sem `stock_report.view` a aba não aparece e a prop não vem.
- **Teste:** [`ProdutoListaEstoqueAbaTest`](../../../../tests/Feature/Produto/ProdutoListaEstoqueAbaTest.php) — `UC-PIDX-08`.
- **Contrato:**
  - **Charter v3** §Goals — *"Abas: Todos os produtos (com contagem) e Relatório de estoque (as colunas da lista Blade, com totais), esta só com `stock_report.view`"*.
  - **Blade** — a aba `#product_stock_report` (`product/index.blade.php`, `@can('stock_report.view')`) pede `/reports/stock-report` com os filtros da lista (local, categoria, marca, unidade, tipo, situação, "não para venda", modelo). O rodapé dela somava só a página; o protótipo (`RelatorioEstoque`) soma todas — e é esse total que o indicador do topo mostra (decisão [F] 2026-10-10, opção B).
  - **Contas** — as de `ProductUtil::linhaDoRelatorioEstoque` (movidas do `ReportController` no #9128, sem mudança), as mesmas da tela de Relatórios → Estoque.
- **Status: 🧪** — passou (2026-10-23). Pest na lane Estoque · MySQL, run 39945557255 (main 53e7995, 2026-10-23): 497 testes, 0 falhas, 0 pulados.


---

## UC-PIDX-09 · O painel de detalhe mostra o conteúdo da janela "Ver" da Blade, com os mesmos gates · `must` `[T0]`
- **Persona:** Larissa clica num produto da lista e quer ver tudo dele — dados, variações e preços, estoque por local, dados fiscais, prateleira — sem sair do catálogo.
- **Aceite:** Dado um produto da empresa · Quando abro o painel (`?ver={id}`) · Então os preços de compra só vêm com `view_purchase_price`, os de venda só com `access_default_selling_price` e o % de lucro só com as duas (sem a permissão a chave não existe — ausência, não zero); o estoque por local só vem com `stock_report.view` e é o mesmo de Relatórios → Estoque; e um produto de outra empresa não abre (`null`).
- **Teste:** [`ProdutoDetalheTest`](../../../../tests/Feature/Produto/ProdutoDetalheTest.php) — `UC-PIDX-09`.
- **Contrato:**
  - **Charter v3** §Goals — painel de detalhe do protótipo (`DetalheDrawer`: Dados, Variações/Composição, Estoque, Fiscal, Prateleira).
  - **Blade** — `ProductController::view` + `product/view-modal.blade.php` (e `single_/variable_/combo_product_details`): os gates `@can('view_purchase_price')` e `@can('access_default_selling_price')`, preço por grupo só dos grupos permitidos, estoque por local carregado de `/reports/stock-report` (pede `stock_report.view`).
  - **ADR 0093** — `Product::where('business_id', …)`: produto de outra empresa não abre.
- **Divergência deliberada, mais restrita que a Blade:** o total da composição pelo custo só vem com `view_purchase_price` (a Blade mostrava a coluna a todos). E "origem da mercadoria" (protótipo) não vem: o campo não existe no banco.
- **Desde 2026-10-26 (decisão [F]):** o imposto do painel aparece por relevância, junto do preço (UC-PIDX-19), e a aba Fiscal só em empresa que emite nota (UC-PIDX-25). Os gates de preço continuam os da Blade; a única diferença é omitir "Imposto aplicável: Nenhum" (`resources/views/product/view-modal.blade.php:94-100`, verificado@3886121) quando o produto não tem imposto, porque o preço mostrado já é o final.
- **Status: 🧪** — passou (2026-10-23). Pest na lane Estoque · MySQL, run 39945557255 (main 53e7995, 2026-10-23): 497 testes, 0 falhas, 0 pulados.


---

## UC-PIDX-10 · "Exportar filtrados" traz as mesmas linhas da lista, em CSV · `should`
- **Persona:** Larissa filtra o catálogo (uma marca, os inativos, uma busca) e quer a lista numa planilha para conferir ou mandar para alguém.
- **Aceite:** Dado um filtro ou uma busca na tela · Quando clico em "Exportar filtrados" · Então baixo um CSV (`;`, vírgula decimal, UTF-8 — abre certo no Excel) com as MESMAS linhas que a lista mostra para esse filtro, todas as páginas; as colunas de preço de compra e de venda só existem para quem tem a permissão de vê-las; produto de outra empresa não entra.
- **Teste:** [`ProdutoListaExportarTest`](../../../../tests/Feature/Produto/ProdutoListaExportarTest.php) — `UC-PIDX-10`.
- **Contrato:**
  - **Protótipo** — rodapé da vista `lista`: "Exportar filtrados" (`exportarCsv(ordenados)`).
  - **Lista** — as linhas saem de `buildProdutoListaPaginada` (a mesma consulta, filtros, busca e ordem da tela); o gate de preço é o do UC-PIDX-03 (sem permissão a coluna não existe).
  - A "Baixar Excel" do cabeçalho segue sendo a exportação completa da Blade (`/products/download-excel`).
- **Desde 2026-10-26 (decisão [F]):** a coluna Imposto do CSV segue a da lista e só sai se a empresa tem alíquota cadastrada (UC-PIDX-34).
- **Status: 🧪** — passou (2026-10-23). Pest na lane Estoque · MySQL, run 39945557255 (main 53e7995, 2026-10-23): 497 testes, 0 falhas, 0 pulados.

## UC-PIDX-11 · A consulta não oferece compra · `should`
- **Persona:** o vendedor e o administrador consultam o produto para informar preço e estoque; comprar é trabalho da tela de compras.
- **Aceite:** Dado qualquer produto, inclusive ativo e com controle de estoque, e mesmo com a permissão de criar compra · Quando abro o menu Ações da linha ou o painel de detalhe · Então não há "Comprar este produto" nem "Comprar", e a lista não manda a permissão `comprar`.
- **Teste:** [`ProdutoDetalheTest`](../../../../tests/Feature/Produto/ProdutoDetalheTest.php) — `UC-PIDX-11` (a lista não manda `comprar`, nem a quem tem `purchase.create`); [`produto-menu-da-linha.test.tsx`](../../../../tests/js/produto-menu-da-linha.test.tsx) — `UC-PIDX-11` (o menu); [`produto-painel-detalhe.test.tsx`](../../../../tests/js/produto-painel-detalhe.test.tsx) — `UC-PIDX-11 · rodapé`.
- **Contrato:** decisão [F] de 2026-10-26, comentário na âncora: *"Aqui não é a tela do comprador, então não fica este botão aqui"*. Protótipo `produto-blade.jsx` sem "Comprar" no `acoesDe` nem no rodapé do `DetalheDrawer` (#9925).
- **Antes (2026-10-11 a 2026-10-26) o caso era o oposto:** "Comprar" abria a nova compra com o produto, a pedido do [F] em 2026-10-11. A tela de compra continua aceitando o produto pelo endereço (UC-PURCRE-09, no módulo Compras); só a consulta deixou de oferecer o atalho.
- **Status: 🧪** — aguarda veredito da lane (o Pest é novo). Vitest local em 2026-10-26: os 103 testes dos 9 arquivos da lista e do painel passaram; mutação de controle (um "Comprar" de volta no menu) derrubou 3 casos.

## UC-PIDX-12 · Os 3 indicadores da aba Estoque do painel somam o relatório de estoque do produto · `must`
- **Persona:** Larissa abre o painel de um produto e quer ver de relance quanto tem dele, quanto isso vale pelo que pagou e por quanto venderia — sem ir a Relatórios → Estoque e somar local por local.
- **Aceite:** Dado um produto com controle de estoque e um usuário com `stock_report.view` · Quando abro a aba Estoque do painel · Então "Estoque atual", "Valor pelo custo" e "Valor pela venda" (com o lucro potencial na legenda) são a **soma das linhas** que a própria aba lista — as de Relatórios → Estoque para esse produto. Sem `view_product_stock_value` os valores não vêm (aparece "—", nunca zero); sem `stock_report.view` nem a aba nem os indicadores vêm.
- **Teste:** [`ProdutoDetalheTest`](../../../../tests/Feature/Produto/ProdutoDetalheTest.php) — `UC-PIDX-12`: cenário de dois locais (7 un. a 12 e 3 un. a 30, venda a 20) conferido por dois caminhos — a conta à mão (10 · 174 · 200 · 26) e a soma, linha a linha, do JSON do DataTable de Relatórios → Estoque (o código da Blade).
- **Contrato:**
  - **Protótipo** — `DetalheDrawer`, aba Estoque: três placas (estoque atual · valor pelo custo · valor pela venda, com lucro potencial).
  - **Contas** — nenhuma nova: `ProductUtil::linhaDoRelatorioEstoque` por linha e `ProductUtil::somaDasLinhasDoEstoque` para somar — a MESMA função do rodapé da aba "Relatório de estoque" (UC-PIDX-08).
  - **Blade** — valores do estoque só com `view_product_stock_value` (colunas do relatório de estoque).
- **Divergência deliberada do protótipo:** a legenda do custo diz "pelo preço pago nas compras" (o protótipo diz "preço de compra padrão"). O campo do relatório é o que resta de cada compra recebida × o preço pago nela, não estoque × preço de compra do cadastro — é a mesma legenda do indicador do topo da lista (UC-PIDX-07).
- **Status: 🧪** — passou (2026-10-23). Pest na lane Estoque · MySQL, run 39945557255 (main 53e7995, 2026-10-23): 497 testes, 0 falhas, 0 pulados.

## UC-PIDX-13 · Clicar no cabeçalho ordena a lista, nas duas direções · `should`
- **Persona:** Larissa quer ver primeiro o que tem mais estoque, ou achar um produto pela marca — clica no nome da coluna, como fazia na lista antiga.
- **Aceite:** Dada a lista aberta · Quando clico no cabeçalho de Produto, Preço de compra, Preço de venda, Estoque atual, Tipo, Categoria, Marca ou SKU · Então a lista volta do servidor ordenada por essa coluna (todas as páginas, não só a que está na tela); clicar de novo na mesma coluna inverte; a coluna ativa mostra a direção, inclusive a ordem padrão (Produto, crescente). Ação, Imagem, Locais, Imposto e IMEI / série não ordenam, como no protótipo. Ordenar por preço de compra ou de venda só existe para quem pode ver esse preço — sem a permissão o pedido cai na ordem por nome, e a ordem das linhas não revela o custo.
- **Teste:** [`ProdutoListaOrdenacaoTest`](../../../../tests/Feature/Produto/ProdutoListaOrdenacaoTest.php) — `UC-PIDX-13` (o servidor: três produtos com nome, estoque e custo em ordens diferentes entre si) e [`produto-lista-ordenacao.test.tsx`](../../../../tests/js/produto-lista-ordenacao.test.tsx) (a tela: o botão no cabeçalho e o pedido que ele manda, com a tabela de verdade).
- **Contrato:**
  - **Protótipo** — `ListaProdutos`: as colunas com `sortable` e o rodapé "clique no cabeçalho pra ordenar".
  - **Servidor** — `$ordenaveis` em `ProductController::buildProdutoListaPaginada`; `compra`/`venda` só entram com `view_purchase_price` / `access_default_selling_price`.
- **Achado que originou o caso (medido em produção, 2026-10-12):** o rodapé prometia a ordenação, mas o cabeçalho era texto puro e o clique não fazia nada. As 8 colunas declaravam `enableSorting: true` sem `accessorFn` (a tabela só desenha o botão com ele), e a tela não informava à tabela a ordem atual (sem isso o segundo clique não inverte). Typecheck, build e CI estavam verdes.
- **Status: 🧪** — passou (2026-10-23). Pest na lane Estoque · MySQL, run 39945557255 (main 53e7995, 2026-10-23): 497 testes, 0 falhas, 0 pulados · Vitest local em 2026-10-23 (main 53e7995): os 133 testes dos arquivos da lista passaram (estes arquivos não rodam em lane).

## UC-PIDX-14 · "Cadastro rápido" cria o produto com os preços que o formulário Blade gravaria · `must` `[V0]` `[T0]`
- **Persona:** Larissa está com o cliente na frente e o produto ainda não existe no catálogo. Quer cadastrar o mínimo — nome, unidade e os dois preços — e completar o resto depois.
- **Aceite:** Dada a permissão de criar produto · Quando preencho a janela "Cadastro rápido" (nome, SKU opcional, unidade, categoria opcional, preço de compra e de venda sem imposto, "Gerenciar estoque" marcado) e salvo · Então o produto nasce na minha empresa, do tipo simples, sem imposto, com o SKU gerado se eu deixei em branco, e a variação guarda: preço de compra e de venda como digitei, os dois "com imposto" iguais a eles, e a margem `(venda − compra) ÷ compra × 100` (custo zero dá margem zero). Empresa com um local permitido: vai para ele sem perguntar; com mais de um: escolho ao menos um, e só entra local permitido da empresa. Preço fora do formato com vírgula decimal é recusado antes de virar número. Campo que a janela não tem (estoque inicial, imposto, "não para venda") não chega à gravação, mesmo se enviado.
- **Teste:** [`ProdutoListaCadastroRapidoTest`](../../../../tests/Feature/Produto/ProdutoListaCadastroRapidoTest.php) — `UC-PIDX-14` (o que é gravado) e [`produto-cadastro-rapido.test.tsx`](../../../../tests/js/produto-cadastro-rapido.test.tsx) (o que a janela manda).
- **Prova de valor por dois caminhos** (REGRA MESTRE): compra 10 e venda 15 → conta à mão (10 · 10 · 50% · 15 · 15) **e** o mesmo produto criado pelo formulário Blade (`saveQuickProduct`) com o que o JS dele preenche — as duas variações saem iguais. Com 1234,56 e 2000: os quatro preços saem idênticos aos do Blade; a margem sai 62,0010 aqui e 62,00 lá.
- **Contrato:**
  - **Charter v3** §Goals — a barra da lista traz "Cadastro rápido".
  - **Protótipo** — `ModalCadastroRapido` (6 campos); "Gerenciar estoque" marcado e o campo de locais com 2+ locais são decisão [F] 2026-10-12.
  - **Blade** — `quick_add_product.blade.php` + `single_product_form_part` (padrões: `single`, `C128`, `exclusive`, estoque gerenciado; local único pré-escolhido) e `saveQuickProduct`, que é quem grava: o endpoint novo só monta o pedido e entrega a ele.
  - **Margem** — `Util::get_percent`, a conta do `__get_rate` do Blade.
- **Divergências deliberadas do formulário Blade:**
  - a margem vai com 4 casas (o Blade arredondava na tela, 2 casas) — a coluna guarda 4, e margem com 2 casas não reconstitui o preço (lição do charter de Preços por grupo);
  - com mais de um local, escolher ao menos um é obrigatório (no Blade o campo podia ficar vazio e o produto não aparecia em local nenhum) — decisão [F];
  - preço só com vírgula decimal e sem milhar; "12.125" é recusado em vez de adivinhado.
- **Status: 🧪** — passou (2026-10-23). Pest na lane Estoque · MySQL, run 39945557255 (main 53e7995, 2026-10-23): 497 testes, 0 falhas, 0 pulados · Vitest local em 2026-10-23 (main 53e7995): os 133 testes dos arquivos da lista passaram (estes arquivos não rodam em lane).

## UC-PIDX-15 · Estoque inicial em janela, com o mesmo saldo que o formulário Blade · `must` `[V0]` `[T0]`
- **Persona:** Larissa acabou de cadastrar um produto e quer informar quanto já tem dele em cada loja, sem sair da lista.
- **Aceite:** Dado um produto da empresa com controle de estoque e a permissão de estoque inicial · Quando escolho "Adicionar ou editar estoque inicial" no menu da linha · Então abre uma janela com uma linha por local em que o produto está disponível (e que eu posso acessar) e por variação, trazendo as linhas já lançadas; posso mudar quantidade e custo, informar validade, lote, data e observação, e acrescentar mais uma linha no mesmo local. Ao salvar, o saldo por local, as linhas de compra e as transações ficam **iguais** aos que o formulário Blade deixaria para os mesmos dados. Local que o pedido não cita não é tocado. Número só com vírgula decimal. Linha, variação ou local que não são deste produto e desta empresa são recusados, e nada é gravado.
- **Teste:** [`EstoqueInicialJanelaTest`](../../../../tests/Feature/Produto/EstoqueInicialJanelaTest.php) — `UC-PIDX-15` (o que é gravado, comparado com um produto gêmeo lançado pelo caminho Blade) e [`produto-estoque-inicial.test.tsx`](../../../../tests/js/produto-estoque-inicial.test.tsx) (o que a janela manda).
- **Prova de estoque por dois caminhos** (REGRA MESTRE): 10 un. a 5 num local e 2,5 un. a 12,125 no outro → conta à mão (saldos 10 e 2,5; totais 50 e 30,3125) **e** o gêmeo lançado por `POST /opening-stock/save` — saldo, linhas e transações comparados por local. O mesmo para aumentar (10 → 14), diminuir (14 → 4) e acrescentar uma segunda linha (10 + 3 = 13).
- **Contrato:**
  - **Protótipo** — `ModalEstoqueInicial` (por local: quantidade, custo sem imposto, validade, lote, subtotal).
  - **Blade** — `opening_stock/form-part.blade.php`: a janela mantém TUDO o que a tela tem (linha por variação, várias linhas por variação, data, observação, segunda unidade) e `OpeningStockController@save` continua sendo o único que grava; `salvarDaJanela` só confere e monta o pedido.
  - **Canon** — `CU-PROD-04` e os UC-PINIC-01..04 de [estoque-inicial.casos.md](../../../../memory/requisitos/Produto/_telas/estoque-inicial.casos.md), que seguem valendo para o caminho Blade.
- **O que a janela confere a mais que o formulário Blade (antes de gravar, sem mudar a conta):**
  - o `save()` apaga, em cada local enviado, a linha de estoque inicial que não vier no pedido, e desconta o saldo; a janela sempre reenvia todas, e o servidor recusa pedido que não traga todas as linhas existentes dos locais citados (alguém lançou uma linha depois que a janela abriu);
  - linha existente tem de ser estoque inicial deste produto, nesta empresa, naquele local e variação;
  - número só com vírgula decimal e sem milhar — "1.500" e "12.125" são recusados, não adivinhados;
  - a data vai e volta sem deslocamento: salvar sem mexer na data não a altera (na tela Blade a data exibida passa pelo `format_date`, com o deslocamento histórico de 3 horas);
  - com validade ou lote desligados na empresa, a linha existente mantém o que já tinha (o formulário Blade, sem o campo, apagava).
- **Achado do caminho antigo, NÃO resolvido aqui (hipótese por leitura, ainda sem medição):** ao zerar uma linha existente, o `save()` não chama `updateProductQuantity` (`if ($qty_remaining != 0)`), então o saldo do local não desceria. A janela herda o que o Blade faz — o teste afirma só a igualdade entre os dois caminhos nesse caso. Decisão de conserto é do [W]/[F].
  - **Medido em 2026-10-15** ([`EstoqueInicialZerarLinhaTest`](../../../../tests/Feature/Produto/EstoqueInicialZerarLinhaTest.php), CT 100 em `8723405fb`): lançar 10 e depois zerar a linha pelo `/opening-stock/save` grava a linha com quantidade 0, mas o saldo do local **fica em 10**. O controle (baixar de 10 para 1) desconta certo, então o defeito é só no zero. O teste está na quarentena da lane de Estoque até o conserto; o conserto mexe em estoque e segue a regra mestre (duas provas e antes→depois) — decisão [W]/[F].
- **Status: 🧪** — passou (2026-10-23). Pest na lane Estoque · MySQL, run 39945557255 (main 53e7995, 2026-10-23): 497 testes, 0 falhas, 0 pulados · Vitest local em 2026-10-23 (main 53e7995): os 133 testes dos arquivos da lista passaram (estes arquivos não rodam em lane).

## UC-PIDX-16 · Arrastar a borda da coluna muda a largura dela · `should`
- **Persona:** Larissa tem produto de nome comprido que aparece cortado; quer abrir a coluna "Produto" e apertar "Locais", como faz numa planilha.
- **Aceite:** Dada a lista aberta · Quando arrasto a borda direita do cabeçalho de Produto, Locais, Preço de compra, Preço de venda, Estoque atual, Tipo, Categoria, Marca ou Imposto · Então só aquela coluna muda de largura, acompanhando o mouse, e nunca fica menor que 48px; as outras colunas ficam como estavam e a tabela cresce ou encolhe o mesmo tanto (com a barra de rolagem horizontal). Ação e Imagem não têm borda de arrastar, nem a última coluna da grade. Arrastar **não ordena** a lista. Ordenar, trocar de página, filtrar e ligar ou desligar colunas não desfazem a largura · **E ela fica lembrada no navegador** (desde 2026-10-17): recarregar a página ou voltar da edição de um produto abre com a mesma largura; "Voltar às larguras de saída", no menu "Colunas" (só aparece quando há largura ajustada), desfaz.
- **Teste:** [`produto-lista-redimensionar.test.tsx`](../../../../tests/js/produto-lista-redimensionar.test.tsx) — `UC-PIDX-16` (as colunas de verdade na tabela de verdade) e [`datatable-redimensionar.test.tsx`](../../../../tests/js/datatable-redimensionar.test.tsx) (a tabela, sozinha); a largura lembrada, em [`produto-lista-encaixe.test.tsx`](../../../../tests/js/produto-lista-encaixe.test.tsx) — `UC-PIDX-16 · a largura arrastada fica lembrada no navegador`. Rodam em lane: `produto-lista-redimensionar` e `produto-lista-encaixe` no Vitest do `Frontend / Vite build` (`.github/workflows/ci.yml`, desde 2026-10-18), e `DataTable resizable…` em [`tests/a11y-primitives.test.tsx`](../../../../tests/a11y-primitives.test.tsx) (lane `datatable-nome-acessivel-gate`).
- **Contrato:**
  - **Protótipo** — `ListaProdutos` roda no `DataTablePro` do DS (= `DataGrid` com `resizable`); a regra está em `prototipo-ui/design-system/components/DataGrid/DataGrid.jsx` (`startResize`): alça de 7px, piso de 48px, sem alça na coluna `resizable: false` (Ação, Imagem) e na última. O rodapé diz "arraste a borda pra redimensionar".
  - **Medido no protótipo com arrasto real (2026-10-13, 1280px):** +50px levou Produto de 300 para 350 e a tabela de 1704 para 1754; encolher Locais além do piso parou em 48; a ordenação não mudou.
- **O que NÃO faz, como no protótipo:** ~~não lembra a largura entre visitas~~ (valeu até 2026-10-17 — ver a diferença abaixo); não tem atalho de teclado nem toque — é só mouse; não tem duplo clique para ajustar ao conteúdo.
- **Diferença deliberada do protótipo (2026-10-17, pedido do [F] a partir da reclamação da ROTA LIVRE):** a largura arrastada fica lembrada no navegador (`oimpresso.produto.lista.larguras`), como a escolha de colunas e a densidade. Sem isso, a ROTA LIVRE refazia o ajuste a cada visita: a edição do produto é Blade e recarrega a página na volta.
- **Diferença deliberada do protótipo:** lá a alça é deslocada 3px para fora da coluna e o cabeçalho vizinho cobre essa parte — sobram 4px úteis (medido com `elementFromPoint`). Aqui os 7px ficam inteiros dentro da coluna, para a borda ser mais fácil de pegar.
- **Onde mora:** `resizable` é opção nova do `shared/DataTable`, desligada por padrão — as outras telas que usam a tabela não mudam.
- **Defeito da primeira versão, medido em produção (2026-10-13, logo depois do deploy do #9233):** a largura era guardada dentro da tabela, e a tabela mora dentro de `<Deferred data="lista">`, que a desmonta enquanto a lista recarrega. Ordenar por "Estoque atual" devolveu Produto de 348 para 300 (o nó `<table>` era outro; o rodapé da página, fora do `<Deferred>`, era o mesmo). O aceite acima — "ordenar, trocar de página e filtrar não a desfazem" — estava escrito e era falso. Os testes daquela versão usavam `rerender`, que mantém a tabela montada, e por isso não viam. Conserto: a largura passa a morar na página (`columnWidths` + `onColumnResize`), e o caso `ordenar, trocar de página ou filtrar (a tabela some e volta)…` reproduz o intervalo sem tabela.
- **Status: 🧪** — passou (2026-10-23). Vitest no Frontend / Vite build, job 120384523098 (main 53e7995, 2026-10-23).

## UC-PIDX-17 · "Dados fiscais" no menu da linha abre o painel na aba Fiscal · `should`
- **Persona:** Larissa vai emitir nota e quer conferir o NCM e o CFOP de um produto sem abrir o cadastro.
- **Aceite:** Dada a lista aberta e a permissão de ver o produto · Quando escolho "Dados fiscais (NCM/CFOP)" no menu Ações da linha · Então o painel de detalhe daquele produto abre **já na aba Fiscal**, com NCM, CEST, CFOP dentro e fora do estado e os CSTs. O item só existe em empresa que emite nota (módulo Fiscal no pacote), como a aba Fiscal (UC-PIDX-25); não depende de permissão além da de ver o produto. O item fica logo depois de "Adicionar ou editar preços por grupo" e não depende de o produto ter estoque nem de estar ativo. Sem a permissão de ver o produto, o item não aparece (como o "Ver"). Abrir um produto pelo "Ver" ou pelo clique na linha continua começando na aba Dados.
- **Teste:** [`produto-menu-da-linha.test.tsx`](../../../../tests/js/produto-menu-da-linha.test.tsx) — `UC-PIDX-17` (o item, a posição, a permissão e a ação que ele dispara) e [`produto-painel-detalhe.test.tsx`](../../../../tests/js/produto-painel-detalhe.test.tsx) — `UC-PIDX-17` (o painel abre na aba pedida).
- **Contrato:**
  - **Protótipo** — `acoesDe`: `{ l: "Dados fiscais (NCM/CFOP)", … on: () => onAcao("fiscal", p) }`, no grupo de Transferir / Usar em uma OS (o "Comprar" saiu em 2026-10-26, UC-PIDX-11); a aba `fiscal` do `DetalheDrawer`.
  - **Dados** — os da aba Fiscal que já existia (`buildProdutoDetalhe` → `fiscal`); nada novo vem do servidor.
- **Diferença deliberada do protótipo:** lá `onAcao("fiscal")` faz o mesmo que o "Ver" (`setVer(p)`), e o painel nasce sempre em "Dados" — o item leva o nome de fiscal e cai na aba errada. Aqui ele abre na aba Fiscal.
- **Efeito no "Ver":** a aba ativa do painel passou a ser guardada pela página, e cada abertura recomeça na aba pedida. Antes o painel guardava a aba e ficava montado entre uma abertura e outra. Medido em produção em 2026-10-13, antes desta mudança: abrir o produto "2" pelo "Ver", ir para Estoque, fechar e abrir o "55555555555" pelo "Ver" caiu em Estoque. No protótipo o painel sempre abre em Dados.
- **Não coberto por teste:** a ligação dentro de `Produto/Index.tsx` (a ação `fiscal` chamar a abertura com a aba Fiscal) — nenhum teste renderiza a página inteira. Conferido em produção depois do deploy.
- **Desde 2026-10-26 (decisão [F]):** o item só aparece em empresa que emite nota, como no protótipo (#9925). O servidor manda `contexto.emite_nota`, com o mesmo teste de pacote do botão "Abrir no módulo Fiscal".
- **Status: 🧪** — Vitest local em 2026-10-26 (este PR): os 9 arquivos de teste da lista e do painel (112 testes) passaram (estes arquivos não rodam em lane). Mutação: tirar o teste do pacote do item derruba o caso "empresa que não emite nota não tem o item".

## UC-PIDX-18 · Aba "Relatório de estoque": linhas por página e lucro potencial com cor · `should` `[V0]`
- **Persona:** Larissa confere o estoque de uma categoria inteira e não quer passar página por página; e quer bater o olho no que dá lucro.
- **Aceite:** Dada a aba "Relatório de estoque" · Quando escolho quantas linhas mostrar no seletor do rodapé (25, 50, 100, 200, 500, 1000 ou Todos) · Então a aba volta à página 1 com esse tamanho, e os **totais do rodapé não mudam** — continuam somando todas as linhas dos filtros. Valor fora das opções cai no padrão. O tamanho da aba é independente do da lista de produtos; sem escolher nada, as duas nascem com o mesmo. Em cada linha, o **lucro potencial** aparece verde quando é positivo e vermelho quando é negativo; zero e o total do rodapé ficam sem cor.
- **Teste:** [`ProdutoListaEstoqueAbaTest`](../../../../tests/Feature/Produto/ProdutoListaEstoqueAbaTest.php) — `UC-PIDX-18` (26 linhas num local próprio: 25 deixa uma para a página 2, 50 e "Todos" trazem as 26; totais iguais nos três tamanhos; valor inválido cai no padrão) e [`produto-relatorio-estoque.test.tsx`](../../../../tests/js/produto-relatorio-estoque.test.tsx) (a cor e o seletor).
- **Prova dos totais por dois caminhos** (REGRA MESTRE): estoque 1+2+…+26 = 351, custo ×12, venda ×20 — conta à mão **e** a soma do JSON do DataTable do relatório (o código da Blade), comparadas com os totais da aba em 25, 50 e Todos.
- **Contrato:**
  - **Protótipo** — `RelatorioEstoque`: `<td … style={{ color: "var(--pos)" }}>` no lucro da linha; total em negrito, sem cor; `Pagination` com tamanho de página.
  - **Blade** — `stock_report_table` é um DataTable com o menu de tamanho padrão do sistema (`public/js/common.js`, `aLengthMenu`: 25/50/100/200/500/1000/todas) e o padrão da empresa (`default_datatable_page_entries`).
  - **Contas** — nenhuma nova: as linhas e os totais são os de `buildRelatorioEstoqueDaLista`; só o tamanho da página deixou de ser fixo em 25.
- **Diferenças deliberadas do protótipo:**
  - as opções são as da lista Blade (25 … 1000, Todos), não 10/25/50/100 — a mesma escolha já feita para a lista de produtos, e é o que a aba Blade oferecia;
  - lucro **negativo** fica vermelho. No protótipo o lucro é sempre positivo e sempre verde; com estoque de verdade existe custo acima do preço de venda, e verde ali enganaria;
  - o rodapé mantém "Página X de Y · N linhas" com Anterior/Próxima; o protótipo usa páginas numeradas.
- **Linha em alerta de estoque:** a linha inteira continua vermelha, como na Blade; a célula do lucro segue a regra dela (verde se positivo).
- **Defeito achado no teste em produção (2026-10-14, depois do deploy do #9324):** as classes de cor chegavam ao DOM e a célula continuava na cor comum — a cor computada era a mesma nas 40 linhas de lucro positivo, nas 3 negativas e no total. Causa medida: o bundle da tela tem `.pb-tbl tbody td { color: var(--text) }` fora das camadas do Tailwind, e regra sem camada vence `@layer utilities`. A linha vermelha de alerta estava inerte desde que entrou (#9129, 2026-10-10), por **outra** causa: herança — a classe ficava no `<tr>`, e cada `td` declara cor própria, o que vence o valor herdado com camada ou sem. E a mensagem de lista vazia ("Nenhum produto com estoque…") pedia a cor apagada por classe e saía na cor comum, pela causa do lucro. Conserto: o lucro pede a cor em `style`, e a linha de alerta troca o `--text` dela — conferido contra o CSS real da tela antes de subir.
- **Status: 🧪** — passou (2026-10-23). Pest na lane Estoque · MySQL, run 39945557255 (main 53e7995, 2026-10-23): 497 testes, 0 falhas, 0 pulados · Vitest local em 2026-10-23 (main 53e7995): os 133 testes dos arquivos da lista passaram (estes arquivos não rodam em lane).

## UC-PIDX-19 · O preço que a venda cobra vem primeiro, e o imposto aparece junto dele · `could`
- **Persona:** o vendedor de balcão procura o produto e informa o preço ao cliente; muitas vendas saem sem nota. O administrador confere se o preço já inclui o imposto.
- **Aceite:** Dado um produto com imposto e a permissão de ver o preço de venda · Quando abro o painel · Então a aba Dados começa com **"Preço de venda"**, o preço com imposto (`sell_price_inc_tax`, o mesmo número da lista e o que a venda cobra, com nota ou sem; faixa "menor – maior" quando há variações), seguido de **"Preço sem imposto"**, **"Imposto aplicável"** com o tipo ("ICMS 18% · exclusivo") e a frase "O preço de venda já inclui o imposto aplicável. É o valor que a venda cobra, com nota ou sem." · Dado um produto sem imposto · Então só "Preço de venda" · Sem a permissão de ver o preço, as linhas de preço somem e o imposto continua (não é segredo, sai impresso na nota) · A aba Fiscal não repete o imposto · As tabelas de Variações e Composição separam "sem imposto" e "com imposto" só quando os dois valores diferem; senão, uma coluna ("Compra", "Venda", "Total").
- **Teste:** [`produto-painel-detalhe.test.tsx`](../../../../tests/js/produto-painel-detalhe.test.tsx) — `UC-PIDX-19`.
- **Contrato:** protótipo `produto-blade.jsx` depois do #9925 (decisão [F] de 2026-10-26) — `DetalheDrawer`, aba `dados`: `dt("Preço de venda", …)`, `comImposto && dt("Preço sem imposto", …)`, `comImposto && dt("Imposto aplicável", …)` e a frase; aba `variacoes` com uma coluna sem imposto. Os dados são os que o painel já recebia (`variacoes[].venda_inc`/`venda_exc`, `dados.imposto`, `dados.tipo_imposto`); nada novo vem do servidor. A coluna única decide pelos valores que vieram: sem a permissão, a chave não vem e a coluna já some.
- **Caso anterior (2026-10-13 a 2026-10-26):** "A aba Fiscal do painel mostra o tipo de imposto na venda" — "Imposto aplicável" e "Tipo de imposto na venda" fechavam a aba Fiscal e se repetiam na aba Dados, sempre, com "Nenhum" quando não havia imposto.
- **Os outros extras do protótipo nesta parte do painel NÃO entraram, e por quê (recomendação do agente em 2026-10-13, apresentada ao [F] com a medição do que existe em produção; ele autorizou seguir com os itens sem contestar a lista — não é uma decisão dele item a item, e qualquer um pode ser reaberto):**
  - **"Origem da mercadoria"** — o campo não existe no cadastro nem no banco (`buildProdutoDetalhe` já registra: "origem da mercadoria não existe no banco e não vem").
  - **"Abrir no módulo Fiscal"** — não há tela fiscal por produto para onde levar.
  - **Texto de ajuda** — o do protótipo afirma, para todo produto sem NCM, "Serviço: sai em NFS-e por ISS"; com dado real, produto sem NCM pode ser só cadastro incompleto. Fica de fora até existir uma frase conferida.
  - **"Transferir" por local (aba Estoque)** — entra com a requisição de transferência.
  - **"Gerar OP" (rodapé de composição)** — a Fabricação produz por receita de uma variação; composição não é receita, e não há tela de destino. Em 2026-10-26 o botão saiu também do protótipo, por decisão [F] ("ordem de produção se inicia na produção"), e deixou de ser pendência.
- **Status: 🧪** — Vitest local em 2026-10-26 (este PR): os 9 arquivos de teste da lista e do painel (112 testes) passaram (estes arquivos não rodam em lane). Mutações: mostrar o sem imposto como "Preço de venda" derruba 2 casos; manter sempre as duas colunas derruba 2.

## UC-PIDX-20 · Painel: composição com total, preço e preços dos itens; imagens das variações · `should` `[V0]`
- **Persona:** Larissa abre um produto composto (um kit) e quer ver, sem sair da lista, quanto custa montá-lo, por quanto ele é vendido e o preço de cada item — como via na janela "Ver" da Blade; e as fotos das variações.
- **Aceite:** Dado o painel de um produto composto · Então a aba Composição mostra a tabela do protótipo (produto, SKU, quantidade, compra, total) com a linha **"Total líquido"**, a soma dos totais dos itens feita no servidor; logo abaixo, o **preço de venda padrão da composição**: primeiro o com imposto (o que a venda cobra), depois, entre parênteses, o sem imposto quando é diferente, e o % de lucro; depois **"Preços dos itens"**, com as colunas de preço que a Blade mostrava por item (compra com imposto, % de lucro, venda sem e com imposto; quando os itens não têm imposto, uma coluna de compra e uma de venda, como no UC-PIDX-19); e as **imagens dos itens**. A aba Variações de qualquer produto mostra as **imagens das variações**. Sem permissão de ver custo não há total nem colunas de compra; sem permissão de ver venda não há preço da composição nem colunas de venda — a chave não vem, nunca zero. Só imagens da própria empresa.
- **Teste:** [`ProdutoDetalheTest`](../../../../tests/Feature/Produto/ProdutoDetalheTest.php) — `UC-PIDX-20` (roda na lane Estoque · MySQL) e [`produto-painel-detalhe.test.tsx`](../../../../tests/js/produto-painel-detalhe.test.tsx) — `UC-PIDX-20` (não roda em lane).
- **Prova do total por dois caminhos** (REGRA MESTRE): itens com custo 10 × 2 e 7,50 × 3 — conta à mão (42,50) **e** a soma das linhas de total que a própria janela "Ver" da Blade imprime (`combo_product_details`), comparadas com o total do painel. O preço da composição é comparado com o que a Blade imprime abaixo da tabela.
- **Contrato:**
  - **Blade** — `product/partials/combo_product_details.blade.php`: por item, compra sem e com imposto (`view_purchase_price`), % de lucro (as duas permissões), venda sem e com imposto (`access_default_selling_price`), quantidade, total pelo custo e imagens; abaixo, o "Preço de venda padrão" da composição. `single_/variable_product_details`: imagens de cada variação (`variation_images`).
  - **Protótipo** — `DetalheDrawer`, aba `composicao`: `tfoot` "Total líquido" e a linha "Preço de venda padrão da composição: … · % de lucro …".
  - **Contas** — nenhuma nova: o total de cada item é a conta da Blade (custo × quantidade × multiplicador da unidade); o total líquido é a soma deles.
- **Diferenças deliberadas:**
  - as colunas de preço da Blade ficam numa tabela à parte, "Preços dos itens", e as imagens numa seção abaixo, em vez de tudo numa tabela só. Medido em produção em 2026-10-14: com todas as colunas, a tabela teria 973 px num painel de 779 px; é o mesmo arranjo que a aba Variações já usa com "Preços por grupo";
  - o preço da composição sai com imposto, e o sem imposto só quando difere: a Blade mostrava o com imposto, o protótipo de 2026-10-14 o sem imposto, e o de 2026-10-26 os dois, nessa ordem;
  - a Blade mostrava o preço da composição a qualquer um; aqui ele segue `access_default_selling_price`, como os outros preços do painel.
- **Status: 🧪** — passou (2026-10-23). Pest na lane Estoque · MySQL, run 39945557255 (main 53e7995, 2026-10-23): 497 testes, 0 falhas, 0 pulados · Vitest local em 2026-10-23 (main 53e7995): os 133 testes dos arquivos da lista passaram (estes arquivos não rodam em lane).

## UC-PIDX-21 · Produto que não controla estoque mostra o aviso na aba Estoque · `could`
- **Persona:** Larissa abre um serviço (ou qualquer produto sem controle de estoque) e procura o saldo; em vez de a aba sumir, quer saber que esse produto não tem estoque.
- **Aceite:** Dado o painel de um produto com "Gerenciar estoque" desligado · Então a aba Estoque aparece e mostra "Este produto não gerencia estoque.", sem tabela, indicadores ou botão de histórico. Pedida a aba Estoque, o painel abre nela. Produto que controla estoque segue como antes: sem a permissão de ver o relatório de estoque, a aba não aparece.
- **Teste:** [`produto-painel-detalhe.test.tsx`](../../../../tests/js/produto-painel-detalhe.test.tsx) — `UC-PIDX-21`.
- **Contrato:** protótipo — aba `estoque` do `DetalheDrawer`: `{p.stockOn ? … : <p className="pb-help">Este produto não gerencia estoque (…)</p>}`. O dado é o `enable_stock` que a aba Dados já mostra ("Gerenciar estoque"); nada novo vem do servidor, que já não manda saldo desse produto.
- **Diferença deliberada:** o protótipo diz "Este produto não gerencia estoque (**enable_stock = 0**) — serviços e composições entram assim." Fica só a primeira frase: o nome da coluna não diz nada a quem usa a tela, e produto composto também pode ter o controle de estoque ligado no cadastro (o formulário não o desliga), então a segunda frase não vale para todo caso.
- **Status: 🧪** — passou (2026-10-23). Vitest local em 2026-10-23 (main 53e7995): os 133 testes dos arquivos da lista passaram (estes arquivos não rodam em lane).

## UC-PIDX-22 · Produto sem foto mostra "Sem imagem" no painel e "IMG" na lista · `could`
- **Persona:** Larissa olha a lista e o painel de um produto que nunca teve foto cadastrada; quer ver que ele está sem foto, não um quadro branco que parece imagem quebrada.
- **Aceite:** Dado um produto sem foto (`products.image` vazio) · Então o servidor manda `imagem = null` na linha da lista e no painel; a coluna Imagem mostra o marcador "IMG" e o painel a caixa escura "Sem imagem" (os dois já existiam na tela para `null`). Produto com foto segue com a mesma URL da Blade (`/uploads/img/…`).
- **Teste:** [`ProdutoDetalheTest`](../../../../tests/Feature/Produto/ProdutoDetalheTest.php) — `UC-PIDX-22` (painel) e [`ProdutoListaConsultaTest`](../../../../tests/Feature/Produto/ProdutoListaConsultaTest.php) — `UC-PIDX-22` (lista), os dois na lane Estoque · MySQL.
- **Contrato:** protótipo `produto-blade.jsx` — `.pb-thumb` "IMG" na coluna Imagem e `.pb-img` no `DetalheDrawer`. O acessor `App\Product::image_url` devolve `/img/default.png` quando não há foto; a lista Blade e as outras telas seguem usando o acessor como está — só esta tela troca por `null`.
- **Medido em produção (biz 1, tema escuro, 2026-10-14):** antes, 73 das 74 miniaturas da 1ª página e o painel do produto 1817 eram `<img src=".../img/default.png">` (sem fundo); simulando a troca no navegador, a caixa do painel fica 168×168, fundo `oklch(0.23 0.006 240)`, borda `oklch(0.34 0.008 240)`, texto `oklch(0.67 0.005 90)` 11px, e a miniatura 30×30 com "IMG" 9px — as regras `.pb-img`/`.pb-thumb` do bundle, iguais às do protótipo.
- **Status: 🧪** — passou (2026-10-23). Pest na lane Estoque · MySQL, run 39945557255 (main 53e7995, 2026-10-23): 497 testes, 0 falhas, 0 pulados.

## UC-PIDX-23 · Produto que sincroniza com o WooCommerce tem a marca ao lado do nome · `could`
- **Persona:** quem vende também na loja WooCommerce olha a lista e quer saber, sem abrir o produto, quais deles vão para a loja.
- **Aceite:** Dado o módulo WooCommerce instalado · Quando um produto tem a sincronização ligada (`woocommerce_disable_sync = 0`) · Então a célula Produto mostra uma marca ao lado do nome, com o nome acessível "Sincroniza com o WooCommerce", também no modo compacto. Produto com a sincronização desligada não tem a marca; sem o módulo, nenhum tem (o servidor nem manda o campo `woocommerce_sync`).
- **Teste:** [`produto-lista-woocommerce.test.tsx`](../../../../tests/js/produto-lista-woocommerce.test.tsx) — `UC-PIDX-23` (não roda em lane). Mutação: trocar a condição por "campo presente" derruba o 1º caso.
- **Contrato:** lista Blade, `ProductController@index` (coluna `product`): `if ($is_woocommerce && ! $row->woocommerce_disable_sync)` põe `<i class="fab fa-wordpress">` sob o nome. O protótipo não tem a marca (não desenha o WooCommerce na linha); a tela segue a Blade aqui. Ícone: `Globe` (lucide), porque o pacote não tem a marca do WordPress.
- **Status: 🧪** — passou (2026-10-23). Vitest local em 2026-10-23 (main 53e7995): os 133 testes dos arquivos da lista passaram (estes arquivos não rodam em lane).

## UC-PIDX-24 · Campos personalizados como colunas opcionais da lista · `could`
- **Persona:** a empresa que usa campos personalizados no cadastro (cor, coleção, fornecedor de origem) quer ver esse dado na lista, sem abrir produto por produto.
- **Aceite:** Dado que a empresa deu rótulo a um campo personalizado de 1 a 7 (rótulos personalizados da empresa) · Então o menu "Colunas" oferece aquele campo com o rótulo da empresa, desligado por padrão · Quando a pessoa liga · Então a coluna mostra o valor de cada produto, e traço quando o produto não tem. Campo sem rótulo não vira coluna. O servidor manda os campos 1 a 7 de cada linha com os mesmos valores das colunas da lista Blade; do 8 em diante, só no painel.
- **Teste:** [`ProdutoListaConsultaTest`](../../../../tests/Feature/Produto/ProdutoListaConsultaTest.php) — `UC-PIDX-24` (roda na lane Estoque Pest), compara com o DataTable da Blade; [`produto-lista-campos-personalizados.test.tsx`](../../../../tests/js/produto-lista-campos-personalizados.test.tsx) — `UC-PIDX-24` (não roda em lane). Mutação: tirar a checagem do rótulo em `colunas.tsx` derruba o 4º caso do Vitest.
- **Contrato:** lista Blade, `product/index.blade.php` (`product_custom_field1..7`, `visible: $('#cf_N').text().length > 0`, rótulo de `custom_labels.product.custom_field_N`). **Divergência deliberada da Blade, pelo protótipo:** na Blade a coluna rotulada aparece de início; aqui começa desligada no menu "Colunas" (`PB_COLS_PADRAO.cf1 = false` em `produto-blade.jsx`). É forma, e na forma o protótipo decide (UI-0029).
- **Status: 🧪** — passou (2026-10-23). Pest na lane Estoque · MySQL, run 39945557255 (main 53e7995, 2026-10-23): 497 testes, 0 falhas, 0 pulados · Vitest local em 2026-10-23 (main 53e7995): os 133 testes dos arquivos da lista passaram (estes arquivos não rodam em lane).

---

## UC-PIDX-25 · Aba Fiscal: origem da mercadoria, texto de ajuda e "Abrir no módulo Fiscal" · `could`
- **Persona:** quem confere os dados fiscais de um produto antes de vender, sem abrir o cadastro.
- **Aceite:** Dada uma empresa sem o módulo **Fiscal** no pacote (`fiscal_module`) · Então o painel não tem aba Fiscal e o menu da linha não tem "Dados fiscais (NCM/CFOP)" (decisão [F] 2026-10-26: quem vende sem nota não precisa de NCM, CEST e CFOP; o teste é o pacote, não permissão) · Dada uma empresa com o módulo · Então a aba Fiscal começa dizendo se o produto está pronto para nota: **"Pronto para nota"** com NCM, ou **"Sem NCM"** e "A nota sai com o NCM padrão da empresa. Se for serviço, a nota é a NFS-e, que não usa NCM." · E mostra **Origem da mercadoria** depois dos CST, como último campo (o imposto fica na aba Dados, UC-PIDX-19), com o código e o nome da tabela A gravados no próprio produto (`products.origem_mercadoria`; sem escolha, `0 — Nacional`), os mesmos nomes do campo do cadastro · E, com NCM, um **texto de ajuda** que explica a ordem da regra (exceção do produto, regra do NCM, padrão da empresa); com CEST, avisa que a nota não troca o CFOP sozinha · E, quando a empresa tem o módulo **Fiscal** no pacote (`fiscal_module`) **e** a pessoa tem a permissão de acessar o módulo (`fiscal.access`) — as duas checagens do menu Fiscal, decisão [F] 2026-10-16 —, mais a permissão `nfe.tributacao.manage` que o simulador cobra, o botão **"Abrir no módulo Fiscal"**, que leva à Tributação com o produto já escolhido no simulador (UC-NFTR-29). Sem módulo ou sem permissão, o botão não existe.
- **Teste:** [`ProdutoDetalheTest`](../../../../tests/Feature/Produto/ProdutoDetalheTest.php) — `UC-PIDX-25` (origem do produto, com um 2º produto em 0 como discriminante; produto de outra empresa segue `null`; as três condições do link; e `contexto.emite_nota` com e sem o módulo no pacote, na lane Estoque · MySQL); [`produto-painel-detalhe.test.tsx`](../../../../tests/js/produto-painel-detalhe.test.tsx) e [`produto-menu-da-linha.test.tsx`](../../../../tests/js/produto-menu-da-linha.test.tsx) — `UC-PIDX-25` / `UC-PIDX-17` (não rodam em lane). Mutação em 2026-10-26: aba Fiscal sempre presente derruba os 2 casos de empresa que não emite nota.
- **Contrato:** protótipo `produto-blade.jsx`, aba `fiscal` do `DetalheDrawer` (NCM, CEST, CFOP, Origem, Imposto aplicável, Tipo de imposto, texto de ajuda e o botão). **Diferenças deliberadas, medidas no código:** ~~(1) o cadastro não tem origem por produto~~ — caiu em 2026-10-15: a origem passou a ser do produto (#9465) e a aba lê `products.origem_mercadoria`, a mesma coluna que as duas emissões usam; o aviso "a origem é a mesma em todas as notas" não aparece mais. (2) O texto do protótipo chama de serviço todo produto sem NCM e diz que, com CEST, a nota sai com CFOP de substituído; a emissão não faz nem uma coisa nem outra (usa o NCM padrão da empresa e não lê o CEST), então o texto foi corrigido. (3) O protótipo leva a um "módulo Fiscal" do produto; a tela que existe é a Tributação do NfeBrasil, com o simulador.
- **Status: 🧪** — aguarda veredito da lane Estoque · MySQL para os 2 casos Pest novos (`emite_nota`) · Vitest local em 2026-10-26 (este PR): os 9 arquivos de teste da lista e do painel (112 testes) passaram (estes arquivos não rodam em lane).

## UC-PIDX-26 · Em tela estreita as colunas encolhem até caber · `should`
- **Persona:** a ROTA LIVRE usa monitor 1366×768. Na lista de produtos, Tipo, Categoria, Marca, Imposto e SKU ficavam fora da tela, e ela estreitava as colunas à mão a cada visita.
- **Aceite:** Dada a lista com 12 colunas (as 11 de saída e a Imposto ligada; até 2026-10-26 ela nascia ligada, UC-PIDX-34) numa janela de 1366px com o menu lateral aberto (1.034px para a tabela, medido em produção em 2026-10-17) · Então todas as colunas aparecem, sem rolar para o lado · E cada coluna que pode encolher cede a mesma fração da sua folga (largura do protótipo − piso), sem passar do piso: preço 92px (cabe um preço de quatro dígitos com centavos, como "9.999,99", com o prefixo da moeda), estoque 88 (cabe "4010 UNID"), tipo 96 (cabe a pílula "Composição"), produto 112, e os de texto com o piso do rótulo do cabeçalho (categoria 80, locais 62, marca 64, imposto 60, SKU 58) · E a faixa de preço de produto variável ("‹mínimo› – ‹máximo›") quebra em duas linhas logo depois do traço, sem cortar valor · E texto cortado com "…" mostra o inteiro ao passar o mouse · E o rótulo do cabeçalho de coluna encolhida quebra em duas linhas em vez de invadir a vizinha, e o rótulo de palavra única ("CATEGORIA") cabe inteiro, porque nenhum piso fica abaixo dele · Quando a janela é larga o bastante (a soma das larguras do protótipo cabe — com o menu aberto, a partir de uns 2.080px de janela) · Então nada muda: valem as larguras do protótipo; em 1920px as colunas ficam um pouco abaixo delas (medido em 2026-10-18: Produto 269px em vez de 300), sem rolar para o lado · Quando a pessoa arrasta uma coluna · Então aquela fica com a largura escolhida e as outras se ajustam ao espaço que sobra · Quando nem os pisos cabem (muitas colunas ligadas) · Então a tabela fica nos pisos e a sobra rola para o lado, com a barra à vista (UC-PIDX-27).
- **Teste:** [`produto-lista-encaixe.test.tsx`](../../../../tests/js/produto-lista-encaixe.test.tsx) — `UC-PIDX-26` (a regra com os números de 1366px, o piso, a coluna arrastada, o `<colgroup>` da tabela de verdade, o rótulo que quebra, a faixa de preço que quebra no traço e o texto inteiro no `title`). **Smoke em produção depois do deploy do #9535 (2026-10-18, 1366×648, menu aberto, empresa 1):** nenhuma coluna fora da tela; tabela de 1.019px numa área de 1.019px; grade com 391px de altura; nenhum preço, estoque ou tipo cortado e 11 faixas de preço quebrando no traço; arrastar Produto para 183px sobreviveu ao F5 (com SKU passando para a direita, alcançável pela barra lateral da grade) e "Voltar às larguras de saída" apagou a chave e devolveu o encaixe; nenhuma falha de rede na carga. **Achado no smoke e corrigido depois:** o rótulo "CATEGORIA" pedia 79px, ficou com 67 e vazava 12px sobre MARCA — os pisos passaram a respeitar o rótulo. **Prévia em produção (2026-10-17, 1366×648, larguras aplicadas no navegador, sem deploy):** nenhuma coluna fora da tela, tabela de 1.019px numa área de 1.019px; nenhum preço cortado com a faixa quebrando; continuam com "…" (texto inteiro no `title`) nomes de produto longos, "Locais" e SKUs de 13 dígitos. Roda no Vitest do `Frontend / Vite build` (desde 2026-10-18). Mutação: desligar o encaixe (`encaixarColunas` devolvendo sempre `{}`) derruba 5 casos.
- **Contrato:** a reclamação (monitor 1366×768, "todos os campos não aparecem, precisa ficar redimensionando") e a regra do cliente piloto em [`memory/regras-time.md`](../../../../memory/regras-time.md) ("monitor 1280px — designs precisam caber"). **Medido em produção (2026-10-17, janela 1366×648, menu aberto):** espaço da tabela 1.034px; a tabela exigia 1.704px (a soma das larguras do protótipo + a caixa de seleção); inteiras na tela só até Preço de venda, Estoque atual cortada, Tipo, Categoria, Marca, Imposto e SKU fora.
- **Diferença deliberada do protótipo:** o protótipo mantém as larguras em qualquer tela e a tabela rola para o lado; aqui, em tela estreita, as colunas encolhem. Em tela larga as larguras são as dele. A regra e os pisos moram em `_components/lista/encaixe.ts`.
- **Status: 🧪** — passou (2026-10-23). Vitest no Frontend / Vite build, job 120384523098 (main 53e7995, 2026-10-23).

## UC-PIDX-27 · A grade tem altura própria e a barra de rolar para o lado fica à vista · `should`
- **Persona:** quem precisa ver uma coluna à direita e não acha a barra de rolar para o lado.
- **Aceite:** Dada a lista · Então a grade tem altura máxima de 72% da área que rola da página (nunca menos de 260px) · E rola por dentro, com o cabeçalho fixo e a barra de rolar para o lado no fim da grade, à vista · Lista curta não ganha espaço vazio (é altura **máxima**).
- **Teste:** [`produto-lista-encaixe.test.tsx`](../../../../tests/js/produto-lista-encaixe.test.tsx) — `UC-PIDX-27` (a área que rola fica limitada; sem a opção, a tabela cresce com as linhas, como antes). Roda no Vitest do `Frontend / Vite build` (desde 2026-10-18).
- **Contrato:** protótipo `produto-blade.jsx`, `ListaProdutos`: altura = `max(260, min(conteúdo, 72% do .pb-body))`, "a rolagem interna com header fixo compensa". Charter (UX): "a tabela rola dentro dela, cabeçalho fixo". **Medido em produção antes (2026-10-17, 1366×648, 75 produtos):** sem altura, a barra de rolar para o lado ficava a 4.097px do topo, numa janela de 648px.
- **Onde mora:** `scrollMaxHeight` é opção nova do `shared/DataTable` (só no `grid`), desligada por padrão — as outras telas não mudam.
- **Status: 🧪** — passou (2026-10-23). Vitest no Frontend / Vite build, job 120384523098 (main 53e7995, 2026-10-23).

## UC-PIDX-28 · Preço proposto pela compra recebida, aguardando aprovação · `should` `[T0]`
- **Persona:** o dono que deixou a regra de custo em "Propõe novo preço" e consulta o produto sem saber que há um preço esperando decisão.
- **Aceite:** Dado um produto que acompanha o custo e uma compra recebida que gravou proposta pendente (`compras_propostas_preco`, US-COM-026) · Então a coluna "Preço de venda" mostra o preço gravado e, abaixo, "proposto ‹preço› · aguardando" (com mais de uma proposta pendente, "‹N› preços propostos · aguardando") · E o painel, na aba Dados, lista cada proposta pendente com a variação, o preço atual, o proposto e a compra de origem (número e data) · E o botão "Abrir a compra" leva ao detalhe da compra, onde se aprova ou recusa (US-COM-028); sem `purchase.view` o botão não existe · E, se o preço mudou depois da proposta, o painel avisa que ela só pode ser recusada · Quando a proposta já foi aprovada ou recusada · Então ela não aparece · Quando a pessoa não pode ver o preço de venda (`access_default_selling_price`) · Então nem a linha nem o painel trazem a proposta (a chave não vem, como em UC-PIDX-03) · Proposta gravada com outra empresa não aparece (Tier 0).
- **Teste:** [`ProdutoPrecoPropostoERoteiroContratoTest`](../../../../tests/Feature/Produto/ProdutoPrecoPropostoERoteiroContratoTest.php) — `UC-PIDX-28` (valores comparados com a linha gravada, propostas decididas, preço mudado, gate de preço, link da compra e outra empresa; lane Estoque · MySQL); [`produto-preco-proposto-roteiro.test.tsx`](../../../../tests/js/produto-preco-proposto-roteiro.test.tsx) — `UC-PIDX-28` (o que a célula e o painel desenham, e que não há Aprovar nem Recusar; não roda em lane). Mutação: tirar o aviso da célula derruba 2 casos do Vitest.
- **Contrato:** UC-FLX-17 (`prototipo-ui/cowork/Wagner/fluxos-data.jsx`) e Compras SPEC US-COM-026/028. **Diferença deliberada do protótipo:** o protótipo tem Aprovar e Recusar no próprio painel; aqui a consulta só avisa e leva à compra, porque o charter desta tela (Automation Anti-hooks, decisão [W]) proíbe a consulta de alterar preço e de criar escrita nova. Decidir pela consulta exige mudar o charter.
- **Status: 🧪** — passou no job 120005347082 (lane Estoque · MySQL, commit 730f065c, 2026-10-22): 7 testes, 0 pulados, 64 verificações. O Vitest não roda em lane; passou local (8 casos).

## UC-PIDX-29 · Aba Roteiro: as etapas do roteiro do produto, com o posto e o setor · `should` `[T0]`
- **Persona:** quem vende ou planeja e quer saber por quais setores o produto passa antes de virar OS.
- **Aceite:** Dado um produto ligado a um roteiro (`pcp_produto_roteiro`, o mesmo vínculo que a OS usa ao nascer, US-PCP-026) · Então o painel tem a aba "Roteiro", com as etapas na ordem do roteiro (bloco, trilha, ordem), cada uma com o posto e o setor do posto, e as horas previstas · E etapa opcional e etapa em paralelo vêm marcadas · E o responsável não aparece: ele é escolhido na OS, entre os operadores do posto (US-PCP-030) · Quando o produto não tem roteiro, ou a empresa não tem o módulo Produção · Então a aba não existe · Vínculo apontando roteiro de outra empresa conta como produto sem roteiro (Tier 0, como na OS, UC-PCROT-04).
- **Teste:** [`ProdutoPrecoPropostoERoteiroContratoTest`](../../../../tests/Feature/Produto/ProdutoPrecoPropostoERoteiroContratoTest.php) — `UC-PIDX-29` (ordem com etapas gravadas fora de ordem, posto e setor, etapa sem posto, produto sem roteiro e roteiro de outra empresa; lane Estoque · MySQL); [`produto-preco-proposto-roteiro.test.tsx`](../../../../tests/js/produto-preco-proposto-roteiro.test.tsx) — `UC-PIDX-29` (a aba só com roteiro e a tabela com posto e setor; não roda em lane). Mutação: trocar a coluna Setor pelo posto derruba o caso da tabela.
- **Contrato:** UC-FLX-07 e UC-FLX-08 (`prototipo-ui/cowork/Wagner/fluxos-data.jsx`) e Produção SPEC US-PCP-026/030. O protótipo junta receita e roteiro numa aba "Receita e roteiro"; a receita na consulta é outro caso (CV6, backlog), por isso aqui a aba é só "Roteiro".
- **Status: 🧪** — passou no job 120005347082 (lane Estoque · MySQL, commit 730f065c, 2026-10-22): 7 testes, 0 pulados, 64 verificações. O Vitest não roda em lane; passou local (8 casos).

## UC-PIDX-30 · Requisição de compra aberta na linha e na aba Estoque · `should` `[T0]`
- **Persona:** quem compra e olha a consulta para decidir se precisa pedir mais material.
- **Aceite:** Dado um produto com requisição de compra aberta (`transactions.type = purchase_requisition`, status `ordered` ou `partial`) — a que o estoque mínimo cria sozinho (US-COM-027) ou uma feita à mão · Então a célula "Estoque atual" mostra, abaixo do saldo, "Requisição ‹número› aberta em ‹dia/mês› · ‹fornecedor›", da requisição aberta mais recente · E a aba Estoque do painel repete a requisição com o botão "Abrir a requisição" · Quando a requisição já foi atendida (`completed`) · Então não aparece · Quem tem `purchase_requisition.view_all` vê todas; quem tem só `view_own`, as que criou; sem nenhuma das duas, a chave não vem · Requisição de outra empresa não aparece (Tier 0).
- **Teste:** [`ProdutoRequisicaoAbertaContratoTest`](../../../../tests/Feature/Produto/ProdutoRequisicaoAbertaContratoTest.php) e [`produto-requisicao-aberta.test.tsx`](../../../../tests/js/produto-requisicao-aberta.test.tsx) (o Vitest não roda em lane) — `UC-PIDX-30` (a mais recente vence, `completed` fica fora, as duas permissões, outra empresa). Mutação: tirar a linha da célula derruba o caso da célula no Vitest.
- **Contrato:** UC-FLX-19 (`prototipo-ui/cowork/Wagner/fluxos-data.jsx`) e Compras SPEC US-COM-027. **Diferença do protótipo:** o protótipo só mostra a requisição quando o produto está abaixo do alerta; aqui ela aparece sempre que houver uma aberta, porque a requisição feita à mão também conta e o alerta pode ter mudado depois.
- **Status: 🧪** — escrito, sem veredito nesta divisão. Junto com os outros dois casos, no #9785, passou no job 120394269474 (lane Estoque · MySQL, commit 3587864, 2026-10-23). O Vitest não roda em lane.

## UC-PIDX-31 · Natureza fiscal: selo, filtro e aba Fiscal · `should` `[T0]`
- **Persona:** quem vende e quem fatura, para saber antes da nota se o produto é mercadoria ou serviço, e o contador, para achar os que ainda precisa decidir.
- **Aceite:** Dada a natureza fiscal decidida pelo contador (`nfe_naturezas_produto`, US-NFE-068; produto sem decisão é mercadoria) · Então a linha mostra "Serviço" ou, quando pendente, "Fiscal a decidir"; mercadoria não ganha selo · E o filtro "Natureza fiscal" (Mercadoria, Serviço, A decidir pelo contador) é resolvido no servidor, com chip (o CSV exportado usa a mesma consulta e sai com o mesmo filtro, sem teste próprio) · E a aba Fiscal mostra a natureza, com o item da LC 116 quando é serviço · Quando pendente · Então o painel avisa que a NF-e comum sai como mercadoria e que a emissão separada (NF-e + NFS-e) fica bloqueada até a decisão · Sem o módulo Fiscal, nem o filtro nem a natureza aparecem · Decisão gravada com outra empresa não vale para o produto (Tier 0).
- **Teste:** [`ProdutoNaturezaFiscalConsultaContratoTest`](../../../../tests/Feature/Produto/ProdutoNaturezaFiscalConsultaContratoTest.php) e [`produto-natureza-fiscal-consulta.test.tsx`](../../../../tests/js/produto-natureza-fiscal-consulta.test.tsx) (o Vitest não roda em lane) — `UC-PIDX-31` (as três naturezas, o filtro nas três opções com controle sem filtro, o item da LC 116 e outra empresa). Mutação: tirar o selo de pendente e a linha da aba Fiscal derruba 2 casos do Vitest.
- **Contrato:** UC-FLX-31 e NfeBrasil `SeparacaoNotasDaVenda` + `NfeService` (o pendente bloqueia a emissão separada; a NF-e comum segue como mercadoria). **Correção de 2026-10-23:** o caso do backlog dizia que o pendente "trava a NF-e da venda"; o código bloqueia só a emissão separada. A aba "Relatório de estoque" não usa este filtro, como já não usa a busca.
- **Status: 🧪** — escrito, sem veredito nesta divisão. Junto com os outros dois casos, no #9785, passou no job 120394269474 (lane Estoque · MySQL, commit 3587864, 2026-10-23). O Vitest não roda em lane.

## UC-PIDX-32 · Método de custo do insumo na aba Variações · `should`
- **Persona:** quem forma preço e quer saber de onde vem o custo que está vendo.
- **Aceite:** Dada a empresa que gravou a regra "Atualizar o custo dos insumos" ligada e disparando no Recebido (as mesmas condições em que o recebimento a aplica, `AplicarCustoDoRecebimento`) · Então a aba Variações ganha a coluna "Método do custo", com "custo médio" ou "última compra" e a data da última compra recebida · Quando a regra é "definido em cada insumo" · Então vale o método do insumo, e insumo sem método mostra "sem método definido" · Sem a regra gravada, com a regra em Conferido, desligada, ou para quem não vê custo · Então a coluna não existe, porque o custo ali é o último preço digitado.
- **Teste:** [`ProdutoMetodoDeCustoContratoTest`](../../../../tests/Feature/Produto/ProdutoMetodoDeCustoContratoTest.php) e [`produto-metodo-custo.test.tsx`](../../../../tests/js/produto-metodo-custo.test.tsx) (o Vitest não roda em lane) — `UC-PIDX-32` (médio e último com a data da última compra; sem regra, Conferido, desligada e sem permissão). Mutação: tirar a coluna derruba o caso do Vitest.
- **Contrato:** UC-FLX-38 e Compras SPEC US-COM-024/026. **Diferença do protótipo:** o protótipo mostra o método na aba Dados, abaixo do custo; aqui fica na aba Variações, onde o painel mostra o custo de cada variação.
- **Status: 🧪** — escrito, sem veredito nesta divisão. Junto com os outros dois casos, no #9785, passou no job 120394269474 (lane Estoque · MySQL, commit 3587864, 2026-10-23). O Vitest não roda em lane.

## UC-PIDX-33 · Aba Estoque do painel: quantidade vendida por local · `should`
- **Persona:** a ROTA LIVRE abre um produto pelo painel e quer saber, sem ir ao Relatório de estoque, quanto daquele item já saiu em cada loja.
- **Aceite:** Dado um produto que controla estoque, com saldo em dois locais, uma venda finalizada de 5 un no local A com 1 un devolvida e um rascunho de 2 un no mesmo local · Quando abro a aba Estoque do painel · Então a tabela de locais tem a coluna "Vendido", logo antes de "Situação" · E o local A mostra "4 Un" e o B "0 Un" · E o número de cada local é o mesmo "Total vendido" do Relatório de estoque para aquele produto e local (vendas finalizadas, desde sempre, menos as devoluções; rascunho e orçamento ficam fora).
- **Teste:** [`ProdutoDetalheTest`](../../../../tests/Feature/Produto/ProdutoDetalheTest.php) — `UC-PIDX-33` (o vendido de cada linha do painel contra a conta à mão e contra a soma do JSON do DataTable do relatório) e [`produto-painel-detalhe.test.tsx`](../../../../tests/js/produto-painel-detalhe.test.tsx) — `UC-PIDX-33` (a coluna e a ordem; cada local com o número que o servidor mandou). Mutação conferida em 2026-10-23: mostrar o saldo no lugar do vendido derruba o caso de tela.
- **Contrato:**
  - **Protótipo** — artefato "Consulta de Produto" (https://claude.ai/artifact/2Rwvt22eRnijtFFZhkpt67, versão de 22/10), aba Estoque do painel: `<th class="d">Vendido</th>` entre o saldo e a situação, valor por local com a unidade.
  - **Contas** — nenhuma nova: o número é o `vendido` que `ProductUtil::linhaDoRelatorioEstoque` já punha em cada linha (`total_sold` de `getProductStockDetails`), o mesmo da coluna "Total vendido" do Relatório de estoque. Só a tela passou a mostrá-lo.
- **Diferenças deliberadas do protótipo:** a coluna "Alerta" continua (o protótipo não a tem); "Separado" e "Livre" não entram, porque dependem do interruptor "Reservar material na venda" (backlog D5).
- **Status: ⬜** — nasce neste PR; verde esperado. A parte do servidor roda na lane Estoque · MySQL; a parte Vitest não roda em lane nenhuma (pôr o arquivo no Vitest do `Frontend / Vite build` fica para PR próprio, porque `.github/` pede revisão do [W]).

## UC-PIDX-34 · Coluna e filtro Imposto só com alíquota cadastrada; a coluna nasce desligada · `could`
- **Persona:** o vendedor procura o produto e informa o preço; o administrador confere o imposto quando precisa. Muitas empresas vendem sem nota e não cadastram alíquota nenhuma.
- **Aceite:** Dada uma empresa sem alíquota de imposto cadastrada (`opcoes.impostos` vazio) · Então a lista não tem o filtro Imposto, o menu "Colunas" não tem a opção Imposto, a coluna não aparece nem que o navegador a tenha guardado ligada, e o CSV de "Exportar filtrados" não tem a coluna · Dada uma empresa com alíquota · Então o filtro existe, a opção existe no menu e a coluna nasce desligada; o CSV tem a coluna, com o imposto de cada produto · Para quem já usava a lista, o "ligada" guardado no navegador antes desta mudança era o padrão antigo e é desligado uma vez; quem religar a coluna depois fica com ela.
- **Teste:** [`ProdutoListaExportarTest`](../../../../tests/Feature/Produto/ProdutoListaExportarTest.php) — `UC-PIDX-34` (CSV com e sem alíquota, na lane Estoque · MySQL) e [`produto-lista-encaixe.test.tsx`](../../../../tests/js/produto-lista-encaixe.test.tsx) — `UC-PIDX-34` (a coluna no menu, o padrão desligado, a 1ª leitura do navegador e o filtro; roda no Vitest do `Frontend / Vite build`). Mutações em 2026-10-26: a coluna nascendo ligada, a opção sem olhar a alíquota, o navegador guardando o padrão antigo e o filtro sempre visível derrubam um caso cada.
- **Contrato:** protótipo `produto-blade.jsx` depois do #9925 (decisão [F] de 2026-10-26): `PB_COLS_PADRAO.imposto = false`, `colunaExiste("imposto", empresa)` só com `empresa.temAliquota`, e o filtro e o CSV seguindo a mesma regra. "Tem alíquota" é a lista de impostos que a tela já recebia (`TaxRate::forBusinessDropdown($business_id, false)`, a mesma do filtro); o CSV usa a mesma lista no servidor.
- **Não coberto por teste:** a ligação dentro de `Produto/Index.tsx` (a alíquota decidindo as opções e forçando a coluna desligada sem alíquota) — nenhum teste renderiza a página inteira. Conferir em produção depois do deploy.
- **Fora daqui:** o "Baixar Excel" do cabeçalho (`app/Exports/ProductsExport.php`) é o arquivo de reimportação da Blade e segue com a coluna Imposto; mexer nele é decisão à parte.
- **Status: ⬜** — nasce neste PR; verde esperado. Vitest local em 2026-10-26 (este PR): os 17 arquivos de teste de produto (211 testes) passaram; o Pest aguarda a lane.

## Achados de governança (o trio prometia o que não tinha)

> Não são bugs de comportamento — são **contratos frouxos** que deixariam a tela ser promovida
> com a rede furada. Cada um verificado por leitura direta de arquivo, não por inferência.
> Os corrigíveis-por-fato foram reconciliados no charter neste mesmo PR (Fase 2.6 do `sdd-from-source`);
> os que exigem decisão de produto ficam abertos pro [W] (§Divergências).

| # | Achado | Evidência |
|---|---|---|
| G-a | O `Index.charter.md` §Pest GUARD promete **10** testes em `tests/Feature/Produto/IndexCharterTest.php`. **O arquivo não existe.** O que existe (`Wave2Index{Inertia,Baseline}Test.php`) é string-match no fonte (`expect($source)->toContain(...)`) — passaria com o isolamento quebrado, com a lista vazia e com o preço vazando. Os 6 UCs deste arquivo cobrem 5 das 10 promessas com comportamento real. | `Index.charter.md:121-136` × `ls tests/Feature/Produto/` |
| G-b | ⚠️ **CORRIGIDO 2026-07-26 — a redação anterior era falsa (LC-08).** Dizia *"os 2 testes de Index não rodam em lane nenhuma… verdes decorativos"*, concluindo isso de um `grep` em `.github/workflows/`. Isso é análise **file-scoped** apresentada como **system-scoped**: `phpunit.xml` inclui `./tests/Feature` **recursivamente** e `scripts/tests/shards-plan.mjs` enumera `tests/Feature/Produto` como shard → **os `Wave2Index*` RODAM no fullsuite nightly do CT 100**. O que é verdade é mais estreito: não estão na allowlist da lane de PR, logo **não rodam no PR** — mas rodam no nightly. As 3 perguntas são distintas: *roda?* (`phpunit.xml` + `shards-plan`) · *roda no PR?* (allowlist) · *bloqueia merge?* (`required-checks-baseline.json`). | `phpunit.xml` (testsuite Feature) + `scripts/tests/shards-plan.mjs` |
| G-c | ⚠️ **CORRIGIDO 2026-07-26 — era vermelho REAL, não "latente".** `Wave2IndexInertiaTest.php:106 (verificado@d4afe95),110` afirmava `file_exists('memory/requisitos/Inventory/…')`; os arquivos moram em `Produto/_telas/`. Como o arquivo **roda** no fullsuite (ver G-b), isso vinha reprovando no nightly e contribuindo pro floor. **Os 7 asserts quebrados do módulo foram reapontados** (`Wave2{BulkEdit,Create,Edit,Index×2,SellingPrices,StockHistory}InertiaTest`) — o do `Show` já havia sido corrigido antes. | `ls memory/requisitos/Inventory/` = `BRIEFING.md`, `SPEC.md` · `grep -rn "requisitos/Inventory" tests/Feature/Produto/` → 0 |
| G-d | Charter §Automation Hooks declarava **2 endpoints inexistentes**: `GET /produto` (a rota é `/products`) e `GET /produto/{id}/sheet-data` — varredura contada: `sheet-data` existe **só** para `/sells/{id}` (`routes/web.php:704 (verificado@e2c8397)`), **0** ocorrências para produto. Corrigido no charter (fato). | `grep -rn "sheet-data" routes/ app/` |
| G-e | Charter §Goals declarava o gate `product.view_own`. Varredura contada em `app/`, `Modules/`, `routes/`, `resources/` (excl. charters): **0** ocorrências — a permissão **não existe** no projeto. O gate real é `product.view` **ou** `product.create` (`:66-68`). Corrigido no charter (fato). | `grep -rn "product.view_own"` |
| G-f | Charter §Refs apontava `ui_kits/cowork-2026-05-09/prod-page.jsx` — caminho **inexistente**. O blueprint vivo é `prototipo-ui/cowork/Wagner/produtos-page.jsx`, que o próprio frontmatter (`bundle_source`) já declara. Corrigido no charter (fato). | `ls ui_kits/…` → No such file |
| G-g | ✅ **Resolvido 2026-10-15** (ver D-4). O charter está `status: live` (promovido em 2026-07-12 por `charter-promote-signal.mjs`, sinal `route-hits:16`) enquanto o SDD §1.1 e o SPEC afirmam *"as 8 telas existem, nenhuma é `live`"*. **Não reconciliei**: o critério de promoção é decisão de processo, não fato de arquivo. Ver §Divergências. | `Index.charter.md:8,166` × `SDD §1.1` × `SPEC.md:18` |

---

## Divergências — resolvidas pela lista nova (2026-10-15)

> ✅ **Resolvidas em 2026-10-15, conferidas no código.** As quatro descrevem a lista de **cartões**,
> que saiu em 2026-10-10 (charter v3, #9118/#9119/#9124). Não pedem mais decisão do [W]:
>
> - **D-1:** a popularidade saiu da tela e do controller (0 ocorrências de `popular` em `Index.tsx`
>   e no `ProductController`). Os indicadores são os 4 do protótipo (UC-PIDX-07).
> - **D-2:** clicar na linha abre o painel de detalhe, só com `product.view`
>   (`Index.tsx`: `onRowClick={pode('view') ? …}`). O gate invertido sumiu com o link.
> - **D-3:** o menu "Ações" da linha (`_components/lista/AcoesProduto.tsx`) tem as ações da lista
>   Blade (ver, editar, estoque inicial, histórico, duplicar, código de barras, preços por grupo,
>   anexo, ativar/desativar, excluir) e mais "Comprar" e "Dados fiscais". As 5 sem Non-Goal entraram.
> - **D-4:** o `ProductController@index` serve a tela React por padrão; o Blade só com `?classico=1`.
>   O tráfego de `/products` agora é da tela React. O SPEC já diz que o `Index` é a única tela `live`;
>   a frase do SDD §1.1 ("zero live") foi corrigida com data neste PR. A dúvida sobre o promotor
>   automático ler tráfego do Blade nas **outras** telas segue no backlog abaixo — é mecanismo, não esta tela.
>
> O texto original segue abaixo, sem mudança, como registro do que se viu em 2026-07-26.
>
> Fase 2.6 do `sdd-from-source`: **fato** eu corrijo; **intenção** e **promessa não cumprida** eu
> registro nos dois lados e levo pro dono. Nenhum item abaixo foi alterado no charter.

| # | Divergência | Os dois lados |
|---|---|---|
| D-1 | **Popularidade é fachada.** O charter promete "barra de popularidade" no card e o KPI "Populares (popularity ≥ 70)". O controller devolve `'popularity' => 0` fixo (`:467`, comentário *"deferred a Wave 3"*) — **toda** barra fica em 0% e o ramo emerald (`≥70`, `Index.tsx:411 (verificado@d4afe95)`) é código morto. Pior: o KPI "Populares" usa outra régua (**≥30 vendas em 30d**, `:401-409`) e o subtítulo da tela diz "≥30 vendas/mês" — duas definições de "popular" na mesma tela. | Podar a promessa do charter **ou** implementar. Decisão de produto. |
| D-2 | **Card abre drawer ou navega?** Charter §Goals: *"Click card abre drawer (mesma DetailSheet do `/products/unificado`)"*. Código: `<Link href={/products/{id}}>` (`Index.tsx:423 (verificado@d4afe95)`) — navega pra ficha. Além disso o link é gated por `permissions.update`, enquanto a ficha exige `product.view`: **quem só pode ver não consegue clicar**, e quem pode editar clica pra uma tela de leitura. | Drawer (charter) × navegação (código) é desenho; o gate invertido é bug derivado da escolha. |
| D-3 | **O menu de Ações da lista.** O Blade oferece **10 ações por linha** (`:202-258`: etiqueta/código de barras · ver · editar · excluir · reativar · estoque inicial · histórico de estoque · tabela de preço · duplicar · baixar anexo). O React oferece **1** interação (o card inteiro como link, contado: 2 `<Link>` no arquivo, um deles o botão "Novo produto"). O charter declara Non-Goal para **parte** disso (`❌ CRUD inline`, `❌ Bulk actions`, `❌ Stock management`, `❌ edição de preço inline`) — mas **não** para etiqueta/código de barras (`CU-PROD-09`), histórico de estoque (`CU-PROD-11`), tabela de preço (`CU-PROD-03`), duplicar (`CU-PROD-07`) nem reativar (`AR-PROD-003/022`). | É **exatamente** a classe de regressão que motivou este agent (o menu de Ações da lista de vendas que sumiu no rewrite #1032). Não virou UC porque decidir *quais* ações a lista lite deve ter é escopo de produto — mas as 5 sem Non-Goal precisam de veredito: entram ou viram Non-Goal explícito. |
| D-4 | **`status: live` × "nenhuma tela é live".** Ver G-g. O charter foi promovido por sinal automático de tráfego (`route-hits:16`) enquanto o SDD/SPEC tratam a tela como `draft` e a US-PROD-023 ainda pede "finalizar + promover". Ou o sinal está medindo o Blade (mesma rota!), ou o SDD está stale. | Suspeito do **primeiro**: `/products` serve os dois branches, então "route-hits" não distingue React de Blade. Se for isso, o promotor automático está promovendo telas React por tráfego do Blade — vale pras 8. |

---

## Paridade Blade→React (gaps do cutover — decisão [W]/[F], não bug afirmado)

> **Conferido em 2026-10-15** contra a lista nova (`_components/lista/colunas.tsx`, `FiltrosLista.tsx`,
> `tipos.ts`). A tabela abaixo descreve a lista de cartões e fica como registro. Na lista nova:
> saldo com unidade (coluna Estoque), imagem, locais, marca, imposto, tipo, os filtros, a seleção em
> massa com desativar/excluir, a aba "Relatório de estoque", "não para venda" (na 2ª linha da célula
> Produto) e a ordenação por coluna **existem**. **Ainda faltam:** os custom fields e o selo de
> WooCommerce sincronizado na linha (o filtro "WooCommerce habilitado" existe, o selo não).
> _Atualização 2026-10-15 [F+C]: o selo do WooCommerce entrou com o **UC-PIDX-23** (marca ao lado
> do nome)._
> _Correção 2026-10-15 [F+C]: os custom fields **não faltavam** — a lista nova tem as colunas 1 a 7
> desde 2026-10-10 (#9124), no menu "Colunas", só para os campos rotulados. Faltava o teste: entrou
> com o **UC-PIDX-24**. A nota acima fica como estava (registro do dia)._

> A lista React mostra 6 atributos por card. O **mesmo método** `index()`, no branch DataTables,
> entrega 14 colunas + 7 filtros + 2 ações em massa. Cada linha é âncora que **não** deve sumir
> sem Non-Goal declarado.

| Gap na lista React | Onde existe no legado | Âncora Delphi | Natureza |
|---|---|---|---|
| **Saldo em estoque na linha** | coluna `current_stock` (`:279-287`, com unidade) | `AR-PROD-012` (Quant. Estoque) | prop declarada (`stockQty`) e **sempre `null`** (`:465`) |
| **Imagem do produto** | coluna `image` (`:272-274`) | — | ausente (card usa ícone genérico) |
| **Localizações do produto** | coluna `product_locations` (`:196-200`) | `AR-PROD-055` | ausente |
| **Marca · unidade · imposto · tipo** | colunas `brand`/`unit`/`tax`/`type` | `AR-PROD-014` (Tipo) | só `unit` chega; resto ausente |
| **20 custom fields** (7 na grade) | `:326-357` | — _(a âncora `AR-PROD-040/041` que estava aqui é Plano de Contas e Marca, não campo personalizado)_ | ~~ausente~~ existe: colunas 1 a 7 no menu "Colunas" (só as rotuladas) e os 20 no painel — UC-PIDX-24 (2026-10-15) |
| **Filtros: tipo · marca · unidade · imposto · localização · not-for-selling** | `resources/views/product/index.blade.php:21-110 (verificado@5d5cac0)` (7 filtros) | `AR-PROD-060` (filtros de consulta) | só categoria + inativos; charter declara Non-Goal p/ "filtros avançados" (backlog) |
| **Seleção em massa + excluir/desativar selecionados** | `mass_delete` (`:276-278`) + `:438-496` | `AR-PROD-022` (soft-delete) | Non-Goal declarado no charter (`❌ Bulk actions`) — **ok, não é regressão** |
| **Aba "Relatório de estoque"** (custo, valor de venda, lucro potencial) | 2ª tabela do mesmo Blade (`:617-670`) | `AR-PROD-051/061` | ausente · pareia com `CU-PROD-12` `[V0]` (agregação de valor) |
| **Badge "não vendável"** (`not_for_selling`) | `:263-264` | — | ausente (só o badge "inativo" migrou) |
| **Selo WooCommerce sincronizado** | `:266-268` | — | ~~ausente~~ existe — UC-PIDX-23 (2026-10-15) |
| **Ordenação por qualquer coluna** | DataTables server-side | `AR-PROD-062` (grid agrupável) | ausente (ordem fixa por nome) |

---

## Âncoras propostas pro SPEC (o agent PROPÕE; [W]/humano aplica)

> **Não aplicadas ao `SPEC.md` neste run** — tocar o SPEC legado do Produto acorda o `anchor-lint`
> diff-aware sobre a dívida grandfathered dele (lápide `proibicoes.md` §5 2026-07-12; o módulo está
> em `cov 11.1%`, 8 de 9 US em `sem_campo`). Ficam como proposta pra [W] aplicar quando a
> US-PROD-023 avançar:

- `US-PROD-023` (promover as 8 telas React) → **Implementado em:** `resources/js/Pages/Produto/Index.tsx` → `GET /products` → [`ProductController@index`](../../../../app/Http/Controllers/ProductController.php) · casos: `Index.casos.md` · verificado@`<sha-quando-a-lane-rodar>`
- `US-PROD-020` (governança: casos.md + casos-gate) → **Implementado em:** `resources/js/Pages/Produto/Index.casos.md` · teste: [`tests/Feature/Produto/ProdutoIndexContratoTest.php`](../../../../tests/Feature/Produto/ProdutoIndexContratoTest.php) · verificado@`<sha>`

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- ✅ **Resolvido 2026-10-15:** a lista é paginada no servidor sem corte (UC-PIDX-01), os indicadores
  contam as mesmas linhas da lista sem filtro (UC-PIDX-07) e a contagem por categoria saiu com os
  cartões. Texto original: **[BACKLOG] Os três denominadores da tela não fecham** — `kpis.total` (todos, sem `modifier`),
  `rows` (sem `modifier`, cortado em 200) e `categorias[].count` (**tudo**, inclusive `modifier` e
  inativos). A soma das abas ≠ total do KPI ≠ cards na tela. Não virou UC porque o número correto
  de cada contador depende de qual pergunta a tela responde — decisão de desenho.
- ✅ **Resolvido 2026-10-15:** a coluna Estoque mostra o saldo com unidade (`colunas.tsx`, id
  `estoque`; `estoqueTexto` em `tipos.ts`). Texto original: **[BACKLOG] `stockQty` sempre `null`** — a interface declara (`Index.tsx:56 (verificado@d4afe95)`), o builder manda
  `null` fixo (`:465`) e o card **não renderiza**. O Blade tem a coluna `current_stock` com unidade.
  Vira UC quando [W] confirmar que o saldo entra no card (toca `[V0]`: saldo é estoque).
- ✅ **Resolvido 2026-10-15:** "não para venda" aparece na 2ª linha da célula Produto
  (`subLinhaProduto` em `tipos.ts`; some na densidade compacta) e há o filtro "Não para venda".
  Texto original: **[BACKLOG] Badge "não vendável" (`not_for_selling`)** — existe no Blade (`:263-264`), some no
  React. Sem âncora Delphi clara; 1 fonte só.
- ✅ **Resolvido 2026-10-15:** `FiltrosLista.tsx` tem tipo, categoria, unidade, imposto, marca,
  local, situação, modelo do dispositivo, "não para venda" e WooCommerce. Texto original:
  **[BACKLOG] Filtros avançados** — o charter declara "filtros avançados" como backlog explícito.
  (A ordenação por coluna, que estava neste item, virou o **UC-PIDX-13** em 2026-10-12.)
- **[BACKLOG] O promotor automático de charter pode estar lendo tráfego do Blade** — `/products`
  serve os dois branches; `route-hits` não distingue. Se confirmado, afeta as 8 telas do módulo e
  qualquer tela em branch dual MWART. É achado de **mecanismo**, não de tela — vira issue de
  governança, não UC.

### Pesquisa de concorrentes (2026-10-20) — casos que a lista ainda não cobre

> Vieram da comparação com 15 sistemas: 11 de catálogo e estoque (Shopify, Olist/Tiny, Omie, Bling,
> Conta Azul, Senior, Alterdata, Nuvemshop, Square, Zoho, Odoo) e 4 de comunicação visual (Mubisys,
> Calcgraf, Zênite, shopVOX). O código entre parênteses (D6, CV1…) é o do documento da pesquisa.
> Ficaram aqui só os que a pesquisa recomenda trazer; os que "não valem agora" ou "dependem de
> conferir" não entraram. Cada um vira UC quando ganhar teste que o defenda. Os marcados `[V0]`
> mostram estoque e caem na REGRA MESTRE de valor/estoque (dupla prova + antes→depois).
>
> **Termos revisados no protótipo (2026-10-20):** na tela, "separado" e "livre" substituem
> "reservado", "empenhado" e "disponível", que o cliente não entendia. Na lista a linha fica
> "20 m² separados para OS · 76 m² livres"; no painel, "Tem no estoque", "Separado para OS"
> (ou "Separado para vendas") e "Livre para usar" (ou "Livre para vender").
>
> **Ajustados às regras e fluxos do Wagner (2026-10-22):** os casos foram conferidos contra os
> `UC-FLX-*` de `prototipo-ui/cowork/Wagner/fluxos-data.jsx` e os interruptores de Sistema ›
> Fluxos e regras. Mudou: "separado" só existe com o interruptor "Reservar material na venda" e
> nasce na venda, então o material também é "separado para vendas"; o eixo produto, material e
> serviço se chama "Uso", porque "natureza" já é a natureza fiscal. Entraram requisição de compra,
> natureza fiscal pendente, método de custo e roteiro (bloco "Regras e fluxos", abaixo).

**Vestuário (ROTA LIVRE)**

- **[BACKLOG] Clicar em "Abaixo do alerta" mostra só esses produtos (D6)** — hoje o indicador conta,
  mas o clique não filtra. Aceite: Dado 3 produtos com estoque ≤ alerta, Quando clico no indicador,
  Então a lista mostra exatamente esses 3, com a etiqueta do filtro, e um 2º clique devolve a lista
  inteira. Fonte: Zoho (filtro Low Stock), Conta Azul (aba Esgotado), Nuvemshop (filtro sem estoque).
- **[BACKLOG] Ver cada cor e tamanho, com saldo, na própria lista (D7)** `[V0]` — hoje a lista diz
  só "N variações"; cada uma aparece no painel. Aceite: Dado um produto variável com 8 variações,
  Quando abro a linha, Então vejo cada variação com SKU e saldo, e a soma das variações é o saldo
  do produto. Fonte: Bling (variações marcadas na lista), Alterdata e Senior (estoque por grade).
- **[BACKLOG] Mostrar o estoque separado para vendas e o livre (D5)** `[V0]` — hoje o saldo não
  desconta o que já foi vendido e ainda não saiu. Aceite: Dado 10 un em estoque e uma venda não
  entregue que separou 3, Então a lista mostra "3 un separadas para vendas · 7 un livres". Só com
  o interruptor "Reservar material na venda" ligado em Sistema › Fluxos e regras (desligado por
  padrão, UC-FLX-14); desligado, a linha não aparece. Fonte: Conta Azul (aba Reservados), Senior
  (quantidade em reserva).
- **[BACKLOG] Excluir sem perder: o excluído pode ser restaurado (D13)** — hoje "Excluir" apaga o
  cadastro, as variações e os preços por grupo, e a confirmação diz que não tem volta. Aceite: Dado
  um produto excluído, Quando filtro a situação "Excluídos", Então ele aparece e "Restaurar" o devolve
  a Ativo com variações e preços. Fonte: Olist/Tiny (situação Excluídos + restaurar).
- **[BACKLOG] Giro e últimas compras no painel do produto (D8)** — hoje o painel mostra preços,
  estoque por local e fiscal. Aceite: o painel mostra a quantidade vendida nos últimos 90 dias e as
  3 últimas compras (fornecedor, data, quantidade). Fonte: Alterdata (consulta de estoque).
- **[BACKLOG] Recortes salvos com nome (D1)** — hoje os filtros vão para o endereço, mas não há
  recorte com nome. Aceite: Dado um conjunto de filtros, Quando salvo como "Lonas abaixo do alerta",
  Então ele aparece numa lista de recortes do usuário e abre com os mesmos filtros. Fonte: Shopify
  (visões salvas), Odoo (favoritos).

**Comunicação visual**

- **[BACKLOG] Separar material, produto e serviço na consulta (CV1)** — hoje o filtro "Tipo de
  produto" separa Único, Variável e Composição; material não tem marca própria. O eixo se chama
  "Uso" (Para vender, Material, Serviço) e não ganha campo novo: serviço vem da natureza fiscal do
  produto (UC-FLX-31) e material é o item que entra em alguma receita. Aceite: Dado lona (entra na
  receita do banner), banner e instalação (natureza fiscal serviço), Quando filtro "Material", Então
  só a lona aparece. No painel fica um rótulo só, "O que é", com o uso; a forma do cadastro aparece só
  quando muda algo ("Produto · com 8 variações", "Produto · kit com 4 itens"), e a coluna Tipo sai
  do padrão da lista (continua no menu Colunas). Fonte: shopVOX (produtos × materiais), Calcgraf
  (insumos e acabados).
- **[BACKLOG] Material separado para vendas × livre (CV4)** `[V0]` — mesmo pedido do D5, aplicado
  ao material. A reserva nasce na venda e a produção abate ao usar o material (UC-FLX-14), por isso
  o texto é "para vendas", não "para OS". Aceite: Dado 50 m² de lona e vendas que reservaram 20 m²,
  Então a lista mostra "20 m² separados para vendas · 30 m² livres". Só com o interruptor ligado; o
  próprio UC-FLX-14 lista como falta "a reserva por m² na CV e mostrar o reservado no saldo". Fonte:
  Calcgraf (material empenhado), shopVOX (quantidade reservada).
  **De onde vem o separado (UC-FLX-14, protótipo de 2026-10-22):** com o interruptor ligado, a aba
  Estoque do painel mostra Separado e Livre por local (o aviso "Repor" olha o livre) e a tabela "De
  onde vem o separado", uma linha por venda: cliente, OS, item com a medida (ex.: "Banner 2,00 x
  1,00 m x 4"), reservado em m², já usado pela produção e o que segue separado, com o total igual ao
  da lista. Aceite: Dado 20 m² de lona reservados por duas vendas, uma de 8 m² com 2 m² já usados na
  OS e outra de 14 m², Então a tabela mostra 6 m² e 14 m² separados e o total de 20 m². Produto de
  revenda (D5) mostra a mesma tabela sem OS e sem "já usado". Depende da reserva por m² existir na
  venda da CV, que o UC-FLX-14 ainda lista como falta.
- **[BACKLOG] Saldo do material em rolos e em m² (CV2)** `[V0]` — hoje o saldo aparece numa unidade
  só. Aceite: Dado 2 rolos de 1,60 m × 50 m, Então a lista mostra "2 rolos · 160 m²", e as duas
  contas fecham. Fonte: shopVOX (unidade de compra × unidade de venda).
- **[BACKLOG] Composição com a receita da Fabricação no painel (CV6)** — hoje o painel mostra só
  os itens de um produto do tipo Composição. Aceite: Dado um produto com receita na Fabricação,
  Quando abro o painel, Então vejo materiais e quantidades da receita. ⚠️ Conferir com o charter: o
  Non-Goal "Sub-views de BOM/Insumos ficam em `/products/unificado`" pode valer aqui — decisão [W].
  Fonte: shopVOX (lista de materiais, mão de obra e máquina do produto).
- **[BACKLOG] Rastrear o rolo por OS a partir da consulta (CV3)** — cada rolo é um lote, e o
  relatório de Lotes já mostra em que OS cada rolo foi usado (US-COM-025 · UC-FLX-18, parcial). A
  consulta seria a porta de entrada: a aba Rolos do painel lista os rolos e leva ao relatório de
  Lotes. Fonte: Mubisys (estoque fracionado com rastreio da mídia), Calcgraf (controle de bobinas).

**Regras e fluxos do Wagner (2026-10-22)**

- Requisição de compra aberta (UC-FLX-19) saiu deste backlog em 2026-10-26: virou UC-PIDX-30, com teste.
- Natureza fiscal (UC-FLX-31) saiu deste backlog em 2026-10-26: virou UC-PIDX-31, com teste. O texto antigo dizia
  que o pendente "trava a NF-e da venda"; o código bloqueia só a emissão separada (NF-e + NFS-e).
- Método de custo do insumo (UC-FLX-38) saiu deste backlog em 2026-10-26: virou UC-PIDX-32, com teste.
- Roteiro de produção (UC-FLX-07), preço proposto pela compra (UC-FLX-17) e setor de cada etapa
  (UC-FLX-08) saíram deste backlog em 2026-10-22: viraram UC-PIDX-28 e UC-PIDX-29, com teste.
- **[BACKLOG] Modelo da pilha do produto** (pedido de 2026-10-23) — Aceite: Dado o produto "Controle
  de portão" que usa a pilha CR2032, Quando abro o painel dele, Então vejo o modelo da pilha e posso
  abrir esse produto; e no painel da pilha CR2032 vejo os produtos e aparelhos que a usam. Produto sem
  pilha não mostra nada. A venda pede o modelo e a série do aparelho (Sells/Create.casos.md, mesmo
  pedido).
- **[BACKLOG] Quantos kits dá para vender** (comparação com concorrentes, 2026-10-23) — Aceite: Dado o
  kit que leva 2 unidades de A e 1 de B, com 10 de A e 3 de B no local, Quando abro o painel do kit,
  Então ele mostra "3 kits disponíveis" e qual componente limita (B); a venda não deixa vender mais
  kits do que isso quando o estoque é controlado. Fonte: Base e Mercado Livre (o estoque do kit sai do
  componente mais escasso) e Omie (o kit só agrupa; o saldo vem dos componentes). Hoje o painel mostra
  a composição do kit, mas não calcula quantos kits os componentes permitem.
- **[BACKLOG] Saldo fiscal no painel do produto** `[E0]` (pedido de 2026-10-27) — Aceite: Dado um
  produto com 5 un. no estoque, das quais 3 entraram com nota, Quando abro a aba Fiscal do painel,
  Então ela mostra "Saldo fiscal 3 un. · Estoque real 5 un." e de onde vem a diferença (compras sem
  nota). O saldo fiscal é o que entrou com nota (compras com nota, notas de entrada próprias,
  devoluções de venda com nota) menos o que saiu com nota (NF-e e NFC-e autorizadas, devoluções de
  compra), lido das próprias notas; não mexe no estoque. É também o número que falta ao inventário do
  SPED (Bloco H). Pedido da PraMoto, que compra com e sem nota; a compra e o aviso na emissão estão em
  `Purchase/Create.casos.md` e `Fiscal/Nfe.casos.md`.
- **[BACKLOG] Produtos sem NCM: filtro e NCM em lote** (pedido de 2026-10-27) — Aceite: Dado produtos
  que entraram sem NCM, Quando escolho o filtro "Falta NCM", Então a lista mostra só eles e eu informo
  o NCM de vários de uma vez. O card de Saúde fiscal, que já conta "Produtos ativos sem NCM válido"
  (`SaudeFiscalService`, `produto_sem_ncm`), abre a lista com esse filtro. Hoje a lista não tem o
  filtro nem a edição de NCM em lote. Pedido da PraMoto.

Fontes abertas em 2026-10-20: [Shopify](https://help.shopify.com/manual/products/searching-filtering) ·
[Olist/Tiny](https://ajuda.olist.com/produtos/acoes-em-lote-e-gerenciamento-de-produtos) ·
[Conta Azul](https://contaazul.com/tutoriais/como-fazer-controle-de-estoque-na-conta-azul) ·
[Senior](https://documentacao.senior.com.br/gestaoempresarialerp/5.10.4/menu_suprimentos/f211cpr.htm) ·
[Alterdata](https://ajuda.alterdata.com.br/shop/relatorios-do-shop/consultas-e-relatorios-de-produtos/relatorio-consulta-de-estoque-shop) ·
[Zoho](https://www.zoho.com/inventory/help/items/item-managing.html) ·
[Odoo](https://www.odoo.com/documentation/latest/applications/essentials/search.html) ·
[Bling](https://blog.bling.com.br/?p=9989) ·
[Nuvemshop](https://atendimento.nuvemshop.com.br/pt_BR/organizar-produtos/como-excluir-produtos-de-forma-rapida) ·
[Mubisys](https://mubisys.com/producao) ·
[Calcgraf](https://www.calcgraf.com.br/solucao/estoque/) ·
[shopVOX](https://shopvox-pro.helpdocs.io/article/htcy37tcab-products-vs-materials-in-shop-vox-pro).

### Decisões do [F] de 2026-10-26 (comentários na âncora e conversa) — todas na produção

O [F] comentou a âncora da tela (`prototipo-ui/cowork/Felipe/produto-blade.jsx`) em 2026-10-26 e, na
conversa do mesmo dia, fechou a regra do imposto: **aparece por relevância, não por permissão**,
porque imposto não é segredo (sai impresso na nota). Quem usa a tela é o administrador e o vendedor
de balcão, que informa o preço ao cliente; muitas vendas saem sem nota, e quem decide isso é a venda,
não o produto. O protótipo mudou no PR #9925. Cada
item abaixo vira caso, com teste, no PR que o levar à produção; os casos que ele contradiz são
reescritos no mesmo PR. As referências de código são do `main` em 3886121.

- A consulta não oferece compra: feito, virou o UC-PIDX-11 (com teste).
- O preço que a venda cobra vem primeiro, e o imposto aparece junto dele: feito, virou o UC-PIDX-19
  (reescrito, com teste), junto com a coluna única nas tabelas de preço.
- Aba Fiscal e "Dados fiscais (NCM/CFOP)" só em empresa que emite nota, começando pela prontidão:
  feito, entrou nos UC-PIDX-17 e 25 (com teste).
- Coluna e filtro Imposto só com alíquota cadastrada, e o CSV junto: feito, virou o UC-PIDX-34 (com
  teste). O "Baixar Excel" segue fora, por decisão à parte.

**Descartado no mesmo dia:** esconder o imposto por permissão (`fiscal.access`), que chegou a ir para
o protótipo neste PR. Escondia de quem vende um dado que a nota imprime, e deixava a tela nova
mostrar menos que a Blade (Non-Goal "Função a menos que a lista Blade").

O "Gerar OP" também saiu do protótipo nesse dia; a produção nunca o teve (ver UC-PIDX-19), então não
há item aqui.

---

## Refs

- Charter (lei): [`Index.charter.md`](Index.charter.md) — `v2`, `status: live`, `last_validated: 2026-07-12`
- SDD (âncora dos CU): [`SDD-tela-cadastro-produto-v1.0.md`](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md) §5.3 F8 · §6.1 `CU-PROD-15`
- Contrato de paridade Delphi: [`ANTI-REGRESSAO-cadastro-produto-legacy.md`](../../../../memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md)
- Paridade do cutover: [`PARIDADE-charter-vs-legado.md`](../../../../memory/requisitos/Produto/PARIDADE-charter-vs-legado.md) §2
- RUNBOOK MWART: [`_telas/RUNBOOK-produto-index.md`](../../../../memory/requisitos/Produto/_telas/RUNBOOK-produto-index.md)
- Irmãos do trio: [`Show.casos.md`](Show.casos.md) · [`Edit.casos.md`](Edit.casos.md) · [`Create.casos.md`](Create.casos.md) · [`SellingPrices.casos.md`](SellingPrices.casos.md) · [`StockHistory.casos.md`](StockHistory.casos.md)
- SPEC (a US que promove a tela): [`SPEC.md`](../../../../memory/requisitos/Produto/SPEC.md) — US-PROD-020 · US-PROD-023
- Mecanismo: [ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md) (`sdd-from-source`) · [errata 0352](../../../../memory/decisions/0352-errata-0351-venue-distiller-citacao-taxonomia.md)
- Gate: `scripts/casos-coverage-guard.mjs` (G-1/G-2/G-5/G-6/G-7 — [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md))

## Trilha do tempo
- 2026-10-27 · [F+C] Revalidado (G-6): o `Index.tsx` ganhou data nova só pelo merge com o `main` de hoje, depois que o #9925 entrou; o conteúdo é o `emiteNota` passado ao painel por este PR (UC-PIDX-17 e 25), já descrito pelos casos acima. Vitest local dos 18 arquivos de produto: 213 testes passaram. Nenhum UC muda de status; last_run 2026-10-26 → 2026-10-27.
- 2026-10-26 · [F+C] **UC-PIDX-34** (decisão [F]): coluna e filtro Imposto da lista só em empresa com alíquota cadastrada; a coluna nasce desligada e o "ligada" que o navegador guardou antes é desligado uma vez; o CSV de "Exportar filtrados" segue a regra. O UC-PIDX-10 deixa de ter nota pendente e o UC-PIDX-26 diz que o cenário de 12 colunas inclui a Imposto ligada. O backlog das decisões de 26/10 fica vazio.
- 2026-10-26 · [F+C] **UC-PIDX-19 reescrito, UC-PIDX-17, 20 e 25 ajustados** (decisão [F]): o imposto aparece por relevância no painel. A aba Dados começa com o preço que a venda cobra (com imposto) e, só com imposto, o preço sem imposto, o imposto aplicável e a frase; as tabelas de preço têm uma coluna quando sem e com imposto são iguais; a aba Fiscal e o item "Dados fiscais" só existem em empresa com o módulo Fiscal no pacote (`contexto.emite_nota`) e a aba começa por "Pronto para nota" ou "Sem NCM". O caso antigo do UC-PIDX-19 fica registrado na seção dele; dois itens do backlog viram caso. Falta a coluna e o filtro Imposto da lista (PR seguinte).
- 2026-10-26 · [F+C] **UC-PIDX-11 reescrito** (decisão [F]): a consulta não oferece compra. Saem "Comprar este produto" do menu da linha, "Comprar" do rodapé do painel e a permissão `comprar` das props da lista; os rótulos "(excl.)/(incl.)" do painel viram "sem imposto/com imposto". O caso antigo ("Comprar abre a nova compra") fica registrado na seção do UC; o backlog perde o item que virou caso.
- 2026-10-26 · [F+C] **só documentação:** as decisões do [F] na âncora e na conversa (Comprar sai, Gerar OP sai, imposto por relevância e não por permissão, preço que a venda cobra em primeiro, aba Fiscal só em empresa que emite nota) entram como backlog, com nota nos UC-PIDX-09, 10, 11, 17, 19 e 25. O protótipo mudou no #9925; a produção não. Nenhum caso, teste ou status muda.
- 2026-10-23 · [M+C] **UC-PIDX-33** (painel, aba Estoque): o backlog "Quantidade vendida por local" vira caso, a pedido da ROTA LIVRE (número 33 porque 28–32 já estão nos rascunhos #9778 e #9785). A tabela de locais ganha a coluna "Vendido", como no artefato "Consulta de Produto" de 22/10; o número já vinha do servidor em cada linha.
- 2026-10-18 · [F+C] `produto-lista-encaixe` e `produto-lista-redimensionar` (UC-PIDX-16, 26 e 27) entram no Vitest do `Frontend / Vite build`. Foi em PR próprio porque `.github/` pede revisão do [W]; o conserto da tela entrou antes, no #9535.
- 2026-10-18 · [F+C] **UC-PIDX-26, conserto do smoke:** o rótulo "CATEGORIA" (79px) vazava sobre MARCA com o piso de 66; os pisos passam a respeitar o rótulo do cabeçalho (categoria 80, locais 62, SKU 58, produto 112 para manter a soma), com caso de teste que trava isso.
- 2026-10-17 · [F+C] **UC-PIDX-26 e UC-PIDX-27 (novos) e UC-PIDX-16 (largura lembrada)**, a partir da reclamação da ROTA LIVRE (monitor 1366×768: colunas fora da tela e ajuste refeito a cada visita). Em tela estreita as colunas encolhem até caber; a grade ganha altura própria, como no protótipo, e a barra de rolar para o lado fica à vista; a largura arrastada passa a ser lembrada no navegador, com "Voltar às larguras de saída" no menu "Colunas". Pôr `produto-lista-redimensionar` e `produto-lista-encaixe` no Vitest do `Frontend / Vite build` fica para PR próprio, porque `.github/` pede revisão do [W].
- 2026-10-16 · [F+C] **UC-PIDX-25, regra do botão (decisão [F]):** "Abrir no módulo Fiscal" aparece só com o módulo Fiscal no pacote **e** a permissão `fiscal.access`, como o menu Fiscal; continua exigindo `nfe.tributacao.manage`. **Errata da entrada abaixo:** ela concluiu que a empresa 1 usa o Fiscal pelo pacote porque havia um link `/fiscal/config` na barra lateral, e esse link é o alerta de certificado vencido, não o menu. Medido em produção em 2026-10-16: o pacote 1 não tem `fiscal_module` nem `nfebrasil_module`, e das 93 empresas (7 com assinatura ativa) nenhuma tem `fiscal_module`. O botão segue escondido em produção até o módulo Fiscal entrar no pacote da empresa, que é ato do superadmin.
- 2026-10-15 · [F+C] **UC-PIDX-25, conserto:** o botão "Abrir no módulo Fiscal" checava o pacote `nfebrasil_module` e não aparecia em produção (biz 1, medido: `link_modulo: null` com a permissão concedida — o simulador respondeu 200). O pacote certo é o `fiscal_module`: desde 2026-05-25 o NfeBrasil não publica menu e a Tributação é aberta pelo hub Fiscal.
- 2026-10-15 · [F+C] **UC-PIDX-25** (aba Fiscal): entram a origem da mercadoria (o valor que a nota usa, com o aviso de que é o mesmo para todos), o texto de ajuda (corrigido onde o protótipo afirmava o que a emissão não faz) e "Abrir no módulo Fiscal", que leva à Tributação com o produto no simulador (UC-NFTR-29). Fecha três dos itens que o UC-PIDX-19 deixou como não feitos.
- 2026-10-15 · [F+C] **UC-PIDX-24** (lista): os campos personalizados 1 a 7 já eram colunas opcionais (desde #9124) e não tinham teste; o caso entra com teste de servidor (comparado com a Blade) e de tela. A tabela de paridade deixa de dizer "ausente" para custom fields e selo WooCommerce, e perde a âncora errada `AR-PROD-040/041`.
- 2026-10-15 · [F+C] **UC-PIDX-23** (lista): produto que sincroniza com o WooCommerce passa a ter a marca ao lado do nome, como a lista Blade; o dado (`woocommerce_sync`) já vinha do servidor.
- 2026-10-15 · [CC] **só documentação:** D-1..D-4 e G-g marcados como resolvidos pela lista nova, com a evidência no código; 4 itens do backlog idem; nota na tabela de paridade com o que ainda falta (custom fields e selo WooCommerce). O SDD §1.1 deixa de dizer "zero live". Nenhum UC muda.
- 2026-10-14 · [F+C] **UC-PIDX-22** (lista e painel): produto sem foto passa a mostrar "IMG" na coluna Imagem e "Sem imagem" no painel, como no protótipo; o servidor manda `imagem = null` em vez da imagem genérica.
- 2026-10-14 · [F+C] **UC-PIDX-21** (painel): produto que não controla estoque passa a ter a aba Estoque, com o aviso "Este produto não gerencia estoque." Sem permissão de ver o relatório, a aba continua fora.
- 2026-10-14 · [F+C] **UC-PIDX-20** (painel): a composição ganha o "Total líquido", o preço de venda da composição e as colunas de preço da Blade por item; as abas Variações e Composição mostram as imagens. Total provado por dois caminhos (à mão e pela janela "Ver" da Blade).
- 2026-10-14 · [F+C] **UC-PIDX-18 / UC-PIDX-08, conserto:** lucro potencial e linha vermelha de alerta passam a pintar de verdade (cor em `style`; a classe utilitária perdia para `.pb-tbl tbody td` do bundle). A linha vermelha de alerta nunca tinha aparecido.
- 2026-10-13 · [F+C] **UC-PIDX-19** (aba Fiscal): entra "Tipo de imposto na venda". Origem da mercadoria, "Abrir no módulo Fiscal", o texto de ajuda, "Transferir" por local e "Gerar OP" ficam registrados como não feitos, com o motivo de cada um.
- 2026-10-13 · [F+C] **UC-PIDX-18** (aba Relatório de estoque): seletor de linhas por página (`estoque_por_pagina`, as opções da lista Blade) e lucro potencial da linha com cor. Totais provados iguais em 25, 50 e Todos.
- 2026-10-13 · [F+C] **UC-PIDX-17** ("Dados fiscais" no menu da linha): item novo no menu Ações, abre o painel na aba Fiscal; a aba ativa do painel passa a ser da página, e cada abertura recomeça na aba pedida.
- 2026-10-13 · [F+C] **UC-PIDX-16, conserto:** a largura arrastada sumia ao ordenar/paginar/filtrar (tabela dentro de `<Deferred>` é desmontada); passou a ser guardada pela página. Achado no arrasto real em produção, depois do deploy.
- 2026-10-13 · [F+C] **UC-PIDX-16** (redimensionar coluna): `shared/DataTable` ganha a opção `resizable` (desligada por padrão) e a lista de produtos a liga; Ação e Imagem ficam fixas, como no protótipo. Rodapé passa a dizer "arraste a borda pra redimensionar".
- 2026-10-13 · [F+C] **UC-PIDX-15** (estoque inicial em janela): o menu da linha abre a janela em vez da página Blade; `OpeningStockController::salvarDaJanela` confere o pedido e entrega ao `save()`; prova de estoque com produto gêmeo no `EstoqueInicialJanelaTest`.
- 2026-10-12 · [F+C] **UC-PIDX-14** ("Cadastro rápido" na barra da lista): a janela manda os dois preços digitados e o servidor monta o pedido do formulário Blade e entrega ao `saveQuickProduct`; prova de valor por dois caminhos no `ProdutoListaCadastroRapidoTest`.
- 2026-10-12 · [F+C] **UC-PIDX-13** (ordenar pelo cabeçalho): a tela prometia e não fazia — as colunas ganharam o `accessorFn` que faltava e a tabela passou a receber a ordem atual; teste de servidor e de tela.
- 2026-10-12 · [F+C] **UC-PIDX-12** (os 3 indicadores da aba Estoque do painel): soma das linhas do relatório de estoque do produto, pela mesma função do rodapé da aba "Relatório de estoque"; prova dupla no `ProdutoDetalheTest`.
- 2026-10-10 · [F+C] **UC-PIDX-10** ("Exportar filtrados" em CSV) e o botão "Etiquetas" na barra de seleção (abre a tela de etiquetas com os produtos selecionados — UC-PETQ-06).
- 2026-10-10 · [F+C] **UC-PIDX-09** (painel de detalhe): os dados do painel no servidor, com o conteúdo e os gates da janela "Ver" da Blade; a tela do painel vem no PR seguinte.
- 2026-10-10 · [F+C] menu da linha: sai "Ver" e o clique na linha (o destino `/products/{id}` não é página — é o pedaço das prateleiras; medido em produção) até o painel de detalhe do protótipo; entra "Adicionar ou editar estoque inicial" (como na lista Blade: estoque gerenciado + `product.opening_stock`, página do `OpeningStockController@add`). Nenhum UC muda.
- 2026-10-10 · [F+C] **UC-PIDX-08** (aba "Relatório de estoque"): linhas de Relatórios → Estoque com os filtros da tela, rodapé com o total de todas as linhas; sem filtro, o valor pelo custo é o indicador do UC-PIDX-07.
- 2026-10-10 · [F+C] **UC-PIDX-07** (os 4 indicadores do protótipo): contagens sobre a consulta da lista; valor = total do relatório de estoque (decisão [F], opção B), com prova dupla no `ProdutoListaKpisTest`.
- 2026-10-10 · [F+C] **revalidação G-6** para a lista no desenho do protótipo (charter v3, #9118; dados #9119; tela #9124). Os 6 UCs ficam; o teste foi reescrito para a prop `lista` e passou inteiro na lane (run 38087431948); a saída da quarentena vai em PR próprio, por CODEOWNERS do `.github/`; status voltam a ⬜ até a lane rodar. A tabela de paridade e o backlog abaixo descrevem a tela de cards: o saldo, a imagem, os locais, as colunas, os 7 filtros, a seleção em massa, o selo "não para venda" e a ordenação por coluna **entram na tela nova**; a aba Relatório de estoque e os indicadores entram nas etapas seguintes.
- 2026-07-26 · [CC] nascido pelo agent `sdd-from-source` ([ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md)) — 3ª tela do módulo (depois de `Edit` e `Show`), trio fechado (charter existia + casos novo + `ProdutoIndexContratoTest` failing-first). Triangulação incomum: aqui a Blade legada **é o outro branch do mesmo método** `index()`, então a paridade é comparação interna do controller. **Achado principal:** a lista React entrega `price`/`cost`/`margin` de todo o catálogo sem consultar permissão, enquanto a **própria lista Blade** gateia as duas colunas com `@can` (UC-PIDX-03) — é o `UC-PSHOW-01` de novo, agora em lote. **Achado de alcance:** `limit(200)` sem paginação, com KPI anunciando o total — perda silenciosa (UC-PIDX-01). **Achado de arquitetura:** `App\Product` **não tem global scope** (`addGlobalScope` = 0 ocorrências); o isolamento do módulo inteiro é `where` manual repetido (UC-PIDX-04). **Governança:** o §Pest GUARD prometia 10 testes e o arquivo não existe; 3 refs factuais do charter estavam podres e foram corrigidas. Nenhum UC declarado ✅ — a lane `Estoque · MySQL` publica o veredito.
