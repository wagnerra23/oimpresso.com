---
id: modules-crm-pages-crm-campanhas-index-casos
casos: Crm · Campanhas · /crm/campaigns
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: campanhas carregam os destinatários de SMS e e-mail de um negócio. Errar o escopo mostra campanha de outro negócio ou de colega; errar o desvio Inertia × DataTables entrega JSON cru no lugar da tela; renderizar o corpo como HTML abre script gravado por quem cria a campanha.
owner: wagner
last_run: "2026-10-13"
last_run_nota: "2026-10-13 thread Crm/11 F3: trio nasce; os testes moram em Modules/Crm/Tests/Feature/CrmCampanhasContratoTest.php (lane verticais-pest)."
last_run_ci: "_pendente_ — o veredito por UC entra no manifesto quando a lane verticais-pest rodar; até lá o Status é 🧪."
---

# Casos de Uso & Aceite — Crm · Campanhas (`/crm/campaigns`)

> **Âncora:** o [charter](Index.charter.md), o `memory/requisitos/Crm/RUNBOOK-campanhas.md` (§2-6) e a
> paridade `campanhas-parity.md` (linhas 1-15). Os UCs não derivam do `.tsx`.

---

## UC-CRMCAM-01 · A flag decide entre a Blade e a tela React · `must`

**Dado** que tenho `crm.access_all_campaigns`
**Quando** abro `/crm/campaigns` com a flag `useV2CrmCampanhas` desligada
**Então** recebo a view `crm::campaign.index` **E** com a flag ligada recebo Inertia `Crm/Campanhas/Index`.

Status: 🧪

---

## UC-CRMCAM-02 · Visita Inertia não vira JSON do DataTables · `must`

**Dado** que o browser manda `X-Inertia` e `X-Requested-With` juntos
**Quando** a tela pede a lista
**Então** recebe a prop `campanhas` — e um ajax **sem** `X-Inertia` (o da tela clássica) continua
recebendo o JSON do DataTables.

Status: 🧪

---

## UC-CRMCAM-03 · Campanha de outro negócio não aparece · `must` `[T0]`

**Dado** uma campanha no meu negócio e outra em outro negócio
**Quando** abro a lista
**Então** vejo a minha e não vejo a do outro negócio.

Status: 🧪

---

## UC-CRMCAM-04 · Quem só vê as próprias vê só as que criou · `must`

**Dado** que tenho só `crm.access_own_campaigns`
**Quando** abro a lista
**Então** vejo as campanhas criadas por mim e não as do colega.

Status: 🧪

---

## UC-CRMCAM-05 · Tipo e busca recortam a lista · `should`

**Dado** uma campanha de SMS e outra de e-mail
**Quando** filtro por SMS, ou busco pelo nome da de e-mail
**Então** a lista traz só a que corresponde.

Status: 🧪

---

## UC-CRMCAM-06 · O drawer abre a própria campanha, com o corpo em texto · `must` `[T0]`

**Dado** uma campanha minha cujo corpo tem HTML e outra de outro negócio
**Quando** abro `?campanha=ID` de cada uma
**Então** a minha chega com nome, quantidade de contatos e o corpo sem marcação **E** a do outro
negócio não abre (`null`).

Status: 🧪

---

## UC-CRMCAM-07 · `?classico=1` mantém a tela antiga, onde se cria, edita, exclui e envia · `must`

**Dado** a flag ligada
**Quando** abro `/crm/campaigns?classico=1`
**Então** recebo a view `crm::campaign.index`, que segue hospedando a escrita e o envio.

Status: 🧪
