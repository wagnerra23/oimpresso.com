---
sessao: "07"
titulo: "Caixa (turno): movimentos + conferência física — saída da thread"
autor: "[CL]"
criado: 2026-10-05
base: 1685efb6f5
thread: 01-telas-legadas.md §07
veredito: "PR 1 (leitura) entregue: a seção Movimentos do caixa de Sells/Caixa/Index mostra o turno aberto com totais por forma, vindos do mesmo CashRegisterUtil::getRegisterDetails que alimenta o Blade. PR 2 (conferência física) empilhado: esperado em dinheiro pela fórmula que o modal de fechamento já usa, contado e diferença na tela, gravação pelo POST /cash-register/close-register existente. Veredito de teste vem da lane PHP / Pest (Sells · MySQL), não roda local."
---

# _saída 07 · Caixa (turno)

## O pendente "Onda 6+1" — antes e depois

Antes (`Sells/Caixa/Index.tsx` em `1685efb6f5`):

- comentário: esperado/conferido/diferença "ficam pra Onda 6+1";
- seção **Movimentos do caixa**: placeholder "read-only · Onda 6+1 wire-up", sangria/suprimento pelo `/cash-register`;
- seção **Conferência física**: placeholder "read-only · Onda 6+1 wire-up", denominações no modal legado.

Depois do PR 1:

- **Movimentos do caixa** mostra o turno aberto do usuário: número, hora de abertura, local, troco inicial, tabela por forma (vendas · despesas · devoluções) com total do turno, e a lista dos movimentos (mais recente primeiro, até 200), com link para a venda.
- **Conferência física** segue placeholder. Fecha no PR 2.

## O que saiu (PR 1)

| arquivo | o que é |
|---|---|
| `app/Http/Controllers/SellController.php` | prop deferida `turno` em `inertiaCaixa` + `buildCaixaTurnoPayload()`. Nenhuma conta nova: os totais são os de `CashRegisterUtil::getRegisterDetails($id)`; a lista é `cash_register_transactions` do registro, com `cr.business_id` e `t.business_id` no join |
| `resources/js/Pages/Sells/Caixa/Index.tsx` | `<Deferred data="turno">` + `TurnoMovimentos` (só formata; classes `vc-pay-table` já existentes) |
| `Index.casos.md` | `UC-SCAIXA-10..12` + 2 itens de backlog |
| `Index.charter.md` | card 3 e Non-Goal de sangria reconciliados |
| `tests/Feature/Sells/SellsCaixaContratoTest.php` | 3 casos novos, tenant 98 × 99 |
| `.github/workflows/sells-pest.yml` | `CashRegisterUtil.php` e `CashRegisterController.php` nos gatilhos (o teste compara com eles); o arquivo de teste já estava na lista |

## Antes → depois de um caso (tenant fictício 98)

Turno aberto com: abertura dinheiro 100,00 · venda dinheiro 60,00 · troco −5,00 (gravado como crédito negativo, como o `addSellPayments` faz) · venda cartão 40,00 · venda dinheiro 50,00 · despesa dinheiro 20,00 · devolução dinheiro 10,00.

| campo | Blade (`getRegisterDetails`) | tela antes | tela depois | conta à mão |
|---|---|---|---|---|
| troco inicial | 100,00 | — (placeholder) | 100,00 | 100 |
| dinheiro · vendas | 105,00 | — | 105,00 | 60 − 5 + 50 = 105 |
| dinheiro · despesas | 20,00 | — | 20,00 | 20 |
| dinheiro · devoluções | 10,00 | — | 10,00 | 10 |
| cartão · vendas | 40,00 | — | 40,00 | 40 |
| total vendas | 135,00 | — | 135,00 | 60 − 5 + 40 + 50 − 10 = 135 |
| movimentos | 7 | — | 7 | 7 |

Dupla prova no `UC-SCAIXA-10`: cada número é comparado com o retorno de `getRegisterDetails` **e** com o literal da conta à mão.

