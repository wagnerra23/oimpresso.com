---
casos: Pedido de venda · /sales-order
irmaos: Index.charter.md (lei) · Index.tsx
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: pedido que ainda não virou venda — quem vê, quem muda o status e em que negócio isso grava não muda num refactor visual.
owner: wagner
last_run: "2026-10-01"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Sells · MySQL)"
---

# Casos de Uso & Aceite — Pedido de venda

> **Fonte:** texto revisado em `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/PedidoVenda.casos.md`
> (3 casos, com id próprio `UC-SORD-*` porque o prefixo do texto revisado já é usado por outra tela e o
> casos-gate casa id no corpus global) + `SalesOrderController` real.
>
> **Teste:** `tests/Feature/Sells/SalesOrderIndexContratoTest.php` — tenant 98 × adversário 99,
> `DatabaseTransactions`, headers do browser (`X-Inertia` + `X-Requested-With`).
>
> ⚖️ **Lane:** `PHP / Pest (Sells · MySQL)` — `.github/workflows/sells-pest.yml`.
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.

## UC-SORD-01 · Recurso desligado é dito na tela `[must]`
- **Persona:** Larissa — o menu só mostra "Pedido de venda" com `enable_sales_order` ligado nas configurações do POS.
- **Aceite:** Dado `enable_sales_order` ligado no negócio da sessão · Quando abro `/sales-order` · Então `salesOrderEnabled` é verdadeiro; desligado, é falso; ligar em outro negócio não muda o meu.
- **Teste:** `SalesOrderIndexContratoTest` — `UC-SORD-01 a tela informa se enable_sales_order está ligado no negócio`.
- **Regressão que defende:** a tela lendo o flag de outro negócio. O item de menu continua em `AdminSidebarMenu` (inalterado).
- **Status: 🧪**

## UC-SORD-02 · Mudar status grava no lugar certo `[T0]` `[must]`
- **Persona:** admin do negócio — muda pedido → parcial → concluído pelo drawer lateral.
- **Aceite:** Dado um pedido do meu negócio · Quando salvo outro status · Então o PUT devolve `success` e a linha reflete sem recarregar a lista. Pedido de outro negócio devolve 404 e não muda. Quem não é admin recebe 403.
- **Teste:** `SalesOrderIndexContratoTest` — `UC-SORD-02 [T0] mudar status grava só no pedido do próprio business` e `UC-SORD-02 sem ser admin o status não muda`.
- **Regressão que defende:** status alterado em pedido de outra empresa (ADR 0093).
- **Status: 🧪**

## UC-SORD-03 · A tela abre com o que precisa `[must]`
- **Persona:** vendedor com `so.view_own`.
- **Aceite:** Dado esse usuário · Quando abre `/sales-order` pelo browser · Então recebe a Page `SalesOrder/Index` com os 3 status, as permissões dele e o endpoint legado da lista.
- **Teste:** `SalesOrderIndexContratoTest` — `UC-SORD-03 render Inertia entrega filtros, permissões e o endpoint legado da lista`.
- **Regressão que defende:** Page sem dado (o skeleton eterno do §5 2026-09-08) ou permissão errada no botão.
- **Status: 🧪**

## UC-SORD-04 · Sem permissão, sem tela `[must]`
- **Persona:** usuário sem `so.view_own`, `so.view_all` nem `so.create`.
- **Aceite:** Dado esse usuário · Quando abre `/sales-order` · Então recebe 403, igual ao Blade.
- **Teste:** `SalesOrderIndexContratoTest` — `UC-SORD-04 sem so.view_own, so.view_all nem so.create a tela devolve 403`.
- **Regressão que defende:** trava removida na migração.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Pedido parcial mostra quantidade restante maior que zero (2º caso do texto revisado). O número vem de `so_qty_remaining` do endpoint legado `/sells?sale_type=sales_order`, que esta migração não toca; ganha id quando o endpoint tiver teste de contrato.

## Trilha do tempo
- 2026-10-01 · [CL] trio criado na thread 06 do playbook `venda-menu`. Refs: ADR 0104 · ADR 0264 G-1/G-2 · ADR 0358.
