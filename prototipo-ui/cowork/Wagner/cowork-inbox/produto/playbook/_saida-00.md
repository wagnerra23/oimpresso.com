---
sessao: "00"
titulo: "Recibo — PUXAR as 8 Pages vivas de Produto para o protótipo (uma rota fixa por Page)"
autor: "[CL]"
data: 2026-10-07
base: origin/main 836619f64d
thread: 00-puxar-vivo.md
veredito: "entregue — 8 de 8 Pages com rota fixa em window.ProdRotas; as 9 rotas (8 Pages + prod-lista) renderizadas sem erro no espelho servido; tocados produto-blade.jsx, produto-blade-forms.jsx, produtos-page.jsx e produto-perms.jsx."
---

# _saída 00 · PUXAR o vivo de Produto

> A ficha dá esta thread ao [CC]. Quem executou foi o [CL], a pedido do agente-pai. Nada subiu ao
> Cowork: escrever no Claude Design exige opt-in do dono (ADR 0315). Até a subida, o check
> `espelho — mexeu depois de verificar` acusa os 4 arquivos. Isso é esperado.

## D4 conferido no `main` (`836619f64d`)

`Inertia::render` lido por grep em `app/Http/Controllers/`: `ProductController` → `Produto/Index` (:350),
`Create` (:605), `Show` (:854), `Edit` (:969), `SellingPrices` (:2152), `BulkEdit` (:2557),
`StockHistory` (:2913); `ProdutoUnificadoController:180` → `Produto/Unificado/Index`. Bate com o
índice, com as linhas de `SellingPrices`/`BulkEdit`/`StockHistory` deslocadas em +2.

Surgiram Pages novas em `Pages/Produto/` desde o índice: `Cadastros/`, `Etiquetas/`, `Importacao/`,
`AtualizarPreco/` (threads 02–06). São de outras threads e ficaram fora desta.

## Ponto aberto: `produtos` × `prod-lista`

**O Unificado NÃO fundiu os dois.** Produção tem duas entradas no menu de Produtos
(`AdminSidebarMenu.php:185-205`): "Listar produtos" → `/products` (`ProductController@index`) e
"Consulta de Produtos" → `/products/unificado` (ligada por [W] em 2026-09-21).

- `produtos` (catálogo, `produtos-page.jsx`) é o porte reverso do `Produto/Unificado/Index` — o
  próprio arquivo declara isso em `:1-2`, e o charter do Unificado o aponta como `bundle_source`.
- `/products` é rota dupla: com header `X-Inertia` monta `Produto/Index` (o catálogo em cards);
  sem ele, devolve o Blade `product.index`. O `prod-lista` do protótipo é o Blade (lista com
  DataTable, filtros, ações em massa). Ficou assim: `prod-lista` continua o Blade e a Page React
  ganhou rota própria, `prod-index`.
- O charter de `Produto/Index` já registra a mesma dúvida como "Divergência aberta (decisão [W])"
  (`Index.charter.md:200`). Consolidar as duas listas é decisão de [W], não desta thread.

## Mapa rota ↔ Page

| rota | Page Inertia | o que monta |
|---|---|---|
| `produtos` | `Produto/Unificado/Index` | `window.ProdListPage` (já existia) |
| `prod-index` | `Produto/Index` | vista nova "Catálogo" (cards) |
| `prod-lista` | — (Blade `product.index`) | lista do Blade (já existia) |
| `prod-novo` | `Produto/Create` | formulário vazio |
| `prod-editar` | `Produto/Edit` | formulário com o produto 1 (`PRD-0001`) |
| `prod-detalhe` | `Produto/Show` | vista nova "Detalhe" do produto 1 |
| `prod-precos` | `Produto/SellingPrices` | Tabelas de preço do produto 1 |
| `prod-massa` | `Produto/BulkEdit` | Edição em massa (4 produtos) |
| `prod-historico` | `Produto/StockHistory` | Histórico de estoque do produto 1 |

