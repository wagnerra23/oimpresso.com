---
id: requisitos-produto-produtos-gap
tela: Produto/Index (/products)
prototipo: prototipo-ui/cowork/produtos-page.jsx
tela_viva: resources/js/Pages/Produto/Index.tsx
---

# Gap — Produto/Index (vivo) × mockup Cowork `produtos-page.jsx`

> **Fase 1 (read-only) da skill `aplicar-prototipo`.** Mapeia o que o protótipo Cowork propõe que a tela VIVA não tem — e onde o vivo já está À FRENTE (mockup stale).
> Gerado 2026-06-30. NÃO edita código, NÃO commita. Só diagnóstico.

- **Vivo:** `resources/js/Pages/Produto/Index.tsx` (456 linhas) — Inertia/React, MWART (ADR 0104), `Deferred`, AppShellV2, multi-tenant no controller.
- **Mockup:** `_cowork-handoff-staging/oimpresso-erp-conunica-o-visual/project/produtos-page.jsx` (700 linhas) — protótipo "Picker Mecânica" (mock estático `PROD_DATA`, sem Inertia/Tier 0).

> ⚠️ O mockup é **vertical Mecânica/Picker** (categoria por hue, OEM, fornecedores, BOM). A tela viva é o **blueprint produto-cockpit** (cards + KPI strip + popularidade). São dois conceitos visuais distintos — boa parte do "gap" é **escopo novo**, não só estilo.

---

## Parte 1 — Header

| | |
|---|---|
| **Vivo** | Header sticky com breadcrumb (Inventário › Produtos), título `Produtos`, ações `Importar` (link `/import-products`) + `Novo produto` (gated por `permissions.create`, link `/products/create`). Usa `AppShellV2` + `Button`/`Link` canônicos. |
| **Mockup** | `os-page-h` com título + **subtítulo dinâmico de contagem** ("N produtos · X disponíveis · Y esgotados"). Botões `Importar` + `Novo produto` (sem gate de permissão). |
| **Gap real** | Subtítulo de contagem viva (produtos/disponíveis/esgotados) abaixo do título. Pequeno, informativo. |
| **Vivo-à-frente** | Breadcrumb + gate `permissions.create` no botão (mockup não tem permissão) + ações via componentes canônicos. **Mockup stale aqui.** |
| **Esforço/risco** | **P** · baixo — derivar contagens dos `rows` já carregados (sem tocar backend). ⚠️ "esgotados/disponíveis" depende de estoque (ver Parte 2). |

## Parte 2 — KPIs / Totalizadores

| | |
|---|---|
| **Vivo** | KPI strip topo (4 cards): Total, Ativos, Categorias, Populares·30d. Via `Deferred data="kpis"`. |
| **Mockup** | **NÃO tem KPI strip no topo.** Em vez disso, **totalizador no rodapé** com 5 blocos: Itens listados, Estoque total (un somadas), **Valor em estoque (venda)** = preço×estoque, **Custo em estoque** = melhor fornecedor, Margem média. |
| **Gap real** | Conceito de **totalizadores financeiros de inventário** (valor/custo em estoque, margem média) — ausente no vivo. É feature analítica nova. |
| **Vivo-à-frente** | KPI strip "Populares·30d" + "Categorias" não existe no mockup. Layouts diferentes (topo vs rodapé). |
| **Esforço/risco** | **G** · ⚠️ **toca estoque** — "valor/custo em estoque", "estoque total", "margem" derivam de quantidade × preço × custo. **Tier 0 cálculo de valor/estoque** (proibicoes.md "REGRA MESTRE"): qualquer número aqui exige dupla-confirmação + impacto antes→depois. **Só descrever visualmente; backend de agregação = _pendente_** (precisa ADR/SPEC + Pest dupla-confirmação). Não adotar no olho. |

## Parte 3 — Filtros / Busca

| | |
|---|---|
| **Vivo** | Busca por nome/SKU (2 campos), toggle "Mostrar inativos", tabs de categoria (Todos + por categoria com contagem). Persistência localStorage (`oimpresso.produto.*`). |
| **Mockup** | Busca **5 campos** (nome, código, marca, categoria, OEM) com badge "5 campos". Toggle "Inativos". **Type-nav** (Todos/Produto/Serviço/Composição). **Stockbar** — chips Em estoque / Estoque baixo / Esgotado com contagem. **Sort multi-coluna** (nome/estoque/custo/preço/margem/variantes/prazo/pop). **Toggle view Densa↔Balcão**. Botão "Limpar filtros". Persistência localStorage (`oimpresso.prod.*`). |
| **Gap real** | (a) **Filtro por tipo** (produto/serviço/composição); (b) **Stockbar** de estoque ⚠️; (c) **sort por coluna**; (d) **toggle view lista/cards**; (e) busca por mais campos (marca/OEM — _depende de schema ter esses campos: pendente_); (f) "Limpar filtros". |
| **Vivo-à-frente** | Tabs de categoria com contagem (mockup moveu tipo pro type-nav, mas não tem categoria-tab equivalente). Vivo já tem `Deferred` nos filtros. |
| **Esforço/risco** | Tipo+sort+view+limpar = **M** · baixo (UI/client). Stockbar = **M** ⚠️ **toca estoque** (categoriza por quantidade) — só visual, threshold de "baixo" = regra de negócio _pendente_. Busca marca/OEM = **P** se campos existirem (_pendente_ confirmar schema). |

