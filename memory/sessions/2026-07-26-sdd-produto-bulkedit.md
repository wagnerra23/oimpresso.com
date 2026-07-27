---
id: sessions-2026-07-26-sdd-produto-bulkedit
type: session
date: "2026-07-26"
topic: "Run B2 do agent sdd-from-source — Produto/BulkEdit (a última tela do módulo sem casos.md)"
authors: [C]
module: Produto
owner: W
related_adrs:
  - 0351-sdd-from-source
  - 0352-errata-0351-venue-distiller-citacao-taxonomia
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
pii: false
---

# Run B2 · `sdd-from-source` sobre `Produto/BulkEdit`

> Continuação medida de [`2026-07-26-sdd-from-source-loop-avaliacao.md`](2026-07-26-sdd-from-source-loop-avaliacao.md)
> (B0 `Show` · B1 `Index`). Esta é a **4ª tela** do módulo e a **última sem `casos.md`**.
> **Não commitei, não abri PR, não rodei teste** (CT 100 · [ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md)).

## Artefatos tocados

| Artefato | O quê |
|---|---|
| `resources/js/Pages/Produto/BulkEdit.casos.md` | **novo** — UC-PBULK-01..06 + 6 `[BACKLOG]` sem id |
| `tests/Feature/Produto/ProdutoBulkEditContratoTest.php` | **novo** — 6 casos failing-first citando os UC (G-2) |
| `.github/workflows/estoque-pest.yml` | +1 entrada na allowlist (senão o teste é "verde impossível") |
| `memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md` | §5.3 **F5.1** novo · `CU-PROD-06` ✅→🟡 · changelog v1.0.4 |
| `resources/js/Pages/Produto/BulkEdit.charter.md` | Fase 2.6 — **só fatos** + §Divergências abertas |
| `memory/requisitos/Produto/PARIDADE-charter-vs-legado.md` | +4 linhas de paridade do bulk |
| `memory/requisitos/Produto/_STATUS-GENERATED.md` | re-derivado (`requisitos-status.mjs --write`) |

**Lacunas do módulo: 5 → 3.** Fecharam `Tela BulkEdit sem casos.md` e `CU-PROD-06 sem UC`.
Restam `CU-PROD-04`, `CU-PROD-05` e `US-PROD-028` (ver §Gaps).

## Orçamento da corrida (Fase 1.4)

| Métrica | Valor |
|---|---|
| Tool calls | ~40 (não medi tokens — quem mede é o processo pai) |
| Arquivos lidos integralmente | 11 (`BulkEdit.tsx` · charter · RUNBOOK · `StockHistory.casos.md` · `ProdutoShowContratoTest` · 3 blades do bulk · `EstoqueProduto` · trechos de `ProductController`/`ReportController`) |
| Varreduras contadas (sem `head_limit`) | 9 — `mass-update` (3 hits, 0 em `routes/`) · `bulk-edit` na UI (1 entrada, atrás de flag) · `fixVariationStockMisMatch` (1/1 consumidor) · `default_sell_price_inc_tax` (4 sites, 0 no schema, 0 em `database/`) · `adjust-product-stock` na UI (0) · `it(` nos 2 Wave2 (0 cross-tenant) · `Wave2BulkEdit` em lanes (0) · refs do charter (2/2 mortos) · `enable_product_bulk_edit` |
| UC gerados | **6 ancorados** · **6 `[BACKLOG]` sem id** (critério ≥2 fontes) |
| Achados novos | 5 (ver abaixo) |
| Line-refs `arquivo:NNN` | **6 no total** (4 no `casos.md`, 1 no teste, 1 no charter) — B1 tinha 41. Todos acompanhados do sha `6cd0fbc4f2` + comando de re-medição |

