---
casos: Movimentação de estoque (domínio) · núcleo UltimatePOS + Modules
irmaos: memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md (contrato — matriz §3 + invariantes §7)
tecnica: Caso de uso = fluxo de negócio que MOVE saldo + critério de aceite (Dado/Quando/Então) verificável por Pest que QUEBRA quando o saldo não move certo.
por_que: os movimentos do dia-a-dia (venda, compra, devolução, ajuste, transferência, opening, fabricação) não tinham UM teste sequer afirmando o delta de qty_available. Isto é o contrato de não-regressão do estoque (pedido Wagner 2026-07-02).
owner: wagner
last_run: "2026-07-02"
---

# Casos de Uso & Aceite — Movimentação de Estoque

> **casos.md de DOMÍNIO (não de tela).** O estoque é transversal (muitos controllers/telas,
> boa parte ainda Blade). O contrato vive no [DOC-RAIZ-ESTOQUE](../../../../memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md)
> (matriz de movimentação §3 + invariantes INV-1..6 §7) — os UCs abaixo são DESTILADOS desse
> contrato, **não derivados do código** (anti-tautológico, proibicoes §ideias-descartadas).
>
> Cada UC tem um Pest que MONTA saldo conhecido (via `Tests\Support\EstoqueFixture`, biz=1
> dogfood — ADR 0101), executa o fluxo e ASSERTA o `qty_available` final. Roda na lane MySQL
> (`estoque-pest.yml`) + CT 100 — em sqlite faz skip gracioso (o gate é o MySQL, não o skip).
>
> **Status:** ✅ passa (com prova no manifesto G-7) · 🧪 em teste/prova parcial (verde ainda
> não capturado no manifesto — falta rodar `npm run casos:results` sobre a lane MySQL) ·
> ⬜ não verificado · ❌ quebrou.

---

## UC-EST-01 · Venda vira FINAL → SAI do estoque
- **Fluxo:** venda de balcão/POS. Ao transicionar RASCUNHO→FINAL, o saldo de cada item vendido baixa pela quantidade vendida (DOC-RAIZ §3 `sell` → `decreaseProductQuantity` via `adjustProductStockForInvoice`). Estornar (FINAL→RASCUNHO) devolve.
- **Aceite:** Dado produto com `qty_available=10` na location · Quando a venda de 3 vira FINAL · Então `qty_available=7`. E: estorno (FINAL→RASCUNHO de linha existente) volta pra 10. E: numa variável, vender a variação A não mexe o saldo da B.
- **Teste:** `tests/Feature/Estoque/EstoqueMovimentacaoVendaTest.php`.
- **Status: 🧪** _(3 asserts escritos; verde a capturar na lane MySQL — `casos:results` bumpa pra ✅)._

---

## UC-EST-02 · Compra recebida → ENTRA no estoque
- **Fluxo:** recebimento de compra. Ao virar `received`, o saldo soma a quantidade recebida no local de destino e na variação comprada (DOC-RAIZ §3 `purchase` → `updateProductQuantity`, chamado por `createOrUpdatePurchaseLines`).
- **Aceite:** Dado produto com `qty_available=10` · Quando recebe +5 · Então `qty_available=15`. E: recebimento ACUMULA sobre o saldo (não sobrescreve). E: produto sem VLD prévia cria a linha. E: numa variável, entra só na variação recebida.
- **Teste:** `tests/Feature/Estoque/EstoqueMovimentacaoCompraTest.php`.
- **Status: 🧪** _(nível do mutador de entrada; ver UC-EST-02b pra reforço de fluxo)._

---

## UC-EST-03 · Devolução de venda → ENTRA (volta pro estoque)
- **Fluxo:** cliente devolve item de uma venda. A devolução reintegra `qty_available` no local da venda, pela quantidade devolvida (DOC-RAIZ §3 `sell_return` → `addSellReturn` → `updateProductQuantity`, TransactionUtil.php:6189). Caminho NÚCLEO UltimatePOS.
- **Aceite:** Dado venda de 5 com saldo pós-venda `qty_available=8` · Quando devolve 2 · Então `qty_available=10`. E: devolução parcial reintegra só o devolvido (devolve 1 de 5 → +1).
- **Teste:** `tests/Feature/Estoque/EstoqueDevolucaoVendaTest.php`.
- **Status: 🧪** _(fluxo núcleo `addSellReturn`; o caminho Vestuario `DevolucaoService` é o UC-EST-04 abaixo)._

---

