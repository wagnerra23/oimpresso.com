---
date: "2026-06-01"
hour: "16:10 BRT"
slug: bankstatementline-model-reimpl-2045
topic: "Re-impl #2045 — Model BankStatementLine + BusinessScope na Conciliação (origem OFX) sobre ADR 0236 → prod (#2094)"
tldr: "Tirei o #2045 do backlog. Model BankStatementLine (BusinessScope NET) cobre SÓ a origem OFX; API (fin_extrato_lancamentos) e o bulk upload seguem DB::table de propósito. Dispatcher conciliacaoTable('ofx') retorna BankStatementLine::query()->toBase() → filtro de tenant embutido no Query\\Builder sem mudar callers. 21 Pest verdes no CT 100 (biz=1). PHPStan ratchet pegou 1 falso-positivo do trait (baseline +1). Mergeado #2094. 2 gotchas de teste CT 100 catalogadas no RUNBOOK."
duration: "~2h"
authors: [CC, Wagner]
session: frosty-greider-83ab2f
---

# Re-impl #2045 — BankStatementLine + BusinessScope na Conciliação (OFX)

## Estado MCP no momento
- Cycle: **CYCLE-08 Receita — Onda A**. Este foi um item de **backlog** registrado no handoff anterior (15:10), não US do MCP: *"#2045 BankStatementLine → backlog (Wagner, baixo ROI — já há scope manual; chip spawnado)"*.
- Decisões base: **ADR 0236** (extrato+conciliação 2 origens, já em prod), **ADR 0093** (Tier 0 multi-tenant), **ADR 0101** (testes biz=1 / adversário biz=99), **ADR 0208** (PHPStan ratchet vs baseline).

## O que aconteceu
Wagner pediu pra re-implementar o #2045 (fechado como superseded): trocar as ~10 ocorrências de `DB::table('fin_bank_statement_lines')` no `ConciliacaoController` por uma Model `BankStatementLine` com `BusinessScope` global scope — defesa em profundidade Tier 0 (hoje o isolamento depende 100% de cada query repetir `where('business_id')`).

