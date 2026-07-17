---
slug: 0216-governance-drift-framework-driftchecker-plugavel
number: 216
title: "Governance Drift Framework — interface DriftChecker plugável (generaliza ADR 0215)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-28"
module: governance
tags: [governance, drift, framework, driftchecker]
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0106-recalibracao-velocidade-fator-10x-ia-pair
  - 0215-secrets-governance-5-camadas-automaticas
pii: false
---

## Contexto

Wagner cobrou 2026-05-28 21:00: *"essas regras [5 camadas ADR 0215] servem só para isso? acho que pode melhorar? ou estou enganado? quem são os melhores nisso e como eles fazem?"*

Wagner está certo — ADR 0215 (secrets governance) é **um caso particular** de um padrão muito maior. Pesquisa estado-da-arte 2026 ([dossier](../sessions/2026-05-28-arte-governance-framework-driftchecker.md), 8 players × 26 WebSearch) mostrou que **Spotify Backstage + OPA/Sentinel + Drata/Vanta + Renovate + AWS Config + Datadog CSPM + GitHub Rulesets convergem num pipeline universal**:

```
Inventory → Check → Score/Severity → Remediate → Audit Trail
```

executado em **3 modos** (pre-commit local, CI gate, scheduled runtime) com **3 níveis** (advisory/warn/block — copiado Sentinel).

Inventário interno ([como-integrar dossier](../sessions/2026-05-28-como-integrar-governance-framework.md)) revelou que o oimpresso **já implementa ~50% disso disperso**: 14 commands "checker" (`governance:detect-drift`, `secrets:scan/audit`, `jana:health-check`, `jana:system-audit`, `jana:freshness-check`, `jana:drift-sentinel`, `ui:lint`, `bin/check-scope.php`, `charter:health`, `governance:scorecard-snapshot`, `fsm:scan-drift`, `kb:drift-detector`, `whatsapp:*-drift-check`, `arquivos:health-check`), persistência canônica `mcp_alertas_eventos` com idempotência por dia, `CentrifugoPublisher` wrapper com OTel, GH workflow template reusável (`secrets-governance.yml`).

Faltam 3 coisas:

1. **Abstração comum** — interface PHP que cada checker implementa
2. **Registry** — singleton que enumera/itera checkers
3. **Command orchestrator** — `governance:audit --check=X|--all` substituindo N comandos
4. (correlato) **Dashboard agregado** — princípio 4 Constituição v2 "loop fechado por métrica" exige score % por módulo (futuro Sprint, fora desta ADR)

Sem framework comum, cada novo domínio (Top 5 priorizados: multi-tenant scope drift Tier 0, composer CVE cooldown supply chain, ADR link-rot, Centrifugo channels orphan, feature flag zombie) reinventa boilerplate (~150 linhas por checker × N → drift entre estilos, persistência inconsistente, sem dashboard agregado).

Frio: o que ADR 0215 ensinou em 5 camadas pra secrets vale pra ~20 domínios.

## Decisão

Adotar **framework `DriftChecker` plugável** em `Modules/Governance/`. 4 peças canônicas:

### 1. Interface `DriftChecker`

```php
namespace Modules\Governance\Contracts;

interface DriftChecker
{
    public function name(): string;                            // 'module_scope_drift'
    public function description(): string;                      // human readable
    public function tags(): array;                              // ['tier_0', 'multi_tenant'] — filter via --tag
    public function severity(): string;                         // 'critical|high|medium|low|info' (Datadog model)
    public function enforcement(): string;                      // 'advisory|warn|block' (Sentinel model)
    public function cadence(): string;                          // 'on_commit|on_pr|hourly|daily|weekly'
    public function check(array $opts = []): DriftCheckResult;  // executa scan, retorna findings
}
```

`DriftCheckResult` DTO: `name`, `ok bool`, `drift_count int`, `findings array<DriftFinding>`, `metadata array`, `duration_ms int`.

`DriftFinding`: `target`, `target_type`, `severity`, `message`, `evidence array`, `business_id ?int`.

### 2. Registry `DriftCheckerRegistry`

