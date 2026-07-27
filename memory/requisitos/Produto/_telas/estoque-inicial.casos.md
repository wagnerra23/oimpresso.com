---
id: memory-requisitos-produto-telas-estoque-inicial-casos
casos: Estoque inicial do produto · GET /opening-stock/add/{produto} → POST /opening-stock/save
irmaos: SDD-tela-cadastro-produto-v1.0.md §6.1 CU-PROD-04 (âncora) · ANTI-REGRESSAO-cadastro-produto-legacy.md
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o saldo de abertura é o número de partida de TODO o kardex — errar aqui contamina custo, margem, alerta de reposição e ruptura de venda para sempre, e não há "desfazer" que reescreva o passado.
owner: wagner
last_run: "2026-07-27"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane Estoque · MySQL"
---

# Casos de Uso & Aceite — Estoque inicial (fluxo sem tela React)

> **Âncora:** `CU-PROD-04` (estoque inicial + localização + alerta + validade/lote) e
> `CU-PROD-10` `[T0]` (multi-tenant) do
> [SDD §6.1](../SDD-tela-cadastro-produto-v1.0.md), cruzados com o **contrato de paridade
> Delphi** ([ANTI-REGRESSAO-cadastro-produto-legacy.md](../ANTI-REGRESSAO-cadastro-produto-legacy.md),
> Office Comercial 2026.1.1.38 — `AR-PROD-051/052/053/055/057` `[V0]`), com o
> [DOC-RAIZ-ESTOQUE](../../Estoque/DOC-RAIZ-ESTOQUE.md) §3 (`opening_stock` → ENTRA) / §7
> (INV-6, saldo endereçado pelo par variação × local) e com a **Blade que define o payload
> real** (`resources/views/opening_stock/form-part.blade.php`).
> Os UCs derivam do **contrato**, **nunca** do `save()` — teste derivado do código é
> tautológico e trava o desvio em vez de pegá-lo (`proibicoes.md` §5, 2026-06-05).
>
> **Como este arquivo nasceu:** agent `sdd-from-source` ([ADR 0351](../../../decisions/0351-sdd-from-source.md)),
> fechando a lacuna `CU-PROD-04 sem UC` do painel [`_STATUS-GENERATED.md`](../_STATUS-GENERATED.md).
> Decisão [W] 2026-07-26: *"1 e 2 são requeridos sim. tem que ter tudo do blade"*.
>
> **Status:** ✅ passa (prova na lane) · 🧪 teste cita o UC (veredito pendente) ·
> ⬜ não verificado · ❌ quebrou · 🔶 decisão [W].

---

## ⚠️ Por que este contrato mora aqui e não ao lado de um `.tsx`

**Não existe tela React de estoque inicial.** Varredura contada nos **`.tsx`** de
`resources/js/Pages/Produto/` (2026-07-27, sha `16606e35c4`): o literal `opening_stock` aparece
**2×**, e as duas são o mesmo **booleano de permissão** nas props (`Edit.tsx`, `Index.tsx`) —
nenhuma tela informa, edita ou exibe saldo de abertura. Re-medir com:

```
git grep -n "opening_stock\|openingStock" -- 'resources/js/Pages/Produto/*.tsx'
```

> ⚠️ Sem o filtro `*.tsx` o mesmo grep devolve **4** linhas: as 2 acima + 2 menções em `.md`
> (`Produto/Index.casos.md`, `Estoque/Movimentacao.casos.md`). Prosa de contrato não é código —
> o denominador honesto é o dos `.tsx`.

O fluxo vivo é **100% Blade**:

| Passo | Onde |
|---|---|
| Botão "Estoque inicial" na lista | `resources/views/product/partials/product_list.blade.php` (gated por `product.opening_stock`) |
| Formulário | `GET /opening-stock/add/{product_id}` → `OpeningStockController@add` → `opening_stock/add.blade.php` + `form-part.blade.php` |
| Writer | `POST /opening-stock/save` → `OpeningStockController@save` |
| Writer IRMÃO (outro caminho) | `ProductUtil::addSingleProductOpeningStock` — usado pelo `store()` e pelo quick-add |

