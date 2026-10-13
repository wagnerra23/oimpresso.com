---
id: resources-js-pages-purchaserequisition-index-casos
casos: Requisições de compra · /purchase-requisition
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: requisição de outro negócio ou de local vetado, ou prazo diferente do da Blade, leva o comprador a repor errado.
owner: wagner
last_run: "2026-10-13"
---

# Casos de Uso & Aceite — Requisições de compra

> Thread `compras/playbook/07`. Derivados do `PurchaseRequisitionController` e da Blade `purchase_requisition/index`
> (RUNBOOK-purchase-requisition-index, `purchase-requisition-parity.md`) — não do `Index.tsx`.
> Teste: [`tests/Feature/Purchase/RequisicoesCompraContratoTest.php`](../../../../tests/Feature/Purchase/RequisicoesCompraContratoTest.php),
> lane `purchase-pest.yml` (MySQL). A Blade está travada em `RequisicoesCompraBaselineTest`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-REQC-01 · Com a flag ligada, a tela abre em React
- **Persona:** o comprador que acompanha o que os locais pediram para repor.
- **Aceite:** Dado `purchase_requisition.view_all` e a flag `useV2ComprasRequisicoes` ligada · Quando faço
  `GET /purchase-requisition` como o browser faz (`X-Inertia` **e** `X-Requested-With`) · Então renderiza
  **`PurchaseRequisition/Index`**.
- **Status: 🧪**

## UC-REQC-02 · Com a flag desligada, ou com `?legacy=1`, a Blade continua
- **Aceite:** Dado a flag desligada · Quando faço `GET /purchase-requisition` · Então a resposta é a Blade
  `purchase_requisition.index` · E com a flag ligada e `?legacy=1` também.
- **Status: 🧪**

## UC-REQC-03 · Minhas requisições, com o mesmo "necessário até" da Blade · `[T0]`
- **Aceite:** Dado uma requisição minha necessária até 10/03/2031, um pedido de compra do meu negócio e uma requisição de
  outro negócio · Quando a lista carrega (prop deferida `requisicoes`) · Então vejo a minha com `necessario_ate` igual à
  coluna da DataTable da Blade para a mesma requisição · E não vejo o pedido nem a requisição alheia.
- **Status: 🧪**

## UC-REQC-04 · Sem acesso a todos os locais, só as requisições dos meus locais · `[T0]`
- **Aceite:** Dado um usuário sem `access_all_locations`, com permissão só no local L · Quando a lista carrega · Então
  vejo a requisição de L · E não vejo a de outro local do mesmo negócio.
- **Status: 🧪**

## UC-REQC-05 · O filtro de status chega ao servidor e os KPIs contam pelo status
- **Aceite:** Dado uma requisição ordenada, uma parcial e uma concluída · Quando a lista carrega com `status=partial` ·
  Então vejo só a parcial · E sem filtro, `sem_pedido` conta as ordenadas e `parciais` conta as parciais da lista.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Ver o detalhe e excluir pela tela nova (PR próprio).
- [BACKLOG] KPI "Itens requisitados" e coluna "Pedido gerado" — decisão [W].

## Trilha do tempo
- 2026-10-13 · [CL] criado com a F3 da thread `compras/playbook/07`.