Singleton bind em `GovernanceServiceProvider`. API: `register(DriftChecker)`, `all(): array`, `get(string $name)`, `byTag(string $tag): array`, `byCadence(string $cadence): array`.

Auto-discovery via array em `config/governance.php` `'drift_checkers' => [...]` — Wagner override sem deploy.

### 3. Comando `governance:audit`

```bash
php artisan governance:audit
  --check=<name>             # 1 checker; default todos
  --all                      # alias --check=*
  --tag=<tag>                # filter por tag
  --cadence=<cadence>        # filter por cadência
  --diff-only                # pre-commit mode (só staged)
  --fail-on-drift            # exit 1 se qualquer drift (CI gate)
  --fail-on=block            # exit 1 só se finding com enforcement=block
  --auto-pr                  # cria PR pra checkers que suportem remediation
  --notify                   # publica Centrifugo governance:drift
  --json                     # output JSON pra CI consumir
```

Orchestrator persiste cada finding em `mcp_alertas_eventos` (idempotente por `(checker, target, date)`), publica Centrifugo, retorna exit code semântico:
- **0**: clean (nenhum drift)
- **1**: drift detectado (block-level se `--fail-on=block` setado)
- **>1**: erro fatal (exception, missing dep)

### 4. Traits reusáveis

- `PersistsDriftAlert` — extraído de `DetectDriftCommand::persistirAlerta()` (sofisticado: schema `mcp_alertas_eventos`, chave_idempotencia <=200 chars, fallback Log channel)
- `PublishesDriftToCentrifugo` — usa `app(CentrifugoPublisher::class)->publish('governance:drift', ...)`

### 5 camadas universalizadas (mapping ADR 0215 → 0216)

| Camada ADR 0215 (secrets) | ADR 0216 (universal) | Mecanismo |
|---|---|---|
| 1. Auto-discovery (`secrets:scan`) | `DriftChecker::check(['mode'=>'discovery'])` | varre git filtrado |
| 2. Auto-validate (`secrets:audit`) | `DriftChecker::check()` default | execução normal |
| 3. Auto-PR + cron (`secrets:audit --auto-pr`) | `governance:audit --auto-pr` daily 06:35 BRT | cron `app/Console/Kernel.php` |
| 4. Auto-alert (Centrifugo `governance:secrets`) | Centrifugo `governance:drift` + Brief Jana | `--notify` flag |
| 5. Pre-commit + GH Action (gate) | `governance:audit --diff-only --fail-on-drift` | `.githooks/pre-commit` + `.github/workflows/governance-drift.yml` |

### 12 decisões pendentes resolvidas

| # | Decisão | Resolução |
|---|---|---|
| D1 | DB schema | **reusar `mcp_alertas_eventos`** com `tipo='drift_<checker>'` |
| D2 | Refatorar SecretsAudit? | **adapter** (preserva skill Tier A `memory-first-secret-search`) |
| D3 | Pre-commit unificar? | **adicionar 4o bloco** mantendo 3 atuais (back-compat migration lenta) |
| D4 | Business scope? | **global MVP** (`business_id=NULL`); per-business Sprint 2 |
| D5 | Slot cron | **06:35 BRT** (livre após `charter:health` 06:30) |
| D6 | RUNBOOK | criar `Modules/Governance/RUNBOOK-DRIFT-FRAMEWORK.md` ao final |
| D7 | Feature flag | **sim**: `governance.drift_framework_enabled` (default true local, false live até canary) |
| D8 | Migration nova | **não** (`mcp_alertas_eventos` suficiente) |
| D9 | ADR 0216 obrigatória | **sim** (esta ADR) |
| D10 | Remover schedules antigos | **canary 7d**: adicionar `governance:audit` 06:35, observar 1 semana, remover entries antigas em PR3 |
| D11 | Mover CentrifugoPublisher | **não** (dívida documentada, refator futuro) |
| D12 | Alias `secrets:audit` | **sim, manter** (não quebra skill Tier A) |

## Não-goals

