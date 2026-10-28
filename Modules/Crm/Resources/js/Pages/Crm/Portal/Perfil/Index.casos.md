---
id: modules-crm-pages-crm-portal-perfil-index-casos
casos: Crm · Portal do contato · Perfil · /contact/contact-profile
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do contato logado + critério de aceite verificável (Dado/Quando/Então)
por_que: é escrita feita por um terceiro com login. Errar a lista permitida deixa o contato mudar o próprio cadastro fiscal; errar o escopo deixa mudar o de outro.
owner: wagner
last_run: "2026-10-27"
last_run_ci: "_pendente_ — o trio nasce na thread Crm/19. O veredito por UC entra no manifesto quando a lane verticais-pest rodar; até lá o Status é ⬜."
---

# Casos de Uso & Aceite — Portal do contato · Perfil (`/contact/contact-profile`)

> **Âncora:** `prototipo-ui/cowork/Wagner/handoff-crm/Portal.casos.md` (UC-CRMPO-04, 05 — o aceite vem de lá, não do `.tsx`)
> e o [charter](Index.charter.md). Os dois escopos andam juntos: `business_id` da sessão **e** `crm_contact_id` do usuário.

---

## UC-CRMPO-04 · Meu cadastro fiscal não muda pelo portal · `must` `[T0]`

**Dado** o contato A
**Quando** a tela posta em `contact-profile-update` `name`, `tax_number`, `contact_id`, `business_id` e `type` alterados junto com e-mail e telefone
**Então** só e-mail, telefone, pessoa de contato e endereço são gravados · e os outros cinco ficam como estavam ·
**E** o contato B do mesmo negócio não muda.

Status: ⬜ — teste escrito, ainda não rodou (o veredito sai da lane verticais-pest do PR #10041).

---

## UC-CRMPO-05 · Troco a minha senha, e só a minha · `must`

**Dado** a senha atual certa e as duas novas iguais **Então** a senha do **usuário logado** muda ·
**Com** a atual errada **Então** a tela recebe erro no campo da senha atual e nada muda ·
**E** um `user_id` de outro no corpo é ignorado.

Status: ⬜ — teste escrito, ainda não rodou (o veredito sai da lane verticais-pest do PR #10041).

> Divergência declarada: o caso de origem diz `422`. Em formulário Inertia a recusa é redirect com erro de validação
> na sessão; o teste prova o erro no campo e o hash intacto (§5 2026-09-05, "302 nos dois caminhos").
