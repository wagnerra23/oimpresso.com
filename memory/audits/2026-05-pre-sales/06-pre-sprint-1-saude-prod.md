---
id: audits-2026-05-pre-sales-06-pre-sprint-1-saude-prod
---

# Audit Saúde Prod oimpresso pre-Sprint 1 — 2026-05-10

> Read-only. Branch: `claude/decisao-sem-daas-externo-eliana-estuda`. HEAD: `f810cec1`.
> Escopo: status dos 6 bugs críticos do PR #372 audit + saúde geral pré-Sprint 1.
> Restrição: sem modificar nada. Sem tocar tenancy ([feedback Wagner 2026-05-09](../../../C:/Users/wagne/.claude/projects/D--oimpresso-com/memory/feedback_tenancy_changes_require_pest_local.md)).

---

## Sumário

- **0 dos 6 bugs críticos resolvidos.** 4 abertos, 2 parcialmente mitigados (workflow existe + audit feito; código não mexido).
- **Pode iniciar Sprint 1?** **NÃO sem pre-fix mínimo.** Bug #1 (wipe-DB-via-HTTP) é blocker pra qualquer demo a prospect com CISO; bug #2 (octane+mcp em composer.json) é blocker de governança Tier 0 ADR 0062. Bug #4 (tests órfãos) bloqueia confiança em CI antes de tocar schema multi-vertical.
- **Pre-fix mínimo recomendado:** ~1h de trabalho (3 fixes cirúrgicos) — ver §Recomendação.

---

## Status dos 6 bugs críticos (PR #372 audit)

### Bug #1 — wipe-DB-via-HTTP (`POST /install/install-alternate`)

**Status: ❌ ABERTO**

- Código intacto: `routes/install_r.php:19` ainda registra `Route::post('/install/install-alternate', …)` **sem nenhum middleware** (apenas controller-side check `file_exists('.env')` que **não impede o wipe** — só redireciona se .env ausente).
- `app/Http/Controllers/Install/InstallController.php:265-286` `installAlternate()` chama `runArtisanCommands()` (linhas 252-263) que executa `Artisan::call('migrate:fresh', ['--force' => true])` + `db:seed --force` se `.env` existe.
- Última modificação: commit `cdeb4ce7 rotas` (legacy UltimatePOS, anterior à fork). Nenhum commit recente endereçou.
- Evidência git: `git log -- routes/install_r.php` retorna apenas commits anteriores a 2026 (`307ec310`, `7ab68816`, `cdeb4ce7`, `46419c12`).
- **Risco real:** prospect com pentest descobre em 5 min via `dirb` → demo morta + DB de prod em risco se cliente piloto rodar.

### Bug #2 — `composer.json` `laravel/octane` + `laravel/mcp` em `require`

**Status: ❌ ABERTO**

- `composer.json:26-27` ainda contém:
  ```json
  "laravel/mcp": "^0.7.0",
  "laravel/octane": "^2.15",
  ```
