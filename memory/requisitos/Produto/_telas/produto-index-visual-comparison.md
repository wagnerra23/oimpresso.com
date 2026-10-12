---
id: requisitos-produto-telas-produto-index-visual-comparison
slug: inventory-produto-index-visual-comparison
title: "Produto — Comparativo visual da tela Lista de produtos"
type: visual-comparison
module: Inventory
status: approved
date: 2026-05-15
# canon_reference: o path abaixo foi removido em 2026-05-20, 1070e3759b7
canon_reference: prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx _(removido em 2026-05-20, 1070e3759b7)_
blade_source: resources/views/product/index.blade.php
inertia_target: resources/js/Pages/Produto/Index.tsx
approved_by: pending_wagner_screenshot_approval
generated_retroactively: false
pattern_reuse: true
# blueprint_cowork: o path abaixo foi removido em 2026-05-20, 1070e3759b7
blueprint_cowork: prototipo-ui/prototipos/produto-cockpit/ _(removido em 2026-05-20, 1070e3759b7)_
---

# Comparativo visual — Lista de produtos (`/products`)

> ## ⚠️ 2026-10-11 — a tela foi refeita; a âncora agora é `prototipo-ui/cowork/Felipe/produto-blade.jsx` (vista `lista`)
>
> O texto abaixo desta seção descreve a v2 de **cards** (âncora `produto-cockpit`, removida em
> 2026-05-20) e fica como histórico. A lista atual segue o charter v3 (#9118): dados #9119, tela
> #9124, menu ligado #9125, indicadores #9127, aba de estoque #9129, painel de detalhe #9131/#9132.
>
> **Medição 2026-10-11** — `scripts/design/design-diff.mjs`, a MESMA sonda nos dois lados, tema
> escuro, 1593px: produção `https://oimpresso.com/products` (biz 1) × espelho
> `cowork/Felipe/oimpresso.com.html` rota `prod-lista`. Papéis: KPI = filhos de `produto-kpis`,
> título = `h1`, filtros = `.pb-fld` de `produto-filtros`, linha = 1ª de `produto-tabela`.
>
> | Medida | Produção | Protótipo | Veredito |
> |---|---|---|---|
> | KPI: quantidade, alinhamento, cor, valor 22px | iguais | iguais | IGUAL |
> | Cor do botão principal | igual | igual | IGUAL |
> | Preço de compra / de venda | mono 12px | normal 13px | **corrigido** (sem mono) |
> | Botão "Ações" da linha | 14px | 12px | **corrigido** (12px) |
> | Título | 22px / 600 | 19px / 700 | mantido — decisão [W] D-PH-0923 (h1 600 em todas as telas, `PageHeader`) |
> | Linhas da área de filtros | 3 | 2 | não é defeito — a empresa medida tem 2 filtros de módulo (Modelo do dispositivo, WooCommerce) que também existem na lista Blade |
> | Coluna Imagem | imagem padrão | marcador "IMG" | não é defeito — dado (a produção sempre devolve uma imagem) |
> | Coluna Categoria | 1 linha | 2 linhas | não é defeito — a 1ª linha medida não tem categoria; a subcategoria aparece quando existe |
> | Larguras declaradas (`<col>`), checkbox como botão | sim | não | produção à frente, não é defeito |
>
> Não medido nesta rodada: toolbar, abas e rodapé da lista (a sonda cobre KPI, título, filtros e a
> 1ª linha da tabela). O painel de detalhe foi medido em 2026-10-11 (noite, horário de Brasília) — seção abaixo.
>
> ### Painel de detalhe — medido em 2026-10-11 (mesma sonda nos dois lados, tema escuro, 1593px)
>
> Produção `https://oimpresso.com/products?ver=1817` (biz 1) × espelho `cowork/Felipe/oimpresso.com.html`
> rota `prod-lista`, `DetalheDrawer`. A sonda lê caixa, tipografia e cor **computadas** de cada papel
> (raiz, selos, título, subtítulo, fechar, abas, corpo, campos, imagem, rodapé) — nunca classe.
>
> **Causa da maioria das diferenças (medida, não deduzida):** o `<Sheet>` abre num portal no `<body>`,
> fora do `.cockpit`. Dentro do painel `--text`, `--text-mute`, `--border`, `--accent`, `--surface` e
> `--font-sans` vinham **vazios** (`getComputedStyle(...).getPropertyValue`), enquanto na lista, dentro do
> `.cockpit`, todos resolvem. Conserto: `resources/css/produto-painel.css` repõe os tokens no painel
> (padrão do Financeiro, `fin-cowork.css` Onda 22b), com o par escuro.
>
> | Medida | Produção antes | Protótipo | Depois do conserto |
> |---|---|---|---|
> | Fonte | `ui-sans-serif` | IBM Plex Sans | IBM Plex Sans |
> | Cor do texto | `oklch(0.965 0.004 240)` | `oklch(0.94 0.005 90)` | igual ao protótipo |
> | Rótulo dos campos | cor cheia do texto | `oklch(0.67 0.005 90)` | igual ao protótipo |
> | Selo do tipo | sem fundo, sem cor | fundo `oklch(0.33 0.09 295)`, texto `oklch(0.7 0.15 295)` | igual ao protótipo |
> | Fundo do painel | `oklch(0.26 0.006 240)` | `oklch(0.30 0.008 240)` | igual ao protótipo |
> | Borda acima do rodapé | cor do texto (branca) | `oklch(0.34 0.008 240)` | igual ao protótipo |
> | Linha das tabelas | sem borda | 1px `oklch(0.31 0.008 240)` | igual ao protótipo |
> | Aba ativa | traço na cor do texto, sem fundo | traço `oklch(0.7 0.15 295)`, fundo `oklch(0.33 0.09 295 / 0.5)` | igual ao protótipo |
> | Faixa das abas | sem fundo, sem linha | fundo `oklch(0.26 0.006 240)`, linha 1px | igual ao protótipo |
> | Cabeçalho | um bloco de 110 | faixa de selos + fechar (57) e bloco do título (76) | estrutura do protótipo |
> | Subtítulo | 14px | 12,5px | 12,5px |
> | Fechar | 44×44, cor cheia | 28×28, raio 6, esmaecido | 28×28, raio 6, esmaecido |
> | Rodapé | botões à esquerda, 32 de altura, 14px | à direita, 30 de altura, 12,5px | à direita, 30, 12,5px |
> | Títulos de seção nas abas | não havia (só `caption` oculto) | 11px / 700, maiúsculas, esmaecido | iguais ao protótipo |
> | Caixa da imagem sem foto | não aparecia | moldura 168 com texto | moldura 168 com "Sem imagem" |
> | Variação de produto único | "DUMMY" | "Padrão" | "Padrão" |
> | Largura, grade de 3 colunas, vãos, tamanho dos campos, ordem das abas | iguais | iguais | IGUAL |
>
> **As colunas "Depois" de cor, fonte, faixa das abas e rodapé foram medidas** injetando o CSS novo na
> página de produção (só no navegador da medição) e relendo os valores computados; a estrutura do
> cabeçalho, os títulos, "Sem imagem" e "Padrão" estão no teste de componente
> `tests/js/produto-painel-detalhe.test.tsx`. Falta a releitura em produção depois do deploy.
>
> **Ficam diferentes do protótipo, de propósito ou por dado:**
> - Texto das abas: a aba ativa usa a cor de texto do `PageHeaderTabs` (`oklch(0.965 0.004 240)`), que é o
>   componente das abas do app inteiro; mudar ali muda todas as telas. Não é decisão desta tela.
> - Texto do botão principal: escuro em produção, branco no protótipo — é o `Button` do Design System.
> - Texto da caixa sem foto: "Sem imagem" (o protótipo traz a dica de envio "1:1, até 5 MB", que num
>   painel só de leitura afirmaria algo que a tela não faz).
> - Aba Fiscal: a produção mostra as colunas fiscais reais do cadastro (CFOP dentro/fora, CST…); o
>   protótipo tem um conjunto menor e um botão "Abrir no módulo Fiscal".
> - Aba Estoque: o protótipo tem 3 indicadores (estoque atual, valor pelo custo, valor pela venda) e
>   um botão "Transferir" por linha. **Ainda não feitos**: os indicadores somam valores (vêm com dupla
>   conferência) e o "Transferir" vira a requisição de transferência combinada com [F] em 2026-10-11.
> - "Gerar OP" no rodapé de composição: depende do módulo de produção.

> **Tipo de tela:** list cockpit list+detail (drawer pattern)
> **Persona:** Larissa (ROTA LIVRE biz=4), 1280px
> **Refs:**
> - Blade legacy: `resources/views/product/index.blade.php` (DataTables jQuery + Yajra server-side)
> - Canon Cowork: `produto-cockpit/produto-cockpit-page.jsx` _(removido em 2026-05-20, 1070e3759b7)_
> - Page MWART: `resources/js/Pages/Produto/Index.tsx`
> - RUNBOOK: [`RUNBOOK-produto-index.md`](RUNBOOK-produto-index.md)
> - ADR 0149 screen-pattern reuse (este é o blueprint pra Show/Edit/Create/SellingPrices)

## Resumo executivo

Blade legacy tem topbar de filtros + DataTables com 11 colunas + ações dropdown por linha. Cowork blueprint substitui por: header sticky + KPI strip 4 cards + filter tabs categoria + lista cards densa + drawer detalhe lateral. **MWART entrega:** versão Page Inertia que reusa AppShellV2 + tokens OKLCH Cowork + header pattern, com `Inertia::defer()` em props caras (KPIs, rows, categorias) — alinhado RUNBOOK-inertia-defer-pattern (Tier 0 desde 2026-05-15).

## Tabela comparativa — 8 dimensões

### 1. Layout

| Aspecto | Blade legacy | Cowork blueprint | Decisão MWART |
|---|---|---|---|
| Sidebar | UPOS 250px submenus | AppShellV2 260px (canon Cockpit) | AppShellV2 — paridade canon |
| Header | `<h1 class="content-header">` simples | Header sticky h1 + subtitle + ações primárias | PageHeader shared — paridade |
| Topnav módulo | Submenu dentro sidebar | Tabs `Catálogo · Categorias · Insumos · Tabelas · Histórico` | Tabs (segue Unificado pattern, opcional Wave 3) |
| Grid breakpoints | Bootstrap | `max-w-7xl` + grid responsivo | `max-w-7xl` |

### 2. Hierarquia visual

| Aspecto | Blade | Cowork | MWART |
|---|---|---|---|
| Ação primária | "+ Adicionar produto" topo direito | Botão primary topo direito | PageHeader action slot — paridade |
| Ações secundárias | Dropdown bulk 12 actions | Botão "Importar" outline | "Importar" + "Novo" — simplificado |
| KPIs top | Ausente | 4 cards: Total · Ativos · Categorias · Populares | 4 cards Deferred — paridade Cowork |

### 3. Densidade

| Aspecto | Blade | Cowork | MWART |
|---|---|---|---|
| Linhas tabela | DataTables ~38px linha | Lista cards densa Linear | Cards 1 col 1280px (Larissa) |
| Padding seções | `panel-body 15px` | `mx-6 mt-4` (24px) | `space-y-6` — paridade Cowork |

### 4-8. Cor, tipografia, interação, acessibilidade, fiscal

- Cores: tokens OKLCH Cowork (emerald/stone/rose semânticos)
- Tipografia: h1 24px, table 12-13px, SKU mono 11.5px, valores `tabular-nums`
- Interação: click card → drawer lateral (pattern Cowork) ou link `/products/{id}`
- Acessibilidade: `aria-label` em ações, focus visible (default Tailwind)
- Fiscal: SKU + categoria + unit visíveis (Larissa diferencia produtos rápido)

## Gaps catalogados

| Gap | Impacto | Quando atacar |
|---|---|---|
| Drawer lateral pattern Cowork | UX melhora — usuário não troca de rota pra ver detalhe | Wave 3 (out of scope desta migração) |
| Tabs categoria interativos (filter client-side) | Larissa filtra por tipo rápido | Wave 3 |
| Toggle "Mostrar inativos" persisted | Larissa às vezes precisa ver inativo | Wave 3 |
| Topnav módulo (afeta 78 telas MWART) | Navegação intra-módulo | PR separado (ADR 0039 §1 gap conhecido) |

## Aprovação

⏳ **Pendente Wagner screenshot approval** — quando build:inertia gera bundle, capturar screenshot 1280×800 e Wagner aprova via SYNC_LOG.

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Comparativo criado em Wave 2 B4 Produto (Agent paralelo). |
