---
casos: Essentials/Tipos — tipos de licença do HRM
irmaos: Tipos.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-09-05"
---

# Casos de Uso & Aceite — Essentials/Tipos

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão.
>
> Os UCs de 01 a 05 são cobertos por `Modules/Essentials/Tests/Feature/HrmTiposIndexTest.php`,
> na lane `essentials-pest` (MySQL real, tenant 98 vs adversário 99).
> Os de exclusão (06–08) são cobertos por `HrmExclusaoGuardaTest.php`, que já existia:
> o comportamento é do servidor (PR #6789) e **não se duplica** aqui.

---

## UC-TIPOS-00 · Chego na tela pelo menu, sem digitar URL
- **Persona:** Larissa — abre o sistema e encontra a tela pelo sidebar do HRM.
- **Aceite:** Dado usuário com a permission `essentials.crud_leave_type` · Quando abre o HRM ·
  Então o item "Tipo de licença" existe no menu e leva a `/hrm/leave-type` (200, sem digitar URL).
- **Regressão que defende:** a tela responder 200 e ninguém alcançar.
- **Nota:** o alcance desta tela **preexiste** à migração — rota `Route::resource`, permission e
  entrada em `nav_hrm.blade.php:18` já estavam lá. Este PR troca a view, não o caminho.
- **Status: ⬜** — verificável no smoke em produção, não por Pest.

---

## UC-TIPOS-01 · Vejo a lista de tipos de licença
- **Persona:** Larissa quer saber quais licenças o time pode pedir.
- **Aceite:** Dado que estou autenticada no meu negócio · Quando abro `/hrm/leave-type` ·
  Então recebo 200 e a tela é o componente Inertia `Essentials/Tipos`.
- **Teste:** `HrmTiposIndexTest.php` — *"UC-TIPOS-01: a lista responde 200 e renderiza o componente Inertia Essentials/Tipos"*.
- **Regressão que defende:** a rota voltar a servir o Blade legado depois da migração.
- **Status: 🧪**

---

## UC-TIPOS-02 · O limite aparece como está cadastrado — e "sem limite" não vira zero
- **Persona:** Larissa cadastrou Férias com 30 dias/ano e "Abonada" sem limite nenhum.
- **Aceite:** Dado um tipo com `max_leave_count=30`/`year` e outro com ambos nulos · Quando a
  lista carrega · Então o primeiro traz `30`/`year` e o segundo traz **nulo** nos dois campos.
- **Teste:** `HrmTiposIndexTest.php` — *"UC-TIPOS-02: o limite chega como max_leave_count + leave_count_interval, e nulo continua nulo"*.
- **Regressão que defende:** o servidor "normalizar" ausência de limite para `0`, que a tela leria
  como *zero dias permitidos* — o oposto de "sem limite".
- **Status: 🧪**

---

## UC-TIPOS-03 · Não vejo tipo de outro negócio
- **Persona:** qualquer tenant — isolamento Tier 0.
- **Aceite:** Dado um tipo meu e um tipo do negócio vizinho · Quando a lista carrega ·
  Então o meu aparece e o do vizinho **não**.
- **Teste:** `HrmTiposIndexTest.php` — *"UC-TIPOS-03: tipo do tenant adversário NÃO aparece na lista (ADR 0093)"*.
- **Regressão que defende:** vazamento cross-tenant na listagem ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
- **Status: 🧪**

---

## UC-TIPOS-04 · "Pedidos no ano" conta só o meu negócio
- **Persona:** Larissa olha quantas vezes cada tipo foi usado antes de mexer no cadastro.
- **Aceite:** Dado 2 licenças minhas e 3 do vizinho apontando para o **mesmo** id de tipo ·
  Quando a lista carrega · Então a contagem exibida é **2**.
- **Teste:** `HrmTiposIndexTest.php` — *"UC-TIPOS-04: 'Pedidos no ano' conta só licenças DO TENANT, nunca as do vizinho"*.
- **Regressão que defende:** `essentials_leaves.essentials_leave_type_id` **não tem FK** (migration
  `2019_05_17_175921` cria só índice), então o banco aceita a linha do vizinho apontando para o
  meu tipo. Sem o filtro por `business_id` na agregação, o número exibido soma o negócio alheio.
- **Status: 🧪**

---

## UC-TIPOS-05 · "Pedidos no ano" é do ano corrente
- **Persona:** Larissa em janeiro — a contagem recomeça.
- **Aceite:** Dado uma licença do ano passado nesse tipo · Quando a lista carrega ·
  Então a contagem é **0**.
- **Teste:** `HrmTiposIndexTest.php` — *"UC-TIPOS-05: 'Pedidos no ano' ignora licença de ano anterior"*.
- **Regressão que defende:** a coluna dizer "no ano" e contar a história inteira.
- **Status: 🧪**

---

## UC-TIPOS-06 · Excluir um tipo em uso me diz QUANTAS licenças travam
- **Persona:** Larissa tenta apagar "Férias" e precisa entender por que não pode.
- **Aceite:** Dado um tipo com 3 licenças registradas · Quando confirmo a exclusão · Então o
  servidor responde **422** com `blocked_by.leaves = 3`, o tipo **continua existindo**, e o
  diálogo permanece aberto exibindo a mensagem com o número.
- **Teste (servidor):** `HrmExclusaoGuardaTest.php` — *"tipo de licença EM USO: 422 dizendo QUANTAS licenças travam, e NÃO apaga"*.
- **Regressão que defende:** a tela mostrar "erro" genérico e tornar invisível o trabalho do
  [#6789](https://github.com/wagnerra23/oimpresso.com/pull/6789) — foi por isso que a exclusão usa
  `fetch` e não `router.delete` (o Inertia converte 422 em `errors` e descarta `blocked_by`).
- **Status: 🧪** (servidor) · **⬜** (render do motivo — smoke em produção)

---

## UC-TIPOS-07 · Excluir um tipo sem uso apaga de fato
- **Aceite:** Dado um tipo sem licença nenhuma · Quando confirmo · Então some da lista.
- **Teste:** `HrmExclusaoGuardaTest.php` — *"tipo de licença SEM uso: DELETE apaga de fato (antes respondia 200 sem apagar)"*.
- **Regressão que defende:** o `destroy` de corpo vazio, que respondia 200 sem apagar nada.
- **Status: 🧪**

---

## UC-TIPOS-08 · Tipo de outro negócio é 404, nunca "não travado"
- **Aceite:** Dado o id de um tipo do negócio vizinho · Quando mando excluir · Então **404** e o
  tipo continua existindo.
- **Teste:** `HrmExclusaoGuardaTest.php` — *"tipo de licença cross-tenant: tipo do biz adversário → 404 e continua existindo"*.
- **Regressão que defende:** o id chega **cru** da rota; resolver fora do business apagaria dado
  alheio ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Criar e editar tipo pela própria tela (hoje `create`/`edit` seguem no Blade legado).
- **[BACKLOG]** Limite deixar de ser informativo e recusar o pedido que o estoura — é o PR-3 do
  pedido HRM (`LeaveBalance`), noutra superfície.

## Trilha do tempo
- 2026-09-05 · [CC] carimbado por `criar-tela.mjs` e preenchido no mesmo PR — HRM-O7 PR-9.
  Refs: UI-0013 · ADR 0264 G-1/G-2 · ADR 0104 (MWART) · PR #6789 (contrato do 422).
