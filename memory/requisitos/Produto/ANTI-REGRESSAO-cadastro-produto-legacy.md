---
titulo: "Lista anti-regressão — Cadastro de Produto (legado Office Comercial 2026)"
tipo: anti-regressao
origem: "Office Comercial 2026 · Versão 2026.1.1.38 · tela 'Todos Produtos'"
parte: "1+2+3+4 — 8 abas (incl. Composição/Kit) + ícones + diálogos + planilha de composição"
gerado: 2026-07-13
observacao: "Contrato de paridade — a tela nova (Inertia/React) NÃO pode perder nenhum comportamento marcado ✅ sem decisão explícita de Non-Goal."
---

# Lista anti-regressão — Cadastro de Produto (legado)

> **Propósito.** Catalogar cada função/campo/comportamento da tela de cadastro de produto do
> **Office Comercial 2026** (Delphi legado) para servir de **contrato de não-regressão** na
> migração pro oimpresso novo. Cada item `AR-PROD-NNN` é uma asserção verificável: a tela nova
> deve **preservar** o comportamento, ou o desvio vira **Non-Goal declarado** (com aprovação).
>
> **Convenção:** `[V0]` = toca **valor/estoque** (REGRA MESTRE Tier 0 — dupla-confirmação +
> antes→depois) · `[calc]` = cálculo a preservar · `[?]` = comportamento inferido do print,
> **confirmar com o legado** antes de virar teste.
>
> **Produto de exemplo nos prints:** `SG03#` — "PARAMETRIZACAO MERCEDES BENS (AXOR)", categoria
> `1.1 ABEL/ELETRICA`, custo R$ 4.300,00 / valor R$ 7.000,00 / margem 62,79%, fornecedor
> "JAIR UMBELINA VARGAS ME", obs "SERVIÇO FEITO NO KOSAKI" — contexto oficina/comunicação visual.

---

## A. Cabeçalho / Identificação (sempre visível, acima das abas)

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-001 | **Código** do produto exibido/gerado (ex: `SG03#`) — read-only na edição | print 1 |
| AR-PROD-002 | **Descrição** — campo longo de texto livre (ex: "PARAMETRIZACAO MERCEDES BENS (AXOR)") | print 1 |
| AR-PROD-003 | **Ativo** S/N (dropdown) — produto inativo não some do cadastro, só do fluxo de venda | print 1 |
| AR-PROD-004 | **Última Alteração** — timestamp read-only `dd/mm/aaaa hh:mm:ss` (auditoria de edição) | print 1 |
| AR-PROD-005 | **Unidade** (dropdown, ex: `UN`) | print 1 |
| AR-PROD-006 `[V0]` | **R$ Custo** com precisão de casas do legado (exibido `4,300000`) — parser pt-BR sem inflar ×100 | print 1 |
| AR-PROD-007 `[V0][calc]` | **Margem %** calculada a partir de Custo e Valor. ✅ **CONFIRMADO na aba Formação de Preço:** `Margem% = (Valor − Custo) / Custo` → (7000−4300)/4300 = **62,79%** (markup sobre custo). Ver AR-PROD-093/094. | prints 1 + 6 |
| AR-PROD-008 `[V0]` | **R$ Valor** (preço de venda) — campo destacado (fundo laranja); editar Valor recalcula Margem (ou vice-versa) `[?]` | print 1 |
| AR-PROD-009 | **Cód. Fábrica** (código do fabricante) | print 1 |
| AR-PROD-010 | **Código EAN** + ícone de código de barras (leitura/geração) | print 1 |
| AR-PROD-011 | **Categoria** — código + descrição (ex: `1.1 · ABEL/ELETRICA`), com lookup (`...`) | print 1 |
| AR-PROD-012 `[V0]` | **Quant. Estoque** no cabeçalho (read-only/agregado) + 2 ícones de ação ao lado — ✅ **ícone vermelho** = ajuste manual de estoque (E/S); **ícone roxo** = saldo por local. Detalhe em AR-PROD-140..145. | print 1 |
| AR-PROD-013 | **Cadastro** — data de criação read-only (ex: `08/03/2023`) | print 1 |
| AR-PROD-014 | **Tipo** (dropdown, ex: `PRODUTO`) — distingue PRODUTO × SERVIÇO × outros `[?]` enumerar opções | print 1 |

---

## B. Barra de ações (coluna direita)

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-020 | **Novo** — inicia cadastro em branco | print 1 |
| AR-PROD-021 | **Alterar** — habilita edição do registro atual (modo leitura → edição) | print 1 |
| AR-PROD-022 | **Excluir** — ✅ **soft-delete**: com permissão, qualquer produto pode ser excluído; o cadastro vira **inativo** e some da lista, mas fica acessível por **filtro** de excluídos/inativos (NUNCA hard-delete — preserva consulta futura) | print 1 + Wagner |
| AR-PROD-023 | **Consultar** — abre busca/listagem de produtos | print 1 |
| AR-PROD-024 | **Navegação ← →** — registro anterior/próximo sem sair da tela | print 1 |
| AR-PROD-025 | **Menu** (atalho de ações do registro) | print 1 |

---

