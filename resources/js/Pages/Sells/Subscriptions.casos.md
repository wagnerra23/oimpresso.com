---
casos: Assinaturas (vendas recorrentes) · /sells/subscriptions
irmaos: Subscriptions.charter.md (lei) · Subscriptions.tsx
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a única tela de listagem de Vendas que ESCREVE — pausar uma assinatura é deixar de cobrar um cliente. Quem vê qual assinatura, quem pode pausar e de qual empresa não pode mudar num refactor visual.
owner: wagner
last_run: "2026-10-14"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Sells · MySQL)"
---

# Casos de Uso & Aceite — Assinaturas

> **Fonte (ordem fixa, how-trabalhar):** aceite da **US-SELL-062** (`memory/requisitos/Sells/SPEC.md`) +
> `Subscriptions.charter.md` (Goals/permissões) + `memory/requisitos/Sells/RUNBOOK-subscriptions.md` (§1-§3).
> O `SDD-tela-venda-v1.0.md` não tem CU de assinatura (só lista a tela como "sem casos.md" e põe
> recorrência como Non-Goal do Create, NG-01). Não há `ANTI-REGRESSAO-*` (Delphi) para esta tela.
> Código (`SellPosController@listSubscriptions` / `@toggleRecurringInvoices`) só confirmou o
> comportamento — re-localizar com `grep -n "function listSubscriptions\|function toggleRecurringInvoices" app/Http/Controllers/SellPosController.php`.
> Nada derivado do `.tsx`.
>
> **Teste:** `tests/Feature/Sells/SellsSubscriptionsContratoTest.php` — tenant 98 × adversário 99,
> `DatabaseTransactions`, permissão direta ao usuário, headers do browser (`X-Inertia` + `X-Requested-With`).
>
> ⚖️ **Lane:** `PHP / Pest (Sells · MySQL)` — `.github/workflows/sells-pest.yml` (allowlist). Se ela bloqueia
> merge é dito por `governance/required-checks-baseline.json`, não por este arquivo.
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.

## UC-SSUB-01 · Lista só as vendas finais recorrentes do business `[T0]` `[must]`
- **Persona:** Larissa (dona) acompanhando as cobranças recorrentes.
- **Aceite:** Dado uma recorrente ativa e uma pausada do meu business, uma venda final não recorrente, um rascunho marcado recorrente e uma recorrente de outra empresa · Quando abro a lista de assinaturas · Então vejo só as duas recorrentes finais do meu business.
- **Fonte:** SPEC US-SELL-062 aceite 2 (escopo `transactions.business_id`) e 3 (`status = final` + `is_recurring = 1`) · charter Mission · RUNBOOK §1.
- **Dados da lista:** `GET /sells/subscriptions?ajax=1` sem `X-Inertia` — o DataTable que a Page lê.
- **Teste:** `SellsSubscriptionsContratoTest` — `UC-SSUB-01 [T0] a lista traz só as vendas finais recorrentes do business…`.
- **Regressão que defende:** assinatura de outra empresa na lista (ADR 0093); venda comum ou rascunho misturados.
- **Status: 🧪**

## UC-SSUB-02 · A tela exige permissão de leitura de venda `[must]`
- **Persona:** vendedor sem acesso a vendas.
- **Aceite:** Dado um usuário com `sell.view`, ou só com `direct_sell.access` · Quando abre Assinaturas · Então a Page `Sells/Subscriptions` abre. Sem nenhuma das duas, a tela **e** o endpoint de dados devolvem 403.
- **Fonte:** SPEC US-SELL-062 aceite 1 · charter Goals ("Permission gate") · RUNBOOK §2.
- **Teste:** `SellsSubscriptionsContratoTest` — `UC-SSUB-02 a tela abre com sell.view ou com direct_sell.access e é negada (403)…`.
- **Regressão que defende:** gate só no render da Page, com o JSON da lista aberto.
- **Status: 🧪**

