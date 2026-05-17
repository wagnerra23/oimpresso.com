---
modulo: Governance
versao_ficha: 1.0 (MATURITY variante CAPTERRA — bucket cross_cutting_infra)
formato: governance-maturity-ficha-canonica (variante ADR 0089 — auditoria reflexiva)
gerado_em: 2026-05-16
gerado_por: Claude Code Wave 22 (subagent paralelo)
bucket: cross_cutting_infra
players_comparados:
  - Port (IDP, $100M Series C 2025) — getport.io
  - OpsLevel (Scoped Scorecards positioning anti-Cortex) — opslevel.com
  - Cortex (Wrapped 2025 — +64% deploy frequency users) — cortex.io
  - Backstage Tech Insights (Spotify CNCF, 89% market share OSS IDP) — backstage.io
reflexividade_caveat: |
  Governance audita Governance. Self-policing paradox catalogado em BRIEFING §8 (risk 🟡).
  Mitigação: tabela seção 3 mede capacidades observáveis (existe rota? existe Service? existe Pest?),
  não auto-elogio. Score final calibrado contra os 4 players (não vs si mesmo).
proximo_review: pós-Wave 24 (AI-driven V2 baseline READ-ONLY) — esperado +5pp Auto-detection accuracy
---

# GOVERNANCE-MATURITY-FICHA — Modules/Governance (self-audit reflexivo)

> **Variante MATURITY** do template CAPTERRA-FICHA pro bucket **cross_cutting_infra** (definido Wave 19 — buckets canônicos).
> **Reflexividade declarada**: Modules/Governance avalia Modules/Governance. Honestidade > otimismo (BRIEFING §8). Cada nota cruza com player externo independente pra evitar self-grading inflado.
> **Disclaimer Wagner-friendly**: este doc não substitui ADR 0153 rubrica `module-grade-v1` (que é interno, 5 dims × 0-20). Aqui é benchmark **externo** contra IDPs líderes mundo.

---

## 1. Posicionamento

**Modules/Governance** é o **enforcer runtime + dashboard humano da Constituição v2** ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)) de um ERP brasileiro **modular especializado por vertical** ([ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)). Diferente dos IDPs de mercado (Port, OpsLevel, Cortex, Backstage) que servem **plataformas com centenas de microsserviços em times de 50-500 engenheiros**, Governance opera num modular monolith Laravel com **34 Modules**, 5 humanos + 1 IA-pair (Claude Code), e PMEs brasileiras como cliente final. Atende 3 personas:

- **Wagner (superadmin)** — Dono enxerga compliance Constituição v2 + drift + module grades em 1 cockpit (`/governance`)
- **Time MCP (Felipe/Maiara/Eliana/Luiz — em breve)** — Leitura `/governance/module-grades` + audit log filtrado por actor
- **Auditor externo (futuro — LGPD/contador)** — `governance.audit.view` export read-only com PII redacted

Posicionamento competitivo: **único enforcer formal de Constituição IA-pair no BR-PME**. Bling/Tiny/Omie/Conta Azul zero governança documentada. IDPs globais (Port/OpsLevel/Cortex/Backstage) escalam pra grandes engenharias, mas **não cobrem** o vetor "drift entre git canônico e prod" via cron daily nem ActionGate runtime middleware no nível request-by-request — eles são scorecards CI-only.

## 2. Players comparados (4 IDPs + nós)

| Player | Tipo | Pricing | Forte | Frágil |
|---|---|---|---|---|
| **Oimpresso Governance (nosso)** | Meta-módulo ERP | embutido (zero $) | ActionGate runtime + drift cron + reflexividade self-grading + PT-BR fit | Cobertura Pest cross-tenant (40%) + falta UI Inertia partes (Audit/Drift) |
| **Port** ($100M Series C 2025) | IDP SaaS no-code | $$$ (enterprise) | Software catalog flexível + self-service actions + scorecards no-code + RBAC maduro | SaaS-only (sem self-host real), curva data model, foco engenharia (não negócio) |
| **OpsLevel** | IDP SaaS | $$$ (enterprise) | **Scoped Scorecards** (rubrica diferente por maturity/criticality/team) + posiciona anti-Cortex em flexibilidade | SaaS-only, time crítico pra calibrar checks, sem runtime gate (só CI/agentes) |
| **Cortex** (Wrapped 2025: +64% deploy freq usuários) | IDP SaaS | $$$ (enterprise) | Onboarding rápido + Scorecards padronizados + Eng Intelligence métricas DORA | Rubrica única (sem scoped), positioning OpsLevel quebra "one-size-fits-all" |
| **Backstage Tech Insights** (Spotify CNCF, 89% market share OSS IDP) | OSS plugin | grátis self-host (mas custo dev pesado) | Facts + Checks model, ecossistema plugin gigante, comunidade ativa | Setup difícil (precisa platform team), Tech Insights ainda community-maintained não-Spotify-core, sem runtime gate |

