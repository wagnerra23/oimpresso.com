---
id: resources-js-pages-recurring-billing-planos-create-casos
casos: Criar plano de assinatura · /recurring-billing/planos/novo
irmaos: Create.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o plano nasce aqui, e o valor com que ele nasce é o valor que vai faturar todo ciclo.
owner: wagner
last_run: "2026-09-08"
last_run_ci: "2026-09-08 (PR tokenizacao da superficie) — REVALIDACAO ANALITICA, suite NAO executada. Por que: Pest so roda no CT 100 (proibicao Tier 0) e o checkout de la nao contem esta mudanca, entao rodar ali produziria recibo de OUTRO codigo. O que a mudanca E: 518 trocas de `className` (cor crua -> token do DS: bg-white->bg-card, text-stone-*->text-foreground/muted-foreground, ring/border-stone-*->ring/border-border). Zero linha de logica, controller, service ou query. MEDIDO, nao lido: (a) grep por `className|bg-white|text-stone|oklch|css|tailwind|style=` nos 14 arquivos de teste citados por estes casos.md devolveu 0 hits em TODOS, com controle positivo (8 a 33 asserts casam por arquivo, logo o grep funciona); (b) as cores de STATUS ficaram INTACTAS — o diff toca emerald/amber/rose em 2 pontos e ambos sao vizinhanca neutra (`border-stone-300`->`border-border` ao lado de um `focus:ring-rose-100` preservado, e o cinza do icone NAO-pinado ao lado de um `fill-amber-500` preservado), o que mantem o UC-RBSUB-07 ("Larissa/Wagner leem a lista por cor") valido; (c) contraste medido no runtime antes/depois: identico (425 passam AA / 27 reprovam / pior 1.00 nos dois estados). Isto NAO substitui execucao: o Status continua vindo do manifesto G-7, que a lane do PR alimenta. ---- REGISTRO ANTERIOR: last_run 2026-07-28."
---

# Casos de Uso & Aceite — Criar plano (`/recurring-billing/planos/novo`)

> **Âncora:** `CU-RB-01` (cadastrar plano de assinatura) do
> [SDD §6.1](../../../../../memory/requisitos/RecurringBilling/SDD-cobranca-recorrente-v1.0.md).
> Os UC derivam do **contrato**, nunca do `.tsx`.
>
> ⚠️ **Força do veredito:** rodam na **nightly CT100**, **não no PR**, **não bloqueiam merge**.
> Status **🧪, nunca ✅** — não rodei nada (CT 100, [ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

## Rastreabilidade

| UC | Caso de uso | Prio | CU | Teste | Status |
|----|-------------|------|----|-------|--------|
| UC-RBPNC-01 | Plano nasce no business da sessão com slug derivado do nome | must `[T0]` | `CU-RB-01` 1 | `Wave6PlanCrudTest` | 🧪 |
| UC-RBPNC-02 | Slug é único **por business** — nunca global | must `[T0]` | `CU-RB-01` 1 | `Wave6PlanCrudTest` | 🧪 |

---

## UC-RBPNC-01 · O plano nasce no business da sessão com slug derivado · `must` `[T0]`
- **Persona:** Wagner cadastra "Mensalidade Gold" e não quer digitar slug.
- **Aceite:** Dado o formulário preenchido com nome, valor e ciclo (slug em branco) · Quando submeto
  `POST /recurring-billing/planos` · Então o plano é criado com `business_id` **da sessão** (nunca do
  request) e `slug` **auto-gerado a partir do nome**, e volto pra lista com flash de sucesso.
- **Teste:** [`Wave6PlanCrudTest`](../../../../../Modules/RecurringBilling/Tests/Feature/Wave6PlanCrudTest.php)
  — `R-RB-WAVE6-1 — store cria plano biz=1 com slug auto-gerado`.
- **Contrato:** `CU-RB-01` item 1 + `Create.charter.md` §Goals (*"slug auto-derivado de name se vazio"*).
- **Regressão que defende:** o `business_id` vir do payload (vetor Tier 0 nº 1 — plano plantado em
  outro tenant) ou o slug nascer vazio, quebrando a URL pública futura que o `Edit.charter.md` avisa.
- **Status: 🧪**

---

## UC-RBPNC-02 · O slug é único por business, nunca global · `must` `[T0]`
- **Persona:** dois businesses diferentes têm, cada um, um plano chamado "Mensal". Os dois têm
  direito ao slug `mensal`.
- **Aceite:** Dado um plano `mensal` já existente no meu business · Quando tento criar outro com o
  mesmo slug **no meu business** · Então é recusado. E o mesmo slug **em outro business** continua
  livre — a unicidade é escopada, não global.
- **Teste:** [`Wave6PlanCrudTest`](../../../../../Modules/RecurringBilling/Tests/Feature/Wave6PlanCrudTest.php)
  — `R-RB-WAVE6-6 — store rejeita slug duplicado per business (unique scoped)`.
- **Contrato:** `CU-RB-01` item 1 + [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** um índice `UNIQUE(slug)` global — o 2º cliente do SaaS não conseguiria
  cadastrar um plano com nome comum. É o bug de multi-tenancy que só aparece com o segundo cliente.
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** `setup_fee >= 0` e `indice_reajuste ∈ {IPCA, IGP-M, none}` validados no
  `StorePlanRequest` — **a DoD da US-RB-001 exige, e as colunas não existem** (`CU-RB-01` item 6; o
  próprio SPEC declara `Implementado em: _parcial_`). Vira UC junto com a migration.
- **[BACKLOG]** `ciclo=custom` exige `ciclo_dias` preenchido (campo condicional do charter).
- **[BACKLOG]** `fiscal_cfop` obrigatório quando `fiscal_type=NFe`; `fiscal_servico` quando NFS-e.
- **[BACKLOG]** Erros de validação aparecem inline por campo, sem perder o que foi digitado.

---

## Refs

- Charter (lei): [`Create.charter.md`](Create.charter.md)
- SDD (âncora): [`SDD-cobranca-recorrente-v1.0.md`](../../../../../memory/requisitos/RecurringBilling/SDD-cobranca-recorrente-v1.0.md) §6.1 `CU-RB-01`
- SPEC (US): US-RB-001 (DoD com os gaps declarados)
- Símbolos: `PlanController@store` · `StorePlanRequest`
- Irmãos do mesmo CU: [`Index.casos.md`](Index.casos.md) · [`Edit.casos.md`](Edit.casos.md)
- Gate: `scripts/casos-coverage-guard.mjs` ([ADR 0264](../../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md))
