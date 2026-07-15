---
casos: Tabela de preço do produto · /products/add-selling-prices/{id}
irmaos: SellingPrices.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o preço que o produto assume numa tabela não muda no refactor.
owner: wagner
last_run: "2026-07-15"
---

# Casos de Uso & Aceite — Tabela de preço do produto

> **Âncora:** `CU-PROD-03` — "Preço por tabela (SellingPriceGroup)" `[must]` do
> [SDD §6.1](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md).
> Os UCs derivam do **contrato** (CU-PROD-03), nunca da implementação — teste derivado do código é
> tautológico e trava o desvio em vez de pegá-lo (`proibicoes.md` §5, entrada 2026-06-05).
>
> **Fluxo de negócio** (Wagner 2026-07-15): a tabela nasce **fora** do produto
> (`/produto/unificado?tela=tabelas`); aqui o operador **seleciona** a tabela e define o preço que o
> produto assume quando ela for aplicada; **depois** a tabela é vinculada ao cadastro do cliente ou a
> um tipo de venda. O produto **nunca** é vinculado direto ao cliente.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa (manifesto não regravado) · ⬜ não verificado · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | CU-PROD-03 | Teste | Status |
|----|-------------|------|-----------|-------|--------|
| UC-PTAB-01 | Salvar a matriz persiste o preço por (variação × tabela) | must | item 1 | `TabelaPrecoContratoTest` | 🧪 |
| UC-PTAB-02 | Produto de outro business não vaza (Tier 0) | must | item 4 `[T0]` | `TabelaPrecoContratoTest` | 🧪 |
| UC-PTAB-03 | Preço da tabela não infla no parser pt-BR (`num_uf`) | must | item 1 `[V0]` | `TabelaPrecoContratoTest` | 🧪 |
| UC-PTAB-04 | `price_group` de outro business não grava row (Tier 0) | must | `CU-PROD-10.1` `[T0]` | `TabelaPrecoContratoTest` | 🧪 |