## Decisões tomadas aqui

1. **Totais pelo Util do Blade, não por SQL novo.** `getRegisterDetails($id)` já escopa `cash_registers.business_id` (correção Tier 0 anterior) e é o que alimenta `payment_details`. A tela não soma nada.
2. **Prop deferida.** O turno é por usuário, não por data: fica fora do `only:` do seletor de data e não atrasa o primeiro render.
3. **Sangria e suprimento não entram.** O caixa do UltimatePOS não grava esses tipos (`transaction_type` só tem `initial`, `sell`, `expense`, `refund`). Criá-los seria regra e endpoint novos: backlog para [W].
4. **Rótulos.** Formas conhecidas usam o mesmo rótulo PT da tabela "Por forma de pagamento"; `custom_pay_N` usa o rótulo do negócio (`payment_types`).

## Para o PR 2 (conferência física) — sem regra de cálculo nova

- **Esperado em dinheiro** = `cash_in_hand + total_cash − total_cash_refund − total_cash_expense`, a mesma expressão com que `close_register_modal.blade.php` preenche o campo "Total em dinheiro". No caso acima: 100 + 105 − 10 − 20 = **175,00**.
- **Atenção registrada:** o Blade tem **duas** fórmulas de "dinheiro": `payment_details.blade.php:225` mostra `cash_in_hand + total_cash − total_cash_refund` (sem a despesa = 195,00 no caso), e o modal de fechamento usa a com despesa (175,00). O PR 2 usa a do **fechamento**, porque é a que vai para o campo gravado. Não se escolheu fórmula nova.
- **Diferença** = contado − esperado, só exibida (não é gravada). Calculada em centavos inteiros no front.
- **Gravação** pelo `POST /cash-register/close-register` que já existe, com `closing_amount` enviado como `175,00` (vírgula decimal), forma que o `num_uf` lê sem ambiguidade.

## Depois do PR 2 (conferência física)

- **Conferência física** deixa de ser placeholder: esperado em dinheiro, campo "contado", diferença ("bateu certinho" · "sobra R$ x" · "falta R$ x"), comprovantes de cartão e cheques pré-preenchidos como no modal, observação obrigatória quando há diferença, e "Fechar caixa com esta contagem" (só com `close_cash_register`) pelo `POST /cash-register/close-register` existente.
- Antes → depois no caso acima, contado 170,00:

| campo | Blade (modal de fechamento) | tela antes | tela depois | conta à mão |
|---|---|---|---|---|
| esperado em dinheiro | 175,00 (pré-preenche "Total em dinheiro") | placeholder | 175,00 | 100 + 105 − 10 − 20 = 175 |
| diferença (contado 170,00) | não mostra | placeholder | falta R$ 5,00 | 17000 − 17500 = −500 centavos |
| `closing_amount` gravado | 170,00 → 170.0000 | — | 170,00 → 170.0000 (mesmo POST) | 170 |
| turno do mesmo usuário no 99 | intocado | — | intocado | — |

Dupla prova: `UC-SCAIXA-13` compara o esperado com a expressão do modal sobre `getRegisterDetails` e com 175,00; `UC-SCAIXA-14` posta o payload que a tela envia e lê o registro gravado.

## Provas locais (antes do CI)

- `php -l` no controller e no teste: sem erro.
- `memory-schemas/validate.mjs` no charter: conforme.
- `casos-coverage-guard.mjs`: sem violações novas deste PR.

## O que ficou fora, e por quê

- **Typecheck e Pest locais não rodaram** (worktree sem `node_modules`; Pest só no CI/CT 100).
- **Denominações (contagem por cédula)** ficam fora do PR 2: o modal legado continua disponível para isso e a soma por cédula seria mais uma conta no front.
- **Cutover F5** (tirar `/cash-register`) é humano.

## PARAR SE

Nenhum disparou no PR 1: nenhuma rota nova, nenhuma regra de cálculo nova, e a tela que toca valor tem teste provando o mesmo total pelo caminho do Blade.