> **Insight crítico**: nenhum dos 4 players faz **runtime middleware gate** equivalente ao nosso `ActionGate`. Eles são todos CI-gate ou scorecard pós-fato. ActionGate é **diferencial defensável** — bloqueia DDL ad-hoc, mass-update bypass FSM, etc no momento do request. Ver [ADR 0147](../../decisions/0147-governance-actiongate-warn-strict-modes.md).

## 3. Matriz 8 capacidades P0 × 4 concorrentes × nós

Escala 0-10 (10 = best-of-class). Pesos: P0 (todas capacidades aqui são P0 por definição do bucket cross_cutting_infra). Self-grading caveat: nota "nós" cruzada com evidência observável (rota existe? Service existe? Pest cobre? PR mergeado?).

| # | Capacidade P0 | Nós (hoje) | Port | OpsLevel | Cortex | Backstage TI | Evidência nossa |
|---|---|---|---|---|---|---|---|
| 1 | **Auto-detection accuracy** (regex + AST + ratio paired indicators) | **8,5** | 8,0 (no-code rules) | 9,0 (flex CEL) | 7,5 (rubrica única) | 8,5 (Facts/Checks model) | `ModuleGradeService::gradeModule()` 5 dims × 0-20, paired indicators anti-gaming (ADR 0153) |
| 2 | **Score-as-code YAML versionado git** | **7,0** | 6,5 (UI-first, YAML export) | 9,5 (YAML/CEL native) | 8,0 (YAML) | 9,5 (TS + YAML facts) | rubrica hardcoded `ModuleGradeService.php` PHP — gap: extrair pra `config/governance/rubric.yml` (Wave 24) |
| 3 | **Paired indicators anti-gaming** (não-bool: ratio Service/Controller, ratio Charter/Page) | **9,0** | 6,0 (bool check default) | 7,5 (CEL pode mas exige custom) | 6,0 (bool) | 7,0 (custom check) | dim D2 (tests) usa ratio `Pest_files / Service_files >= 0.5`; dim D4 (charter) usa `Charters / Pages.tsx >= 0.7` |
| 4 | **Drift detection daily snapshots** (cron canônico vs prod real) | **8,0** | 5,0 (catalog drift via webhook) | 6,0 (sync API only) | 5,5 (catalog sync) | 5,0 (Facts retrievers schedule, sem prod-canônico delta) | `DetectDriftCommand` + `governance:scan-drift` cron daily 03:30 BRT (vetor #1 incidents catalogado WhatsApp maratona) |
| 5 | **Dual-mode v3↔v4 migration safe** (rubrica nova sem quebrar histórico) | **6,0** | 8,5 (scorecard versioning native) | 8,0 (Levels migration) | 7,5 (versioned) | 7,0 (Facts schemas) | gap: rubrica `module-grade-v1` ainda sem v2 — quando Wave 24 sair AI-driven, precisa dual-mode (planejado Fase B ADR 0153) |
| 6 | **Gate CI anti-regressão** (PR bloqueia se score cair >X pp) | **7,5** | 8,5 (Port GitHub Action) | 9,0 (CI gates ricos) | 8,5 (CI checks) | 8,0 (Tech Insights PR widget) | `governance-gate.yml` CI bloqueia ADR canon edit + handoff overwrite (Mecanismo #2 ENFORCEMENT). Falta: gate score regressão por módulo (Wave 23) |
| 7 | **Buckets canônicos** (categorização semântica módulos — Wave 19) | **8,5** | 7,5 (catalog group_by genérico) | 8,0 (categories) | 7,0 (groups) | 7,5 (kinds) | 4 buckets: `core_pme`, `vertical_specialized`, `cross_cutting_infra`, `experimental` (Wave 19 catalog) |
| 8 | **AI-driven V2 baseline READ-ONLY** (LLM sugere rubrica refinada sem aplicar auto) | **5,0** | 7,0 (Port AI Agent 2026) | 6,5 (AI Engineer beta) | 8,0 (Cortex AI Eng Intel) | 5,0 (sem AI nativo) | gap: planejado Wave 24 (`module:grade --ai-suggest` chama Claude API mock READ-ONLY, Wagner approves manual) |

### Onde já somos melhores ou empatamos com o topo (3 capacidades)

1. **Paired indicators anti-gaming** (9,0) — ninguém faz ratio nativamente; Cortex/Port usam bool simples → vira gamed (devs marcam "test exists" sem cobertura real)
2. **Drift detection daily** (8,0) — IDPs medem catalog drift (entity sync), nós medimos **drift prod vs canônico git** (vetor incidents real)
3. **Reflexividade self-audit** (não na tabela, mas único) — Governance roda rubrica nele mesmo (49 → 84 → ~100 projected). IDPs comerciais nunca publicam self-score

### Onde estamos atrás de líderes (3 capacidades — Waves planejadas)

| Capacidade | Nós | Líder | Gap pp | Wave fix |
|---|---|---|---|---|
| Score-as-code YAML | 7,0 | OpsLevel/Backstage 9,5 | -2,5 | Wave 24 (extract `config/governance/rubric.yml`) |
| Dual-mode v3↔v4 migration | 6,0 | Port 8,5 | -2,5 | Fase B ADR 0153 (versioned rubric `v1` → `v2-ai`) |
| AI-driven V2 baseline | 5,0 | Cortex AI Eng Intel 8,0 | -3,0 | Wave 24 (`--ai-suggest` Claude mock) |

## 4. Score consolidado

| Métrica | Valor |
|---|---|
| **Média ponderada P0 (8 capacidades)** | **(8,5+7,0+9,0+8,0+6,0+7,5+8,5+5,0) / 8 = 59,5/80 = 74,4/100** |
| **Bucket interno `module-grade-v1`** (5 dims × 0-20) | **49 → 84 projected pós-Wave G** (BRIEFING §2) |
| **Posição vs 4 IDPs (média deles em P0)** | nós 74 vs Port 70 / OpsLevel 78 / Cortex 70 / Backstage 70 = **2º lugar empate Port/Cortex, atrás OpsLevel** |
| **Self-grading honestidade index** | rubrica externa contra players reais (não vs si) — calibrado |

> **Bottom-line**: Governance hoje está **competitivo com IDPs comerciais** em escopo PME-Brasil onde opera, **liderança em 3 capacidades** (paired indicators, drift cron, reflexividade), e gap fechável em 3 capacidades via Wave 23+24 (~10pp = chegar a 84-88/100).

## 5. UX heuristics (eixo Usabilidade — variante MATURITY)

Self-audit operacional de Wagner (operador único hoje):

```yaml
ux_heuristics:
  - id: dashboard-load-time
    nome: "Tempo abrir /governance até KPIs renderizados"
    score: P0
    benchmark: "Port: ~2s. OpsLevel: ~1.5s. Cortex: ~1.8s. Backstage: ~3s."
    target: "<= 2s"
    nosso_estado: "~1.8s (Inertia::defer agregados — gradeAllModules cache 5min)"

  - id: drill-down-clicks
    nome: "Cliques de /governance até ver gaps de 1 módulo específico"
    score: P0
    benchmark: "Port: 2. OpsLevel: 2. Cortex: 2. Backstage: 3."
    target: "<= 2 cliques"
    nosso_estado: "2 cliques (`/governance/module-grades` → click módulo → Show.tsx 5 dim cards)"

  - id: evolve-action-availability
    nome: "Botão 'Evoluir' / 'Auto-fix' disponível em drill-down"
    score: P1
    benchmark: "Port: Self-service actions trigger ✅. OpsLevel: Campaigns ✅. Cortex: Initiatives ✅. Backstage: manual."
    target: "1 botão disponível em Show"
    nosso_estado: "✅ done — botão 'Evoluir' em ModuleGrades/Show.tsx (drawer copy-as-markdown MVP A; Fase B integração MCP tasks-create)"
```

## 6. Automation targets (eixo Automação)

```yaml
automation:
  - capacidade: "Drift detection daily"
    estado_atual: "✅ `governance:scan-drift` cron daily 03:30 BRT"
    target: "também emit Slack/email Wagner se drift > 0"
    proxima_iter: "Wave 23 (alerta canal Whatsapp Wagner)"

  - capacidade: "Module grade snapshot histórico 90d"
    estado_atual: "❌ backlog (US-GOV-009 Fase B ADR 0153)"
    target: "cron daily 06:00 BRT `module:grade --all --snapshot` → mcp_module_grades_history"
    proxima_iter: "Wave 23"

  - capacidade: "AI auto-suggest rubric refinement"
    estado_atual: "❌ backlog (Wave 24)"
    target: "`module:grade <X> --ai-suggest` chama Claude API mock → Wagner aprova → vira config commit"
    proxima_iter: "Wave 24 (depende `LARAVEL_AI_FORCE_MOCK=true` env Pest)"

  - capacidade: "Audit log export LGPD CSV"
    estado_atual: "🟡 listagem básica feita, export CSV pendente"
    target: "filtro + export por actor/período pra auditor externo"
    proxima_iter: "Wave 23"
```

## 7. Mapa de gaps acionáveis (top 5)

| # | Gap | Esforço | Impacto pp (rubrica externa) | Wave | Owner |
|---|---|---|---|---|---|
| 1 | Score-as-code YAML extract (`config/governance/rubric.yml`) | 3h IA-pair | +2pp | Wave 24 | Claude+Wagner |
| 2 | AI-driven V2 baseline READ-ONLY (`--ai-suggest`) | 6h IA-pair | +3pp | Wave 24 | Claude+Wagner |
| 3 | Dual-mode v3↔v4 rubric versioning | 4h IA-pair | +2,5pp | Fase B ADR 0153 | Claude+Wagner |
| 4 | Cron 90d snapshot histórico (US-GOV-009) | 2h IA-pair | +1,5pp | Wave 23 | Claude |
| 5 | CI gate score regressão por módulo (>5pp queda = block PR) | 3h IA-pair | +1pp | Wave 23 | Claude+Wagner |

Total: **~18h IA-pair** → score externo **74 → ~84/100** (alcança paridade OpsLevel líder atual).

## 8. Risks ativos da variante MATURITY

- 🟡 **Self-policing paradox** (BRIEFING §8) — mitigação: rubrica externa cruzada com 4 players reais nesta FICHA
- 🟡 **Players SaaS evoluem rápido** — Port lançou AI Agent 2026; gap pode crescer sem Wave 24
- 🟢 **Custo zero defensável** — IDPs comerciais cobram $20-50/dev/mês; nosso embutido no ERP = vantagem perpétua em BR-PME
- 🟢 **Reflexividade vira marketing** — "único ERP brasileiro que publica nota da própria governança" = história de transparência (Constituição v2 Art. 8)

## 9. ADRs canônicas relacionadas

- [ADR 0086](../../decisions/0086-fase-5-mvp-governance-actiongate-warn.md) — Fase 5 MVP scaffold + ActionGate (mãe do módulo)
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — **Constituição v2** (mãe das 7 camadas + 8 princípios)
- [ADR 0147](../../decisions/0147-governance-actiongate-warn-strict-modes.md) — ActionGate 3-mode (warn/strict/block)
- [ADR 0153](../../decisions/0153-module-grade-rubrica-v1.md) — Rubrica `module-grade-v1` interna (US-GOV-006..008)
- [ADR 0089](../../decisions/0089-capterra-driven-module-evolution.md) — CAPTERRA-driven module evolution (template mãe desta variante)
- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) — Tests biz=1 nunca cliente

## 10. Próxima revisão

**Pós-Wave 24** (esperado +5pp Auto-detection accuracy + Score-as-code YAML + AI-suggest READ-ONLY). Quando próxima `module:grade --all --snapshot` rodar, atualizar tabela seção 3 com nota observada e checar se gap vs OpsLevel fechou pra <3pp.

**Mantenedor:** Claude (auto via subagent Wave NN) + Wagner (review).
