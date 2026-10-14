---
id: resources-js-pages-transaction-payment-edit-casos
casos: Editar pagamento · /payments/v2/{id}/edit
irmaos: Edit.charter.md (lei) · tests/Feature/TransactionPayment/TransactionPaymentEditContratoTest.php (defesa)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-10-14"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Financeiro · MySQL)"
---

# Casos de Uso & Aceite — Editar pagamento

> A tela **altera o valor de um pagamento já lançado** e, por tabela, a situação da venda ou da
> compra (paga, parcial, em aberto). Vive sob a **REGRA MESTRE valor/estoque** de
> [`memory/proibicoes.md`](../../../../memory/proibicoes.md): os casos de escrita provam o valor
> por dois caminhos (a linha do pagamento e o lado da transação), com a conta antes → depois à mão.
>
> **Status:** ✅ passa (com prova no manifesto G-7) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.
>
> **De onde os casos saem (ordem de fonte, [`memory/how-trabalhar.md`](../../../../memory/how-trabalhar.md)):**
> `Edit.charter.md` §Intent, §Diferenças vs Blade, §Props, §Tier 0, §Validação ·
> [`RUNBOOK-transaction-payment.md`](../../../../memory/requisitos/Financeiro/RUNBOOK-transaction-payment.md)
> F2/F4 e §Tier 0 · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) ·
> paridade com o modal legado (`TransactionPaymentController::edit` →
> `resources/views/transaction_payment/edit_payment_row.blade.php` → o mesmo `update`).
> Não há US de SPEC para `/payments/v2` (varrido em 2026-10-14) nem `ANTI-REGRESSAO` Delphi.
> O `TransactionPaymentController` foi lido só para **confirmar** — nenhum caso deriva dele
> (`memory/proibicoes.md` §5 2026-06-05).
>
> **Tenant:** fictício **98** ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) contra o 2º business semeado.
> `biz=4` (ROTA LIVRE) é proibido em teste, fixture ou exemplo.
>
> **Força do veredito:** a lane `PHP / Pest (Financeiro · MySQL)` está na lista de contexts required de
> [`governance/required-checks-baseline.json`](../../../../governance/required-checks-baseline.json)
> (lido em 2026-10-14) — a fonte do que bloqueia merge é aquele arquivo, não este.

---

## UC-TPEDT-01 · Pagamento de outra empresa não abre na edição `[T0]` `[must]`
- **Persona:** qualquer operador. O isolamento entre empresas não depende de ninguém digitar o id certo na URL.
- **Aceite:** Dado um pagamento de OUTRO business, e um usuário COM permissão de editar pagamento · Quando abro `/payments/v2/{id}/edit` · Então recebo 404 e nada do pagamento alheio viaja na resposta.
- **Âncora:** `Edit.charter.md` §Tier 0 ("editInertia já valida Transaction.business_id == session → 404 cross-tenant") + RUNBOOK F4 cenário 7 + ADR 0093.
- **Teste:** `tests/Feature/TransactionPayment/TransactionPaymentEditContratoTest.php` — pré-condições anti-vácuo: o pagamento alheio existe e o usuário tem `edit_sell_payment` (o 404 não pode ser o gate de permissão).
- **Regressão que defende:** um `findOrFail` sem o escopo da transação devolvendo a tela de pagamento de outra empresa.
- **Status: 🧪 sem veredito** — o arquivo nunca foi executado (Pest roda só no CI/CT 100, ADR 0062).

---

## UC-TPEDT-02 · Pagamento próprio abre a tela com o que o charter promete `[must]`
- **Persona:** Larissa corrigindo um pagamento lançado errado.
- **Aceite:** Dado um pagamento de 100,00 da minha empresa · Quando abro `/payments/v2/{id}/edit` · Então recebo a tela `TransactionPayment/Edit` com o pagamento (mesmo id, valor 100,00), a transação dele (da minha empresa), as formas de pagamento habilitadas no local da venda (exatamente as habilitadas — com `cash` e `card` ligadas, vêm só essas duas) e a chave `accounts`.
- **Âncora:** `Edit.charter.md` §Props (`payment_line` · `transaction` · `payment_types` · `accounts`) + RUNBOOK F2 (`editInertia` "retorna payment_line + transaction + payment_types + accounts").
- **Teste:** `tests/Feature/TransactionPayment/TransactionPaymentEditContratoTest.php`. O smoke em quarentena (`TransactionPaymentInertiaSmokeTest`, cenário 6) cobre só o status; este caso cobre o conteúdo.
- **Regressão que defende:** a tela abrir sem o pagamento ou com a transação errada.
- **Status: 🧪 sem veredito** — mesma condição do UC-TPEDT-01.

