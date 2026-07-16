---
titulo: "Lista anti-regressão — Aba VARIAÇÃO (Grades e Variações · preços e Cores)"
tipo: anti-regressao
origem: "Office Comercial 2026 · Versão 2026.1.1.38 · tela 'Grades e Variações preços e Cores'"
parte: "Documento separado — aba Variação (complementa ANTI-REGRESSAO-cadastro-produto-legacy.md)"
gerado: 2026-07-13
doc_principal: "ANTI-REGRESSAO-cadastro-produto-legacy.md (abas Fiscal/Dados/Estoque/Custos/Preço Especial/Anexo/Atividade/Composição)"
observacao: "Contrato de paridade — a aba Variação da tela nova NÃO pode perder nenhum comportamento marcado ✅ sem Non-Goal explícito."
---

# Lista anti-regressão — Aba **VARIAÇÃO** (Grades e Variações · preços e Cores)

> **Documento adicional**, separado do principal a pedido do Wagner. Cobre **só a aba Variação**
> — que abre a janela "Grades e Variações preços e Cores" e **só aparece para produtos com
> variação** (grade tam×cor ou preço por quantidade). Continua a numeração `AR-PROD-*` do doc
> principal (a partir de **170**) pra facilitar a consolidação futura no `casos.md`.
>
> **Convenção:** `[V0]` = toca **valor/estoque** (REGRA MESTRE Tier 0) · `[calc]` = cálculo a
> preservar · `[?]` = confirmar antes de virar teste.
>
> **Produto de exemplo:** `6232` — "CAMISA SUBLIMAÇÃO TOTAL CACHARREL C/ MANGA CURTA", categoria
> `13 SUBLIMACAO`, custo R$ 17,49 / valor R$ 60,00 / margem **243,05%**, estoque **−7.155,3576**
> (negativo — sublimação sob demanda). Ancoragem no manual: `PRODUTO_PRECO`, `PRODUTO_GRADE_MODELO`,
> `PRODUTO_GRADE_MODELO_ITEM` + colunas `TEM_VARIACAO`/`VARIACAO_*`/`TEM_FILHO_*` em `PRODUTO`.

---

## Q. Aba "Variação" — contexto

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-170 | Aba **Variação** só aparece p/ produto com variação (`TEM_VARIACAO`); abre a janela **"Grades e Variações preços e Cores"** | print 18 |
| AR-PROD-171 | **Tipo de Variação** (dropdown) com 2 modos: **"Preço por quantidade"** e **"Cor e Tamanho"** (`VARIACAO_TIPO`) | print 18 |
| AR-PROD-172 | Checkbox **"Filhos Tem Preço Individual"** (`TEM_FILHO_PRECO_INDIVIDUAL`) — cada variação-filho tem preço próprio | print 18 |
| AR-PROD-173 | Checkbox **"Filhos Tem Descrição Individual"** (`TEM_FILHO_DESCRICAO_INDIVIDUAL`) — cada filho tem descrição própria | print 18 |
| AR-PROD-174 | Ações: **Adicionar** · **Alterar** · **Excluir** · **Modelo Grade** | prints 18-19 |
| AR-PROD-175 `[V0]` | Cabeçalho mostra estoque agregado do pai; ✅ **estoque negativo** (ex −7.155,3576 / −4.603,00) = efeito de **"Controla Estoque" desligado** no produto (não é regra da variação) | prints 18, 19 + Wagner |

## Q.1 — Modo "Preço por quantidade" (faixa de qtd → produto filho vinculado)