## C. Estrutura de abas (navegação do detalhe)

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-030 | **Abas do produto** na ordem do legado: `Fiscal` · `Dados Adicionais / Obs` · `Estoque` · `Custos e Tabelas de Preços` · `Preço Especial` · `Anexo` · `Atividade` | prints 1-5 |
| AR-PROD-031 | **Múltiplas telas "Todos Produtos"** abertas em abas de topo simultâneas (MDI) `[?]` | prints 1-5 |
| AR-PROD-032 | Trocar de aba **preserva** o registro carregado no cabeçalho | prints 1-5 |

> ⏳ **Abas ainda NÃO detalhadas (Parte 2):** `Fiscal`, `Custos e Tabelas de Preços`, `Preço Especial`, `Anexo`, `Atividade`.

---

## D. Aba "Dados Adicionais / Obs"

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-040 | Seção **Classificação › Plano de Contas** — vínculo contábil, com lookup (`...`) | print 1 |
| AR-PROD-041 | Seção **Classificação › Marca** — com lookup (`...`) | print 1 |
| AR-PROD-042 | Seção **Observações** — text area livre e grande (ex: "SERVIÇO FEITO NO KOSAKI"); persiste no produto | print 1 |

---

## E. Aba "Estoque" → sub-aba **GERAL**

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-050 | Sub-abas de Estoque: `GERAL` · `HISTÓRICO DE MOVIMENTO` · `FORNECEDOR` · `COMPRAS` | print 2 |
| AR-PROD-051 `[V0]` | **Disponibilidade**: `Disponível` · `Em Produção` · `Pendente` (3 saldos distintos) | print 2 |
| AR-PROD-052 `[V0]` | Botão **Verificar** — recalcula/confere disponibilidade sob demanda | print 2 |
| AR-PROD-053 | **Quantidades padrões**: `Estoque Máx.` e `Estoque Mín.` (base de alerta de reposição) | print 2 |
| AR-PROD-054 | **Tempo (dias) para entrega do material**: `Dias Mínimo` a `Dias Máximo` (lead time) | print 2 |
| AR-PROD-055 | **Local de Estoque Padrão** (dropdown) | print 2 |
| AR-PROD-056 `[V0]` | Checkbox **"Bloquear venda com estoque negativo"** (default marcado) — regra que impede venda abaixo de zero | print 2 |
| AR-PROD-057 | **Descrição do Local** (texto livre, ex: "RUA 1 - ARMÁRIO 2 - ANDAR 5") — endereçamento físico | print 2 |

---

## F. Aba "Estoque" → sub-aba **HISTÓRICO DE MOVIMENTO** (kardex)

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-060 `[V0]` | Filtro por **Ano** + **Mês** (ex: 2026 / Julho) + botão **Mostrar tudo** | print 3 |
| AR-PROD-061 `[V0][calc]` | **Saldo Inicial do Período** e **Saldo Final do Período** — computados sobre o filtro | print 3 |
| AR-PROD-062 | Grid com **agrupamento por arrastar coluna** ("Arraste uma coluna para fazer o agrupamento") | print 3 |
| AR-PROD-063 `[V0]` | Colunas do kardex: `Data/Hora` · `Tipo de (movimento)` · `Quant. Inicial` · `Quantidade` · `Quant. Final` · `Valor` · `Observação` · `Local do Estoque` · `Usuário` · `Fornecedor` · `Cód. Venda` · `Cód. NF Entrada` · `Tipo Uso` | print 3 |
| AR-PROD-064 `[V0]` | Cada movimento rastreia **origem** (Cód. Venda / Cód. NF Entrada) + **usuário** (auditoria append-only — não editar/apagar movimento) | print 3 |
| AR-PROD-065 | Estado vazio explícito: **"<Sem dados para exibir>"** | print 3 |

> 🎯 **Nota de migração:** este kardex real é exatamente a **fachada** apontada no SDD (tela React
> `StockHistory` grade 47 — hoje `movements` fica `undefined`). Preservar TODAS estas colunas +
> saldo inicial/final + agrupamento é o alvo de não-regressão da migração (CU-PROD-11 / G-01).

---

## G. Aba "Estoque" → sub-aba **FORNECEDOR**

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-070 | **Fornecedor Principal** (ex: `209 · JAIR UMBELINA VARGAS ME`), com lookup | print 4 |
| AR-PROD-071 | **Outros Fornecedores** — adicionar N fornecedores ao mesmo produto (dropdown + ↑↓ para incluir/remover) | print 4 |
| AR-PROD-072 `[V0]` | Por fornecedor: **Dt. Última Compra** + **R$ Valor** (último preço de compra) | print 4 |
| AR-PROD-073 | **"Observação desse fornecedor para ser lembrado quando for comprar novamente"** — nota por fornecedor | print 4 |
| AR-PROD-074 | Grid de fornecedores: `Ativo` · `Tipo` · `Cód. Fornecedor` · `Razão Social` · `Dt. Última Compra` · `R$ Valor` · `Cód. Fábrica` · `Fantasia` · `Observação` | print 4 |
| AR-PROD-075 | Estado vazio: **"<Sem dados para exibir>"** | print 4 |

> 🎯 **Nota:** este é o **fornecedor/cotação por produto** que o oimpresso novo hoje NÃO tem
> (`insumos()` retorna `fornecedor => null` — único ❌ AUSENTE do inventário; OF-03/C18 no SDD).

---

