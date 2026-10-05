---
id: resources-js-pages-sells-caixa-index-casos
casos: Caixa do dia · /vendas/caixa
irmaos: Index.charter.md (lei) · Index.tsx
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o que a tela garante é o agregado do dia (valor, forma de pagamento, origem, caixa aberto) — isso não muda num refactor visual.
owner: wagner
last_run: "2026-10-05"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Sells · MySQL)"
---

# Casos de Uso & Aceite — Caixa do dia

> **Fonte:** derivados do CÓDIGO atual — `SellController@inertiaCaixa` + `Sells/Caixa/Index.tsx`.
> Decisão [W] 2026-09-23, textual: *"o caixa segue o código atual, escreve os UC da thread 04"*.
> O charter divergia em 4 pontos (KPIs, link da OS, botão Fechar/Abrir, Imprimir Z) e foi
> reconciliado no mesmo trabalho.
>
> **Teste:** `tests/Feature/Sells/SellsCaixaContratoTest.php` — tenant 98 × adversário 99
> (`seededSupportClientTenant()`, criado se faltar), `DatabaseTransactions`, valores esperados
> derivados à mão da fixture (dia isolado 2031-03-17) e escritos no cabeçalho do teste.
>
> ⚖️ **Lane:** `PHP / Pest (Sells · MySQL)` — [`.github/workflows/sells-pest.yml`](../../../../../.github/workflows/sells-pest.yml).
> Se ela bloqueia merge é o [`required-checks-baseline.json`](../../../../../governance/required-checks-baseline.json) que diz, não este arquivo.
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.
>
> **Os 4 KPIs e de onde vêm:** Faturado no dia = `totalDia`/`countDia` (UC-SCAIXA-01) · Vendas em
> dinheiro = linha `cash` de `porFormaPagamento`, derivada no front (UC-SCAIXA-05) · Caixa
> aberto/fechado = `caixaAberto`/`cashRegisterId` (UC-SCAIXA-07) · Origens hoje = quantidade de
> linhas de `porOrigem` (UC-SCAIXA-06). O teste prova o **dado** que alimenta cada KPI; o desenho
> do KPI no front não tem teste de render.

## UC-SCAIXA-01 · Faturado do dia `[V0]` `[must]`
- **Persona:** Larissa — no fim do dia confere quanto vendeu e em quantas vendas.
- **Aceite:** Dado vendas `final` do tipo `sell` no dia · Quando abro `/vendas/caixa?date=D` · Então `totalDia` = soma de `final_total` dessas vendas e `countDia` = quantidade; rascunho não entra.
- **Teste:** `SellsCaixaContratoTest` — `UC-SCAIXA-01 [V0] faturado do dia soma só vendas finais do dia` (espera 150.00 / 2, com um rascunho de 999.00 fora).
- **Regressão que defende:** faturado contando rascunho ou venda de outro dia.
- **Status: 🧪**

## UC-SCAIXA-02 · Outro business não entra `[T0]` `[must]`
- **Persona:** Larissa — o caixa dela nunca mostra dinheiro de outra empresa.
- **Aceite:** Dado uma venda de 70000.00 do business 99 no mesmo dia · Quando o business 98 abre o caixa · Então total, contagem, linha de dinheiro, origem oficina e refs de OS ficam iguais aos do 98 sozinho.
- **Teste:** `SellsCaixaContratoTest` — `UC-SCAIXA-02 [T0] venda de outro business não entra em nenhum agregado`.
- **Regressão que defende:** agregado perdendo o `where business_id` (ADR 0093).
- **Status: 🧪**

## UC-SCAIXA-03 · Sem permissão, sem caixa `[must]`
- **Persona:** usuário sem `direct_sell.view`, `view_own_sell_only` nem `view_commission_agent_sell`.
- **Aceite:** Dado esse usuário · Quando abre `/vendas/caixa` · Então recebe 403.
- **Teste:** `SellsCaixaContratoTest` — `UC-SCAIXA-03 sem permissão de venda a tela devolve 403`.
- **Regressão que defende:** gate removido e o caixa exposto a qualquer usuário logado.
- **Status: 🧪**

## UC-SCAIXA-04 · Filtro por data `[must]`
- **Persona:** Larissa — confere o caixa de ontem.
- **Aceite:** Dado vendas em D e D-1 · Quando escolho D-1 · Então só as de D-1 contam e `dateSelected` = D-1; sem `date`, `dateSelected` = hoje.
- **Teste:** `SellsCaixaContratoTest` — `UC-SCAIXA-04 a data escolhida muda o dia; sem data o dia é hoje`.
- **Regressão que defende:** filtro ignorado (mostra sempre hoje) ou default errado.
- **Status: 🧪**

## UC-SCAIXA-05 · Por forma de pagamento `[V0]` `[must]`
- **Persona:** Larissa — separa o que entrou em dinheiro do que entrou no cartão (é daqui que sai o KPI "Vendas em dinheiro").
- **Aceite:** Dado pagamentos dinheiro 60 + 50 e cartão 40, mais um troco de 5.00 com `is_return=1` · Quando abro o dia · Então dinheiro = 2 vendas / 110.00 e cartão = 1 venda / 40.00, com rótulos "Dinheiro" e "Cartão"; o troco não soma.
- **Teste:** `SellsCaixaContratoTest` — `UC-SCAIXA-05 [V0] por forma de pagamento conta vendas e soma valor sem o troco`.
- **Regressão que defende:** troco somado como entrada; contagem de pagamentos no lugar de vendas.
- **Status: 🧪**