## UC-SSUB-03 · KPIs total/ativas/pausadas contam só o business `[T0]` `[should]`
- **Persona:** Larissa olhando quantas cobranças estão paradas.
- **Aceite:** Dado uma recorrente ativa e uma pausada novas no meu business (e o mesmo ruído do UC-SSUB-01) · Quando reabro a tela · Então total sobe 2, ativas 1, pausadas 1 — não recorrente, rascunho e outra empresa não contam.
- **Fonte:** charter Goals ("3 KPIs: total / ativas / pausadas" + "scope business_id") · RUNBOOK §3 passo 1 (`kpis`).
- **Teste:** `SellsSubscriptionsContratoTest` — `UC-SSUB-03 [T0] os KPIs total/ativas/pausadas contam só…` (por delta: a lane persiste dados alheios no tenant 98).
- **Regressão que defende:** KPI global ou contando venda que não é assinatura.
- **Status: 🧪**

## UC-SSUB-04 · Pausar e retomar alterna a cobrança, só no próprio business `[T0]` `[must]`
- **Persona:** Larissa pausando a assinatura de um cliente que pediu para parar.
- **Aceite:** Dado uma assinatura ativa do meu business · Quando peço pausar · Então ela passa a ter data de parada; pedindo de novo, volta a ativa. Dado uma assinatura de **outra empresa** ou uma venda final **não recorrente** do meu business · Quando peço pausar pelo id · Então é recusado (`success = 0`) e o banco não muda.
- **Fonte:** SPEC US-SELL-062 §Problema ("pausa e retoma uma cobrança recorrente") + aceite 2 · charter Goals ("Toggle start/stop por linha") · RUNBOOK §1.
- **Endpoint real:** `GET /toggle-subscription/{id}` (ver divergência de rota abaixo).
- **Teste:** `SellsSubscriptionsContratoTest` — `UC-SSUB-04 [T0] pausar e retomar alterna a parada…` (assert pelo efeito em `transactions.recur_stopped_on`).
- **Regressão que defende:** pausar cobrança de outra empresa por id adivinhado; "pausar" virar flag em venda comum.
- **Status: 🧪**

## UC-SSUB-05 · Pausar exige permissão de escrita `[must]`
- **Persona:** vendedor que só consulta vendas.
- **Aceite:** Dado um usuário só com `sell.view` · Quando pede pausar · Então recebe 403 e a assinatura segue ativa. A Page recebe `permissions.update` = falso para ele e verdadeiro para quem tem `sell.update`.
- **Fonte:** SPEC US-SELL-062 aceite 4 (`sell.update`, escrita distinta da leitura) · charter Goals ("`sell.update` (toggle)").
- **Teste:** `SellsSubscriptionsContratoTest` — `UC-SSUB-05 sem permissão de escrita o pausar é recusado (403)…`.
- **⚠️ Divergência contrato × código (hipótese medida por leitura, sem teste vermelho):** SPEC e charter dizem que o toggle exige **`sell.update`**; `toggleRecurringInvoices` checa **`sell.create`** (a lista DataTable e `permissions.update` usam `sell.update`). Logo, por leitura: quem tem só `sell.update` vê o botão e leva 403; quem tem só `sell.create` alterna sem `sell.update`. O teste deste UC **não** toma partido (o leitor não tem nenhuma das duas). Não consertado — decisão [W] (qual permissão vale) antes de virar teste que separe os dois casos.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Divergência de permissão do toggle (`sell.update` no contrato × `sell.create` no código) — ver UC-SSUB-05. Vira caso próprio quando [W] decidir a permissão.
- **[BACKLOG]** O toggle muda estado por **GET** (`/toggle-subscription/{id}`, herdado do Blade) — sem CSRF. O charter Goals fala em `POST /sells/recurring-toggle/{id}`, rota que não existe.
- **[BACKLOG]** Próxima fatura calculada (dias/meses com `subscription_repeat_on`/anos) e lista de faturas geradas — fonte só no RUNBOOK §1 + código; sem 2ª fonte de contrato.
- **[BACKLOG]** Filtro por cliente (dropdown deferred do charter) e por período (Blade legado) — 1 fonte cada.

## Trilha do tempo
- 2026-10-14 · [CL] trio completado (E1 do regime de evolução, agente `sdd-from-source`). Refs: ADR 0104 · ADR 0264 G-1/G-2 · ADR 0358 · US-SELL-062.
