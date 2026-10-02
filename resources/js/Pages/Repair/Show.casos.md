---
id: resources-js-pages-repair-show-casos
casos: Detalhe da venda-de-reparo · /repair/repair/{id}
irmaos: Show.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + criterio de aceite verificavel (Dado/Quando/Entao)
por_que: o contrato dura — "esta tela e a VENDA, nao a Ordem-de-Servico", "o historico chega deferido" e "o valor sai formatado em pt-BR" sobrevivem a qualquer refactor
owner: wagner
autor: "[C] 2026-09-05"
last_run: "2026-09-05"
---

# Casos de Uso & Aceite — Detalhe da venda-de-reparo

> Derivados do [Show.charter.md](Show.charter.md) (lei) e do
> [RUNBOOK-repair-show.md](../../../../memory/requisitos/Repair/RUNBOOK-repair-show.md) (F1 PLAN) —
> **não** do `.tsx`. O `RepairController@show` foi lido para **confirmar**, nunca para derivar.
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **A confusão que o charter manda evitar:** esta tela é a **venda-de-reparo**
> (`Transaction` com `sub_type='repair'` — invoice, sell lines, pagamentos, garantia). A
> **Ordem-de-Serviço** é outra entidade (`repair_job_sheets`) e outra tela (`/repair/job-sheet/{id}`).
> O charter lista isso como anti-padrão de UX explícito ("deixar header explícito 'Venda de Reparo'").

---

## UC-RSHW-01 · O payload é o de uma VENDA, não o de uma Ordem-de-Serviço
- **Persona:** operador abre o detalhe para conferir o que foi faturado no reparo.
- **Aceite:** Dado uma venda-de-reparo finalizada · Quando abro `/repair/repair/{id}` com a flag
  MWART ligada · Então a prop `sell` traz os campos da **venda** — `invoice_no`, `final_total`,
  `payment_status`, `sell_lines[]` e `payments[]` — além dos campos do aparelho (`device_model_name`,
  `serial_no`, `defects`).
- **Regressão que defende:** trocar a origem por `repair_job_sheets` (o outro "OS" do módulo)
  entregaria uma tela plausível e **sem faturamento nenhum** — sem invoice, sem linhas, sem
  pagamento. O charter chama isso pelo nome: "Confusão com JobSheet".
- **Teste:** `Modules/Repair/Tests/Feature/RepairShowContratoTest.php`
- **Status: 🧪** _(teste cita o UC e passa — run CT 100 2026-09-05: 12 passed, 57 assertions)_

## UC-RSHW-02 · O histórico não segura o primeiro paint
- **Persona:** operador abrindo uma OS antiga, com log longo de atividades.
- **Aceite:** Dado uma venda-de-reparo com atividades · Quando faço o `GET` inicial · Então a prop
  `activities` **não** vem no payload (é `Inertia::defer`); Quando peço o partial reload de
  `activities` · Então ela chega, limitada a 50 entradas.
