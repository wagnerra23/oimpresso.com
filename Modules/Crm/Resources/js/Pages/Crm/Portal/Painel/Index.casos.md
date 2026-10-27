---
id: modules-crm-pages-crm-portal-painel-index-casos
casos: Crm · Portal do contato · Início · /contact/contact-dashboard
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do contato logado + critério de aceite verificável (Dado/Quando/Então)
por_que: é a única superfície que um terceiro acessa com login. Errar o escopo mostra a dívida de outro contato — incidente LGPD, não bug de tela.
owner: wagner
last_run: "2026-10-27"
last_run_ci: "run 40498496667 (verticais-pest): UC-CRMPO-01/02/03 passaram, UC-CRMPO-18 quebrou e foi corrigido no PR #10023."
---

# Casos de Uso & Aceite — Portal do contato · Início (`/contact/contact-dashboard`)

> **Âncora:** `prototipo-ui/cowork/Wagner/handoff-crm/Portal.casos.md` (UC-CRMPO-01, 02, 03, 18 — o texto
> de aceite vem de lá, não do `.tsx`), o [charter](Index.charter.md) e a regra mestre de valor
> (`memory/proibicoes.md`). Os dois escopos andam juntos: `business_id` da sessão **e** `crm_contact_id` do usuário.

---

## UC-CRMPO-01 · Vejo o meu saldo, e só o meu · `must` `[T0]`

**Dado** o contato A com venda final de 100,00 (pago 40,00), o contato B do mesmo negócio com venda de 900,00, e o contato A' no negócio 99
**Quando** A abre `/contact/contact-dashboard`
**Então** "a pagar" = 60,00 e "em vendas" = 100,00 · e nenhum valor de B ou de A' aparece em nenhuma prop.

Status: 🧪 — passou no CI, lane verticais-pest, run 40498496667 (lane: 1102 passed, 8209 assertions).

---

## UC-CRMPO-02 · Os blocos seguem o meu tipo · `must`

**Dado** um contato `customer` **Então** o payload traz `vendas` e não traz `compras` ·
**Dado** `supplier` **Então** só `compras` · **Dado** `both` **Então** os dois ·
**E** `abertura` só vem quando existe saldo de abertura.

Status: 🧪 — passou no CI, lane verticais-pest, run 40498496667 (lane: 1102 passed, 8209 assertions).

---

## UC-CRMPO-03 · O portal abre na casca do contato · `must`

**Dado** o contato logado **Quando** abre `/contact/contact-dashboard` com a flag ligada
**Então** recebe Inertia `Crm/Portal/Painel/Index` com `shell.portal`, e a resposta **não** traz `shell.menu` do ERP ·
**E** com a flag desligada, ou com `?classico=1`, recebe a view `crm::dashboard.index`.

Status: 🧪 — passou no CI, lane verticais-pest, run 40498496667 (lane: 1102 passed, 8209 assertions).

---

## UC-CRMPO-18 · Cada um na sua porta · `must` `[T0]`

**Dado** um operador (sem `user_customer`) **Quando** abre `/contact/contact-dashboard` **Então** é recusado ·
**Dado** o contato A **Quando** abre `/crm/leads` **Então** é recusado e nada do ERP renderiza.

Status: ⬜ — quebrou no run 40498496667 (o operador recebe 404, não 403; o teste esperava 403). Teste corrigido para aceitar a recusa como 403 ou 404; ainda não rodou de novo.

---

## Backlog de casos

- **[BACKLOG]** UC-CRMPO-17 · Aviso no Início quando uma OS do contato está pronta ou com orçamento aguardando. Fora desta thread: o `main` não tem a derivação de fase/orçamento da OS para o portal — nasce com a aba Assistência (thread 25, UC-COS-24).
