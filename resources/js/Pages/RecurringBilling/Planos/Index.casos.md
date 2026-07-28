---
id: resources-js-pages-recurring-billing-planos-index-casos
casos: Planos de assinatura · /recurring-billing/planos
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o plano é a fonte do valor que fatura — apagar o errado quebra assinatura viva.
owner: wagner
last_run: "2026-07-28"
---

# Casos de Uso & Aceite — Planos (`/recurring-billing/planos`)

> **Âncora:** `CU-RB-01` (cadastrar plano) do
> [SDD §6.1](../../../../../memory/requisitos/RecurringBilling/SDD-cobranca-recorrente-v1.0.md).
> Os UC derivam do **contrato**, nunca do `.tsx`.
>
> **O que torna esta tela `[V0]` mesmo sendo "cadastro":** o `valor` do plano é **o valor que fatura**
> (SDD §5.3 F2 passo 4 — `invoice.valor = plan.valor`). Mexer no plano mexe no dinheiro do próximo
> ciclo de **todas** as assinaturas ligadas a ele.
>
> ⚠️ **Força do veredito:** os testes rodam na **nightly CT100**, **não no PR**, e **não bloqueiam
> merge** (zero linhas do módulo em `.github/ci-sqlite-pest.list`). Status **🧪, nunca ✅** — não rodei.

## Rastreabilidade

| UC | Caso de uso | Prio | CU | Teste | Status |
|----|-------------|------|----|-------|--------|
| UC-RBPLN-01 | Excluir plano é **soft delete**, nunca hard | must | `CU-RB-01` 4 | `Wave6PlanCrudTest` | 🧪 |
| UC-RBPLN-02 | Plano com assinatura ativa **não** pode ser excluído | must `[V0]` | `CU-RB-01` 3 | `Wave6PlanCrudTest` | 🧪 |
| UC-RBPLN-03 | Plano de outro business é invisível e intocável | must `[T0]` | `CU-RB-01` 5 | `Wave6PlanCrudTest` | 🧪 |

---

## UC-RBPLN-01 · Excluir plano é soft delete · `must`
- **Persona:** Wagner desativa um plano antigo. Faturas históricas continuam apontando pra ele — o
  registro **não pode** sumir do banco.
- **Aceite:** Dado um plano **sem** assinatura ativa · Quando clico Excluir · Então ele some da lista
  com flash de sucesso, mas continua no banco como **soft-deleted** (`SoftDeletes`), preservando o
  vínculo das faturas já emitidas.
- **Teste:** [`Wave6PlanCrudTest`](../../../../../Modules/RecurringBilling/Tests/Feature/Wave6PlanCrudTest.php)
  — `R-RB-WAVE6-3 — destroy soft-deleta plano sem assinatura ativa`.
- **Contrato:** `CU-RB-01` item 4 + `Planos/Index.charter.md` §UX Anti-patterns
  (*"Hard delete por engano — Plan tem SoftDeletes trait"*).
- **Regressão que defende:** trocar `delete()` por `forceDelete()` deixaria faturas órfãs — e o
  histórico fiscal aponta pro plano.
- **Status: 🧪**

---

## UC-RBPLN-02 · Plano com assinatura ativa não pode ser excluído · `must` `[V0]`
- **Persona:** Wagner tenta faxinar o catálogo e escolhe um plano que ainda cobra 12 clientes.
- **Aceite:** Dado um plano com ≥1 `Subscription` **ativa** (`active`/`trialing`/`past_due`) · Quando
  peço a exclusão · Então **422** com mensagem clara e a **contagem exata** de assinaturas — nunca
  stacktrace, nunca sumiço silencioso.
- **Teste:** [`Wave6PlanCrudTest`](../../../../../Modules/RecurringBilling/Tests/Feature/Wave6PlanCrudTest.php)
  — `R-RB-WAVE6-4 — destroy retorna 422 quando plano tem Subscription ativa`.
- **Contrato:** `CU-RB-01` item 3 + `Planos/Index.charter.md` §UX Anti-patterns
  (*"Esconder erro de FK/integridade — sempre mostra contagem exata"*).
- **Regressão que defende:** apagar o plano deixa as assinaturas com `plan_id` apontando pra
  soft-deleted → o gerador cai no branch `plan === null` e **para de faturar em silêncio**
  ([SDD §9.1](../../../../../memory/requisitos/RecurringBilling/SDD-cobranca-recorrente-v1.0.md)).
  É por isso que este UC é `[V0]`: a consequência é receita que não entra.
- **Status: 🧪**

---

## UC-RBPLN-03 · Plano de outro business é invisível e intocável · `must` `[T0]`
- **Aceite:** Dado um plano de biz=1 · Quando um usuário biz=99 tenta `edit`, `update` ou `destroy`
  por ID · Então **404** — o `HasBusinessScope` + o `where('business_id', …)` explícito não deixam
  passar.
- **Teste:** [`Wave6PlanCrudTest`](../../../../../Modules/RecurringBilling/Tests/Feature/Wave6PlanCrudTest.php)
  — `R-RB-WAVE6-5 — cross-tenant edit() biz=99 acessando plano biz=1 dispara 404`.
- **Contrato:** `CU-RB-01` item 5 + `CU-RB-10` +
  [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
  Teste biz=1 vs biz=99, **nunca biz=4** ([ADR 0101](../../../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md)).
- **Regressão que defende:** alterar o preço do plano de outra empresa por ID adivinhado. Como o valor
  do plano é o que fatura, isso seria mexer no faturamento alheio.
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** KPIs do header (total / ativos / MRR potencial / distribuição de ciclos) agregam
  corretamente — hoje nenhum teste cobre o `buildKpisPayload` **desta** tela (o teste de KPI que
  existe é o da carteira de assinaturas, `Wave4PresenterIndexTest`).
- **[BACKLOG]** `plans` e `kpis` chegam **deferidos** (`Inertia::defer`), com skeleton — o charter
  promete e nada prova.
- **[BACKLOG]** Busca server-side por nome ou slug com partial reload `only:[plans, kpis]`.
- **[BACKLOG]** Empty state com CTA quando o business não tem plano nenhum.

---

## Refs

- Charter (lei): [`Index.charter.md`](Index.charter.md)
- SDD (âncora): [`SDD-cobranca-recorrente-v1.0.md`](../../../../../memory/requisitos/RecurringBilling/SDD-cobranca-recorrente-v1.0.md) §6.1 `CU-RB-01`
- SPEC (US): US-RB-001
- Símbolo: `PlanController@destroy` (`grep -n "public function destroy" Modules/RecurringBilling/Http/Controllers/PlanController.php`)
- Irmãos do mesmo CU: [`Create.casos.md`](Create.casos.md) · [`Edit.casos.md`](Edit.casos.md)
- Gate: `scripts/casos-coverage-guard.mjs` ([ADR 0264](../../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md))
