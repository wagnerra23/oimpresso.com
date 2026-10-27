---
id: modules-crm-pages-crm-funil-index-casos
casos: Crm · Funil de vendas · /crm/funil
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do vendedor + critério de aceite verificável (Dado/Quando/Então)
por_que: o funil mostra valores de negócio e clientes. Errar o escopo vaza negócio de outra empresa; errar a etapa final deixa um ganho voltar a aberto e distorce a previsão.
owner: wagner
last_run: "2026-10-26"
---

# Casos de Uso & Aceite — Crm · Funil de vendas (`/crm/funil`)

> **Âncora:** SPEC Crm US-CRM-092 (critérios 1-6), [ADR 0441](../../../../../../../memory/decisions/0441-funil-de-vendas-sai-da-depreciacao-emenda-0301.md)
> e o [charter](Index.charter.md). Os UCs não derivam do `.tsx`.

---

## UC-CRMFN-01 · Só quem tem permissão abre o funil · `must`

**Dado** um usuário sem `crm.funil.ver` · **Quando** abre `/crm/funil` · **Então** recebe 403; e **Dado**
`crm.funil.ver` · **Então** recebe Inertia `Crm/Funil/Index` com as 6 etapas, sem poder editar.

- **Teste:** `Modules/Crm/Tests/Feature/CrmFunilContratoTest.php` — `UC-CRMFN-01 · sem permissão o funil é 403; com crm.funil.ver abre as 6 etapas`.

Status: 🧪 — CT 100 MySQL 2026-10-26: 5 passed (53 assertions) no arquivo.

---

## UC-CRMFN-02 · Só os negócios da minha empresa · `must` [T0]

**Dado** negócios na minha empresa e na outra · **Quando** a lista carrega · **Então** só aparecem os
meus, com título, etapa, valor e cliente.

- **Teste:** mesmo arquivo — `UC-CRMFN-02 · lista os negócios da própria empresa, nunca os da outra [T0]`.

Status: 🧪

---

## UC-CRMFN-03 · Criar negócio · `must`

**Dado** `crm.funil.editar` · **Quando** crio um negócio com cliente do cadastro e valor · **Então** ele
nasce em Lead, comigo como responsável, valor com 2 casas; cliente de outra empresa ou valor negativo
são recusados; sem `crm.funil.editar`, 403.

- **Teste:** mesmo arquivo — `UC-CRMFN-03 · criar negócio exige crm.funil.editar, nasce em Lead e recusa cliente de outra empresa`.

Status: 🧪

---

## UC-CRMFN-04 · Mover de etapa · `must` [T0]

**Dado** um negócio meu · **Quando** arrasto ou escolho outra etapa · **Então** a etapa muda e a
transição fica gravada; Ganho e Perdido são finais (não voltam); negócio de outra empresa dá 404.

- **Teste:** mesmo arquivo — `UC-CRMFN-04 · mover de etapa grava a transição; ganho não volta; negócio de outra empresa dá 404 [T0]`.

Status: 🧪

---

## UC-CRMFN-05 · Indicadores do funil · `should`

**Dado** negócios em várias etapas · **Quando** o resumo carrega · **Então** a soma por etapa e a previsão
do mês contam só os negócios da minha empresa.

- **Teste:** mesmo arquivo — `UC-CRMFN-05 · o resumo soma por etapa e prevê o fechamento do mês só com negócios da empresa`.

Status: 🧪

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** O item "Funil" aparece no menu do CRM só para quem tem `crm.funil.ver` (E2E de alcance).
- **[BACKLOG]** Soltar em Ganho/Perdido abre a confirmação antes de gravar (E2E do quadro).

## Trilha do tempo
- 2026-10-26 · [C] trio nascido com a tela (US-CRM-092 · ADR 0441).
