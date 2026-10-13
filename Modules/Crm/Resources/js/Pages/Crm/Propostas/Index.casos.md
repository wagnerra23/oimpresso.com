---
id: modules-crm-pages-crm-propostas-index-casos
casos: Crm · Propostas · /crm/proposals
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a lista de propostas mostra e-mails comerciais enviados a contatos (assunto, corpo, cc, bcc, anexos). Errar o escopo vaza proposta de outro negócio ou de colega; errar o desvio Inertia × DataTables entrega JSON cru no lugar da tela.
owner: wagner
last_run: "2026-10-13"
last_run_ci: "_pendente_ — o trio nasce na thread Crm/14 (F3). O veredito por UC entra quando a lane verticais-pest rodar; até lá o Status é 🧪."
---

# Casos de Uso & Aceite — Crm · Propostas (`/crm/proposals`)

> **Âncora:** o [charter](Index.charter.md), o mapa de paridade
> `memory/requisitos/Crm/propostas-parity.md` (linhas altas 1–3) e o baseline da Blade
> `CrmPropostasBaselineTest` (F2). Os UCs não derivam do `.tsx`.

---

## UC-CRMPRO-01 · Com a flag ligada, a lista responde em Inertia · `must`

**Dado** a flag `useV2CrmPropostas` ligada para o meu negócio
**Quando** abro `/crm/proposals`
**Então** recebo Inertia `Crm/Propostas/Index`, não a view `crm::proposal.index`.

Status: 🧪

---

## UC-CRMPRO-02 · Visita Inertia não vira JSON do DataTables · `must`

**Dado** que o browser manda `X-Inertia` e `X-Requested-With` juntos
**Quando** a tela pede a lista
**Então** recebe a prop `propostas` — e um ajax **sem** `X-Inertia` (o da tela clássica) continua
recebendo o JSON do DataTables.

Status: 🧪

---

## UC-CRMPRO-03 · Proposta de outro negócio não aparece nem abre · `must` `[T0]`

**Dado** uma proposta no meu negócio e outra em outro negócio
**Quando** abro a lista e peço o detalhe da do outro negócio pelo id
**Então** vejo só a minha na lista e o drawer não abre (`proposta` nulo).

Status: 🧪

---

## UC-CRMPRO-04 · Quem não é Admin# vê só as que enviou, na lista e no drawer · `must`

**Dado** que não sou `Admin#` do negócio
**Quando** abro a lista e peço o detalhe de uma proposta enviada pelo colega
**Então** vejo só as minhas e o drawer da do colega não abre — e o `Admin#` vê a do colega.

Status: 🧪

---

## UC-CRMPRO-05 · Mandar só com modelo e permissão · `should`

**Dado** um negócio sem modelo de proposta, ou um usuário sem `crm.access_proposal`
**Quando** abro a lista
**Então** `pode_enviar` é falso — e fica verdadeiro com modelo cadastrado **e** a permissão.

Status: 🧪

---

## UC-CRMPRO-06 · O drawer mostra o corpo como texto, nunca HTML · `must`

**Dado** uma proposta cujo corpo tem marcação HTML (incluindo `<script>`)
**Quando** abro o detalhe
**Então** o `corpo` chega sem nenhuma tag, com o texto dos parágrafos.

Status: 🧪

---

## UC-CRMPRO-07 · Flag desligada ou `?classico=1` seguem na Blade · `must`

**Dado** a flag desligada (default) — ou ligada com `?classico=1`
**Quando** abro `/crm/proposals`
**Então** recebo a view `crm::proposal.index`.

Status: 🧪
