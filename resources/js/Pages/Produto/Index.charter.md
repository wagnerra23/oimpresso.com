---
id: resources-js-pages-produto-index-charter
page: /products
component: resources/js/Pages/Produto/Index.tsx
related_prototype: "prototipo-ui/cowork/Felipe/produto-blade.jsx (vista lista — TelaLista)"
owner: wagner
status: live
last_validated: "2026-07-12"
parent_module: Produto
related_adrs: [110, 107, 93, 104, 149]
related_us: [US-PROD-020, US-PROD-023]
related_runbook: memory/requisitos/Produto/_telas/RUNBOOK-produto-index.md
related_visual_comparison: memory/requisitos/Produto/_telas/produto-index-visual-comparison.md
tier: A
charter_version: 2
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Felipe/produto-blade.jsx"
  blueprint_screenshot_approval: "SYNC_LOG (pendente — Wave 2 B4 Produto 2026-05-15)"
  derived_screens: [Create, Show, Edit, SellingPrices, BulkEdit, StockHistory]
  divergence_from_blueprint: "none — Index é o blueprint canon B4 Produto"
---

# Page Charter — /products (lista de produtos no desenho do protótipo)

> **v3 · 2026-10-10 · [F+C], autorizado por [W] (WhatsApp, repassado pelo [F]: "corrigir a tela").**
> A tela de `/products` é a vista **`lista`** (`TelaLista`) de `prototipo-ui/cowork/Felipe/produto-blade.jsx`
> — a tradução 1:1 do `index.blade.php` no visual Cockpit — **com tudo o que a lista Blade já faz**
> (decisão do [F] 2026-10-09: "não podemos perder funções que existem em produção nem descartar as
> boas funções do protótipo"). As versões v1/v2 abaixo descreviam um catálogo de **cards** ("lite"),
> que foi o desenho errado: subiu pelo menu em 2026-10-09 (#9094), a ROTA LIVRE reclamou (não achava
> produto pelo código, não via estoque, faltavam filtros) e o menu voltou ao Blade (#9117).
> Mission, Goals, Non-Goals, UX e Anti-hooks foram reescritos para a v3; o texto v2 fica só no Histórico.
>
> _(v2, histórico — superado pela v3:)_

> **Status:** draft v2 atualizado 2026-05-15 (Wave 2 B4 Produto Agent W2-C). Blueprint visual definido: `prototipo-ui/cowork/Wagner/produtos-page.jsx` (cockpit-page.jsx + Produtos Cockpit.html + visual-source.html). Pattern serve de blueprint pras 6 telas derivadas (Create/Show/Edit/SellingPrices/BulkEdit/StockHistory) — ADR 0149.
>
> Versão v1 (2026-05-09) referenciava `prod-page.jsx` (6.5 KB grid-first). v2 promove para blueprint cockpit completo. Wagner aprova **Non-Goals + Automation Anti-hooks** ANTES de virar `status: live`.
>
> ⚠️ **Relação com `/products/unificado`:** essa Page é a versão SIMPLES (catálogo grid only). `/products/unificado` é a versão DENSA (5 sub-views). Wagner decide na aprovação se mantém ambas ou unifica em uma só. Backend canon: `app/Http/Controllers/ProductController.php` (UPOS herdado). Produto = `App\Product` direto em `app/`, **NÃO** em `Modules\Produto\`.

---

## Mission

Lista completa do catálogo para o balcão e o cadastro: achar qualquer produto (por nome, código,
código da variação, categoria, marca ou campo personalizado), ver preço, estoque e situação, filtrar
como na lista Blade e agir sobre um ou vários produtos — no desenho do protótipo, sem perder nenhuma
função da lista Blade que roda hoje em produção.

---

## Goals — Features (faz)

- AppShellV2 + `<PageHeader>`: "Produtos · Catálogo", linha de contexto (empresa · local · nº de
  produtos · grupos de preço · papel), "Atualizado HH:MM" (recarrega), **Baixar Excel**
  (`/products/download-excel`) e **Novo produto** (`/products/create`, Blade).
- 4 indicadores (protótipo): produtos cadastrados (variáveis · composições), abaixo do alerta,
  **valor em estoque pelo custo** (o MESMO total do Relatório de estoque — regra mestre de valor:
  prova dupla antes de subir) e inativos.
- Filtros (recolhíveis): tipo, categoria, unidade, imposto, marca, local do negócio, situação,
  "Não para venda"; e os de módulo, só para quem tem o módulo: "Modelo do dispositivo"
  (assistência técnica) e "WooCommerce habilitado". Chips "Filtrando por" + "Limpar tudo".
- Abas: **Todos os produtos** (com contagem) e **Relatório de estoque** (as colunas da lista Blade,
  linhas por página à escolha, lucro potencial da linha verde/vermelho,
  com totais), esta só com `stock_report.view`.
- Barra: busca (atalho `/`) com a **mesma regra da lista Blade**, no servidor e no catálogo inteiro
  (cada palavra em nome, SKU, SKU da variação, tipo, categoria, marca ou campo personalizado);
  Confortável/Compacto; **Colunas** (lembradas no navegador); **Cadastro rápido**.
- Tabela (`shared/DataTable`, densidade `grid`, colunas redimensionáveis pela borda do cabeçalho —
  menos Ação, Imagem e a última; a largura não é lembrada entre visitas): Ação, Imagem, Produto (+ 2ª linha: estoque
  gerenciado · não para venda · inativo · N variações), Locais, Preço de compra, Preço de venda,
  Estoque atual (vermelho abaixo do alerta), Tipo, Categoria (+ subcategoria), Marca, Imposto, SKU;
  opcionais IMEI/série e os campos personalizados que a empresa rotulou. Linha inativa apagada, linha
  abaixo do alerta com faixa vermelha. Ordenação e **paginação no servidor**, com as opções da lista
  Blade (25 a 1000 e todas) e o mesmo padrão (configuração da empresa).
- Menu **Ações** por produto — união do protótipo com a lista Blade, com as MESMAS permissões:
  Ver, Editar, Estoque inicial, Histórico de estoque, Duplicar, Gerar código de barras, Preços por
  grupo, Baixar anexo, Ativar/Desativar, Excluir; e Dados fiscais (abre o painel na aba Fiscal).
  Transferir entra com a requisição de transferência; "Usar em OS" saiu por decisão [F] de
  2026-10-11; "Comprar" saiu por decisão [F] de 2026-10-26 (a consulta não é a tela de compras,
  UC-PIDX-11).
- Seleção em massa: Edição em massa, Adicionar/Remover do local, Etiquetas, Desativar, Excluir e
  WooCommerce Sync (só com o módulo).
- Painel de detalhe (Sheet) com Dados · Variações/Composição · Estoque · Fiscal · Prateleira. Abre em
  Dados, ou na aba que a ação pediu ("Dados fiscais" → Fiscal).
- Destinos abrem com navegação de página inteira, como na lista Blade (o cadastro da ROTA LIVRE
  segue no Blade — US-PROD-029).
- Multi-tenant: `business_id` explícito em toda consulta (não há global scope em `App\Product`).
- Permission gate: `product.view` **ou** `product.create` (`ProductController@index`, 403).

> **Decidido por [F] em 2026-10-26 (comentários na âncora e conversa).** O protótipo
> (`produto-blade.jsx`, PR #9925) já mudou; a produção recebe cada item no PR que o leva, com os
> casos e os testes reescritos junto. O que já está na produção está marcado **Feito**.
> - **Quem usa:** o administrador (cadastro, preço, estoque, nota) e o vendedor de balcão, que procura
>   o produto e informa o preço ao cliente. Muitas vendas saem sem nota ([F]); quem decide é cada
>   venda, pelo processo "Venda Sem Nota" ou "Venda Com Nota" (`memory/requisitos/Sells/SPEC.md`), e
>   não o produto.
> - **Feito:** sai o "Comprar" do menu Ações e do rodapé do painel, porque a consulta não é a tela de
>   compras (UC-PIDX-11 reescrito, com teste).
> - O rodapé do painel não tem "Gerar OP": a ordem de produção começa na produção. A produção nunca
>   teve o botão; o protótipo deixou de ter.
> - **Imposto aparece por relevância, não por permissão:** imposto não é segredo, sai impresso na nota.
>   - O preço de venda mostrado é o que a venda cobra: o preço com o imposto do produto
>     (`sell_price_inc_tax`), com nota ou sem. É o número que o vendedor informa ao cliente, e é o
>     que a lista já mostra hoje.
>   - **Feito:** aba Dados com "Preço de venda" primeiro; se o produto tem imposto, também o preço
>     sem imposto e o imposto aplicável (com o tipo), com uma frase dizendo que o preço já inclui o
>     imposto (UC-PIDX-19).
>   - **Feito:** tabelas de Variações e Composição com uma coluna de preço quando sem e com imposto
>     são iguais; senão as duas, "sem imposto" e "com imposto" (UC-PIDX-19 e 20).
>   - **Feito:** aba Fiscal e "Dados fiscais (NCM/CFOP)" do menu só em empresa que emite nota (módulo
>     Fiscal no pacote, o mesmo teste do botão "Abrir no módulo Fiscal"); a aba começa dizendo se o
>     produto está pronto para nota (UC-PIDX-17 e 25).
>   - **Feito:** coluna e filtro Imposto da lista só se a empresa tem alíquota cadastrada; a coluna
>     nasce desligada e fica no menu Colunas; o CSV de "Exportar filtrados" segue a regra (UC-PIDX-34).
>     O "Baixar Excel" é o arquivo de reimportação da Blade e fica como está, por decisão à parte.
>
> Nada disso esconde da tela nova o que a lista Blade mostra, então não entra no Non-Goal "Função a
> menos que a lista Blade". Uma diferença pequena, de propósito: a janela "Ver" da Blade escreve
> "Imposto aplicável: Nenhum"; a tela nova omite a linha quando não há imposto, porque o preço
> mostrado já é o final. **Descartado no mesmo dia:** esconder o imposto por permissão
> (`fiscal.access`). Escondia de quem vende um dado que a nota imprime, e deixava a tela nova mostrar
> menos que a Blade.

---

## Non-Goals — Features (NÃO faz)

> ⚠️ Anti-alucinação. v3 autorizada por [W] em 2026-10-10 (via [F]).

- ❌ Corte silencioso do catálogo (lista limitada a N sem paginação)
- ❌ Busca ou filtro resolvidos só no navegador sobre uma página do catálogo
- ❌ Função a menos que a lista Blade (`product.index`) — o que ela faz, esta faz
- ❌ Cadastro/edição do produto dentro da lista (abre `create`/`edit`, Blade — US-PROD-029)
- ❌ Edição de preço inline na tabela
- ❌ Sub-views de BOM/Insumos/Tabelas (ficam em `/products/unificado`)
- ❌ Árvore de categorias (filtro é a lista plana da categoria, como no Blade)

---

## UX Targets

- Catálogo inteiro alcançável (ROTA LIVRE: milhares de produtos) — paginação e busca no servidor
- Busca acha pelo código da variação e pelo código escrito no nome
- 0 erros JS console
- Cabe em 1280px sem scroll horizontal **da página** (a tabela rola dentro dela, cabeçalho fixo)
- Mesmas fontes e tons do protótipo (bundle `cowork-produto-bundle.css`), medido com
  `design-diff.mjs` contra o protótipo, mesmo tema e largura

---

## UX Anti-patterns

- ❌ Cards no lugar da tabela (era o desenho errado — v2)
- ❌ Estoque vazio quando o produto controla estoque
- ❌ Preço ou custo enviado a quem não pode vê-los (gate no servidor, UC-PIDX-03)
- ❌ Botão de ação sem efeito para quem não tem a permissão
- ❌ Cor crua `bg-(green|red)-N`; `sessionStorage`

---

## Automation Hooks

> 🔧 **Reconciliação factual 2026-07-26** (agent `sdd-from-source`, Fase 2.6 — só fato, nenhuma
> intenção alterada). As 4 linhas abaixo afirmavam rotas e mecanismos que **não existem**; cada
> correção traz a evidência ao lado. Intenção (Goals/Non-Goals/Anti-hooks) segue sendo do [W].

- Endpoint `GET /products` — `ProductController@index` (`:64-374`) serve **três** consumidores no mesmo método: `request()->ajax()` → DataTables server-side (`:73-312`), header `X-Inertia` → `Produto/Index` (`:342-359`), nenhum dos dois → `view('product.index')` (`:361`).
  _(era `GET /produto` — rota inexistente; a rota real é `/products`, `routes/web.php:679 (verificado@e2c8397)` `Route::resource`.)_
- Props deferidas (`Inertia::defer`): `kpis` (`:349`) · `rows` (`:350`) · `categorias` (`:351`). **A lista React não pagina** — `buildProdutoIndexRows` corta em `limit(200)` (`:447`).
  _(era "retorna lista paginada" — não retorna; ver `UC-PIDX-01`.)_
- ~~Endpoint `GET /produto/{id}/sheet-data`~~ — **não existe.** Varredura contada em `routes/`+`app/`: `sheet-data` só existe para `/sells/{id}` (`routes/web.php:704 (verificado@e2c8397)`); **0** ocorrências para produto. O card navega pra ficha (`Index.tsx:423 (verificado@d4afe95)`), não abre drawer.
- Multi-tenant: `where('business_id', …)` **explícito** nos 3 builders (`:382`, `:427`, `:479`).
  _(era "global scope `business_id` em `App\Product`" — **não há global scope**: `addGlobalScope` = **0** ocorrências em `app/Product.php`. A contenção é manual e repetida; ver `UC-PIDX-04`.)_
- Permission gate: `can('product.view')` **OU** `can('product.create')` (`:66-68`, 403).

---

## Automation Anti-hooks

> ⚠️ v3 autorizada por [W] em 2026-10-10 (via [F]).

- ❌ Não dispara emails nem SMS
- ❌ Não roda jobs; não chama Brain B
- ❌ Não acessa produto de outro `business_id`
- ❌ Não cria endpoint de escrita novo nesta Page: as escritas são **só** as que a lista Blade já faz,
  pelos MESMOS endpoints e permissões (ativar, desativar, excluir, mudar local, estoque inicial,
  WooCommerce Sync)
- ❌ Não altera preço nem estoque pela tabela (estoque inicial só pelo fluxo existente de
  `OpeningStockController`)
- ❌ Não dispara `MfgRecipe` recompute; não persiste upload de imagem

---

## Métricas vivas (Pest GUARD)

> 🔧 **Reconciliação factual 2026-07-26:** o arquivo prometido abaixo (`IndexCharterTest.php`)
> **nunca existiu** (`ls tests/Feature/Produto/` — 21 arquivos, nenhum com esse nome). O que existe
> é `Wave2Index{Inertia,Baseline}Test.php`, que fazem **string-match no fonte**
> (`expect($source)->toContain(...)`) — passariam com o isolamento quebrado e o preço vazando — e
> que **não rodam na lane de PR** — a lane `Estoque · MySQL` usa allowlist explícita de arquivos e
> nenhum `Wave2Index*` está nela. ⚠️ Eles **rodam** no fullsuite nightly do CT 100 (`phpunit.xml`
> inclui `./tests/Feature` recursivamente + `scripts/tests/shards-plan.mjs` enumera
> `tests/Feature/Produto` como shard) — a redação anterior dizia "não rodam em lane nenhuma", o que
> era falso (LC-08: conclusão *system-scoped* tirada de varredura *file-scoped* em `.github/`).
> A lista de intenção fica **preservada** (é do [W]); marcado ao lado
> o que passou a ter defesa real.
>
> **Defesa real desde 2026-07-26** — [`tests/Feature/Produto/ProdutoIndexContratoTest.php`](../../../../tests/Feature/Produto/ProdutoIndexContratoTest.php)
> (na lane, failing-first): `UC-PIDX-01` alcance · `UC-PIDX-02` busca server-side · `UC-PIDX-03`
> preço/custo por permissão · `UC-PIDX-04` isolamento + KPIs · `UC-PIDX-05` GET não escreve ·
> `UC-PIDX-06` filtro de inativos. Contrato em [`Index.casos.md`](Index.casos.md).

```php
// ⚠️ ARQUIVO INEXISTENTE — lista de INTENÇÃO do [W], mantida como tal.
// tests/Feature/Produto/IndexCharterTest.php

it('reaches the whole catalog with server-side pagination (no silent cut)')
it('does not emit emails on render')
it('does not dispatch jobs')
it('does not mutate state on GET')
it('isolates products by business_id')
it('returns 404 for cross-tenant product access')
it('renders at 1280px without horizontal scroll')
it('finds a product by variation SKU and by a code written in the name')
it('lists the same products as the Blade list for the same filters')
it('uses localStorage prefix oimpresso.produto.* if any state persisted')
```

---

## Comparáveis canônicos (`mwart-comparative` V4)

- **Stripe Products listing** (cards visuais densos)
- **Linear projects grid** (card pattern)
- **Excluir:** Shopify (overhead), Vendor home pages

---

## Refs

- Material visual: [`prototipo-ui/cowork/Felipe/produto-blade.jsx`](../../../../prototipo-ui/cowork/Felipe/produto-blade.jsx), vista `lista` (`TelaLista`): o mesmo que o frontmatter declara em `related_prototype`
  _(até 2026-10-09 o frontmatter declarava `related_prototype: n/a` + `bundle_source: produtos-page.jsx`, com o aviso "PENDURADO NA TELA ERRADA" de 2026-09-09: `produtos-page.jsx` é o protótipo de `/products/unificado`. Corrigido em 2026-10-09 [F+C]. A âncora é a vista `lista` (`TelaLista`), não a `TelaCatalogo` de cards do mesmo arquivo. A cópia `cowork/Felipe/` é a do projeto Cowork do [F] (`2e7d3640`), conferida byte a byte por `get_file` nesta data. A cópia `cowork/Wagner/produto-blade.jsx` difere: a `TelaLista` dela usa os blocos antigos, e o mapa de rotas dela liga `Produto/Index` à vista `catalogo`.)_
  _(a linha anterior apontava `ui_kits/cowork-2026-05-09/prod-page.jsx`; caminho **inexistente** — `ls` → No such file. Corrigido 2026-07-26.)_
- Contrato de casos: [`Index.casos.md`](Index.casos.md) — `UC-PIDX-01..06` (`CU-PROD-15` do SDD)
- Canon visual: [ADR ui/0012](../../../../memory/requisitos/_DesignSystem/adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md)
- Charter relacionado: [`/products/unificado`](Unificado/Index.charter.md) — versão densa
- [ADR 0110 — Cockpit Pattern V2](../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0093 — Multi-tenant Tier 0](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [LICOES_F3_FINANCEIRO_REJEITADO.md](../../../../memory/reference/prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) — pré-flight obrigatório
- Backend: `app/Http/Controllers/ProductController.php` (UPOS canon)

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-10-10 | [F+C] | **v3 — tela certa.** Âncora = vista `lista` de `produto-blade.jsx` (cópia `cowork/Felipe/`). Mission, Goals, Non-Goals, UX e Anti-hooks reescritos para a lista do protótipo + paridade total com a lista Blade, autorizado por [W] via WhatsApp (repassado pelo [F]). Os Non-Goals da v2 (sem tabela, sem densidade, sem ações em massa, sem filtros avançados, read-only) eram do catálogo de cards, o desenho errado. `Index.casos.md` reconcilia na etapa do contrato. |
| 2026-05-09 | [CL] | Charter draft criado em batch. Path `Pages/Produto/Index.tsx` (flat). **Decisão pendente Wagner:** mantém Page simples + Page unificada (`Produto/Unificado/`) como duas opções, OU consolida em uma só? Material `prod-page.jsx` é mais "balcão rápido", `produto-app.jsx` é "admin completo". **Aprovação pendente** em Non-Goals + Anti-hooks pra `status: live`. |

| 2026-07-26 | [CC] | **Reconciliação factual** (agent `sdd-from-source`, [ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md) Fase 2.6) — corrigidos 5 FATOS verificáveis, com a evidência ao lado de cada um: rota `/produto`→`/products` · `sheet-data` inexistente · `product.view_own` inexistente · "global scope" inexistente (`addGlobalScope`=0) · material visual em caminho podre. §Pest GUARD anotado (o `IndexCharterTest.php` nunca existiu; a defesa real agora é `ProdutoIndexContratoTest`). **Nada de intenção foi tocado** — Goals/Non-Goals/Anti-hooks seguem do [W]. Trio fechado com [`Index.casos.md`](Index.casos.md). |

> _Promovido draft→live em 2026-07-12 por `charter-promote-signal.mjs` — sinal: route-hits:16._
>
> ⚠️ **Divergência aberta (decisão [W], não reconciliada):** este charter está `status: live`, mas o
> [SDD §1.1](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md) e o
> [SPEC](../../../../memory/requisitos/Produto/SPEC.md) afirmam *"as 8 telas React existem, nenhuma é
> `live`"*, e a US-PROD-023 ainda pede "finalizar + promover". **Hipótese** (não afirmo): `/products`
> serve os dois branches (Blade e Inertia) na **mesma rota**, então `route-hits:16` pode ter medido
> tráfego do **Blade** — se for isso, afeta as 8 telas do módulo e qualquer tela em branch dual
> MWART. Verificar antes de tratar o `live` como fato. Registrado em `Index.casos.md` §Divergências D-4.
