---
id: resources-js-pages-purchasereturn-index-casos
casos: Devoluções de compra · /purchase-return
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é dinheiro — devolução de outro negócio na tela, ou devido diferente do da Blade, faz cobrar ou pagar errado o fornecedor.
owner: wagner
last_run: "2026-10-13"
---

# Casos de Uso & Aceite — Devoluções de compra

> Thread `compras/playbook/08`. Derivados do `PurchaseReturnController` e da Blade `purchase_return/index`
> (RUNBOOK-purchase-return-index, `purchase-return-parity.md`) — não do `Index.tsx`.
> Teste: [`tests/Feature/Purchase/DevolucoesCompraContratoTest.php`](../../../../tests/Feature/Purchase/DevolucoesCompraContratoTest.php),
> lane `purchase-pest.yml` (MySQL). A Blade está travada em `DevolucoesCompraBaselineTest`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-DEVC-01 · Com a flag ligada, a tela abre em React
- **Persona:** o comprador que acompanha o que voltou para o fornecedor e quanto ainda é devido.
- **Aceite:** Dado `purchase.view` e a flag `useV2ComprasDevolucoes` ligada · Quando faço `GET /purchase-return` como o
  browser faz (`X-Inertia` **e** `X-Requested-With`) · Então renderiza **`PurchaseReturn/Index`**.
- **Status: 🧪**

## UC-DEVC-02 · Com a flag desligada, ou com `?legacy=1`, a Blade continua
- **Aceite:** Dado a flag desligada · Quando faço `GET /purchase-return` · Então a resposta é a Blade
  `purchase_return.index` · E com a flag ligada e `?legacy=1` também.
- **Status: 🧪**

## UC-DEVC-03 · Minhas devoluções, com o mesmo total e o mesmo devido da Blade · `[T0]` `[valor]`
- **Aceite:** Dado uma devolução minha de 1770,50 com pagamentos de 500,25 e 270,25, uma devolução minha de 640,00 sem
  pagamento, uma compra do meu negócio e uma devolução de outro negócio · Quando a lista carrega (prop deferida
  `devolucoes`) · Então a de 1770,50 vem com `devido` 1000,00 e a de 640,00 com `devido` 640,00 · E esses números são os
  mesmos `data-orig-value` das colunas `final_total`/`payment_due` da DataTable · E os textos são o `num_f` dos mesmos
  números · E os números das duas somam o mesmo que o rodapé da Blade soma (total 2410,50 e devido 1640,00), embora a
  tela não mostre soma · E não vejo a compra nem a devolução alheia.
- **Status: 🧪**

## UC-DEVC-04 · Sem acesso a todos os locais, só as devoluções dos meus locais · `[T0]`
- **Aceite:** Dado um usuário sem `access_all_locations`, com permissão só no local L · Quando a lista carrega · Então
  vejo a devolução de L · E não vejo a de outro local do mesmo negócio.
- **Status: 🧪**

## UC-DEVC-05 · A contagem por status de pagamento bate com a lista
- **Aceite:** Dado devoluções com pagamento `due` e `paid` · Quando a lista carrega · Então `por_pagamento` traz a
  quantidade de cada status igual à contagem das linhas · E o rótulo de `paid` é o "recebido" da Blade.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Total devolvido e total devido (rodapé/KPIs) — decisão [W], com dupla prova.
- [BACKLOG] Registrar pagamento, editar e excluir pela tela nova (PR próprio, regra mestre de valor).

## Trilha do tempo
- 2026-10-13 · [CL] criado com a F3 da thread `compras/playbook/08`.