**Reusei (não re-derivei):** §5.3 F1-F8 e §6.1 `CU-PROD-01..15` do SDD como âncora direta · o
`ANTI-REGRESSAO` (43 KB) por **1 grep temático** (`massa|bulk|lote|reajuste`) em vez de leitura
integral · `ProdutoShowContratoTest`/`ProdutoEditPayloadContratoTest` como molde (padrão anti-vácuo,
`EstoqueFixture`, header `X-Inertia-Version`) · `TabelaPrecoContratoTest` pro helper de
`selling_price_groups` · a convenção de frontmatter (`last_run_ci`) e o bloco "força do veredito"
dos irmãos · o wiring da lane (o comentário-receita do `estoque-pest.yml`).

**Não reusei (e a definição está certa nisso):** a resolução da Blade — aqui ela é o **outro branch
do mesmo método**, mas o payload real vem dos **partials** (`bulk_edit_variation_row`), que nenhuma
tela irmã usava; a varredura de consumidores; a verificação factual do charter.

**Gargalo:** decidir **o que NÃO vira UC**. Com 3 defeitos empilhados (flag desligada · rota
inexistente · reader≠writer) a tentação era escrever 12 UC; metade não tinha 2ª fonte ou encodaria
um remédio que é decisão [W]. O corte custou mais tempo que a leitura de código.

## Achados (predições, não vereditos — não rodei teste)

1. **`default_sell_price_inc_tax` não existe** — 4 telas React montam `defaultSellPrice` lendo um
   atributo que não é coluna de `variations` nem accessor de `App\Variation` → **preço de venda `0`**
   em `Show`, `SellingPrices`, `BulkEdit` e `Unificado`. **Corrige** a leitura do changelog v1.0.3 /
   `CU-PROD-14` / `Show.casos.md`, que descreviam o campo como "venda com imposto".
