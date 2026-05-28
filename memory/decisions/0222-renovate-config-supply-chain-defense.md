---
adr: 0222
title: Renovate config — defesa proativa supply chain (lições Shai-Hulud + axios + laravel-lang 2026)
status: accepted
date: 2026-05-28
deciders: [Wagner]
amends: []
references:
  - 0061-conhecimento-canonico-git-mcp-zero-automem.md
  - 0094-constituicao-v2-7-camadas-8-principios.md
  - 0216-governance-drift-framework-driftchecker-plugavel.md
  - 0217-composer-audit-checker-supply-chain-detection.md
lifecycle: active
---

## Contexto

ADR 0217 ComposerAuditChecker é **defesa reativa** — detecta CVEs já reportadas. Faltam 2 camadas:

1. **Defesa proativa** — atualizar deps ANTES de CVE aparecer, com **cooldown** que evita supply chain attacks
2. **Cobertura supply chain ampla** — Composer + npm + GitHub Actions + Docker em 1 mecanismo unificado

### Lições supply chain 2026 (documentadas em ADR 0217)

- **Shai-Hulud 2.0** (set-dez/2025, wave 4 mai/2026): npm worm self-replicante; 640+ pacotes wave única
- **axios npm** (mar/2026): 5 minutos de exposição → **895 repos PR-pushed por Renovate/Dependabot → 60% auto-merged em <1h** → ataque massivo
- **laravel-lang packagist** (22-23 mai/2026): republicou versões com `autoload.files` malicioso atacando stack PHP direto

Lição central: **dependency bot é arma de dois gumes**. Bem configurado, defende; mal configurado, vetor de ataque massivo. Configuração canônica obrigatória.

### Estado atual oimpresso (smoke 2026-05-28)

- ❌ `.github/dependabot.yml` ausente
- ❌ `renovate.json` ausente
- ✅ Repo NÃO tem auto-merge habilitado (verificado `gh repo view --json deleteBranchOnMerge`)
- ✅ Sem `laravel-lang` nas deps

**Veredicto:** safe HOJE, mas zero defesa proativa. Próxima CVE high/critical entrará pelas portas dos fundos (cron `governance:audit` daily 06:35 detecta, mas humano leva 12-36h pra agir).

## Decisão

