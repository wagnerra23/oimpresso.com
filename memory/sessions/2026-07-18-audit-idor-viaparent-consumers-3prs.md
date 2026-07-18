---
date: "2026-07-18"
topic: "Follow-up #4474: auditoria per-site dos consumers BelongsToBusinessViaParent → 5 IDOR cross-tenant de WRITE (backstop não cobre INSERT) fechados em 3 PRs (Jana Periodos, KB Fontes, Essentials SalesTarget/Shift)"
authors: [W, C]
prs: [4512, 4513, 4514]
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0101-tests-business-id-1-nunca-cliente
  - 0298-teto-de-governanca-anti-proliferacao-gates
  - 0314-poda-gates-onda-2-lei-fusoes
---

# Follow-up #4474 — auditoria per-site dos consumers ViaParent

## TL;DR
O #4474 consertou o `ScopeByBusinessViaParent` (fail-open). Este follow-up auditou
**per-site** os consumers HTTP das 17 entities que usam `BelongsToBusinessViaParent`,
partindo do princípio de que o backstop de scope é **defesa-em-profundidade** e
**NÃO cobre INSERT** (só SELECT). Achei **5 IDOR cross-tenant de WRITE** sem gate
explícito e fechei em 3 PRs por módulo, cada um com teste cross-tenant biz=1 vs
biz=99 rodando **de verdade** em CI (não falsa cobertura). Todos os required verdes.

## Achados (write IDOR — backstop de scope não protege INSERT)

| # | Site | Vetor | PR |
|---|---|---|---|
| 1 | `Jana/PeriodosController::store` | `MetaPeriodo::create(['meta_id'=>$metaId])` id cru → cria período na meta de outro biz | #4512 |
| 2 | `Jana/PeriodosController::update`/`destroy` | só backstop (o explorável nomeado na task) | #4512 |
| 3 | `KB/FontesController::update` | `MetaFonte::updateOrCreate` id cru → **injeta `driver:sql`+config na meta de outro biz** (roda na apuração com o business_id da vítima) — CRÍTICO | #4513 |
| 4 | `Essentials/SalesTargetController::saveSalesTarget` | `EssentialsUserSalesTarget::create` com user_id cru do body | #4514 |
| 5 | `Essentials/ShiftController::postAssignUsers` | `Shift` validado com `->find()` sem abort + user_id (chave) cru | #4514 |

**Fix (padrão único):** validar o parent por lookup escopado (`Meta::findOrFail` /
`User::where('business_id',$biz)->findOrFail` / `Shift::...->findOrFail` + `abort_unless`
das chaves de user) **ANTES** de tocar o filho. Parents têm `HasBusinessScope` → id
cross-tenant 404 antes de qualquer escrita. É o gate que os FormRequests do Periodos
**já documentavam** mas o controller nunca executava.

**Seguros (não mexi):** MetasController::show, ChatController::escolher, ApuracaoService
(id derivado de model/job), Document* (Wave 15 já bem-gated), ToDoController
(scopedQueryForUser + self-scoped), DataController/Payroll/Dashboard (id de model/self).
Ponto EscalaTurno só tem seeder. Mcp* = superfície admin/superadmin (outro perfil).

**Residual (fora de escopo, não vira gate quebrado):** a permissão `copiloto.fontes.edit`
que o docblock do FontesController afirma **não está registrada** em lugar nenhum —
NÃO adicionei `can:` (daria 403 pra todos). Registrar+seedar+assignar fica pra follow-up.

## Lições transferíveis (a saga de validação valeu mais que o fix)

1. **As lanes Pest de CI usam ALLOWLIST CURADA — teste novo NÃO roda sozinho.**
   `jana-pest.yml` roda 5 arquivos hardcoded; meu teste passou "verde" sem rodar
   (falsa cobertura). A própria lane manda: "cada novo teste MySQL-only é adicionado
   AQUI". Fix: registrei os testes Jana na allowlist + criei `essentials-pest.yml`
   (Essentials não tinha lane MySQL de PR — só CT100 nightly). **Sempre confirmar que
   o teste EXECUTOU (contagem/nome no log), não só que o job ficou verde.**

2. **A rule PHPStan Tier-0 (T-AP-2/T-AP-8) conta queries Eloquent POR MÉTODO vs baseline.**
   `Meta::findOrFail` inline no `update()` virou a 2ª query → "(2)" ≠ baseline "(1)" =
   regressão. Comment com o token `business_id`/`BusinessScope` **não basta** (inline `//`
   não é escaneado). Fix real: extrair o gate num **helper privado** (o `store/update`
   mantêm 1 query) — foi por isso que #4512 passou, não pelo token.

3. **Endpoints RH do Essentials vivem sob `/hrm/`, não `/essentials/`.** `web.php:58`
   `Route::prefix('hrm')` é IRMÃO de `prefix('essentials')` (ambos no grupo de middleware
   sem prefixo). URL certa: `/hrm/save-sales-target`, `/hrm/shift/assign-users`. Errei o
   nível 2× — o que fechou foi diagnóstico em CI (dump de rotas + body do 404 com
   APP_DEBUG). **Route-404 mascara gate-404 quando o teste espera 404 (cross-tenant).**

4. **Rotas `/essentials` rodam `SetSessionData` DEPOIS do auth** → reconstroem
   `user.business_id` do usuário autenticado. Setar session à mão furava (bloco não-stale
   → reconstrução pulada). Padrão certo: `flush + actingAs` (EssentialsTestCase/TodoTest).
   (Diferente de `/ia`, onde SetSessionData roda ANTES do auth e a session manual é
   necessária.)

5. **Workflow novo exige registro em `gates-registry.json` no MESMO PR** (memory-health,
   ADR 0298 "a torneira, não o balde"): `nome+classe+terminal+promote_by` (advisory não
   nasce eterno — janela como ponto-pest, 2026-07-31).

## Estado no fechamento
3 PRs abertos, **todos required verdes** (PHPStan · Pest Unit · Pest Jana/Essentials MySQL
com os testes rodando de verdade: cross-tenant→404/403, positivo→302). Advisory vermelhos
não-bloqueadores: `module-grades-gate` (ADR 0314, os 3) + `dup-detector` (#4513, falso-
positivo: helper privado `assertMetaDoTenant` com mesmo nome em 2 controllers isolados).
**Merge pendente [W] (R10)** — nenhum required tocado além do PHPStan (verde).

CT100 direto indisponível na sessão (Tailscale SSH 502 — precisa re-auth manual [W]);
validação foi via CI (lanes MySQL biz=1/biz=2, que é o gate equivalente).
