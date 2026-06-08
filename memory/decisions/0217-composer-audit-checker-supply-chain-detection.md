---
slug: 0217-composer-audit-checker-supply-chain-detection
number: 217
title: "ComposerAuditChecker — CVE detection deps composer.lock (supply chain)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-28"
module: governance
tags: [governance, supply-chain, composer, cve, security]
related:
  - 0216-governance-drift-framework-driftchecker-plugavel
pii: false
---

## Contexto

ADR 0216 estabeleceu framework `DriftChecker`. Este é o primeiro checker filha.

**Evidência empírica**: smoke 2026-05-28 21:30 rodou `composer audit --locked --format=json` no oimpresso e detectou **4+ CVEs ATIVAS** em `symfony/yaml` (CVE-2026-45065 "Tag URI Resolution", CVE-2026-45304 "Billion Laughs", CVE-2026-45305 "ReDoS catastrophic backtracking", CVE-2026-45133 "Stack Exhaustion") reportadas 2026-05-20 — apenas 8 dias antes. Nenhuma ação tomada porque nenhum mecanismo automático monitorava.

Smoke completo com `governance:audit --check=composer_audit` revelou **13 findings totais** em deps PHP, severities low-medium dominantes.

**Contexto supply chain 2026** (dossier sessão): 3 ataques majores contra ecossistema npm+composer:
- **Shai-Hulud 2.0** (set-dez/2025, wave 4 mai/2026): npm worm self-replicante, 640+ pacotes wave única, infectou Zapier/ENS/PostHog/Postman
- **axios npm** (mar/2026): 5 minutos de exposição → 895 repos PR-pushed por Dependabot → 60% auto-merged
- **laravel-lang** (22-23 mai/2026): packagist republicou versões com `autoload.files` malicioso ATACANDO STACK PHP DIRETO

Auditoria preventiva confirmou: oimpresso NÃO tem Dependabot nem Renovate configurados; `laravel-lang` ausente das deps. **Emergência supply chain NÃO ativa**, mas gap futuro é certeza.

Sem este checker, próxima CVE high/critical passa despercebida até alguém manualmente rodar `composer audit`.

## Decisão

Implementar `Modules\Governance\Services\Checkers\ComposerAuditChecker` (`name='composer_audit'`):

- **Mecanismo**: shell-out `composer audit --locked --format=json` (cwd = `base_path()`), timeout 120s, parse JSON output Composer 2.6+ canon (`{"advisories": {"<pkg>": [<adv>...]}}`)
- **Severity mapping**: `composer severity` → DriftChecker severity (critical/high/medium/low passa direto)
- **Enforcement baseline**: `warn` (não bloqueia merge); findings individuais `critical` viram `block` futuramente quando Severity Override Sprint
- **Cadence**: `daily` (cron 06:35 BRT via `governance:audit --cadence=daily`)
- **Tags**: `['tier_1', 'security', 'supply_chain']`
- **Exit semantic**: clean (0 advisories) → DriftCheckResult::clean; advisories>0 → DriftCheckResult::drifted com 1 finding por advisory

**Finding payload**:
```json
{
  "target": "<package>",
  "target_type": "composer_package",
  "severity": "<canon>",
  "evidence": {
    "cve": "CVE-2026-XXXXX",
    "severity_composer": "low|medium|high|critical",
    "affected_versions": ">=...<...",
    "link": "https://...",
    "reported_at": "ISO8601"
  }
}
```

**Não inclui auto-update PR**: lição supply chain 2026 (axios) — auto-merge sem cooldown 7d = vetor de malware. Sprint 2 (ADR 0222 futura) cobre: Renovate config + `minimumReleaseAge: 7d` + `pinDigest: false` em `.github/workflows/**`.

## Não-goals

- ❌ **Não atualiza deps automaticamente** — só detecta. Humano decide `composer update <pkg>`.
- ❌ **Não cobre npm/yarn** — Sprint 2 (ADR 0222) trará `NpmAuditChecker`. JS audit precisa adicionar Node ao GH runner + parsing distinto.
- ❌ **Não cobre transitive vulnerabilities além do composer audit** — confia no Composer audit canon.
- ❌ **Não emite RemediationProposal nesta versão** — futura interface `DriftCheckerWithRemediation` opcional.

## Plano implementação

✅ **Já implementado neste PR1 (ADR 0216 ship junto)**:
- `Modules\Governance\Services\Checkers\ComposerAuditChecker` (~180 linhas)
- Registrado em `config/governance.php > drift_checkers[]`
- Smoke local executado: 13 findings detectados, exit code semântico OK

## Consequências

✅ **Boas:**
- Drift CVE detectado em ≤24h (cron daily) em vez de "quando alguém roda audit manualmente" (nunca)
- 0 custo adicional ($0 — composer audit é built-in 2.4+, free)
- Composer audit é Composer-team mantido — não precisamos lógica de regex CVE
- Integração com Brief Jana via `governance:drift` Centrifugo channel
- Empiricamente validado dia 1 — 13 findings reais

⚠️ **Tradeoffs:**
- `composer audit` shell-out adiciona ~3-5s ao `governance:audit` daily (aceitável vs valor)
- False positives possíveis se advisory affecta versão major mas oimpresso está em compatibility patch — Composer normaliza isso bem
- Em CI Linux (GH Action), composer deve estar instalado no setup-php@v2 step (já está)
- Brief Jana pode ficar barulhento se >5 CVEs persistirem (ex: symfony/yaml 4 CVEs hoje) — Wagner age ou suprime via exception lifecycle ADR 0216 §Exception

## Validação

- ✅ Smoke `php artisan governance:audit --check=composer_audit --json` retorna estrutura findings esperada
- ✅ 13 findings reais detectados na primeira execução (symfony/yaml + outros)
- ✅ Performance: 3575ms execution time (aceitável pra cron)
- ⏳ Próximo: rodar `composer update symfony/yaml` em PR separado → smoke deve voltar findings menores (validates updating mecanismo)

## Notas

- Wagner não-bloqueante mas tradução: até `composer update symfony/yaml` rodar, brief 06h vai mostrar 13 findings every day. Aceitar 1-2 ciclos enquanto plano de update é executado.
- Próximas ADRs filhas (0218 MultiTenantScope, 0219 AdrLinks, 0221 RoutesZombie) seguem mesmo padrão.
- ADR 0222 (Sprint 2) — `RenovateConfigChecker` + Renovate yaml com `minimumReleaseAge: 7d`, fechando proteção supply chain camada 5.