- ❌ **Não substitui Backstage** — sem dashboard agregado nesta ADR (futura Sprint usando `Modules/Governance/Services/ModuleGradeService.php` existente)
- ❌ **Não substitui OPA Rego DSL** — PHP classes + enum tipam tudo; Pkl/CUE rejeitados (overkill stack Laravel)
- ❌ **Não substitui Drata SaaS** — não emite evidence SOC2 nesta versão (futura Sprint)
- ❌ **Não migra `mcp_alertas_eventos` schema** — reusa exatamente
- ❌ **Não toca `CentrifugoPublisher`** (mora em `Modules/Whatsapp/` — dívida documentada)
- ❌ **Não escreve checkers além dos 5 priorizados** — outros 15 domínios candidatos ficam em ADRs 0222+ conforme demanda real (princípio "cliente como sinal" [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md))

## Plano implementação (3 PRs)

### PR 1 — Framework base (S-0216-1, ~250 linhas)

- `Modules/Governance/Contracts/DriftChecker.php` (interface)
- `Modules/Governance/Services/DriftCheckResult.php` + `DriftFinding.php` (DTOs)
- `Modules/Governance/Services/DriftCheckerRegistry.php` (singleton)
- `Modules/Governance/Services/Concerns/PersistsDriftAlert.php` (trait — extrai `persistirAlerta` de `DetectDriftCommand`)
- `Modules/Governance/Services/Concerns/PublishesDriftToCentrifugo.php` (trait)
- `Modules/Governance/Console/Commands/GovernanceAuditCommand.php` (orchestrator)
- Edit `Modules/Governance/Providers/GovernanceServiceProvider.php` (singleton register + `commands([GovernanceAuditCommand])`)
- Edit `Modules/Governance/Config/config.php` (adicionar `drift_checkers => []` + `drift_framework_enabled => env(...)`)
- `Modules/Governance/Tests/Feature/GovernanceAuditCommandTest.php` (Pest — registra fake checker, assert flow)
- `Modules/Governance/Tests/Unit/DriftCheckerRegistryTest.php` (Pest — register/get/byTag/byCadence)
- Esta ADR

### PR 2 — 5 checkers (S-0216-2..6, separáveis)

Cada checker = ADR filha + commit isolado:

- **0217** ComposerAuditChecker (CVE detection, suporte ao caso symfony/yaml CVE-2026-45065)
- **0218** MultiTenantScopeChecker (Tier 0 IRREVOGÁVEL — Princípio 6 Constituição v2)
- **0219** AdrLinksChecker (link rot + lifecycle integrity)
- **0220** ModuleScopeChecker (adapter `DetectDriftCommand` — back-compat preservada)
- **0221** SecretsScanChecker + SecretsAuditChecker (adapters ADR 0215 — back-compat preservada)

### PR 3 — Wire-up + canary 7d (S-0216-7, ~150 linhas)

- Edit `app/Console/Kernel.php` — ADICIONAR `governance:audit --all --auto-pr --notify` daily 06:35 BRT (slot livre)
- Edit `.githooks/pre-commit` — ADICIONAR bloco 4o `governance:audit --diff-only --fail-on-drift` (3 blocos atuais permanecem 7d canary)
- Edit `.github/workflows/secrets-governance.yml` → renomear `governance-drift.yml`, generalizar
- Smoke test biz=1 + biz=4

**+7 dias depois (PR 4 cleanup):**
- Remover entries 06:15 antigas (`governance:detect-drift`, `secrets:audit`, `secrets:scan` weekly)
- Remover 3 blocos `.githooks/pre-commit` antigos
- Diff esperado: ~100 linhas removidas

## Consequências

