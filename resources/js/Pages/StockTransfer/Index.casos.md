---
casos: Transferências de estoque · /stock-transfers
irmaos: Index.charter.md (lei) · Index.tsx
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a lista mostra o que saiu de uma filial e entrou em outra; empresa misturada ou filtro quebrado escondem mercadoria em trânsito.
owner: wagner
last_run: "2026-10-27"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Estoque · MySQL)"
---

> ℹ️ **2026-10-13 — o que mudou na tela NÃO foi comportamento.** O playbook estoque (thread 02) acrescenta 3 atributos
> `data-contract` (`est-tr-cabecalho` num div em volta do `PageHeader`, `est-tr-toolbar` no card de filtros, `est-tr-tabela` no card
> da tabela) para o contrato `governance/design/contracts/estoque-transferencias.contract.json`. Zero handler, zero dado, zero copy.
> Nenhum UC afirma âncora; nenhum UC foi reexecutado.

> ℹ️ **2026-10-27 — a lista ganhou o botão "Editar".** Aparece só com `purchase.update` + `purchase.create` e enquanto a
> transferência não moveu saldo (paridade com a Blade). O comportamento da edição é contrato de `Edit.casos.md` (UC-TREDT-01..03).
> Os 3 UCs abaixo (isolamento, permissão, detalhe) não tratam do botão e seguem ⬜: revalidados pela leitura, nenhum reexecutado.

# Casos de Uso & Aceite — Transferências de estoque (lista)

> **Fonte:** [DOC-RAIZ-ESTOQUE](../../../../memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md) §3/§6/§7 + RUNBOOK da tela (`memory/requisitos/Estoque/_telas/`); o controller só confirma. Nada aqui deriva do `.tsx`.
> Ajuste e transferência gravam quantidade: conserto de qualquer item do Backlog segue a REGRA MESTRE e o merge é do [W].
> **Teste:** `tests/Feature/Stock/MovimentacoesTelasContratoTest.php` (só leitura · tenants 98 × 99 · lane `PHP / Pest (Estoque · MySQL)`).
> **Status:** ✅ passa (G-7) · 🧪 cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.

## UC-TRIDX-01 · A lista só mostra transferências do próprio business `[T0]` `[must]`
- **Aceite:** Dado uma transferência (par `sell_transfer` + `purchase_transfer`) no meu business e outra no 99 · Quando abro `/stock-transfers?v=2` · Então vejo a minha e não a do 99.
- **Teste:** `MovimentacoesTelasContratoTest` — `UC-TRIDX-01 [T0] a lista de transferências só mostra transferências do próprio business`.
- **Regressão que defende:** consulta sem `transactions.business_id` (ADR 0093). O caso exige a própria linha presente.
- **Status: ⬜**

## UC-TRIDX-02 · Sem permissão de compra, não abre `[must]`
- **Aceite:** Dado um usuário sem `purchase.view`, `purchase.create` e `view_own_purchase` · Quando abre a lista · Então recebe 403.
- **Teste:** `MovimentacoesTelasContratoTest` — `UC-AJIDX-02 e UC-TRIDX-02 sem permissão de compra as duas listas respondem 403`.
- **Status: ⬜**

## UC-TRIDX-03 · O "Ver" mostra o detalhe do transferência, só do próprio business `[T0]` `[must]`
- **Aceite:** Dado um transferência no meu business e outro no 99 · Quando clico em "Ver" na lista (o diálogo pede `GET /stock-transfers/{id}` com `Accept: application/json`) · Então recebo o detalhe do meu (referência, linhas, totais; valores só com `view_purchase_price`) e 404 no do 99.
- **Teste:** `MovimentacoesTelasContratoTest` — `UC-TRIDX-03 [T0] o Ver da lista de transferências devolve o detalhe em JSON, só do próprio business`.
- **Regressão que defende:** o botão fazia `router.visit` e o `show()` devolvia o fragmento de modal da tela Blade, que não é resposta Inertia (achado 2026-10-13, depois do cutover #9096). E id de outro business caía em 500 em vez de 404.
- **Status: ⬜**

## UC-TRIDX-04 · O "Imprimir" gera o recibo da transferência, só do próprio business `[T0]` `[must]`
- **Persona:** quem separa e despacha a mercadoria e leva o recibo junto com a carga.
- **Aceite:** Dado uma transferência do business 98 · Quando a lista pede `GET /stock-transfers/print/{id}` · Então recebe `success = 1` e o recibo em HTML com a referência dela (o mesmo `stock_transfer.print` da Blade); pedida por outro business, recebe `success = 0` e nenhum HTML.
- **Teste:** [`tests/Feature/Estoque/EstoqueTransferenciaImpressaoTest.php`](../../../../tests/Feature/Estoque/EstoqueTransferenciaImpressaoTest.php) — os casos `UC-TRIDX-04`.
- **Regressão que defende:** até 2026-10-27 o botão abria `/stock-transfers/{id}/print` numa aba, rota que não existe (404). A impressão continua sendo o HTML da Blade, por decisão [W] de 2026-10-27.
- **Status: 🧪**

## Backlog
- [BACKLOG] O filtro não volta a página: mesmo defeito de ordem `ajax()` × `X-Inertia` do `index()` de ajustes (ver `StockAdjustment/Index.casos.md`). **Consertado em 2026-10-09** no cutover pelo menu · thread 04: a decisão React/Blade passou pra antes do ramo `ajax()`; a visita Inertia com `X-Requested-With` recebe a Page (`tests/Feature/CutoverMenu/EstoqueSemXInertiaTest.php`). Vira UC quando um caso citar o filtro.
- [BACKLOG] `destroy()` busca a transferência só por `id` + `type`, sem `business_id` (mesmo achado do `destroy()` de ajuste). Grava quantidade: decisão [W].
- [BACKLOG] A lista corta em 200 linhas (`limit(200)`) sem paginação nem aviso.
- [BACKLOG] **Esta lista vira a tela da requisição de transferência (US-ESTOQUE-001) — com o [W]** (decisões de [F] em 2026-10-15, detalhe em [`Create.casos.md`](Create.casos.md) §Backlog): toda transferência chega aqui **pendente** (não há baixa automática) e mostra **Aprovar** (executa a transferência e muda o saldo) e **Recusar** (situação nova "recusada", fica no histórico). Quem pode aprovar ou recusar é uma **permissão**, não uma pessoa fixa.
