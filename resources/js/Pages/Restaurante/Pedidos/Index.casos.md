---
id: resources-js-pages-restaurante-pedidos-index-casos
casos: Pedidos · /modules/orders
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: pedido de outro negócio ou de outro atendente na tela é incidente; item servido duas vezes ou esquecido atrasa a mesa.
owner: wagner
last_run: "2026-10-13"
---

# Casos de Uso & Aceite — Pedidos

> Thread `restaurante/playbook/05` (Pedidos). Derivados do `OrderController`, do `RestaurantUtil` e das Blades
> `restaurant.orders.index` + `partials/line_orders` + `partials/show_orders` (RUNBOOK-pedidos) — não do `Index.tsx`.
> Teste: [`tests/Feature/Restaurante/PedidosContratoTest.php`](../../../../../tests/Feature/Restaurante/PedidosContratoTest.php),
> lane `acessos-pest.yml` (MySQL). A Blade está travada em `PedidosBaselineTest`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-PEDR-01 · Com a flag ligada, a tela abre em React
- **Persona:** atendente do salão que leva os pedidos à mesa.
- **Aceite:** Dado a flag `useV2RestaurantePedidos` ligada · Quando faço `GET /modules/orders` como o browser faz
  (`X-Inertia` **e** `X-Requested-With`) · Então renderiza **`Restaurante/Pedidos/Index`**.
- **Status: 🧪**

## UC-PEDR-02 · Com a flag desligada, ou com `?classico=1`, a Blade continua
- **Aceite:** Dado a flag desligada · Quando faço `GET /modules/orders` · Então a resposta é a Blade
  `restaurant.orders.index` · E com a flag ligada e `?classico=1` também.
- **Status: 🧪**

## UC-PEDR-03 · O atendente vê só os dele, do meu negócio · `[T0]`
- **Aceite:** Dado um pedido meu com um item recebido e um preparado, um pedido de um colega e um pedido de outro
  negócio com o meu id de atendente · Quando as listas carregam (props deferidas `itens` e `pedidos`) · Então vejo o meu
  pedido com situação `partial_cooked` e os dois itens · E nada do colega nem do outro negócio.
- **Status: 🧪**

## UC-PEDR-04 · Quem não é atendente escolhe o atendente
- **Aceite:** Dado um usuário que não é atendente · Quando abro a tela sem escolher · Então recebo a lista de atendentes,
  `atendente_escolhido` vazio e nenhum pedido · E com `?service_staff=<id>` recebo os pedidos daquele atendente.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] `markLineOrderAsServed` sem filtro de negócio — Tier 0 aberto, conserto em PR próprio (RUNBOOK §10).
- [BACKLOG] "Itens prontos para servir" só com itens preparados, se [W] decidir pelo protótipo.
- [BACKLOG] Imprimir item e detalhe da venda fora da clássica.

## Trilha do tempo
- 2026-10-13 · [CL] criado com a F3 da thread `restaurante/playbook/05`.
