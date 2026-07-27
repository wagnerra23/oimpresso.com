---
id: memory-requisitos-produto-telas-quick-add-casos
casos: Cadastro rápido inline · GET /products/quick_add → POST /products/save_quick_product
irmaos: SDD-tela-cadastro-produto-v1.0.md §6.1 CU-PROD-08 (âncora) · Produto/Create.casos.md (o cadastro completo)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é o caminho de cadastro com menos validação do módulo, e roda no meio de uma venda — com o cliente na frente, pressa, e o preço digitado virando o preço da venda aberta.
owner: wagner
last_run: "2026-07-27"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane Estoque · MySQL"
---

# Casos de Uso & Aceite — Cadastro rápido inline (fluxo sem tela React)

> **Âncora:** `CU-PROD-08` (quick-add inline, sem sair do fluxo), `CU-PROD-01` (cadastro simples —
> SKU server-side + parser pt-BR) e `CU-PROD-10` `[T0]` (multi-tenant) do
> [SDD §6.1](../SDD-tela-cadastro-produto-v1.0.md), cruzados com o **contrato de paridade Delphi**
> ([ANTI-REGRESSAO-cadastro-produto-legacy.md](../ANTI-REGRESSAO-cadastro-produto-legacy.md) —
> `AR-PROD-006`/`AR-PROD-008` `[V0]`) e com a **Blade que define o formulário real**
> (`product/partials/quick_add_product.blade.php` + `single_product_form_part.blade.php`).
> Os UCs derivam do **contrato**, **nunca** do `saveQuickProduct()` (`proibicoes.md` §5, 2026-06-05).
>
> **Como este arquivo nasceu:** agent `sdd-from-source` ([ADR 0351](../../../decisions/0351-sdd-from-source.md)),
> fechando a lacuna `CU-PROD-08 sem UC` do painel [`_STATUS-GENERATED.md`](../_STATUS-GENERATED.md).
> O [`Create.casos.md`](../../../../resources/js/Pages/Produto/Create.casos.md) já apontava este
> buraco: *"Quick-add inline — `CU-PROD-08`. Rota e contrato separados (`save_quick_product`);
> merece [caso próprio]"*.
>
> **Status:** ✅ passa (prova na lane) · 🧪 teste cita o UC (veredito pendente) ·
> ⬜ não verificado · ❌ quebrou · 🔶 decisão [W].

---

## ⚠️ Onde este fluxo é chamado (varredura contada, 2026-07-27, sha `16606e35c4`)

O modal de cadastro rápido de **produto** é aberto por **10 Blades**, todas de venda ou compra —
e por **0** telas React:

| Origem | Arquivo |
|---|---|
| Compra | `purchase/create.blade.php` · `purchase/edit.blade.php` |
| Ordem de compra | `purchase_order/create.blade.php` · `purchase_order/edit.blade.php` |
| PDV | `sale_pos/partials/pos_form.blade.php` · `pos_form_edit.blade.php` · `sale_pos/create_old.blade.php` · `sale_pos/edit_old.blade.php` |
| Venda | `sell/create.blade.php` · `sell/edit.blade.php` |
| Busca sem resultado (JS) | `public/js/purchase.js` → `/products/quick_add?product_name=<termo>` |

Re-medir com:

```
git grep -n "'quickAdd'" -- resources/views/ | wc -l     # → 10
git grep -n "quick_add\|quickAdd" -- resources/js/       # → só cliente/veículo, nenhum produto
```

> ⚠️ **Não confundir com os homônimos.** `quick_add` aparece também em `contact.create`
> (cliente), `unit/create`, `brand/create` e em `Sells/_components/CustomerSearchAutocomplete.tsx`
> (`quickAddOpen`, cliente) e `Sells/Create.tsx` (`quickAddVehicleOpen`, veículo). **Nenhum deles é
> este fluxo.** Comparar contra o homônimo daria "paridade OK" falsa — mesma armadilha da Blade
> homônima do `show()` documentada no `CU-PROD-14`.

⚖️ **Força do veredito destes UC — `advisory`.** Lane `PHP / Pest (Estoque · MySQL)`, fora do
[`required-checks-baseline.json`](../../../../governance/required-checks-baseline.json):
**reprovação é visível e não bloqueia merge.**

---

## Rastreabilidade