**Base correta = `origin/main`, não `feat/staging-ct100`.** O working tree local (e o clone do staging no CT 100) estavam em `feat/staging-ct100` (dc256cb83), **atrás do `main`** — sem `reabrir`/audit-log/match_score nem os Pest de conciliação (que vieram nos #2083/#2087). O Model + Pest originais do #2045 (`origin/fix/financeiro-bankstatementline-model`, commit B3 `fa03622ad`) eram PRE-ADR-0236 → o **Model e o teste eram reaproveitáveis, o controller do B3 não**. Re-implementei sobre o controller atual (2 origens).

## Design (o miolo)
- **`BankStatementLine`** (BusinessScope + SoftDeletes + casts) cobre **só a origem OFX** (`fin_bank_statement_lines`). A origem **API** (`fin_extrato_lancamentos`, schema/tabela distintos) e o **bulk upload** (`insertOrIgnore` + dedupe `pluck`) seguem `DB::table` **de propósito** (caminho de ingestão / fora do escopo da Model OFX) — documentado no código.
- **Dispatcher `conciliacaoTable('ofx')` retorna `BankStatementLine::query()->toBase()`.** O `toBase()` chama `applyScopes()` ANTES de devolver o `Query\Builder` → o `BusinessScope` (`where business_id = session('user.business_id')`) e o `SoftDeletes` (`whereNull deleted_at`) ficam **embutidos** no builder. Resultado: o NET de tenant existe mesmo que um caller futuro esqueça o `where('business_id')`, **sem mudar o contrato** (`match`/`ignorar`/`reabrir`/`sugerirParaLinha` continuam usando `->where()->update()/first()` num `Query\Builder` que devolve `stdClass`). `BusinessScopeImpl` lê a MESMA chave `session('user.business_id')` que o controller → o scope embutido == o where explícito (redundância segura).
- `index`/`sugerirMatches` (OFX) usam `BankStatementLine::where(...)->...->toBase()->get()` → `stdClass` cru → **payload Inertia byte-for-byte** (casts da Model NÃO reformatam datas/decimais no wire).
- `where('business_id')` explícito **mantido** em todos os pontos (2ª camada, padrão do módulo). Comportamento idêntico (tabela append-only: soft-delete nunca ocorre no fluxo).

## CT 100 — validação (biz=1 dogfooding, NUNCA cliente)
**21 Pest / 87 assertions verdes.** Como o staging vivo está em `feat/staging-ct100` (sem os arquivos de origin/main) e tem WIP local (Otel), **não toquei o branch do staging**:
- **Model test** (`BankStatementLineModelTest`, 4/4): extraí os 2 arquivos novos pro clone via `git show origin/<branch>:<path>` (untracked, removidos depois) e rodei no container vivo. Prova: BusinessScope isola cross-tenant + casts roundtrip (decimal:2/decimal:4/date).
- **Regressão do controller** (17/17: MatchScore, AuditReabrir incl. cross-tenant 404, LeExtratoApi incl. `index` 2-origens + `match api tier0`, UploadDedupe): rodei num **git worktree descartável** (`/tmp/bsl-wt` no branch completo) num container one-off, com `vendor`/`storage`/`bootstrap-cache` montados do staging. Provou que o refactor preserva as 2 origens + Tier 0.

## PHPStan ratchet (ADR 0208) — 1 falso-positivo, baseline +1
CI vermelho: `Access to an undefined property Illuminate\Database\Eloquent\Model::$business_id` em `Models/Concerns/BusinessScope.php:33` **in context of BankStatementLine**. O trait tipa o param da closure `creating()` como base `Model`; larastan resolve `business_id` dos Models antigos via schema-scan mas **não do Model novo** → cai pra base Model. `@property` no Model **não resolve** (o erro é no param tipado base Model, não no Model). É runtime-válido (provado pelos Pest). O repo não usa `@property` nos Models do módulo e tem 242 linhas `business_id` no baseline → **baseline +1** é o caminho canônico (a própria mensagem do ratchet manda isso). Commit separado (`1adb29a5`), verificado "No errors" no CT 100 antes de subir.

## Correção de rota minha (precisão)
Em algum momento afirmei *"ADR 0101 não existe"* — **errado**. Meu `Glob` rodou no CWD do worktree quebrado (`frosty-greider-83ab2f` não tem `memory/`). A **ADR 0101 existe** (`0101-tests-business-id-1-nunca-cliente.md`, há colisão de número com a `0101-sistema-charter-capterra`) e define explicitamente: *"Cross-tenant adversário: `business_id = 99` (número alto improvável de existir como tenant real)"*. Confirmei no banco do staging: **não existe business 99** (69 tenants, ids 1–213 com gaps; biz=1 = WR2 Sistemas, biz=4 = ROTA LIVRE). O teste está ADR-0101-compliant.

## Artefatos
| PR | O quê | Validação |
|---|---|---|
| **[#2094](https://github.com/wagnerra23/oimpresso.com/pull/2094)** (merged `06b1f2760`) | Model `BankStatementLine` + refactor OFX no `ConciliacaoController` + `BankStatementLineModelTest` + baseline +1 | 21 Pest CT 100 · CI verde · merge squash (admin; CI-gate no lugar de review, ADR 0241) |

## Gotchas catalogadas (RUNBOOK-staging-ct100.md §"Rodar testes de um branch")
1. **FK `business_id → business` vs ADR 0101 biz=99:** o teste original do #2045 **inseria row** em `business_id=99` → viola a FK no CT 100 (não há business 99 no clone). Fix: inserir só no **biz=1** e **flipar a sessão** pro 99 (valor de sessão não precisa de row). Vale pra qualquer tabela com FK em `business`.
2. **Rodar Pest/PHPStan de um worktree isolado:** precisa (a) **não** passar `-e APP_ENV=staging` (senão o phpunit.xml não força `testing` → CSRF 419 nos POST + 409 nos GET Inertia); (b) **copiar `public/build-inertia/manifest.json`** pro worktree (senão `HandleInertiaRequests::version()` diverge do helper `inertiaGet` → 409); (c) montar `bootstrap/cache` **gravável** (senão phpstan/artisan: "Please provide a valid cache path").

## Próximos / observações
- Itens fora de escopo (intactos, ainda em `DB::table`): `BackfillExtratoOfxCommand` (CLI, sem sessão → scope não aplicaria) e `FinanceiroServiceProvider`.
- Anomalia observada: arquivos não-relacionados (`RecurringBilling/.../recurringbilling.php`, `Fiscal/Nfe-visual-comparison.md`) apareceram modificados no working tree do worktree durante a sessão (provável hook/processo de regeneração em paralelo) — **não** entraram em nenhum commit meu.