## UC-SCAIXA-06 · Por origem e link da OS `[must]`
- **Persona:** Larissa — vê quanto veio do balcão e quanto veio da oficina, e abre a venda de uma OS.
- **Aceite:** Dado uma venda de balcão (100.00) e uma de oficina (50.00, `os_ref` OS-7001) · Quando abro o dia · Então `porOrigem` tem 2 linhas, a oficina traz a ref com o `id` da venda; o link `↗ #OS` navega para `/sells?open=<id>` (front, `openVenda` no `.tsx`).
- **Teste:** `SellsCaixaContratoTest` — `UC-SCAIXA-06 por origem separa balcão e oficina e entrega o id que abre a venda da OS`. A navegação em si é do front e não tem teste de render; o teste prova o `id` que ela usa.
- **Regressão que defende:** ref de OS apontando para a venda errada; origem não agrupada.
- **Status: 🧪**

## UC-SCAIXA-07 · Caixa aberto/fechado `[T0]` `[must]`
- **Persona:** Larissa — sabe se o caixa dela está aberto.
- **Aceite:** Dado nenhum caixa aberto → `caixaAberto` falso · Dado um caixa aberto do mesmo usuário em OUTRO business → continua falso · Dado um caixa aberto no próprio business → verdadeiro e `cashRegisterId` = id dele.
- **Teste:** `SellsCaixaContratoTest` — `UC-SCAIXA-07 [T0] caixa aberto é o do próprio usuário no próprio business`.
- **Regressão que defende:** KPI "Caixa" acendendo com registro de outro business.
- **Status: 🧪**

## UC-SCAIXA-08 · Botão Fechar/Abrir caixa `[should]`
- **Persona:** operador com permissão de fechar caixa.
- **Aceite:** Dado usuário sem `close_cash_register` · Então `permissions.close` falso e o botão não aparece · Dado com a permissão · Então verdadeiro. No front o botão diz "Fechar caixa" (→ `/cash-register/close-register/{id}`) com caixa aberto e "Abrir caixa" (→ `/cash-register/create`) sem caixa.
- **Teste:** `SellsCaixaContratoTest` — `UC-SCAIXA-08 o botão Fechar/Abrir caixa só existe com close_cash_register`. O rótulo e o destino do botão são do front e não têm teste de render.
- **Regressão que defende:** botão de fechamento visível para quem não pode fechar.
- **Status: 🧪**

## UC-SCAIXA-09 · Imprimir Z `[should]`
- **Persona:** Larissa — imprime o relatório do caixa.
- **Aceite:** Dado a tela · Quando clico "Imprimir Z" · Então abre `/cash-register/register-details` em nova aba — rota do caixa legado, servida por `CashRegisterController@getRegisterDetails`.
- **Teste:** `SellsCaixaContratoTest` — `UC-SCAIXA-09 Imprimir Z aponta para uma rota registrada do caixa legado` (pergunta ao router vivo; o clique é do front).
- **Regressão que defende:** botão apontando para rota removida (404).
- **Status: 🧪**

## UC-SCAIXA-10 · Movimentos e totais do turno `[V0]` `[must]`
- **Persona:** Larissa — durante o turno vê o que entrou e saiu da gaveta sem abrir o modal legado.
- **Fonte:** thread 07 venda-menu (`01-telas-legadas.md` §07) + `Caixa.charter.md` do playbook ("acompanhar o que entrou por forma de pagamento"). Totais = `CashRegisterUtil::getRegisterDetails`, o mesmo do Blade `cash_register/payment_details`.
- **Aceite:** Dado um turno aberto com abertura 100.00, vendas em dinheiro 60.00 − troco 5.00 + 50.00, cartão 40.00, despesa 20.00 e devolução 10.00 · Quando abro `/vendas/caixa` · Então a prop deferida `turno` traz troco inicial 100.00, total de vendas 135.00, despesas 20.00, devoluções 10.00, dinheiro (vendas 105.00 · despesas 20.00 · devoluções 10.00), cartão (vendas 40.00) e os 7 movimentos, o mais recente primeiro; os mesmos números que `getRegisterDetails` devolve.
- **Teste:** `SellsCaixaContratoTest` — `UC-SCAIXA-10 [V0] movimentos e totais do turno batem com o caminho do Blade e com a conta à mão`.
- **Regressão que defende:** total da tela divergindo do relatório do caixa legado; troco somado como entrada.
- **Status: 🧪**

## UC-SCAIXA-11 · Turno de outro business não aparece `[T0]` `[must]`
- **Persona:** Larissa — o turno dela nunca mostra gaveta de outra empresa.
- **Aceite:** Dado um turno aberto do mesmo `user_id` no business 99 com abertura 70000.00 · Quando o 98 abre a tela · Então `turno` é vazio; aberto também o turno do 98, `turno` é o do 98 e nenhum movimento do 99 entra.
- **Teste:** `SellsCaixaContratoTest` — `UC-SCAIXA-11 [T0] turno de outro business nunca aparece, nem os movimentos dele`.
- **Regressão que defende:** `cash_registers` sem global scope (ADR 0093) lido sem `business_id`.
- **Status: 🧪**

## UC-SCAIXA-12 · Sem turno aberto `[should]`
- **Aceite:** Dado nenhum caixa aberto · Então `turno` vem vazio e a seção mostra o link para abrir o caixa.
- **Teste:** `SellsCaixaContratoTest` — `UC-SCAIXA-12 sem caixa aberto o turno vem vazio`.
- **Status: 🧪**

- `[BACKLOG]` Conferência física: esperado em dinheiro, contado e diferença, fechando pelo `POST /cash-register/close-register` que já existe — PR 2 da thread 07.
- `[BACKLOG]` Sangria e suprimento: o caixa do UltimatePOS não grava esses tipos (`cash_register_transactions.transaction_type` só tem `initial`/`sell`/`expense`/`refund`); exigiria regra e endpoint novos — decisão [W].
