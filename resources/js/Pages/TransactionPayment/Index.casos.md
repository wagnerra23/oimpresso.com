---
casos: TransactionPayment/Index · /payments/v2
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-10-14"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Financeiro · MySQL)"
---

# Casos de Uso & Aceite — TransactionPayment/Index

> Derivados do `Index.charter.md` (Intent · Props · UX · Validações Tier 0), não do `.tsx`.
> `TransactionPaymentController::indexInertia` foi lido só para confirmar (2026-10-14).
> Teste: `tests/Feature/TransactionPayment/TransactionPaymentIndexContratoTest.php`, tenant 98 × business 2.
> Lane: `PHP / Pest (Financeiro · MySQL)` (`.github/workflows/financeiro-pest.yml`, extra nomeado fora da árvore do módulo). Se ela bloqueia merge é do [`required-checks-baseline.json`](../../../../governance/required-checks-baseline.json), não deste arquivo.
> **Status:** ✅ passa · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.
>
> O smoke antigo `Modules/Financeiro/Tests/Feature/TransactionPaymentInertiaSmokeTest.php` cobre index/show/edit,
> mas está na `.github/financeiro-pest-quarantine.list` — não roda. Estes casos têm teste próprio que roda.

---

## UC-TPIDX-01 `[T0]` `[must]` · A lista mostra só pagamentos do meu negócio
- **Persona:** quem confere recebimentos e pagamentos no financeiro.
- **Aceite:** Dado um pagamento do meu negócio e um de outro negócio no mesmo período · Quando abro a lista · Então vejo o meu e não o outro.
- **Fonte:** charter §Validações Tier 0 ("query scopa `business_id`") + ADR 0093.
- **Regressão que defende:** a lista é cross-transaction; sem o filtro por negócio ela mostra o caixa de outra empresa.
- **Status: 🧪 sem veredito**

## UC-TPIDX-02 `[T0]` `[must]` · Só entra quem tem `sell.payments` ou `purchase.payments`
- **Aceite:** Dado um usuário só com `sell.payments`, ou só com `purchase.payments` · Quando abro `/payments/v2` · Então a tela abre. Dado um usuário só com `edit_sell_payment`, ou sem permissão · Então recebe 403.
- **Fonte:** charter §Validações Tier 0 ("requer `sell.payments` OR `purchase.payments`").
- **Regressão que defende:** permissão de editar pagamento não é permissão de ver a lista de todos os pagamentos.
- **Status: 🧪 sem veredito**

## UC-TPIDX-03 `[must]` · Filtro tipo separa recebido de pago
- **Aceite:** Dado um pagamento de venda e um de compra no mesmo período · Quando escolho Recebidos · Então vejo só o da venda; Pagos, só o da compra; Todos, os dois.
- **Fonte:** charter §Props (`filtros.tipo: recebido|pago|null`) + §UX (chips Todos/Recebidos/Pagos).
- **Status: 🧪 sem veredito**

## UC-TPIDX-04 `[should]` · Filtro status mostra só a transação naquele status
- **Aceite:** Dado pagamentos de transações paga, parcial e em aberto · Quando escolho um status · Então vejo só o pagamento daquele status, e a tela volta com o chip marcado (`filtros.status`).
- **Fonte:** charter §Props (`filtros.status: paid|partial|due|null`) + §UX (chips Todos/Pago/Parcial/Em aberto).
- **Status: 🧪 sem veredito**

## UC-TPIDX-05 `[V0]` `[T0]` `[must]` · KPIs carregam depois e somam só o meu negócio em 30 dias
- **Aceite:** Dado que abro a tela · Então a lista vem sem os KPIs (carregam depois). Dado um recebimento de 123,45 e um pagamento de 67,89 de ontem, um recebimento de 500 de 40 dias atrás, um de 1000 de outro negócio, e duas transações minhas pendentes (uma em aberto, uma parcial) · Quando os KPIs carregam · Então Recebido 30d sobe 123,45, Pago 30d sobe 67,89 e Pendentes sobe 2.
- **Dupla prova (regra mestre de valor):** (a) delta contra a conta à mão; (b) o mesmo delta somado da própria lista da tela (pagamentos da tag nos últimos 30 dias).
- **Fonte:** charter §Props (`kpis: Deferred<{recebido_30d, pago_30d, pendentes_count}>`) + §UX (KpiGrid 3 cards) + ADR 0093.
- **Status: 🧪 sem veredito** — só leitura; nada aqui grava valor.

## UC-TPIDX-06 `[should]` · 50 por página, mais recente primeiro, com o filtro na página 2
- **Aceite:** Dado 51 pagamentos num período · Quando abro a lista filtrada por esse período · Então a página 1 tem 50, do mais recente para o mais antigo, e o link da página 2 leva o mesmo período e mostra o mais antigo.
- **Fonte:** charter §Props (`paginate(50)`) + §UX ("Paginação: native Laravel paginate").
- **Status: 🧪 sem veredito**

---

## Perguntas abertas ([W])

- **Sem US.** Nenhum `SPEC.md` tem US para `/payments/v2` (varrido 2026-10-14: `TransactionPayment` só aparece em US-AUDIT-002, trilha de auditoria, e em notas do Financeiro sobre o observer). A tela nasceu numa wave de migração Blade (2026-05-17) sem US. Qual módulo é dono dela, e ela ganha US?
- **Blade de referência:** não há lista global legada — `TransactionPaymentController@index` é vazio e os pagamentos da Blade vivem dentro de cada venda/compra. O charter já diz isso ("UX nova"). Sem `ANTI-REGRESSAO-*` do Office Comercial para esta tela.
- **`[V0]` O que conta como "recebido" e "pago" nos KPIs?** O código soma em Recebido 30d os pagamentos de `sell`, `sell_return` e `opening_balance`, e em Pago 30d os de `purchase`, `purchase_return`, `expense` e `expense_refund`, todos com sinal positivo. Pagamento de devolução de venda é dinheiro que sai, e entra como recebido. O charter não define. Hipótese, não medida — o teste usa só `sell`/`purchase` para não fixar comportamento sem contrato.
- **Janela de 30 dias inclui data futura:** o filtro é `paid_on >= hoje − 30d`, sem teto. O charter não define.
- **Pendentes:** conta transações `due`/`partial` de todos os tipos e status (inclui rascunho?). O charter diz só "Pendentes (count)".
- **Como se chega na tela:** nenhum item de menu aponta para `/payments/v2` (varrido 2026-10-14 em `resources/js`, `app`, `Modules`); só a própria tela e Show/Edit citam a rota.

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Filtros tipo/status persistem em localStorage (`oimpresso.transaction_payment.index.tipo|status`) — comportamento de front, contrato só no charter; precisa de teste de browser.
- **[BACKLOG]** Ações Ver/Editar por linha levam a `/payments/v2/{id}` e `/payments/v2/{id}/edit` — cobertas pelos casos de Show/Edit.
- **[BACKLOG]** Filtro de período `from`/`to` na UI — o servidor aceita (usado nos testes acima), o charter lista em Props mas a UX não descreve controle de data.

## Trilha do tempo
- 2026-10-14 · [CL] casos derivados do charter (agente `sdd-from-source`, etapa E1). Teste novo independente do smoke em quarentena.
