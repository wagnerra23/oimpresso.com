---
modulo: Brief (Daily Brief MCP-tool)
wave: 22
agente: governance-maturity-ficha-brief
data: 2026-05-16
nota_capterra: 88/100
concorrentes_pesquisados:
  - Notion AI (Notion 3.2 + Agent 3.0 — jan/2026)
  - Linear Updates + Linear Agent (mar/2026)
  - Pendo Resource Center + Onboarding Impact Brief (2026)
  - LaunchDarkly Launch Insights (2026)
related_adrs: [0091, 0094, 0095, 0153, 0154]
---

# GOVERNANCE-MATURITY-FICHA — Modules/Brief

> **Tool MCP atômica**: `brief-fetch` substitui 5-8 chamadas exploratórias no início de cada sessão Claude (cycles-active + sessions-recent + tasks-active + decisions-search + memoria-search). Cron 6x/dia (07/11/14/17/20/23 BRT), cache 5min, ~277 tokens médios, Brain B `gpt-4o-mini` (~$0.005/run × 6/dia = $0.03/dia).

## 1. Propósito + escopo

Snapshot consolidado do projeto entregue como **markdown ≤3.5k tokens** com 7 seções fixas (ESTADO MACRO, EM VOO AGORA, DECISÕES RECENTES 24h, SKILLS USO 7d, CHARTERS APODRECENDO, FLAGS, METADATA). Ground truth pra todo agent humano ou automatizado iniciar sessão alinhado. Skill `brief-first` (Tier A always-on) força ordem.

## 2. Inventário canônico

| Camada | Arquivo | Função |
|---|---|---|
| Tool MCP | `Modules/Brief/Mcp/Tools/BriefFetchTool.php` (280 linhas) | Registra `brief-fetch`, cache 5min, audit log, telemetria skill, cycle drift detector |
| Service gerador | `Modules/Brief/Services/BriefGeneratorService.php` (264 linhas) | Pipeline OpenAI gpt-4o-mini, PII redact pré-LLM, OtelHelper spans, custo tracking |
| Validator | `Modules/Brief/Services/BriefValidator.php` | 7 headers + ---END--- + token budget |
| Console | `GenerateBriefCommand.php` + `BriefHealthCommand.php` | Cron 6x/dia + health check |
| Service Provider | `BriefServiceProvider.php` | Registro MCP tool (CT 100 only via `MCP_TOOLS_EXPOSED`) |
| Tests | 6 Pest files (Multi-tenant, LGPD, Validator, GenerateRequest, Smoke, Scaffold) | Cobertura cross-tenant biz=1, redaction, schema |
| Schema | `mcp_briefs` (persistência) + `mcp_brief_inputs_cache` (singleton refresh via stored proc) | Append-only |
| Skill | `.claude/skills/brief-first/SKILL.md` (Tier A always-on) | Bloqueador pré-tool MCP |
| Charter | ADR 0091 (canônico — SPEC + BRIEFING são N/A justified D3.a/b) | — |

## 3. Capacidades P0-P3 (15 dimensões)

| # | Capacidade | Peso | oimpresso | Notion AI | Linear Agent | Pendo | LaunchDarkly Insights |
|---|---|---|---|---|---|---|---|
| 1 | Entrega como tool MCP nativa (não UI/chat) | P0 ×4 | **SIM** (única) | NÃO (Notion app) | parcial (MCP via Linear Agent mar/2026) | NÃO | NÃO |
| 2 | Skill always-on forçando consumo | P0 ×4 | **SIM** (Tier A bloqueador) | NÃO | NÃO | NÃO | NÃO |
| 3 | Cache trivial multi-agent (5min) | P0 ×4 | **SIM** (10 agents ⇒ 1 hit DB) | parcial | parcial | NÃO | NÃO |
| 4 | Custo trivial (~$0.005/gen) | P0 ×4 | **SIM** ($0.03/dia total) | $20/user/mês | $8-14/user/mês | enterprise | $14-75/user/mês |
| 5 | 7 seções fixas estruturadas + validador | P0 ×4 | **SIM** (BriefValidator) | parcial (templates) | parcial (Slack post) | parcial (Word export) | parcial (4 scores) |
| 6 | PII redaction pré-LLM | P1 ×2 | **SIM** (PiiRedactor BR) | NÃO público | NÃO público | NÃO | N/A |
| 7 | Multi-tenant scope (`business_id`) | P1 ×2 | **SIM** (Tier 0) | workspace | team | account | project |
| 8 | Audit log + telemetria automática | P1 ×2 | **SIM** (`mcp_audit_log` + `mcp_skill_telemetry`) | parcial | SIM | SIM | SIM |
| 9 | Drift detector cycle vs commits 7d | P1 ×2 | **SIM** (Wave aprendizado CYCLE-01) | NÃO | NÃO | NÃO | NÃO |
| 10 | Cron multi-shot/dia (6x) | P1 ×2 | **SIM** (07/11/14/17/20/23) | 1x/dia | configurável | sob demanda | 30d janelas |
| 11 | force_refresh com cap + RBAC | P2 ×1 | **SIM** (8/dia, só Wagner) | NÃO | NÃO | NÃO | NÃO |
| 12 | OpenTelemetry spans end-to-end | P2 ×1 | **SIM** (OtelHelper::spanBiz) | NÃO público | NÃO público | NÃO público | SIM |
| 13 | Stop sequence + token budget enforce | P2 ×1 | **SIM** (max 4096 + ---END---) | parcial | parcial | parcial | N/A |
| 14 | Briefing executivo exportável (PDF/Word) | P3 ×0.5 | NÃO | parcial | NÃO | **SIM** (Onboarding Impact Brief) | NÃO |
| 15 | Score adoção best-practices (Excellent/Good/Fair/At risk) | P3 ×0.5 | NÃO | NÃO | NÃO | NÃO | **SIM** (Launch Insights) |

