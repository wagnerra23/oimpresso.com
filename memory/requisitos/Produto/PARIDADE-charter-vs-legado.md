---
titulo: "Paridade — Charters React × Cadastro de Produto legado (mapa de gaps do cutover)"
tipo: paridade
module: Produto
status: ativo
owner: wagner
gerado: 2026-07-13
fontes:
  - resources/js/Pages/Produto/Create.charter.md (+ 7 charters irmãos)
  - memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md
  - memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-variacao-legacy.md
  - memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md
observacao: "Cruzamento contrato-vivo × comportamento legado. Base de decisão do cutover — o que falta virar charter/US antes de aposentar o Delphi."
---

# Paridade — Charters React × Cadastro de Produto legado

> **Pergunta que este doc responde:** o `Create.charter.md` (e irmãos) é o contrato de paridade
> suficiente pra migrar o cadastro de produto do Office Comercial (Delphi) pro oimpresso? **Não.**
> O charter de Create cobre ~10-15% da tela legada (é um *Wave 2 draft* do formulário básico).
> Este doc mapeia, item a item, o que **já está no charter**, o que mora em **telas-irmãs**, e o
> que **não tem casa em nenhum charter** — que é o backlog real do cutover.
>
> **Insumos:** `Create.charter.md` (`status: draft`, Tier A, charter_version 1) + a lista
> anti-regressão de ~140 itens (`ANTI-REGRESSAO-cadastro-produto-legacy.md` + `-variacao-legado.md`).

---

## 0. Resumo executivo

| Métrica | Valor |
|---|---|
| Itens anti-regressão catalogados | **~140** (`AR-PROD-001..187`) |
| Cobertos pelo `Create.charter.md` | **~9 distintos** (era "~15" — corrigido 2026-07-16, §1) |
| Mapeáveis a telas-irmãs (charter existe, cobertura parcial) | ~25 |
| **Sem casa em nenhum charter atual** | **~100** ⚠️ |
| Colisão direta charter Non-Goal × legado | **2 áreas inteiras** (Composição, Variação) 🔴 |
| **Falso-crédito corrigido** | **2 itens** — `AR-PROD-014` (Tipo) e `AR-PROD-007` (Margem %) estavam creditados ao charter e **não estão nele** (§1) |

**Leitura:** o `Create.charter` é honesto sobre seu escopo mínimo, mas **não é o contrato de paridade**
da migração. O cadastro legado é uma tela-mãe de 8 abas + Composição + Variação; a arquitetura nova
espalha isso em 8 páginas — e a maioria das funções de valor/fiscal/produção ainda **não foi contratada**.

---

## 1. O que o `Create.charter.md` COBRE ✅ (~9 itens distintos)

> ⚠️ **Corrigido 2026-07-16.** A tabela original creditava **~15** e continha **2 falso-créditos** +
> **3 contagens duplas**. Reverificado campo a campo contra o `Create.charter.md` (Goals), o
> `Create.tsx` (461 linhas, enumerado) e o dicionário `memory/dominio/estoque.md`.

**Cobertura real do cabeçalho legado (`AR-PROD-001..014`) — a "aba geral":**

| # | Campo legado | AR | Charter | Veredito |
|---|---|---|---|---|
| 2 | Descrição | 002 | `name` | ✅ |
| 5 | Unidade | 005 | `unit` | ✅ |
| 11 | Categoria | 011 | `category` | ✅ |
| 1 | Código (`SG03#`) | 001 | `sku` | 🟡 provável, não declarado 1:1 |
| 10 | Código EAN | 010 | `barcode_type` (Avançado) | 🟡 é o **tipo** do código, não o EAN |
| 12 | Quant. Estoque | 012 `[V0]` | card "Estoque" | 🟡 agregado + 2 ícones do cabeçalho ausentes |
| 3 · 4 · 9 · 13 | Ativo S/N · Última Alteração · Cód. Fábrica · data de Cadastro | 003, 004, 009, 013 | — | ❌ ausentes |
| 6 · 7 · 8 | **R$ Custo · Margem % · R$ Valor** | 006, 007, 008 `[V0]` | — | ❌ ausentes do charter **e da tela React** — mas **existem no oimpresso** (Blade + `variations` + `product.js`). Ver ⚠️ + §1.1 |
| 14 | Tipo | 014 | `type` | 🔴 **falso-crédito** (ver abaixo) |

