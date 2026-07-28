---
id: sessions-2026-07-27-sdd-produto-fluxos-sem-tela
type: session
date: "2026-07-27"
topic: "Run B3 do agent sdd-from-source — os 4 fluxos do Produto SEM tela React (Blade puro / API / chamado de outro módulo)"
authors: [C]
module: Produto
owner: W
related_adrs:
  - 0351-sdd-from-source
  - 0352-errata-0351-venue-distiller-citacao-taxonomia
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0093-multi-tenant-isolation-tier-0
  - 0101-tests-business-id-1-nunca-cliente
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
pii: false
---

# Run B3 · `sdd-from-source` sobre os 4 fluxos SEM tela React do Produto

> Continuação medida de [`2026-07-26-sdd-produto-bulkedit.md`](2026-07-26-sdd-produto-bulkedit.md)
> (B0 `Show` · B1 `Index` · B2 `BulkEdit`). Aquele run fechou a **última tela** do módulo;
> este fecha as **4 lacunas que sobraram** — e nenhuma delas tem `.tsx`.
> Decisão [W] 2026-07-26: *"1 e 2 são requeridos sim. tem que ter tudo do blade"*.
> **Não commitei, não abri PR, não rodei teste** (CT 100 · [ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md)).
> Repo **completo** (`git rev-parse --is-shallow-repository` = `false`, 5.7k commits) — as
> afirmações de história/data abaixo se sustentam (lápide §5 2026-07-24).

## Artefatos tocados

| Artefato | O quê |
|---|---|
| `memory/requisitos/Produto/_telas/estoque-inicial.casos.md` | **novo** — `UC-PINIC-01..04` + 6 `[BACKLOG]` |
| `memory/requisitos/Produto/_telas/bom-combo.casos.md` | **novo** — `UC-PBOM-01..04` + 6 `[BACKLOG]` |
| `memory/requisitos/Produto/_telas/quick-add.casos.md` | **novo** — `UC-PQCK-01..04` + 5 `[BACKLOG]` |
| `memory/requisitos/Produto/_telas/ajuste-estoque-relatorio.casos.md` | **novo** — `UC-PFIX-01..03` + 4 `[BACKLOG]` |
| `tests/Feature/Produto/EstoqueInicialContratoTest.php` | **novo** — 4 casos failing-first |
| `tests/Feature/Produto/ProdutoBomContratoTest.php` | **novo** — 4 casos failing-first |
| `tests/Feature/Produto/QuickAddProdutoContratoTest.php` | **novo** — 4 casos failing-first |
| `tests/Feature/Produto/AjusteEstoqueRelatorioContratoTest.php` | **novo** — 2 casos failing-first |
| `tests/Feature/Estoque/EstoqueFixMismatchNumUfTest.php` | +3 linhas de comentário citando `UC-PFIX-01` (não duplicar assert) |
| `.github/workflows/estoque-pest.yml` | +4 entradas na allowlist (senão os testes são "verde impossível") |
| `memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md` | §5.3 **F9–F12** novos · `CU-PROD-04`/`CU-PROD-08` ✅→🟡 · `CU-PROD-05`.4 ⬜→🔴 · changelog v1.0.5 |
| `memory/requisitos/Produto/_STATUS-GENERATED.md` | re-derivado (`requisitos-status.mjs --write`) |

**Lacunas do módulo: 4 → 0.** O painel fecha com
*"Nenhuma lacuna: toda tela tem caso, todo CU é citado, e toda US entregue tem contrato"*.
Placar: 9 US · 14 CU · 7 telas · **11 `casos.md`** (7 React + 4 Blade) · **54 UC**, 0 sem teste.

## Veredito da Camada 3 (gates rodados localmente)