## UC-EST-04 · Devolução Vestuario → REINTEGRA estoque (bug Tier 0 CORRIGIDO)
- **Fluxo:** devolução pelo módulo Vestuario (`DevolucaoService::registrarDevolucao` — estorno_dinheiro / crédito ficha / troca). O item físico volta pra loja, então o saldo do sistema volta (DOC-RAIZ §3 `sell_return` → ENTRA), como o núcleo faz em `addSellReturn`.
- **Era bug (RED-SPEC), CORRIGIDO 2026-07-02 (Wagner-aprovado, regra mestre VALOR/ESTOQUE):** o service reintegra a quantidade devolvida no local da venda via `ProductUtil::updateProductQuantity` (auditável — INV-1/INV-5), no `DB::transaction` (INV-3), com guard Tier 0 cross-tenant (ADR 0093). Reason-agnostic (todos os tipos).
- **Aceite:** Dado saldo pós-venda `qty_available=8` · Quando `registrarDevolucao(estorno_dinheiro, quantidade_devolvida=2)` · Então `qty_available=10`. E: credito_ficha reintegra igual. E: devolver sell_line de outro business é rejeitado (não reintegra).
- **Teste:** `tests/Feature/Estoque/EstoqueDevolucaoVestuarioTest.php`.
- **Status: 🧪** _(fix aplicado + 3 asserts verdes na lane MySQL; `casos:results` bumpa pra ✅)._

---

## UC-EST-05 · Ajuste de estoque → SAI (normal) / reverte ao deletar
- **Fluxo:** ajuste manual de estoque. Um ajuste de saída baixa `qty_available` pela quantidade ajustada (DOC-RAIZ §3 `stock_adjustment` → `decreaseProductQuantity`); deletar o ajuste reverte (devolve) o saldo.
- **Aceite:** Dado `qty_available=10` · Quando ajuste de saída de 4 · Então `qty_available=6`. E: deletar o ajuste devolve → 10.
- **Teste:** `tests/Feature/Estoque/EstoqueAjusteTest.php`.
- **Status: 🧪** _(nível do mutador; verde a capturar na lane MySQL)._

---

## UC-EST-06 · Transferência entre locais → SAI origem + ENTRA destino
- **Fluxo:** transferência de estoque entre duas locations. Decrementa o saldo da ORIGEM e incrementa o do DESTINO pela mesma quantidade, conservando o total (DOC-RAIZ §3 `sell_transfer`+`purchase_transfer` → `decreaseProductQuantity`+`updateProductQuantity`).
- **Aceite:** Dado origem=10 e destino=2 · Quando transfere 3 · Então origem=7, destino=5, total conservado=12. E: um TERCEIRO local do mesmo produto não é tocado.
- **Teste:** `tests/Feature/Estoque/EstoqueTransferenciaTest.php`.
- **Status: 🧪** _(prova o par de 2 lados + especificidade por local — não-tautológico)._

---

## UC-EST-07 · Estoque inicial (opening) → ENTRA
- **Fluxo:** informar estoque inicial de um produto num local cria o saldo pela quantidade informada (DOC-RAIZ §3 `opening_stock` → `addSingleProductOpeningStock` → `updateProductQuantity` + Transaction `opening_stock`). Fluxo REAL.
- **Aceite:** Dado produto sem VLD · Quando opening de 10 no local · Então `qty_available=10`.
- **Teste:** `tests/Feature/Estoque/EstoqueOpeningStockTest.php`.
- **Status: 🧪** _(fluxo real `addSingleProductOpeningStock`)._

---

## UC-EST-08 · Fabricação / kit → consome componentes + produz acabado
- **Fluxo:** montar um kit / produzir um acabado consome o saldo de cada componente pela quantidade da receita (`decreaseProductQuantityCombo`) e entra o saldo do acabado (`updateProductQuantity`).
- **Aceite:** Dado compA=20 e compB=20 · Quando fabrica com receita [A×2, B×1] · Então A=18, B=19 (quantidades distintas). E: produzir 5 do acabado → acabado=5.
- **Teste:** `tests/Feature/Estoque/EstoqueFabricacaoTest.php`.
- **Status: 🧪** _(decomposição de combo/kit; fluxo completo Modules/Manufacturing = UC-EST-08b follow-up)._

---

## UC-INV-02 · INV-2: rascunho/cotação NÃO movimenta
- **Contrato (§7 INV-2):** só status terminal (`final`/`received`) mexe saldo. Venda em RASCUNHO ou COTAÇÃO não toca `qty_available`.
- **Aceite:** Dado `qty_available=10` · Quando `adjustProductStockForInvoice` draft→draft (ou quotation) · Então `qty_available=10` (intocado).
- **Teste:** `tests/Feature/Estoque/EstoqueInvarianteRascunhoTest.php`.
- **Status: 🧪**

---

## UC-INV-03 · INV-3: movimentação dentro de DB::transaction (atomicidade)
- **Contrato (§7 INV-3):** movimento roda dentro de `DB::transaction` → falha no meio = nada persiste (movimento parcial não vaza). Prova behavioral, não grep estrutural.
- **Aceite:** Dado `qty_available=10` · Quando uma baixa dentro de `DB::transaction` estoura · Então `qty_available=10` (revertido). Contraprova: sem exceção, commita → 6.
- **Teste:** `tests/Feature/Estoque/EstoqueInvarianteTransacaoTest.php`.
- **Status: 🧪**

---

