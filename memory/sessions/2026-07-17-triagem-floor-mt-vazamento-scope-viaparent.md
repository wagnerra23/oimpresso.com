---
date: "2026-07-17"
topic: "Triagem floor multi-tenant CT100 (cont. #4454) → vazamento cross-tenant Tier 0 no ScopeByBusinessViaParent (fail-open desde Wave 7); fix por reflection + 3 PRs mergeados"
authors: [W, C]
prs: [4474, 4475, 4476]
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0101-tests-business-id-1-nunca-cliente
---

# Triagem floor multi-tenant → vazamento Tier 0 no ScopeByBusinessViaParent

## TL;DR
Continuando a triagem de #4454 (Fiscal), triei os 4 testes multi-tenant do floor
nightly CT100 rodando **cada um no CT100** (§5 — não afirmar sem rodar). Veredito:
Compras e Financeiro **passam** (não-vazamento; o vermelho do Financeiro era assert
OTel stale, não-MT); Ponto era **artefato** (config-override morto + FK biz=99
ausente); e o do **Jana**, ao armar o biz=99 que a FK mascarava, fez a asserção
rodar e **VAZAR** — descobri um **vazamento cross-tenant Tier 0 real** no
`ScopeByBusinessViaParent`. Escalei antes de tocar (R10), [W] aprovou, corrigi,
provei no CT100, mergeei. **3 PRs em main.**

## Método (o que a triagem provou)
Pra cada teste: (1) ler o global scope + de onde lê o business_id; (2) comparar com
como o teste seta sessão/auth; (3) **RODAR no CT100**; (4) canário as duas direções.

| Teste | Sweep dizia | Realidade (CT100) |
|---|---|---|
| Compras/MultiTenantTest | gap real em `contacts`? | **5/5 verde** — nada a fazer |
| Financeiro/MultiTenantIsolationTest | artefato de sessão? | teste MT **passa**; vermelho = `d9_otel_span` (assert de conteúdo stale `fluxo.projetar`→`.render`, Wave 17) |
| Ponto/Wave27 | mecanismo diferente | D2.A1 artefato (`config('multi_tenant.business_id_override')` que **nada lê** + no-op sem auth); A4/A5 FK 1452 (biz=99 ausente) |
| Jana/EntitiesFilhas | schema/child | FK 1452 mascarava; **armei biz=99 → asserção rodou e FALHOU com vazamento** |

## O vazamento Tier 0 (o achado)
`ScopeByBusinessViaParent::apply()` **nunca aplicava** o `whereHas` de tenancy.
Lia `$model->businessParentRelation`, mas a property é **`protected`** → lida de
FORA (da classe Scope) cai no `__get` do Eloquent → `getAttribute` → **NULL**
(não `'conversa'`/`'meta'`). `property_exists` dava `true` (por isso parecia
certo), mas o VALOR era null → `if (! $relation) return` → early-return sem filtro
→ **fail-open**.

**Prova (CT100, `Auth::login` biz=1 + session):**
- ANTES: `SQL = select * from jana_mensagens` (sem whereHas) · `LEAKED=YES`
- DEPOIS: `SQL = … where exists (… jana_conversas.business_id = ?)` · `LEAKED=NO`
- Mecanismo reproduzido em Mensagem/Sugestao/MetaApuracao: `property_exists=1 · via_arrow(__get)=NULL · via_reflection='conversa'/'meta'`.

**Raio:** 17 entities usando `BelongsToBusinessViaParent` — Jana (Mensagem,
Sugestao, MetaApuracao, MetaFonte, MetaPeriodo + 5 Mcp), Ponto (EscalaTurno),
Essentials (6 — folha/RH). **Consumidor EXPLORÁVEL:** `PeriodosController::update/destroy`
(`MetaPeriodo::where('meta_id', $metaId)->findOrFail($id)` com IDs crus da rota,
sem gate; `resource` route sob grupo `/ia` só-auth → IDOR cross-tenant update/**delete**).
Protegidos: `MetasController::show` (gate `Meta::findOrFail`), `ADS SkillsController`
(whereHas manual), `ChatController`/AI-drivers (`$conversa` já escopado).
**Latente desde Wave 7 (2026-05-16)** — o `MultiTenantIsolationComprehensiveTest`
só checa contrato **estrutural** (trait presente, scope booted), nunca o filtro em
runtime, então passava verde por cima do buraco.

## Fix (mergeado, provado)
`resolveRelation()` lê a property por **Reflection** (independe da visibilidade),
cacheada por classe — corrige os 17 entities num ponto só; `whereHas`/superadmin
inalterados. O `EntitiesFilhasMultiTenantViaParentTest` (arma biz=99 via
`DatabaseTransactions`) vira o regression-test e entra na allowlist do `jana-pest.yml`.

## PRs (todos mergeados, merge = [W] R10)
- **#4474** — fix Tier 0 do scope + regression Jana + âncora jana-pest → `4f9fe79598`
- **#4475** — Ponto Wave27 (biz=99 + reframe D2.A1) + **novo `ponto-pest.yml`** (Ponto não tinha lane MySQL de PR) → `bbe5f72f8b`
- **#4476** — Financeiro D9 needle → `f6377c85a9`

## Lições / caveats
1. **`protected` prop lida via `$model->x` dentro de Scope = NULL silencioso** (Eloquent `__get`). Custou este vazamento. Candidato a §5/proibicoes.
2. **Teste estrutural (trait presente/scope booted) NÃO prova runtime** — só o teste que RODA a query com dado cross-tenant pega o fail-open.
3. **FK-1452 mascarando asserção** — teste que morre no insert (biz=99 ausente) nunca chega no `expect`; o "vermelho" parecia infra, escondia bug de produto.
4. **Gate novo (ADR 0298 teto)**: workflow novo exige `terminal`+`anchor`+`promote_by` no `gates-registry.json`, não só nome+classe (grandfather).
5. **Base stale**: staleness gerou falso-positivo em `baseline-tamper-guard`+`module-grades-gate` (advisory); resolvido com `gh pr update-branch` (merge, NÃO force-push — o hook bloqueou force-push, corretamente).

## Follow-ups (não feitos — deliberado)
1. Auditoria per-site do gate de tenant nos 14 consumers Essentials + `PeriodosController` (gate explícito além do backstop restaurado). Folha/RH.
2. Registrar a lição #1 no §5 de `memory/proibicoes.md` (PR próprio).

## Pointers
- Fix: [ScopeByBusinessViaParent.php](../../Modules/Jana/Scopes/ScopeByBusinessViaParent.php) · [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)
- Precedente: #4454 (Fiscal cockpit MT — mesmo espírito, causa diferente)
