---
id: resources-js-pages-produto-unificado-index-charter
page: /products/unificado
component: resources/js/Pages/Produto/Unificado/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela — referência viva /contacts = Pages/Cliente/Index.tsx)
owner: wagner
status: draft
last_validated: "2026-05-09"
parent_module: Produto
related_us: [US-PROD-023]
related_adrs: [110, 107, 93, 94]
tier: A
charter_version: 1
---

# Page Charter — /products/unificado (DRAFT)

> **Status:** draft criado em batch 2026-05-09 a partir de `produto-app.jsx` (60 KB — material mais robusto do canon). Wagner aprova **Non-Goals + Automation Anti-hooks** ANTES de virar `status: live`.
>
> ⚠️ **Backend canon:** `app/Http/Controllers/ProductController.php` (UPOS herdado). Produto = `App\Product` + `App\Variation` + `App\Brands` + `App\Category` direto em `app/`, **NÃO** em `Modules\Produto\` ([LICOES_F3_FINANCEIRO_REJEITADO.md](../../../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) AP-1). BOM = `Modules\Manufacturing\Entities\MfgRecipe`. Tabelas de preço = `App\SellingPriceGroup`. Histórico = `App\TransactionSellLine`.

---

## Mission

**Consulta de produtos.** Responder três perguntas em um olhar — *existe?*, *tem?*, *quanto custa
pro cliente?* — e levar ao cadastro completo quando a resposta exige ação. Índice do catálogo em
PARIDADE com a Consulta de Clientes (`/contacts` = `Pages/Cliente/Index.tsx`), que é a golden
master do padrão de índice.

As outras visões do catálogo (Categorias · Insumos·BOM · Tabelas de preço · Histórico de uso)
continuam nesta mesma rota, via `?tela=`, mas **desde 2026-08-24 não têm entrada nesta tela** — o
grupo "Outras visões" saiu do menu `⋯` (handoff V6 §15.1 nº 11 · aceite §16 nº 4). A barra de abas
é do recorte por **tipo do item**.

> ⚠️ **Exceção DECLARADA à paridade acima, para ninguém "consertar" de volta.** A golden master
> `Pages/Cliente/Index.tsx` **mantém** navegação no seu menu `⋯` — a seção "Configuração" com
> *Grupos de clientes* (`/customer-group`, L928-940). Esta tela **não** mantém: o handoff §4.1.1
> declara o menu como lista FECHADA de apresentação + dados, e o §16 nº 4 transforma "zero link que
> navegue para outra tela" em critério de aceite. Decisão humana de 2026-08-24, tomada ciente de
> que **Insumos·BOM e Histórico de uso ficam sem nenhum acesso** (a sidebar cobre só Categorias e
> Grupo de preços de venda, nas telas legadas). Dar-lhes acesso é trabalho da **sidebar do módulo**
> e decisão de fora deste handoff. Achado levantado pelo `pr-critic` na [PR #6204](https://github.com/wagnerra23/oimpresso.com/pull/6204); a divergência é
> real e fica registrada aqui em vez de o charter seguir prometendo paridade que não existe.

---

## Goals — Features (faz)

> Layout: pacote **"Consulta de Produtos" de 2026-08-17** (decisão [M] 2026-08-18 — ver Histórico).
> Toda diferença dele está declarada aqui — qualquer outra exige aprovação antes de implementar.
>
> ⚠️ Este bloco está sendo migrado por ondas. Enquanto a migração corre, **cada onda corrige no
> seu próprio PR** os itens desta página que ela contradiz — é a regra de precedência
> (`proibicoes.md`), não descuido. Itens ainda descrevendo o pacote de 18/08: a faixa de KPI em
> uma linha (onda 2), as colunas e seus rótulos (onda 3), a ausência de rodapé (onda 6) e a
> largura do drawer (onda 7).

- **Árvore:** AppShellV2 → cabeçalho (título + `N cadastrados` + ações) → barra de abas →
  KPI-filtros → toolbar em uma linha (filtros → contagem → busca) → cartão da tabela →
  drawer de detalhe
- **Abas por TIPO do item:** Todos · Produtos · Serviços · Matéria-prima · Kits · Inativos. As
  abas de tipo contam **só ativos**; `Inativos` é o complemento; `Todos` é o cadastro inteiro.
  Trocar de aba muda tabela, contagem, KPIs **e zera a seleção** — não é filtro decorativo
- **Tipo é DERIVADO** de colunas que já existem, nesta ordem: `type = 'combo'` → kit ·
  `not_for_selling = 1` → matéria-prima · `enable_stock = 0` → serviço · resto → produto
- **Até quatro KPI-filtros**, contados sobre a aba ativa e **clicáveis** (toggle): Abaixo do
  mínimo · Sem saldo · Sem venda 90d\* · Margem baixa\*. Os dois com \* são recortes de gestão
  e só existem pra quem vê custo — o gate vale no servidor, não só na tela
- **Toolbar em UMA linha** (pacote 17/08 · `.fbar`): gatilhos de filtro → contagem de registros
  → busca ocupando o resto da linha. Abaixo de 780px de largura disponível os gatilhos
  opcionais somem e voltam pelo **"Mais filtros"**; Categoria e Tipo nunca somem
- **Filtros** cujo gatilho JÁ É o estado (`Categoria: Insumos`), sem linha de chips:
  Categoria · Tipo · Unidade · **Marca** · **Disponível** · Margem, mais um **"Limpar"** que só
  existe quando há recorte. A busca cobre descrição, código, referência e categoria; `/` foca o
  campo e o `kbd` na barra anuncia o atalho
- **Gatilho de "Ordem"** ao lado dos filtros, mostrando a ordem corrente (`Código ↑`) antes de
  qualquer clique. O clique no cabeçalho da coluna escreve no mesmo estado
- **Busca + aba + KPI + filtros compostos num único `where` server-side** — os quatro recortes
  se combinam
- **Colunas por AUTORIZAÇÃO, não por CSS** (`_components/Colunas.tsx`): vendedor vê
  `seleção · Código · Produto · Disponível · Preço de venda · Ações`; perfil autorizado ganha
  `Tipo` (só quando a aba tem mais de um), `Custo` e `Margem`. Coluna que não aparece é coluna
  que não foi montada. O `min-width` da tabela é **calculado** a partir das colunas visíveis —
  esconder coluna elimina a rolagem horizontal de verdade
- **Esconder coluna é preferência; não montar é autorização.** O menu ⋯ oferece Tipo, Custo,
  Preço e Margem — mas só as que a permissão já montou
- **Disponível com quatro rótulos** e três estados de dado: `null` = Não estocável ·
  `0` = Sem saldo · `≤ mínimo` = Abaixo do mínimo · resto = Disponível. O selo traz rótulo e
  valor **com unidade** na mesma linha ("Disponível 96 m²"). Ordena por rank semântico
- **Seleção em lote** que ATRAVESSA páginas (a caixa do cabeçalho soma a página) e é zerada por
  qualquer mudança de recorte. A barra flutuante declara quantos estão fora da página visível.
  Única ação: **Inativar**, no `POST /products/mass-deactivate` real, montada só com
  `product.update`
- **Teclado:** `/` busca · `⌘K`/`Ctrl+K` paleta (Recentes · Abas · Recortes · Ações) · `↑` `↓`
  movem a linha ativa e viram a página na borda da fatia · `↵` abre · `esc` solta
- **Rodapé de paginação** (10/25/50/100 · «‹ `N / M` ›» · "Mostrando X–Y de Z"), com `page`,
  `per_page` e `ORDER BY` resolvidos no **servidor** e total autoritativo vindo de `totalDaAba`.
  A rolagem vertical é da PÁGINA — a altura fixa (460) e o teto de 500 linhas que a
  substituíam saíram junto
- **Drawer de detalhe** (420px; 480 com composição) na ordem **disponibilidade primeiro,
  cadastro por último**: alertas → identidade → Disponível → Preço e margem (preço → margem →
  custo) → Estoque → Giro → Identificação → Observações. Rodapé com esteira `‹ ›` ("2 de 13") e
  três saídas: Abrir cadastro · Formar preço\* · Usar em orçamento. Custo e margem seguem o
  mesmo gate da tabela, e sem permissão o painel **diz** que o campo é restrito
- **Totais do recorte no rodapé da tabela** — "Valor em estoque (recorte, físico)" e "Repor até
  o mínimo", do recorte inteiro (não da página) e gateados por custo
- **Sub-telas secundárias** preservadas em `?tela=categorias|insumos|tabelas|historico`, no menu
  de ações do cabeçalho, com os mesmos gates de permissão de sempre
- **Multi-tenant:** as três leituras (linhas · KPIs · contagem das abas) saem da **mesma
  subconsulta**, com `business_id` declarado — contador que discorda da lista destrói a confiança
- **Permission gate:** `product.view` **ou** `product.create` na tela; `view_purchase_price` pra
  custo/margem (coluna, drawer **e** KPI); `access_default_selling_price` pra preço e tabelas

---

## Non-Goals — Features (NÃO faz)

> ⚠️ Anti-alucinação. Wagner aprova esta lista.

- ❌ CRUD inline (criar/editar via rotas dedicadas Blade `/products/create`, `/products/{id}/edit`)
- ❌ Bulk actions (deletar/ativar múltiplos) — backlog
- ❌ Stock management (entradas/saídas — vai pra `/stocks` Blade legacy)
- ❌ Importar CSV (rota Blade `/products/import`)
- ❌ Print etiqueta de barras (rota Blade `/products/{id}/print-label`)
- ❌ Variações inline no drawer (vai pra `/products/{id}/variations` Blade)
- ❌ Multiplicador `App\SellingPriceGroup` editável aqui — **decisão schema pendente**: (a) adicionar coluna `multiplier` em `selling_price_groups`, ou (b) calcular via `VariationGroupPrice` e dropar conceito multiplicador; ADR `arq/NNNN-selling-price-multiplier.md` antes de F3
- ❌ Auto-aplicar margem mínima em produto novo (vai vir do template do business)
- ❌ Recalcular custo médio em tempo real ao abrir drawer (usa `default_purchase_price` cached)
- ❌ Forecast de demanda baseado em histórico (escopo Modules/Inventory futuro)
- ❌ Preview de imagem do produto no drawer (UPOS guarda em `media` table — feature backlog)
- ❌ Formação de preço com markup composto aqui (tela própria — handoff §8)
- ❌ Ajuste/movimentação de estoque a partir desta tela (handoff §8)
- ❌ Mobile/tablet: a plataforma alvo declarada é **cockpit desktop** (handoff §8)
- ❌ ~~Rodapé de paginação~~ — **revogado em 2026-08-19** pelo pacote V2 §4.8. Era D-06 do SPEC
  v1.0, resolvido na época com teto de 500 + rolagem interna. O teto não dava caminho pra 501ª
  linha sem o operador inventar um filtro. Virou objetivo; ver a linha do rodapé em Goals
- ❌ Virtualização de linhas — a paginação server-side resolve o volume por ora; virtualizar
  volta à mesa se `porPagina=100` ficar lento com o catálogo real
- ❌ Trigger sync com fornecedor externo (cron separado)
- ❌ **Saldo vendável × físico, custódia e baixas** (handoff 21/08 §6) — exige `natureza` no
  cadastro de LOCAL (`venda`/`bloqueado`), que não existe. Sem ela "disponível" soma o que não
  vende, e derivar a natureza pelo nome do local seria adivinhar
- ❌ **Matriz da grade, faixas de preço por quantidade e alçada de desconto** (§7) — o cadastro
  não guarda saldo/código/preço por combinação, nem degrau de quantidade, nem limite por
  colaborador. Aparecem quando o modelo de dados existir, não antes
- ❌ **Reposição** (fornecedor, última compra, custo na última compra · §5 item 6) — o
  UltimatePOS não guarda fornecedor no produto, só por compra
- ❌ **"Exportar seleção" e "Gerar etiquetas" em lote** (§4.4) — `/products/download-excel`
  ignora seleção e `/labels/show` só aceita UM `product_id`. Entram quando houver endpoint que
  aceite a seleção; até lá, botão que não faz o que promete custa mais que botão ausente

---

## UX Targets

- p95 first-paint < 1500ms (aba Produtos com 100 itens)
- 0 erros JS console
- Cabe em monitor 1280px (Larissa balcão)
- Troca de aba / KPI-filtro / filtro `<200ms` (Inertia partial reload — `only:[produtos,kpis,totalDaAba]`)
- Drawer abre instantâneo (a linha já está carregada — nenhuma request ao abrir)
- Busca debounced 350ms, resolvida no servidor
- Tipografia canon ADR 0110: h1 22-24px, KPI value 28px, table row 13px
- Cores semânticas: emerald (ativo/popular), amber (warning baixo estoque), rose (inativo/sem giro), stone (neutro)

---

## UX Anti-patterns

- ❌ 5 telas separadas em URLs diferentes (canon = sub-views state-driven via `?tela=`)
- ❌ Modal/Dialog pra detalhe produto (canon = `<Sheet>` lateral)
- ❌ Cor crua `bg-(red|green|orange)-N`
- ❌ **Monograma/inicial no lugar da foto do produto** — revogado em 2026-08-21 pelo §3.2. No DS
  o `Avatar` é canon de PESSOA: iniciais e cor por hash existem porque gente tem nome próprio.
  Produto tem foto; sem ela, o lugar é um espaço reservado tracejado que DECLARA a ausência
- ❌ Chip preenchido como gatilho de filtro em repouso (canon = variante leve, aparência de campo)
- ❌ Esconder coluna por CSS (`display:none`, `hidden`) — coluna é **montada ou não montada**
- ❌ Redeclarar o piso de margem no frontend (ele vem do servidor em `pisoMargem`)
- ❌ "Consertar" a faixa de 6 KPIs com `auto-fit` no desktop declarado — quebrar em duas linhas
  diverge da referência e foi reprovado

> **Corrigidos em 2026-08-18 (regra de precedência — `proibicoes.md`):** dois anti-padrões desta
> lista contradiziam a golden master que o próprio charter elegeu em 2026-08-13, então o perdedor
> foi corrigido no mesmo PR.
> • *"`font-bold` em h1"* — a `/contacts` usa `text-[22px] font-bold` com decisão registrada no
>   código (*"22px font-weight 700 — peso espelhando /sells canon"*). Obedecer ao charter faria a
>   tela divergir da referência.
> • *"KPI custom inline (canon `shared/KpiCard`)"* — a golden master **não** usa `KpiCard` na
>   faixa: usa a `KpiStripClickable` local, porque KPI-filtro clicável não é KPI de leitura. O
>   equivalente aqui é `_components/KpiFiltros.tsx`.
- ❌ `sessionStorage` (canon = `localStorage` prefix `oimpresso.produto.*`)

---

## Automation Hooks

- Endpoint `GET /products/unificado?tela=<sub>` — `ProdutoUnificadoController::index()` agrega:
  - `Product::where('business_id', $bid)->active()->count()` (KPI catálogo ativo)
  - `TransactionSellLine` join `transactions` últimos 30d sum quantity (KPI saídas)
  - Sub-view específica conforme `tela`
- Endpoint `GET /produto/{id}/sheet-data` — drawer detail com Variation default + BOM (`MfgRecipe`) + 5 últimas vendas
- Multi-tenant: `App\Product`, `App\Variation`, `App\Brands`, `App\SellingPriceGroup` todos com `business_id` (UPOS canon)
- Permission middleware no `__construct` (`can:product.view`)
- Cache: KPI agregations cacheadas por job diário (chave `produto:kpis:{business_id}`)

---

## Automation Anti-hooks

> ⚠️ Wagner aprova esta lista.

- ❌ Não dispara emails ao abrir
- ❌ Não dispara webhook fornecedor
- ❌ Não escreve no banco no render (read-only puro)
- ❌ Não roda recálculo custo médio na request (cron diário faz)
- ❌ Não chama Brain B/Sonnet
- ❌ Não acessa produto de outro `business_id` (multi-tenant Tier 0)
- ❌ Não dispara `MfgRecipe` recompute em sub-view "Insumos"
- ❌ Não cria variação automática ao abrir drawer
- ❌ Não persiste imagem upload nesta request (upload vai por rota dedicada)

---

## Métricas vivas (Pest GUARD — a escrever em F1.5)

```php
// tests/Feature/Produto/UnificadoCharterTest.php

