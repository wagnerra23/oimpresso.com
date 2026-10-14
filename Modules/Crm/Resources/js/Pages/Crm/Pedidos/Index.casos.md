---
id: modules-crm-pages-crm-pedidos-index-casos
casos: Crm · Pedido de ordem · /crm/order-request
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela mostra pedidos e valores de clientes. Errar o escopo expõe pedido de outro negócio ou de colega; errar a soma mostra um total de pedidos que não existe.
owner: wagner
last_run: "2026-10-13"
last_run_ci: "_pendente_ — o trio nasce na thread Crm/17. O veredito por UC entra no manifesto quando a lane verticais-pest rodar; até lá o Status é 🧪."
---

# Casos de Uso & Aceite — Crm · Pedido de ordem (`/crm/order-request`)

> **Âncora:** o [charter](Index.charter.md) (Goals e Anti-hooks), as permissões com que a Blade desenha a
> grade (`so.view_all` / `so.view_own`) e a regra mestre de valor (`memory/proibicoes.md`). Os UCs não derivam do `.tsx`.

---

## UC-CRMPED-01 · Com a flag, a lista responde em Inertia; sem ela, a Blade · `must`

**Dado** que tenho `so.view_all`
**Quando** abro `/crm/order-request` com a flag `useV2CrmPedidos` ligada
**Então** recebo Inertia `Crm/Pedidos/Index` — e com a flag desligada, ou com `?classico=1`,
recebo a view `crm::order_request.all_list`.

Status: 🧪

---

## UC-CRMPED-02 · Só pedidos de cliente do meu negócio · `must` `[T0]`

**Dado** um pedido de cliente no meu negócio, outro no negócio vizinho e um pedido de venda interno
**Quando** abro a lista
**Então** vejo só o pedido de cliente do meu negócio.

Status: 🧪

---

## UC-CRMPED-03 · Quem só vê os próprios vê só os que criou · `must`

**Dado** que tenho só `so.view_own`
**Quando** abro a lista
**Então** vejo o pedido que eu criei e não o do colega — e sem `so.view_all` nem `so.view_own`
não vejo pedido nenhum.

Status: 🧪

---

## UC-CRMPED-04 · O total do rodapé é a soma dos pedidos filtrados · `must`

**Dado** dois pedidos de cliente com totais conhecidos, um deles com duas linhas de venda
**Quando** abro a lista
**Então** cada linha traz o total gravado no pedido e o rodapé traz a soma dos dois — sem contar o
pedido em dobro por causa das linhas de venda.

Status: 🧪

---

## UC-CRMPED-05 · Filtro por status e busca por número · `should`

**Dado** um pedido `ordered` e outro `completed` no meu negócio
**Quando** filtro por `completed`, ou busco pelo número do outro
**Então** a lista mostra só o pedido correspondente.

Status: 🧪
