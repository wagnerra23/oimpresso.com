---
id: modules-crm-pages-crm-painel-index-casos
casos: Crm · Painel · /crm/dashboard
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o painel agrega contatos, conversões e agenda do negócio inteiro. Errar o escopo mostra número de outro negócio; errar o gate entrega o quadro do negócio a quem não é Admin.
owner: wagner
last_run: "2026-10-01"
last_run_ci: "_pendente_ — o trio nasce na thread Crm/04. O veredito por UC entra no manifesto quando a lane verticais-pest rodar; até lá o Status é 🧪."
---

# Casos de Uso & Aceite — Crm · Painel (`/crm/dashboard`)

> **Âncora:** o [charter](Index.charter.md) (Goals e Anti-hooks), o SPEC do Crm §0 ("Dashboard
> CRM — funcional Blade", US-CRM-062) e os gates que a tela Blade já aplicava por seção
> (`crm.access_*_schedule`, `crm.access_*_leads`, `Admin#<biz>`). Os UCs não derivam do `.tsx`.

---

## UC-CRMPAI-01 · O painel responde em Inertia · `must`

**Dado** que sou usuário do CRM
**Quando** abro `/crm/dashboard`
**Então** recebo Inertia `Crm/Painel/Index`, não a view `crm::crm_dashboard.index`.

Status: 🧪

---

## UC-CRMPAI-02 · "Meus leads convertidos" conta só este negócio · `must` `[T0]`

**Dado** uma conversão minha no meu negócio e outra registrada em outro negócio
**Quando** abro o painel
**Então** o KPI sobe 1 — a conversão do outro negócio não entra na conta.

Status: 🧪

---

## UC-CRMPAI-03 · Admin vê o quadro do negócio, só do próprio negócio · `must` `[T0]`

**Dado** que sou Admin, com uma fonte e um lead nela no meu negócio, e outra fonte em outro negócio
**Quando** abro o painel
**Então** "Fontes" mostra a minha com o lead contado e não mostra a do outro negócio.

Status: 🧪

---

## UC-CRMPAI-04 · Quem não é Admin não recebe o quadro do negócio · `must`

**Dado** que não tenho o papel `Admin#<biz>`
**Quando** abro o painel
**Então** a prop `negocio` vem vazia — o dado nem sai do servidor.

Status: 🧪

---

## UC-CRMPAI-05 · Seções pessoais seguem a permissão · `should`

**Dado** um usuário sem permissão de acompanhamento nem de leads
**Quando** abre o painel
**Então** não recebe "Acompanhamentos de hoje", "Meus acompanhamentos" nem "Meus leads" — e quem
tem `crm.access_own_schedule` e `crm.access_own_leads` recebe os três.

Status: 🧪

---

## UC-CRMPAI-06 · `?classico=1` devolve a tela Blade · `should`

**Dado** que filtros por período e o detalhe das conversões ainda vivem na Blade
**Quando** abro `/crm/dashboard?classico=1`
**Então** recebo a view `crm::crm_dashboard.index`.

Status: 🧪