---

## UC-TPEDT-03 · Sem permissão de editar pagamento, nem a tela nem o salvar passam `[must]`
- **Persona:** Larissa configurando perfis — quem só vende não altera o valor de um pagamento já recebido.
- **Aceite:** Dado um usuário da MINHA empresa sem `edit_sell_payment`, `edit_purchase_payment`, `all_expense.access` e `view_own_expense` · Quando abro `/payments/v2/{id}/edit` e quando envio `PUT /payments/{id}` · Então recebo 403 nos dois, e o pagamento segue com o valor, a data e a situação da venda de antes.
- **Âncora:** `Edit.charter.md` §Tier 0 ("RBAC: `edit_purchase_payment` OR `edit_sell_payment`") + paridade do modal legado (o `edit()` e o `update()` respondem 403 sem permissão).
- **Teste:** `tests/Feature/TransactionPayment/TransactionPaymentEditContratoTest.php` — usuário novo, sem papel; o banco é conferido depois do PUT (o status sozinho não prova que nada foi gravado).
- **Regressão que defende:** salvar por baixo da tela, com o PUT direto, sem a permissão que a tela exige.
- **Status: 🧪 sem veredito** — mesma condição do UC-TPEDT-01.

---

## UC-TPEDT-04 · Alterar o valor grava o novo valor e recalcula a situação da venda `[V0]` `[must]`
- **Persona:** Larissa — a cliente pagou 60,00, não 100,00, e a venda tem de voltar a mostrar saldo em aberto.
- **Aceite:** Dado uma venda de 100,00 paga com um pagamento de 100,00 · Quando envio, no formato do modal legado, valor `60,00` e data `10/10/2026 09:15` · Então:

  | o quê | antes | depois (conta à mão) |
  |---|---|---|
  | valor do pagamento | 100,00 | **60,00** |
  | data do pagamento | 2026-10-01 08:00 | **2026-10-10 09:15** |
  | soma paga da venda | 100,00 | **60,00** |
  | situação da venda (`payment_status`) | `paid` | **`partial`** (60 < 100 e > 0) |

- **Âncora:** `Edit.charter.md` §Diferenças ("mesmo endpoint `PUT /payments/{id}` reusado") + paridade do modal legado (`edit_payment_row` manda `paid_on` no formato da empresa e `amount` em pt-BR) + REGRA MESTRE (dupla prova).
- **Teste:** `tests/Feature/TransactionPayment/TransactionPaymentEditContratoTest.php` — caminho 1: a linha do pagamento; caminho 2: soma dos pagamentos da venda e `payment_status` da transação.
- **Regressão que defende:** valor gravado diferente do enviado (o incidente `num_uf` de 2026-06-05) ou a venda continuar "paga" depois de o pagamento cair.
- **Status: 🧪 sem veredito** — mesma condição do UC-TPEDT-01.

---

