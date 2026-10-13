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

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Alterar status e editar envio pela tela nova (PR próprio).
- [BACKLOG] Excluir pedido e enviar notificação pela tela nova.
- [BACKLOG] KPI "Vindos de requisição" — decisão [W].

## Trilha do tempo
- 2026-10-13 · [CL] criado com a F3 da thread `compras/playbook/06`.
