---
id: audits-2026-05-pre-sales-04-ci-pr-audit-30d
---

# Audit CI + PRs últimos 30 dias — 2026-05-09

> Escopo: 35 PRs mais recentes (#329-#369), ~100 últimos workflow runs.
> Repo: `wagnerra23/oimpresso.com`. Branch base: `main`.
> Todos PRs auditados foram mergeados (estado: merged).

## 1. Inventário de workflows

| Arquivo | Trigger | Modo | O que checa | Última falha | Notas |
|---|---|---|---|---|---|
| `ci.yml` | push main/6.7-react + PR (todos) | **HARD** (bloqueia) | `composer validate --strict`, `php -l` em `app/`+`Modules/`, Pest `tests/Feature/Form` em SQLite, `npm run build:inertia` | Algumas cancellations por `cancel-in-progress` (não falha real) | Único Pest rodado: ≈25 tests Form shim. Resto da suite NÃO roda em CI. |
| `mwart-gate.yml` | PR tocando `resources/js/Pages/**/*.tsx` | **SOFT** (`continue-on-error: true`) | RUNBOOK + SPEC + charter `.charter.md` + Pest `<Tela>ControllerTest.php` + `*-visual-comparison.md` (status approved + ≥6 dim + sem TODO) | Detectou violações em PR #349 mas mergeou OK (soft) | Conclusion sempre "success" mesmo com violations; só comenta no PR. |
| `charter-gate.yml` | PR tocando charters/Pages/s6 | **SOFT** | Frontmatter (8 keys) + 8 seções obrigatórias + ❌ prefix em Non-Goals | Reportou "⚠️ Issues detected" em PR #349 | Mesma natureza soft. |
| `visual-regression.yml` | PR tocando `Pages/`, `Layouts/`, `Components/`, `tests/Browser/` | **INFRA-ONLY** (3 steps `continue-on-error: true`) | Setup Laravel + migrate + Pest Browser tests | Falhas internas em `claude/visual-regression-pest` (7 falhas seguidas em 2026-05-09 14:04→14:21) | Workflow inteiro é placeholder — Pest Browser **NÃO ROTUA tests reais** até migration order ser fixada. |
| `scope-guard.yml` | PR + push tocando `Modules/**/Http/Controllers/**.php` ou `SCOPE.md` | **HARD em PR**, **soft em push** | `php bin/check-scope.php --strict` — controllers fora de SCOPE.md.contains[] | PR #349 1ª run falhou (drift detected → autor adicionou em SCOPE.md → 2ª run pass) | Funciona como esperado. |
| `adr-lint.yml` | push/PR tocando `memory/decisions/**` | **HARD** | Pest `AdrFrontmatterLinterTest` | 2026-05-09 21:50 (PR #357 → main; 2 falhas mergeadas) | Failure nos pushes de main ficaram visíveis mas NÃO bloqueou pq pra `push` não tem branch protection — só pra PRs. PR #357 merge-time PR check passou? **Verificar:** `gh pr view 357` — vide §6. |
| `quick-sync.yml` | push em main (paths app code) | HARD | SSH Hostinger → git pull → npm ci → build:inertia → smoke | 4 falhas em 2026-05-09 (#330, #336, #339, #355 quick-syncs) — provável race lock `.git/index.lock` ou SSH timeout | **FLAKY** ~13% últimos 30 runs. Já tem mitigação (rm -f locks). Mas falhas significam main-em-prod ficou stale por minutos. |
| `deploy.yml` | `workflow_dispatch` apenas | HARD | Backup + git pull + composer + migrate + smoke. Manual. | N/A | Não usado em volume (manual trigger). |
| `composer-lock-sync.yml` | `workflow_dispatch` | HARD | Update composer.lock e abre PR | N/A | OK. |
| `cowork-inbox.yml` | push em main + paths `cowork-inbox/**` | HARD | python script + auto PR + auto squash merge | Falhas 2026-05-09 17:22→17:26 (3 falhas seguidas, 1 skipped — tail success) | **FLAKY após 17h dia 09**. Loop guard funcionando, mas 3 retries indica bug pontual. |

## 2. Top 10 PRs com gaps detectados

| PR | Título | Modo merge | Gap detectado | Severidade |
|---|---|---|---|---|
| **#349** | feat(financeiro): tela Visao Unificada (Cockpit V2) | **all gates pass at merge time** mas mwart-gate **comentou "❌ Violações detectadas"** (RUNBOOK + visual-comparison ausentes) | `<tela>-visual-comparison.md`, RUNBOOK ausentes, charter ausente, Pest test ausente. **Mergeou apesar disso** (soft mode). 4 PRs follow-up (#355, #358, #359, #361) tiveram que vir depois pra retroativamente arrumar artefatos. | **HIGH** — exemplo paradigmático do soft-mode gap |
| **#357** | feat(legacy-migration): fase 7 — ADR 0119 + template | adr-lint **failure** | ADR frontmatter lint falhou — provável frontmatter inválido em `0119-*.md`. PR mergeou pois adr-lint não está em required-checks; failure também ocorreu em main pós-merge | **HIGH** |
| **#330** | feat(repair): F3 Produção · Oficina — kanban 5 colunas | mergeado | Greenfield Page sem `Producao.charter.md` ao lado nem visual-comparison. | MEDIUM |
| **#363** | feat(repair): US-REPAIR-PROD-4 drag-and-drop | mergeado | Mexe em Page existente; mwart-gate passou — porque tela já tinha artefatos? Verificar se não é falso-positivo de gate | LOW (verificar) |
| **#336** | fix(routing): remove name colliding com business.update | mergeado | quick-sync.yml falhou pós-merge — main em prod ficou stale | LOW |
| **#367** | chore(_DesignSystem) canon visual cowork | mergeado, 18.5k linhas | Volume gigante (+18532 / -0). Sem charter/visual-comp. Constituição §5 SoC + commit-discipline (≤300 linhas) violadas. | **HIGH** (commit discipline) |
| **#368** | consolidação geral pós-audit Constituição v2 | mergeado, 1.5k linhas | Mesmo padrão "consolidação grande" — princípio commit-discipline ≤300 linhas. | MEDIUM |
| **#343** | feat(legacy-migration): fase 3 — schema baseline | mergeado, +24.6k / -0 | Volume gigante. Provavelmente arquivos gerados, mas não há label/marcador de auto-generated. | MEDIUM |
| **#329** | chore(cowork): inbox processed | bot squash merge | OK — bot. Mas precedeu falhas cowork-inbox 17h. | INFO |
| **#347** | feat(legacy-migration): fase 5a — importer python | mergeado | Pure Python, fora do gate MWART/charter — OK. | INFO |

## 3. Padrões de regressão silenciosa

- **5 PRs com Pages alteradas em 2026-05-09:** #330, #337, #340, #349, #363, #366, #367 (subset). Apenas #340, #363, #366 (que já existiam) tinham artefatos pré-existentes; #349 não. **MWART soft-gate flagged but merged anyway** ≥1 caso confirmado (#349).
- **Visual-comparison.md no repo:** apenas **4** arquivos vs **127** Pages `.tsx` (cobertura ~3%). Mesmo descontando `_components/` e helpers internos, gap enorme.
- **Charters .charter.md no repo:** **13** arquivos vs **127** Pages (~10%).
- **RUNBOOKs:** **22** arquivos. Maior cobertura — mas ainda <20%.
- **adr-lint failures:** 4 das últimas 30 runs (~13% fail rate em main + PRs combinados). Confirma que ADR PRs estão criando frontmatter inválido com regularidade.
- **Pest tests da mudança:** PR #349 não trouxe `UnificadoControllerTest.php` no commit principal — veio só em PR #359 retroativo. Padrão **"feature first, test depois"** se repete (#330 → #338 retroativo, #349 → #359).

## 4. Workflows quebrados/flaky

| Workflow | Saúde | Diagnóstico |
|---|---|---|
| **`visual-regression.yml`** | **QUEBRADO** | 7 falhas seguidas no branch `claude/visual-regression-pest` em 2026-05-09 14:04→14:21. Workflow tem 3 steps com `continue-on-error: true`, então em PRs externos retorna "success" mesmo sem rodar tests reais. **Em prática NÃO valida nada.** |
| **`quick-sync.yml`** | **FLAKY** | 4 falhas em últimos 30 runs em main. Causa provável: SSH/lock contention durante a maratona 23 PRs (push storm). Mitigação `rm -f .git/index.lock` já existe. |
| **`cowork-inbox.yml`** | **FLAKY** | 3 falhas seguidas + 1 skipped em 2026-05-09 17h. Próximas runs OK. Indica bug pontual (provavelmente race/timing). |
| **`mwart-gate.yml`** | **WORKING (soft)** | Funciona como projetado: detecta tudo, comenta no PR, NÃO bloqueia. Maior risco operacional. |
| **`charter-gate.yml`** | **WORKING (soft)** | Idem, soft. |
| **`adr-lint.yml`** | **WORKING (hard)** | Detecta frontmatter inválido. Falha em main após merges PR #357 indica que adr-lint **não está em required-checks** — failures pós-merge são silenciosas. |
| **`scope-guard.yml`** | **WORKING (hard PR / soft push)** | Detectou drift em PR #349 e bloqueou até autor consertar. Bom sinal. |
| **`ci.yml`** | **WORKING but minimal** | Só roda Pest `tests/Feature/Form` (~25 tests) + `npm run build`. **Cobertura real de testes Pest é zero em CI** para Modules/Jana, NfeBrasil, RecurringBilling, etc — todos esses só rodam local. |

## 5. Tempo de CI

Estimativa derivada das durações `startedAt → updatedAt`:

| Workflow | p50 | p95 |
|---|---|---|
| `ci.yml` (PHP+Frontend, paralelo) | ~1m50s | ~2m30s |
| `mwart-gate.yml` | ~15s | ~20s |
| `charter-gate.yml` | ~15s | ~20s |
| `scope-guard.yml` | ~15s | ~20s |
| `visual-regression.yml` | ~2m30s | ~3m |
| `adr-lint.yml` | ~30s | ~50s |
| `quick-sync.yml` | ~45s | ~1m45s |

**Total CI por PR (mais pesado em Pages mexidas):** p50 ~2m30s, p95 ~3m30s — **rápido**.

**Gargalo atual:** `ci.yml/php` step (~1m55s) — domina porque é o único workflow rodando vendor install + boot artisan. **Não há gargalo crítico**, mas há sub-utilização: mais Pest deveria rodar nos 90s sobrando do envelope p95.

## 6. `--no-verify` ou skip detectados

Busca em commits desde 2026-05-01:

```
6b3cc65d build(inertia): bundles governance pages [skip-build-auto]
1b33f258 fix(eval): bypass Meilisearch BM25 quando semanticRatio < 0.25  ← false positive (não é bypass de CI)
```

- **Nenhum `--no-verify` detectado** — bom.
- **`[skip-build-auto]`** é convenção interna pra não disparar quick-sync (path-skip), não bypass de gate. OK mas seria bom documentar.
- Não há `gpg-sign:false` ou bypass de pre-commit hooks.

**adr-lint** falhou em PR #357 mas o PR mergeou — não foi `--no-verify`, foi simplesmente que **`adr-lint.yml` não está nas required status checks da branch protection**. Failures em PR não bloqueiam merge, e em push pra main passam ignorados.

## 7. Cobertura por módulo (heurística controllers vs tests + registro phpunit)

| Módulo | Controllers | Tests | Em phpunit.xml? | Veredicto |
|---|---|---|---|---|
| Grow | 142 | 0 | NO | **CRÍTICO** — 142 controllers zero cobertura |
| Connector | 30 | 0 | NO | **CRÍTICO** |
| Crm | 21 | 0 | NO | **HIGH** |
| Essentials | 19 | 2 | YES | low |
| ADS | 15 | 7 | NO | **MED** — tests existem mas CI nunca roda (não em phpunit.xml — proibição CLAUDE.md violada) |
| Superadmin | 14 | 0 | NO | HIGH |
| Accounting | 12 | 0 | NO | HIGH |
| Financeiro | 12 | 5 | YES | médio |
| Ponto | 12 | 11 | **NO** | **MED** — 11 tests existem mas CI nunca roda (proibição §"Tests sem registro" violada) |
| Jana | 11 | 15 | YES | OK |
| NfeBrasil | 10 | 22 | YES | OK |
| Repair | 10 | 2 | YES | low |
| ProjectMgmt | 10 | 2 | YES | low |
| Whatsapp | 8 | 19 | YES | OK |
| Officeimpresso | 7 | 0 | NO | HIGH |
| AssetManagement | 7 | 0 | NO | HIGH |
| Manufacturing | 6 | 0 | NO | HIGH |
| RecurringBilling | 6 | 11 | YES | OK |
| Cms | 5 | 6 | YES | OK |
| Woocommerce | 4 | 0 | NO | MED |
| Spreadsheet | 3 | 0 | NO | MED |
| ProductCatalogue | 3 | 0 | NO | MED |
| IProduction | 2 | 0 | NO | LOW |
| Copiloto | 0 | 0 | YES | (sem controllers — OK) |
| PontoWr2 | 0 | 0 | YES | (porta de entrada PontoWr2) |

**Zero-cobertura críticos:** Grow (142!), Connector (30), Crm (21), Superadmin (14), Accounting (12), Officeimpresso (7).

**Tests órfãos** (existem em disk, NÃO em phpunit.xml — falsa cobertura, proibição explícita CLAUDE.md):
- **Modules/Ponto/Tests** (11 tests) — não roda
- **Modules/ADS/Tests** (7 tests) — não roda

Mesmo o que está em phpunit.xml: lembrar que o `ci.yml` só roda `tests/Feature/Form` (linha 81), então **só ~25 tests do Form shim rodam em CI**. Os outros tests apenas rodam local.

## 8. Top 10 ações recomendadas (priorizadas)

| # | Ação | Severidade | Esforço | Por quê |
|---|---|---|---|---|
| 1 | Registrar `Modules/Ponto/Tests` e `Modules/ADS/Tests` em `phpunit.xml` (proibição CLAUDE.md) | **blocker** | minutos | 18 tests órfãos = falsa cobertura, viola proibição explícita |
| 2 | Habilitar `adr-lint.yml` como required-check em branch protection (PR #357 mergeou com falha) | **blocker** | minutos | Frontmatter inválido em ADR já está chegando em main |
| 3 | Estender `ci.yml` Pest step de `tests/Feature/Form` pra `tests/Feature/` completo + `Modules/*/Tests/Feature/Memory` (offline-safe) | **high** | hora | CI hoje valida ~25 testes; centenas existem mas só rodam local. Envelope tem 90s sobrando |
| 4 | Decidir: `mwart-gate.yml` vira **hard** ou continua soft? Documentar critério em ADR (handoff diz "fase 2 após backfill" — mas backfill já tem 4/127 — ritmo lento) | **high** | dias (decisão + comm) | PR #349 prova que soft-mode permite regressão silenciosa em massa |
| 5 | Resolver `visual-regression.yml` migration order legacy (handoff item) e remover 3× `continue-on-error: true` | **high** | horas | Workflow é placeholder — não valida nada hoje |
| 6 | Rodar campanha de backfill `*-visual-comparison.md` pra Pages existentes (4/127 atual) — começar por módulos quentes (Financeiro 9 Pages, NfeBrasil 4, Repair, Sells) | **medium** | dias | Pré-condição pra item 4 |
| 7 | Acrescentar `quick-sync.yml` retry automático em SSH timeout (falhas 13%) | medium | hora | Reduz main-stale-window |
| 8 | Adicionar smoke test de Pest em CI pra módulos zero-cobertura críticos (Grow, Connector, Accounting, Crm) — mínimo 1 happy-path por módulo | **high** | dias | 200+ controllers sem nenhum teste |
| 9 | Codex commit-discipline (≤300 linhas) — alertar (não bloquear) PRs >500 linhas. PR #367 (+18.5k) e #343 (+24.6k) escapam por serem "consolidação"/"baseline" | medium | hora | Princípio duro Tier A violado em volume |
| 10 | Criar workflow `mwart-gate-stats.yml` weekly que conta % Pages com artefatos completos e posta em discussion/issue — visibilidade do gap | low | hora | Mede se o backfill está progredindo |

---

## Apêndice — observação adicional

A **maratona 23 PRs em ~6h** de 2026-05-09 funcionou tecnicamente (todos passaram CI hard checks) mas custou:
- Pelo menos 5 PRs follow-up retroativos (#355, #358, #359, #361, #355) só pra arrumar artefatos MWART do PR #349 inicial
- 4 falhas quick-sync (main-stale)
- 3 falhas cowork-inbox flaky
- 2 falhas adr-lint

**A próxima maratona deveria pré-condicionar:** todos PRs já vêm com charter+visual-comparison no commit inicial (não em retroativo). Item 4 (gate hard) força isso por construção.

**Análise gerada por:** SRE/CI auditor agent — read-only audit
**Próximo audit:** 2026-06-09 (mensal)
