---
id: resources-js-pages-repair-index-casos
casos: Ordens de Serviço (fila) · /repair/repair
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + criterio de aceite verificavel (Dado/Quando/Entao)
por_que: o que dura e o CONTRATO — "a fila e de venda-de-reparo, nao de JobSheet" e "quem so tem view_own nao ve a fila inteira" valem em qualquer refactor da tela
owner: wagner
autor: "[C] 2026-09-05"
last_run: "2026-09-05"
---

# Casos de Uso & Aceite — Ordens de Serviço (fila)

> Derivados do [Index.charter.md](Index.charter.md) (lei) e do
> [RUNBOOK-repair-index.md](../../../../memory/requisitos/Repair/RUNBOOK-repair-index.md) (F1 PLAN) —
> **não** do `.tsx`. O `RepairController` foi lido para **confirmar** o comportamento, nunca para
> derivar o caso (§5 2026-06-05 — teste tautológico).
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **A distinção que dá nome à tela:** `/repair/repair` lista a **VENDA-de-reparo**
> (`transactions` com `sub_type='repair'`), **não** a Ordem-de-Serviço (`repair_job_sheets`, que é
> a tela `/repair/job-sheet`). O RUNBOOK grifa isso em maiúsculas no F1 §3 porque os dois nomes
> em português são "OS" e a confusão já custou wave. UC-RIDX-01 existe para que trocar o `sub_type`
> quebre o CI, e não a fila da Larissa.

---

## UC-RIDX-01 · A fila mostra venda-de-reparo, e só ela
- **Persona:** atendente abre a fila para saber quais reparos estão em andamento.
- **Aceite:** Dado no mesmo tenant uma venda comum (`sub_type` nulo), um rascunho de reparo
  (`status='draft'`) e uma venda-de-reparo finalizada · Quando faço `GET /repair/repair` com a flag
  MWART ligada · Então a lista traz **apenas** a venda-de-reparo finalizada.
- **Regressão que defende:** afrouxar qualquer um dos três filtros (`type='sell'`, `status='final'`,
  `sub_type='repair'`) mistura o faturamento inteiro na fila da oficina — e some com a fila real
  no meio do ruído.
- **Teste:** `Modules/Repair/Tests/Feature/RepairIndexContratoTest.php`
- **Status: 🧪** _(teste cita o UC e passa — run CT 100 2026-09-05: 12 passed, 57 assertions)_

## UC-RIDX-02 · Distinguir "filtro não achou" de "não há OS"
- **Persona:** Larissa filtra por status e vê a tela vazia — precisa saber se errou o filtro ou se
  a oficina está limpa.
- **Aceite:** Dado que apliquei `repair_status_id` que não casa com nada · Quando abro a fila ·
  Então a prop `filters` volta **ecoando o filtro aplicado**, e a tela mostra "Nenhuma OS no filtro";
  Dado nenhum filtro e base vazia · Então `filters` volta **sem** filtro ativo e a tela mostra
  "Sem ordens de serviço".
- **Regressão que defende:** o Controller parar de ecoar `filters` deixa os dois estados
  indistinguíveis — a tela passa a dizer "sem ordens de serviço" para quem só digitou um filtro
  errado, e o operador conclui que perdeu dado.
- **Nota de precisão:** o texto dos dois estados vive no `.tsx` (`Index.tsx:332`); o que este UC
  defende é o **dado** que torna a distinção possível, que é responsabilidade do Controller.
- **Teste:** `Modules/Repair/Tests/Feature/RepairIndexContratoTest.php`
- **Status: 🧪** _(teste cita o UC e passa — run CT 100 2026-09-05: 12 passed, 57 assertions)_

## UC-RIDX-03 · Quem só tem `repair.view_own` não enxerga a fila dos outros
- **Persona:** técnico externo com acesso restrito ao próprio trabalho.
- **Aceite:** Dado um usuário **sem** `repair.view` e **com** `repair.view_own` · Quando abre a fila ·
  Então vê apenas as OS em que é `created_by` **ou** `res_waiter_id` (service staff); Dado um usuário
  com `repair.view` · Então vê a fila inteira do tenant.
- **Regressão que defende:** perder o ramo dual (`created_by` OR `res_waiter_id`) tem duas faces —
  cair para só `created_by` **esconde do técnico a OS que é dele**, e remover o `if` inteiro
  **mostra a fila toda a quem não pode vê-la**. As duas são silenciosas.
- **Âncora:** o docblock de `buildInertiaIndexData` declara "espelha o critério dual do caminho AJAX"
  — este UC é o que torna a declaração verificável.
- **Teste:** `Modules/Repair/Tests/Feature/RepairIndexContratoTest.php`
- **Status: 🧪** _(teste cita o UC e passa — run CT 100 2026-09-05: 12 passed, 57 assertions)_

## UC-RIDX-04 · Abrir a fila não escreve nada
- **Persona:** ninguém — este caso existe porque a violação é **invisível** até virar auditoria.
- **Aceite:** Dado o estado atual das `transactions` do tenant · Quando faço o `GET` da fila ·
  Então nenhuma linha é criada, alterada ou apagada, e nenhum job é despachado.
- **Regressão que defende:** o charter declara `❌ NÃO grava nada em GET` e `❌ NÃO dispara transição
  de estágio ao carregar`. Um "só marca como visualizado" enfiado no `index()` violaria os dois sem
  nenhum sintoma visível — e num módulo com FSM append-only isso contamina a trilha.
- **Teste:** `Modules/Repair/Tests/Feature/RepairIndexContratoTest.php`
- **Status: 🧪** _(teste cita o UC e passa — run CT 100 2026-09-05: 12 passed, 57 assertions)_

---

## Contrato ainda sem UC (prosa honesta, sem gate)

> Comportamento real, hoje defendido por teste que **não cita UC** — logo invisível ao G-2. Vira UC
> quando ganhar um teste que o cite pelo id. Não duplico o teste só para criar a citação.

- **[BACKLOG]** Ordenação aceita apenas a whitelist (`invoice_no`, `repair_due_date`,
  `transaction_date`, `final_total`, `contact_name`, `repair_status`) — já defendido por
  `RepairIndexMwartTest.php` → *"valida sort fora da whitelist é rejeitado"*.
- **[BACKLOG]** A fila não cruza tenant — já defendido por `RepairIndexMwartTest.php` →
  *"força business_id scope — não vaza dados de outro tenant"*.
- **[BACKLOG]** Flag MWART OFF preserva o Blade, e a whitelist `business_ids` governa o rollout —
  já defendido por 4 casos de `RepairIndexMwartTest.php`.
- **[BACKLOG]** `permitted_locations()` restringe a fila às localizações do usuário — sem teste hoje.
- **[BACKLOG]** Os KPIs de topo (`meta.totals`) são calculados **sobre o filtro aplicado**, não sobre
  a base inteira. O charter só diz "KPIs de topo resumindo a fila" — qual das duas leituras é a
  desejada é decisão de [W], não minha; sem essa definição um teste aqui fixaria um palpite.
