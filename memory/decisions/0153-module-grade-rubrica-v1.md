---
slug: 0153-module-grade-rubrica-v1
number: 0153
title: "Rubrica oficial `module-grade-v1` — nota 0-100 ponderada pra cada Module"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-16
module: Governance
quarter: 2026-Q2
tags: [governance, qualidade, audit, dashboard, dim-5-pesos-100]
supersedes: []
supersedes_partially: []
superseded_by: []
related: [0093, 0094, 0101, 0105, 0143]
pii: false
review_triggers:
  - Quando pesos das 5 dimensões precisarem ajuste (ex: receita por módulo vira mensurável)
  - Quando dimensão nova for incluída (Performance, LGPD, ROI financeiro)
  - Quando média projeto bater 70+ (sinal que rubrica saturou — precisa v2)
---

# ADR 0153 — Rubrica oficial `module-grade-v1` — nota 0-100 ponderada pra cada Module

## Contexto

Projeto tem 34 Modules com maturidade desigual (auditoria sessão 2026-05-15→16):
- 2 excelentes (80+): Whatsapp, NfeBrasil
- 8 bons (60-79): Jana, ComunicacaoVisual, KB, Repair, OficinaAuto, RecurringBilling, Arquivos, Vestuario
- 5 médios (40-59): Auditoria pós-Wave, Ponto, ADS, Admin, Cms
- 15 críticos (20-39): Crm, Manufacturing, NFSe, Connector, Accounting, Superadmin, Officeimpresso, SRS, Brief, Governance, ConsultaOs, ProductCatalogue, TeamMcp, ProjectMgmt, Essentials
- 3 embriões (<20): AssetManagement, Spreadsheet, Woocommerce

Sem rubrica formal, conversas sobre "prioridade" viram subjetivas. Wagner precisa de:
- **Critério único reusável** pra avaliar qualquer módulo
- **Comparabilidade** entre módulos
- **Drill-down** que mostra ONDE está o gap (qual dimensão)
- **Ação canônica** pra evoluir (botão "Evoluir" gera tasks priorizadas no MCP)
- **Tracking 90d** pra ver evolução do projeto

Sessão atual aplicou rubrica ad-hoc (5 dimensões, pesos 100). Funcionou empiricamente — Wagner pediu pra formalizar.

## Decisão

Aceitar **`module-grade-v1`** como rubrica canônica oficial pra avaliar maturidade de qualquer `Modules/<X>/` do oimpresso.

### Dimensões (peso = 100)

| Dim | Peso | O que mede | Como pontua |
|---|---|---|---|
| **D1. Multi-tenant Tier 0** | **30** | Isolamento `business_id` real ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)) | (a) BusinessScope global em Entities críticas: 10 / (b) cross-tenant Pest biz=1 vs biz=99 ([ADR 0101](0101-tests-business-id-1-nunca-cliente.md)) cobrindo ≥50% Entities: 15 / (c) Jobs assíncronos recebem `$businessId` no constructor: 5 |
| **D2. Pest cobertura** | **20** | Quantidade × qualidade × surface | (a) Razão `tests/Controllers ≥ 0.5`: 8 / (b) Tem MultiTenant + Smoke + Scaffold canônicos: 8 / (c) Registrado em phpunit.xml CI: 4 |
| **D3. Documentação canônica** | **15** | SPEC + BRIEFING + Charter + ADR | (a) `memory/requisitos/<X>/SPEC.md` com US-XXX-NNN: 5 / (b) `BRIEFING.md` 1-pager atualizado ≤90d: 5 / (c) Charter por tela `.charter.md` ≥30% telas: 3 / (d) ADR mãe declarada referenciando módulo: 2 |
| **D4. Maturidade arquitetura** | **20** | Service + FSM + Inertia + Observability | (a) Razão `Services/Controllers ≥ 0.3` (não fat Controller): 6 / (b) FSM canônica se aplicável ([ADR 0143](0143-fsm-pipeline-live-prod-marco-2026-05-12.md)): 5 / (c) Pages Inertia `.tsx` ≥ Blade `.blade.php` legacy: 5 / (d) AuditLog + OTel telemetry presente: 4 |
| **D5. Cliente real + criticidade** | **15** | Sinal qualificado ([ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md)) | (a) biz=4 ROTA LIVRE prod com volume mensurado: 15 / (b) biz=1 Wagner ativo diário: 10 / (c) Cliente piloto reportando dor (sinal qualificado): 8 / (d) Backlog hipótese sem cliente: 3 / (e) Ninguém usa: 0 |