| Gate | Required? | Resultado |
|---|---|---|
| `requisitos-status.mjs --selftest` | não (umbrella, advisory) | ✅ 19/19 |
| `requisitos-status.mjs Produto --check` | não | ✅ em dia |
| `casos-coverage-guard --check` | **sim** (`Casos-coverage · ratchet`) | ✅ sem violações novas (débito **−6**) |
| `deadlink-gate --check` | **sim** | ✅ nenhum arquivo vivo piorou |
| `anchor-lint memory/requisitos/Produto/SPEC.md --check` | **sim** | ✅ exit 0 |
| `sdd-scorecard --check` | **sim** (GT-G3) | ✅ 9 métricas, `distiller_freshness` = 0 |
| `screen-coverage-map` | **sim** | ✅ Produto 8/8 |
| `PHP / Pest (Estoque · MySQL)` | **NÃO** — advisory | 🧪 sem veredito (não rodo teste) |

⚖️ **Força declarada em todos os 4 `casos.md`:** a lane que executa estes UC **não consta** de
[`required-checks-baseline.json`](../../governance/required-checks-baseline.json) (34 contexts,
conferidos nominalmente) → **reprovação é visível e não bloqueia merge**.

## Achados (com âncora e varredura contada)

1. **`BomResolverTest` e `ReservarEstoqueBomTest` não rodam em lugar nenhum.** Auto-pulam fora do
   sqlite (`markTestSkipped` se `config('database.default') !== 'sqlite'`) **e** não estão em
   `.github/ci-sqlite-pest.list`; varredura de `Domain/Inventory` em `.github/`+`scripts/`: **0**.
   O nightly do CT 100 roda `DB_CONNECTION=mysql` (`ct100-fullsuite.sh:122,263,360`) → auto-pulam
   lá também. **Skip-as-pass em todo lugar** — e o comentário do próprio arquivo afirma o
   contrário (*"cobertura real é na lane sqlite (per-PR)"*).
   **As 3 portas, nomeadas:** roda? (`phpunit.xml` + `shards-plan.mjs`) → sim, é enumerado no
   nightly · roda no PR? → não · bloqueia merge? → não.