Por isso o `casos-coverage-guard` (que varre só `Pages/**`) não enxerga este contrato, e ele
mora na 2ª casa lida pelo [`requisitos-status.mjs`](../../../../scripts/governance/requisitos-status.mjs).

⚖️ **Força do veredito destes UC — `advisory`, não bloqueante.** Os testes rodam em
`PHP / Pest (Estoque · MySQL)` no PR (allowlist do [`estoque-pest.yml`](../../../../.github/workflows/estoque-pest.yml))
e no fullsuite nightly do CT 100, mas essa lane **não consta** em
[`governance/required-checks-baseline.json`](../../../../governance/required-checks-baseline.json).
Ou seja: **reprovação aqui é visível e não bloqueia merge.**

---

## Rastreabilidade

| UC | Caso de uso | Prio | Contrato | Teste | Status |
|----|-------------|------|----------|-------|--------|
| UC-PINIC-01 | Estoque inicial não entra em local de outro business | must `[T0]` | `CU-PROD-04`.5 + `AR-PROD-055` + [ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md) | `EstoqueInicialContratoTest` | 🧪 (verde esperado) |
| UC-PINIC-02 | Produto de outro business não recebe estoque inicial | must `[T0]` | `CU-PROD-04`.5 + `CU-PROD-10` | `EstoqueInicialContratoTest` | 🧪 (verde esperado) |
| UC-PINIC-03 | Quantidade pt-BR (`"1.500"` / `"1,5"`) entra pelo valor certo | must `[V0]` | `CU-PROD-04`.4 + REGRA MESTRE | `EstoqueInicialContratoTest` | 🧪 (verde esperado) |
| UC-PINIC-04 | Lote informado no estoque inicial persiste | must | `CU-PROD-04`.3 + Blade `form-part:98` | `EstoqueInicialContratoTest` | 🧪 (verde esperado) |

> 🧪 **e não ✅/❌**: eu não rodo teste (CT 100 · [ADR 0062](../../../decisions/0062-separacao-runtime-hostinger-ct100.md)).
> "Verde esperado" é **predição** derivada de leitura + varredura contada — o veredito é da lane
> (G-7 · `proibicoes.md` §5 2026-07-15). Estes 4 são majoritariamente **travas de invariante**
> (o guard existe no código e nunca teve teste), não achados.

---

## UC-PINIC-01 · Estoque inicial não entra em local de outro business · `must` `[T0]`

- **Persona:** Larissa / ROTA LIVRE — na implantação ela informa o saldo de abertura loja a loja.
  O parâmetro do local viaja no nome do campo (`stocks[<location_id>][…]`), não num dropdown
  assinado: quem monta o POST escolhe o local.
- **Aceite:** *Dado* que o mesmo payload no **meu** local persiste (pré-condição) · *Quando*
  submeto o saldo apontando para um `location_id` de **outro** business · *Então* nenhuma linha
  de saldo nasce naquele local.
- **Teste:** [`EstoqueInicialContratoTest`](../../../../tests/Feature/Produto/EstoqueInicialContratoTest.php)
  — `UC-PINIC-01 · estoque inicial não entra em local de outro business (Tier 0)`.