## H. Aba "Estoque" → sub-aba **COMPRAS**

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-080 | **Histórico de Compras desse produto** — grid com agrupamento por arrastar coluna | print 5 |
| AR-PROD-081 `[V0]` | Colunas: `Código` · `Data da Compra` · `Nota Fiscal` · `Fornecedor` · `Quantidade Comprada` · `R$ Custo` · `R$ Valor` · `Cód. Fábrica` · `Tipo da Nota` · `Situação` | print 5 |
| AR-PROD-082 | Checkbox **"Mostrar compras em aberto"** (filtro de situação) | print 5 |
| AR-PROD-083 | Botão **"Filtrar Tipos de Nota"** | print 5 |
| AR-PROD-084 | Estado vazio: **"<Sem dados para exibir>"** | print 5 |

---

# ═══════ PARTE 2 (prints 6-10) ═══════

## I. Aba "Custos e Tabelas de Preços" → sub-aba **Custos** → **Formação de Preço**

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-090 | Sub-abas da aba: `Custos` · `Tabela de Preço`; dentro de Custos há a aba interna **Formação de Preço** | print 6 |
| AR-PROD-091 `[V0]` | Seção **Rendimento da Última Compra**: `Valor Compra` · `Impostos/Desc.` · `Rendimento` · `R$ Compra` — deriva o **custo real** a partir da última compra (impostos + rendimento) | print 6 |
| AR-PROD-092 `[V0]` | Seção **Formação do Valor de Venda**: `Un. Rend.` · `R$ Custo` · `Markup` · `Margem%` · `R$ Valor Venda` · `Lucro Previsto` (+ botão **Copiar**) | print 6 |
| AR-PROD-093 `[V0][calc]` | ✅ **`Margem% = (R$ Valor Venda − R$ Custo) / R$ Custo`** → (7.000−4.300)/4.300 = **62,79%** (campo Margem% em verde) | print 6 |
| AR-PROD-094 `[V0][calc]` | ✅ **`Lucro Previsto = R$ Valor Venda − R$ Custo`** → 7.000 − 4.300 = **R$ 2.700,00** | print 6 |
| AR-PROD-095 `[V0][calc]` | **Markup** como fator sobre o custo (editável) — alterar Markup recalcula Valor Venda/Margem (dupla via: por Markup OU por Margem OU por Valor) `[?]` confirmar qual campo é mestre | print 6 |
| AR-PROD-096 | Botão **Copiar** na formação de preço — ✅ copia o **custo de compra do produto da nota de entrada** (traz o custo da última NF de entrada para o R$ Custo) | print 6 + Wagner |
| AR-PROD-097 `[V0]` | Checkbox **"Mantém Margem na importação"** — ao entrar compra nova com custo diferente, recalcula o Valor mantendo a margem | print 6 |
| AR-PROD-098 `[V0]` | Checkbox **"Atualiza Markup"** | print 6 |
| AR-PROD-099 | Checkbox **"Movimenta Estoque"** (marcado) — produto movimenta estoque; serviço tipicamente não | print 6 |
| AR-PROD-100 | Checkboxes **"Pode Comprar"** e **"Pode Vender"** (ambos marcados) — habilitam o item em compra/venda | print 6 |
| AR-PROD-101 `[V0]` | **R$ Valor mínimo de venda** — piso de preço (bloqueia venda abaixo) | print 6 |
| AR-PROD-102 | **Dimensões: `Qtd de Peça` · `Larg` · `Comp` · `Espessura`** (todos default `1,00`) — base de cálculo por **área/volume/peça** (comunicação visual: m² = Larg × Comp) | print 6 |
| AR-PROD-103 | **Un. Rend.** (unidade de rendimento, ex: UN) + campo **Rendimento** — quantos itens saem de 1 unidade de compra | print 6 |

## J. Aba "Custos e Tabelas de Preços" → sub-aba **Tabela de Preço** (faixa de quantidade)

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-105 `[V0]` | Criar faixa: `De` · `Até` · `Quantidade` (UN) · `% Desconto` · `R$ Valor` + botões incluir/remover | print 7 |
| AR-PROD-106 `[V0]` | Grid de tabela: `DE` · `Tipo` · `Quant.` · `% Desconto` · `R$ Valor` | print 7 |
| AR-PROD-107 `[V0]` | **Preço fixo por faixa de quantidade** (ex: "de 1 a 100 unidades, o preço sempre será R$ 40,00") — atacado/escalonado | print 7 |
| AR-PROD-108 | Checkbox **"Tabela de Preço pela Quantidade de Peças em vez da Quantidade"** — faixa por **nº de peças** (dimensão), não por unidade | print 7 |
| AR-PROD-109 | Estado vazio: **"<Sem dados para exibir>"** | print 7 |

## K. Aba "Preço Especial" (por cliente)

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-111 | **Preço Especial para Cliente** — vínculo por `Código` + `Descrição` do cliente (lookup) | print 8 |
| AR-PROD-112 `[V0]` | Campos: `Valor Original` (read-only, = 7.000,00) · `% Acréscimo` · `% Desconto` · `R$ Valor` | print 8 |
| AR-PROD-113 `[V0][calc]` | **`R$ Valor`** recalculado a partir do `Valor Original` aplicando `% Acréscimo`/`% Desconto` | print 8 |
| AR-PROD-114 | Checkbox **"Manter Desconto"** por linha | print 8 |
| AR-PROD-115 | Grid: `Código Tabela` · `Descrição` · `% Desconto` · `% Acréscimo` · `R$ Valor` · `Manter Desconto` (agrupável por arrastar coluna) | print 8 |
| AR-PROD-116 | Botão **Confirmar** (rodapé) | print 8 |