| UC | Caso de uso | Prio | Contrato | Teste | Status |
|----|-------------|------|----------|-------|--------|
| UC-PQCK-01 | Carimba o business da sessão e cria a variação vendável | must `[T0]` | `CU-PROD-08`.3 + `CU-PROD-08`.2 + [ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md) | `QuickAddProdutoContratoTest` | 🧪 (verde esperado) |
| UC-PQCK-02 | Categoria de outro business não é aceita | must `[T0]` | `CU-PROD-10`.1 + precedente `UC-PTAB-04` | `QuickAddProdutoContratoTest` | 🧪 (vermelho **esperado** — predição) |
| UC-PQCK-03 | Preço pt-BR (`"1.234,56"`) persiste 1234.56 | must `[V0]` | `CU-PROD-01`.4 + `AR-PROD-006/008` + REGRA MESTRE | `QuickAddProdutoContratoTest` | 🧪 (verde esperado) |
| UC-PQCK-04 | SKU vazio é gerado pelo servidor (produto e variação) | must | `CU-PROD-08`.1 + `CU-PROD-01`.2 | `QuickAddProdutoContratoTest` | 🧪 (verde esperado) |

> 🧪 **e não ✅/❌**: eu não rodo teste (CT 100 · [ADR 0062](../../../decisions/0062-separacao-runtime-hostinger-ct100.md)).
> "Vermelho esperado" é **predição** derivada de leitura + varredura contada — o veredito é da lane.

---

## UC-PQCK-01 · Carimba o business da sessão e cria a variação vendável · `must` `[T0]`

- **Persona:** Larissa está com a venda aberta, o produto não existe, ela clica no `+`. O tenant
  **não é escolha dela** — vem da sessão. E o produto tem que voltar utilizável: se nascer sem
  variação, ela não consegue adicioná-lo à venda que motivou o cadastro.
- **Aceite:** *Dado* o payload mínimo do modal · *Quando* o `POST /products/save_quick_product`
  roda · *Então* o produto existe (pré-condição), `business_id` é o da sessão, e há ≥1 variação.
- **Teste:** [`QuickAddProdutoContratoTest`](../../../../tests/Feature/Produto/QuickAddProdutoContratoTest.php)
  — `UC-PQCK-01 · quick-add carimba o business da sessão e cria a variação vendável`.
