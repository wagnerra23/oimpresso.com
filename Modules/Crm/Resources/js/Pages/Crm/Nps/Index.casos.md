---
id: modules-crm-pages-crm-nps-index-casos
casos: Crm · Painel NPS · /crm/nps
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do dono/comercial + critério de aceite verificável (Dado/Quando/Então)
por_que: o painel mostra o que clientes disseram, com nome. Errar o escopo mostra cliente de outra empresa; errar a conta distorce o NPS que o dono acompanha.
owner: wagner
last_run: "2026-10-27"
---

# Casos de Uso & Aceite — Crm · Painel NPS (`/crm/nps`)

> **Âncora:** SPEC Crm US-CRM-097 (critério 6) e o [charter](Index.charter.md). Os UCs não derivam do `.tsx`.

---

## UC-NPSP-01 · Só quem tem permissão abre o painel · `must`

**Dado** um usuário sem `crm.nps.ver` · **Quando** abre `/crm/nps` · **Então** recebe 403; com a permissão,
recebe Inertia `Crm/Nps/Index` com o período padrão de 30 dias.

- **Teste:** `Modules/Crm/Tests/Feature/CrmNpsPainelContratoTest.php` — `UC-NPSP-01 · sem crm.nps.ver é 403; com a permissão abre em 30 dias`.

Status: 🧪

---

## UC-NPSP-02 · Só as respostas da minha empresa, detratores primeiro · `must` [T0]

**Dado** respostas 10, 9, 8 e 3 na minha empresa e uma resposta 0 em outra · **Quando** o painel carrega ·
**Então** o NPS é +25 (2 promotores − 1 detrator sobre 4), a lista tem 4 linhas começando pela nota 3, e a
resposta da outra empresa não aparece.

- **Teste:** mesmo arquivo — `UC-NPSP-02 · resumo e lista só com a empresa da sessão, detratores primeiro`.

Status: 🧪

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** O item "NPS" aparece no menu do CRM só para quem tem `crm.nps.ver` (E2E de alcance).

## Trilha do tempo
- 2026-10-27 · [C] trio nascido com a tela (US-CRM-097).
