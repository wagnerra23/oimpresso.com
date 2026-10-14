---
id: modules-crm-pages-crm-logins-index-casos
casos: Crm · Login de contatos · /crm/all-contacts-login
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela lista credenciais de acesso ao portal do contato. Errar o escopo expõe login de outro negócio ou da equipe; errar as props vaza hash de senha para o browser.
owner: wagner
last_run: "2026-10-13"
last_run_ci: "_pendente_ — o trio nasce na thread Crm/16. O veredito por UC entra no manifesto quando a lane verticais-pest rodar; até lá o Status é 🧪."
---

# Casos de Uso & Aceite — Crm · Login de contatos (`/crm/all-contacts-login`)

> **Âncora:** o [charter](Index.charter.md) (Goals e Anti-hooks), a permissão que a Blade já exigia
> (`crm.access_contact_login`) e a D3 de [W] (portal do contato no escopo). Os UCs não derivam do `.tsx`.

---

## UC-CRMLOG-01 · Com a flag, a lista responde em Inertia; sem ela, a Blade · `must`

**Dado** que tenho `crm.access_contact_login`
**Quando** abro `/crm/all-contacts-login` com a flag `useV2CrmLogins` ligada
**Então** recebo Inertia `Crm/Logins/Index` — e com a flag desligada, ou com `?classico=1`,
recebo a view `crm::contact_login.all_contacts_login`.

Status: 🧪

---

## UC-CRMLOG-02 · Só logins de contato do meu negócio · `must` `[T0]`

**Dado** um login de contato no meu negócio, outro no negócio vizinho e um usuário da equipe
**Quando** abro a lista
**Então** vejo só o login de contato do meu negócio.

Status: 🧪

---

## UC-CRMLOG-03 · A senha nunca chega à tela · `must` `[T0]`

**Dado** um login de contato com senha gravada
**Quando** a tela pede a lista
**Então** nenhuma linha traz `password` nem `remember_token`.

Status: 🧪

---

## UC-CRMLOG-04 · Sem a permissão de login de contatos é barrado · `must`

**Dado** que não tenho `crm.access_contact_login`
**Quando** abro `/crm/all-contacts-login`
**Então** recebo 403.

Status: 🧪

---

## UC-CRMLOG-05 · Filtro por contato e busca por nome ou usuário · `should`

**Dado** logins de dois contatos do meu negócio
**Quando** filtro por um contato, ou busco pelo usuário do outro
**Então** a lista mostra só o login correspondente.

Status: 🧪
