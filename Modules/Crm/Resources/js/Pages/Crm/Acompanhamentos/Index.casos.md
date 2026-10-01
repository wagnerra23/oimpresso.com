---
id: modules-crm-pages-crm-acompanhamentos-index-casos
casos: Crm · Acompanhamentos · /crm/follow-ups
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a agenda comercial mostra compromissos de clientes e leads. Errar o escopo vaza agenda de outro negócio ou de colega que não devia ver; errar o desvio Inertia × DataTables entrega JSON cru no lugar da tela.
owner: wagner
last_run: "2026-10-01"
last_run_ci: "_pendente_ — o trio nasce na thread Crm/03. O veredito por UC entra no manifesto quando a lane verticais-pest rodar; até lá o Status é 🧪."
---

# Casos de Uso & Aceite — Crm · Acompanhamentos (`/crm/follow-ups`)

> **Âncora:** o [charter](Index.charter.md) (Goals e Anti-hooks), o SPEC do Crm §0 ("Follow-up
> agendado — funcional") e as permissões que a tela Blade já exigia
> (`crm.access_all_schedule` / `crm.access_own_schedule`). Os UCs não derivam do `.tsx`.

---

## UC-CRMACO-01 · A lista responde em Inertia · `must`

**Dado** que tenho `crm.access_all_schedule`
**Quando** abro `/crm/follow-ups`
**Então** recebo Inertia `Crm/Acompanhamentos/Index`, não a view `crm::schedule.index`.

Status: 🧪

---

## UC-CRMACO-02 · Visita Inertia não vira JSON do DataTables · `must`

**Dado** que o browser manda `X-Inertia` e `X-Requested-With` juntos
**Quando** a tela pede a lista
**Então** recebe a prop `acompanhamentos` — e um ajax **sem** `X-Inertia` (o da tela clássica)
continua recebendo o JSON do DataTables.

Status: 🧪

---

## UC-CRMACO-03 · Acompanhamento de outro negócio não aparece · `must` `[T0]`

**Dado** um acompanhamento no meu negócio e outro em outro negócio
**Quando** abro a lista
**Então** vejo o meu e não vejo o do outro negócio.

Status: 🧪

---

## UC-CRMACO-04 · Quem só vê os próprios vê só os atribuídos a ele · `must`

**Dado** que tenho só `crm.access_own_schedule`
**Quando** abro a lista
**Então** vejo os acompanhamentos atribuídos a mim e não os do colega — e quem tem
`crm.access_all_schedule` vê o do colega.

Status: 🧪

---

## UC-CRMACO-05 · Sem permissão é barrado · `must`

**Dado** um usuário sem nenhuma das duas permissões
**Quando** abre `/crm/follow-ups`
**Então** recebe 403 — e o mesmo teste prova que quem tem a permissão recebe 200.

Status: 🧪

---

## UC-CRMACO-06 · A aba recorrente lista só os recorrentes · `should`

**Dado** um acompanhamento avulso e um recorrente
**Quando** abro a aba "Acompanhamento recorrente"
**Então** vejo só o recorrente, e a aba principal não mostra o recorrente.

Status: 🧪

---

## UC-CRMACO-07 · `?classico=1` devolve a tela Blade · `should`

**Dado** que a escrita ainda vive nos modais da Blade
**Quando** abro `/crm/follow-ups?classico=1`
**Então** recebo a view `crm::schedule.index`.

Status: 🧪