**Total normalizado:** 0-100.

### Buckets qualitativos

| Faixa | Label | Tradução operacional |
|---|---|---|
| **80-100** | **Excelente** | World-class. Cliente prod + cobertura + doc + arq. Manter (CONSOLIDAR) |
| **60-79** | **Bom** | Funciona em prod ou pré-prod com gaps menores. EVOLUIR seletivo |
| **40-59** | **Médio** | Gap óbvio em ≥1 dimensão. Priorizar fix da dimensão mais fraca |
| **20-39** | **Crítico** | Gap em ≥2 dimensões. Pacote de melhoria estruturado |
| **0-19** | **Embrião** | Scaffold sem cliente nem cobertura. Decidir: investir ou descontinuar |

### Implementação canônica

1. **Service:** `Modules/Governance/Services/ModuleGradeService.php` — método `gradeModule(string $name): ModuleGrade` retorna value object com nota total + breakdown 5 dimensões + lista gaps priorizados
2. **Command:** `php artisan module:grade [name] [--all] [--json]` invoca Service
3. **UI:** `/governance/module-grades` (Inertia Page tabela) + `/governance/module-grades/{module}` (drill-down + botão Evoluir)
4. **Botão Evoluir (MVP A):** gera batch `tasks-create` no MCP server priorizando gaps top 3 da dimensão mais fraca. Wagner revisa via `my-work`. Claude Code executa via Waves paralelas (pattern PRs #945/#946)
5. **Skill `avaliar-modulo`** (Tier B): auto-trigger quando Wagner pede "/avaliar-modulo X" ou "nota do módulo X"

### Coleta automática vs manual

| Dimensão | Coleta | Fonte |
|---|---|---|
| D1.a BusinessScope | **Auto** | Grep `extends BusinessScope` em `Modules/<X>/Entities/*.php` |
| D1.b Cross-tenant tests | **Auto** | Grep `biz=99\|BIZ_FICTICIO\|withoutGlobalScopes` em `Modules/<X>/Tests/` |
| D1.c Jobs business_id | **Auto** | Grep `__construct.*\$businessId` em `Modules/<X>/Jobs/*.php` |
| D2.a-c Pest | **Auto** | `find Modules/<X>/Tests + grep phpunit.xml + count Controllers` |
| D3.a SPEC | **Auto** | `[ -f memory/requisitos/<X>/SPEC.md ]` |
| D3.b BRIEFING | **Auto + staleness** | File mtime ≤ 90d |
| D3.c Charter | **Auto** | `find resources/js/Pages/<X> -name "*.charter.md"` |
| D3.d ADR mãe | **Auto** | Grep módulo nome em frontmatter `module:` de `memory/decisions/*.md` |
| D4.a Services | **Auto** | `find Modules/<X>/Services + ratio com Controllers` |
| D4.b FSM | **Auto** | Grep trait `GuardsFsmTransitions` ou tabela `sale_processes` por business |
| D4.c Inertia ratio | **Auto** | `find resources/js/Pages/<X>/*.tsx vs resources/views/<x>/*.blade.php` |
| D4.d AuditLog/OTel | **Auto** | Grep `LogsActivity\|activity(` + `OpenTelemetry\|otel_span` |
| **D5. Cliente** | **Manual primeira versão** | Wagner marca manualmente em `config/governance/module_clients.yaml` |

### Tracking 90d

- Cada execução `php artisan module:grade --all` grava snapshot em tabela `mcp_module_grades_history (module, score, dim1..dim5, snapshot_at)`
- Cron daily 06:00 BRT roda `module:grade --all` (alinhado com `jana:health-check`)
- Goal canônico CYCLE: "elevar média projeto de 41 → 60 em 60d"
- Dashboard sparkline 90d por módulo

## Justificativa

- **Pesos 30/20/15/20/15:** refletem prioridade Tier 0 IRREVOGÁVEL (Multi-tenant pesa mais) + valor de cobertura (Pest 20) + valor de cliente real (15 — alta porque ADR 0105 sinal qualificado guia o que se faz). Doc e Arq ficam em 15-20 (importantes mas não bloqueadores).
- **5 dimensões e não mais:** dimensões testáveis 10 minutos via bash/grep. Mais dimensões = mais coleta manual = decai de uso. v2 pode adicionar Performance/LGPD/ROI quando dados objetivos existirem.
- **Buckets 5 faixas:** facilita conversa ("módulo X está crítico, módulo Y é embrião"). Cores natural pra UI.
- **Botão Evoluir MVP A (gera tasks):** evita mágica falsa. Cria tasks priorizadas que Claude Code (com você ou Felipe) executa. Pode evoluir pra Brain B quando ADR 0035 maturo + cliente reportando dor justifique custo.
- **Cliente manual:** sem source autoritativo hoje, melhor honesto. Wagner edita YAML. Quando Modules/Brief gerar volume/módulo automático, vira coleta automática.

## Consequências

**Positivas:**
- Conversas viram objetivas — "Crm 38 vs Vestuario 63" não tem opinião pra discordar
- Goal CYCLE mensurável — "média 41 → 60" trackável daily
- Gate de governança natural — PR que move módulo pra bucket inferior flag automático
- Onboarding Felipe/Maiara — abrem `/governance/module-grades` veem prioridade sem perguntar
- Time MCP futuro tem rubrica única — sem caos de critérios pessoais

**Negativas / Trade-offs:**
- Subjetividade residual em D4.a-d (interpretar "Service layer" varia)
- D5.a "volume mensurado" depende de Brief gerar dado — primeiros meses Wagner marca manual
- Risco de gaming — alguém pode adicionar Pest fraco só pra subir D2 sem real valor (mitigação: revisão PR + skill `module-completeness-audit`)
- Pesos podem precisar ajuste após 30d uso real — v2 vira ADR 0154 (append-only)
- 5min de execução `module:grade --all` × 34 módulos pode ser lento — otimizar com cache se necessário

**Riscos mitigados:**
- ⛔ "Prioridade subjetiva" sem rubrica → ADR 0153 cria rubrica única
- ⛔ "Wagner decide tudo manualmente" → tela `/governance/module-grades` self-service
- ⛔ "Gap escondido por boa narrativa" → 5 dimensões objetivas expõem onde dói
- ⛔ "Time novo sem contexto" → buckets + breakdown ensina o que importa no projeto

## Como evoluir (v2 candidatos)

- **+ Dimensão Performance** (peso 5-10) — p99 response time, partial reload, defer pattern, time-to-interactive
- **+ Dimensão LGPD** (peso 5-10) — PII redaction, audit log, retention policy, consent tracking
- **+ Dimensão ROI financeiro** (peso 10) — receita gerada vs custo dev/mês (quando Brief gerar dado)
- **Penalidade negativa** — descontar 5 pts por incident crítico catalogado nos últimos 90d
- **Bonus** — +10 pts se módulo entrou em CONSOLIDAR sustentado 60d

Cada evolução vira ADR 0154+ append-only.

## Referências

- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL (peso máximo em D1)
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (Governance enforcer canônico)
- [ADR 0101](0101-tests-business-id-1-nunca-cliente.md) — Tests biz=1 nunca cliente (D1.b validação)
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal qualificado (D5 baseado nisso)
- [ADR 0143](0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM Pipeline canônico (D4.b)
- PR #945 + #946 — Waves A/B Pest cobertura 34/34 que viabilizam D1.b + D2 medíveis
- `memory/sessions/2026-05-15-inventario-pest-coverage.md` — auditoria empírica que motivou a rubrica
- `memory/handoffs/2026-05-16-0000-pest-cobertura-34de34-modulos-waves-AB.md` — sessão de origem

---

**Próxima ação Wagner:** revisar ADR + ajustar pesos se quiser ANTES de marcar `status: accepted` → habilita Service + Command + UI implementarem.