Adotar **Renovate Bot** ([renovatebot.com](https://www.mend.io/renovate/)) self-hosted GitHub App com **`renovate.json` canônico** na raiz do repo.

Por que Renovate ≫ Dependabot:
1. **Cobertura PHP completa** (Composer 1ª classe; Dependabot historicamente limitado)
2. **Configuração rica** (packageRules, groupName, minimumReleaseAge — features Dependabot copiou após axios attack)
3. **Auto-merge granular controlado** (no oimpresso: SEMPRE OFF; Renovate permite override pra dev deps Tier C futuramente)
4. **Vulnerability alerts integradas** (osvVulnerabilityAlerts: true cobre OSV.dev + GitHub Advisory + Snyk)

### Configuração canônica (anti supply chain attack)

| Setting | Valor | Por quê |
|---|---|---|
| `minimumReleaseAge` | **7 days** | Defesa #1 — axios attack expôs em 5min; janela 7d permite community detection |
| `vulnerabilityAlerts.minimumReleaseAge` | 0 days | Exceção: CVE crítica passa direto (override pelo `osvVulnerabilityAlerts`) |
| `automerge` | **OFF** | Wagner SEMPRE revisa — lição Renovate axios attack (60% auto-merged em <1h) |
| `pinDigests` em GitHub Actions | **true** | Defesa #2 — actions têm SHA mutável, pinDigest força hash imutável |
| `prConcurrentLimit` | 5 | Evita spam — Wagner não-bloqueante mas atenção |
| `prHourlyLimit` | 2 | Throttle anti-fadiga |
| `internalChecksFilter` | strict | Renovate só abre PR se passar próprias verificações |
| `osvVulnerabilityAlerts` | true | OSV.dev coverage além de GitHub Advisory |

### Package groups (1 PR por ecossistema)

- **Laravel framework** (`laravel/*`, `illuminate/*`) — schedule Mondays
- **Symfony components** (`symfony/*`) — schedule Mondays (alvo histórico 2x CVE batches)
- **Spatie packages** (`spatie/*`)
- **Inertia+React+Vite** frontend stack
- **Tailwind + PostCSS**
- **TypeScript @types/** (Tier C dev deps)
- **GitHub Actions** (pinDigest true)
- **Dev dependencies** geral
- **@anthropic-ai/*** (3-day cooldown — anti compromise)

### Major versions

`minimumReleaseAge: 30 days` + `schedule: before 5am on first day of month` + label `major-upgrade` + label `needs-review`. **Wagner aprova individualmente cada major.**

### IgnorePaths

`node_modules/`, `vendor/`, `storage/`, `public/build*/`, `_lab/`, `prototipo-ui/`, `Modules/Whatsapp/daemon-node/`

## Não-goals

- ❌ **Automerge OFF universal** — sem exceções nesta versão; Sprint 2 pode adicionar `automerge: true` pra `@types/*` patches após canary
- ❌ **Não substitui ComposerAuditChecker ADR 0217** — defesa em profundidade (Renovate proativo + composer audit reativo)
- ❌ **Não habilita Renovate em fork ou monorepo paths externos**
- ❌ **Não configura Mend.io paid features** (self-hosted GitHub App é suficiente; free tier oimpresso adequate)

## Plano implementação

✅ **Já implementado neste PR**:
- `renovate.json` canônico raiz repo (137 linhas — todas convenções acima)
- Esta ADR

⏳ **Operação manual Wagner (única irreduzível)**:
1. Aprovar Renovate Bot GitHub App em `wagnerra23/oimpresso.com` (1 click em github.com/apps/renovate)
2. Aguardar primeiro Dashboard issue aparecer (Renovate cria automatic)
3. Revisar primeiro batch de PRs Renovate quando vierem (~7d após aprovação devido `minimumReleaseAge`)

⏳ **Sprint 2 followup**:
- Após canary 7d sem incidentes, considerar `automerge: true` em `@types/*` patches (Tier C devDeps zero-risco)
- Após canary 30d, considerar `automerge: true` em Laravel/Symfony patches NÃO-major (alta confiança em testes)

## Consequências

✅ **Boas:**
- Defesa proativa supply chain — patch entra ANTES da CVE virar pública (avg ~7d advance)
- 7-day cooldown elimina vetor axios-style (publish→exploit em <1h)
- pinDigest em GitHub Actions elimina vetor SHA-mutável
- Dashboard Renovate único ponto pra Wagner ver tudo (Tier B opcional vs auditar PRs individuais)
- Grupos por ecossistema = ≤10 PRs/semana em vez de ~50 individuais
- Vulnerability alerts redundantes com ComposerAuditChecker (defesa em profundidade)
- ROI: 1× setup (~30min IA-pair + ~5min Wagner aprovar App) → economiza N horas review individual

⚠️ **Tradeoffs:**
- Mais PRs no dashboard (≤10/semana) — Wagner precisa rotina de review semanal
- `minimumReleaseAge: 7d` significa CVE com fix público demora 7d pra entrar (exceto vulnerability flag — passa imediato via osvVulnerabilityAlerts)
- Renovate Bot precisa ser aprovado como GitHub App (Wagner ação única irreduzível)
- Renovate pode gerar PR conflitando com `composer update` manual humano — `rangeStrategy: bump` minimiza
- Lock file maintenance weekly = 1 PR a mais cada segunda 5am — aceitável

## Validação

- ⏳ Wagner aprova Renovate GitHub App em wagnerra23/oimpresso.com
- ⏳ Dashboard issue aparece em <24h pós-aprovação
- ⏳ Primeiro batch de PRs em ~7d (devido minimumReleaseAge)
- ⏳ Smoke `governance:audit --check=composer_audit` continua 0 findings após primeiros Renovate PRs merged

## Notas

- ADR 0222 NÃO depende de outro PR pra funcionar — Renovate respeita `renovate.json` no momento que App for aprovado
- Branch `feat/governance-renovate-config-supply-chain` pode rodar mesmo sem GitHub App aprovado (config dorme até App acordar)
- Renovate dashboard issue (criada automatic) = Wagner-controlled — pode "snooze all PRs" se viagem/férias
- Sprint 2 ADR 0223 futura: `NpmAuditChecker` cobre frontend deps reactive (composer_audit equivalente)
- Renovate inclui `lockFileMaintenance` weekly Monday 5am — mantém composer.lock + package-lock.json sempre atualizados sem deps update agressivo
