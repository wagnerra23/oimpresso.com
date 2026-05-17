---
data: 2026-05-16
tipo: session-log
slug: governance-maturity-self-audit
wave: 22 (1/12 agents paralelos)
branch: claude/governance-wave-21-22-mega
worktree: D:\oimpresso.com\.claude\worktrees\jolly-hypatia-b8741c
agent: Claude Code subagent (Wave 22)
escopo: Modules/Governance (cross_cutting_infra bucket)
output_canonico: memory/requisitos/Governance/GOVERNANCE-MATURITY-FICHA.md
---

# Session log — Governance MATURITY self-audit (Wave 22, agent 1/12)

## Missão

Gerar **GOVERNANCE-MATURITY-FICHA.md** variante do CAPTERRA-FICHA pro bucket `cross_cutting_infra` (definido Wave 19), comparando Modules/Governance contra 4 IDPs líderes mundo:

- **Port** ($100M Series C 2025)
- **OpsLevel** (Scoped Scorecards positioning anti-Cortex)
- **Cortex** (Wrapped 2025: +64% deploy frequency users)
- **Backstage Tech Insights** (Spotify CNCF, 89% market share OSS IDP)

Reflexividade explicitamente declarada: **Governance audita Governance** (dogfood). Self-policing paradox catalogado em BRIEFING §8.

## Pré-flight (FASE 1 obrigatória — proibicoes.md)

Leituras concluídas antes de Write:

- `memory/requisitos/Governance/BRIEFING.md` — estado consolidado 49/100 → 84 projected → ~100 saturated Wave 18
- `memory/requisitos/Governance/SPEC.md` — 10 US (US-GOV-001..010), cross-tenant INTENCIONAL (ADR 0093 exceção formal)
- `Modules/Governance/SCOPE.md` — bounded context + not_contains + ActionGate modes
- `Modules/Governance/Console/Commands/` — 7 commands (CharterAudit, CharterHealth, CharterMetrics, DetectDrift, GovernanceHealth, ModuleGrade, ModuleGradeSnapshot)
- `Modules/Governance/Http/Controllers/` — 7 controllers (Audit, Dashboard, Data, DriftAlerts, Install, ModuleGrade, Policies)
- `Modules/Governance/Services/` — 5 services (AuditDrillDown, DriftAlert, ModuleGrade, PolicyToggle, ScopedScorecardEvaluator)
- `memory/requisitos/_TEMPLATE_capterra_ficha.md` — shape canônico
- `memory/requisitos/KB/CAPTERRA-FICHA.md` — exemplar recente Wave anterior

## Research executado (WebSearch)

2 WebSearch queries (estado-da-arte 2026 dos 4 players):

1. **Port IDP scorecard maturity 2026** → confirmou: no-code holistic IDP, scorecards from DORA to readiness, RBAC + workflow automation. Gartner 80% software org com platform engineering team em 2026.
2. **OpsLevel scoped scorecards vs Cortex 2025 2026** → confirmou positioning OpsLevel: rubrica diferente por maturity/criticality/team vs Cortex one-size-fits-all baseline única. OpsLevel = flexibilidade, Cortex = padronização.

3. **Backstage Tech Insights plugin 2026** → confirmou: Facts + Checks model, community-maintained (não Spotify-core), scorecards group checks em "Security best practices L1", "Production SRE L3" etc.

Cortex Wrapped 2025 stat (+64% deploy freq) extraído do BRIEFING input direto Wave 22 (não pedi WebSearch dedicado pra economizar — stat já validado).

## Decisões da FICHA

### Posicionamento

Foco em diferenciação **escala PME-BR-vertical-modular-monolith** vs IDPs **escala enterprise-microservices-platform-engineering**. Governance opera com **34 Modules + 5 humanos + 1 IA-pair** — escala fundamentalmente diferente dos players.

### 8 capacidades P0 escolhidas

