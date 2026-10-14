---
id: resources-js-pages-restaurante-cozinha-index-casos
casos: Cozinha · /modules/kitchen
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: pedido de outro negócio na fila é incidente Tier 0; pedido que some da fila antes de ficar pronto atrasa a mesa.
owner: wagner
last_run: "2026-10-13"
---

# Casos de Uso & Aceite — Cozinha

> Thread `restaurante/playbook/04` (Cozinha). Derivados do `KitchenController`, do `RestaurantUtil::getAllOrders` e do
> `partials/show_orders.blade.php` (RUNBOOK-cozinha) — não do `Index.tsx`.
> Teste: [`tests/Feature/Restaurante/CozinhaContratoTest.php`](../../../../../tests/Feature/Restaurante/CozinhaContratoTest.php),
> lane `acessos-pest.yml` (MySQL). A Blade está travada em `CozinhaBaselineTest`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-COZ-01 · Com a flag ligada, a tela abre em React
- **Persona:** quem cozinha e acompanha a fila de pedidos.
- **Aceite:** Dado `sell.view` e a flag `useV2RestauranteCozinha` ligada · Quando faço `GET /modules/kitchen` como o
  browser faz (`X-Inertia` **e** `X-Requested-With`) · Então renderiza **`Restaurante/Cozinha/Index`**.
- **Status: 🧪**

## UC-COZ-02 · Com a flag desligada, ou com `?classico=1`, a Blade continua
- **Aceite:** Dado a flag desligada · Quando faço `GET /modules/kitchen` · Então a resposta é a Blade
  `restaurant.kitchen.index` · E com a flag ligada e `?classico=1` também.
- **Status: 🧪**

## UC-COZ-03 · A fila é a da Blade, com a situação dos itens · `[T0]`
- **Aceite:** Dado um pedido de cozinha meu com um item recebido e um preparado, um pedido meu todo preparado e um
  pedido de outro negócio · Quando a fila carrega (prop deferida `pedidos`) · Então vejo só o primeiro, com situação
  `partial_cooked` e os dois itens com a situação de cada um · E os outros dois não aparecem.
- **Status: 🧪**

## UC-COZ-04 · Marcar como preparado tira o pedido da fila
- **Aceite:** Dado o pedido da fila · Quando chamo o endpoint que a tela usa (`GET /modules/kitchen/mark-as-cooked/{id}`)
  · Então os itens recebidos viram `cooked` · E a fila recarregada não traz mais o pedido.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Detalhes da venda num drawer (hoje é modal parcial da Blade).
- [BACKLOG] Gate de permissão em `markAsCooked` e nos `refresh*` — resíduo da US-GOV-059.

## Trilha do tempo
- 2026-10-13 · [CL] criado com a F3 da thread `restaurante/playbook/04`.
