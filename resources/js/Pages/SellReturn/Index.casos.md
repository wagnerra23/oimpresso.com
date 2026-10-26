---
id: resources-js-pages-sellreturn-index-casos
casos: Devolução de venda · lista · /sell-return
irmaos: Index.charter.md (lei) · Index.tsx
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a lista garante QUAIS devoluções a pessoa vê e QUANTO elas somam — isso não muda num refactor visual.
owner: wagner
last_run: "2026-10-26"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Sells · MySQL)"
---

<!-- REVALIDAÇÃO 2026-10-26 (G-6: o .tsx mudou depois do last_run de 01/10).
     Commits no Index.tsx depois de 01/10, medidos com `git log --since=2026-10-01 -- SellReturn/Index.tsx`:
     #8487 (02/10) é o próprio nascimento da tela, com estes casos; #8634 (05/10) só trocou o
     "Editar" de <a> para <Link> (visita Inertia ao SellReturn/Add), o que a regra L4 do charter já
     descreve. A seção nova desta edição (UC-SRET-VT-*, vale-troca, US-VEST-021) é do servidor e não
     toca o .tsx. Nenhum UC muda de status. -->

# Casos de Uso & Aceite — Devolução de venda · lista

> **Fonte:** o contrato do DataTable legado (`SellReturnController@index`, ramo `ajax()`) +
> o domínio em `memory/requisitos/Sells/CASOS-USO-DEVOLUCAO.md` + o charter revisado em
> `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/Devolucao.charter.md`.
> Os 4 UC daquela pasta (`UC-DEV-01..04`) são do **registro** da devolução e ficam no backlog
> abaixo até o PR 2 (`SellReturn/Add`).
>
> **Teste:** `tests/Feature/Sells/SellReturnIndexContratoTest.php` — tenant 98 × adversário 99,
> `DatabaseTransactions`, valores esperados derivados à mão da fixture e escritos no cabeçalho.
> **Lane:** `PHP / Pest (Sells · MySQL)` — `.github/workflows/sells-pest.yml`.
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.

## UC-SRIDX-01 · A visita Inertia recebe a tela React `[must]`
- **Persona:** Larissa — abre Devoluções pelo menu de Vendas.
- **Aceite:** Dado uma visita Inertia (`X-Inertia` **e** `X-Requested-With`) · Quando pede `/sell-return` · Então recebe o componente `SellReturn/Index`, com a lista e os números carregados em seguida (props deferidas).
- **Teste:** `SellReturnIndexContratoTest` — `UC-SRIDX-01 a visita Inertia recebe a página React, não o JSON do DataTable`.
- **Regressão que defende:** o ramo `ajax()` engolir a visita Inertia e devolver JSON no lugar da página.
- **Status: 🧪**

## UC-SRIDX-02 · Outro business não entra `[T0]` `[must]`
- **Persona:** Larissa — a lista dela nunca mostra devolução de outra empresa.
- **Aceite:** Dado uma devolução de 70000.00 do business 99 no mesmo mês · Quando o business 98 abre a lista · Então ela não aparece e os números do topo ficam iguais aos do 98 sozinho.
- **Teste:** `SellReturnIndexContratoTest` — `UC-SRIDX-02 [T0] devolução de outro business não entra na lista nem nos números`.
- **Regressão que defende:** consulta perdendo o `where business_id` (ADR 0093).
- **Status: 🧪**

## UC-SRIDX-03 · Sem permissão, sem lista `[must]`
- **Persona:** usuário sem `access_sell_return` nem `access_own_sell_return`.
- **Aceite:** Dado esse usuário · Quando abre `/sell-return` · Então recebe 403.
- **Teste:** `SellReturnIndexContratoTest` — `UC-SRIDX-03 sem permissão de devolução a tela devolve 403`.
- **Regressão que defende:** o ramo novo pular a checagem de permissão.
- **Status: 🧪**

## UC-SRIDX-04 · Só as próprias `[must]`
- **Persona:** vendedor com `access_own_sell_return`.
- **Aceite:** Dado devoluções criadas por ele e por outro usuário · Quando abre a lista · Então vê só as que criou, e os números do topo contam só essas.
- **Teste:** `SellReturnIndexContratoTest` — `UC-SRIDX-04 quem só tem access_own_sell_return vê apenas as devoluções que criou`.
- **Regressão que defende:** a tela React mostrar mais do que o DataTable legado mostrava.
- **Status: 🧪**

## UC-SRIDX-05 · Números do topo são leitura `[V0]` `[must]`
- **Persona:** Larissa — quer saber quanto voltou no mês e o que ainda falta pagar ao cliente.
- **Aceite:** Dado devoluções finais em meses diferentes e um rascunho · Quando abre a lista · Então "Com saldo a pagar" = devoluções com `payment_status` diferente de `paid`, "Devoluções no mês" = quantidade do mês corrente, "Valor devolvido no mês" = soma de `final_total` do mês; o rascunho não entra.
- **Teste:** `SellReturnIndexContratoTest` — `UC-SRIDX-05 [V0] os três números do topo são leitura do que está gravado` (espera 2 / 2 / 140.50).
- **Regressão que defende:** número do topo somando rascunho ou recalculando valor.
- **Status: 🧪**