> Cada faixa de quantidade vira um **produto filho vinculado** com preço próprio (`PRODUTO_PRECO`
> → `CODPRODUTO_VINCULADO`). É atacado escalonado materializado como SKUs filhos.

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-176 `[V0]` | Grid: `De` · `Tipo` (Até) · `Quantidade` · `Porcentagem` · `Valor` · `Referência` · `Código Único` (SKU) · `Produto Vinculado` · `[Ver Histórico]` | prints 18-19 |
| AR-PROD-177 `[V0][calc]` | **Porcentagem = desconto OU acréscimo** sobre o valor base → `Valor` (Valor Final). ✅ **% negativo = acréscimo** (ex `7650`: faixa 1001–10000 com −7,34% → R$ 1,90, acima do R$ 1,00 da faixa anterior). Desconto positivo ex (base R$ 60,00): 16,67%→50 · 33,33%→40 · 41,67%→35 · 53,33%→28 | prints 18, 22 + Wagner |
| AR-PROD-178 `[V0]` | **Produto Vinculado é OPCIONAL**: ✅ pode haver faixa **com** filho vinculado (SKU próprio `CODPRODUTO_VINCULADO`, ex 8380–8384 — **aparece na lista/busca**) OU faixa **sem** filho (só preço-por-qtd no próprio produto, ex `7652`/`7650` — coluna vazia) | prints 18, 19, 22 + Wagner |
| AR-PROD-179 | Faixa por **tipo** `Até` ou **`Acima de`** (última faixa costuma ser "Acima de", ex `7652`: 501 Acima de → 66% → 0,17). Faixas contíguas sem buraco | prints 19, 20 |
| AR-PROD-180 | **Ver Histórico** por linha — histórico de preço daquela faixa/filho | prints 18-19 |
| AR-PROD-181 | `Referência` + `Código Único` (SKU) por faixa (`PRODUTO_PRECO.REFERENCIA`/`SKU`) | print 18 |
| AR-PROD-182 | Rodapé: **"Preço Fixo por faixa de Quantidade"** + checkbox exemplo (1–100 = R$ 40,00) + checkbox **"Tabela de Preço pela Quantidade de Peças em vez da Quantidade"** | print 18 |
| AR-PROD-186 | **Diálogo Adicionar/Alterar** ("Variação por Preço por quantidade", ex "Maior que 11 Até 30"): **Configurar Quantidade** [`Qual o tipo de Cálculo?` = `Até`/`Acima de` · `Quantidade Inicial` · `Quantidade`] + **Configurar Valor** [`Valor Inicial` (read-only) · `% Desconto` · `Valor Final`] + Confirmar/Cancelar | prints 20, 22 |

## Q.2 — Modo "Cor e Tamanho" (grade)

> Grade tam×cor via **modelo de grade** (`PRODUTO_GRADE_MODELO`: `TIPO`, `TIPOSMEDIDAS`, `T1..T11`
> = rótulos de tamanho; `PRODUTO_GRADE_MODELO_ITEM` = itens). `CODPRODUTO_GRADE_MODELO` no produto.

| ID | Comportamento a preservar | Prova |
|---|---|---|
| AR-PROD-183 | Modo **"Cor e Tamanho"** monta a grade (matriz cor × tamanho) | print 18 (dropdown) |
| AR-PROD-184 | Botão **"Modelo Grade"** aplica um **modelo de grade** reutilizável (tamanhos `T1..T11`, tipo de medidas) | print 18 |
| AR-PROD-185 `[V0]` | Cada célula da grade (cor×tamanho) = variação-filho com SKU + preço + estoque próprios (governado por `VARIACAO_VARIA_PRECO`/`VARIACAO_CONTROLA_ESTOQUE`) | print 18 + manual |
| AR-PROD-187 | **Cadastro do Modelo de Grade** (menu **Produto → Modelo de Variação**): tela "Cadastro de Modelo de Grade" com `Descrição` (ex "PLUS SIZE") · `Ativo` · **`Tipo da Grade`** (ex `TAMANHO`) + lista de itens ordenável (ex G1/G2/G3) com ↑↓. Reutilizável entre produtos | prints 21, 22 |

---

## Achados de valor/estoque a validar (Tier 0 — REGRA MESTRE)

1. **Porcentagem × Valor por faixa** (AR-PROD-177) — % de desconto sobre a base e preço resultante; preservar o cálculo e o arredondamento (`num_uf`).
2. **Produto filho vinculado por faixa** (AR-PROD-178) — mexer em preço de faixa mexe no filho vendável; dupla-confirmação.
3. **Preço/descrição individual do filho** (AR-PROD-172/173) — flags que mudam de onde vem preço/descrição na venda.
4. **Estoque negativo agregado** (AR-PROD-175) — variação sublimação não bloqueia venda por estoque; confirmar regra por produto.
5. **Estoque por variação-filho** (AR-PROD-185) — `VARIACAO_CONTROLA_ESTOQUE` decide se o estoque é por filho.

---

## Apêndice — Mapa campo (tela) ↔ coluna legada