2. **`ProductBomController` tem 0 testes** e a **variação** do componente não é validada contra o
   tenant: `where('business_id')` ×4 / `firstOrFail()` ×4, todos sobre `Product`/`ProductBom`,
   **0** sobre `variations` — `component_variation_id`/`parent_variation_id` passam por
   `'nullable|integer'` e vão diretos pro `create`. **3ª instância** da família `UC-PTAB-04`
   ([#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300)) → `UC-PBULK-03` → `UC-PBOM-02`.
3. **Quick-add: 0 `$request->validate`.** `saveQuickProduct` monta o payload com
   `$request->only($form_fields)` (**34** campos) e joga no `Product::create`;
   `category_id`/`brand_id`/`unit_id`/`tax` entram **sem consulta de tenant**. **4ª instância** da
   mesma família (`UC-PQCK-02`). O `required` de `name`/`unit_id`/`barcode_type`/`tax_type` vive
   **só** no jQuery Validate da Blade.
4. **Dois writers de estoque inicial parseiam a VALIDADE de formas diferentes:**
   `OpeningStockController@save` → `uf_date()` (lê `session('business.date_format')`) ×
   `ProductUtil::addSingleProductOpeningStock` → `Carbon::createFromFormat('d-m-Y', …)` **fixo**.
   Num business fora de `d-m-Y`, o mesmo campo grava valores diferentes conforme o caminho.
5. **`save()` do estoque inicial devolve `success: 1`** ("estoque inicial adicionado") para produto
   alheio / inexistente / sem `enable_stock` — três situações distintas, uma só mensagem de sucesso
   de uma operação que não ocorreu.
6. **O "Fix" do relatório é o único ponto do ecossistema que SOBRESCREVE saldo** (`= X`, não
   `+= delta`), dentro de um `GET`, com os 3 parâmetros na querystring e **sem rastro no kardex**
   (`AR-PROD-064` exige origem + usuário por movimento). Eixo numérico blindado pela `US-PROD-028`;
   GET→POST/CSRF **explicitamente fora de escopo por decisão [W] na própria US** → fica `[BACKLOG]`,
   **não vira UC** (não se reabre por dentro uma fronteira que o dono desenhou).
7. **Falso-positivo do `anchor-lint` (pré-existente, de terceiro — reporto, não conserto):** ele
   marca `US-PROD-028` como *"teste-que-cobre fora das lanes de JUnit → verde impossível"*. O
   `EstoqueFixMismatchNumUfTest` **está** na allowlist do `estoque-pest.yml` (linha 180) e essa
   lane **emite** `--log-junit`. A causa é o modelo de lane do lint: `inLane()` conhece só
   `.github/ci-sqlite-pest.list` + 3 dirs hardcoded (`JUNIT_MODULE_LANES` =
   Financeiro/Jana/NfeBrasil, `anchor-lint.mjs:442`). `estoque-pest` não está no mapa. Não toquei
   (fora do meu diff, e o `--check` sai 0).

## Orçamento da corrida (Fase 1.4 — reuso vs re-varredura)

| Métrica | Valor |
|---|---|
| Tool calls | ~45 |
| Arquivos lidos integralmente | 9 (`OpeningStockController` · `ProductBomController` · `BomResolver` §resolve · `estoque-pest.yml` · `requisitos-status.mjs` · `BulkEdit.casos.md` · `ProdutoBulkEditContratoTest` · `EstoqueFixMismatchNumUfTest` · `ci-sqlite-pest.list`) |
| Varreduras contadas (sem `head_limit`) | 11 — `quickAdd` em views (**10**/10 arquivos) · `opening_stock` em `Pages/Produto/*.tsx` (**2**) · `combo` idem (**3**) · `quick_add\|quickAdd` em `resources/js/` (só cliente/veículo) · `Domain/Inventory` em `.github/`+`scripts/` (**0**) · `firstOrFail`/`where('business_id')` no BomController (**4**/**4**) · `variation_id` no mesmo (**0** guards) · `$form_fields` (**34**) · `validate` em `saveQuickProduct` (**0**) · `product_bom` no schema baseline (**3**) · prefixos UC do repo (31 famílias, 4 livres) |
| UC gerados | **14 ancorados** (`PINIC` 4 · `PBOM` 4 · `PQCK` 4 · `PFIX` 2) + **21 `[BACKLOG]`** sem id |
| Testes Pest escritos | 4 arquivos / 14 casos (+1 citação em teste existente) |
| Achados | 7 (6 de código/paridade + 1 falso-positivo de gate de terceiro) |

**Reusado da análise do módulo (não re-varri):** o §5.3 F1–F8 e o §6.1 inteiro do SDD (herdados de
B0/B1/B2) · a resolução das lanes e a régua de "advisory vs required" (medida no B2 e apenas
**re-conferida** no baseline) · o dicionário `AR-PROD-*` (varri só as seções E/F e a Parte 4) · o
idioma do `EstoqueFixture`/`seededTenant` e o formato do `casos.md` (copiado do `BulkEdit`).
**Re-varri obrigatoriamente:** a resolução da fonte de cada fluxo (Blade/rota/JS — cada um tem a
sua, e o `quick_add` tem **4 homônimos** que dariam paridade falsa) e os consumidores de cada rota.

**Gargalo:** a **resolução de fonte** do `quick-add` (desambiguar 4 homônimos: produto × cliente ×
unidade × marca) e a verificação das 3 portas de lane do BOM. Juntas, ~40% das tool calls.
**Custo por lacuna caiu**: B2 fechou 2 lacunas com ~40 calls; B3 fechou 4 com ~45 — porque o
formato, o fixture e a régua de veredito vieram prontos.

## Lições de mecanismo (o que atrapalhou / o que aprendi)

1. **Eu gamifiquei o painel sem querer, e o painel me deixou.** Escrevi `US-PROD-025` **dentro de
   uma linha de tabela** (a tabela de "fatos medidos"), e o `citadoComoAncora` conta linha-de-tabela
   como âncora estrutural → a US saiu do backlog **sem contrato nenhum**. Peguei porque comparei o
   backlog antes/depois (7 → 6 US). Corrigi movendo a menção pra prosa. **A regra `^\|` é boa
   contra prosa solta, mas qualquer tabela do documento vira superfície de âncora** — inclusive
   tabelas que não são de rastreabilidade. Sugestão pro dono do script (não implementei):
   restringir o branch de tabela às linhas cujo **primeiro campo** casa `UC-`/`US-`/`CU-`, que é a
   forma da tabela de rastreabilidade real.
2. **Citar UC de OUTRO módulo transfere a posse.** Escrevi `UC-EST-07`/`UC-EST-08` (do Estoque) nos
   meus `casos.md` do Produto; o `donoDe` atribuiu os dois ao Produto e o placar foi a **56 UC**
   em vez de 54 — requisito alheio contado como meu. Troquei por ponteiro *título + arquivo de
   teste + path do casos.md*, que re-localiza igual sem inflar. Registrei o porquê **dentro** dos
   dois arquivos, senão a próxima sessão "conserta" de volta.
3. **`_telas/` não é varrido pelo `casos-coverage-guard`, e isso muda o critério de parada.** Sem o
   G-2 punindo órfão, a disciplina do "≥2 fontes → UC; 1 fonte → `[BACKLOG]`" passa a ser só
   minha. Segui a régua (14 UC × 21 backlog), mas registro: **é a primeira casa de contrato do
   projeto sem gate próprio**. Se um dia [W] quiser fechar isso, o caminho é **estender o
   `requisitos-status`** (que já lê a 2ª casa) — nunca apontar o G-1 pra `resources/views/**`, que
   faria ~600 Blades nascerem em violação (big-bang de legado, lápide §5 2026-07-12).
4. **Escopo de varredura ≠ escopo da afirmação.** Escrevi *"em `Pages/Produto/` `opening_stock`
   aparece 2×"* tendo medido só os `.tsx`; o grep do diretório devolve 4 (2 `.md` de contrato
   entram). Corrigi os 5 sites e passei a citar o comando **com o filtro** que produz o número.
   É LC-08 em miniatura: o número estava certo, o **denominador anunciado** é que era outro.
5. **`php` não está no PATH desta máquina** — não consegui nem `php -l` nos 4 arquivos novos.
   Erro de sintaxe só aparece na lane. Não é violação (o proibido é `pest`/`artisan test`/`phpstan`
   local), mas vale registrar como risco desta corrida: **4 arquivos PHP entregues sem lint algum**.

## O que precisa do [W]

| # | Decisão | Onde está registrado |
|---|---|---|
| 1 | **Validade do estoque inicial**: unificar em `uf_date`? fixar ISO? validar no request? | `_telas/estoque-inicial.casos.md` §Backlog + SDD F9 |
| 2 | **`success: 1` para operação que não ocorreu** (produto alheio/sem estoque) — 404, 422 ou mensagem neutra? Mesma decisão pendente em `SellingPrices.casos.md` e agora no "Fix": **3 telas, 1 decisão** | §Backlog dos 3 arquivos |
| 3 | **Ciclo transitivo A→B→A no BOM** — validar no cadastro ou manter "explode no consumo" (desenho declarado no controller)? | `_telas/bom-combo.casos.md` §Backlog |
| 4 | **Ajuste do "Fix" deixa rastro no kardex?** `AR-PROD-064` exige origem+usuário por movimento; hoje escreve `qty_available` direto | `_telas/ajuste-estoque-relatorio.casos.md` §Backlog |
| 5 | **`BomResolverTest`/`ReservarEstoqueBomTest` que não rodam** — portar pra MySQL, entrar na `ci-sqlite-pest.list`, ou aposentar? (achado 1; fora do meu diff, não toquei) | este log + SDD nota F10 |
| 6 | **Alerta de reposição**: paridade Delphi tem **dois** limiares (`AR-PROD-053` Máx./Mín.), o oimpresso tem um (`alert_quantity`) — gap ou Non-Goal? | `_telas/estoque-inicial.casos.md` §Backlog |
| 7 | Aplicar as linhas `**Implementado em:**` no SPEC? **Não propus nenhuma nesta corrida** — as 4 lacunas eram CU/UC, não US sem âncora, e tocar o SPEC legado acorda o `anchor-lint` diff-aware sobre dívida grandfathered (lápide 2026-07-12) | — |