- Viola Tier 0 IRREVOGÁVEL — [ADR 0062 separação runtime Hostinger ≠ CT 100](../../decisions/0062-separacao-runtime-hostinger-ct100.md) + proibição CLAUDE.md §"Ambiente": "Nunca instalar `laravel/mcp` ou `laravel/octane` no Hostinger". `composer install` em produção **arrasta esses pacotes pro Hostinger shared hosting**.
- Último commit em `composer.json`: `6b57f1cf feat(jana): setup Horizon UI CT-only` (PR #312) — não removeu octane/mcp.
- **Risco governança:** prospect que conhece arquitetura Laravel pergunta "vocês rodam Octane no Hostinger shared?" — `composer.json` diz que sim, viola contrato Tier 0.

### Bug #3 — NCM default ausente nos 11 templates tributários

**Status: ❌ ABERTO (mas mitigado em runtime por fallback)**

- Não existe seeder canônico em `database/seeders/Tributario*` ou `Modules/NfeBrasil/database/seeders/` (Glob retornou vazio).
- `Modules/NfeBrasil/Http/Controllers/ConfigDefaultController.php:37` cria default com `'ncm_default' => '00000000'` (placeholder inválido — 8 zeros não é NCM real).
- `Modules/NfeBrasil/Services/NfeService.php:113-120, 263-269` têm fallback que **lança RuntimeException** se `ncm_default` ausente ou zerado: `"Configure ncm_padrao no cadastro do business…"` — runtime não emite NFe sem NCM, mas falha tarde (usuário tenta emitir, recebe erro).
- 11 templates tributários (10 UF + 1 MEI — auto-mem [project_nfebrasil_estado_2026_05_07]) **não persistem `ncm_default`** quando aplicados; depende de business preencher manualmente em `/nfe-brasil/tributacao/config-default`.
- **Risco produto:** smoke SEFAZ biz=1 só passou pq Wagner configurou manual; novo cliente onboarding descobre erro só ao tentar emitir.

### Bug #4 — Tests órfãos `Modules/Ponto/Tests` + `Modules/ADS/Tests` sem phpunit.xml

**Status: ❌ ABERTO**

- `phpunit.xml:13-30` lista 12 suites Module mas **NÃO inclui** `Modules/Ponto/Tests` nem `Modules/ADS/Tests`.
- Evidência tests órfãos:
  - `Modules/Ponto/Tests/Feature/` tem 9 arquivos (AprovacaoTest, BancoHorasTest, DashboardTest, IntercorrenciaAIClassifierTest, ModuleManagerTest, MultiTenantIsolationTest, SpatiePermissionsTest, TelasNavegacaoTest) + 2 Unit (ApuracaoServiceTest, MarcacaoServiceTest) = **11 tests**.
  - `Modules/ADS/Tests/Unit/` tem 7 arquivos (ConfidenceEngineTest, DecisionRouterTest, PolicyEngineTest, RiskEngineTest, PatternLearningWilsonTest, GovernanceRulesDslTest, ContextForTaskActiveTasksTest) = **7 tests**.
- **18 tests órfãos = falsa cobertura** (proibição CLAUDE.md §"Código": "Não criar `Modules/X/Tests/` sem registrar em `phpunit.xml` — testes ficam no repo mas CI nunca roda → falsa cobertura").
- Documentado em [`memory/audits/2026-05-pre-sales/04-ci-pr-audit-30d.md:124-126`](04-ci-pr-audit-30d.md) como "blocker" #1 mas não fixado.

### Bug #5 — `adr-lint.yml` não-required-check em branch protection

**Status: ⚠️ PARCIAL (workflow funciona, branch protection não enforcer)**

- `.github/workflows/adr-lint.yml` existe e roda Pest `AdrFrontmatterLinterTest` (HARD fail no workflow).
- Audit 04-ci-pr-audit-30d.md §6 confirma: PR #357 mergeou com adr-lint **failure** porque "adr-lint.yml não está nas required status checks da branch protection". Failures pós-merge em main são silenciosas.
- Não há evidência de mudança em branch protection (settings GitHub não estão no repo, mas comportamento PR #357 → merge mesmo com fail prova que continua não-required).

### Bug #6 — `mwart-gate.yml` soft (deveria ser HÍBRIDO conforme ADR 0120)

**Status: ⚠️ PARCIAL (gate é soft, ADR 0120 não trata disso)**

- `.github/workflows/mwart-gate.yml:1` declara `name: MWART Gate (soft)`. Linha 65 tem `continue-on-error: true` no step Verify — workflow **comenta no PR mas não bloqueia merge**.
- Audit 04-ci-pr-audit-30d.md §2 confirma comportamento (PR #349 mergeou com violações flagged).
- ADR 0120 (`memory/decisions/0120-supersession-metadata-housekeeping.md` por commit `fdd4944b`) é sobre supersession metadata housekeeping, **não trata de mwart-gate hybrid**. Audit menciona "ADR 0120 proposto" — não confirmado no repo.
- `mwart-gate-hybrid` não foi implementado.

---

## Saúde geral

### `composer.json` / `composer.lock`

- ❌ `composer.json:26-27` viola Tier 0 (octane+mcp).
- ⚠️ `composer.json:53` `stripe/stripe-php: "^7.122"` — major 7 (atual 14+; CVEs 2024-2025 perdidos — audit 03 §K-3).
- ⚠️ `composer.json:13` `automattic/woocommerce: "^3.0"`, `:18` `giggsey/libphonenumber: "^8.12"` — 1 major atrás.
- ⚠️ `composer.json:43` `nwidart/laravel-modules: "^10.0"` — 12.x estável.
- ⚠️ `consoletvs/charts: "^6.5"` — pacote **abandoned em 2024** (audit 03 §8).
- ✅ `laravel/framework: "^13.0"`, `inertiajs/inertia-laravel: "^3.0"`, `laravel/ai: "^0.6.3"` corretos.
- ✅ `composer-lock-sync.yml` workflow existe pra evitar drift; `ci.yml:38` valida `composer validate --strict`.

### Workflows

- ✅ 10 workflows ativos (`ci, deploy, adr-lint, composer-lock-sync, scope-guard, charter-gate, quick-sync, visual-regression, cowork-inbox, mwart-gate`).
- ❌ `visual-regression.yml` é **placeholder quebrado** (3× `continue-on-error: true` — audit 04 §4).
- ❌ `mwart-gate.yml` soft permite regressão silenciosa (PR #349 caso paradigmático).
- ⚠️ `quick-sync.yml` **flaky 13%** (4 falhas em 30 runs — audit 04 §4).
- ⚠️ `ci.yml:81` Pest só roda `tests/Feature/Form` (~25 tests) — cobertura real em CI ≈ 0% pra Modules/Jana, NfeBrasil, Repair etc.

### Modules conformes (8 peças obrigatórias)

- 30 módulos com `module.json` (Glob).
- Não auditei one-by-one as 8 peças por módulo (escopo audit). Audit 04 §7 aponta **6 módulos zero-cobertura críticos** (Grow 142 controllers, Connector 30, Crm 21, Superadmin 14, Accounting 12, Officeimpresso 7).
- ✅ Modules canônicos referência (Jana, NfeBrasil, Repair) têm tests + estão em phpunit.xml.

### Migrations recentes

- 11 migrations 2026 em `database/migrations/`. Última: `2026_05_09_120000_create_jana_health_narratives_table.php`.
- Recentes inspecionadas (samples):
  - `2026_05_07_220000_move_nfe_cert_files_outside_webroot.php` — security fix US-NFE-041 (cert pra fora do webroot) ✅
  - `2026_05_09_120000_create_jana_health_narratives_table.php` — Brain A narrador (US-COPI-099), schema clean com índices ✅
- Nenhuma quebra aparente; nada parece DDL drift.
- ✅ `jana:health-check` `procedure_drift` check existe (proibição CLAUDE.md confirma) — protege contra DDL direto em prod.

---

## Top 5 débitos técnicos a fechar antes Sprint 1

| # | Débito | Bloqueia? | Esforço | Justificativa |
|---|---|---|---|---|
| **1** | **Bug #1 wipe-DB**: adicionar `middleware(['auth', 'superadmin'])` em `routes/install_r.php` (todas rotas exceto `/install-start` zero-state) | **SIM (qualquer demo a prospect com CISO)** | ~10 min | Sprint 1 vai gerar PRs com schema multi-vertical visíveis em prod-staging — qualquer audit externo descobre `/install/install-alternate` antes do schema novo |
| **2** | **Bug #2 octane+mcp**: remover `laravel/octane` + `laravel/mcp` de `composer.json` `require` (mover pra `require-dev` ou `composer-platform-overrides`) e regenerar `composer.lock` | **SIM (governança Tier 0 ADR 0062)** | ~15 min + workflow `composer-lock-sync.yml` | Sem fix, qualquer `composer install` em Hostinger arrasta daemons proibidos. Sprint 1 toca migrations → deploy → composer install → contamina prod |
| **3** | **Bug #4 tests órfãos**: registrar `Modules/Ponto/Tests` + `Modules/ADS/Tests` em `phpunit.xml` (4 linhas — Unit + Feature pra cada) | **SIM (confiança em CI antes de schema multi-vertical)** | ~5 min | 18 tests já existem, basta declarar suites. Sprint 1 vai exigir test-first em schema multi-vertical — começar com base falsa de cobertura é receita pra regressão |
| **4** | **Bug #5 adr-lint required**: marcar `adr-lint.yml` como required-check em branch protection (settings GitHub) | NÃO (mas alta prioridade) | ~5 min UI GitHub | PR #357 mergeou com fail; Sprint 1 vai criar ADRs (multi-vertical schema) — começar com lint não-enforcing causa frontmatter inválido em massa |
| **5** | **Bug #3 NCM default**: criar seeder `Modules/NfeBrasil/database/seeders/TributarioTemplateSeeder.php` que popula `ncm_default` real (49019900 ou genérico setor) nos 11 templates | NÃO (workaround manual existe) | ~30 min | Sprint 1 prepara venda multi-vertical → onboarding cliente novo descobre falha só na 1ª emissão. Risco demo |

---

## Recomendação

**NÃO iniciar Sprint 1 sem pre-fix mínimo.** Sequência sugerida (~1h total):

1. **PR pre-Sprint 1** (cirúrgico, ≤300 linhas conforme commit-discipline):
   - Fix Bug #1: `routes/install_r.php` middleware (10 min)
   - Fix Bug #2: remove `laravel/octane` + `laravel/mcp` de `composer.json:26-27`, regenera lock (15 min)
   - Fix Bug #4: registra 4 suites Ponto+ADS em `phpunit.xml:13-30` (5 min)
2. **Setting branch protection** (Wagner via UI GitHub): adiciona `adr-lint` + `ci` como required-checks (5 min)
3. **Aceitar risco residual** Bug #3 (NCM default) e Bug #6 (mwart-gate soft) — fechar via task no backlog Sprint 1 (não bloqueia kickoff).

**Após pre-fix:** Sprint 1 (Felipe + Wagner pareados em schema multi-vertical) pode começar com base saudável.

**Sem pre-fix:** alto risco de:
- Demo a prospect descoberta de wipe-DB endpoint (#1) — venda morta
- `composer install` em Hostinger contamina prod com daemons proibidos (#2) — quebra contrato Tier 0
- Schema multi-vertical novo sem cobertura test real em CI (#4) — regressão silenciosa

---

**Análise gerada por:** SRE/audit agent (read-only) — 2026-05-10
**Refs:** [audit 03 security](03-security-review-quick.md), [audit 04 CI/PR](04-ci-pr-audit-30d.md), [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md), [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md), [CLAUDE.md proibições](../../proibicoes.md)