## Parte 4 — Tabela / Cards (lista)

| | |
|---|---|
| **Vivo** | **Só cards** (grid 1→4 colunas). Card: badge categoria, nome, SKU, preço, barra de **popularidade %**. Inativos acinzentados. `Deferred data="rows"`. |
| **Mockup** | **Dois modos**: (a) **Lista densa** — tabela com colunas Produto / Estoque / Custo / Preço(faixa) / Margem / Variantes / Prazo, ordenável, thumb colorida por categoria + prateleira + OEM; (b) **Cards "Balcão"** — img colorida, marca, faixa de preço, unidade. **Seção "Esgotados"** separada no fim (acinzentada). Faixa de preço por variantes (min–max). |
| **Gap real** | (a) **Modo lista densa** (tabela ordenável) — alternativa ao card-only; (b) **faixa de preço** quando há variantes; (c) **coluna custo/margem/fornecedor** ⚠️; (d) coluna **estoque por linha** ⚠️; (e) **thumb/cor por categoria** (hue OKLCH harmônico); (f) **prateleira** (mock determinístico no protótipo — _negócio real pendente_); (g) seção Esgotados. |
| **Vivo-à-frente** | Barra de **popularidade %** no card (mockup tem `popularity` no sort mas não exibe barra). Cards com `Deferred`/skeleton + `EmptyState` canônico. |
| **Esforço/risco** | Lista densa + faixa preço + cor categoria + thumb = **G** · médio (reescrita do corpo). Colunas custo/margem/estoque/esgotados ⚠️ **toca estoque/valor** — Tier 0, exige backend (fornecedores, custo, qty) que o vivo **não expõe hoje** (`ProdutoRow` tem `cost`/`margin`/`stockQty` mas sem fornecedores/variantes). _Backend de fornecedor/variante/BOM = pendente._ |

## Parte 5 — Ações por linha

| | |
|---|---|
| **Vivo** | Card inteiro é `Link` para `/products/{id}` (se `permissions.update`). Sem ações inline. |
| **Mockup** | Clique na linha/card **abre drawer** (não navega). Ações ficam no drawer (ver Parte 6). |
| **Gap real** | Padrão **drawer-on-click** em vez de navegação de página. Diferença de UX (peek vs full page). |
| **Vivo-à-frente** | Vivo respeita `permissions.update` no clique (mockup não tem permissão). |
| **Esforço/risco** | **M** · baixo-médio — trocar navegação por drawer é decisão de UX (precisa charter/Wagner). Não é regressão; é mudança de paradigma. |

## Parte 6 — Drawer de detalhe

| | |
|---|---|
| **Vivo** | **NÃO existe drawer.** Detalhe = página `/products/{id}`. |
| **Mockup** | Drawer rico: KPIs (preço, custo melhor cotação, margem, estoque, prazo, prateleira); **OEM/equivalentes**; **ficha técnica** (specs); **tabela de preços 4 níveis** (Varejo/Atacado/Convênio/Funcionário com desconto/margem); **grade de variantes/SKUs** (estoque/preço); **fornecedores/cotação** (melhor destacado); **composição BOM**; ações Editar/Duplicar/Desativar. |
| **Gap real** | Drawer inteiro é **escopo novo grande**: tabela de preços por nível, fornecedores/cotação, variantes-SKU, BOM, OEM, ficha técnica. Nenhum existe no vivo. |
| **Vivo-à-frente** | — (vivo não tem nada equivalente). |
| **Esforço/risco** | **G** · alto · ⚠️ **toca estoque/valor em vários pontos** (tabela de preços com multiplicadores, custo×margem, estoque por variante). **Tier 0 cálculo de valor** — multiplicadores de preço (0.90/0.85/0.75) e margem são regra de negócio que exige ADR/SPEC + dupla-confirmação. Drawer **NÃO adotar no olho**; cada cálculo = _pendente_ de backend + prova dupla. |

---

## Veredito: **ADOTAR-PARCIAL**

O mockup está **à frente em conceito/escopo** (lista densa, drawer rico, tabela de preços, fornecedores, totalizadores de inventário) mas **atrás em fundação** (mock estático, sem Inertia/Tier 0/permissões/Deferred — o vivo já tem tudo isso). Não é stale puro nem adoção forte: é **garimpo seletivo de features**, com a maioria das peças valiosas bloqueadas por **Tier 0 valor/estoque** (exigem backend + dupla-confirmação, não cópia visual).

**Adotar agora (P/M, sem tocar estoque/valor — UI/client puro):**
1. **Subtítulo de contagem** no header (N produtos · disponíveis · esgotados) — _Parte 1, P_ (⚠️ "disponíveis/esgotados" só se estoque já vier nos rows).
2. **Filtro por tipo** (Produto/Serviço/Composição) + **"Limpar filtros"** — _Parte 3, M_.
3. **Toggle view Lista densa ↔ Cards (Balcão)** + **sort por coluna** — _Parte 3+4, M_ (popularidade/preço/nome; colunas custo/margem ficam de fora até backend).

