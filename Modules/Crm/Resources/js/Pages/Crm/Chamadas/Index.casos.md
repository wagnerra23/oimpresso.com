---
id: modules-crm-pages-crm-chamadas-index-casos
casos: Crm · Registro de chamadas · /crm/call-log
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o registro de chamadas guarda número de celular e nome de contato de clientes. Errar o escopo mostra a ligação de outro negócio ou de colega; deixar a flag furar a config liga uma tela que o produto mantém desligada.
owner: wagner
last_run: "2026-10-13"
last_run_nota: "2026-10-13 thread Crm/12 F3: trio nasce; os testes moram em Modules/Crm/Tests/Feature/CrmChamadasContratoTest.php (lane verticais-pest)."
last_run_ci: "_pendente_ — o veredito por UC entra no manifesto quando a lane verticais-pest rodar; até lá o Status é 🧪."
---

# Casos de Uso & Aceite — Crm · Registro de chamadas (`/crm/call-log`)

> **Âncora:** o [charter](Index.charter.md), o `memory/requisitos/Crm/RUNBOOK-chamadas.md` (§2-6) e a
> paridade `chamadas-parity.md` (linhas 1-13). Os UCs não derivam do `.tsx`.

---

## UC-CRMCHA-01 · A flag decide entre a Blade e a tela React · `must`

**Dado** a config ligada e `crm.view_all_call_log`
**Quando** abro `/crm/call-log` com a flag `useV2CrmChamadas` desligada
**Então** recebo a view `crm::call_logs.index` **E** com a flag ligada recebo Inertia `Crm/Chamadas/Index`.

Status: 🧪

---

## UC-CRMCHA-02 · Visita Inertia não vira JSON do DataTables · `must`

**Dado** que o browser manda `X-Inertia` e `X-Requested-With` juntos
**Quando** a tela pede a lista
**Então** recebe a prop `chamadas` (duração em HH:MM:SS) — e um ajax **sem** `X-Inertia` continua
recebendo o JSON do DataTables.

Status: 🧪

---

## UC-CRMCHA-03 · Registro de outro negócio não aparece · `must` `[T0]`

**Dado** um registro no meu negócio e outro em outro negócio
**Quando** abro a lista
**Então** vejo o meu e não o do outro negócio.

Status: 🧪

---

## UC-CRMCHA-04 · Quem só vê os próprios vê só os que criou · `must`

**Dado** que tenho só `crm.view_own_call_log`
**Quando** abro a lista
**Então** vejo os registros criados por mim e não os do colega.

Status: 🧪

---

## UC-CRMCHA-05 · Contato, autor e busca recortam a lista · `should`

**Dado** um registro com contato e outro criado por um colega
**Quando** filtro por contato, por "registro criado por" ou busco pelo número
**Então** a lista traz só o registro que corresponde.

Status: 🧪

---

## UC-CRMCHA-06 · Só o admin exclui em massa; a flag não fura a config · `must`

**Dado** a flag ligada
**Quando** um admin e um não-admin abrem a tela, e depois a config é desligada
**Então** só o admin recebe `pode_excluir` verdadeiro **E** com a config desligada a rota dá 403.

Status: 🧪

---

## UC-CRMCHA-07 · `?classico=1` mantém a tela antiga · `must`

**Dado** a flag ligada
**Quando** abro `/crm/call-log?classico=1`
**Então** recebo a view `crm::call_logs.index`.

Status: 🧪
