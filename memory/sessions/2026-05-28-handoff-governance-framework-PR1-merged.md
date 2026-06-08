---
date: 2026-05-28
session: handoff governance drift framework PR1 → próxima sessão
status: PR #1874 MERGED em main (commit 066cd96bf)
next_sprint: S-0216-2 — canary 7d observation + Sprint 2 followups
---

# Handoff — Governance Drift Framework PR1 mergeado, próximos passos

## 🎯 Onde paramos (Sessão 2026-05-28)

Wagner cobrou: *"essas regras [5 camadas ADR 0215] servem só para isso? acho que pode melhorar? quem são os melhores nisso e como eles fazem?"*

Resposta: padrão 5 camadas é caso particular de **continuous governance / drift detection** universal. Implementado framework `DriftChecker` plugável.

**PR [#1874](https://github.com/wagnerra23/oimpresso.com/pull/1874) MERGED 2026-05-28 14:56 UTC.** Commit em main: `066cd96bf`.

## 📦 O que foi entregue

### Pesquisa + planejamento
- [Dossier estado-da-arte](2026-05-28-arte-governance-framework-driftchecker.md) — 8 players (Backstage, OPA/Sentinel, Drata/Vanta, Renovate, AWS Config, Datadog, Pkl/CUE, GitHub Rulesets) × 26 WebSearch
- [Dossier como-integrar](2026-05-28-como-integrar-governance-framework.md) — inventário 14 commands existentes + 12 decisões pendentes resolvidas

### ADRs (5 novas — Nygard)
- [0216](../decisions/0216-governance-drift-framework-driftchecker-plugavel.md) mãe — Framework + interface + 12 decisões
- [0217](../decisions/0217-composer-audit-checker-supply-chain-detection.md) — ComposerAuditChecker
- [0218](../decisions/0218-multi-tenant-scope-checker-tier-0.md) — MultiTenantScopeChecker (Tier 0)
- [0219](../decisions/0219-adr-links-checker-memory-canon-integrity.md) — AdrLinksChecker
- [0221](../decisions/0221-routes-zombie-checker-blast-radius.md) — RoutesZombieChecker
- **0220 PULADO** (era ChartersFreshness — adapter `charter:health` Sprint 2)

### Código (framework Modules/Governance/)
- Contracts/`DriftChecker.php` — interface 7 métodos canon
- Services/`DriftCheckResult.php` + `DriftFinding.php` — DTOs readonly
- Services/`DriftCheckerRegistry.php` — singleton + byTag/byCadence/byEnforcement
- Services/Concerns/`PersistsDriftAlert.php` — trait (idempotência `mcp_alertas_eventos`)
- Services/Concerns/`PublishesDriftToCentrifugo.php` — trait (channel `governance:drift`)
- Services/Checkers/ — **4 checkers concretos** (Composer, MultiTenant, AdrLinks, RoutesZombie)
- Console/Commands/`GovernanceAuditCommand.php` — orchestrator (`--check|--all|--tag|--cadence|--diff-only|--auto-pr|--notify|--json|--fail-on-drift|--fail-on=block|--no-persist`)
- Providers/`GovernanceServiceProvider.php` — edit: singleton + auto-register checkers
- Config/`config.php` — `drift_framework_enabled` (kill switch) + `drift_checkers[]` + allowlists

### Wire-up
- `app/Console/Kernel.php` — schedule `governance:audit --all --notify` daily **06:35 BRT** (slot livre — 06:15 disputado por 4 schedules)
- `.githooks/pre-commit` — bloco 4o (3 antigos preservados — canary 7d)
- `.github/workflows/governance-drift.yml` — PR + schedule + dispatch
- **Kill switch:** `GOVERNANCE_DRIFT_FRAMEWORK_ENABLED=false` rollback 1 ENV

### Tests
- 18/18 Pest verde: `vendor/bin/pest Modules/Governance/Tests/Feature/`
- `DriftCheckerRegistryTest.php` (8 tests)
- `GovernanceAuditCommandTest.php` (10 tests)
- **Pegadinha técnica anotada:** Pest 4 + Laravel exige `uses(Tests\TestCase::class);` explícito no topo do file pra `app()` resolver dentro do test, e `beforeEach` não funciona pra `app()->instance()` (facade root not set). Helper `resetRegistry()` inline em cada test foi a solução.

## 🟢 Smoke real (validado dia 1)

```
$ php artisan governance:audit
⚠ composer_audit — 13 findings (3102ms)     # CVEs reais
✓ multi_tenant_scope — 0 findings (10ms)    # 209 Models Tier 0 saudável
⚠ adr_link_rot — 3 findings (67ms)          # ADRs canon drift
✓ routes_zombie — 0 findings (0ms)          # 1889 routes snapshot, skip table missing
Governance audit: 4 checkers · 2 clean · 2 drifted · 16 findings total
```

## 🚨 Pendências críticas — próxima sessão

### Imediato (P0)
1. **`composer update symfony/yaml`** — resolver 4 CVEs detectadas pelo `composer_audit`
   - CVE-2026-45065 "Tag URI Resolution"
   - CVE-2026-45304 "Billion Laughs" exponential memory
   - CVE-2026-45305 "ReDoS catastrophic backtracking"
   - CVE-2026-45133 "Stack Exhaustion unbounded recursion"
   - Reported 2026-05-20 — versão target `>=7.4.12` ou major upgrade
   - PR pequeno isolado pra fácil revisão
2. **Smoke prod canary 7d** — observar cron 06:35 BRT em CT 100 + Brief Jana 07h consumindo Centrifugo `governance:drift`
3. **3 drift ADRs detectados** — ADR 0018 supersedes "2026" e "0000" inválidos. Manual fix OR melhorar parser AdrLinksChecker (provável bug interpretação YAML quando number sem quotes)

### Sprint 2 (P1)
4. **Cleanup canary 7d:** após observar `governance:audit` 06:35 sem regressão, remover:
   - 3 blocos `.githooks/pre-commit` antigos (scope/ui-lint/secrets) → unificar em 1 chamada `governance:audit --diff-only`
   - Schedules legacy 06:15 BRT (`secrets:audit --auto-pr --notify`, `governance:detect-drift`, `secrets:scan` weekly)
   - Workflow `secrets-governance.yml` → arquivar (replaced by `governance-drift.yml`)
5. **ChartersFreshnessChecker** — adapter wrappingexistente `charter:health` command em DriftChecker interface
6. **NpmAuditChecker** — cobrir frontend deps (Node + npm audit JSON). Lição supply chain 2026.
7. **`system_access_log` table OR mcp_observability_spans integration** — pra RoutesZombieChecker virar útil (atualmente skip)
8. **AST scan completo via nikic/php-parser** pra MultiTenantScopeChecker (raw queries `DB::table()` sem `where('business_id')` em controllers + jobs — Sprint 2 ADR 0223 futura)
9. **Backstage-like dashboard** `/governance/status` — score % por módulo + timeline drift findings (consome `mcp_alertas_eventos` + DriftCheckerRegistry)
10. **Renovate config + Dependabot off** (`renovate.json` com `minimumReleaseAge: 7d` + `pinDigest: false` em `.github/workflows/**`) — defesa proativa supply chain 2026 (Shai-Hulud/axios/laravel-lang lições). ADR 0222 futura.

## 🧠 Conhecimento crítico pra próxima sessão

### Convenções canon estabelecidas no framework
- **Severity 5 níveis:** `critical|high|medium|low|info` (Datadog/CSPM model — copiar pra novos checkers)
- **Enforcement 3 níveis:** `advisory|warn|block` (HashiCorp Sentinel — não inventar variações)
- **Cadence 5:** `on_commit|on_pr|hourly|daily|weekly` (usado por `--cadence` filter)
- **Persistência:** SEMPRE `mcp_alertas_eventos` com `tipo='drift_<checker_name>'` e `chave_idempotencia` formato `drift_<checker>:<target_type>:<sha1(target)[12]>:<YYYY-MM-DD>` (200 chars max)
- **Centrifugo channel:** `governance:drift` único pra todos checkers (override via `config('governance.drift_centrifugo_channel')`)
- **Tags convenção:** `tier_0|tier_1|tier_2` + dominio (`security|compliance|tech_debt|memory_canon|multi_tenant|supply_chain|observability`)

### Pegadinhas conhecidas
1. **`CentrifugoPublisher` mora em `Modules/Whatsapp/`** (semanticamente errado pra Governance, mas mover quebra cascateado — dívida documentada, refator futuro)
2. **Pest 4 + Laravel testes** exigem `uses(Tests\TestCase::class);` explicit + helper função pra reset registry (não `beforeEach`)
3. **Composer dump-autoload após adicionar classe nova** — sem isso, `class_exists()` retorna false mesmo com file salvo. Run: `composer dump-autoload`
4. **Slot cron 06:15 BRT** disputado por 4 schedules (jana:system-audit + secrets:audit + governance:detect-drift + nfebrasil:dist-dfe-puxar) — sempre escolher slot livre alternativo (06:35 foi escolhido pra ADR 0216)
5. **Charter discovery cache** — após adicionar `GovernanceAuditCommand` ao `registerCommands()`, precisou `php artisan optimize:clear` pra apparecer em `artisan list`

### Como adicionar novo checker (~80 linhas)
1. Criar `Modules/Governance/Services/Checkers/MyNewChecker.php` implementando `DriftChecker`
2. Definir `name()` snake_case único + `description()` + `tags()` + `severity()` + `enforcement()` + `cadence()`
3. Implementar `check(array $opts = []): DriftCheckResult` — retornar `DriftCheckResult::clean(...)` ou `DriftCheckResult::drifted(...)` com array de `DriftFinding`
4. Registrar em `config/governance.php > drift_checkers[]`
5. `php artisan config:clear`
6. Smoke: `php artisan governance:audit --check=my_new_checker --json`
7. Criar Pest test em `Modules/Governance/Tests/Feature/MyNewCheckerTest.php` (lembrar `uses(Tests\TestCase::class)`)
8. ADR Nygard 0222+ filha de 0216 (~150 linhas)

## 🔗 Refs canônicas

- **ADR 0216 mãe:** [memory/decisions/0216-governance-drift-framework-driftchecker-plugavel.md](../decisions/0216-governance-drift-framework-driftchecker-plugavel.md)
- **PR mergeado:** [#1874](https://github.com/wagnerra23/oimpresso.com/pull/1874) (commit `066cd96bf`)
- **Comando canon:** `php artisan governance:audit --help` (lista todas flags)
- **Kill switch:** `GOVERNANCE_DRIFT_FRAMEWORK_ENABLED=false` no `.env`
- **Cron canary:** `app/Console/Kernel.php:716+` (linhas 717-732 novo schedule 06:35 BRT)
- **Dossier estado-da-arte:** [memory/sessions/2026-05-28-arte-governance-framework-driftchecker.md](2026-05-28-arte-governance-framework-driftchecker.md) (não comitado)
- **Dossier como-integrar:** [memory/sessions/2026-05-28-como-integrar-governance-framework.md](2026-05-28-como-integrar-governance-framework.md) (comitado)

## 📋 MCP task pendente

Próxima sessão: chamar `tasks-detail` na task que vou criar agora pra ter contexto rápido.