it('renders under 1500ms p95 with 100 products')
it('switches sub-view via querystring without full reload')
it('does not emit emails on render')
it('does not dispatch jobs on render')
it('does not mutate state on GET')
it('isolates products by business_id across all 5 sub-views')
it('returns 404 for cross-tenant product access via sheet-data')
it('renders at 1280px without horizontal scroll')
it('persists densidade preference in localStorage')
it('uses localStorage prefix oimpresso.produto.* (never sessionStorage)')
it('does not call MfgRecipe recompute on insumos sub-view')
it('does not access App\\Product without ->where(business_id)')
```

---

## Comparáveis canônicos (`mwart-comparative` V4)

- **Linear** (lista densa + atalhos) — referência principal pra Produtos sub-view
- **Stripe Products** (catálogo com sub-views unificadas) — referência pra arquitetura sub-tela
- **Notion database** (apenas pra view toggle table/grid — visual rejeitado pelo resto)
- **Excluir:** Shopify Admin (overhead e-commerce), POS-Larissa-style (vai pra `/sale-pos/create` separado)

---

## Refs

- Material visual: `ui_kits/cowork-2026-05-09/produto-app.jsx` (60 KB) + `produto-data.jsx` + `produto-icons.jsx` + `Produto Unificado.html`
- Screenshot evidência: `screenshot-06-produto.png` (95 KB)
- Canon visual: [ADR ui/0012](../../../../../memory/requisitos/_DesignSystem/adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md)
- [ADR 0110 — Cockpit Pattern V2](../../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0107 — Visual gate F1.5](../../../../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0093 — Multi-tenant Tier 0](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [LICOES_F3_FINANCEIRO_REJEITADO.md](../../../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) — pré-flight obrigatório antes de F3 (Models reais UPOS, NÃO inventar `Modules\Produto`)
- Backend candidate: `prototipo-ui-patch/app/Http/Controllers/ProdutoUnificadoController.php` no zip Cowork — referência **com TODOs** (NÃO copiar literal — investigou Models reais mas sem `__construct` middleware nem permissions)

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-08-25 | [W] via [M+C] | **"Design system SEMPRE ganha" — regra dada por [W], e duas decisões dele mesmo caem por ela.** **(1) Texto do selo** volta ao TOM CHEIO do `StatusBadge` (era o par `-fg`, escolha minha por contraste). Custo medido e registrado, não escondido — sobre o fundo do tom a 16%, em branco: Disponível **2,86:1**, Abaixo do mínimo **2,36:1**, Sem saldo **3,75:1**, contra o mínimo de **4,5:1** do WCAG AA pra texto pequeno. Os três REPROVAM: é defeito do componente do DS, que vale pra toda tela que o use, não desta. ⚠️ Corrijo um número meu que circulou em commit, PR, comentário e documento: eu vinha dizendo "1,9:1 vs 3,1:1" — era estimativa de cabeça e estava ERRADA; os valores acima são calculados (oklch→sRGB→luminância→razão WCAG). **(2) "Não estocável"** vai ao `text-foreground` do tom `outline` (transparent + border + FOREGROUND, sem ponto). O cinza que estava aqui era ratificação [W] de **2026-08-18** — mas naquela data a disputa era cinza vs VERMELHO, e o cinza ganhou do vermelho, não do preto. [W] 2026-08-25: *"aqui é o design system quem manda. Pode desfazer"*. A entrada de 18/08 fica como está: era verdade na data. **O que NÃO foi copiado ao pé da letra, e por regra do projeto:** o `fresc-cold` é o ÚNICO dos três tons que não usa token — traz `fg` LITERAL `oklch(0.74 0.14 18)`, valor de tema ESCURO vazado no claro, que sobre este fundo dá **1,94:1** (invisível) e viola o §6 do patch + a AP1 (zero cor crua na tela). Usei `text-destructive`, o MESMO padrão dos dois irmãos (`var(--color-<tom>)`). O literal é a anomalia do bundle, não a regra. **Achado pro DS, não pra esta tela:** (a) `fresc-cold` precisa de `fg` de tema claro; (b) os três tons reprovam WCAG AA em texto pequeno. |
| 2026-08-24 | [M+C] | **Pacote V6 + patch de cor — fecha o §15.1, e o DS ganha do patch onde os dois discordam.** O V6 é o V5 com UMA diferença (o resto do zip é byte-idêntico): a §15 reescrita, separando as 6 divergências abertas das fechadas. Conferidas **na tela em produção antes de tocar código**, e **duas não confirmaram** — o §15 mediu o deploy anterior ao #6171: a faixa já tinha 4 KPIs com a coluna Margem ativa (#12) e a placa já era tintada, não branca (#2). **Aplicadas: (6)** `MoreHorizontal` no ⋯ do cabeçalho — o da linha já era horizontal. **(11)** grupo "Outras visões" removido; as 4 sub-telas seguem por `?tela=` mas **perdem entrada na tela** — exceção à paridade registrada na Mission, achado do `pr-critic`. **NÃO aplicado: (8)** `+N reservado` exige natureza do local (`venda`/`bloqueado`), que não existe no cadastro do UltimatePOS; o `N locais` fica **onde está** (§3.2 e §4.3 se contradizem, e §4.3 é o que o código implementa). **(5)** facetas Unidade e Margem ratificadas. **⚠️ DOIS ERROS MEUS, corrigidos dentro da própria PR — ambos por INFERIR em vez de medir o componente, e ficam registrados porque a próxima sessão pode repetir:** **(a) A tela virou AZUL.** Medi `--accent: oklch(0.55 0.15 220)` em prod e concluí que era "a cor da empresa". Não é: `AppShellV2.tsx:384-408` reescreve `--accent`/`--accent-2`/`--accent-soft` a partir de `accentHue`, um **seletor de matiz no `localStorage`** (`LS.TW_HUE`). Amarrar a faixa de abas nele faz a preferência de UM navegador mandar no DS. Três fontes diziam o contrário e eu passei por cima: README §10 ("Acento roxo, hue 295"), o bundle (`--accent: oklch(0.55 0.15 295)`) e a própria **ADR 0401** do pacote ("o azul/ciano da aba ativa NÃO EXISTE na paleta do DS"). Fonte do roxo passa a ser **`--color-primary`**, que nenhum código reescreve. **(b) O selo deixou de ser pílula.** Afirmei "o DS usa `--radius-md` pra selo, 9 usos contra zero de pílula" — contagem sobre o bundle INTEIRO, não sobre o componente. O protótipo não desenha o selo: chama `DS.StatusBadge`, cuja raiz é `borderRadius: c.mono ? var(--radius-sm) : 9999` — **pílula redonda** com rótulo de texto. Revertido. **O que o DS respondeu quando enfim foi medido**, e que desmente o patch: `fresc-hot/warm/cold` usam **bg 16% · border 30% · fg no tom**, e `--fs-2` é **11.5px** — enquanto o patch §2 pede 6%/22%/12px AFIRMANDO ser "a mesma receita do guia do DS para Alert e StatusBadge". [W] escolheu o DS. **Receita final, DUAS e não uma:** selo em **16/30** (é `StatusBadge`, o DS manda); **placa do KPI em 6/22** (não é `StatusBadge`, não há componente do DS que a contradiga, o patch fica sem oponente medido); abas e linha selecionada em **`--color-primary`** a 10% e 7%. **Desvio ÚNICO e declarado:** o texto do selo usa `-fg` e não o tom cheio — `--color-warning` é oklch(0.70 0.13 75) e sobre fundo a 16% dá ~1,9:1 de contraste, ilegível em 11.5px; o par `-fg` do próprio DS sobe pra ~3,1:1. O `fg` do `fresc-cold` no bundle ainda é um LITERAL de tema escuro, que violaria o §6 do patch. **Mantido:** "Não estocável" sem fundo e sem ponto (o protótipo mapeia pro tom `outline` do DS: transparent + border, sem dot); card do KPI neutro com anel `primary` no selecionado (§1 do patch diz `primary` explicitamente). **A ADR 0401 fica resolvida pelo lado do sistema:** os seis literais crus saem de `cockpit.css` **num lugar só**, como a nota de lá previa, e o bloco `[data-theme="dark"]` deles **morre — a ausência é a correção**, porque todo token de origem já tem par de tema. |
| 2026-08-24 | [M+C] | **Pacote V3 (24/08) — fecha as divergências §15 que o próprio handoff mediu contra produção.** O V3 NÃO traz protótipo novo (o `.dc.html` é byte-idêntico ao V2): o que ele traz é o **§10.1 normativo** (mapa Lucide + tons) e a lista de 10 divergências. Aplicadas aqui as que ainda valiam depois do #6171: **(3) trilho da linha** — barra vermelha de 3px na borda esquerda, cobrindo os TRÊS motivos de ação (sem saldo · abaixo do mínimo · margem sob o piso); substitui o lavado `bg-destructive-soft/30`, que só marcava o zerado e a 30% de opacidade não sobrevivia ao olhar de relance. **(4) marcador de grade derivado** — `4 de 6 com saldo` (vermelho quando há furo) no lugar do resumo de atributo; em produção a linha imprimia `Tamnha p-m-g (4)`, o rótulo do cadastro com erro de digitação do tenant. Backend: 4ª consulta `whereIn` da revelação progressiva (`gradeComSaldo`), com **duas** cláusulas de tenant — `products.business_id` e `business_locations.business_id`, porque a linha de saldo pendura num LOCAL. **(7) chip de observação com texto** — era ícone mudo; sem o texto o balcão abria o painel item a item pra descobrir que o item é sob encomenda, que é o custo que o marcador existia pra evitar. **(1) `Ban` no KPI "Sem saldo"** — `CircleSlash` era aproximação; §10.1 é normativo. **RECONCILIA a decisão (c) de 2026-08-19:** o resumo `Cor (4) · Tamanho (3)` deixa de ser o marcador **da linha** e passa a viver **só no painel**, onde o nome do eixo é o assunto — `resumoVariacoes` continua exportado e usado lá. **NÃO aplicado, e por quê:** (8) `+N reservado` exige **natureza do local** (`venda`/`bloqueado`), que não existe no cadastro do UltimatePOS — sem ela o número seria adivinhado; `N locais` já existia desde a onda 3. O "vermelho quando a observação é crítica" (§3.2) esbarra na mesma falta: criticidade não é campo. As divergências **(2) tons do KPI** e **(1) ícones** já tinham sido fechadas pelo #6171, mergeado ~1h antes de o zip V3 ser gerado — o §15 mediu o deploy anterior. **(5) (6) (9) (10)** o próprio handoff declara corretas; nada a fazer. UC novo: **UC-PUNI-17** (`ProdutoUnificadoGradeContratoTest`), na lane Estoque · MySQL, com o cross-tenant do saldo por combinação. |
| 2026-08-19 | [C] | **Pacote V2 — onda 3: revelação progressiva.** A lista continua enxuta e o detalhe aparece só quando a pessoa demonstra interesse (V2 §4.9). Entram: **saldo por local** (pilula ganha segunda linha “N locais” que abre o saldo por local, com alerta no caso misto — §4.6) · **observação do produto** (ícone de recado ao lado do nome, só nos itens que têm nota — §4.7) · **terceira linha de variações** na célula Produto (§3.2) · **três seções novas no drawer** (Por local, Variações, Observações — §5). Os três popovers usam um componente só (`_components/PopoverAncorado.tsx`): hover é atalho, clique FIXA, Esc e clique-fora fecham, `position: fixed` por portal no `body` (o wrapper `overflow-x` da tabela clipa overlay absoluto) e flip pra cima quando faltam 220px até o fim da janela. **Backend:** três consultas `whereIn` sobre os ids DA PÁGINA — não é N+1, e agregar dentro da subconsulta do catálogo faria o saldo total contar a mesma linha uma vez por local. **Decisões declaradas:** (a) **o “A PARTIR DE” (faixa de preço por quantidade) NÃO foi implementado** — o UltimatePOS tem preço por **grupo de cliente** (`variation_group_prices`), não por quantidade comprada, e o próprio handoff §9 proíbe derivar a faixa do preço em produção; a decisão de schema está em [proposta 2026-08-19](../../../../../memory/decisions/proposals/2026-08-19-faixa-de-preco-por-quantidade.md). (b) **badges “Sob encomenda”/“Exige aprovação” não são servidos** — não existem no cadastro; no protótipo são campo do dado de mentira, e deduzi-los do texto seria adivinhação exibida como fato. (c) **o resumo de variação sai `Cor (4) · Tamanho (3)`**, não `4 cores · 3 tamanhos`: o nome do atributo é texto livre do tenant e pluralizar o que o cliente digitou daria “4 Cors”. UCs novos: **UC-PUNI-12..14**, na lane Estoque · MySQL, com o cross-tenant do saldo por local (`UC-PUNI-12B`) escopado por `business_locations.business_id`. |
| 2026-08-21 | [M+C] | **Pacote "2 - PROTÓTIPO OFICIAL - PRODUTO UNIFICADO V2" (21/08, após 27 ondas) — substitui integralmente o de 17-19/08.** Aplicado em três ondas nesta PR. **(1) Vocabulário e faixa de KPI:** "Em estoque/Estoque baixo/Sem estoque" viram **Disponível/Abaixo do mínimo/Sem saldo** (§4.3) — o rótulo antigo falava do depósito, o novo fala do que dá pra prometer ao cliente; selo com rótulo + valor + unidade na mesma linha; sai o KPI **"Ativos"** (§4.1 — contava a própria lista) e "Sem venda Nd"/"Margem baixa" passam a ser gateados por custo **no servidor**; sai a **linha de chips** (§4.2 — com o gatilho imprimindo o valor no próprio rótulo, o chip virou o mesmo texto duas vezes com dois "×" pro mesmo filtro), entra um **"Limpar"**; entra o gatilho **"Ordem"** e o `kbd` "/"; **miniatura 30px** substitui o monograma colorido (no DS o `Avatar` é canon de PESSOA — produto tem foto, e sem foto o lugar declara que falta imagem), nome em caixa normal, **código que copia**, **um** gatilho de ação por linha, larguras do §3.1 e `min-width` **calculado**; densidade e seletor de colunas no menu ⋯, persistidos em `localStorage` (`oi.produtos.prefs.v1`). **(2) Painel:** ordem invertida pra **disponibilidade primeiro, cadastro por último** (§5), preço → margem → custo, esteira `‹ ›`, três saídas no rodapé, código/referência copiáveis; **totais do recorte** no rodapé da tabela (§4.6), `defer` e gateados. **(3) Seleção em lote** (§4.4) que atravessa páginas e declara "N fora desta página", zerada por mudança de recorte (§4.2); **teclado** `↑ ↓ ↵ esc` virando página na borda, e **paleta ⌘K** com Recentes (§4.5). **Decisões declaradas:** (a) das três ações em lote do §4.4, só **Inativar** foi montada — nesta base `/products/download-excel` ignora seleção e `/labels/show` só aceita UM `product_id`; no protótipo as duas respondem com aviso e não fazem nada, o que é correto num protótipo e seria mentira em produção. (b) O recorte (aba/KPI/filtro/ordem/página) continua na **URL**, não em `localStorage` como o §4.7 pede: colar o endereço tem que abrir a mesma lista do outro lado; só a **apresentação** (densidade, colunas, recentes) foi pro storage. (c) **Fora do escopo por falta de dado no cadastro**, não por decisão de design: natureza do local (vendável × bloqueado), custódia de cliente, baixas com janela, matriz da grade, faixas de preço por quantidade, alçada de desconto, Reposição (fornecedor/última compra) e fotos de produto — §5, §6 e §7 do handoff. |
| 2026-08-19 | [C] | **Pacote V2 — onda 2: paginação server-side.** Rodapé próprio (10/25/50/100 · «‹ `N / M` ›» · "Mostrando X–Y de Z"), com `page`, `per_page` e `ORDER BY` resolvidos no **servidor**, lista branca de 7 colunas de ordenação e total autoritativo vindo de `totalDaAba`. **Altura fixa 460 e teto de 500 linhas removidos** — a rolagem vertical volta a ser da página (V2 §3.1). O teto funcionava (a tela pedia as 500 primeiras e declarava o corte), mas não havia caminho pra 501ª linha: pra alcançá-la o operador precisava inventar um filtro que estreitasse o recorte, o que só é possível se ele já souber o que procura. **Decisões declaradas:** (a) **`porPagina` é 10/25/50/100**, do V2 §4.8, não o 10/20/50/100 que o plano de ondas do 17/08 previa; **o padrão é 25, não o 10 do protótipo** — o 10 é artefato do dataset dele (14 itens) e num catálogo real vira centenas de páginas; 25 é o menor valor que a golden master oferece. (b) **Ordenar por custo/margem é ignorado** pra quem não tem `view_purchase_price`: a posição na lista denuncia o valor invisível (AR-PROD-015 com um passo a mais) — cai no padrão em vez de dar erro, porque erro também informaria. (c) Desempate estável por `c.id` — sem ele, linhas de mesmo preço trocam de lugar entre consultas e um item aparece em duas páginas enquanto outro some. **Revoga o Non-Goal "sem rodapé de paginação"** (SPEC v1.0 D-06). UCs novos: **UC-PUNI-11..11d**, na lane Estoque · MySQL. |
| 2026-08-19 | [C] | **Pacote "PROTÓTIPO OFICIAL — PRODUTO UNIFICADO V2" (19/08) — onda 1: moldura e recorte.** O V2 substitui o pacote de 17/08 como referência da tela. Abas com as cores da referência de produção (ativa clara + borda accent, hover das inativas, contagem em badge arredondado escuro) · gatilhos de filtro **sem moldura em repouso** (V2 §4.3) · **chips do recorte ativo, removíveis, + "Limpar filtros"** · KPI **"Itens listados" removido** (V2 §4.2 — não recortava nada; a contagem passa pro rodapé de paginação, que chega na onda 2) · grid **sem raio e sem sombra**, cabeçalho sticky **opaco** (com raio o sticky precisa de clipe de canto e o `backdrop-filter` quebra esse clipe — achado do LAUDO do pacote) · tabela `min-w-[1000px]` no wrapper que rola · **avatar 32** na coluna Produto com rótulo alinhado ao nome (pl 58) · coluna Ações 72px com alvos 28×28. **Decisões declaradas:** (a) **as 5 cores cruas do pacote viraram token** `--idx-*` em `cockpit.css`, com par claro/escuro — o claro é byte-a-byte o pedido, então o pixel não muda, e o escuro deixa de quebrar; é o encaminhamento que o LAUDO do pacote recomenda (§3, [ALTA]) e que a ADR 0401 dele registra como "impacto visual: nenhum". (b) **Avatar é cópia local** (`_components/AvatarProduto.tsx`), não import da `Pages/Cliente/_components/Avatar` — a restrição dura de [M] de 2026-08-18 proíbe consumir componente compartilhado com a `/contacts`; hash e as 12 rampas são cópia literal pra mesma cor sair nas duas listagens, e as iniciais são **primeira + última palavra**, medidas no protótipo rodando (a função `monograma` do script do pacote não é a que a tela usa). |
| 2026-08-10 | [M+C] | `related_prototype` deixa de ser `n/a` → `prototipo-ui/cowork/prototipo-ui-patch/prototipos/produto/produto-app.jsx`. **Por quê:** o corpo do charter (§Status e §Refs) sempre declarou que a tela nasceu desse protótipo, mas o frontmatter dizia `n/a` — e é o frontmatter que `prototipo-ui/ancora.mjs` lê. Com `n/a`, a tela não tinha âncora resolvível e a fidelidade proto×prod só podia ser julgada no olho (o incidente que originou a máquina de âncora). Âncora desambiguada: `produtos-page.jsx` (ex-`prod-page.jsx`, "visual Picker Mecânica") é do `Produto/Index`, **não** desta tela. Adicionado também `related_us: [US-PROD-023]` — a lane `charter-us-lint --check` (no-new-lie, ADR 0275 §5) morde charter tocado sem US declarada, e este era um dos 92 sem cobertura. A US-PROD-023 (*[G-05] Finalizar + promover as 8 telas React do Produto draft→live*, [SPEC.md](../../../../../memory/requisitos/Produto/SPEC.md)) é a que cobre esta tela — cita `/unificado` nominalmente. **Resíduo declarado:** o arquivo não segue o padrão `<id>-page.jsx` nem tem rota no shell de staging, então `render-proto-baseline.mjs --gerar` segue indisponível aqui — o loop 0→6 do RUNBOOK-fidelidade-fingerprint roda **manual** (passo 1 na mão) até o protótipo ganhar rota própria. |
| 2026-05-09 | [CL] | Charter draft criado em batch. Path canon `Pages/Produto/Unificado/Index.tsx` segue padrão `Pages/Financeiro/Unificado/Index.tsx` (subdir). Backend em `app/Http/Controllers/` (UPOS canon — não em Modules). **Decisões pendentes pra Wagner:** (1) `SellingPriceGroup.multiplier` schema (a vs b) precisa ADR; (2) confirmar `MfgRecipe` namespace em `Modules\Manufacturing\Entities\` (controller candidato Cowork admite "TODO confirmar"); (3) cache strategy KPIs (job diário vs `Cache::remember`). **Aprovação pendente** em Non-Goals + Anti-hooks pra `status: live`. |
| 2026-08-11 | [M+C] | `page` corrigido de `/produto/unificado` → **`/products/unificado`**. A rota real é `routes/web.php:450 (verificado@70c36b4)` (`products.unificado.index`); a declarada não existe. Ficava contraditória com o `Index.casos.md` que nasce no mesmo PR e cita a rota certa. `ancora.mjs` resolve por `page`/`component` — `component` já estava certo, então a âncora nunca quebrou; o campo errado enganava humano, não máquina. |
| 2026-08-13 | [M+C] | `related_prototype` volta a **`n/a`**. Em 2026-08-10 o campo foi apontado pra `produto-app.jsx` só pra dar âncora resolvível ao `ancora.mjs`. Depois disso, Wagner definiu que **o padrão desta tela é a tela de Contatos** (`/contacts` = `Pages/Cliente/Index.tsx`, PT-01 gold 9,4/10) — e o `produto-app.jsx` é justamente o protótipo cujo drawer 480px e BulkBar foram **descartados** por decisão. Com a âncora velha, qualquer máquina de fidelidade mediria contra a fonte errada e empurraria a tela **de volta** pro descartado. `n/a` não é vazio: é a **declaração** "nasce do DS", usada por 135 dos 158 charters (medido 2026-08-11) e pelos irmãos `Produto/Index` e `Produto/Show`. A comparação de fidelidade desta tela é **prod×prod** (`/contacts` × `/products/unificado` via `style-fingerprint --snippet` + `--compare --sem-ancora`), não proto×prod. |
| 2026-08-18 | [M+C] | **Layout do handoff "Consulta de Produtos" aplicado.** A tela passa a ser o índice do catálogo em paridade com a `/contacts` — a golden master que o próprio charter elegeu em 2026-08-13. O que mudou: a barra de abas deixa de ser "sub-telas" e vira **recorte por tipo do item** (6 abas com contagem); a faixa de 5 KPIs de leitura vira **6 KPI-filtros clicáveis** contados sobre a aba; entram busca em linha própria, 5 filtros com contagem à direita, colunas por autorização, disponibilidade com 4 rótulos e drawer de 2 seções. As 4 sub-telas anteriores **não sumiram** — foram pro menu de ações do cabeçalho, com os mesmos gates. Backend: `catalogoSub()` vira a fonte única de "o que é uma linha do catálogo" (linhas + KPIs + contagem das abas leem dela), com saldo real (`SUM(vld.qty_available)`), mínimo (`alert_quantity`), tipo derivado e última venda; `Inertia::defer` em toda prop agregada. UCs novos: **UC-PUNI-07..10** (`Index.casos.md`), com `ProdutoUnificadoIndiceContratoTest` na lane Estoque · MySQL. **Dois desvios declarados, pendentes de aprovação [W]:** (1) o filtro **"Fornecedor"** do handoff virou **"Marca"** — o UltimatePOS não guarda fornecedor no produto, só por compra; (2) **"Não estocável"** ficou neutro em vez do vermelho que o protótipo herda do badge de frescor — serviço não tem saldo por natureza, e pintá-lo de vermelho treina o balcão a ignorar a cor. **Dívidas do handoff que continuam abertas:** responsividade abaixo do desktop declarado e volume de catálogo real (ADR 0402 proposta — carga incremental × virtualização × rodapé canônico). |
| 2026-08-18 | [M+C] | **Pacote canônico passa a ser a "Consulta de Produtos" de 17/08** (zip `Esse 1`), por decisão de [M]. O pacote de 18/08 que a tela seguia até aqui **revoga por escrito** vários pontos da 17/08 — toolbar em uma linha, filtro Unidade, "Mais filtros", paginação e as regras `@container`. Como a 17/08 volta a ser a oficial, essas revogações caem, e este charter passa a descrever a 17/08. Ondas planejadas: 1 toolbar · 2 grade de KPI `auto-fit` · 3 rótulos e coluna Referência · 4 avatar + código `P-` + cartão sem raio · 5 colunas que somem por largura · 6 paginação 10/20/50/100 · 7 drawer 410 · 8 filtro Fornecedor. **Onda 1 nesta PR:** toolbar em uma linha, entra Unidade, sai Marca, "Mais filtros" abaixo de 780px. **Duas divergências DECLARADAS do alvo, decididas por [M]:** (a) **"Importar" fica no menu ⋯**, não como botão visível ao lado de "Novo produto" — o menu também guarda as 4 sub-telas, e tirá-lo de lá quebraria esse agrupamento; (b) **"Fornecedor" ainda não é montado** — o UltimatePOS não guarda fornecedor no produto (só por compra), e gatilho que não filtra é affordance mentindo; ele entra na onda 8, com a consulta ao histórico. **Restrição dura de [M]:** a tela `/contacts` **não se toca em nenhuma hipótese** — nem o arquivo, nem componente compartilhado que ela consuma; por isso tudo desta onda vive em `Pages/Produto/Unificado/_components/`. |
| 2026-08-18 | [M+C] | **Ratificações de [W] na onda 1**, via [M]. (1) **Marca FICA** — o pacote de 17/08 pede "Fornecedor", que o UltimatePOS não guarda no produto (só por compra); Marca é o atributo que o produto de fato carrega e que o balcão já usa. A onda 1 tinha removido Marca ao trazer Unidade; com esta decisão os DOIS ficam, e a **onda 8 (filtro Fornecedor) deixa de ser necessária** — reabri-la exige sinal novo. (2) **"Não estocável" fica em cinza**, não no vermelho que o protótipo herda do badge de frescor: serviço não tem saldo por natureza, e pintá-lo de vermelho treina o balcão a ignorar a cor. As duas eram divergências DECLARADAS desde a [#5906](https://github.com/wagnerra23/oimpresso.com/pull/5906) e agora são decisão registrada, não pendência. **Aprovação visual da onda 1 dada por [W]** sobre o comparativo antes/depois/diff da barra de filtros. |