A tabela está em `const ROTAS` e exportada em `window.ProdRotas` (`produto-blade.jsx`). Nenhuma
rota nova foi registrada no `app.jsx`: ele manda todo `prod-*` para `window.ProdutoBladePage`, e
rota que não está no `PROD_VIEW` dele chega como `view="lista"`. Por isso a página lê
`window.__route` na tabela antes de usar a `view` (`prod-index`, `prod-editar` e `prod-detalhe`
não estão no `PROD_VIEW`). Navegação interna também troca a rota do shell.

⚠️ O `design-diff-lote.mjs` lê a tabela `ROTAS` **da âncora** da tela. A âncora de Create/Edit é
`produto-blade-forms.jsx` (sem a tabela) e as outras são `n/a`, então o lote ainda não usa estas
rotas. `rotasDaAncora()` aplicado a `produto-blade.jsx` devolve as 8 Pages (medido abaixo).

## Diff por tela — o que o vivo tem e entrou

Fonte: leitura dos 8 `.tsx` (+ `Unificado/_components/`) no `main` `836619f64d`. Nenhum `.tsx`
foi editado.

| Page | entrou no protótipo |
|---|---|
| `Produto/Index` | Vista nova `TelaCatalogo`: KPIs Total de produtos · Ativos · Categorias · Populares · 30d ("≥30 vendas/mês"); busca "Buscar por nome ou SKU"; "Mostrar inativos"; abas por categoria com contagem ("Todos" + cada categoria); cards com categoria, selo "Inativo", nome, SKU, "Preço", "/ unidade" e barra de "Popularidade"; vazio "Nenhum produto encontrado" / "Ajuste filtros ou cadastre o primeiro produto." O preço some sem `access_default_selling_price`. Popularidade vem das vendas do histórico mock (`vendas30`). |
| `Produto/Create` | D7: os campos personalizados 1–7 deixaram de ter rótulo fixo. O rótulo vem de `PBD.CAMPOS_PERSONALIZADOS` (mock do cadastro do negócio, só 1 e 2 preenchidos); campo sem rótulo fica oculto no formulário, na coluna da lista e no detalhe. |
| `Produto/Edit` | Título "Editar · {nome}" com o SKU na nota; botão principal "Salvar alterações" (no Create segue "Salvar"). |
| `Produto/Show` | Vista nova `TelaDetalhe`: nome + "SKU · categoria · unidade", botões "← Voltar", "Histórico estoque" e "Editar" (este só com `product.update`), abas Resumo · Variações · Estoque. Resumo: Identificação (Nome, SKU, Tipo, Unidade, Categoria, Subcategoria, Marca, Estoque controlado?) + Descrição. Variações: Variação · SKU · Preço compra · Preço venda; cada coluna de preço some sem a permissão. Estoque: Local · Prateleira / Fileira / Posição · Estoque atual. Estados vazios do vivo nas duas abas. |
| `Produto/SellingPrices` | Título "Tabelas de preço · {nome}" com SKU; selo "Não salvo"/"Salvo"; colunas Variação · SKU · Preço padrão (sempre, não só em produto variável); tipo "Fixo"/"%"; texto de ajuda do vivo com `⌘S`; atalho ⌘S/Ctrl+S salva; botão "Salvar tabelas", desabilitado sem alteração; estados vazios "Nenhum grupo de preço cadastrado…" e "Produto sem variações cadastradas…". |
| `Produto/BulkEdit` | Título "Edição em massa · N produtos"; alerta "Estas alterações afetam N produtos simultaneamente." / "Revise cada linha antes de confirmar. Não há desfazer automático — apenas re-edição manual."; confirmação em dois passos: "Atualizar N produtos" → "Confirmar (N)". |
| `Produto/StockHistory` | Nota "nome · SKU · unidade"; botão "← Voltar ao produto"; resumo Entradas · Saídas · Ajustes · Saldo do período (só com movimento); colunas na ordem do vivo Data · Tipo · Origem · Referência · Quantidade · Saldo, com Tipo em selo Entrada/Saída/Ajuste; vazio "Sem movimentação registrada" + "Não há entradas, saídas ou ajustes para a variação e o local selecionados." |
| `Produto/Unificado/Index` | O porte de 2026-08-25 já cobria quase tudo. Entraram: vazio da tabela "Nenhum item neste recorte" com a frase condicional do vivo; paleta com "Ir para aba, recorte ou item recente…", "Sem venda há N dias", "Limpar o recorte" e "Usar linhas compactas"/"Voltar às linhas confortáveis"; texto da confirmação de inativar; o botão ⋯ ganhou `aria-label` "Mais ações" e `title` "Apresentação e dados" (antes o rótulo acessível era vazio). |