- **Contrato:** `CU-PROD-04` item 5 `[T0]` (*"estoque no local do business correto"*) +
  `AR-PROD-055` (**Local de Estoque Padrão** é do cadastro do meu negócio) + DOC-RAIZ §7 INV-6
  (o par variação × local **é** o endereço do saldo — se o local vaza, o saldo vaza) +
  [ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** o guard é uma linha só — `array_key_exists($location_id, $locations)`
  com `$locations = BusinessLocation::forDropdown($business_id)` — e **não tinha teste nenhum**.
  Ele é fácil de perder num refactor que troque o dropdown por `pluck('id')` sem escopo, e a
  falha seria silenciosa (o writer engole exceção em `catch` genérico e redireciona com sucesso).
  Re-localize com `grep -n "array_key_exists(\$location_id" app/Http/Controllers/OpeningStockController.php`.
- **Status: 🧪** — verde esperado (trava de invariante).

---

## UC-PINIC-02 · Produto de outro business não recebe estoque inicial · `must` `[T0]`

- **Persona:** qualquer tenant. `product_id` também é campo de formulário.
- **Aceite:** *Dado* que o saldo do **meu** produto entra (pré-condição) · *Quando* submeto o
  mesmo formulário com o `product_id`/`variation_id` de **outro** business · *Então* nenhuma
  linha de saldo nasce para aquela variação.
- **Teste:** [`EstoqueInicialContratoTest`](../../../../tests/Feature/Produto/EstoqueInicialContratoTest.php)
  — `UC-PINIC-02 · produto de outro business não recebe estoque inicial (Tier 0)`.
- **Contrato:** `CU-PROD-04` item 5 `[T0]` + `CU-PROD-10` + ADR 0093.
- **Regressão que defende:** o `save()` resolve o produto com
  `Product::where('business_id', …)->where('id', …)->first()` e todo o corpo do método está dentro
  de `if (! empty($product) && $product->enable_stock == 1)`. O isolamento **funciona por queda**:
  o `$product` nulo faz o método pular tudo. Este UC trava o **resultado** — se alguém trocar o
  `first()` por `find()` (ou mover o escopo pra dentro do laço), a escrita cross-tenant volta e
  nada acusa.
  > ⚠️ **Nota honesta de comportamento (não vira assert):** quando o produto não é seu, a resposta
  > é `success: 1` + *"estoque inicial adicionado com sucesso"*. A operação não aconteceu e o
  > operador é informado de que aconteceu. Está no §Backlog abaixo como divergência aberta —
  > escolher entre 404, 422 ou mensagem neutra é decisão [W], e encodá-la no assert seria escolher
  > o remédio antes do diagnóstico (`proibicoes.md` §5, 2026-07-15).
- **Status: 🧪** — verde esperado (trava de invariante).

---

## UC-PINIC-03 · Quantidade pt-BR (`"1.500"` / `"1,5"`) entra pelo valor certo · `must` `[V0]`

- **Persona:** Larissa digita `1.500` (mil e quinhentas peças) ou cola do Excel. E, no tecido,
  digita `1,5` (um metro e meio). Os dois no **mesmo campo**.
- **Aceite:** *Dado* um produto sem saldo · *Quando* informo `quantity = "1.500"` · *Então* o
  saldo do local fica `1500`. E *quando* informo `"1,5"` · *Então* fica `1.5`.
- **Teste:** [`EstoqueInicialContratoTest`](../../../../tests/Feature/Produto/EstoqueInicialContratoTest.php)
  — `UC-PINIC-03 · quantidade em pt-BR ("1.500" e "1,5") entra pelo valor certo (V0)`.
- **Contrato:** `CU-PROD-04` item 4 `[V0]` (*"quantidade fracionada respeita a unidade; `num_uf`
  não strippa decimal"*) + REGRA MESTRE (`proibicoes.md` Tier 0 — origem: incidente 2026-06-05,
  `num_uf` strippando o ponto decimal e inflando venda ~×100k em biz=4) +
  `AR-PROD-051` `[V0]` (Disponível é saldo por local, e é dele que sai a ruptura de venda).
- **Regressão que defende:** a Blade envia a quantidade como **texto formatado**
  (`@format_quantity`), então o `num_uf` é a única coisa entre o que a operadora digitou e a
  coluna `decimal(22,4)`. É **o mesmo remédio** que a `US-PROD-028` aplicou no irmão
  `fixVariationStockMisMatch` ([#4636](https://github.com/wagnerra23/oimpresso.com/pull/4636)) —
  **caminho de código diferente**: este UC não prova aquele, e vice-versa. O par (milhar ×
  fracionado) é deliberado: um remédio que "conserte" o milhar quebrando o decimal (ou o
  contrário) reprova aqui.
- **Status: 🧪** — verde esperado (trava `[V0]`).

---

## UC-PINIC-04 · Lote informado no estoque inicial persiste · `must`

- **Persona:** Larissa recebe a coleção com etiqueta de lote do fornecedor. Se o lote não entra
  junto do saldo de abertura, o rastreio nasce cego — e recall/troca vira busca manual.
- **Aceite:** *Dado* um produto sem saldo · *Quando* informo `quantity = 12` **e** um
  `lot_number` sentinela · *Então* o saldo entra (pré-condição) **e** alguma linha de compra da
  variação carrega aquele lote.
- **Teste:** [`EstoqueInicialContratoTest`](../../../../tests/Feature/Produto/EstoqueInicialContratoTest.php)
  — `UC-PINIC-04 · lote informado no estoque inicial persiste na linha de compra`.
- **Contrato:** `CU-PROD-04` item 3 (*"`enable_product_expiry`/`enable_lot_number` habilitam
  validade/lote"*) + Blade `opening_stock/form-part.blade.php` (o campo existe no payload) +
  DOC-RAIZ §3 (a entrada de abertura cria `purchase_lines` rastreáveis).
- **Regressão que defende:** o assert é **desacoplado da chave** (lição 2026-07-26): procura o
  **valor** sentinela em qualquer campo da linha, não `toHaveKey('lot_number')`. Assim, renomear
  a coluna não faz o vazamento passar nem reprova um fix legítimo — o contrato é *"o lote
  sobreviveu"*, não *"a coluna se chama X"*.
- **Escopo deliberado — só LOTE, não VALIDADE.** A validade (`exp_date`) viaja no mesmo bloco do
  payload, mas os **dois writers de estoque inicial a parseiam de formas diferentes** (medido —
  ver §Backlog). Encodar um formato no assert escolheria qual dos dois está certo, e isso é
  decisão [W].
- **Status: 🧪** — verde esperado.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2 (espírito): UC declarado sem teste citando o id = **órfão**. Contrato em **1 fonte
> só**, achado sem âncora, ou remédio ainda não decidido fica aqui, como prosa visível.
> ⚠️ Este diretório **não** é varrido pelo `casos-coverage-guard` (que vê só `Pages/**`), então
> o G-2 não pune um órfão aqui — o critério de parada é disciplina, não gate.

- **[BACKLOG] Dois writers de estoque inicial parseiam a VALIDADE de formas diferentes.** Medido
  (2026-07-27): `OpeningStockController@save` usa `$this->productUtil->uf_date($pl['exp_date'])`,
  que lê `session('business.date_format')`; `ProductUtil::addSingleProductOpeningStock` usa
  `\Carbon::createFromFormat('d-m-Y', $value['exp_date'])`, **formato fixo**. Um business
  configurado em `m/d/Y` grava validades diferentes conforme o caminho (formulário de estoque
  inicial × cadastro/quick-add). Não vira UC porque o remédio (unificar em `uf_date`? fixar
  ISO no payload? validar no request?) é decisão [W] — e um assert já escolheria.
  Re-medir: `grep -n "exp_date" app/Http/Controllers/OpeningStockController.php app/Utils/ProductUtil.php`.
- **[BACKLOG] `success: 1` para produto que não é seu / sem `enable_stock`.** O `save()` cai fora
  do `if` e mesmo assim monta `['success' => 1, 'msg' => opening_stock_added_successfully]`. Três
  situações distintas (produto alheio · produto inexistente · produto sem controle de estoque)
  produzem a **mesma mensagem de sucesso** de uma operação que não ocorreu. Divergência aberta —
  decisão [W] entre 404/422/mensagem neutra.
- **[BACKLOG] `alert_quantity` (alerta de reposição) não é exercitado por nenhum UC.**
  `CU-PROD-04` item 2 e `AR-PROD-053` (**Estoque Máx./Mín.**) descrevem o alerta, mas o campo é
  do cadastro do produto (`store`/`update`/`saveQuickProduct`), não deste fluxo. Vira UC no trio
  do `Create`/`Edit` quando [W] decidir se a paridade Delphi (dois limiares, Máx. **e** Mín.)
  vale — hoje o oimpresso tem só `alert_quantity` (um limiar).
- **[BACKLOG] Endereçamento físico (`AR-PROD-057`, "RUA 1 - ARMÁRIO 2 - ANDAR 5") não existe
  neste fluxo.** O legado guarda a **Descrição do Local** junto do saldo; aqui o rack vive em
  `product_racks` e não é tocado pelo estoque inicial. **1 fonte só** (Delphi) → gap de paridade,
  registrado na [PARIDADE-charter-vs-legado.md](../PARIDADE-charter-vs-legado.md), não UC.
- **[BACKLOG] `lead time` de reposição (`AR-PROD-054`, Dias Mínimo/Máximo) e "bloquear venda com
  estoque negativo" (`AR-PROD-056` `[V0]`).** Duas capacidades do legado sem contraparte no
  cadastro atual. **1 fonte só** → Non-Goal ou gap, decisão [W].
- **[BACKLOG] Quantidade em SEGUNDA unidade (`secondary_unit_quantity`).** O writer a lê e aplica
  `num_uf`, e a Blade a marca `required` quando o produto tem `second_unit`. Fluxo real sem
  contrato — vira UC quando houver fixture de produto com segunda unidade.

---

## Refs

- SDD (âncora dos CU): [`SDD-tela-cadastro-produto-v1.0.md`](../SDD-tela-cadastro-produto-v1.0.md)
  §5.3 **F9** (fluxo do estoque inicial) + §6.1 `CU-PROD-04` / `CU-PROD-10`
- Paridade Delphi: [`ANTI-REGRESSAO-cadastro-produto-legacy.md`](../ANTI-REGRESSAO-cadastro-produto-legacy.md)
  (`AR-PROD-051/052/053/054/055/056/057`)
- Doutrina de estoque: [`DOC-RAIZ-ESTOQUE.md`](../../Estoque/DOC-RAIZ-ESTOQUE.md) §3 (matriz de
  movimentação) + §7 (INV-5 `enable_stock`, INV-6 isolamento)
- Blade que define o payload: `resources/views/opening_stock/form-part.blade.php` (+ `add`/`ajax_add`)
- Controller: `app/Http/Controllers/OpeningStockController.php` — `add()` e `save()`.
  Re-localize com `grep -n "function add\|function save" app/Http/Controllers/OpeningStockController.php`
- Writer irmão (outro caminho, outro contrato): `ProductUtil::addSingleProductOpeningStock`,
  coberto pelo módulo **Estoque** — [`Estoque/Movimentacao.casos.md`](../../../../resources/js/Pages/Estoque/Movimentacao.casos.md),
  caso *"Estoque inicial (opening) → ENTRA"* (`EstoqueOpeningStockTest`).
  > 🔍 **O id daquele caso não é escrito por extenso aqui de propósito:** o
  > [`requisitos-status.mjs`](../../../../scripts/governance/requisitos-status.mjs) dá a **posse**
  > de um UC ao primeiro `casos.md` que o cita, varrendo por módulo — citar o id do Estoque aqui
  > faria o painel do **Produto** contá-lo como seu, inflando o placar com requisito alheio.
- Painel da cadeia: [`_STATUS-GENERATED.md`](../_STATUS-GENERATED.md)
  (`node scripts/governance/requisitos-status.mjs Produto`)
- Lane: `PHP / Pest (Estoque · MySQL)` (**advisory** — fora do `required-checks-baseline.json`)