**Outras áreas creditadas (fora do cabeçalho):** `AR-PROD-042` (Observações → `description`) ✅ ·
`AR-PROD-050/051/055` (opening stock, parcial) 🟡 · `AR-PROD-053` (estoque mín/máx → `alert_quantity`) 🟡
— `alert_quantity` é alerta, não o par mín/máx do legado.

### ⚠️ Os 3 campos de dinheiro não existem — nem no charter, nem na tela

O `Create.charter` declara 5 seções Card, entre elas **"Preço & Imposto"**. Mas os 8 campos visíveis que
ele enumera são `name · sku · type · unit · category · brand · tax · alert_quantity` — **nenhum é preço**.
Verificado no código (`Create.tsx`, 461 linhas, enumerado 2026-07-16): os Cards reais são
**Identificação · Preço & Imposto · Estoque**, e a busca por `price`/`preço`/`custo`/`margem` retorna
apenas `sellingPriceGroupCount` (uma contagem), o título do card, e o label `tax_type`
("Imposto inclui no preço?"). **A tela React de cadastro não define preço de produto.** O card promete
preço e entrega imposto.

> ⚠️ **Escopo da frase acima: é sobre a TELA REACT, não sobre o oimpresso.** Os três campos **existem**
> no produto — no Blade que roda hoje, nas colunas de `variations` e no `product.js`. O gap é do
> `Create.tsx`. Ver §1.1. *(Precisão corrigida 2026-07-17 — a redação anterior dizia "ausentes do
> charter E do código" e generalizava indevidamente de `Create.tsx` pro sistema inteiro.)*

---

## 1.1 Como o oimpresso trata esses campos hoje (verificado 2026-07-17)

> Varredura de **todas** as migrations de `products` + leitura do Blade de cadastro + `product.js`.
> Responde "onde cada campo da aba geral vive no oimpresso" — e mostra que o gap é **da tela React**,
> não do sistema.

### Mapa dos 14 campos

| Campo legado | oimpresso | Tabela |
|---|---|---|
| Descrição | `name` | `products` |
| Código | `sku` | `products` |
| Ativo S/N | `is_inactive` | `products` (migration 2019) |
| Unidade | `unit_id` | `products` |
| Categoria | `category_id` + `sub_category_id` | `products` |
| Última Alteração | `updated_at` | `products` (`timestamps()`) |
| data de Cadastro | `created_at` | `products` |
| **R$ Custo** | `default_purchase_price` + `dpp_inc_tax` | **`variations`** |
| **Margem %** | `profit_percent` | **`variations`** |
| **R$ Valor** | `default_sell_price` + `sell_price_inc_tax` | **`variations`** |
| Quant. Estoque | `qty_available` | `variation_location_details` |
| Código EAN | `barcode_type` cobre o formato (`EAN-13`/`EAN-8`/`UPC-A`…) | `products` |
| Cód. Fábrica | sem coluna própria — cairia em `product_custom_field1..20` | `products` |
| **Tipo** (PRODUTO/SERVIÇO) | **não existe** (`products.type` é outra coisa — §1 falso-crédito 1) | — |

### A diferença de topologia: o dinheiro mora na VARIAÇÃO, não no produto

**`products` não tem nenhuma coluna de dinheiro.** Varredura de todas as migrations de `products`:
**zero** ocorrências de `profit_percent`, `default_sell_price`, `default_purchase_price`. Os três são
`decimal(22,4)` em **`variations`** (migration `2017_08_10_061216_create_variations_table.php`).

| | Legado | oimpresso |
|---|---|---|
| Onde o preço mora | `PRODUTO.CUSTO` / `.VALOR` / `.MARGEM` — **no produto** | `variations.default_purchase_price` / `.default_sell_price` / `.profit_percent` — **na variação** |

Até um produto `single` tem uma **variação DUMMY interna** que carrega o preço (idioma UltimatePOS —
`memory/dominio/estoque.md`). É a **mesma classe de diferença** do Preço Especial (§2): a capacidade
existe, a topologia é outra.

### O Blade tem os 5 campos — todos `required`

`resources/views/product/partials/single_product_form_part.blade.php`: `single_dpp` (custo exc.) ·
`single_dpp_inc_tax` · `profit_percent` (com tooltip) · `single_dsp` (venda exc.) · `single_dsp_inc_tax`.
Todos com `'required'`.

### O binding do oimpresso é o MESMO do Delphi

`public/js/product.js:37-54` — ao mudar o custo, recalcula o valor a partir da margem:

```js
var selling_price = __add_percent(purchase_exc_tax, profit_percent);
```

E as funções (`public/js/functions.js`) são algebricamente idênticas às do Delphi:

| oimpresso | Delphi (`UnitFuncoes.pas`) | Fórmula |
|---|---|---|
| `__add_percent(amount, pct)` | `PercAdd(AValor, APerc)` | `amount × (1 + pct/100)` |
| `__get_rate(principal, amount)` | `PercAplicado(ATotal, AValor)` | `((amount − principal) / principal) × 100` |

**Custo é a âncora nos dois; Valor↔Margem se recalculam.** A investigação não achou divergência a
corrigir — achou **convergência a preservar**. (O oimpresso usa `Decimal.js`, não float nativo — mais
seguro que o Delphi nesse ponto.)

### O diagnóstico correto

O gap **não** é "o oimpresso não sabe precificar". É: **o `Create.tsx` é um subconjunto do Blade que
ele deveria substituir.** O Blade exige custo/margem/valor como obrigatórios; o React não tem onde
digitá-los. Logo o `Create.tsx` **não consegue substituir o Blade** — não por falta de polimento, por
falta de função. É o que mantém as 8 telas `draft` atrás da flag `X-Inertia` (US-PROD-023).

Isso **não elimina** a fronteira com a Formação de Preço (`AR-PROD-090..103`, §3): o legado tem markup
composto, rendimento da última compra e valor mínimo, que o `profit_percent` simples não cobre. Ver §5
item 1.

> ⚠️ **Sem teste.** Isto é leitura de schema + Blade + JS, enumerada. Não há Pest cobrindo o binding do
> `product.js` nem a paridade Blade↔React. Vale como mapa, não como contrato.

### 🔴 Falso-crédito 1 — `AR-PROD-014` (Tipo): mesmo nome, outro conceito

| | Legado | Charter |
|---|---|---|
| **Campo** | `Tipo` (dropdown, ex: `PRODUTO`) | `type` (default `single`) |
| **O que é** | `PRODUTO_TIPO` — **tabela configurável** de natureza do item (PRODUTO/SERVIÇO/matéria-prima/uso-e-consumo) + flags `TEM_*` | `products.type` do UltimatePOS — **estrutura de variação** |
| **Valores** | configuráveis por business | `{single, variable, modifier}` (`memory/dominio/estoque.md`) |

São conceitos distintos. O charter **não** cobre a natureza do item — e o `Edit.charter` reforça o
engano ao declarar Non-Goal *"Mudar `type` (Single/Variable/Combo)"*, que fala da estrutura, não do Tipo.

> ⚠️ **Bônus:** o charter cita **`combo`** (Mission + Non-Goals + Edit), mas o dicionário de domínio diz
> `products.type ∈ {single, variable, modifier}` e **"`combo` não existe no enum atual — não inventar"**.
> Esse enum é machine-checked pelo `dominio:check` ([ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) G-4, **required**). Ver §4.

### 🔴 Falso-crédito 2 — `AR-PROD-007` (Margem %) creditado a "SKU server-side + duplicate"

A linha original creditava `AR-PROD-002, 007 (dup), 010` à feature *"SKU server-side + duplicate `?d=N`
+ multi-tenant scope"*. Mas `AR-PROD-007` é a **Margem %** — um campo `[V0]` de dinheiro, sem relação
com SKU ou duplicação, e **ausente do charter e do código**. A anotação `(dup)` sugere que se quis dizer
"duplicate", mas o número aponta pra outro item. Além disso `002` e `010` já constavam em linhas
anteriores (**contagem dupla**, junto com `001`, que aparecia em duas) — é o que inflava o "~15".

> **Por que isso importa:** um `[V0]` marcado como coberto sem estar é a **mesma classe de erro** que o
> [#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300) achou no `SellingPrices.charter` — o §Pest GUARD prometia `it('Controller cross-tenant
> retorna 404')` desde 2026-05-15 e **esse teste nunca existiu**. Documentação afirmando cobertura que
> o código não tem é instrução ativa pra regressão ([proibicoes.md](../../proibicoes.md) §Precedência: *"o charter pode estar
> ERRADO e ainda é lei"*).

> O charter acerta os invariantes Tier 0 (business_id, sem sessionStorage, SKU server-side) —
> alinhado com o SDD §3. A crítica acima é de **cobertura**, não de qualidade do que ele declara.

---

## 2. Legado que mora em TELAS-IRMÃS (charter existe, cobertura parcial) 🟡

Na arquitetura nova, abas do legado viram páginas separadas. Precisam do seu próprio cruzamento:

| Aba legada | Itens | Página nova / charter | Estado |
|---|---|---|---|
| Estoque › Histórico de Movimento (kardex) | AR-PROD-060..065 | `StockHistory.charter.md` | 🔴 grade 47 "fachada" (`movements` undefined) — G-01 do SDD |
| Custos e Tabelas de Preços | AR-PROD-090..109 | `SellingPrices.charter.md` / `Unificado` | 🟡 multiplicador oco (G-02); Formação de Preço ausente |
| **Preço Especial por cliente** | AR-PROD-111..116 | `SellingPrices` (trio fechado, [#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300)) | ✅ **topologia substituída** — ver abaixo |
| Estoque › Fornecedor | AR-PROD-070..075 | `Unificado` (insumos) | ❌ `fornecedor => null` (C18 do SDD) |
| Estoque › Compras | AR-PROD-080..084 | `Unificado` / `Show` | ❌ sem cobertura |
| Estoque › Geral + saldo por local | AR-PROD-050..057, 144..145 | `StockHistory` / `Unificado` | 🟡 parcial |

### Preço Especial — a topologia mudou (decisão [W] 2026-07-15)

Estava listado no §3 como *"sem casa em nenhum charter"*. **Não está mais** — a capacidade foi
entregue por um **desenho diferente**, decidido pelo [W] e registrado no
[`SellingPrices.casos.md`](../../../resources/js/Pages/Produto/SellingPrices.casos.md):

> *"a tabela nasce **fora** do produto (`/produto/unificado?tela=tabelas`); aqui o operador **seleciona**
> a tabela e define o preço que o produto assume quando ela for aplicada; **depois** a tabela é vinculada
> ao cadastro do cliente ou a um tipo de venda. O produto **nunca** é vinculado direto ao cliente."*

| | Legado (`AR-PROD-111`) | Novo (decisão [W]) |
|---|---|---|
| **Topologia** | produto → cliente (lookup dentro do produto) | produto → **tabela** → cliente |
| **Onde vive** | aba "Preço Especial" do cadastro | `/products/add-selling-prices/{id}` + vínculo no cadastro do cliente |

Isso **revoga** `AR-PROD-111` como comportamento a preservar: o lookup de cliente dentro do produto
contradiz a decisão. O trio da tela está fechado (charter v2 + `casos.md` + `TabelaPrecoContratoTest`
com 4 UCs rodando em MySQL real).

> ⚠️ **Resíduo não verificado** — o legado tinha, por cliente: `% Acréscimo` / `% Desconto` sobre o
> `Valor Original` (AR-PROD-112/113) e o checkbox **"Manter Desconto"** (AR-PROD-114). O modelo novo tem
> `price_type ∈ {fixed, percentage}` por célula, o que cobre o eixo percentual — mas **não confirmei**
> equivalência item a item, nem onde "Manter Desconto" pousa. Não tratar como coberto sem checar.

---

## 3. Legado SEM CASA em nenhum charter ⚠️ (o backlog real do cutover · ~100 itens)

Nenhuma página/charter atual contempla estas áreas — cada uma precisa virar charter + US:

| Área legada | Itens | Por que importa |
|---|---|---|
| **Aba Fiscal** (NCM · CEST · origem · grupo imposto · PAF-ECF IAT/IPPT · pesos) | AR-PROD-124..130 | o charter só tem o campo `tax`; NF-e depende disso |
| **Formação de Preço** (markup composto · rendimento última compra · dimensões Larg/Comp/Espessura · valor mínimo · flags pode comprar/vender/movimenta estoque) | AR-PROD-090..103 | é o motor de custo/margem — Tier 0 valor. **Inclui os 3 campos de dinheiro do cabeçalho** (`AR-PROD-006/007/008`), que o `Create.charter` não tem (§1) |
| **Anexo** (visibilidade cadastro/venda/produção · caminho de rede) | AR-PROD-117..123 | charter diz "1 imagem só"; é a "arte anexada" (F4 do SDD) |
| **Atividade** (histórico de alterações do cadastro) | AR-PROD-131..134 | auditoria append-only |
| **Ícones de estoque do cabeçalho** (ajuste manual E/S · saldo por local) | AR-PROD-012, 140..145 | movimento de estoque manual |
| **Excluir** (soft-delete → inativo + filtro) | AR-PROD-022 | Create é create-only; falta o ciclo de exclusão |
| **Dados Adicionais** (Plano de Contas · Marca) | AR-PROD-040..041 | vínculo contábil |

---

## 4. Colisão direta charter Non-Goal × legado 🔴 (maior risco de paridade)

O `Create.charter` **declara Non-Goal** exatamente as duas features mais ricas — e mais valiosas pras
verticais comunicação visual/oficina — que o legado já tem por completo:

| Charter Non-Goal (adiado p/ "Wave 3") | Legado equivalente (anti-regressão) |
|---|---|
| ❌ "Variation builder dinâmico inline (variable — Wave 3)" | **Aba Variação inteira** — AR-PROD-170..187 (grade tam×cor · preço por quantidade com filho vinculado · tipo de cálculo Até/Acima de · % desconto ou acréscimo · Modelo de Grade reutilizável) |
| ❌ "Combo composition picker inline (combo — Wave 3)" | **Aba Composição inteira** — AR-PROD-150..168 (BOM multi-nível `ORDEM_ARVORE` · 11 fórmulas ÁREA QUADRADA/PERÍMETRO/ILHÓS/FOLHAS-CHAPA/BARRAS · planilha embutida · Produzir · Diferença no Valor) |

> ⚠️ São **as fórmulas de m²/perímetro/ilhós** (comunicação visual) e a **grade tam×cor** (vestuário/
> oficina) — o núcleo do diferencial vertical do oimpresso (SDD §1.0). O único charter existente as
> empurra pra "Wave 3" sem contrato. Também colide com o dicionário de domínio, que diz
> `products.type ∈ {single, variable, modifier}` — **`combo`/kit não existe** ("não inventar",
> `memory/dominio/estoque.md`): a composição legada tem que virar `ProductBom` + motor de fórmula,
> **não** `type=combo`.

---

## 5. Recomendação — roadmap de charters pro cutover

Ordem sugerida (cada um vira charter Tier A + US no SPEC + casos.md ancorado nos `AR-PROD-*`):

1. **Formação de Preço** (AR-PROD-090..103) — Tier 0 valor; destrava custo/margem correto. Pré-req de tudo que emite NF/vende.
2. **Aba Fiscal** (AR-PROD-124..130) — sem NCM/CEST/origem não emite NF-e.
3. **Composição/BOM + fórmulas** (AR-PROD-150..168) — a perna de comunicação visual (CV-01/CV-03 do SDD); a mais rica e a que o legado já resolvia.
4. **Variação/Grade** (AR-PROD-170..187) — grade tam×cor + preço por quantidade.
5. **Kardex real** (AR-PROD-060..065) — fecha a fachada `StockHistory` (G-01).
6. **Fornecedor + Compras + Anexo + Atividade + Excluir** (AR-PROD-070..084, 117..134, 022) — completam a paridade. *(Preço Especial saiu da fila — §2.)*

**Gate de cada item — o trio** ([ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md), `casos-gate` **required**): `.charter.md` (lei) + `.casos.md`
(contrato UC) + teste que **cita o UC**. G-1 exige o par charter+casos por tela roteada; G-2 reprova
UC órfão (caso no papel sem teste). As 7 telas de Produto estão no baseline da dívida
(`missing_casos: 243`) — e a regra F3 é **"cada tela tocada fecha o trio dela"**: mexer no `Create.tsx`
**obriga** a criar o `Create.casos.md`. Molde vivo: o [#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300) (`SellingPrices` — charter v2 + casos +
`TabelaPrecoContratoTest` em MySQL real).

> ⚠️ **Onde o pedido do dono entra — verificado 2026-07-16.** O canal real é o **chat**; o agente
> materializa downstream. **US no SPEC e UC no casos.md NÃO são canal de pedido** — foi proposto e
> **refutado** (2 céticos + 7 verificadores): o SPEC é o elo mais fraco da precedência e `_pendente_` já
> conta como coberto; UC sem teste **quebra o `casos-gate` required**. Ver [proibicoes.md](../../proibicoes.md)
> §5 entrada *"Eleger US (SPEC) ou UC (casos.md) como CANAL DE PEDIDO do dono"* +
> [how-trabalhar.md §Pedido de tela/feature](../../how-trabalhar.md). Os 4 slots onde a palavra do [W]
> vira máquina: `criar-tela.mjs (Mod/Tela, PT-0X)` · **Non-Goals + Automation Anti-hooks** do charter
> (só [W] preenche — `charter-write` é proibida de inferir) · `## Contrato visual` (copy literal) ·
> bullet **`[BACKLOG] <frase>` sem id** no `casos.md` (prosa honesta pré-teste, sem gate — vira UC
> quando ganhar teste que o cite). **É o slot certo pros campos ausentes do §1.**

---

## 6. Referências

- Charters: `resources/js/Pages/Produto/*.charter.md` (8, todas `draft`)
- Anti-regressão: [ANTI-REGRESSAO-cadastro-produto-legacy.md](ANTI-REGRESSAO-cadastro-produto-legacy.md) · [ANTI-REGRESSAO-cadastro-produto-variacao-legado.md](ANTI-REGRESSAO-cadastro-produto-variacao-legacy.md)
- SDD: [SDD-tela-cadastro-produto-v1.0.md](SDD-tela-cadastro-produto-v1.0.md)
- Manual legado: `memory/dominios/wr-comercial/modulos/estoque/tabelas/PRODUTO.md` (+ satélites)
- SPEC/gaps: [SPEC.md](SPEC.md) (US-PROD-020..026) · [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (61/100)

---

## Trilha do tempo

| Data | O que mudou |
|---|---|
| 2026-07-13 | Cruzamento criado a partir do `Create.charter.md` × lista anti-regressão (~140 itens). Conclusão: charter atual cobre ~15%; Composição e Variação (núcleo das verticais) estão como Non-Goal adiado. [CC] |
| 2026-07-17 | **§1.1 nova — como o oimpresso trata os 14 campos** (pergunta [F]). Varredura de todas as migrations de `products` + Blade + `product.js`: os 3 campos de dinheiro **existem** (em `variations`, `decimal(22,4)`), o Blade os exige (`required`), e o binding do `product.js` é **algebricamente idêntico** ao do Delphi (`__add_percent` ≡ `PercAdd`; `__get_rate` ≡ `PercAplicado`) — convergência a preservar, não divergência a corrigir. Diferença de topologia registrada: dinheiro na **variação** (oimpresso) vs no **produto** (legado). **Corrigida imprecisão** do §1 de ontem ("ausentes do charter E do código" generalizava de `Create.tsx` pro sistema). Diagnóstico refinado: o `Create.tsx` é **subconjunto do Blade** que deveria substituir — não consegue substituí-lo por falta de função (US-PROD-023). [CC] |
| 2026-07-16 | **Reverificação do mapa da "aba geral" (cabeçalho `AR-PROD-001..014`) + Preço Especial.** §0/§1: "~15 cobertos" → **~9 distintos** — 2 **falso-créditos** (`AR-PROD-014` Tipo: `products.type` é estrutura de variação, não `PRODUTO_TIPO`; `AR-PROD-007` Margem % creditado a "SKU server-side + duplicate") + 3 contagens duplas (001, 002, 010). §1: registrado que **`AR-PROD-006/007/008` não existem no charter NEM no código** — `Create.tsx` (461 linhas, enumerado) tem card "Preço & Imposto" **sem preço**; só `tax`/`tax_type`. §2: **Preço Especial movido do §3** — topologia produto→cliente **substituída** por produto→tabela→cliente (decisão [W] 2026-07-15 no `SellingPrices.casos.md`, trio fechado no #4300); resíduo `%acr`/`%desc`/"Manter Desconto" declarado **não verificado**. §5: gate de cada item reescrito pro **trio** (ADR 0264) + registrado o canal real de pedido (chat; US/UC **não** são canal — refutado 2026-07-16). [CC] |
