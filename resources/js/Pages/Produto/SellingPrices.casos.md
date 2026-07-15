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
| UC-PTAB-01 | Salvar a matriz persiste o preço por (variação × tabela) | must | item 1 | `TabelaPrecoContratoTest` | ⬜ |
| UC-PTAB-02 | Produto de outro business retorna 404 (Tier 0) | must | item 4 `[T0]` | `TabelaPrecoContratoTest` | ⬜ |
| UC-PTAB-03 | Preço da tabela não infla no parser pt-BR (`num_uf`) | must | item 1 `[V0]` | `TabelaPrecoContratoTest` | ⬜ |

**Veredito honesto:** os 3 UCs têm teste escrito e citando o id, mas **nenhum rodou** — o CT 100 está
inacessível (Tailscale em `NoState`) e teste local é proibição Tier 0. Por isso `⬜ não verificado`, não
`🧪`. Sobem pra `🧪` quando a lane MySQL do CT 100 rodar, e pra `✅` quando `npm run casos:results`
regravar o manifesto.

---

## UC-PTAB-01 · Salvar a matriz persiste o preço por (variação × tabela) · `must`
- **Persona:** Wagner / operador — criou a tabela "Atacado", abre o produto, define R$ 150,00 nela. Se não gravar, o cliente de atacado compra pelo preço de varejo.
- **Aceite:** Dado um produto com variação e uma tabela de preço ativa no business · Quando envio `group_prices[{tabela}][{variação}] = {price, price_type}` pro `POST /products/save-selling-prices` · Então redireciona (302) e o par (variação × tabela) persiste em `variation_group_prices` com o preço e o `price_type`.
- **Teste:** `tests/Feature/Produto/TabelaPrecoContratoTest.php` — `UC-PTAB-01 · salvar a matriz persiste o preço por (variação × tabela)`.
- **Contrato:** `CU-PROD-03` item 1 — *"Matriz grupo × variação salva preço por tabela (`variation_group_prices`)"*.
- **Status: ⬜** — teste escrito, não executado (CT 100 fora).

---

## UC-PTAB-02 · Produto de outro business retorna 404 · `must` `[T0]`
- **Persona:** qualquer tenant — o preço de um business jamais pode ser lido nem gravado por outro. É o pior bug possível neste projeto.
- **Aceite:** Dado um produto que pertence a **outro** `business_id` · Quando abro `/products/add-selling-prices/{id}` **ou** envio `save-selling-prices` com aquele `product_id` · Então volta **404** e **nada** é gravado em `variation_group_prices` do business alheio.
- **Teste:** `tests/Feature/Produto/TabelaPrecoContratoTest.php` — `UC-PTAB-02 · produto de outro business retorna 404` + `UC-PTAB-02 · salvar preço em produto de outro business retorna 404`.
- **Regressão que defende:** o `SellingPrices.charter.md` **prometia** `it('Controller cross-tenant retorna 404')` no §Pest GUARD desde 2026-05-15 e esse teste **nunca existiu** — o que havia era um grep procurando a string `session()->get('user.business_id')` no fonte do controller. Buraco Tier 0 documentado como se estivesse coberto.
- **Contrato:** `CU-PROD-03` item 4 `[T0]` — *"Tabelas só do business atual"* + [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Status: ⬜** — teste escrito, não executado (CT 100 fora).

---

## UC-PTAB-03 · Preço da tabela não infla no parser pt-BR · `must` `[V0]`
- **Persona:** Larissa / Wagner — digita `1.234,56` e o sistema tem que gravar mil duzentos e trinta e quatro reais, não um milhão.
- **Aceite:** Dado o salvamento da matriz · Quando o preço chega como `1.234,56` (pt-BR canônico) **ou** como `204.99605` (fracionário com ponto — a forma que o React manda) · Então grava `1234.56` e `~204.996` respectivamente, e **nunca** um valor de ordem de grandeza maior.
- **Teste:** `tests/Feature/Produto/TabelaPrecoContratoTest.php` — `UC-PTAB-03 · preço pt-BR com milhar e decimal grava o valor certo` + `UC-PTAB-03 · preço fracionário com ponto NÃO infla ×100k`.
- **Regressão que defende:** `saveSellingPrices` faz `price_inc_tax = productUtil->num_uf($value[...]['price'])` — **o mesmo parser** que no incidente 2026-06-05 leu o `.` de `204.99605` como separador de milhar e gravou R$ [redacted Tier 0] numa venda de R$ [redacted Tier 0] (16 vendas infladas ×100k na ROTA LIVRE). O caminho do preço de tabela passa pelo mesmo `num_uf` e **não tinha teste nenhum**. Lição perene: separador de milhar tem SEMPRE 3 dígitos.
- **Contrato:** `CU-PROD-03` item 1 + REGRA MESTRE valor/estoque (`proibicoes.md`).
- **Status: ⬜** — teste escrito, não executado (CT 100 fora).

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
- **Preço especial por (produto × cliente)** — `AR-PROD-111..116` do legado. **Non-Goal declarado**
  (Wagner 2026-07-15): o modelo é tabela vinculada ao cadastro do cliente; o produto nunca é
  vinculado direto. Não é backlog, é divergência consciente — registrado aqui só pra não ser
  redescoberto como "gap" na próxima auditoria.

---

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
