---
id: resources-js-pages-essentials-todo-index-casos
casos: Essentials/Todo/Index — lista de tarefas da equipe · /essentials/todo
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — isolamento por business, filtro por status e troca rápida de status não mudam quando o visual do protótipo for aplicado.
owner: wagner
last_run: "2026-09-23"
---

# Casos de Uso & Aceite — Essentials/Todo/Index

> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão.
>
> **Fontes.** Derivados do [charter](Index.charter.md) (Goals + Non-Goals) e do Controller real
> `Modules\Essentials\Http\Controllers\ToDoController@index` (render `Essentials/Todo/Index`, `todos` via
> `Inertia::defer`) e `@update` com `only_status`. Nenhum UC vem do protótipo.
>
> **Teste:** `tests/Feature/Essentials/TodoIndexContratoTest.php` — tenant fictício **98** contra adversário **99**
> ([ADR 0358](../../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)); nunca biz=4.
>
> ⚖️ **Onde roda (medido 2026-09-23):** **nenhuma lane de PR** executa `tests/Feature/Essentials/` — a lane
> `Essentials · Pest (MySQL)` ([`essentials-pest.yml`](../../../../../.github/workflows/essentials-pest.yml)) lista arquivos
> explícitos e este não está lá. Hoje roda só na nightly do CT 100 (árvore inteira). Por isso todo UC abaixo
> nasce **⬜ sem veredito**: 0 UC executado — trio nasce neste PR; veredito pendente da lane.

---

## UC-ETODO-01 · Abro a lista e vejo as tarefas do meu negócio
- **Persona:** Larissa — abre Tarefas para ver o que a equipe tem pendente.
- **Aceite:** Dado uma tarefa do meu business · Quando abro `GET /essentials/todo` · Então a resposta é 200 com o componente `Essentials/Todo/Index`, e a prop `todos` (deferida) traz a tarefa com o nome e o status gravados.
- **Teste:** `tests/Feature/Essentials/TodoIndexContratoTest.php` — `UC-ETODO-01`.
- **Regressão que defende:** a lista abrir vazia ou com linha sem nome/status depois de trocar o visual.
- **Status:** ⬜ sem veredito — teste nasce neste PR.

---

## UC-ETODO-02 · [T0] Tarefa de outro negócio nunca aparece
- **Persona:** Larissa — nunca vê tarefa de outra empresa.
- **Aceite:** Dado uma tarefa no business 98 e outra no 99 · Quando o usuário do 98 lista · Então a do 98 aparece e a do 99 não (charter Non-Goal: *"NÃO lista tarefas de outro business"*; Controller `ToDo::where('business_id', ...)`).
- **Teste:** `tests/Feature/Essentials/TodoIndexContratoTest.php` — `UC-ETODO-02` (com pré-condição anti-vácuo: a própria tarefa precisa aparecer).
- **Regressão que defende:** vazamento cross-tenant (ADR 0093).
- **Status:** ⬜ sem veredito — teste nasce neste PR.

---

## UC-ETODO-03 · Filtro por status mostra só aquele status
- **Persona:** Larissa — filtra "Concluída" para revisar o que foi entregue.
- **Aceite:** Dado uma tarefa `completed` e outra `new` · Quando listo com `?status=completed` · Então só a concluída volta, e todas as linhas têm status `completed` (charter Goals: filtros por partial reload).
- **Teste:** `tests/Feature/Essentials/TodoIndexContratoTest.php` — `UC-ETODO-03`.
- **Regressão que defende:** filtro que o front envia e o servidor ignora.
- **Status:** ⬜ sem veredito — teste nasce neste PR.

---

## UC-ETODO-04 · Troco o status pelo modal e ele fica gravado
- **Persona:** Larissa — marca a tarefa como concluída sem abrir a edição completa.
- **Aceite:** Dado uma tarefa `new` · Quando envio `PUT /essentials/todo/{id}` com `only_status=1` e `status=completed` · Então não há erro de validação e a tarefa passa a `completed` no banco (charter Goals: troca rápida de status via modal).
- **Teste:** `tests/Feature/Essentials/TodoIndexContratoTest.php` — `UC-ETODO-04` (prova pelo efeito no banco, não pelo redirect — sucesso e recusa são ambos redirect).
- **Regressão que defende:** o modal fechar "com sucesso" sem gravar.
- **Status:** ⬜ sem veredito — teste nasce neste PR.

---

## UC-ETODO-05 · [T0] Não troco status de tarefa de outro negócio
- **Persona:** Larissa — um id alheio na URL não mexe em dado de outra empresa.
- **Aceite:** Dado uma tarefa do business 99 · Quando o usuário do 98 envia a troca de status para o id dela · Então a resposta é 404 e o status continua `new` (Controller `scopedQueryForUser(...)->findOrFail`).
- **Teste:** `tests/Feature/Essentials/TodoIndexContratoTest.php` — `UC-ETODO-05`.
- **Regressão que defende:** escrita cross-tenant por id cru (ADR 0093).
- **Status:** ⬜ sem veredito — teste nasce neste PR.

---

## Fora deste contrato (registrado, não inventado)

- [BACKLOG] Filtro não-admin "só as próprias ou atribuídas" — está no charter e no Controller, mas exige montar usuário sem papel Admin e atribuição em `essentials_todos_users`; fica para quando houver fixture de usuário não-admin reutilizável.
- [BACKLOG] Remover com confirmação e gating por `can.edit/delete` — charter Goals; sem teste nesta leva.
- Export CSV e visão Kanban: o charter marca como Non-Goal e pendente de [W]; não viram UC.