| Campo na tela | Coluna/tabela legada |
|---|---|
| Tipo de Variação | `PRODUTO.VARIACAO_TIPO` |
| Filhos Tem Preço Individual | `PRODUTO.TEM_FILHO_PRECO_INDIVIDUAL` |
| Filhos Tem Descrição Individual | `PRODUTO.TEM_FILHO_DESCRICAO_INDIVIDUAL` |
| Variação varia preço / controla estoque | `PRODUTO.VARIACAO_VARIA_PRECO` · `PRODUTO.VARIACAO_CONTROLA_ESTOQUE` |
| Grid "Preço por quantidade" (De/Tipo/Qtd/%/Valor/Ref/SKU/Vinculado) | `PRODUTO_PRECO` (`DE`, `QUANT`, `TIPO`, `PORCENTAGEM`, `CODPRODUTO_VINCULADO`, `REFERENCIA`, `SKU`, `DESCRICAO`) |
| Produto Vinculado (filho) | `PRODUTO_PRECO.CODPRODUTO_VINCULADO` → `PRODUTO` |
| Modelo Grade (Cor e Tamanho) | `PRODUTO.CODPRODUTO_GRADE_MODELO` → `PRODUTO_GRADE_MODELO` (`TIPO`, `TIPOSMEDIDAS`, `T1..T11`) + `PRODUTO_GRADE_MODELO_ITEM` |
| Vínculo pai↔filho | `PRODUTO.CODPRODUTO_ORIGEM` / `CODPRODUTO_FINAL` |

---

## Nota de migração (paridade vs oimpresso novo)

- **"Cor e Tamanho"** mapeia bem pro oimpresso: `products.type=variable` + `variations` (tam×cor) +
  `variation_group_prices`. O **modelo de grade reutilizável** (`PRODUTO_GRADE_MODELO`, `T1..T11`)
  não tem equivalente direto — hoje a grade nova é montada ad-hoc; avaliar "modelo de grade" como feature.
- ⚠️ **"Preço por quantidade" diverge:** no legado a faixa de qtd **pode** virar um **produto filho
  vinculado** (SKU próprio que aparece na busca) **ou** ficar só como preço-por-qtd no próprio produto
  (sem filho). No oimpresso, preço por faixa é `selling_price_groups`/tabela de preço (não gera filho).
  Mapear com cuidado — materializar filhos por faixa pode **duplicar SKU** no catálogo novo.
- **Preço por quantidade ≠ Tabela de Preço** (esclarecido por Wagner): **Preço por quantidade** depende
  da **quantidade + tipo de cálculo** (`Até`/`Acima de`); **Tabela de Preço** (aba Custos, AR-PROD-105)
  é o valor do produto **independente de quantidade**, podendo ser ligado ao **tipo de venda** ou
  **vinculado ao cliente**. São dois eixos de preço distintos — ambos precisam existir no novo.
- **Modelo de Grade** = cadastro reutilizável próprio (menu Produto → Modelo de Variação, `Tipo da Grade`
  + itens) — avaliar equivalente no oimpresso (hoje a grade é montada ad-hoc, sem "modelo").
- **Estoque negativo** = "Controla Estoque" desligado no produto; no oimpresso é `enable_stock=0` +
  flag "bloquear venda com estoque negativo" (AR-PROD-056 do doc principal). Preservar "não bloqueia".

---

## Dúvidas da aba Variação — todas resolvidas (Wagner 2026-07-13)

1. ✅ **Produto Vinculado** — os filhos aparecem na lista/busca; mas há também o caso **sem filho vinculado** (só preço-por-qtd no próprio produto) — AR-PROD-178.
2. ✅ **Preço por quantidade × Tabela de Preço** — Preço por qtd depende de quantidade + tipo de cálculo (`Até`/`Acima de`); Tabela de Preço independe de quantidade e liga a tipo de venda/cliente — ver Nota de migração.
3. ✅ **Modelo de Grade** — cadastra-se em **menu Produto → Modelo de Variação** (`Tipo da Grade` + itens, ex "PLUS SIZE" TAMANHO G1/G2/G3) — AR-PROD-187.
4. ✅ **Estoque negativo** = "Controla Estoque" desligado no produto — AR-PROD-175.
5. ✅ **Porcentagem** = desconto **ou acréscimo** (% negativo → acréscimo, valor final maior) — AR-PROD-177.

> **Nenhuma dúvida aberta.** Contexto extra capturado do menu Produto (print 21): os tipos de produto
> são entradas próprias — Adicionar Produto · Variação por (Tamanho/Cor/Preço) · Matéria Prima · Serviço
> · Composição · Patrimônio · Personalizado · Uso e Consumo (casam com as flags `TEM_*` do `PRODUTO`).

---

**Aba Variação concluída** (`AR-PROD-170..187`). Este documento complementa o principal
(`ANTI-REGRESSAO-cadastro-produto-legacy.md`) — juntos cobrem o cadastro de produto inteiro:
produto padrão (8 abas) + Composição/Kit + Variação/Grade.
