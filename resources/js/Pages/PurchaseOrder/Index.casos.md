---
id: resources-js-pages-purchaseorder-index-casos
casos: Pedidos de compra · /purchase-order
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: pedido de outro negócio, de local vetado ou com quantidade restante diferente da Blade leva o comprador a comprar errado.
owner: wagner
last_run: "2026-10-13"
---

# Casos de Uso & Aceite — Pedidos de compra

> Thread `compras/playbook/06`. Derivados do `PurchaseOrderController` e da Blade `purchase_order/index`
> (RUNBOOK-purchase-order-index, `purchase-order-parity.md`) — não do `Index.tsx`.
> Teste: [`tests/Feature/Purchase/PedidosCompraContratoTest.php`](../../../../tests/Feature/Purchase/PedidosCompraContratoTest.php),
> lane `purchase-pest.yml` (MySQL). A Blade está travada em `PedidosCompraBaselineTest`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-PEDC-01 · Com a flag ligada, a tela abre em React
- **Persona:** o comprador que acompanha o que foi pedido aos fornecedores.
- **Aceite:** Dado `purchase_order.view_all` e a flag `useV2ComprasPedidos` ligada · Quando faço `GET /purchase-order`
  como o browser faz (`X-Inertia` **e** `X-Requested-With`) · Então renderiza **`PurchaseOrder/Index`**.
- **Status: 🧪**

## UC-PEDC-02 · Com a flag desligada, ou com `?legacy=1`, a Blade continua
- **Aceite:** Dado a flag desligada · Quando faço `GET /purchase-order` · Então a resposta é a Blade
  `purchase_order.index` · E com a flag ligada e `?legacy=1` também.
- **Status: 🧪**

## UC-PEDC-03 · Meus pedidos, com a mesma quantidade restante da Blade · `[T0]`
- **Aceite:** Dado um pedido meu com linhas de 10 (3 já comprados) e 5 (nenhum comprado), uma compra recebida do meu
  negócio e um pedido de outro negócio · Quando a lista carrega (prop deferida `pedidos`) · Então vejo o meu pedido com
  `restante` 12 e `restante_texto` igual à coluna da DataTable da Blade para o mesmo pedido · E não vejo a compra
  recebida nem o pedido alheio.
- **Status: 🧪**

## UC-PEDC-04 · Sem acesso a todos os locais, só os pedidos dos meus locais · `[T0]`
- **Aceite:** Dado um usuário sem `access_all_locations`, com permissão só no local L · Quando a lista carrega · Então
  vejo o pedido de L · E não vejo o pedido de outro local do mesmo negócio.
- **Status: 🧪**

## UC-PEDC-05 · O filtro de status chega ao servidor
- **Aceite:** Dado um pedido parcial e um concluído · Quando a lista carrega com `status=partial` · Então vejo só o
  parcial · E o KPI `em_aberto` conta só os pedidos não concluídos da lista.
- **Status: 🧪**

## UC-PEDC-06 · Editar pedido só alcança pedido da própria empresa · `[T0]`
- **Aceite:** Dado um pedido de compra de outra empresa · Quando o formulário de edição é enviado com o id dele
  (`PUT /purchase-order/{id}`) · Então responde 404 e nada é gravado (referência e total ficam como estavam). O
  pedido da própria empresa é editado normalmente.
- **Teste:** `tests/Feature/Purchase/PedidoCompraUpdateTenantTest.php`
- **Contrato:** ADR 0093. Auditoria Compras 2026-11-05, achado A1.
- **Status: 🧪**

## UC-PEDC-07 · Subir o valor na edição passa pela alçada de novo · `[T0]`
- **Aceite:** Dado a alçada ligada (comprador até 1.000,00) e um pedido de 500,00 · Quando quem não tem alçada de
  gerente edita o pedido para 5.000,00 · Então o pedido fica aguardando aprovação de gerente e não pode virar compra
  recebida. Editar sem mudar o valor não mexe na aprovação.
- **Teste:** `tests/Feature/Purchase/PedidoCompraUpdateTenantTest.php`
- **Contrato:** SPEC US-COM-032. Auditoria Compras 2026-11-05, achado A7.
- **Status: 🧪**

## UC-PEDC-08 · Pedido novo só aceita requisição e linha da própria empresa · `[T0]`
- **Aceite:** Dado uma requisição de compra de outra empresa (ou a linha dela, ou uma linha de compra qualquer) ·
  Quando o pedido novo chega com `purchase_requisition_ids`, `purchase_requisition_line_id` ou `purchase_line_id`
  apontando para ela · Então o pedido é recusado com 422, nenhum pedido nasce e a requisição fica com o status e a
  quantidade pedida de antes. Requisição e linha da própria empresa passam.
- **Teste:** `tests/Feature/Purchase/PedidoCompraStoreTenantTest.php`
- **Contrato:** ADR 0093. Auditoria Compras 2026-11-05, achado A2.
- **Status: 🧪**

## UC-PEDC-09 · Trocar o status do pedido só aceita pedido de compra e status da lista
- **Aceite:** Dado um administrador da empresa · Quando ele troca o status pela rota do pedido de compra · Então só
  um pedido de compra (`type=purchase_order`) muda, e só para `ordered`, `partial` ou `completed`. Uma venda
  ou outra transação da mesma empresa, ou um status fora da lista, voltam `success=0` e nada é gravado.
- **Teste:** `tests/Feature/Purchase/ComprasStatusPermissaoUnidadeTest.php`
- **Contrato:** auditoria Compras 2026-11-05, achado D3 (lote 2.3).
- **Status: 🧪**

## UC-PEDC-10 · Endpoints de apoio do pedido e da compra conferem permissão e unidade
- **Aceite:** Dado um usuário sem a permissão da tela que chama o endpoint · Quando ele pede as linhas do pedido,
  a impressão da compra, os produtos da requisição, as requisições do local ou as linhas da requisição · Então
  recebe 403; com a permissão da tela, passa. E a sub-unidade de uma linha de compra só vale se for da empresa da
  compra: a de outra empresa não grava linha; a própria aplica o multiplicador (2 caixas de 12 = 24 un).
- **Teste:** `tests/Feature/Purchase/ComprasStatusPermissaoUnidadeTest.php`
- **Contrato:** ADR 0093 (sub-unidade). Auditoria Compras 2026-11-05, achado D3 (lote 2.3).
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Alterar status e editar envio pela tela nova (PR próprio).
- [BACKLOG] Excluir pedido e enviar notificação pela tela nova.
- [BACKLOG] KPI "Vindos de requisição" — decisão [W].

## Trilha do tempo
- 2026-10-13 · [CL] criado com a F3 da thread `compras/playbook/06`.
- 2026-11-05 · [CL] UC-PEDC-06 e UC-PEDC-07 (lote 0 da auditoria de Compras, PR 0.3).
- 2026-11-05 · [CL] UC-PEDC-08 (lote 0 da auditoria de Compras, PR 0.4).
- 2026-11-06 · [CL] UC-PEDC-09 e UC-PEDC-10 (lote 2 da auditoria de Compras, PR 2.3).