Mudança de apoio: `produto-perms.jsx` ganhou `product.update`, `view_purchase_price` e
`access_default_selling_price`. Administrador e Gerente têm as três; Balcão tem só a de preço de
venda. Nenhuma tela antiga muda por isso, porque nenhuma lia essas permissões.

## O que o vivo tem e é defeito — não entrou

- `Produto/StockHistory`: o rodapé "Precisa do saldo corrente ou de tipos ainda não migrados?"
  com link pro relatório legado é andaime de migração ("até Wave 3"), não produto.
- `Produto/Show`: "Rack/Row/Position" (rótulo em inglês) entrou como "Prateleira / Fileira /
  Posição"; "Sem rack/localização cadastrada." entrou como "Sem prateleira/localização cadastrada.".
- `Produto/Create` e `Edit`: "Campo personalizado 1/2" com rótulo fixo contraria o D7.
- `Produto/BulkEdit` e `Show`: "Sub-categoria" com hífen. O protótipo mantém "Subcategoria".
- `Produto/Index`: o cabeçalho próprio da Page duplica o título e o breadcrumb que o `AppShellV2`
  já desenha. O protótipo usa o cabeçalho do módulo.

## Ficou fora, e por quê

- **Sub-telas do Unificado** (`?tela=categorias|insumos|tabelas|historico`, `SubTelas.tsx`): sem
  entrada na tela desde 2026-08-24 (charter do Unificado), só por URL. Não portadas.
- **Rótulos do formulário do Create/Edit vivo** ("Nome do produto *", "SKU (deixe em branco pra
  gerar automático)", "Imposto inclui no preço?", "Exclusivo (somar imposto)", "Controlar
  estoque", "Não está à venda (apenas insumo)", a seção recolhível "Mais opções"). O
  `produto-blade-forms.jsx` é a **âncora** do Create e do Edit (`related_prototype`); trocar o
  rótulo da âncora pelo do vivo inverteria a autoridade (protótipo manda na forma, UI-0029). Listo
  para [W]; os campos em si já existem no protótipo.
- **`app.jsx` e `data.jsx`** (fora do prefixo): `prod-index`, `prod-editar` e `prod-detalhe` não
  estão no `PROD_VIEW` do `app.jsx` nem na sidebar. Funcionam porque a página lê `window.__route`.
  Para virar atalho na sidebar, alguém dono do `data.jsx` registra.
- **`produto-blade.css`** (fora do prefixo): os cards do Catálogo usam estilo inline com tokens
  (`var(--border)`, `var(--surface)`…), nenhuma cor literal.
- **Grupos de preço com descrição** (`SellingPrices` mostra `pg.description` no cabeçalho): o mock
  `PRICE_GROUPS` não tem descrição.

## Só no protótipo — para [W] decidir (nada foi apagado)

1. Lista do Blade (`prod-lista`): KPIs Abaixo do alerta / Valor em estoque (custo) / Inativos,
   densidade, menu de colunas, "Cadastro rápido", rodapé de seleção (edição em massa, local,
   etiquetas, desativar, excluir) e o drawer de detalhe. A Page React `Produto/Index` não tem nada
   disso.
