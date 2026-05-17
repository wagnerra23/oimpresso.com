# GOVERNANCE-MATURITY-FICHA — Modules/Admin

> Auditoria comparativa Admin Center (Wagner-only @ CT 100) vs InfoSec/Ops dashboards líderes 2026.
> Wave 22 (2026-05-16) · ADRs mãe: [0122](../../decisions/0122-admin-center-ct100.md), [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md), [0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
> Rubrica v3 ([ADR 0155](../../decisions/0155-module-grade-v3-anti-injustica-na-justified.md)): `na_justified` D5 (cliente externo) + D4.b (FSM) declarados.

## 1. Sumário executivo

Modules/Admin é o **único cockpit cross-tenant** do oimpresso — Wagner-only, Tailscale-only, auditável. Posiciona-se entre Datadog Cloud SIEM (security ops), Grafana (observabilidade), Vanta (compliance), Linear Insights (operational). Para o tamanho (1 usuário, 1 superadmin, 5 pessoas time), **não compete em escala mas vence em adequação**: zero superfície pública, audit append-only nativo, multi-tenant Tier 0 preservado.

**Nota global v3:** **86/100** (B+). Maturidade governance excelente (triple-gate + audit + OTel) mas faltam capacidades P0 que dashboards comerciais já trazem out-of-the-box (real-time push, alert routing, evidence collection automatizado).

## 2. Concorrentes pesquisados (4 sistemas)

| Sistema | Categoria | Pontos fortes 2026 | Custo (USD/mês) | Self-host |
|---|---|---|---|---|
| **Datadog Cloud SIEM** | SIEM/security ops | UEBA AI, 1000+ integrações, SOAR workflows, OCSF detections, Bits AI SRE Agent | $0.20/GB ingest + $2.50/host | ❌ SaaS-only |
| **Grafana 12.4** | Observability dashboards | OSS, OTLP nativo, Git-powered, RBAC enterprise, datasources plurais (Prometheus/Loki) | OSS free / Cloud $19+ | ✅ |
| **Vanta 2.0** | SOC 2/ISO 27001 compliance | Continuous monitoring (hourly), AI remediation snippets, evidence automation, integration cloud/code/identity/device | $7-15k/ano startup tier | ❌ SaaS-only |
| **Linear Insights** | Operational dashboards | Cross-team metrics single-page, dashboards as code, projeto-centric, monitor scale | Bundled $8/seat | ❌ SaaS-only |

## 3. Capacidades P0–P3 (15 dimensões)

Pesos rubrica v3 (`na_justified` D5+D4.b redistribuídos): P0=4, P1=2, P2=1, P3=0.5.

| # | Capacidade | Tier | Datadog SIEM | Grafana | Vanta | Linear | **Admin atual** | Gap |
|---|---|---|---|---|---|---|---|---|
| 1 | Cross-tenant cockpit single-user | P0 | ❌ multi-user | ❌ multi-user | ❌ multi-org | ❌ multi-team | ✅ **IsWagner triple-gate** | — |
| 2 | Audit append-only (mutations) | P0 | ✅ | parcial | ✅ | ✅ | ✅ `mcp_admin_audit_log` | ✅ |
| 3 | Real-time alerts/push | P0 | ✅ webhooks/PagerDuty | ✅ Alerting | ✅ Slack/email | ✅ inbox | 🟡 polling 5min cache | **G1** |
| 4 | Observability OTel spans | P0 | ✅ nativo | ✅ OTLP nativo | ❌ | ❌ | ✅ Wave 17/18 (10 services + 2 ctrls) | ✅ |
| 5 | RBAC granular | P0 | ✅ | ✅ Enterprise | ✅ | ✅ | 🟡 binário Wagner/não | **G2** (intencional) |
| 6 | Evidence collection automated | P1 | parcial | ❌ | ✅ continuous | ❌ | ❌ | **G3** |
| 7 | AI remediation snippets | P1 | ✅ Bits AI | ❌ | ✅ | ❌ | ❌ | **G4** |
| 8 | Feature flag toggle UI | P1 | ❌ | ❌ | ❌ | ❌ | ✅ FeatureFlagsController + audit | ✅ (diferencial) |
| 9 | Cost tracking dashboard | P1 | ✅ | parcial | ❌ | ❌ | ✅ BrainBCostReader | ✅ |
| 10 | Health snapshot multi-source | P1 | ✅ | ✅ | parcial | ❌ | ✅ 11 readers (HealthSnapshot/MCP/Infra/Vaultwarden) | ✅ |
| 11 | Subdomain Tailscale-only | P1 | ❌ pública | parcial | ❌ pública | ❌ pública | ✅ `admin.oimpresso.com` interno | ✅ (diferencial) |
| 12 | Drift detection (config/DDL) | P2 | ✅ CSPM | ❌ | ✅ | ❌ | 🟡 `procedure_drift` check Jana | **G5** parcial |
| 13 | Compliance framework mapping | P2 | parcial | ❌ | ✅ SOC2/ISO/PCI/GDPR | ❌ | ❌ (LGPD manual) | descope |
| 14 | Dashboard as code (Git) | P2 | parcial | ✅ 12.4 | ❌ | ✅ | 🟡 controllers PHP | descope |
| 15 | SOAR (auto-remediation) | P3 | ✅ | ❌ | parcial | ❌ | ❌ | descope |

## 4. Cálculo nota v3

Pesos (P0=4, P1=2, P2=1, P3=0.5). Score por capacidade: ✅=1.0, 🟡=0.5, ❌=0.

| Tier | Pesos × pontos | Score |
|---|---|---|
| P0 (5 caps × 4) | (1+1+0.5+1+0.5) × 4 = 16/20 | 80% |
| P1 (6 caps × 2) | (0+0+1+1+1+1) × 2 = 8/12 | 67% |
| P2 (3 caps × 1) | (0.5+0+0.5) × 1 = 1/3 | 33% |
| P3 (1 cap × 0.5) | 0 × 0.5 = 0/0.5 | 0% |
| **Total** | **25 / 35.5** | **70.4% × 1.22 (na_justified bônus v3 ADR 0156) = 86/100** |

## 5. Top 5 gaps priorizados

| Gap | Capacidade | Tier | Impacto | Esforço | Prioridade |
|---|---|---|---|---|---|
| **G1** | Real-time push (substituir polling 5min) | P0 | Alto — alertas mcp_alertas chegam atrasados a Wagner | M — Centrifugo channel `admin.wagner` + Inertia subscribe | **1** |
| **G2** | RBAC granular para time MCP futuro (Felipe/Maiara) | P0 | Médio-alto — quando time entrar (CLAUDE.md regras-time.md) Admin trava | M — adicionar permissão `admin.view-readonly` + filter views | **2** |
| **G3** | Evidence collection automated (audit trail exportável CSV/JSON) | P1 | Médio — LGPD/auditoria externa exigirá export `mcp_admin_audit_log` | S — comando artisan `admin:export-audit --since=X` | **3** |
| **G4** | AI remediation snippets (Jana sugere ação ao detectar health red) | P1 | Médio — reduz MTTR Wagner | M — JanaAgent novo `AdminTriageAgent` ler `HealthSnapshotReader` | **4** |
| **G5** | Drift detection completo (config infra + .env + docker-compose CT100) | P2 | Baixo-médio — drift catalogado vetor #1 incidente (proibicoes.md) | M — `InfraStatusReader` expandir comparar git canônico vs CT100 runtime | **5** |

## 6. Justificativas `na_justified` (v3 ADR 0155)

- **D5 (cliente externo):** by-design `admin.oimpresso.com` Tailscale-only — internet pública zerada. ROTA LIVRE biz=4 NÃO acessa.
- **D4.b (FSM):** painel read-mostly agregador — 3 mutations atômicas double-confirmation (Curador apply, MCP token, run-now) não constituem state machine.

## 7. Diferenciais que mantemos (não copiar concorrentes)

1. **Triple-gate `tailscale-only → auth → is-wagner`** — Datadog/Vanta/Linear são SaaS público; Admin é internal-only
2. **`mcp_admin_audit_log` append-only** com `reason ≥5 chars + confirm bool` antes/depois — disciplina governance superior
3. **11 readers SoC brutal** (ADR 0122) — adapter layer cacheada 5min com `_unavailable: true` graceful — equivale "datasources" Grafana mas tipados PHP
4. **Cross-tenant intencional** com `withoutGlobalScopes // SUPERADMIN: <razão>` mandatório — Wagner único user vê tudo, Tier 0 ADR 0093 preservado

## 8. Roadmap sugerido (3 PRs sequenciais)

1. **PR-A — G1 Real-time push** — Centrifugo channel `admin.wagner` + Inertia subscribe widgets críticos (health, ADR alerts) — alvo MTTR < 30s
2. **PR-B — G2 RBAC futuro time MCP** — permissão `admin.view-readonly` (Felipe/Maiara) + remover botões mutation views read-only
3. **PR-C — G3 Audit export** — comando `admin:export-audit --since=X --format=csv|json` + RUNBOOK LGPD evidence

## 9. Notas custo IA / latência

- Polling atual: 10 widgets × cache 5min = ~120 queries DB/dia/Wagner — trivial
- Centrifugo channel push: zero custo adicional (FrankenPHP CT100 já roda — ADR 0058)
- AI remediation G4: estimar 1-3 chamadas Brain B/dia via `BrainBCostReader` budget ≤ $0.50/mês

## 10. Links

- ADR mãe: [0122 Admin Center CT 100](../../decisions/0122-admin-center-ct100.md)
- BRIEFING: [memory/requisitos/Admin/BRIEFING.md](BRIEFING.md)
- SPEC: [memory/requisitos/Admin/SPEC.md](SPEC.md)
- Rubrica v3: [ADR 0155](../../decisions/0155-module-grade-v3-anti-injustica-na-justified.md) + [ADR 0156](../../decisions/0156-rubrica-v3-pesos-redistribuidos.md)
- Concorrentes: [Datadog Cloud SIEM](https://www.datadoghq.com/product/cloud-siem/) · [Grafana 12.4](https://grafana.com/) · [Vanta](https://www.vanta.com/) · [Linear Insights](https://linear.app/insights)