- **Regressão que defende:** o RUNBOOK marca o log de atividades como risco R2 ("Activities log
  heavy — defer"). Trocar o `Inertia::defer` por valor eager devolve o custo ao first-paint sem
  mudar uma linha visível da tela — regride o p95 e ninguém vê no diff.
- **Teste:** `Modules/Repair/Tests/Feature/RepairShowContratoTest.php`
- **Status: 🧪** _(teste cita o UC e passa — run CT 100 2026-09-05: 12 passed, 57 assertions)_

## UC-RSHW-03 · Sem `repair.view`, a tela não existe
- **Persona:** usuário do tenant sem permissão de reparo.
- **Aceite:** Dado um usuário sem `repair.view` e sem `superadmin` · Quando pede
  `/repair/repair/{id}` · Então recebe **403** e nenhum dado da venda é serializado.
- **Regressão que defende:** o gate está no topo do `show()`, antes de qualquer query. Movê-lo para
  depois do branch MWART — ou confiar só no menu que não mostra o link — deixa a URL direta aberta.
- **Nota de precisão:** o gate real é composto — `superadmin` **ou** (`repair_module` na assinatura
  **e** `repair.view`). Este UC exercita a perna da permissão; a perna da assinatura é a do
  `hasThePermissionInSubscription`, e não a exercito aqui para não fabricar estado de pacote.
- **Teste:** `Modules/Repair/Tests/Feature/RepairShowContratoTest.php`
- **Status: 🧪** _(teste cita o UC e passa — run CT 100 2026-09-05: 12 passed, 57 assertions)_

## UC-RSHW-04 · O valor sai formatado em pt-BR, e o número cru vai junto
- **Persona:** Larissa confere o total do reparo antes de cobrar.
- **Aceite:** Dado uma venda com `final_total = 1234.56` · Quando abro o detalhe · Então
  `sell.final_total_formatted` é `"R$ 1.234,56"` (ponto de milhar, vírgula decimal) **e**
  `sell.final_total` continua sendo o número `1234.56` para quem precisar recalcular.
- **Regressão que defende:** o incidente de 2026-06-05 (ROTA LIVRE, `Util::num_uf` lendo o ponto
  decimal como separador de milhar) começou exatamente numa fronteira de formatação: número
  locale-ambíguo atravessando camada. Manter **os dois** campos — cru e formatado — é o que impede
  que a tela vire a única fonte do valor e que alguém reparseie a string de volta.
- **Nota de escopo:** este UC **lê** e fixa a formatação vigente; não altera cálculo algum. Mexer em
  valor exigiria dupla prova + antes→depois + aprovação [W] (regra-mestre Tier 0).
- **Teste:** `Modules/Repair/Tests/Feature/RepairShowContratoTest.php`
- **Status: 🧪** _(teste cita o UC e passa — run CT 100 2026-09-05: 12 passed, 57 assertions)_

---

## UC-RSHW-05 · "Editar" abre a edição da venda de reparo
- **Persona:** técnico que abriu o detalhe da venda de reparo e precisa corrigir algo.
- **Por que existe:** o botão "Editar" aponta para `/repair/repair/{id}/edit`, rota excluída do resource (`->except(['create','edit'])`) — **404 em prod** (curl 2026-10-01). Decisão [W] 2026-10-01 (reparo como tipo de venda): a edição de reparo é a do POS com o tipo reparo, `/pos/{id}/edit?sub_type=repair`, o mesmo destino da listagem Blade, que traz a seção de reparo e salva pelo `SellPosController@update`.
- **Aceite:** Dado uma venda de reparo do próprio business · Quando clica "Editar" (navegação React, `X-Inertia`) · Então a resposta é **409 com `X-Inertia-Location`** = `/pos/{id}/edit?sub_type=repair` (o navegador abre a página inteira) · E a visita comum ao mesmo endereço recebe **302** para o mesmo destino · Dado uma venda **comum** do mesmo business, ou uma venda de reparo de **outro business** · Então **404**.
- **Teste:** `Modules/Repair/Tests/Feature/RepairShowContratoTest.php` (UC-RSHW-05, tenant 98, lane `verticais-pest`).
- **Status: 🧪** _(nasce sem run.)_
- **Por que no servidor e não no botão:** a `Repair/Show.tsx` está na dívida do header antigo (`config/pageheader-shared-baseline.json`); tocá-la exige migrar o header no mesmo PR (ADR 0409), com aprovação visual. A rota resolve sem tocar a tela.

---

## UC-RSHW-06 · Venda fora do prazo de edição volta pro detalhe, sem loop
- **Por que existe:** smoke em prod (2026-10-01, venda de reparo de 2022): `/repair/repair/{id}/edit` digitado direto dava **`ERR_TOO_MANY_REDIRECTS`**. O `SellPosController@edit` recusa venda fora do prazo (`canBeEdited`) ou com devolução (`isReturnExist`) com `back()`; sem `Referer`, o `back()` cai no "previous URL" da sessão — que era a própria rota do UC-RSHW-05 — e o ciclo não termina. Com clique (há `Referer`) voltava pro detalhe; o defeito era só sem página anterior.
- **Aceite:** Dado `transaction_edit_days` = 30 · Quando pede "Editar" de uma venda de reparo de 2 dias atrás · Então vai pro PDV (`/pos/{id}/edit?sub_type=repair`, controle) · Quando pede o de uma venda de 2 anos atrás · Então volta pro **detalhe da venda** com a mensagem de recusa na sessão (`status.success` = 0), sem passar pelo PDV.
- **Teste:** `Modules/Repair/Tests/Feature/RepairShowContratoTest.php` (UC-RSHW-06, lane `verticais-pest`).
- **Status: 🧪** _(nasce sem run.)_

---

## Contrato ainda sem UC (prosa honesta, sem gate)

> Já defendido por teste que **não cita UC** — invisível ao G-2. Vira UC quando ganhar um teste que
> o cite pelo id; não duplico o teste só para criar a citação.

- **[BACKLOG]** Flag MWART OFF preserva o Blade e ON entrega Inertia — `Wave3B6RepairShowTest.php`.
- **[BACKLOG]** Venda de outro tenant devolve 404 — `Wave3B6RepairShowTest.php` →
  *"biz cross-tenant — 404 ao tentar repair show de outro biz"*.
- **[BACKLOG]** O painel FSM tem flag **própria** (`mwart.repair_show_fsm_panel.enabled`), separada
  da flag da tela — `Wave3B6RepairShowTest.php`.
- **[BACKLOG]** O checklist do aparelho é a concatenação do checklist do modelo com o
  `default_repair_checklist` das configurações do módulo — sem teste hoje, e a ordem da concatenação
  (padrão antes do específico) não está declarada em lugar nenhum além do código.