**NÃO adotar sem ADR/SPEC + backend + dupla-confirmação (⚠️ Tier 0 valor/estoque):**
- Totalizadores financeiros de inventário (valor/custo em estoque, margem média) — _Parte 2_.
- Stockbar + coluna estoque/esgotados por linha — _Parte 3/4_.
- Drawer rico: tabela de preços 4 níveis, fornecedores/cotação, variantes-SKU, BOM — _Parte 6_.
- Faixa de preço por variantes, custo/margem por fornecedor — _Parte 4_.

**Pendências a confirmar (não inventar):** schema expõe marca/OEM/fornecedores/variantes/BOM/prateleira? `ProdutoRow` hoje só tem `cost/margin/stockQty` (sem fornecedores/variantes). Backend de agregação de inventário = _pendente_. Drawer-on-click vs página = decisão de charter/Wagner.

## Tabela de partes (derivada 2026-09-06, r5)

> Escrita à mão por [C] em 2026-09-06 (r5 — a derivação mecânica r1 deste arquivo copiava a linha "Gap real" e descartava "Esforço/risco" e o §Veredito, que separa "Adotar agora (UI/client puro)" de "NÃO adotar sem ADR/SPEC + backend + dupla-confirmação (Tier 0 valor/estoque)"; refutação GT-G5 r4). Cada Ação cita a Parte e o item do §Veredito. A fonte segue sendo a prosa; em conflito, a prosa vence.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Header | Gap: subtítulo de contagem viva (produtos/disponíveis/esgotados) · Vivo à frente: breadcrumb + gate permissions.create + componentes canônicos (mockup stale aqui) | **Decidir.** Subtítulo de contagem — §Veredito adotar #1 (Parte 1, P, derivar dos rows já carregados). ⚠️ "disponíveis/esgotados" só se estoque já vier nos rows (Parte 1 Esforço/risco). |
| KPIs / Totalizadores | Gap: totalizadores financeiros de inventário (valor/custo em estoque, margem média) · Vivo à frente: KPI strip Populares·30d + Categorias | Nada — §Veredito "NÃO adotar sem ADR/SPEC + backend + dupla-confirmação (⚠️ Tier 0 valor/estoque)": Parte 2, esforço G, quantidade × preço × custo (REGRA MESTRE de proibicoes.md). |
| Filtros / Busca | Gap: (a) filtro por tipo, (b) Stockbar ⚠️, (c) sort por coluna, (d) toggle lista/cards, (e) busca marca/OEM (_pendente_ schema), (f) Limpar filtros · Vivo à frente: tabs de categoria com contagem + Deferred | **Decidir.** Só (a) filtro por tipo + (f) Limpar filtros — §Veredito adotar #2 (Parte 3, M) — e (c) sort + (d) toggle — adotar #3. (b) Stockbar toca estoque (threshold = regra de negócio _pendente_). (e) busca por marca/OEM: Parte 3 diz "P se campos existirem, _pendente_ confirmar schema" — confirmado no código: `products.brand_id` existe (`database/schema/mysql-schema.sql:7577`, dentro de `CREATE TABLE products`), campo OEM não existe (0 ocorrências no schema) → marca é P; OEM fica `_pendente_` de schema. |
| Tabela / Cards (lista) | Gap: (a) modo lista densa, (b) faixa de preço com variantes, (c) coluna custo/margem/fornecedor ⚠️, (d) estoque por linha ⚠️, (e) thumb/cor por categoria, (f) prateleira · Vivo à frente: barra de popularidade, Deferred/skeleton, EmptyState canônico | **Decidir.** Só (a) lista densa ordenável + toggle — §Veredito adotar #3 (Parte 3+4, M; popularidade/preço/nome). (c) custo/margem/fornecedor, (d) estoque por linha e (b)/(f) dependentes de variante/estoque: NÃO adotar sem ADR/SPEC + backend + dupla-confirmação (Tier 0 — Parte 4 Esforço/risco: `ProdutoRow` já tem `cost`/`margin`/`stockQty`; o que o backend não expõe é fornecedores/variantes/BOM, _pendente_). |
| Ações por linha | Gap: drawer-on-click em vez de navegação de página (peek vs full page) · Vivo à frente: permissions.update no clique | **Decidir.** Trocar navegação por drawer é mudança de paradigma de UX — Parte 5, M, "precisa charter/Wagner" (não é regressão; decisão de produto, não de tela). |
| Drawer de detalhe | Gap: drawer inteiro é escopo novo grande (tabela de preços por nível, fornecedores/cotação, variantes-SKU, BOM, OEM, ficha técnica) · Vivo à frente: — (nada equivalente) | Nada — §Veredito "NÃO adotar sem ADR/SPEC + backend + dupla-confirmação (⚠️ Tier 0)": Parte 6, esforço G/alto, multiplicadores de preço e margem são regra de negócio (REGRA MESTRE de proibicoes.md). |