2. Formulário: Fiscal (NCM · CEST · CFOP · origem), sub-unidades, locais, prateleira por local,
   variações e composição, rascunho automático, "Bipar", imagem/folheto, os botões "Salvar e
   adicionar preços por grupo / estoque inicial / outro". No Edit o vivo tem só "Cancelar" e
   "Salvar alterações"; os três "Salvar e adicionar…" seguem no modo edição do protótipo.
3. Histórico: filtros Produto e Período, quadros "Quantidades que entraram/saíram/Totais",
   cobertura sobre o alerta, "Lançar ajuste" e a coluna "Cliente / fornecedor".
4. Tabelas de preço: "Salvar e adicionar estoque inicial" / "Salvar e adicionar outro" e o preço
   com imposto embaixo do preço padrão.
5. Edição em massa: coluna Locais, preço com imposto e % de lucro, busca para somar produto.
6. Detalhe: os campos personalizados com rótulo aparecem na Identificação (o vivo não os mostra).

## Provas

- **Render.** Espelho servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`)
  na porta 5577, Playwright chromium do repo principal, viewport 1280×900, `localStorage` limpo e
  só `oimpresso.route` fixado antes do boot, espera `networkidle` + 2,5 s. Rotas medidas:
  `produtos, prod-index, prod-lista, prod-novo, prod-editar, prod-detalhe, prod-precos,
  prod-massa, prod-historico` → **9 de 9 com 0 erro de página e 0 erro de console**;
  `window.ProdRotas` com 9 entradas em todas. Rótulo de tela lido no DOM:
  `prod-index` → "01 Produto · Catálogo"; `prod-editar` → corpo começa em
  "EDITAR · LONA 380G BRILHO IMPRESSA PRD-0001"; `prod-detalhe` → "01 Produto · Detalhe";
  `prod-precos` → "01 Produto · Tabelas de preço"; `prod-massa` → corpo começa em "Estas
  alterações afetam 4 produtos simultaneamente."; `prod-historico` → corpo com "← Voltar ao
  produto". Antes das edições, as 6 rotas que já existiam também saíam com 0/0 (mesmo script).
  `prod-precos` e `prod-detalhe` foram re-medidas depois da última edição: 0/0.
- **Tabela de rotas.** `rotasDaAncora(produto-blade.jsx)` (do `design-diff-lote.mjs`) → 8 pares
  page→rota, um por Page; `prod-lista` (`page: null`) fica fora, como deve.
- **Sintaxe.** `esbuild.transformSync(..., { loader: 'jsx' })` nos 4 arquivos → OK, e varredura de
  bytes de controle (< 0x20 fora de TAB/LF/CR) → 0.
- **ds-guard.** `node scripts/design/ds-guard.mjs <os 4 .jsx>` → `limpo`, mas **o resultado é
  vazio**: o próprio ds-guard imprime "ignorado: nao e css/html" para os 4. Não serve de prova
  para JSX. Complemento: nas linhas adicionadas do diff, 0 ocorrências de cor literal (`#hex`,
  `oklch(`, `rgb(`).
- **Baselines.** `node scripts/design/render-proto-baseline.mjs --check` → 12 drifts em 9
  baselines STALE. O `render_sha256` cobre o grafo inteiro do protótipo, então editar estes
  arquivos deixa as baselines STALE (foi o caso do #8528 no Repair). **Não medi** se já estavam
  STALE antes das minhas edições. O agente-pai precisa regenerar com `--gerar` e conferir com
  `--check` antes do merge.
- **Base.** Worktree em `836619f64d`; `origin/main` andou 4 commits (`4bc5c314b8`), nenhum toca
  `prototipo-ui/cowork/Wagner/` nem `resources/js/Pages/Produto/`.

## O que precisa subir ao Cowork

- `prototipo-ui/cowork/Wagner/produto-blade.jsx`
- `prototipo-ui/cowork/Wagner/produto-blade-forms.jsx`
- `prototipo-ui/cowork/Wagner/produtos-page.jsx`
- `prototipo-ui/cowork/Wagner/produto-perms.jsx`
- `prototipo-ui/cowork/Wagner/cowork-inbox/produto/playbook/_saida-00.md`