**Veredito:** os 4 UCs **rodam e passam** na lane `PHP / Pest (Estoque · MySQL)` do
[#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300) — MySQL real, biz=1 + biz=2 semeados.
Não é verde-por-skip: o `paths-filter` da lane inclui `tests/Feature/Produto/**` e o log traz
`PASS Tests\Feature\Produto\TabelaPrecoContratoTest`.

`🧪` e não `✅` porque o manifesto do G-7 (`scripts/casos-test-results.json`, via `npm run casos:results`)
ainda não foi regravado — `✅` sem prova no manifesto o gate classifica como `unverified`.

> **Dois vermelhos vieram antes do verde, e os dois viraram conhecimento:**
> 1. `UC-PTAB-02` — `Expected 404 but received 302` → o §Pest GUARD do charter prometia 404 e o POST
>    engole a exceção. Proxy errado; UC corrigido pro invariante. Divergência registrada + decisão [W].
> 2. `UC-PTAB-04` — `Gravou (minha variação × price_group de OUTRO business)` → o `✅ (reusa guard)`
>    do `CU-PROD-10` era **falso**. Virou correção Tier 0 no `saveSellingPrices`, no mesmo PR.
>
> Nos dois casos eu tinha **afirmado a conclusão lendo o código** antes de rodar. O CI foi quem
> separou o que era verdade do que era narrativa.

---

## UC-PTAB-01 · Salvar a matriz persiste o preço por (variação × tabela) · `must`
- **Persona:** Wagner / operador — criou a tabela "Atacado", abre o produto, define R$ 150,00 nela. Se não gravar, o cliente de atacado compra pelo preço de varejo.
- **Aceite:** Dado um produto com variação e uma tabela de preço ativa no business · Quando envio `group_prices[{tabela}][{variação}] = {price, price_type}` pro `POST /products/save-selling-prices` · Então redireciona (302) e o par (variação × tabela) persiste em `variation_group_prices` com o preço e o `price_type`.
- **Teste:** `tests/Feature/Produto/TabelaPrecoContratoTest.php` — `UC-PTAB-01 · salvar a matriz persiste o preço por (variação × tabela)`.
- **Contrato:** `CU-PROD-03` item 1 — *"Matriz grupo × variação salva preço por tabela (`variation_group_prices`)"*.
- **Status: 🧪** — passou na lane Estoque · MySQL (#4300, run 29418132080); ✅ quando o manifesto G-7 for regravado.

---

## UC-PTAB-02 · Produto de outro business não vaza · `must` `[T0]`
- **Persona:** qualquer tenant — o preço de um business jamais pode ser lido nem gravado por outro. É o pior bug possível neste projeto.
- **Aceite:** Dado um produto que pertence a **outro** `business_id` · Quando abro `/products/add-selling-prices/{id}` **ou** envio `save-selling-prices` com aquele `product_id` · Então **nada** é gravado em `variation_group_prices` do business alheio e a operação **não reporta sucesso**. (O GET volta **404**; o POST volta **302** com `status.success=0` — ver a divergência abaixo.)
- **Teste:** `tests/Feature/Produto/TabelaPrecoContratoTest.php` — `UC-PTAB-02 · produto de outro business retorna 404` + `UC-PTAB-02 · salvar preço em produto de outro business não grava nada`.
- **Regressão que defende:** o `SellingPrices.charter.md` **prometia** `it('Controller cross-tenant retorna 404')` no §Pest GUARD desde 2026-05-15 e esse teste **nunca existiu** — o que havia era um grep procurando a string `session()->get('user.business_id')` no fonte do controller. Buraco Tier 0 documentado como se estivesse coberto.
- **⚠️ Divergência achada na 1ª execução real ([#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300)):** a promessa do charter valia só pra **metade** do contrato. O GET (`addSellingPrices`) devolve 404 de verdade. O **POST** (`saveSellingPrices`) **não**: o `findOrFail` roda dentro de `try { } catch (\Exception $e)`, a `ModelNotFoundException` é engolida pelo catch genérico e vira `redirect('products')` + *"something went wrong"* — **302**. O isolamento **não vaza** (a exceção aborta antes de qualquer write + rollback), mas uma tentativa cross-tenant fica **indistinguível de um erro de banco** no log e pro operador. Decisão pendente [W] no Backlog abaixo.
- **Contrato:** `CU-PROD-03` item 4 `[T0]` — *"Tabelas só do business atual"* + [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md). Note que o contrato pede **isolamento**, não um código HTTP — o 404 era proxy do charter, e o proxy estava errado pro POST.
- **Status: 🧪** — passou na lane Estoque · MySQL após a correção do proxy (#4300, run 29418132080); ✅ quando o manifesto G-7 for regravado.

---

## UC-PTAB-03 · Preço da tabela não infla no parser pt-BR · `must` `[V0]`
- **Persona:** Larissa / Wagner — digita `1.234,56` e o sistema tem que gravar mil duzentos e trinta e quatro reais, não um milhão.
- **Aceite:** Dado o salvamento da matriz · Quando o preço chega como `1.234,56` (pt-BR canônico) **ou** como `204.99605` (fracionário com ponto — a forma que o React manda) · Então grava `1234.56` e `~204.996` respectivamente, e **nunca** um valor de ordem de grandeza maior.
- **Teste:** `tests/Feature/Produto/TabelaPrecoContratoTest.php` — `UC-PTAB-03 · preço pt-BR com milhar e decimal grava o valor certo` + `UC-PTAB-03 · preço fracionário com ponto NÃO infla ×100k`.
- **Regressão que defende:** `saveSellingPrices` faz `price_inc_tax = productUtil->num_uf($value[...]['price'])` — **o mesmo parser** que no incidente 2026-06-05 leu o `.` de `204.99605` como separador de milhar e gravou R$ [redacted Tier 0] numa venda de R$ [redacted Tier 0] (16 vendas infladas ×100k na ROTA LIVRE). O caminho do preço de tabela passa pelo mesmo `num_uf` e **não tinha teste nenhum**. Lição perene: separador de milhar tem SEMPRE 3 dígitos.
- **Contrato:** `CU-PROD-03` item 1 + REGRA MESTRE valor/estoque (`proibicoes.md`).
- **Status: 🧪** — passou na lane Estoque · MySQL (#4300, run 29418132080); ✅ quando o manifesto G-7 for regravado.

---

## UC-PTAB-04 · `price_group` de outro business não grava row · `must` `[T0]`
- **Persona:** qualquer tenant. O `UC-PTAB-02` fecha o eixo do **produto** (produto alheio). Este fecha o eixo da **tabela**: produto meu + `price_group` alheio — o caminho que o `findOrFail` escopado **não** cobre.
- **Aceite:** Dado um produto **do meu business** e um `price_group_id` que pertence a **outro** business · Quando envio `group_prices[{tabela_alheia}][{minha_variação}]` · Então **nenhuma** linha liga a minha variação à tabela alheia.
- **Teste:** `tests/Feature/Produto/TabelaPrecoContratoTest.php` — `UC-PTAB-04 · price_group de outro business não grava row`.
- **Contrato:** **`CU-PROD-10`** — *"Isolamento multi-tenant `[must]` ✅ (reusa guard)"*, item 1: *"`[must][T0]` `App\Product` global scope em **toda** query"*.
- **Regressão que defende:** o `saveSellingPrices` escopa por `business_id` **apenas** o `Product::findOrFail`. O `price_group_id` vem **cru da chave do array do request** (`foreach ($request->input('group_prices') as $key => $value)`) — sem `validate`, sem `exists:` escopado. E o `VariationGroupPrice` **não tem global scope** (`$guarded = ['id']`, nada mais). O FK barra id **inexistente**, mas não barra id **de outro tenant**. O "reusa guard" do `CU-PROD-10` cobre `Product` — a tabela de preço não é `Product`.
- **Nasceu vermelho — e foi assim que virou prova.** 1ª execução ([#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300), run `29421329161`): `FAILED — Gravou (minha variação × price_group de OUTRO business)`. O `✅ (reusa guard)` do `CU-PROD-10` era **falso**: eu tinha afirmado isso lendo o código 3 turnos antes, e só virou fato quando o CI reprovou.
- **Correção (mesmo PR — failing-first):** `saveSellingPrices` agora resolve `$allowedPriceGroupIds = SellingPriceGroup::where('business_id', $business_id)->pluck('id')` **antes** do laço e pula (+ `Log::warning`) qualquer `price_group_id` fora do business. `abort(404)` ali seria engolido pelo `catch (\Exception)` genérico — por isso skip + log, e o UC assere o invariante (nada gravado), não o status.
- **Status: 🧪** — vermelho → correção → verde na mesma lane (`Estoque · MySQL`); ✅ quando o manifesto G-7 for regravado.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão = violação nova no `casos-gate`.
> Estes casos são contrato **conhecido e aceito**, mas hoje não têm teste possível — por isso ficam
> sem `UC-*`. Não é esquecimento; é a fila.

- **Multiplicador/markup por tabela** — `CU-PROD-03` item 2 `[V0][reg]`. `SellingPriceGroup.mult` é
  hardcoded `1.00`: o preço por tabela **aparenta** funcionar mas é 1:1. Um teste afirmando que o
  multiplicador funciona sairia **vermelho**. Sobe pra UC quando a **US-PROD-022** implementar
  ([ADR ARQ-0001 produto](../../../../memory/requisitos/Produto/adr/arq/0001-selling-price-multiplier.md), `proposed`).
  A dupla-confirmação exigida pela REGRA MESTRE já está prescrita no próprio CU: **2 caminhos —
  coluna `multiplier` vs cálculo `VariationGroupPrice`** + tabela antes→depois.
- **Markup recalcula o preço da tabela sem divergir do financeiro** — `CU-PROD-03` item 3 `[V0]`.
  O **motor** já está coberto por `tests/Feature/Produto/FormacaoPrecoParidadeLegadoTest.php`
  (markup mestre, piso de 4 casas — `variations.profit_percent` é `DECIMAL(22,4)`; a 2 casas,
  custo 4.300,00 + 62,79% dá 6.999,97 em vez de 7.000,00). Falta o **elo**: markup mudou → preço da
  tabela reprecifica. Vira UC junto com a US-PROD-022.
- **Piso de venda vs preço de tabela** — `AR-PROD-101` (`R$ Valor mínimo de venda`) é piso que
  bloqueia venda abaixo. A tabela pode furar o piso? Sem contrato hoje — decisão pendente.
- **⚠️ [W] DECIDIR — cross-tenant no POST devolve 302, não 404.** Achado pela 1ª execução real do
  `UC-PTAB-02` ([#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300)). Em
  `ProductController@saveSellingPrices` o `findOrFail` está dentro de `try { } catch (\Exception $e)`
  — a `ModelNotFoundException` é engolida e vira `redirect('products')` + `success: 0`. **Não é
  vazamento** (nada grava; o `UC-PTAB-02` prova). Mas tentativa cross-tenant fica indistinguível de
  falha de banco no `Log::emergency`, e o charter prometia 404. Duas saídas: (a) **US** pra
  re-lançar `ModelNotFoundException` (ou `abort(404)`) antes do catch genérico → o POST passa a
  honrar o mesmo contrato do GET; (b) **Non-Goal declarado** — 302 genérico é aceito de propósito,
  e o §Pest GUARD do charter é corrigido pra parar de prometer 404. Enquanto não decide, o UC
  defende o invariante que importa (isolamento), não o proxy.
- **Preço especial por (produto × cliente)** — `AR-PROD-111..116` do legado. **Non-Goal declarado**
  (Wagner 2026-07-15): o modelo é tabela vinculada ao cadastro do cliente; o produto nunca é
  vinculado direto. Não é backlog, é divergência consciente — registrado aqui só pra não ser
  redescoberto como "gap" na próxima auditoria.

---

## Pendência de CONTRATO (achado sem caso de uso — não vira UC até [W] decidir)

> Diferente do Backlog acima: lá o contrato **existe** e falta teste. Aqui **falta o contrato**.
> Escrever UC pra estes seria inventar a regra a partir do código — tautologia
> (`proibicoes.md` §5). O SDD §6 diz a mesma coisa na linha 139: *"teste E2E ancora no contrato
> (SPEC/casos), **nunca** na implementação"*. Ficam registrados pra não serem redescobertos.
>
> **Origem:** passe adversarial 2026-07-15 sobre o ecossistema da tabela de preço.

**O `getVariationGroupPrice` tem 5 consumidores e 3 não guardam o retorno.** Ele devolve
`price_inc_tax => ''` quando **não há row** (caso normal: produto sem preço naquela tabela) e `0`
quando há row com zero. Quem consome:

| Consumidor | Guarda `!empty()`? | Efeito sem row (`''`) |
|---|---|---|
| `SellPosController:1791` (PDV) | ✅ | cai no preço padrão |
| `Modules/Crm/OrderRequestController:325` | ✅ | cai no preço padrão |
| `LabelsController:145` | ❌ | `sell_price_inc_tax = ''` → **etiqueta sem preço** |
| `WoocommerceUtil:343` | ❌ | sincroniza preço vazio pra loja |
| `WoocommerceUtil:733` | ❌ | idem, por variação |

Os dois caminhos de **venda interna** guardam; os três de **saída pro cliente final** não.

- **Etiqueta (`LabelsController`)** — o `CU-PROD-09` ("Código de barras + etiqueta") fala só de
  `barcode_types` + ZPL/PDF + GTIN. **Não menciona preço.** Sem contrato dizendo o que é certo
  quando a tabela não tem preço, não há UC possível. Decisão [W]: vira `CU-PROD-09.3`?
- **WooCommerce** — **não tem CU nenhum** no SDD do Produto (aparece no SPEC §2 como capacidade
  em produção, sem caso de uso). Decisão [W]: nasce CU de canal?
- **O 0-row é inerte por acidente** — a UI (React **e** Blade) pré-preenche célula sem preço com
  `0` e envia (`row[v.id] = existing ?? { price: 0, ... }` / `... : 0`). Salvar a tela converte
  "sem row (usa o padrão)" em "row com preço 0". No PDV isso não faz mal **só porque `!empty(0)`
  é `false` em PHP** — coincidência de semântica, não invariante desenhado. Um refactor razoável
  (`isset`, `!== null`, tipar `?float`) destrava preço zero em produção. Nada testa esse acidente.
  Corolário: **preço 0 legítimo é inexprimível** (tabela promocional "grátis" não existe).

> ⚠️ As correções **brigam entre si**: fazer a UI parar de gravar zeros (bom) aumenta os casos de
> "sem row" → **piora** etiqueta/Woo. Não há uma raiz única; são 3 defeitos independentes
> (contrato do caller · contrato do writer · validação). Decidir um sem os outros regride.

## Notas de cobertura

- **O que este arquivo NÃO cobre:** a variação (grade cor×tamanho / preço por quantidade) tem
  contrato próprio — `CU-PROD-02` + [`VariacaoPrecos.charter.md`](VariacaoPrecos.charter.md). Eixos
  ortogonais: tabela = *em qual lista de preço*; variação = *qual filho*.
- **Os 2 testes legados desta tela são tautológicos** — `Wave2SellingPricesInertiaTest` e
  `Wave2SellingPricesBaselineTest` só fazem grep de string no fonte (`contém 'variations.map'`,
  `importa AppShellV2`). Nenhum UC os cita de propósito: ancorar contrato neles seria teatro
  (`proibicoes.md` §5). Ficam como estão até alguém decidir se apagam ou viram teste de verdade.
- **Vínculo tabela → cliente** acontece na **venda**, não aqui — e já tem teste verde:
  `CustomerAutoApplyOnSelectTest` (CU-01 de `memory/requisitos/Sells/CASOS-USO-CREATE-VENDA.md`,
  status 🟢): ao selecionar o cliente, o grupo de preço re-aplica e recalcula as linhas.
