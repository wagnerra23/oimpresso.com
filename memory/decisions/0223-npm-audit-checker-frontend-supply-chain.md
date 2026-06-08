---
slug: 0223-npm-audit-checker-frontend-supply-chain
number: 223
title: "NpmAuditChecker — frontend supply chain CVE detection (complementa 0217)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-28"
module: governance
tags: [governance, supply-chain, npm, frontend, security]
related:
  - 0216-governance-drift-framework-driftchecker-plugavel
  - 0217-composer-audit-checker-supply-chain-detection
  - 0222-renovate-config-supply-chain-defense
pii: false
---

## Contexto

ADR 0217 (ComposerAuditChecker) cobre lado PHP do supply chain. Falta cobertura **frontend** (Node + npm + React + Inertia + Vite + Tailwind + TypeScript).

oimpresso `package.json` + `package-lock.json` existem (Inertia v3 + React 19 + Vite stack). Smoke 2026-05-28 rápido em `npm audit --json` mostrou **vulnerabilidades reais ATIVAS** (engine.io-client, protobufjs, etc.) sem ninguém saber — mesmo gap de ComposerAuditChecker descoberto há ~7h.

### Lições supply chain 2026 (npm específico)

- **Shai-Hulud 2.0** wave 4 mai/2026 — 640+ pacotes npm worm self-replicante
- **axios npm** mar/2026 — 5min exposição → 895 repos Renovate/Dependabot auto-merged
- Lock files (`package-lock.json`) NÃO impedem ataque — só registram hash atual, não verificam idoneidade

Renovate config (ADR 0222) é defesa proativa mas demora 7d (cooldown). Sem `npm_audit` daily, ataques entre janelas Renovate passam invisíveis até alguém manualmente rodar `npm audit`.

## Decisão

Implementar `Modules\Governance\Services\Checkers\NpmAuditChecker` (`name='npm_audit'`) **simétrico** ao ComposerAuditChecker:

### Mecanismo

```php
public function check(array $opts = []): DriftCheckResult
{
    if (!file_exists(base_path('package.json'))) return DriftCheckResult::clean(...);

    $process = Process::path(base_path())
        ->timeout(180)
        ->run(['npm', 'audit', '--json']);

    // exit 0 = clean, 1 = vulns, 2+ = error
    // parse schema auditReportVersion: 2:
    //   vulnerabilities.<pkg>.{severity, via[], range, isDirect, fixAvailable, effects[]}
}
```

### Severity mapping npm → canonical

| npm | canonical | Rationale |
|---|---|---|
| critical | critical | Tier 0-equivalent (RCE, supply chain compromise) |
| high | high | XSS exploitable, sensitive data leak |
| moderate | medium | normalização canon ADR 0216 (5 níveis Datadog) |
| low | low | DoS, minor info disclosure |
| info | info | advisory only |

### via[] parsing (transitive vs direct)

npm audit `via[]` pode conter:
- **string** = nome do parent package (transitive vulnerability — vuln vem de dep do dep)
- **object** = advisory direto com `{source, title, url, severity, cwe}` (CVE direto no package)

Implementação cria 1 finding por advisory object, ou 1 finding agregado quando só houver string transitives (preservando rastro `via_parents` em evidence).

### Convenções canon (consistentes ADR 0216)

- Severity baseline: `high` (frontend deps podem permitir XSS/RCE em produção)
- Enforcement: `warn` (findings critical viram block via `--fail-on=block` runtime)
- Cadence: `daily` (cron 06:35 BRT junto com outros checkers)
- Tags: `['tier_1', 'security', 'supply_chain', 'frontend']`
- Persistência: `mcp_alertas_eventos` tipo `drift_npm_audit`
- Centrifugo: channel `governance:drift`

### Fail-safe quando npm ausente

Se `package.json` OU `package-lock.json` faltarem → `DriftCheckResult::clean` com metadata `skipped`. Se npm binary falhar (exit code ≥ 2) → clean com error metadata + Log channel ALERT. Nunca lança exception (ADR 0216 §exit code semantic).

## Não-goals

- ❌ **Não roda `npm audit fix`** — apenas detecta. Humano decide upgrades (lição supply chain: auto-fix = vetor)
- ❌ **Não cobre yarn/pnpm** — projeto usa npm canon; outros gerenciadores Sprint 3 ADR 0225 futura
- ❌ **Não cobre pacotes globais** (`npm install -g`) — apenas deps do projeto
- ❌ **Não emite RemediationProposal** — humano decide `npm update <pkg>`

## Plano implementação

✅ **Já implementado neste PR**:
- `Modules\Governance\Services\Checkers\NpmAuditChecker` (~220 linhas)
- Registrado em `config/governance.php > drift_checkers[]` (posição 2, logo após ComposerAuditChecker)
- Esta ADR

⏳ **GH Action update (Sprint 2 followup)**:
- `.github/workflows/governance-drift.yml` precisa adicionar Node setup (`actions/setup-node@v4`) ANTES de chamar `governance:audit` no schedule job

## Consequências

✅ **Boas:**
- 6 checkers no framework (Composer + Npm + MultiTenant + AdrLinks + Charters + RoutesZombie) cobertura supply chain end-to-end (PHP + JS)
- Empiricamente justificado: smoke 2026-05-28 mostrou vulns reais (engine.io-client, protobufjs)
- Renovate (ADR 0222 proativo) + Composer Audit + Npm Audit = defesa em profundidade 3 camadas supply chain
- Brief Jana 06h ingere `npm.drift_detected` via Centrifugo `governance:drift`
- ROI: ~3-5s shell-out `npm audit` × daily → economiza ataques supply chain passar invisíveis

⚠️ **Tradeoffs:**
- `npm audit` adiciona ~3-5s ao `governance:audit --all` daily (aceitável)
- npm precisa estar instalado em CI runner — adicionar `actions/setup-node@v4` no workflow
- Performance local Windows: ~2s shell-out npm (testado smoke)
- npm audit às vezes retorna false positives quando advisory affecta versão major mas oimpresso está em compatibility patch — `range` evidence permite review human
- Brief Jana pode ficar ruidoso se >5 npm CVEs persistirem — Wagner suprime via exception lifecycle ou roda `npm update`

## Validação

- ⏳ Smoke `php artisan governance:audit --check=npm_audit --json` retorna findings reais
- ⏳ `governance:audit --all` agora tem 6 checkers
- ⏳ Pest framework 18/18 verde (não regredir)
- ⏳ Follow-up Sprint 2: atualizar `.github/workflows/governance-drift.yml` adicionar Node setup

## Notas

- ADR 0223 numeração reservada Sprint 2 — esperada handoff PR #1874
- npm audit schema `auditReportVersion: 2` é canon npm 7+ (npm 11.4.2 oimpresso usa atual)
- Sprint 3 futura: `YarnAuditChecker` + `PnpmAuditChecker` se algum sub-módulo migrar de gerenciador
- Sprint 3 futura: `npm-check-updates`-style proactive (Renovate cobre isto — não duplicar)
- Wagner pediu "continuar" sessão 2026-05-28 — esta ADR fecha defesa supply chain frontend