- **Contrato:** `CU-PROD-08` item 3 `[T0]` (*"produto criado no business atual"*) + item 2 `[reg]`
  (*"não perde o contexto de origem (venda/OC) ao voltar"* — a perna verificável em servidor é
  *"volta utilizável"*) + [ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** o writer monta o payload com `$request->only($form_fields)` — uma
  lista de **34 campos** (14 nomeados + `product_custom_field1..20`) — e **sobrescreve**
  `business_id`/`created_by` a partir da sessão logo
  depois. A ordem importa: se o `business_id` fosse setado antes do `only()`, ou se `business_id`
  entrasse na lista de campos, o cliente escolheria o tenant. Nada testava isso.
  A perna (b) trava o `createSingleProductVariation` — sem ele o produto é invisível pra venda.
- **Status: 🧪** — verde esperado (trava de invariante).

---

## UC-PQCK-02 · Categoria de outro business não é aceita · `must` `[T0]`

- **Persona:** qualquer tenant. O modal manda `category_id`, `brand_id`, `unit_id` e `tax` como
  ids crus de `<select>`.
- **Aceite:** *Dado* que uma categoria **minha** persiste no produto (pré-condição — prova que o
  campo chega ao banco) · *Quando* repito a requisição com `category_id` de **outro** business ·
  *Então* o produto criado **não** fica classificado naquela categoria.
- **Teste:** [`QuickAddProdutoContratoTest`](../../../../tests/Feature/Produto/QuickAddProdutoContratoTest.php)
  — `UC-PQCK-02 · categoria de outro business não é aceita no quick-add (Tier 0)`.
- **Contrato:** `CU-PROD-10` item 1 `[must][T0]` (*"o próximo model pendurado em `Product` nasce
  com o mesmo buraco"*) + [ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md).
  A **Blade já cumpre a metade da UI**: os dropdowns vêm de `Category::forDropdown($business_id)`,
  `Brands::forDropdown($business_id)`, `Unit::forDropdown($business_id)` e
  `TaxRate::forBusinessDropdown($business_id)` — todos escopados. O que falta é o servidor **não
  confiar** no que a UI devolveu.
- **Regressão que defende (o achado):** varredura contada em `saveQuickProduct`: **0** consultas
  que validem `category_id`/`brand_id`/`unit_id`/`tax` contra o business — os quatro entram via
  `$request->only($form_fields)` e vão direto pro `Product::create`. É a **quarta instância** da
  mesma família de defeito: `price_group_id` cru (`UC-PTAB-04`, vermelho em CI,
  [#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300)) → `bulkUpdate` (`UC-PBULK-03`) →
  `component_variation_id` no BOM (`UC-PBOM-02`) → aqui.
  > **Impacto honesto:** não é vazamento de *dado* (o produto criado é meu); é **contaminação de
  > taxonomia** — meu catálogo classificado por uma categoria que não existe pra mim, sumindo dos
  > meus filtros e aparecendo com nome alheio em relatório por categoria. Sério, mas menor que o
  > `UC-PTAB-04` (que escrevia **preço** em tabela alheia).
  > O assert é **neutro quanto ao remédio**: rejeitar a requisição ou ignorar o campo passam os dois.
- **Status: 🧪** — vermelho **esperado** (predição; veredito da lane).

---

## UC-PQCK-03 · Preço pt-BR (`"1.234,56"`) persiste 1234.56 · `must` `[V0]`

- **Persona:** Larissa digita o preço às pressas, com o cliente esperando. Esse número vira o
  preço da linha da venda que está aberta — não há segunda conferência.
- **Aceite:** *Dado* `single_dsp = "1.234,56"` e `single_dpp = "999,99"` · *Quando* o produto é
  criado · *Então* a variação guarda `1234.56` e `999.99`.
- **Teste:** [`QuickAddProdutoContratoTest`](../../../../tests/Feature/Produto/QuickAddProdutoContratoTest.php)
  — `UC-PQCK-03 · preço em pt-BR ("1.234,56") persiste 1234.56 no quick-add (V0)`.
- **Contrato:** `CU-PROD-01` item 4 `[V0]` (*"preço de custo e venda passam pelo parser pt-BR sem
  ×100 (`num_uf`); arredondar 2 casas"*) + `AR-PROD-008` `[V0]` (parser pt-BR sem inflar ×100) +
  `AR-PROD-006` `[V0]` (precisão do custo) + REGRA MESTRE (`proibicoes.md` Tier 0 — origem:
  incidente 2026-06-05, biz=4, venda inflada ~×100k).
- **Regressão que defende:** o `num_uf` mora no `createSingleProductVariation`, não no controller —
  ou seja, a defesa está **uma camada abaixo** de onde o payload chega, e o `saveQuickProduct`
  aplica `num_uf` explicitamente só em `alert_quantity` e `expiry_period`. Se alguém trocar a
  chamada por um `Variation::create` direto (o atalho óbvio pra "simplificar"), o parser some sem
  ruído. Mesmo remédio de `UC-PBULK-06` e `UC-PINIC-03`, **caminho de código diferente** — nenhum
  prova o outro.
- **Status: 🧪** — verde esperado (trava `[V0]`).

---

## UC-PQCK-04 · SKU vazio é gerado pelo servidor (produto e variação) · `must`

- **Persona:** o campo SKU **não** é obrigatório no modal (a Blade não o marca `required`), então
  deixar em branco é o caminho **normal**, não a exceção. Produto sem código quebra etiqueta,
  leitura de código de barras e a busca por código na própria venda que o originou.
- **Aceite:** *Dado* `sku = ""` · *Quando* o produto é criado · *Então* `products.sku` não fica
  vazio nem só-espaço, **e** a variação nasce com `sub_sku` preenchido.
- **Teste:** [`QuickAddProdutoContratoTest`](../../../../tests/Feature/Produto/QuickAddProdutoContratoTest.php)
  — `UC-PQCK-04 · SKU vazio no quick-add é gerado pelo servidor (não fica em branco)`.
- **Contrato:** `CU-PROD-08` item 1 (*"cadastra mínimo (nome+SKU+preço)"*) + `CU-PROD-01` item 2
  (*"SKU vazio → gerado **server-side**"*) + Blade `quick_add_product.blade.php` (campo
  opcional, com tooltip explicando que será gerado).
- **Regressão que defende:** o writer grava `sku = ' '` (**um espaço**) como marcador antes do
  `create` e só depois chama `generateProductSku($product->id)` — a geração depende do id, então
  precisa de dois passos. Se a segunda etapa cair (ou for movida pra depois do
  `createSingleProductVariation`), o produto e a variação ficam com **um espaço** por código:
  não-vazio para qualquer `empty()`, inútil para o operador. A perna (b) existe porque a busca da
  venda procura em `variations.sub_sku` — se o pai for corrigido e o filho não, o produto
  recém-criado não é encontrável.
- **Status: 🧪** — verde esperado (trava de invariante).

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> ⚠️ Este diretório **não** é varrido pelo `casos-coverage-guard` (que vê só `Pages/**`), então o
> G-2 não pune um órfão aqui — o critério de parada é disciplina, não gate.

- **[BACKLOG] "Não perde o contexto de origem" (`CU-PROD-08` item 2 `[reg]`) é comportamento de
  JS, não de servidor.** O retorno do writer é um array (`product` + `variation` + `locations`)
  consumido por `public/js/pos.js` / `purchase.js`, que injetam o item na venda/compra aberta.
  Provar isso exige e2e (Playwright) sobre a Blade legada — fora do alcance do Pest de contrato.
  Vira UC quando houver spec e2e; hoje seria promessa sem defesa.
- **[BACKLOG] Estoque inicial no quick-add só existe quando `product_for == 'pos'`.** Medido: a
  Blade inclui `quick_product_opening_stock` **apenas** nesse caso
  (`quick_add_product.blade.php`), enquanto o writer aceita `opening_stock` de qualquer
  origem. Quem cadastra a partir de uma **compra** não informa saldo; quem cadastra a partir do
  **PDV** informa. Assimetria deliberada (na compra o saldo entra pela própria nota) ou omissão?
  Decisão [W] — não vira UC porque o assert escolheria.
- **[BACKLOG] Sem `validate()` — nenhuma regra de validação server-side.** Varredura contada em
  `saveQuickProduct`: **0** ocorrências de `$request->validate` / `FormRequest`. O `required` de
  `name`/`unit_id`/`barcode_type`/`tax_type` existe **só no jQuery Validate da Blade**. Um POST
  direto sem `name` cai no `catch` genérico e devolve *"algo deu errado"*. Divergência aberta —
  o remédio (FormRequest? validate inline?) é decisão [W], e o irmão `store()` tem o mesmo padrão.
- **[BACKLOG] SKU digitado duplicado não é bloqueado neste caminho.** `CU-PROD-01` item 2 promete
  *"SKU digitado → validado duplicado"*; o `checkProductSku` existe como endpoint separado
  (`POST /products/check_product_sku`) e é chamado pela Blade do cadastro **completo**. No
  quick-add o campo não tem essa amarração. Vira UC quando [W] decidir se a unicidade é invariante
  de servidor (e aí vale pros dois caminhos) ou cortesia de UI.
- **[BACKLOG] Paridade Delphi do cadastro rápido.** O Office Comercial não tem um "cadastro
  rápido" separado — o operador abre a ficha completa. **Não há `AR-PROD-*` para este fluxo**, o
  que é informação, não lacuna: o quick-add é capacidade **nova** do oimpresso, então a régua aqui
  é o mercado (Bling/Tiny têm), não o legado. Registrado pra evitar que uma sessão futura invente
  um anti-padrão legado que nunca existiu (`proibicoes.md` §5, 2026-07-16).

---

## Refs

- SDD (âncora dos CU): [`SDD-tela-cadastro-produto-v1.0.md`](../SDD-tela-cadastro-produto-v1.0.md)
  §5.3 **F11** (fluxo do quick-add) + §6.1 `CU-PROD-08` / `CU-PROD-01` / `CU-PROD-10`
- Paridade Delphi: [`ANTI-REGRESSAO-cadastro-produto-legacy.md`](../ANTI-REGRESSAO-cadastro-produto-legacy.md)
  (`AR-PROD-006`/`AR-PROD-008`)
- Blade que define o formulário: `resources/views/product/partials/quick_add_product.blade.php`
  (+ `single_product_form_part.blade.php`, `quick_product_opening_stock.blade.php`)
- Controller: `app/Http/Controllers/ProductController.php` — `quickAdd()` e `saveQuickProduct()`.
  Re-localize com `grep -n "function quickAdd\|function saveQuickProduct" app/Http/Controllers/ProductController.php`
- Irmão (cadastro completo, mesma família de contrato):
  [`Produto/Create.casos.md`](../../../../resources/js/Pages/Produto/Create.casos.md) (`UC-PCAD-01..06`)
- Painel da cadeia: [`_STATUS-GENERATED.md`](../_STATUS-GENERATED.md)
- Lane: `PHP / Pest (Estoque · MySQL)` (**advisory** — fora do `required-checks-baseline.json`)