## UC-TPEDT-05 · Salvar pela tela React grava o valor digitado `[V0]` `[must]`
- **Persona:** Larissa usando a tela nova em vez do modal.
- **Aceite:** Dado uma venda de 100,00 com um pagamento de 100,00 · Quando abro a tela, troco só o valor para `60.5` e salvo (o payload é o que `Edit.tsx` manda: `paid_on` no formato `AAAA-MM-DD HH:MM` vindo do próprio pagamento, valor com ponto decimal do campo numérico) · Então o pagamento passa a 60,50 e a venda passa a `partial`.
- **Âncora:** `Edit.charter.md` §Intent ("editar um pagamento existente em página full ao invés de modal") + §Diferenças (submit reusa `PUT /payments/{id}`) + RUNBOOK F2 ("POST update reusa update() existente").
- **Teste:** `tests/Feature/TransactionPayment/TransactionPaymentEditDefeitosTest.php` (fora da lane `PHP / Pest (Financeiro · MySQL)` por decisão [W] 2026-10-14, até o conserto do `update()` — o PR do conserto liga o arquivo na lane) — o caso confere primeiro o desfecho que o `update()` grava no flash `status`, depois o banco pelos dois caminhos.
- **Regressão que defende:** a tela nova não conseguir salvar o que ela mesma mostra.
- **Status: ❌ quebrou** — medido no [run 38564874922](https://github.com/wagnerra23/oimpresso.com/actions/runs/38564874922) (2026-10-14): o `update()` devolveu `success=false` para o payload que o `Edit.tsx` manda (`paid_on` `AAAA-MM-DD HH:MM`, valor `60.5`); o pagamento não muda. A tela React não salva. Correção é decisão [W] (regra mestre de valor) — nenhum código foi tocado.

---

## UC-TPEDT-06 · Salvar em cima de pagamento de outra empresa não muda nada dela `[T0]` `[V0]` `[must]`
- **Persona:** qualquer operador — o PUT pode ser montado à mão, fora da tela.
- **Aceite:** Dado um pagamento de 100,00 de OUTRO business · Quando envio `PUT /payments/{id}` com valor `10,00` · Então o pagamento alheio segue com 100,00, a mesma data e a mesma observação, e a venda alheia segue `paid`. **Controle:** o mesmo usuário com o mesmo payload altera o pagamento da própria empresa para 10,00.
- **Âncora:** `Edit.charter.md` §Tier 0 (multi-tenant) + RUNBOOK §Tier 0 ("`Transaction::where('business_id', $businessId)` em todo query") + ADR 0093.
- **Teste:** `tests/Feature/TransactionPayment/TransactionPaymentEditContratoTest.php` — assere o banco, não o status (sucesso e recusa podem sair com o mesmo código).
- **Regressão que defende:** alterar valor de pagamento de outra empresa pelo endpoint compartilhado.
- **Status: 🧪 sem veredito** — mesma condição do UC-TPEDT-01.

---

## UC-TPEDT-07 · Salvar em cima de pagamento alheio não mexe no adiantamento alheio `[T0]` `[V0]` `[must]`
- **Persona:** qualquer operador — pagamento que consome um adiantamento do cliente.
- **Aceite:** Dado um adiantamento de 500,00 de OUTRO business e um pagamento alheio de 100,00 que nasce dele · Quando envio `PUT /payments/{id}` do pagamento alheio com valor `10,00` · Então o adiantamento alheio segue com 500,00 e o pagamento alheio segue com 100,00. O `PUT` é recusado com **404** antes de qualquer escrita. **Controle (mesmo cálculo, empresa própria):** adiantamento 500,00 com filho 100,00 → `PUT` 10,00 → pai 410,00 = 500,00 − (100,00 − 10,00), filho 10,00.
- **Âncora:** as mesmas do UC-TPEDT-06 — o adiantamento também é dado de outra empresa.
- **Teste:** `tests/Feature/TransactionPayment/TransactionPaymentEditContratoTest.php` (dois `it()`: o cross-tenant e o controle do adiantamento próprio).
- **Regressão que defende:** escrita em outra empresa feita antes de o endpoint descobrir que a transação não é da empresa logada.
- **Status: 🧪 sem veredito** — o vermelho foi medido no [run 38569047207](https://github.com/wagnerra23/oimpresso.com/actions/runs/38569047207) (adiantamento alheio 500,00 → 410,00). O `update()` passou a conferir a empresa da venda antes de qualquer escrita (PR do conserto); o veredito novo vem da lane `PHP / Pest (Financeiro · MySQL)`.

---

## Backlog — achados sem teste ainda (prosa honesta, sem id)

- **[BACKLOG]** Depois de salvar, a tela mostra "Pagamento atualizado." mesmo quando o `update()` não gravou: ele responde `redirect()->back()` com `status.success = false`, e o `onSuccess` do `form.put` dispara em qualquer redirect sem erro de validação. Fonte única (leitura do `.tsx`); vira UC quando houver contrato de feedback.
- **[BACKLOG]** O GET da tela aceita 2 permissões (`edit_sell_payment`, `edit_purchase_payment`); o PUT aceita 4 (mais `all_expense.access` e `view_own_expense`). Quem tem só as de despesa salva por PUT sem conseguir abrir a tela. Decisão de produto: alinhar ou não.
- **[BACKLOG]** Pagamento com método `advance` não abre na edição (o `editInertia` e o `edit()` legado filtram `method != 'advance'`). O charter não fala disso.
- **[BACKLOG]** O charter cita `custom_pay_1/2/3`; o `update()` só lê `transaction_no_1..3`, e as formas de pagamento podem ir além do 3.
- **[BACKLOG]** O modal legado tem anexo (`document`) e contagem de cédulas (`denominations`); a tela React não tem nenhum dos dois. Lacuna de paridade para o cutover, sem decisão registrada.
