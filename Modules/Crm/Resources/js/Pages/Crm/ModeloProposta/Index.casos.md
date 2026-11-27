---
id: modules-crm-pages-crm-modeloproposta-index-casos
casos: Crm · Modelo de proposta · /crm/proposal-template
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o modelo de proposta é o e-mail comercial que a empresa manda aos contatos. Errar o escopo mostra o modelo de outra empresa; errar a permissão deixa quem não pode editar ou mandar a proposta.
owner: felipe
last_run: "2026-11-27"
last_run_ci: "_pendente_ — o trio nasce na Onda 1 do RUNBOOK-modelo-proposta. O veredito por UC entra quando a lane verticais-pest rodar; até lá o Status é 🧪."
---

# Casos de Uso & Aceite — Crm · Modelo de proposta (`/crm/proposal-template`)

> **Âncora:** o [charter](Index.charter.md) e a tabela de paridade do RUNBOOK-modelo-proposta (§10). Os UCs não
> derivam do `.tsx`.

---

## UC-CRMMOD-01 · Com a flag ligada, a tela responde em Inertia · `must`

**Dado** a flag `useV2CrmModeloProposta` ligada para o meu negócio
**Quando** abro `/crm/proposal-template`
**Então** recebo Inertia `Crm/ModeloProposta/Index`; com `?classico=1`, recebo a view `crm::proposal_template.index`.

Status: 🧪

---

## UC-CRMMOD-02 · O modelo exibido é o da minha empresa · `must`

**Dado** um modelo na minha empresa e outro numa empresa vizinha
**Quando** abro a tela
**Então** vejo o assunto do meu modelo e nunca o da vizinha.

Status: 🧪

---

## UC-CRMMOD-03 · O corpo chega como texto, sem HTML · `must`

**Dado** um modelo cujo corpo tem marcação do editor (`<p>`, `<br>`, `<strong>`)
**Quando** abro a tela
**Então** a prop `modelo.corpo` traz o texto, com quebra de linha no lugar dos blocos, sem nenhuma tag.

Status: 🧪

---

## UC-CRMMOD-04 · Sem modelo, a tela avisa e só quem pode cadastrar vê "Adicionar" · `must`

**Dado** que minha empresa não tem modelo
**Quando** abro a tela
**Então** a prop `modelo` é nula; `pode_editar` é verdadeira só com `crm.add_proposal_template`.

Status: 🧪

---

## UC-CRMMOD-05 · Sem o módulo CRM na assinatura, a tela não abre · `must`

**Dado** um usuário sem superadmin numa empresa sem `crm_module`
**Quando** abro `/crm/proposal-template`
**Então** recebo 403, como na Blade.

Status: 🧪
