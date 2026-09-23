---
id: resources-js-pages-essentials-reminders-index-casos
casos: Essentials/Reminders/Index — lembretes pessoais · /essentials/reminder
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o lembrete ser de um usuário só, dentro de um business só, não muda quando a grade do mês for redesenhada.
owner: wagner
last_run: "2026-09-23"
---

# Casos de Uso & Aceite — Essentials/Reminders/Index

> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão.
>
> **Fontes.** Derivados do [charter](Index.charter.md) (Goals, Non-Goals, *Backend contract*, *Métricas de sucesso*)
> e do Controller real `Modules\Essentials\Http\Controllers\ReminderController@index` (render
> `Essentials/Reminders/Index` com `reminders` + `repeats`, filtro `business_id` + `user_id`) e `@store`.
> Nenhum UC vem do protótipo.
>
> **Teste:** `tests/Feature/Essentials/RemindersIndexContratoTest.php` — tenant fictício **98** contra adversário **99**
> ([ADR 0358](../../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)); nunca biz=4.
>
> ⚖️ **Onde roda (medido 2026-09-23):** **nenhuma lane de PR** executa `tests/Feature/Essentials/` — a lane
> `Essentials · Pest (MySQL)` ([`essentials-pest.yml`](../../../../../.github/workflows/essentials-pest.yml)) lista arquivos
> explícitos e este não está lá. Hoje roda só na nightly do CT 100. Todo UC nasce **⬜ sem veredito**:
> 0 UC executado — trio nasce neste PR; veredito pendente da lane.

---

## UC-EREM-01 · Abro a tela e vejo o meu lembrete
- **Persona:** Larissa — confere o que tem agendado no mês.
- **Aceite:** Dado um lembrete meu (semanal, 09:30) · Quando abro `GET /essentials/reminder` · Então a resposta é 200 com o componente `Essentials/Reminders/Index`; `reminders` traz o lembrete com nome, hora `09:30` e repetição `every_week`; `repeats` oferece as 4 opções (`one_time`, `every_day`, `every_week`, `every_month`).
- **Teste:** `tests/Feature/Essentials/RemindersIndexContratoTest.php` — `UC-EREM-01`.
- **Regressão que defende:** a grade do mês renderizar sem dado ou com hora/repetição trocada.
- **Status:** ⬜ sem veredito — teste nasce neste PR.

---

## UC-EREM-02 · [T0] Lembrete de outro negócio nunca aparece
- **Persona:** Larissa — nunca vê lembrete de outra empresa.
- **Aceite:** Dado um lembrete no business 98 e outro no 99, com o mesmo `user_id` · Quando o usuário do 98 abre a tela · Então só o do 98 aparece (charter *Métricas*: cross-tenant; Controller `where('business_id', ...)`).
- **Teste:** `tests/Feature/Essentials/RemindersIndexContratoTest.php` — `UC-EREM-02` (mesmo `user_id` nos dois de propósito: só o filtro de business pode separá-los).
- **Regressão que defende:** vazamento cross-tenant (ADR 0093).
- **Status:** ⬜ sem veredito — teste nasce neste PR.

---

## UC-EREM-03 · Lembrete de um colega do mesmo negócio não aparece
- **Persona:** Larissa — o lembrete é pessoal; o da vendedora não aparece na tela dela.
- **Aceite:** Dado um lembrete meu e um de outro usuário do mesmo business · Quando abro a tela · Então só o meu aparece (charter Non-Goal: *"NÃO mostra lembretes de outros usuários"*; Controller `where('user_id', auth id)`).
- **Teste:** `tests/Feature/Essentials/RemindersIndexContratoTest.php` — `UC-EREM-03`.
- **Regressão que defende:** a tela virar agenda compartilhada por engano.
- **Status:** ⬜ sem veredito — teste nasce neste PR.

---

## UC-EREM-04 · Crio um lembrete e ele fica meu
- **Persona:** Larissa — cadastra "pagar fornecedor, todo mês, 14:00".
- **Aceite:** Dado o form preenchido (`name`, `date`, `time`, `repeat`) · Quando envio `POST /essentials/reminder` · Então não há erro de validação e a linha gravada tem o meu `business_id`, o meu `user_id`, repetição `every_month` e hora `14:00`.
- **Teste:** `tests/Feature/Essentials/RemindersIndexContratoTest.php` — `UC-EREM-04` (prova pelo banco, não pelo redirect).
- **Regressão que defende:** lembrete gravado sem dono ou no business errado.
- **Status:** ⬜ sem veredito — teste nasce neste PR.

---

## Fora deste contrato (registrado, não inventado)

- [BACKLOG] Editar e excluir lembrete (`PUT`/`DELETE`, também escopados por `business_id` + `user_id`) — charter Goals; sem teste nesta leva.
- [BACKLOG] Validação `name`/`date`/`time` obrigatórios (charter *Métricas*, `StoreReminderRequest`) — sem teste nesta leva.
- Grade do mês, navegação por teclado e recorrência expandida são comportamento de **front** (charter Goals); o contrato HTTP não os alcança. Pedem teste de componente/e2e, não inventado aqui.
- Lembrete de outra origem (Financeiro, Ponto): Non-Goal do charter, decisão de [W].