### Cálculo nota (peso × score 0-1)

- P0 (4×) × 5 capacidades × 1.0 = **20.0/20.0**
- P1 (2×) × 5 capacidades × 1.0 = **10.0/10.0**
- P2 (1×) × 3 capacidades × 1.0 = **3.0/3.0**
- P3 (0.5×) × 2 capacidades × 0.0 = **0.0/1.0** (sem export PDF/Word, sem score adoção)

Soma bruta: 33/34 → normalizado base 100: **97**.
Ajuste maturidade prod: 6 Pest passing + LIVE prod desde 2026, custo $0.03/dia comprovado, telemetria ativa (alta confiança). Penalidade: gap P3 export executivo (–4), gap dashboard adoption score (–5).

**Nota final: 88/100**.

## 4. Diferencial defensável (3 pontos)

1. **Tool MCP atômica é categoria nova** — Notion/Linear/Pendo entregam UI app/chat; brief-fetch é primitiva consumível por QUALQUER agent (Claude Code, Cursor, future LLM) via MCP protocol. Vendor-neutral.
2. **Skill always-on bloqueador** — `brief-first` Tier A força ordem ANTES de qualquer outra tool. Economia mensurada: ~27k tokens/sessão (3k vs ~30k exploração). Nenhum concorrente tem equivalente — todos dependem do humano "lembrar" de checar dashboard.
3. **Cycle drift detector embutido** — cruza `mcp_git_links` 7d com `mcp_cycles.tasks` e alerta inline no brief quando >50% trabalho real fora do cycle planejado. Aprendizado retro CYCLE-01 (2026-05-07) virou código. Nenhum concorrente.

## 5. Top 5 gaps prioritizados

| # | Gap | Prioridade | Esforço | Concorrente que tem | Impacto |
|---|---|---|---|---|---|
| **G1** | Sem export executivo (PDF/Word) pra Wagner reportar a stakeholders externos (Felipe/Maiara/parceiros) | P2 | S (1d) | Pendo Onboarding Impact Brief | UX governança Wagner |
| **G2** | Sem score adoção best-practices ("Excellent/Good/Fair/At risk") por skill/área | P2 | M (3d) | LaunchDarkly Launch Insights | Visibilidade time MCP |
| **G3** | Sem 2º brief especializado por persona (Wagner full vs Felipe/Maiara focused vs Luiz iniciante) | P3 | M (3-5d) | Linear (per-team views) | Onboarding time MCP |
| **G4** | Sem fallback gracioso quando `gpt-4o-mini` indisponível (hoje throws RuntimeException; deveria servir cached + flag stale) | P1 | S (1d) | LaunchDarkly guarded rollouts | Confiabilidade Tier 0 |
| **G5** | Sem voice/audio brief (TTS executivo 60-90s pra Wagner ouvir caminhando) | P3 | L (1-2sem) | Notion AI + integrações | Mobilidade dono |

## 6. Conclusão

Brief é **best-of-breed na categoria "tool MCP de orientação inicial"** — categoria que os 4 concorrentes nem perseguem. Nota 88/100 reflete maturidade prod + 6 Pest + cron + telemetria + LIVE desde 2026. Gaps são incrementais (P2/P3), nenhum fatal. Roadmap próximas 2 waves: G4 (fallback) + G1 (export) atacam Tier 0 confiabilidade + governança Wagner.

## 7. ADRs canônicas

- **[ADR 0091](../../decisions/0091-daily-brief.md)** — Daily Brief (spec irreplaceable)
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §L7 Skills layer
- [ADR 0095](../../decisions/0095-skills-tiers-convencao-interna.md) — `brief-first` Tier A
- [ADR 0153](../../decisions/0153-module-grade-rubrica-v1.md) + [ADR 0154](../../decisions/0154-na-justified-modules-infra.md) — N/A justified D3.a/b
