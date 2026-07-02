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
- **Status: 🧪** _(fluxo núcleo `addSellReturn`; o caminho Vestuario `DevolucaoService` é investigado à parte — ver Backlog)._

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado (heading `## UC-…`) sem teste citando o id = órfão → falha o gate.
> Os itens abaixo ficam SEM heading de UC de propósito até existir teste real — visíveis,
> não esquecidos, sem virar dívida no baseline.

- **[BACKLOG] UC-EST-02b — Compra RASCUNHO não entra / RECEIVED entra (decisão de status via `createOrUpdatePurchaseLines`)** — reforço não-tautológico do UC-EST-02: provar que só o status terminal `received` movimenta. Exige fixture de purchase Transaction + purchase_lines.
- **[BACKLOG] Ajuste de estoque → SAI (normal) / reverte ao deletar** — PR2 (`stock_adjustment` DOC-RAIZ §3).
- **[BACKLOG] Transferência → SAI origem + ENTRA destino** — PR2.
- **[BACKLOG] Estoque inicial (opening) → ENTRA** — PR2.
- **[BACKLOG] Fabricação → consome componentes + produz acabado** — PR2.
- **[BACKLOG] OS OficinaAuto → baixa peça ao concluir** — já coberto por `Modules/OficinaAuto/Tests/Feature/ServiceOrderItemStockBaixaTest.php` (skip-gracioso; roda no CT 100).
- **[BACKLOG] INV-2/3/5/6** — invariantes, PR3 (rascunho não move · dentro de DB::transaction · `enable_stock=0` não move · tenant isolado biz=1 vs biz=2).
- **[SUSPEITA/red-spec] Devolução Vestuario (`Modules/Vestuario/Services/DevolucaoService`) NÃO reintegraria estoque** — investigar; se confirmado, red-spec (`markTestSkipped` apontando o bug) + FLAG Wagner (regra mestre VALOR/ESTOQUE — proibicoes.md). NÃO corrigir a lógica de estoque sem dupla-confirmação Wagner.

## Como rodar a suíte
1. **Lane CI:** `estoque-pest.yml` (MySQL real + seed biz=1/biz=2 via `pest-mysql-setup`) — allowlist verde (catraca).
2. **CT 100:** `tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging php artisan test --filter=Estoque"`.
3. **NUNCA local/Hostinger** (proibicoes — Pest só no CT 100). Captura de veredito: `npm run casos:results` sobre o JUnit da lane → bumpa Status 🧪→✅.

## Trilha do tempo
- 2026-07-02 · [CC] criado — PR1 do mandato "cobertura real de estoque": `EstoqueFixture` + UC-EST-01/02/03 (venda/compra/devolução) + lane `estoque-pest.yml`. UCs em 🧪 até a lane MySQL capturar o verde no manifesto.