2. **`bulkUpdate` grava `variation_group_prices` sem guard de tenant** — `price_group_id` cru da
   chave do request. É o **mesmo** buraco do `UC-PTAB-04` ([#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300)),
   fechado no `saveSellingPrices` e **aberto** na tela irmã. O `CU-PROD-10` já previa: *"o próximo
   model pendurado em Product nasce com o mesmo buraco"*.
3. **A tela React não tem para onde salvar** — `post('/products/mass-update')`, rota inexistente.
4. **Reader manda 2 campos, writer lê 5 sem `??`** — o lote inteiro reverte com "algo deu errado".
5. **A feature está desligada** — `enable_product_bulk_edit = false` (upstream: *"Will be
   depreciated in future"*). Nenhum operador chega à tela pela UI hoje.

## Gaps que precisam do [W]

- **`US-PROD-028` — a tela dona NÃO é do Produto.** O único consumidor de
  `fixVariationStockMisMatch` é `ReportController::adjustProductStock` (`GET /reports/adjust-product-stock`),
  cuja tela é a **Blade** `report.product_stock_details` — sem `.tsx`, logo sem lugar pra `casos.md`
  no esquema atual (`requisitos-status.mjs` só varre `resources/js/Pages/<Mod>/*.casos.md`). Não
  forcei UC no BulkEdit nem no StockHistory. **Decisão [W]:** (a) aceitar `@covers-us` + teste como
  contrato pra tela Blade-only (e ensinar o gerador a ler isso), ou (b) a US fica "sem contrato" até
  a tela virar React.
- ⚠️ **A régua fecha por substring.** Escrevi `US-PROD-028` na prosa do `casos.md` e a lacuna
  **sumiu do painel** — `casos.src.includes(us.id)`. Reverti de propósito e deixei a nota no arquivo.
  Fechar por menção é `presença ≠ correção`; quem for endurecer o gerador, comece por aqui.
- **3 divergências abertas do charter** (registradas nos dois lados, **sem** escolher remédio):
  rota de submit inexistente · coluna "Locations" prometida e não renderizada · feature-flag off.
- **6 famílias de paridade Blade→React** (margem % com recálculo encadeado · custo/venda com imposto ·
  preços por tabela na linha · adicionar produto à matriz · localizações · gate de custo `AR-PROD-015`):
  cada uma é **Non-Goal** ou **gap**, decisão de produto.
- **Eixo `[V0]`**: os fixes dos achados 1/2/4 tocam valor → **REGRA MESTRE** (2 caminhos +
  antes→depois + aprovação [W]). O teste PROVA; o conserto não é meu.

## Camada 3 — vereditos (por UC, agregado por US)

| Gate | Resultado |
|---|---|
| `casos-coverage-guard --json` | **0 violações** no meu diff (trio G-1 ✓ · 6/6 UC citados G-2 ✓ · metadata G-5 ✓ · frescor G-6 ✓) |
| `screen-coverage-map --check` | `✓ CATRACA: nenhuma regressão`; Produto 8/8 com charter |
| `requisitos-status Produto --write` | 9 US · 14 CU · **39 UC** (era 33) · 7/7 telas com `casos.md` |
| `anchor-lint SPEC.md` | 11,1% (inalterado — **não toquei o SPEC**, lápide 2026-07-12) |

**Força do veredito:** a lane `PHP / Pest (Estoque · MySQL)` **não** consta em
`governance/required-checks-baseline.json` → reprovação é **visível e não bloqueia merge**.
Nenhum UC marcado ✅/❌: eu não rodo teste (G-7).

### 🚩 Gate vermelho de terceiro (reporto, não conserto)

`anchor-lint` acusa `US-PROD-028: tem teste-que-cobre mas NENHUM numa lane de JUnit (verde
impossível)`. **É limitação do mapa de lanes do próprio lint**, não gap real: o
`EstoqueFixMismatchNumUfTest.php` **está** na allowlist do `estoque-pest.yml`, que emite
`--log-junit` e é consumido pelo `casos-results-publish.yml` (`estoque-pest.yml:pest-estoque-junit`)
e pelo `scripts/casos-test-results.json`. O `inLane()` do `anchor-lint.mjs` só conhece
`.github/ci-sqlite-pest.list` + 3 lanes de módulo. Fora do meu diff — não mexi.

## Autoavaliação contra as 5 regras que o B1 violou

| Regra | B1 | B2 (esta corrida) |
|---|---|---|
| assert por comportamento, não chave literal | ❌ usou `not->toHaveKey` | ✅ valores sentinela; zero `toHaveKey` de payload |
| âncora estável > `:NNN` | ❌ 41 refs | ✅ **6**, todas com sha + comando de re-medição |
| "roda?" pelas portas vivas | ❌ grep em `.github/` | ✅ `phpunit.xml` + `shards-plan.mjs` + allowlist + baseline, citando qual medi |
| declarar a força do veredito | ❌ 0 ocorrências | ✅ bloco próprio + 3 menções |
| persistir orçamento em session log | ❌ nenhum | ✅ este arquivo |

## Lições de mecanismo (o que na definição atrapalhou)

1. **O critério de parada resolve "quantos", não "qual lado".** Três dos meus achados têm **dois
   remédios legítimos** cada; a definição diz "não escolha o vencedor", mas um UC **precisa** de um
   assert. A saída foi escrever asserts **remédio-neutros** (aceitar qualquer base de preço; exigir
   "o local aparece" e não a chave). Isso merece virar regra explícita: *quando há 2 remédios, o
   assert tem que passar nos dois — senão vira `[BACKLOG]`*.
2. **A definição manda "não crie tipo novo" e está certa — mas o painel derivado pode ser fechado
   por prosa.** Um agente cumprindo a letra (citar a US no `casos.md`) fecharia a lacuna sem
   contrato. Vale a linha: *nunca cite id de US/CU num `casos.md` sem que um UC o exercite*.
3. **A Fase 1.4 (reuso) funcionou melhor no TESTE que no doc.** O maior ganho foi copiar a mecânica
   do `ProdutoShowContratoTest` (anti-vácuo, fixture, headers). A definição fala de reusar o §5.3/§6
   e as âncoras `AR-*`, mas não menciona **reusar o teste irmão como molde** — foi a maior economia
   isolada da corrida, tal como o B1 já tinha registrado. Deveria estar escrito na Fase 1.4.