## UC-SRIDX-06 · Linha com venda de origem `[must]`
- **Persona:** Larissa — da devolução chega à venda que a originou.
- **Aceite:** Dado uma devolução com pagamento parcial · Quando aparece na lista · Então a linha traz o id e o número da venda de origem, o total, o valor já pago e a situação; a mais recente vem primeiro.
- **Teste:** `SellReturnIndexContratoTest` — `UC-SRIDX-06 cada linha traz a venda de origem, o valor e o que já foi pago`.
- **Regressão que defende:** link "venda de origem" e "Editar" (`/sell-return/add/{venda}`) apontando para o id errado.
- **Status: 🧪**

## UC-SRIDX-07 · O DataTable legado continua `[must]`
- **Persona:** quem abre a tela pela carga completa (Blade), até o cutover F5.
- **Aceite:** Dado uma chamada ajax sem `X-Inertia` · Quando pede `/sell-return` · Então recebe o JSON do DataTable, como antes.
- **Teste:** `SellReturnIndexContratoTest` — `UC-SRIDX-07 o DataTable legado continua respondendo à chamada ajax sem X-Inertia`.
- **Regressão que defende:** o ramo novo quebrar a tela Blade que ainda está em produção.
- **Status: 🧪**

---

## Vale-troca: a devolução vira saldo do cliente (US-VEST-021)

> **Âncora:** US-VEST-021 (`memory/requisitos/Vestuario/SPEC.md`) e decisão [W] de 2026-10-26: o vale-troca usa o
> saldo de **Adiantamento** do cliente (`contacts.balance`), que o caixa já aceita como forma de pagamento e já trava
> no saldo. Endpoint `POST /sell-return/{id}/vale-troca`. Prova: `tests/Feature/Sells/ValeTrocaContratoTest.php`.

## UC-SRET-VT-01 · A devolução vira saldo do cliente e fica paga `[V0]` `[must]`
- **Aceite:** Dado um cliente com saldo 20,00 e uma devolução de 150,00 · Quando vira vale-troca · Então o saldo vai a 170,00, a devolução fica paga e o pagamento dela é `advance` com `VT-<id>`. Nenhum dinheiro sai do caixa.
- **Teste:** `tests/Feature/Sells/ValeTrocaContratoTest.php` — `UC-SRET-VT-01`.
- **Status: 🧪** — roda na lane `sells-pest`.

## UC-SRET-VT-02 · Credita só o que falta pagar da devolução `[V0]` `[must]`
- **Aceite:** Dado uma devolução de 150,00 com 50,00 já devolvidos em dinheiro · Quando vira vale · Então o vale é de 100,00 e o saldo vai de 20,00 a 120,00.
- **Teste:** `tests/Feature/Sells/ValeTrocaContratoTest.php` — `UC-SRET-VT-02`.
- **Status: 🧪** — roda na lane `sells-pest`.

## UC-SRET-VT-03 · Devolução já paga não vira vale de novo `[V0]` `[must]`
- **Aceite:** Dado uma devolução já virada vale · Quando peço o vale de novo · Então `422 sem_saldo` e o saldo não muda.
- **Teste:** `tests/Feature/Sells/ValeTrocaContratoTest.php` — `UC-SRET-VT-03`.
- **Status: 🧪** — roda na lane `sells-pest`.

## UC-SRET-VT-04 · Apagar o vale tira do saldo o que ele pôs `[V0]` `[must]`
- **Aceite:** Dado o vale de 150,00 (saldo 170,00) · Quando o pagamento do vale é apagado · Então o saldo volta a 20,00.
- **Teste:** `tests/Feature/Sells/ValeTrocaContratoTest.php` — `UC-SRET-VT-04`.
- **Status: 🧪** — roda na lane `sells-pest`.

## UC-SRET-VT-05 · Devolução de outra empresa não vira vale `[T0]` `[must]`
- **Aceite:** Dado uma devolução de outro business · Quando peço o vale · Então `404`, nenhum pagamento e o saldo do cliente alheio não muda.
- **Teste:** `tests/Feature/Sells/ValeTrocaContratoTest.php` — `UC-SRET-VT-05`.
- **Status: 🧪** — roda na lane `sells-pest`.

## Backlog de casos

- Os `UC-DEV-01..04` do texto revisado viraram casos do registro: `Add.casos.md` (`UC-SRADD-01..06`, PR 2).
- **[BACKLOG]** Excluir devolução e adicionar pagamento pela tela React (hoje só na Blade).

## Trilha do tempo
- 2026-10-01 · [CL] trio nascido junto (charter + casos + teste) na thread 03 de venda-menu, PR 1 de 2.
- 2026-10-05 · [CL] backlog do registro movido para `Add.casos.md` (PR 2); "Editar" passa a abrir `SellReturn/Add` por visita Inertia.