## L. Aba "Anexo"

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-117 | Anexos por produto em **caminho de rede** `\\servidor\WR Sistema\Fotos\Produto\{código}\Anexos\` | print 9 |
| AR-PROD-118 | **3 categorias de visibilidade** (coluna esquerda): `Anexos` (apenas no cadastro) · `Venda` (visível na venda) · `Produção` (visível na produção) | print 9 |
| AR-PROD-119 | Explorador de arquivos: `Nome` · `Tamanho` · `Tipo de item` · `Data de modificação` | print 9 |
| AR-PROD-120 | Botão **"Migrar fotos antigas"** (migração de layout antigo de anexos) | print 9 |
| AR-PROD-121 | Botão **"Configurações"** (do caminho/anexo) | print 9 |
| AR-PROD-122 | **Aviso de caminho indisponível**: "Não foi possível conectar ao caminho: ... Clique aqui para verificar" — falha graciosa quando o servidor de fotos cai | print 9 |
| AR-PROD-123 | Barra de status: contador de `Itens` · `Itens selecionados` · `KB Selecionados` · drive/espaço livre | print 9 |

> 🎯 **Nota de migração:** este é o conceito de **"arte anexada" por visibilidade** (cadastro/venda/produção) — casa com o F4 "arte como gate de produção" do SDD (comunicação visual). Ponto de atenção: hoje é **pasta de rede** (`\\servidor\...`), não storage gerenciado — migrar pra `Modules/Arquivos`/storage sem perder o vínculo por código.

## M. Aba "Fiscal"

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-124 | **Código do NCM / TIPI** + descrição (ex: `85365090` · "Do tipo utilizado em residências") | print 10 |
| AR-PROD-125 | **CEST** (dropdown) | print 10 |
| AR-PROD-126 | **Cód. Grupo Imposto** + descrição (ex: `1` · "SEM ST REV") — vínculo a grupo tributário | print 10 |
| AR-PROD-127 | **Descrição Alternativa para Nota Fiscal** (texto que vai na NF, ≠ descrição do cadastro) | print 10 |
| AR-PROD-128 | **Origem da Mercadoria** (dropdown, ex: `Nacional`) | print 10 |
| AR-PROD-129 | Seção **Cupom Fiscal - PAF-ECF**: `IAT` · `IPPT` · `EX TIPI` | print 10 |
| AR-PROD-130 | **Peso Bruto** e **Peso Líquido** (Kg) | print 10 |

---

# ═══════ PARTE 3 (prints 11-13 + esclarecimentos Wagner) ═══════

## N. Aba "Atividade"

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-131 | **Atividade = histórico de modificações** feitas no cadastro do produto (log de alterações, append-only) | print 11 + Wagner |
| AR-PROD-132 | Estado vazio explícito: **"<Sem dados para exibir>"** | print 11 |
| AR-PROD-133 | Rodapé com **caixa de texto + botão "Enviar"** — comentário/anotação no histórico do produto | print 11 |
| AR-PROD-134 | Modo edição mostra **Confirmar / Cancelar** na barra de ações (substituem Novo/Alterar/Excluir enquanto edita) | print 11 |

## O. Ações de estoque do cabeçalho (os 2 ícones ao lado de "Quant. Estoque")

### O.1 — Ícone vermelho → "Alteração de Estoque" (entrada/saída manual)

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-140 `[V0]` | Abre diálogo **"Alteração de Estoque"** — ajuste **manual** de entrada/saída (fora de compra/venda) | print 12 |
| AR-PROD-141 `[V0]` | Campo **Quantidade** a adicionar/remover + seletor **Ent/Saída** (`E`/`S`) | print 12 |
| AR-PROD-142 | Campo **Local do Estoque** (ex: `PRINCIPAL`) — ajuste é por local | print 12 |
| AR-PROD-143 | Campo **Observação** (motivo do ajuste — vira linha no kardex/AR-PROD-063) + **Confirmar/Cancelar** | print 12 |

### O.2 — Ícone roxo → saldo por local (grade de locais)

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-144 `[V0]` | Abre grade de **saldo do produto por local de estoque** | print 13 |
| AR-PROD-145 `[V0]` | Colunas por local: `FANTASIA` · `ESTOQUE TOTAL` · `PRINCIPAL` · `QUEBRA` · `CLIENTE` · `ASSISTENCIA` — locais nomeados por empresa; `ESTOQUE TOTAL` = Σ locais | print 13 |

> 🎯 **Nota de migração:** os locais nomeados (`PRINCIPAL/QUEBRA/CLIENTE/ASSISTENCIA`) mapeiam
> pra `business_location` no oimpresso; o ajuste manual (O.1) é `transactions.type=stock_adjustment`
> (`adjustment_type ∈ {normal, abnormal}` — ver `memory/dominio/estoque.md`), gerando linha no kardex.

---

# ═══════ PARTE 4 (prints 14-16) — Aba COMPOSIÇÃO (Kit / Ordem de Produção) ═══════

> **Contexto:** esta aba **só aparece para produtos do Tipo `COMPOSIÇÃO`** (Unidade `KIT`,
> Categoria "COMPOSIÇÃO/ORDEM DE PRODUÇÃO"). O produto do exemplo mudou para `COM1/1-BD`
> "DISPOSITIVO DE SEGURANÇA PRIMÁRIO — CLASSE A" (custo R$ 1.667,68 / valor R$ 2.980,00 /
> margem 78,69%). É o motor de **BOM "desmonta peça por peça"** — o mais rico do cadastro.
> Ancoragem no manual: `PRODUTO_COMPOSICAO` (31 col.) + `FORMULAS` + `FORMULA_PERFIL`.

## P. Aba "Composição" — contexto

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-150 | Aba **Composição** aparece **só quando Tipo = COMPOSIÇÃO** (janela vira "Produto Composição"); Unidade = `KIT` | prints 14-16 |
| AR-PROD-151 | Topo direito **"Informe as Dimensões do Produto"**: `Unidade` (KIT) + `Peças` (1,00) — dimensões do kit final | print 14 |
| AR-PROD-152 | 3 sub-abas: **Materiais** · **Fórmulas Avançadas** · **Produzir** | prints 14-16 |
| AR-PROD-153 | Barra de ícones da Composição (adicionar/duplicar/remover componente) | print 14 |

## P.1 — sub-aba "Materiais" (a lista do BOM)

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-154 `[V0]` | Grid de componentes: `Ordem` · `Cód. Produto` · `Descrição` · `R$ Custo Unitário` · `Qtd. Peças` · `Comprimento` · `Largura` · `Espessura` · `Fórmula` · `Rendimento` · `Unidade` · `R$ Valor` · `R$ Valor Total` | print 14 |
| AR-PROD-155 | Linha de inclusão: busca **Matéria Prima** + descrição + dimensões + fórmula + rendimento + ↑↓ para incluir/remover | print 14 |
| AR-PROD-156 `[V0][calc]` | **Fórmula por componente** + dimensões calculam a quantidade real. Ex: `MANG06` comprimento 21 → **21,0000 M** → R$ 105,00; `AJ19`/`CP01` 2 peças → 2,0000 UN | print 14 |
| AR-PROD-156a | **Enumeração de fórmulas** (dropdown, 11 tipos): `A CADA` · `ÁREA QUADRADA` · `BARRAS` · `FOLHAS/CHAPA` · `IGUAL` · `IGUAL LARGURA` · `ILHÓS` · `PERÍMETRO` · `PERSONALIZADA` · `PROPORCIONAL` · `SEM FÓRMULA`. **Núcleo da precificação de comunicação visual** (ÁREA QUADRADA = m², PERÍMETRO = acabamento de borda, ILHÓS = ilhós por metro, FOLHAS/CHAPA = aproveitamento de chapa, BARRAS = corte de perfil, PERSONALIZADA = fórmula livre) | print 17 (dropdown) |
| AR-PROD-157 `[V0]` | **Rendimento** por componente (quantos saem por unidade de compra) afeta o valor da linha | print 14 |
| AR-PROD-158 `[V0][calc]` | `R$ Valor Total` do componente = quantidade/dimensão/rendimento × `R$ Valor` | print 14 |
| AR-PROD-159 `[V0]` | Linha **"Diferença no Valor"** — ✅ valor **inserido manualmente** (a mais ou a menos) no valor de venda para **justificar a diferença** entre a soma das matérias-primas e o valor de venda final do kit (ex R$ 66,00). É o "plug" de reconciliação, não cálculo automático. | print 14 + Wagner |
| AR-PROD-160 `[V0]` | Agregados no rodapé: **Peso Bruto** (Σ dos pesos = 15,2 Kg) · **Peso Líquido** · **R$ Custo Total** · **R$ Total** (= valor do kit, R$ 2.980,00) | prints 14-16 |
| AR-PROD-161 | Botões do rodapé da composição: **Atualizar Preços** · **Custos** · **Markup** · **Peso** · **Arruma** (✅ **Arruma** = corrige/reordena as matérias-primas quando a ordem dos itens está incorreta — mostrada **em vermelho**) | print 14 + Wagner |

## P.2 — sub-aba "Fórmulas Avançadas" (planilha embutida)

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-162 `[V0]` | **Planilha estilo Excel** com barra de fórmula + referência de célula (ex `K7`) — cada célula pode conter fórmula | print 15 |
| AR-PROD-163 `[V0][calc]` | Fórmulas referenciam outras células. Ex confirmado: **`Margem Bruta % = ((L7/I7)-1)*100`** = (Valor/Custo − 1)×100 — coerente com AR-PROD-093 | print 15 |
| AR-PROD-164 `[V0]` | Colunas da planilha: `A Código` · `B Descrição` · `C Peças` · `D Comp` · `E Larg` · `F Espes` · `G Quant` · `H UN` · `I Custo` · `J Custo Total` · `K Margem Bruta %` · `L Valor` · `M Total` · `O Valor Compra` · `Q Desconto na venda` · `R Desc. no Item` · `S Acréscimo na Venda` · `T Acrésc. no Item` · `U Frete` · `V ICMS ST` · `W IPI` · `X Frete (CTe)` · `Y DIFAL %` · `Z DIFAL R$` · `Total` | print 15 |
| AR-PROD-165 `[V0]` | Bloco inferior **"Formação do Preço de Venda - Markup"** (R$ 1.667,68) com componentes de custo: **Outros Custos · Perdas de Produção · Impostos · Custos Fixos** — o "desmonta peça por peça pra garantir margem" | print 15 |
| AR-PROD-166 | Fórmulas **reutilizáveis** (catálogo `FORMULAS`, texto até 4000 chars) + **perfil de fórmula** por componente (`FORMULA_PERFIL`) — não são hardcoded na linha | print 15 + manual |

## P.3 — sub-aba "Produzir" (ordem de produção)

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-167 | Sub-aba **Produzir** — ✅ **indica o cadastro do produto final** que essa composição gera (o vínculo composição → produto acabado, ex `COM1/1-BD`). Não é o disparo da OP; é a amarração do acabado. | print 16 + Wagner |
| AR-PROD-168 | **Fórmulas persistem** (resposta à dúvida #5): catálogo reutilizável `FORMULAS` (expr. até 4000 chars) + `FORMULA_PERFIL` (com flag `PADRAO`) + `FORMULAS_GABARITO`; instância aplicada por componente em `PRODUTO_COMPOSICAO.{COMP,LARG,ESPESSURA,QTDADEPECA}_FORMULA`. Colunas calculadas (margem/total) são recomputadas; as **fórmulas de entrada ficam gravadas**. | manual |

> 🎯 **Nota de migração (importante):** o BOM legado é **multi-nível** (`PRODUTO_COMPOSICAO.ORDEM_ARVORE`
> = árvore) + **dirigido por fórmula por dimensão** (`*_FORMULA` VARCHAR 500 + catálogo `FORMULAS`) +
> **planilha embutida**. O `ProductBom` do oimpresso novo é **CRUD plano, sem fórmula nem planilha**
> (ver SDD CU-PROD-05 / OF-05 / CV-03). Este é o **maior gap de paridade** do cadastro — a composição
> não pode ser migrada "no olho" como kit simples; é engenharia de produto + precificação por fórmula.
> As 11 fórmulas (AR-PROD-156a) — ÁREA QUADRADA/PERÍMETRO/ILHÓS/FOLHAS-CHAPA/BARRAS — são o
> **motor de m² de comunicação visual** (o gap CV-01/CV-03 do SDD, que o legado já resolvia).
> "Produzir" apenas **amarra o produto acabado** à composição (a produção em si = fluxo à parte).

---

## Achados de valor/estoque a validar (Tier 0 — REGRA MESTRE)

Itens que **calculam ou movimentam dinheiro/estoque** e exigem teste com dupla-confirmação
(2 caminhos numéricos + antes→depois) na migração:

1. ✅ **Margem % = (Valor − Custo)/Custo** e **Lucro Previsto = Valor − Custo** (AR-PROD-093/094) — confirmado nos prints.
2. **Precisão de casas** — Custo exibe 6 casas (`4,300000`), Valor 4 casas (`7.000,0000`); preservar sem truncar/`num_uf`-inflar (AR-PROD-006/008).
3. **Saldos de disponibilidade** — Disponível × Em Produção × Pendente são 3 conceitos distintos (AR-PROD-051).
4. **Saldo inicial/final do kardex** por período (AR-PROD-061).
5. **Bloquear venda com estoque negativo** — regra de negócio ligada por produto (AR-PROD-056).
6. **Rendimento/custo da última compra** — custo derivado de `Valor Compra + Impostos + Rendimento` (AR-PROD-091) — cadeia de cálculo inteira a preservar.
7. **Markup ↔ Margem ↔ Valor** — 3 campos ligados; confirmar qual é o mestre e a ordem de recálculo (AR-PROD-095).
8. **Preço por faixa de quantidade** (AR-PROD-107) e **por quantidade de peças** (AR-PROD-108) — regras de atacado/dimensão.
9. **Preço especial por cliente** = Valor Original ± %acréscimo/%desconto (AR-PROD-112/113).
10. **Valor mínimo de venda** como piso (AR-PROD-101) e **Mantém Margem na importação** (AR-PROD-097).
11. **Dimensões Larg/Comp/Espessura/Qtd de Peça** (AR-PROD-102) — base de m²/volume; é o gap "produto por m²" de comunicação visual (CV-01 do SDD) que o **legado já resolvia**.

---

## Pendências / dúvidas — resolvidas pelo manual legado

> Fonte: `memory/dominios/wr-comercial/modulos/estoque/tabelas/` (dicionário auto-gerado do
> Firebird do WR Comercial via `UpdateSQL.txt`) + `memory/dominio/estoque.md`/`compras.md`.

- ✅ **Fórmula da Margem %** — `Margem% = (Valor − Custo)/Custo` · `Lucro = Valor − Custo` (AR-PROD-093/094).
- ✅ **Markup ↔ Margem ↔ Valor** — o **Markup é o mestre** e é *composto* por um perfil (`PRODUTO_MARKUP`): `PERC_CUSTO_FIXO + PERC_CUSTO_FINANCEIRO + PERC_CUSTO_VARIAVEL + PERC_LUCRO_DESEJADO` → fator `MARKUP`. `Valor Venda = f(Custo, Markup)`; **Margem% e Lucro são derivados**. O operador pode sobrescrever o Valor (flag `PODE_ATUALIZAR_VALORES_VENDA`). Os `CALC_PVENDA_*` guardam a decomposição (custo fixo/variável/financeiro, lucro desejado, comissões rep/fun/agência/produção, frete, impostos, perda de produção) — é o **"desmonta peça por peça pra garantir a margem"** (AR-PROD-095).
- ✅ **Tipo** (`PRODUTO_TIPO` + flags `TEM_*` em PRODUTO) — não é enum fixo: é **tabela configurável** de tipos, cada um com comportamento próprio via `TEM_PRODUTO` · `TEM_SERVICO` · `TEM_MATERIAPRIMA` · `TEM_USOECONSUMO` (+ `PODE_SER_VENDIDO`/`PODE_SER_COMPRADO`/`PODE_ALTERAR_ESTOQUE`/`BLOQUEIA_ESTOQUE_INSUFICIENTE`).
- ✅ **Un. Rend./Rendimento** — `UNIDADE_RENDIMENTO` + `QUANT_RENDIMENTO`/`QTDADEPECA_RENDIMENTO`/`COMP_RENDIMENTO`/`LARG_RENDIMENTO`/`ESPESSURA_RENDIMENTO`: quantos itens/qual área saem de 1 unidade de compra → rateia o custo unitário. Base do `R$ Compra` real.
- ✅ **Dimensões por fórmula** (comunicação visual) — além de `COMP`/`LARG`/`ESPESSURA`/`QTDADEPECA`, existem `COMP_FORMULA`/`LARG_FORMULA`/`ESPESSURA_FORMULA`/`QTDADEPECA_FORMULA` (VARCHAR 500) + `*_AVANCO1`: a **medida pode ser calculada por fórmula** (m² automático).

### Esclarecido por Wagner (2026-07-13)
- ✅ **Ícone vermelho** = ajuste manual de estoque (E/S por local) — AR-PROD-140..143.
- ✅ **Ícone roxo** = saldo do produto por local (PRINCIPAL/QUEBRA/CLIENTE/ASSISTENCIA) — AR-PROD-144/145.
- ✅ **Copiar** = traz o custo de compra da nota de entrada pro R$ Custo — AR-PROD-096.
- ✅ **Excluir** = soft-delete → inativo + filtro (nunca hard-delete) — AR-PROD-022.
- ✅ **Atividade** = histórico de modificações do cadastro + comentário — AR-PROD-131..134.

> **Nenhuma dúvida aberta.** Restam apenas enumerações a coletar quando for gerar os testes
> (opções de `Unidade`, `Origem da Mercadoria`, `CEST`, `IAT/IPPT`) — dados de tabela, não comportamento.

---

## Apêndice A — Mapa campo (tela) ↔ coluna legada (`PRODUTO`, 140 col.)

> Fonte: `memory/dominios/wr-comercial/modulos/estoque/tabelas/PRODUTO.md` (+ satélites). Ajuda
> a migração a preservar semântica, não só o rótulo. `CALC_*` = valor calculado (derivado).

| Campo na tela | Coluna(s) legada(s) | Satélite |
|---|---|---|
| Descrição / Descrição NF | `DESCRICAO` · `DESCRICAO_NFE` | — |
| Ativo · Cadastro/Alteração | `ATIVO` · `DT_ALTERACAO`/`DT_ATUALIZADO` | — |
| R$ Custo · R$ Valor · Margem% | `CUSTO` · `CALC_VVENDA_SUGERIDO`/`VALOR_VENDA` · `CALC_PMARGEM_CONTRIBUICAO` | — |
| Markup | `CALC_PMARKUP` (perfil) | `PRODUTO_MARKUP` (custo fixo/fin/var + lucro) |
| Lucro Previsto | `CALC_VLUCRO` · `CALC_PLUCRO_DESEJADO` | — |
| Valor mínimo de venda | `VALOR_VENDA_MINIMO` · `TEM_VMINIMO_VENDA_VALOR`/`_QUANTIDADE` | — |
| Rendimento última compra | `VALOR_COMPRA` · `QUANT_COMPRA` · `DT_ULTIMA_COMPRA` · `CALC_VCOMPRA_*` | — |
| Un. Rend. / Rendimento | `UNIDADE_RENDIMENTO` · `QUANT_RENDIMENTO` · `*_RENDIMENTO` | — |
| Larg/Comp/Espessura/Qtd Peça | `LARG` · `COMP` · `ESPESSURA` · `QTDADEPECA` (+ `*_FORMULA`, `*_AVANCO1`) | `FORMULAS` |
| Pode Comprar/Vender | `PODE_SER_COMPRADO` · `PODE_SER_VENDIDO` | `PRODUTO_TIPO` |
| Movimenta/Controla Estoque | `TEM_CONTROLE_ESTOQUE` · `PODE_ALTERAR_ESTOQUE` · `PODE_RETORNAR_AO_ESTOQUE` | — |
| Mantém Margem imp. / Atualiza Markup | `PODE_ATUALIZAR_VALORES_VENDA` · `PODE_ATUALIZAR_MARKUP` | — |
| Tipo | `CODPRODUTO_TIPO` + `TEM_PRODUTO`/`TEM_SERVICO`/`TEM_MATERIAPRIMA`/`TEM_USOECONSUMO` | `PRODUTO_TIPO` |
| Unidade / Subunidade | `UN_PADRAO_VENDA`/`UN_PADRAO_COMPRA` · `TEM_SUBUNIDADE` | `PRODUTO_SUBUNIDADE`, `UNIDADE` |
| Categoria · Marca · Plano de Contas | `CODPRODUTO_CATEGORIA` · (marca) · `CODPLANOCONTAS` | `PRODUTO_CATEGORIA`, `PRODUTO_MARCA` |
| Estoque máx/mín · dias entrega | (máx/mín) · `DIAS_PARA_COMPRAR_MIN`/`MAX` · `PRODUCAO_DIAS_PARA_PRODUZIR` | `PRODUTO_ESTOQUE`, `PRODUTO_ESTOQUE_LOCAL` |
| Fornecedor(es) | — | `PRODUTO_FORNECEDOR` |
| Tabela de Preço (faixa) | `TEM_TABELA_PRECO`/`_FIXO`/`_QTDADEPECA` · `CALC_VALOR_TABELA_PRECO` | `PRODUTO_TABELA_PRECO`, `PRODUTO_PRECO` |
| Composição / BOM | `TEM_COMPOSICAO` | `PRODUTO_COMPOSICAO` |
| Grade / Variação | `TEM_GRADE`/`TEM_VARIACAO` · `CODPRODUTO_GRADE_MODELO` · `VARIACAO_*` | `PRODUTO_GRADE_MODELO` |
| Fiscal: NCM · CEST · Origem · Grupo Imp. | (NCM) · `CODNF_CEST` · `ORIGEM_MERCADORIA` · `TEM_IMPOSTO_ESPECIAL` | `PRODUTO_IMPOSTO`, `PRODUTO_REGRA_TRIBUTARIA`, `NF_CEST` |
| PAF-ECF (IAT/IPPT) · Pesos | `TEM_IAT` · `TEM_IPPT` · `CALC_QPESO_BRUTO`/`_LIQUIDO` | — |
| **Ponte de migração** | `OIMPRESSO_CODIGO` · `OIMPRESSO_ATIVO` · `OIMPRESSO_DT_ALTERACAO` · `OIMPRESSO_UPDATED_AT` | — |

> ⚠️ **Divergência de vocabulário a resolver:** o novo oimpresso (`memory/dominio/estoque.md`)
> tem `products.type ∈ {single, variable, modifier}` — **`combo`/kit NÃO existe no enum** ("não
> inventar"). O legado usa `TEM_COMPOSICAO`/`PRODUTO_COMPOSICAO` pra kit/BOM. Mapear com cuidado
> (composição legada → BOM `ProductBom`, não `type=combo`).
>
> ⚠️ **Maior gap de paridade — a Composição (Parte 4, AR-PROD-150..167):** o BOM legado é
> **multi-nível** (`PRODUTO_COMPOSICAO.ORDEM_ARVORE`) + **por fórmula de dimensão** (`*_FORMULA`
> + catálogo `FORMULAS` até 4000 chars + `FORMULA_PERFIL`) + **planilha Excel embutida** com
> componentes de custo (perdas de produção, custos fixos, impostos). O `ProductBom` do oimpresso
> é CRUD plano. Não é migração de kit simples — é engenharia de produto + precificação por fórmula.

## Cobertura de abas

| Aba | Status | Itens |
|---|---|---|
| Fiscal | ✅ Parte 2 | AR-PROD-124..130 |
| Dados Adicionais / Obs | ✅ Parte 1 | AR-PROD-040..042 |
| Estoque (4 sub-abas) | ✅ Parte 1 | AR-PROD-050..084 |
| Custos e Tabelas de Preços | ✅ Parte 2 | AR-PROD-090..109 |
| Preço Especial | ✅ Parte 2 | AR-PROD-111..116 |
| Anexo | ✅ Parte 2 | AR-PROD-117..123 |
| Atividade | ✅ Parte 3 | AR-PROD-131..134 |
| Ações de estoque do cabeçalho (2 ícones) | ✅ Parte 3 | AR-PROD-140..145 |
| **Composição** (só p/ Tipo=KIT) | ✅ Parte 4 | AR-PROD-150..167 |

**✅ DOCUMENTO COMPLETO** — 8 abas (7 do produto padrão + Composição do kit) + cabeçalho + barra
de ações + diálogos de estoque + planilha de composição. **~120 itens `AR-PROD-*`**. Pronto pra
virar contrato de não-regressão (cada `[reg]`/`[V0]` → teste Pest failing-first na migração MWART).

### Dúvidas da Parte 4 — todas resolvidas (Wagner 2026-07-13)
- ✅ **"Produzir"** = indica o cadastro do **produto final** que a composição gera (AR-PROD-167).
- ✅ **"Diferença no Valor"** = ajuste **manual** (+/−) pra justificar a diferença Σ matérias-primas × valor de venda (AR-PROD-159).
- ✅ **"A CADA"** é uma das **11 fórmulas** enumeradas (AR-PROD-156a).
- ✅ **"Arruma"** = reordena as matérias-primas quando a ordem está errada (em vermelho) (AR-PROD-161).
- ✅ **Persistência das fórmulas** = sim, gravadas (catálogo + por componente) — AR-PROD-168.
- 🟡 **Multi-nível (kit dentro de kit)** — suportado pelo schema (`PRODUTO_COMPOSICAO.ORDEM_ARVORE` = árvore); confirmar em uso real quando for migrar.

**Próximo passo sugerido:** converter em `casos.md` (US-PROD-020) ancorando UC-IDs, OU cruzar os
~120 itens contra as 8 telas React (`Pages/Produto/`) pra medir paridade antes do cutover.