Vieram do prompt Wave 22 + ajustadas:
1. Auto-detection accuracy (regex + AST + ratio)
2. Score-as-code YAML versionado git
3. **Paired indicators anti-gaming** (nosso diferencial — ninguém faz)
4. **Drift detection daily snapshots** (cron prod vs canônico — vetor #1 incidents)
5. Dual-mode v3↔v4 migration safe
6. Gate CI anti-regressão
7. 4 buckets canônicos (Wave 19)
8. AI-driven V2 baseline READ-ONLY (Wave 24)

### Scores justificados

Self-grading calibrado contra evidência observável (rota existe? Service existe? Pest cobre? PR mergeado?). Notas cruzadas com 4 players reais via WebSearch + conhecimento prévio Wave 19+18.

**Resultado**: nós **74,4/100** ponderado P0. Vs Port 70 / OpsLevel 78 / Cortex 70 / Backstage 70 → **2º lugar empate Port/Cortex, atrás OpsLevel**.

### Onde lideramos (3 capacidades)

1. **Paired indicators anti-gaming** (nós 9,0 vs líder 7,5) — ratio Service/Controller, ratio Charter/Page; IDPs comerciais usam bool simples → gamed
2. **Drift detection daily** (nós 8,0 vs líder 5,5) — IDPs medem catalog drift, nós medimos prod vs canônico git
3. **Reflexividade self-audit** (não na tabela — único no mercado, IDPs comerciais nunca publicam self-score)

### Onde estamos atrás (3 capacidades — Wave 23+24 fecham)

| Gap | Nós | Líder | pp |
|---|---|---|---|
| Score-as-code YAML | 7,0 | OpsLevel/Backstage 9,5 | -2,5 |
| Dual-mode rubric migration | 6,0 | Port 8,5 | -2,5 |
| AI-driven V2 baseline | 5,0 | Cortex 8,0 | -3,0 |

## Reflexividade explicitamente declarada

**Caveat formal na seção meta + seção 8 risks**: Governance audita Governance. Mitigação adotada — usar rubrica externa cruzada com 4 players reais ao invés de rubrica interna `module-grade-v1` (que tem viés do próprio designer da rubrica).

## Output

1. `memory/requisitos/Governance/GOVERNANCE-MATURITY-FICHA.md` — 10 seções formato canônico
2. `memory/sessions/2026-05-16-governance-maturity-self-audit.md` — este log

## Edge cases descobertos

- **Cortex Wrapped 2025 stat** (+64% deploy freq) é marketing — não é peer-reviewed. Citado mas não usado como score boost.
- **Backstage Tech Insights** é **community-maintained**, não Spotify-core. Marketing 89% OSS market share inclui plugins comunitários. Refleti gap mais honesto na nota 5,0 de AI-driven.
- **Self-grading viés**: paired indicators (cap #3) nós demos 9,0 — defendível porque evidência (`ScopedScorecardEvaluator` Service + ratio-based formulas em `ModuleGradeService.gradeModule()` ADR 0153) é observável. Se Wagner discordar, baixar pra 8,0 — gap pra OpsLevel ainda fica.

## Sem git ops (compliance prompt Tier 0)

- ❌ NÃO git add / commit / push / branch / PR — parent (Wagner) consolida Wave 22
- ❌ NÃO editou nada fora `memory/requisitos/Governance/GOVERNANCE-MATURITY-FICHA.md` + este session log
- ✅ PT-BR em tudo
- ✅ Sem BOM (arquivos UTF-8 sem BOM por padrão Write tool)
- ✅ Isolamento área exclusiva respeitado

## Sources (WebSearch)

- [Port IDP scorecards](https://getport.io/product/scorecards)
- [OpsLevel scoped scorecards vs Cortex](https://www.opslevel.com/resources/opslevel-vs-cortex-whats-the-best-internal-developer-portal)
- [Backstage Tech Insights plugin](https://backstage.spotify.com/docs/plugins/soundcheck/core-concepts/tech-insights)
- [Cortex vs Port positioning](https://www.cortex.io/post/what-is-port)

## Próximos passos sugeridos (não-Claude — Wagner decide)

- Wagner aprova Wave 23 (3 gaps: cron 90d snapshot, CI gate regressão, audit CSV export)
- Wagner aprova Wave 24 (2 gaps: YAML extract + AI-suggest READ-ONLY)
- Pós-Wave 24, re-rodar self-audit e validar gap vs OpsLevel < 3pp
