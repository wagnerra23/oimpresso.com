---
id: resources-js-pages-recurring-billing-planos-edit-casos
casos: Editar plano de assinatura · /recurring-billing/planos/{id}/editar
irmaos: Edit.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: mudar o valor do plano muda o que TODAS as assinatura ligadas a ele vão faturar no próximo ciclo.
owner: wagner
last_run: "2026-07-28"
---

# Casos de Uso & Aceite — Editar plano (`/recurring-billing/planos/{id}/editar`)

> **Âncora:** `CU-RB-01` (cadastrar/manter plano) do
> [SDD §6.1](../../../../../memory/requisitos/RecurringBilling/SDD-cobranca-recorrente-v1.0.md).
> Os UC derivam do **contrato**, nunca do `.tsx`.
>
> 🔴 **Esta é a tela mais `[V0]` do módulo, e não parece.** Ela é um CRUD trivial na aparência — mas
> `invoice.valor = plan.valor` (SDD §5.3 F2 passo 4). **Salvar aqui redefine o valor da próxima
> fatura de toda assinatura ligada ao plano**, sem nenhuma confirmação. Qualquer mudança neste fluxo
> cai sob a REGRA MESTRE ([proibicoes](../../../../../memory/proibicoes.md)): dupla confirmação por 2
> caminhos + tabela antes→depois + OK humano.
>
> ⚠️ **Força do veredito:** rodam na **nightly CT100**, **não no PR**, **não bloqueiam merge**.
> Status **🧪, nunca ✅** — não rodei nada.

## Rastreabilidade

| UC | Caso de uso | Prio | CU | Teste | Status |
|----|-------------|------|----|-------|--------|
| UC-RBPNE-01 | Editar valor/ciclo preserva os demais campos | must `[V0]` | `CU-RB-01` 2 | `Wave6PlanCrudTest` | 🧪 |
| UC-RBPNE-02 | Editar plano de outro business é 404 | must `[T0]` | `CU-RB-01` 5 | `Wave6PlanCrudTest` | 🧪 |

---

## UC-RBPNE-01 · Editar valor/ciclo preserva os demais campos · `must` `[V0]`
- **Persona:** Wagner reajusta a mensalidade de R$ 150 pra R$ 165 e muda de mensal pra trimestral.
  O `fiscal_type`, o `trial_days`, a descrição e o `slug` **não podem** se perder no caminho.
- **Aceite:** Dado um plano existente com todos os campos preenchidos · Quando submeto
  `PUT /recurring-billing/planos/{id}` alterando **só** `valor` e `ciclo` · Então os dois mudam e
  **todos os outros campos permanecem exatamente como estavam**.
- **Teste:** [`Wave6PlanCrudTest`](../../../../../Modules/RecurringBilling/Tests/Feature/Wave6PlanCrudTest.php)
  — `R-RB-WAVE6-2 — update altera valor + ciclo preservando outros campos`.
- **Contrato:** `CU-RB-01` item 2 + `Edit.charter.md` §Goals (form pré-preenchido via prop `plan`).
- **Regressão que defende:** um `update($request->all())` que zere campos ausentes do payload. Como o
  `fiscal_type` decide **se sai NFe**, perdê-lo silenciosamente quebra o diferencial do módulo
  (`CU-RB-09`); e perder o `ciclo` muda **quando** o dinheiro entra.
- **Status: 🧪**

---

## UC-RBPNE-02 · Editar plano de outro business é 404 · `must` `[T0]`
- **Persona:** qualquer tenant. O ID do plano é um inteiro sequencial — adivinhar é trivial.
- **Aceite:** Dado um plano de biz=1 · Quando um usuário biz=99 abre `edit` ou submete `update` com
  aquele ID · Então **404** — nem lê, nem grava.
- **Teste:** [`Wave6PlanCrudTest`](../../../../../Modules/RecurringBilling/Tests/Feature/Wave6PlanCrudTest.php)
  — `R-RB-WAVE6-5 — cross-tenant edit() biz=99 acessando plano biz=1 dispara 404`.
- **Contrato:** `CU-RB-01` item 5 + `CU-RB-10` +
  [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
  Teste biz=1 vs biz=99, **nunca biz=4** ([ADR 0101](../../../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md)).
- **Regressão que defende:** o pior caso deste módulo — alterar o preço do plano de **outra empresa**
  e mudar o faturamento dela no ciclo seguinte, sem que ninguém veja.
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Editar plano **soft-deleted** é impossível (`findOrFail` ignora trashed) — o charter
  declara como anti-pattern; nenhum teste prova.
- **[BACKLOG]** Mudar o `slug` avisa o risco (é parte da URL pública futura) — hoje é só copy do charter.
- **[BACKLOG]** 🔴 **O que ninguém defende hoje:** salvar um `valor` novo **não avisa quantas
  assinaturas ativas serão refaturadas com o valor novo no próximo ciclo**. A REGRA MESTRE exige
  "apresentar o impacto antes de aplicar" — e esta tela não apresenta nada. **Vira UC quando [W]
  decidir o remédio** (confirmação com contagem × versionar preço × congelar valor na assinatura);
  são remédios que se anulam, e escolher é decisão de produto.

---

## Refs

- Charter (lei): [`Edit.charter.md`](Edit.charter.md)
- SDD (âncora): [`SDD-cobranca-recorrente-v1.0.md`](../../../../../memory/requisitos/RecurringBilling/SDD-cobranca-recorrente-v1.0.md) §6.1 `CU-RB-01` · §5.3 F2
- SPEC (US): US-RB-001
- Símbolos: `PlanController@edit` · `PlanController@update` · `UpdatePlanRequest`
- Irmãos do mesmo CU: [`Index.casos.md`](Index.casos.md) · [`Create.casos.md`](Create.casos.md)
- Gate: `scripts/casos-coverage-guard.mjs` ([ADR 0264](../../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md))