✅ **Boas:**
- 14 commands → 1 orchestrator `governance:audit` (drift removido entre estilos)
- Novo checker = ~80 linhas (interface + persiste via trait), em vez de ~250 (boilerplate completo)
- Dashboard futuro (Sprint posterior) consome registry: `<dl><dt>multi_tenant_scope</dt><dd>✅ 0 drift</dd>...`
- Brief Jana 06h ingere 1 canal Centrifugo (`governance:drift`) em vez de N
- Pre-commit hook unifica progressivamente (canary 7d evita corte abrupto)
- ROI: 5 novos checkers Top 5 + framework = ~22h IA-pair ([recalibração ADR 0106](0106-recalibracao-velocidade-fator-10x-ia-pair.md) = ~2.5 dev-days reais)
- Estado-da-arte: copiamos lições Sentinel (enforcement 3-níveis) + AWS Config (conformance pack agrupamento) + Drata (framework→control→test mapping) sem fork de Rego/HCL
- Princípio 4 Constituição v2 ("loop fechado por métrica") explicitado: cada checker tem severity + cadence + remediation

⚠️ **Tradeoffs:**
- `mcp_alertas_eventos` cresce — drift checkers diários produzem ~10-50 rows/dia. Retention policy via `Modules/Governance/Config/retention.php` (existente) ajustar pra `drift_*` tipos. Aceitável (tabela já tem index).
- `CentrifugoPublisher` em `Modules/Whatsapp/` semanticamente errado — dívida documentada (refator não-blocking).
- Pre-commit hook ganha latência ~500ms adicional (registry init + 4 blocos PHP startup). Aceitável vs valor.
- `governance:audit --all` em 06:35 BRT roda 14 checkers em sequência — duration esperada 30-90s. Mitigação: `withoutOverlapping` + `onOneServer` + `--parallel` flag futura.
- Schedule legacy 06:15 BRT vai conviver 7d com 06:35 — risco duplicate alertas. Mitigação: `chave_idempotencia` UNIQUE no banco (já testado em `DetectDriftCommand`).
- Backwards-compat 12 alias commands = ruído. Aceitar 1 ciclo, depois deprecar com aviso.
- Sem dashboard nesta ADR — governança "invisível" até Sprint posterior; mitigação interim: Brief Jana texto manhã.

## Validação

- ✅ Pest `GovernanceAuditCommandTest` verde (fake checker registrado + assert flow)
- ✅ Pest `DriftCheckerRegistryTest` verde (register/get/byTag/byCadence)
- ✅ `php artisan governance:audit --all --json` em local retorna JSON estruturado com N checkers
- ✅ `php artisan governance:audit --check=secrets-audit` produz output equivalente a `secrets:audit` legacy
- ✅ Pre-commit hook bloco 4o NÃO quebra commits existentes (canary 7d 3+1 blocos coexistem)
- ✅ GH Action `governance-drift.yml` verde em PR de teste
- ✅ Centrifugo channel `governance:drift` recebe payload via smoke local

## Notas

- **Empiricamente validado**: `composer audit --locked` rodado 2026-05-28 21:30 revelou 4 CVEs symfony/yaml low (CVE-2026-45065, 45304, 45305, 45133, reported 2026-05-20) → ADR 0217 ComposerAuditChecker justificado por evidência real (não hipótese)
- **Supply chain attack 2026 OFF-target**: Dependabot + Renovate ambos NÃO configurados no oimpresso; laravel-lang ausente das deps → emergência 24h NÃO confirmada; ADR 0217 entra na ordem normal
- **Senior expert dossier** ([sessions/2026-05-28-arte-governance-framework-driftchecker.md](../sessions/2026-05-28-arte-governance-framework-driftchecker.md)) catalogou 8 players × 33 fontes; este ADR cita parcial — players completos no dossier
- **Como-integrar dossier** ([sessions/2026-05-28-como-integrar-governance-framework.md](../sessions/2026-05-28-como-integrar-governance-framework.md)) listou 14 commands existentes + 12 decisões pendentes; este ADR resolve as 12
- **Próximas ADRs filhas**: 0217 (ComposerAudit), 0218 (MultiTenantScope), 0219 (AdrLinks), 0220 (ModuleScope adapter), 0221 (Secrets adapters) — cada uma ≤200 linhas Nygard
- Wagner expressou: *"sim acho que ficou otimo, pode sim pesquisar e fazer cada etapa incrivel"* — aprovação opção A do prompt anterior, com mandato qualidade
