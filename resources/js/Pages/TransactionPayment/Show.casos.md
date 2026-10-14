---
casos: Detalhe do pagamento · /payments/v2/{id}
irmaos: Show.charter.md (lei) · Show.tsx · tests/Feature/TransactionPayment/TransactionPaymentShowContratoTest.php (defesa)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o detalhe do pagamento mostra valor e dados do cliente — de qual empresa é, quem pode ver e se a tela muda alguma coisa não pode depender de um refactor visual.
owner: wagner
last_run: "2026-10-14"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Financeiro · MySQL)"
---

# Casos de Uso & Aceite — Detalhe do pagamento

> **Fonte (ordem fixa, how-trabalhar):** `Show.charter.md` (Props · UX Cards 1-3 · Tier 0) +
> `memory/requisitos/Financeiro/RUNBOOK-transaction-payment.md` (F2 `showInertia` = "equivalente a
> `viewPayment()`"; F4 cenários 4 e 5). **Paridade:** o modal legado que esta tela substitui —
> `TransactionPaymentController@viewPayment` + `resources/views/transaction_payment/single_payment_view.blade.php`.
> O `TransactionPaymentController@showInertia` foi lido só para confirmar. Nada derivado do `.tsx`.
>
> **Sem US no SPEC:** varrido `memory/requisitos/*/SPEC.md` — nenhuma US ancora `/payments/v2` nem a
> `TransactionPayment/Show`. Ver Perguntas abertas.
>
> **Teste:** `tests/Feature/TransactionPayment/TransactionPaymentShowContratoTest.php` — tenant 98 ×
> adversário 99 (ADR 0358), `DatabaseTransactions`, permissões concedidas DIRETO ao usuário (no banco do
> CI não há roles), headers do browser (`X-Inertia` + `X-Requested-With`). Só lê: zero escrita fora da
> fixture.
>
> ⚖️ **Lane:** `PHP / Pest (Financeiro · MySQL)` — `.github/workflows/financeiro-pest.yml` (extras nomeados do run-set). Se ela
> bloqueia merge, quem diz é `governance/required-checks-baseline.json`, não este arquivo.
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.

## UC-TPSHW-01 · Pagamento de outra empresa não abre e não mostra nada `[T0]` `[must]`
- **Persona:** qualquer operador — o isolamento não pode depender de ninguém digitar o id errado.
- **Aceite:** Dado um pagamento que pertence a OUTRO business · Quando abro `/payments/v2/{id}` · Então recebo 404 e nem a referência, nem o nome do cliente, nem a observação desse pagamento aparecem no corpo da resposta.
- **Fontes:** charter §Tier 0 ("showInertia valida Transaction.business_id → 404 cross-tenant") · RUNBOOK F4 cenário 5 · ADR 0093.
- **Teste:** `TransactionPaymentShowContratoTest` — `UC-TPSHW-01 [T0] · pagamento de outro business devolve 404…` (com controle positivo no próprio business).
- **Regressão que defende:** dado de cliente de outra empresa na tela.
- **Status: 🧪**

## UC-TPSHW-02 · O pagamento abre com o valor e as referências gravadas `[V0]` `[must]`
- **Persona:** Larissa conferindo um recebimento.
- **Aceite:** Dado um pagamento em dinheiro com valor de fixture, com referência e observação · Quando abro o detalhe · Então a tela recebe esse valor (sem transformação), a referência, a observação, o rótulo do método e a transação a que ele pertence.
- **Fontes:** charter §Props + §UX Card 2 (valor, método com label, referência, observação) · Blade `single_payment_view` (valor, método por `payment_types`, ref, nota) · RUNBOOK F4 cenário 4.
- **Teste:** `TransactionPaymentShowContratoTest` — `UC-TPSHW-02 [V0] · pagamento próprio abre a tela Show…`.
- **Regressão que defende:** valor exibido diferente do gravado; método mostrado como chave crua.
- **Status: 🧪**

## UC-TPSHW-03 · Quem não tem permissão de pagamento não vê o pagamento `[must]`
- **Persona:** usuário de estoque/produção do mesmo business.
- **Aceite:** Dado um usuário sem nenhuma das 6 permissões (`sell.payments`, `purchase.payments`, `edit_sell_payment`, `delete_sell_payment`, `edit_purchase_payment`, `delete_purchase_payment`) · Quando abro um pagamento do meu business · Então recebo 403 e nada do pagamento aparece no corpo.
- **Fontes:** charter §Tier 0 ("RBAC: mesmo set que viewPayment") · gate de `viewPayment` (legado).
- **Teste:** `TransactionPaymentShowContratoTest` — `UC-TPSHW-03 · usuário do mesmo business sem nenhuma permissão…`.
- **Regressão que defende:** tela de pagamento aberta para qualquer logado.
- **Status: 🧪**

## UC-TPSHW-04 · Qualquer permissão do conjunto basta, como no modal antigo `[should]`
- **Persona:** operador de compras (`purchase.payments`) ou caixa (`sell.payments`).
- **Aceite:** Dado um usuário com só UMA das 6 permissões · Quando abro um pagamento do meu business · Então ele abre e mostra a referência do pagamento — para cada uma das 6.
- **Fontes:** charter §Tier 0 · gate de `viewPayment` (legado) — paridade.
- **Teste:** `TransactionPaymentShowContratoTest` — `UC-TPSHW-04 · cada uma das 6 permissões do gate…` (dataset com as 6).
- **Regressão que defende:** quem via o pagamento no modal legado perder acesso na tela nova.
- **Status: 🧪**

## UC-TPSHW-05 · O cliente do pagamento aparece `[should]`
- **Persona:** Larissa identificando de quem foi o recebimento.
- **Aceite:** Dado um pagamento de uma venda de um cliente · Quando abro o detalhe · Então o nome do cliente da transação chega na tela.
- **Fontes:** charter §UX Card 1 (Contato) · Blade `single_payment_view` (bloco `address` do contato).
- **Teste:** `TransactionPaymentShowContratoTest` — `UC-TPSHW-05 · o cliente da transação do pagamento chega na tela`.
- **Status: 🧪**

## UC-TPSHW-06 · Pagamento agrupado (pai) abre pela transação do filho do mesmo business `[T0]` `[should]`
- **Persona:** operador abrindo um pagamento de "pagar dívida do contato", que fica sem `transaction_id` e se divide em filhos.
- **Aceite:** Dado um pagamento-pai sem transação, com um filho do meu business · Quando abro o pai · Então a tela mostra a transação do filho. Dado um pagamento-pai cujo filho é de OUTRO business · Quando abro · Então recebo 404 e o cliente do outro business não aparece.
- **Fontes:** `viewPayment` legado (ramo `parent_id` → transação do filho) · RUNBOOK F2 ("equivalente a viewPayment()") · charter §Tier 0 (404 cross-tenant).
- **Teste:** `TransactionPaymentShowContratoTest` — dois casos `UC-TPSHW-06`.
- **Status: 🧪**

## UC-TPSHW-07 · Abrir o detalhe não muda o pagamento `[V0]` `[must]`
- **Persona:** Wagner auditando — consultar não altera valor nem status.
- **Aceite:** Dado um pagamento e sua venda · Quando abro o detalhe · Então valor, método, data, referência e `updated_at` do pagamento, e `final_total`, `payment_status` e `updated_at` da venda continuam iguais, e nenhuma linha de pagamento é criada.
- **Fontes:** charter §Intent ("Mostrar detail") · Blade `single_payment_view` (modal só de leitura) · REGRA MESTRE valor/estoque.
- **Teste:** `TransactionPaymentShowContratoTest` — `UC-TPSHW-07 [V0] · abrir o detalhe do pagamento não altera…`.
- **Status: 🧪**

## Backlog (sem id — contrato em 1 fonte só, ou não testável por Pest)

- `[BACKLOG]` **Bloco da empresa (nome, local, CNPJ, telefone):** o modal legado mostra; o charter não lista, e a tela nova não renderiza (`transaction.business` nem é carregado no `showInertia`). Só 1 fonte — decisão [W] se é perda de paridade ou corte.
- `[BACKLOG]` **"Folha de pagamento de" (payroll):** charter Card 1 e Blade mostram `transaction_for` quando a transação é payroll; o servidor envia `transaction_for`, mas o `.tsx` não o renderiza (só Cliente/Fornecedor). Divergência charter×tela, não exercitável por Pest (é render) — pede teste de componente ou E2E.
- `[BACKLOG]` **Botão "Editar" sem gate:** o `.tsx` mostra "Editar" para qualquer usuário que abre a tela, inclusive quem só tem `sell.payments`; o modal legado não tinha Editar. Sem contrato que diga se deve esconder.
- `[BACKLOG]` **Trilha de auditoria (`LogsActivity`):** charter marca como futuro, não bloqueante.
- `[BACKLOG]` **Endereço do contato como texto:** o Blade renderiza HTML cru (`{!! !!}`); a tela nova renderiza texto (anti-XSS). Divergência intencional pelo comentário do `.tsx`; registrar se vira contrato.

## Perguntas abertas ([W])

1. Qual US cobre `/payments/v2`? Nenhum SPEC ancora a tela; os UCs acima saem do charter + paridade com o modal.
2. O bloco da empresa do modal legado (nome/CNPJ/local) deve existir na tela nova?
