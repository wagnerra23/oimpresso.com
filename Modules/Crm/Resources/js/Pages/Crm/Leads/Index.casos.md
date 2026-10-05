---
id: modules-crm-pages-crm-leads-index-casos
casos: Crm · Leads · /crm/leads
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a lista de leads mostra contatos em prospecção com celular, e-mail e documento. Errar o escopo vaza contato de outro negócio ou de colega que não devia ver; errar o desvio Inertia × DataTables entrega JSON cru no lugar da tela.
owner: wagner
last_run: "2026-10-01"
last_run_ci: "_pendente_ — o trio nasce na thread Crm/02. O veredito por UC entra no manifesto quando a lane verticais-pest rodar; até lá o Status é 🧪."
---

# Casos de Uso & Aceite — Crm · Leads (`/crm/leads`)

> **Âncora:** o [charter](Index.charter.md) (Goals e Anti-hooks), a ficha da thread Crm/02 (lista
> + show em drawer PT-02) e as permissões que a tela Blade já exigia
> (`crm.access_all_leads` / `crm.access_own_leads`). Os UCs não derivam do `.tsx`.

---

## UC-CRMLD-01 · A lista responde em Inertia · `must`

**Dado** que tenho `crm.access_all_leads`
**Quando** abro `/crm/leads`
**Então** recebo Inertia `Crm/Leads/Index`, não a view `crm::lead.index`.

Status: 🧪

---

## UC-CRMLD-02 · Visita Inertia não vira JSON do DataTables · `must`

**Dado** que o browser manda `X-Inertia` e `X-Requested-With` juntos
**Quando** a tela pede a lista
**Então** recebe a prop `leads` — e um ajax **sem** `X-Inertia` (o da tela clássica) continua
recebendo o JSON do DataTables.

Status: 🧪

---

## UC-CRMLD-03 · Lead de outro negócio não aparece · `must` `[T0]`

**Dado** um lead no meu negócio e outro em outro negócio
**Quando** abro a lista
**Então** vejo o meu e não vejo o do outro negócio.

Status: 🧪

---

## UC-CRMLD-04 · Quem só vê os próprios vê só os atribuídos a ele · `must`

**Dado** que tenho só `crm.access_own_leads`
**Quando** abro a lista
**Então** vejo os leads atribuídos a mim e não os do colega — e quem tem `crm.access_all_leads`
vê o do colega.

Status: 🧪

---

## UC-CRMLD-05 · Sem permissão é barrado · `must`

**Dado** um usuário sem nenhuma das duas permissões
**Quando** abre `/crm/leads`
**Então** recebe 403 — e o mesmo teste prova que quem tem a permissão recebe 200.

Status: 🧪

---

## UC-CRMLD-06 · O drawer só abre lead do escopo da lista · `must` `[T0]`

**Dado** um lead meu, um lead de outro negócio e um contato do meu negócio que é cliente
**Quando** peço o detalhe de cada um (`?lead=ID`)
**Então** recebo o meu lead, e `null` para o de outro negócio e para o cliente.

Status: 🧪

---

## UC-CRMLD-07 · `?classico=1` e o kanban devolvem a tela Blade · `should`

**Dado** que conversão, kanban e "local" ainda vivem na Blade
**Quando** abro `/crm/leads?classico=1` ou `/crm/leads?lead_view=kanban`
**Então** recebo a view `crm::lead.index`.

Status: 🧪

---

> **Thread Crm/06 — formulário de lead.** Âncora: D2 ([W] 2026-10-01, *"o form de lead reusa
> `Cliente/Create`"*) e os campos que a tela Blade (`contact.create` com `types = lead`) já
> pedia: fonte, estágio de vida e atribuído a. O store é o mesmo `LeadController@store`.

## UC-CRMLD-08 · "Adicionar" abre o Cliente/Create em modo lead · `must` `[T0]`

**Dado** que tenho `crm.access_all_leads` e há fonte e usuário no meu negócio e no vizinho
**Quando** abro `/crm/leads/create`
**Então** recebo Inertia `Cliente/Create` com tipo `lead`, título "Novo lead" e destino
`/crm/leads`; e as opções de fonte e de atribuído trazem só as do meu negócio.

Status: 🧪

---

## UC-CRMLD-09 · Salvar grava lead com fonte, estágio e atribuído · `must`

**Dado** o formulário de lead preenchido com nome, fonte, estágio e atribuído
**Quando** salvo
**Então** o contato é gravado no meu negócio com `type = lead` (mesmo que o payload diga
outro tipo), com a fonte, o estágio e o atribuído escolhidos, e volto para `/crm/leads`.
Sem nome, o campo acusa erro.

Status: 🧪

---

## UC-CRMLD-10 · Atribuído, fonte e estágio de outro negócio não entram · `must` `[T0]`

**Dado** um payload com usuário, fonte e estágio de outro negócio
**Quando** salvo o lead
**Então** o lead é gravado no meu negócio sem atribuído, sem fonte e sem estágio.

Status: 🧪