## UC-INV-05 · INV-5: enable_stock=0 não movimenta
- **Contrato (§7 INV-5):** produto sem controle de estoque (`enable_stock=0`) não tem o saldo mexido — os mutadores checam `enable_stock == 1` antes de tocar `qty_available`.
- **Aceite:** Dado produto `enable_stock=0` com VLD=10 · Quando baixa/entrada · Então `qty_available=10` (guard). Contraprova: `enable_stock=1` baixa → 7.
- **Teste:** `tests/Feature/Estoque/EstoqueInvarianteEnableStockTest.php`.
- **Status: 🧪**

---

## UC-INV-06 · INV-6: isolamento multi-tenant do saldo
- **Contrato (§6/§7 INV-6, ADR 0093):** VLD não tem `business_id`; isolamento é TRANSITIVO (variation_id/location_id/product_id são PKs globais únicas — sem colisão entre businesses). Movimento do biz=1 não alcança o saldo do biz=2.
- **Aceite:** Dado biz=1 (V1/L1=10) e biz=2 (V2/L2=10), IDs distintos · Quando baixa 3 no biz=1 · Então biz=1=7 e **biz=2=10 intocado**.
- **Teste:** `tests/Feature/Estoque/EstoqueInvarianteTenantTest.php` (requer 2º tenant — lane seed biz=1/biz=2).
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado (heading `## UC-…`) sem teste citando o id = órfão → falha o gate.
> Os itens abaixo ficam SEM heading de UC de propósito até existir teste real — visíveis,
> não esquecidos, sem virar dívida no baseline.

- **[BACKLOG] UC-EST-02b — Compra RASCUNHO não entra / RECEIVED entra (decisão de status via `createOrUpdatePurchaseLines`)** — reforço não-tautológico do UC-EST-02: provar que só o status terminal `received` movimenta. Exige fixture de purchase Transaction + purchase_lines.
- **[BACKLOG] UC-EST-08b — Fabricação fluxo completo (`Modules/Manufacturing/ProductionController`)** — receita/BOM real consome ingredientes + produz acabado end-to-end (UC-EST-08 cobre a decomposição via `decreaseProductQuantityCombo`).
- **[BACKLOG] OS OficinaAuto → baixa peça ao concluir** — já coberto por `Modules/OficinaAuto/Tests/Feature/ServiceOrderItemStockBaixaTest.php` (skip-gracioso; roda no CT 100).
- **[BACKLOG] INV-1/INV-4** — já cobertas: INV-1 (saldo só por caminho auditável) por `ProductStockLogsActivityTest`/`ConsumirEstoqueAuditTest`; INV-4 (reserva ≠ baixa) por `StockReservationsTest` (caso 9).

> **Bug UC-EST-04 CORRIGIDO** (Wagner-aprovado 2026-07-02): a suspeita "Devolução Vestuario não reintegra estoque" foi confirmada, virou red-spec e agora está fechada — o `DevolucaoService` reintegra via ProductUtil auditável. O caminho era um scaffold (US-VEST-021, não wired) → zero impacto retroativo em dados.

## Como rodar a suíte
1. **Lane CI:** `estoque-pest.yml` (MySQL real + seed biz=1/biz=2 via `pest-mysql-setup`) — allowlist verde (catraca).
2. **CT 100:** `tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging php artisan test --filter=Estoque"`.
3. **NUNCA local/Hostinger** (proibicoes — Pest só no CT 100). Captura de veredito: `npm run casos:results` sobre o JUnit da lane → bumpa Status 🧪→✅.

## Trilha do tempo
- 2026-07-02 · [CC] criado — PR1 do mandato "cobertura real de estoque": `EstoqueFixture` + UC-EST-01/02/03 (venda/compra/devolução) + lane `estoque-pest.yml`. 9 asserts VERDES na lane MySQL (não skip). UCs em 🧪 até `casos:results` capturar o verde no manifesto G-7.
- 2026-07-02 · [CC] UC-EST-04 (red-spec ❌) — bug Tier 0 confirmado: `Vestuario\DevolucaoService` não reintegra estoque. Contrato pronto (`markTestSkipped`); fix aguarda decisão Wagner (regra mestre VALOR/ESTOQUE).
- 2026-07-02 · [CC] PR2 — UC-EST-05 ajuste · UC-EST-06 transferência (par 2-lados + conservação) · UC-EST-07 opening (fluxo real) · UC-EST-08 fabricação/kit (decomposição combo).
- 2026-07-02 · [CC] PR3 — invariantes UC-INV-02 (rascunho não move) · UC-INV-03 (DB::transaction atômica, behavioral) · UC-INV-05 (enable_stock=0 guard) · UC-INV-06 (isolamento tenant transitivo biz=1 vs biz=2). INV-1/INV-4 já cobertas alhures.
- 2026-07-02 · [CC] UC-EST-04 fix — `Vestuario\DevolucaoService::registrarDevolucao` passa a reintegrar estoque (ProductUtil auditável + guard Tier 0). Red-spec ❌ → contrato vivo 🧪. Scaffold não-wired = zero impacto retroativo.
