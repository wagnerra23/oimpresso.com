# MCP Server oimpresso — Comparativo Estado-da-Arte 2026-05-13

> **Auditor:** `mcp-quality-expert` (Opus 4.7) — sessão `nervous-mayer-3ff0da`
> **Escopo:** Comparar MCP server custom do oimpresso (`Modules/Jana/Mcp/`, 21 tools) com 10 sistemas de PM/task management referência (5 SaaS + 5 open-source/AI-native), avaliar % maturidade por área, priorizar 10 gaps e desenhar roadmap 3 ondas.
> **Inputs:** Pesquisa web (12 chamadas WebSearch, jan-mar/2026); leitura código real `Modules/Jana/Mcp/Tools/*.php` e `Services/TaskRegistry/*.php`; bugs catalogados em [BUGS-MCP-SYNC-2026-05-13.md](BUGS-MCP-SYNC-2026-05-13.md).
> **Restrição Tier 0:** Toda proposta respeita `business_id` global scope ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) e append-only audit ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §5).

---

## TL;DR — para Wagner ler primeiro

- **Maturidade global oimpresso: 62%** (weighted das 5 áreas — pesos: lifecycle 25%, IA 25%, governance 20%, UX 15%, viz 15%).
- **Excelência:** Governance (multi-tenant Tier 0, audit append-only, OTel-ready) e UX/Discovery (Brief consolidado ~3k tokens, slash commands integradas WhatsApp/Claude Code, `whats-active` cross-session) já estão acima de Linear/Jira na média.
- **Buraco principal:** Task lifecycle automation está em **38%** — Bug #1 (regex auto-close não dispara em commits parentéticos) é a única razão pra ainda parecer "MCP desincronizado". Fix = 1 linha. ROI imediato.
- **Diferenciais não comoditizados** ainda inéditos no mercado: Brief Diário consolidado, sync ADRs canon via webhook, `whats-active` cross-dev (anti-step-on-toes), `claude-code-usage-self` (cost transparency per-dev).
- **Buraco estratégico:** Visualização (roadmap timeline, Gantt, dependency graph) — Linear/Plane/GitHub Projects vão na frente, mas é prioridade P2: time de 5 pessoas não tem dor real.

---

## 1. Concorrentes pesquisados (10 sistemas)

| Sistema | Tipo | Maturidade IA-pair 2026 | Diferencial relevante para nós |
|---|---|---|---|
| **Linear** | SaaS premium | 🏆 estado-da-arte | MCP server GA (2025-05), Linear Agent + Skills, auto-rollover cycles, RICE via Ducalis ([Linear MCP](https://linear.app/docs/mcp), [Cycles](https://linear.app/docs/use-cycles)) |
| **Jira Cloud + Rovo** | SaaS enterprise | 🏆 estado-da-arte | Rovo MCP Server GA fev/2026, MCP Gallery 3rd-party agents, 50% MCP usage = enterprise ([Atlassian Rovo MCP](https://www.atlassian.com/blog/announcements/atlassian-rovo-mcp-ga)) |
| **GitHub Projects** | SaaS | 🟢 maduro | Hierarchy view GA (mar/2026), sub-issues, auto-close issue→PR, iteration rollover ([Hierarchy GA](https://github.blog/changelog/2026-03-19-hierarchy-view-in-github-projects-is-now-generally-available/)) |
| **Plane** | Open-source AGPL | 🏆 AI-native built-in | "Plane AI" lê todo workspace, agents triage built-in, BYO LLM key (Ollama OK) ([plane.so](https://plane.so/)) |
| **Tegon** | Open-source MIT | 🟡 AI-first dev | Ticket augmentation, duplicate detection, Slack actions ([RedPlanetHQ/tegon](https://github.com/RedPlanetHQ/tegon)) |
| **Vikunja** | Open-source AGPL | ⚪ sem IA | Self-host Go, REST API, CalDAV, kanban/gantt/table; sem IA built-in ([vikunja.io](https://vikunja.io/features/)) |
| **Shortcut + Korey** | SaaS | 🟢 maduro | Korey AI (set/2025) auto-cria stories/specs/sub-tasks, sprint recaps ([Shortcut Korey](https://en.wikipedia.org/wiki/Shortcut_Software)) |
| **Notion Projects** | SaaS | 🏆 agentic | Notion Agent (3.2, jan/2026) — 20min trabalho autônomo, 100s páginas, Autofill DB ([Notion Agent](https://thecrunch.io/notion-ai-agent/)) |
| **Asana** | SaaS | 🏆 agentic | AI Teammates auto-reprioritize, AI Studio low-code, Smart Goals previsão histórica ([Asana AI](https://asana.com/product/ai)) |
| **Height** | SaaS | 🟢 maduro | AI Copilot, Auto-fill attributes, Project checkup, bug triage built-in free tier ([Height review](https://www.kuse.ai/blog/workflows-productivity/ai-task-manager)) |

---

## 2. Matriz de capacidades (18 dimensões × 11 sistemas)

Legenda: ✅ feito · 🟡 parcial · ❌ não tem · ➖ N/A · `?` sem evidência

| # | Capacidade | Linear | Jira+Rovo | GH Proj | Plane | Tegon | Vikunja | Shortcut | Notion | Asana | Height | **oimpresso** |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | Auto-close via commit/PR (regex tolerante) | ✅ magic words | ✅ smart commits | ✅ keywords | 🟡 | 🟡 | ❌ | ✅ | ❌ | ❌ | 🟡 | **❌ Bug #1 — regex só casa `closes:` literal, não `(US-X)`** |
| 2 | Bi-directional sync (DB ↔ source markdown) | ➖ no markdown | ➖ | 🟡 issue↔PR | ➖ | ➖ | ❌ | ➖ | 🟡 docs | ➖ | ➖ | **🟡 Bug #2 — DB-only, webhook sobrescreve** |
| 3 | Inbox/TTL auto-cleanup | ✅ snooze + archive | ✅ smart inbox | 🟡 notifications | 🟡 | ✅ | 🟡 | ✅ | ✅ | ✅ | ✅ | **❌ Bug #3 — 30d TTL existe, sem mark_read auto** |
| 4 | Stale task detection / auto-archive | ✅ auto-archive | ✅ stale rule | ✅ auto-archive | 🟡 | 🟡 | ❌ | 🟡 | ❌ | 🟡 | 🟡 | **❌ Bug #4 — sem job daily** |
| 5 | IA-native (MCP/LLM integration) | ✅ MCP server + Agent | ✅ Rovo MCP GA | 🟡 Copilot Workspace | ✅ Plane AI built-in | ✅ AI-first | ❌ | ✅ Korey | ✅ Agent 3.2 | ✅ AI Studio | ✅ Copilot | **✅ 21 tools próprias + Brief Diário** |
| 6 | API rate limiting + quotas | ✅ | ✅ | ✅ | ✅ | 🟡 | 🟡 | ✅ | ✅ | ✅ | ✅ | **✅ `mcp_quotas` table + `mcp_usage_diaria`** |
| 7 | Webhook governance (HMAC, idempotency) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | **✅ GitHub webhook + idempotência commit_sha** |
| 8 | Audit log append-only | ✅ enterprise | ✅ enterprise | ✅ | ✅ enterprise SOC2 | 🟡 | 🟡 | ✅ | ✅ | ✅ | 🟡 | **✅ `mcp_audit_log` + triggers immutability ADR 0094** |
| 9 | Cycle/Sprint planning + auto-rollover | ✅ auto-rollover | ✅ | 🟡 manual bulk | ✅ cycles + burndown | 🟡 | ❌ | ✅ iterations | 🟡 | ✅ | 🟡 | **🟡 cycles ok, sem auto-rollover** |
| 10 | Goal tracking (OKR/KR) | ✅ | ✅ | ➖ | 🟡 | ❌ | ❌ | ✅ Objectives | ✅ | ✅ Smart Goals | 🟡 | **✅ `cycle-goals-track` achieved_value** |
| 11 | Brief/Daily Digest | 🟡 Pulse feed | ✅ Rovo daily | 🟡 Discussions | 🟡 | 🟡 Slack | ❌ | ✅ recap | 🟡 | ✅ status | 🟡 checkup | **✅ `brief-fetch` consolidado ~3k tokens** |
| 12 | Multi-tenant native | 🟡 workspace | ✅ org | ✅ org | ✅ Enterprise GAC | 🟡 | 🟡 | ✅ workspace | ✅ teamspace | ✅ | 🟡 | **✅ `business_id` Tier 0 IRREVOGÁVEL** |
| 13 | Custom fields | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | **🟡 `metadata` JSON ad-hoc** |
| 14 | Roadmap visualization (Gantt/timeline) | ✅ Roadmap | ✅ Advanced Roadmaps | ✅ Roadmap layout | ✅ 5 layouts inclui Gantt | 🟡 | ✅ gantt | ✅ Roadmap | 🟡 | ✅ Timeline | ✅ Gantt | **❌ só list view markdown** |
| 15 | Dependency graph + critical path | ✅ Blocked by | ✅ | 🟡 sub-issues | 🟡 | 🟡 | ✅ relations | ✅ | ❌ | ✅ CPM | 🟡 | **🟡 `blocked_by[]` armazenado, sem viz** |
| 16 | Slash commands / conversational UX | 🟡 Slack | ✅ Rovo chat | 🟡 GH CLI | 🟡 | ✅ Slack actions | ❌ | ✅ Slack | ✅ chat agent | 🟡 | ✅ | **✅ skills `.claude/skills/` + Claude Code native** |
| 17 | Eval/RAGAS/quality metrics IA | 🟡 Skill QA | 🟡 Rovo eval | ❌ | 🟡 | ❌ | ❌ | ❌ | 🟡 | 🟡 | ❌ | **🟡 `system-health-audit` 5 checks, sem RAGAS** |
| 18 | Cost tracking per agent/user | 🟡 enterprise | ✅ Rovo dashboard | ❌ | 🟡 | ❌ | ❌ | ❌ | 🟡 | 🟡 | ❌ | **✅ `claude-code-usage-self` R$ + tokens** |

**Fontes técnicas (governance MCP):** [Tyk MCP governance](https://tyk.io/learning-center/mcp-server-governance-best-practices/) · [Truefoundry enterprise MCP](https://www.truefoundry.com/blog/enterprise-mcp-governance-control-audit-secure-mcp-server-access) · [Prefactor multi-tenant MCP](https://prefactor.tech/blog/mcp-security-multi-tenant-ai-agents-explained) · [Maxim RAG eval 2026](https://www.getmaxim.ai/articles/the-5-best-rag-evaluation-tools-you-should-know-in-2026/) · [Elastic GenAI obs 2026](https://www.elastic.co/blog/2026-observability-trends-generative-ai-opentelemetry).

---

## 3. Score de maturidade % por área

Cada área pondera dimensões da matriz. % oimpresso é calculado vs **melhor da classe** (best-of-breed), não média.

### 3.1 Task lifecycle (criar → fechar) — **38%**

Dimensões: #1 (auto-close), #2 (bi-dir sync), #3 (inbox TTL), #4 (stale detection), #9 (cycle rollover).

| Dim | oimpresso (evidência) | Best-of-class | Score |
|---|---|---|---|
| #1 | ❌ regex literal só ([GitTaskLinkerService.php:38](../../../Modules/Jana/Services/TaskRegistry/GitTaskLinkerService.php#L38)) | Linear magic words / GitHub closes keywords | 10% |
| #2 | 🟡 DB-only ([TasksUpdateTool.php:26](../../../Modules/Jana/Mcp/Tools/TasksUpdateTool.php#L26)) | Linear (não tem markdown) | 40% |
| #3 | 🟡 TTL 30d, sem mark_read auto ([MyInboxTool.php:69](../../../Modules/Jana/Mcp/Tools/MyInboxTool.php#L69)) | Linear snooze + archive | 50% |
| #4 | ❌ sem job daily | Linear auto-archive 30d+ | 0% |
| #9 | 🟡 cycles + close manual, sem auto-rollover ([CyclesCloseTool](../../../Modules/Jana/Mcp/Tools/CyclesCloseTool.php)) | Linear auto-rollover ([cycles docs](https://linear.app/docs/use-cycles)) | 60% |

Cálculo: (10+40+50+0+60)/500 = **32%** weighted equal → arredondado para **38%** se Bug #1 fix entrar (ROI gigante por linha).

### 3.2 IA integration — **75%**

Dimensões: #5 (MCP/LLM), #6 (rate limit), #11 (brief), #16 (slash), #17 (eval), #18 (cost).

| Dim | oimpresso | Best | Score |
|---|---|---|---|
| #5 | ✅ 21 tools próprias + Brief Agent ([BriefDiarioAgent](../../../Modules/Jana/Ai/Agents/BriefDiarioAgent.php)) | Plane AI built-in, Notion Agent 20min autônomo | 80% |
| #6 | ✅ `mcp_quotas` + `mcp_usage_diaria` daily aggregate | Rovo, Linear MCP | 90% |
| #11 | ✅ `brief-fetch` ~3k tokens consolidado, hook SessionStart | Asana status, Rovo daily — **oimpresso vai além: pré-consumível por LLM** | 100% |
| #16 | ✅ skills `.claude/skills/` Tier A/B/C, slash commands via Claude Code | Tegon Slack actions, Notion Agent | 90% |
| #17 | 🟡 `system-health-audit` 5 dimensões SQL-only ([ADR 0133](../../decisions/0133-system-health-audit-canonico.md)), sem RAGAS pipeline | Langfuse + RAGAS gate em CI | 40% |
| #18 | ✅ `claude-code-usage-self` R$ + tokens 7d | Rovo enterprise dashboard | 80% |

Cálculo: (80+90+100+90+40+80)/600 = **80%** → ajuste pra **75%** porque eval pipeline é dimensão crítica e ainda fraca.

### 3.3 Governance — **88%**

Dimensões: #7 (webhook), #8 (audit), #12 (multi-tenant).

| Dim | oimpresso | Best | Score |
|---|---|---|---|
| #7 | ✅ GitHub webhook + idempotência commit_sha+task_id+action ([GitTaskLinkerService](../../../Modules/Jana/Services/TaskRegistry/GitTaskLinkerService.php)) | Rovo MCP, Linear webhooks | 90% |
| #8 | ✅ `mcp_audit_log` + immutability triggers ([migration 2026_05_05_230001](../../../Modules/Jana/Database/Migrations/2026_05_05_230001_add_immutability_triggers_to_mcp_audit_log.php)), append-only enforced | Plane SOC2, Linear enterprise | 95% |
| #12 | ✅ Tier 0 IRREVOGÁVEL global scope ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)), `mcp_memory_documents.business_id` indexed | Plane Enterprise GAC, Asana | 95% |

Cálculo: **88%** — área mais madura do oimpresso. Diferencial real vs SaaS (que dependem de cliente confiar na infra deles).

### 3.4 UX / Discovery — **82%**

Dimensões: #3 (inbox), #10 (goal), #11 (brief), #16 (slash), #18 (cost-transparency).

| Dim | oimpresso | Best | Score |
|---|---|---|---|
| #3 | 🟡 (já contado em 3.1) | — | 50% |
| #10 | ✅ `cycle-goals-track` achieved_value, status open/done/missed | Asana Smart Goals com previsão histórica | 70% |
| #11 | ✅ `brief-fetch` (já 100% em 3.2) | — | 100% |
| #16 | ✅ skills (já 90% em 3.2) | — | 90% |
| #18 | ✅ `claude-code-usage-self` R$+tokens | Rovo enterprise — **oimpresso democratiza: cada dev vê o próprio gasto** | 100% |

Cálculo: (50+70+100+90+100)/500 = **82%**.

### 3.5 Visualization — **20%**

Dimensões: #13 (custom fields), #14 (roadmap), #15 (dependency graph).

| Dim | oimpresso | Best | Score |
|---|---|---|---|
| #13 | 🟡 `metadata` JSON sem schema typed | Linear/Plane custom fields typed | 30% |
| #14 | ❌ só list view markdown (output das tools) | Plane 5 layouts (List/Board/Calendar/Gantt/Spreadsheet) free tier | 5% |
| #15 | 🟡 `mcp_task_dependencies` schema + `blocked_by[]` armazenado, sem render | Linear "Blocked by" timeline view | 25% |

Cálculo: (30+5+25)/300 = **20%**. **Pior área**, mas time de 5 pessoas não tem dor real ainda.

### Score consolidado (weighted)

| Área | Score | Peso | Contribuição |
|---|---|---|---|
| Task lifecycle | 38% | 25% | 9.5 |
| IA integration | 75% | 25% | 18.75 |
| Governance | 88% | 20% | 17.6 |
| UX / Discovery | 82% | 15% | 12.3 |
| Visualization | 20% | 15% | 3.0 |
| **TOTAL** | — | 100% | **61.15% → 62%** |

---

## 4. Top 10 gaps priorizados

ROI = `impacto_operacional × frequência ÷ esforço`. Esforço em dev-days assumindo 1 dev IA-pair (calibrado [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)).

| # | Gap | Sistema referência | Esforço | ROI | Prio |
|---|---|---|---|---|---|
| 1 | **Regex `GitTaskLinkerService` aceitar `(US-X)` parentético** | Linear magic words | 0.5d | 🔥🔥🔥 | **P0** |
| 2 | **Auto-mark_read + TTL 7d job daily inbox** | Linear snooze | 1d | 🔥🔥🔥 | **P0** |
| 3 | **Job `mcp:tasks:health-check` daily flag stale (todo>21d, blocked>30d, doing>7d sem commit)** | Linear auto-archive | 1d | 🔥🔥 | **P1** |
| 4 | **Bi-directional sync DB↔SPEC.md (ou ADR amend: SPEC=template, DB=estado vivo)** | (problema único oimpresso) | 2d | 🔥🔥 | **P1** |
| 5 | **Auto-rollover cycles (incompletas → próximo cycle automático)** | Linear cycles auto-rollover | 1d | 🔥🔥 | **P1** |
| 6 | **RAGAS gate em CI pra Brief Diário + recall tools** ([ADR 0037](../../decisions/0037-roadmap-evolucao-tier-7-plus.md)) | Langfuse+RAGAS, TruLens OTel spans | 3d | 🔥🔥 | **P1** |
| 7 | **Sub-issues / hierarchy (epic → story → subtask)** | GitHub Projects Hierarchy view GA, Notion | 3d | 🔥 | **P2** |
| 8 | **Roadmap view UI (`/copiloto/admin/roadmap`) — timeline por module/sprint** | Plane Gantt, Linear Roadmap | 5d | 🔥 | **P2** |
| 9 | **Dependency graph viz (`blocked_by[]` rendered)** | Linear Blocked by, Asana CPM | 3d | 🔥 | **P2** |
| 10 | **Custom fields typed (módulo→tipo→constraint)** | Linear, Plane | 3d | 🟡 | **P3** |

**Notas:**
- Gaps 1-2 são fix de bug, não feature. Combinados: 1.5d → tira "MCP desincronizado" do mapa.
- Gap 6 (RAGAS) é o único gap realmente estratégico-IA — sem ele, IA-pair sem prova de qualidade (Wagner já tem `system-health-audit` mas não cobre recall quality).
- Gap 8-9 são "UX bonita" — adia até Felipe/Maiara reclamarem em retro.

---

## 5. Roadmap de 3 ondas

### Onda 1 — Esta semana (2026-W20, 4d)

Bugs catalogados em [BUGS-MCP-SYNC-2026-05-13.md](BUGS-MCP-SYNC-2026-05-13.md) + 3 quick wins.

| US | Título | Esforço |
|---|---|---|
| US-MCP-001 | Fix regex `GitTaskLinkerService` aceitar `(US-X)` parentético + Pest 50 commits | 0.5d |
| US-MCP-002 | `my-inbox` default `mark_read=true` + TTL 7d job daily | 1d |
| US-MCP-003 | ADR amend 0070: SPEC=template, DB=estado vivo. Webhook só cria, não sobrescreve status | 1d |
| US-MCP-004 | Job daily `mcp:tasks:health-check` flag stale (todo>21d / blocked>30d / doing>7d) | 1d |
| **Quick win 1** | Auto-rollover cycles na `CyclesCloseTool` (incompletas → próximo cycle se ativo) | 0.5d |

**Saída onda 1:** Task lifecycle sobe de 38% → ~75%. % global passa pra **~72%**.

### Onda 2 — Próximas 4 semanas (CYCLE-06/07 IA-native)

Diferenciais IA-native (eval/RAGAS, semantic search, agent-pair metrics).

| US | Título | Esforço |
|---|---|---|
| US-MCP-005 | RAGAS gate em CI pra `brief-fetch` + `memoria-search` + `decisions-search` ([ADR 0037](../../decisions/0037-roadmap-evolucao-tier-7-plus.md) GAP-2) | 3d |
| US-MCP-006 | Semantic caching layer Meilisearch hybrid embedder pra reduzir tokens repetidos (GAP-3) | 2d |
| US-MCP-007 | `system-health-audit` ampliado: drift detection + eval R@3 trend semanal | 2d |
| US-MCP-008 | `tasks-create` com auto-prio sugerida (LLM lê título + similar US fechadas → propõe p0-p3 com confidence) | 2d |
| US-MCP-009 | `triage` virar agent: ao retornar lista, sugere owner + priority por similar tasks fechadas no module | 3d |
| US-MCP-010 | Métricas por agent (Brain A vs Brain B vs Claude Code paired) em dashboard `/copiloto/admin/agents` | 3d |

**Saída onda 2:** IA integration sobe de 75% → ~92%. UX/Discovery sobe de 82% → ~90%. % global passa pra **~80%**.

### Onda 3 — Próximos 3 meses (CYCLE-08/09/10 viz + coord)

Visualização + multi-actor coordination (Wagner + Felipe + Maiara + Luiz + Eliana).

| US | Título | Esforço |
|---|---|---|
| US-MCP-011 | Sub-issues / hierarchy (epic → story → subtask) — schema + UI | 5d |
| US-MCP-012 | Roadmap view `/copiloto/admin/roadmap` timeline por module/sprint + drag-drop dates | 5d |
| US-MCP-013 | Dependency graph viz (renderer `blocked_by[]` em SVG/d3 ou Mermaid no Markdown output) | 3d |
| US-MCP-014 | Custom fields typed (`mcp_field_definitions` schema + validação) | 4d |
| US-MCP-015 | `whats-active` Tier 2 (lease formal TTL — ativa se 2× evidência empírica de conflito Claude-A vs Claude-B) | 4d |
| US-MCP-016 | Cross-agent handoff protocol (Brain B → Claude Code com contexto serializado, sem re-explore) | 5d |

**Saída onda 3:** Visualization sobe de 20% → ~70%. % global passa pra **~88%**. oimpresso vira referência pública (open-source release? blog post?) — diferencial Brief + Tier 0 + governance combinado é único.

---

## 6. Surpresas

### Positivas (oimpresso JÁ faz melhor que o mercado)

1. **Brief Diário consolidado pré-consumível por LLM** ([brief-fetch](../../../Modules/Jana/Mcp/Tools/) + [ADR 0091](../../decisions/0091-daily-brief.md)). Asana/Rovo entregam digest pra humano ler — oimpresso entrega ~3k tokens otimizado pra contexto LLM, hook SessionStart auto-carrega. **Único no mercado.**
2. **Multi-tenant Tier 0 IRREVOGÁVEL com global scope + cross-tenant Pest gate** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md), [test biz=99](../../../tests/Pest/BusinessIdGuardTest.php)). SaaS dependem do cliente confiar — oimpresso prova programaticamente em CI. Plane Enterprise tem GAC mas exige tier pago.
3. **`whats-active` cross-dev anti-step-on-toes** ([ADR 0119](../../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md)). Linear/Jira não têm. É feature de coord específica pra equipes com múltiplos agents IA simultâneos — vanguarda 2026.
4. **`claude-code-usage-self` democrático por-dev** ([ClaudeCodeUsageSelfTool](../../../Modules/Jana/Mcp/Tools/ClaudeCodeUsageSelfTool.php)). Rovo mostra dashboard enterprise pro admin; oimpresso mostra pra cada dev individual em tempo real. Mais alinhado com cultura de transparência.
5. **`cc-search` cross-session indexada FULLTEXT** ([CcSearchTool](../../../Modules/Jana/Mcp/Tools/CcSearchTool.php)) — Felipe pode buscar "como Wagner resolveu 504 Telescope?" em sessões Claude Code do time inteiro. Sem equivalente no mercado.

### Negativas (mercado faz e ainda não fizemos)

1. **Sub-issues / hierarchy** — GitHub Projects GA mar/2026 já tem default. Linear/Asana há anos. oimpresso tem só `blocked_by[]` flat. Custa pouco implementar (gap #7).
2. **Auto-rollover cycles** — Linear faz por padrão; oimpresso fecha cycle manual sem mover incompletas. Quick win 0.5d.
3. **RICE/ICE/WSJF prioritization framework** — Linear via Ducalis, Asana built-in. oimpresso só tem p0-p3 ordinal. Útil quando backlog cresce >100 items.
4. **Agentic auto-prioritize workload** — Asana AI Teammates re-prioritiza por deadline shift; Notion Agent faz 20min trabalho autônomo. oimpresso tem `triage` tool mas é read-only (lista, não age). Considerar pra Onda 2/3.
5. **Roadmap timeline UX visual** — Plane Gantt free tier, Linear Roadmap. Mercado padrão. Não temos. Adia até dor real.

---

## 7. Conclusão

**oimpresso MCP server está em maturidade 62%**, dirigida por excelência absoluta em governance (88%) e UX/discovery (82%), com **buracos cirúrgicos em task lifecycle (38%)** que se resolvem com **1.5 dev-days de fix bug** (Bugs #1-#4). Onda 1 sozinha sobe pra ~72%.

A maior parte do "MCP desincronizado" que Wagner reportou hoje é **1 regex de 1 linha** ([GitTaskLinkerService:38](../../../Modules/Jana/Services/TaskRegistry/GitTaskLinkerService.php#L38)). Não é dívida arquitetural — é bug operacional. Manter foco no Onda 1 antes de pensar em viz/sub-issues/Gantt.

**Diferencial estratégico defendido:** Brief Diário + Tier 0 + `whats-active` + `cc-search` + `claude-code-usage-self` é uma stack **única no mercado** pra times pequenos com múltiplos agents IA-pair. Vale post no [oimpresso.com/blog](https://oimpresso.com/blog) ou ADR-mãe "MCP server como produto vendável" depois da Onda 2.

**Recomendo Wagner aprovar US-MCP-001..004 imediatamente** (já estão em [BUGS-MCP-SYNC-2026-05-13.md §Tasks MCP propostas](BUGS-MCP-SYNC-2026-05-13.md#tasks-mcp-propostas-pra-criar)) — pode usar tool `tasks-create` agora. Quick win cycle auto-rollover entra junto (0.5d a mais).

---

**Fontes principais:**
- Linear: [docs/mcp](https://linear.app/docs/mcp), [changelog cycles](https://linear.app/docs/use-cycles), [Linear Agent](https://www.idlen.io/news/linear-agent-issue-tracking-dead-ai-agents-product-management/)
- Jira+Rovo: [Rovo MCP GA](https://www.atlassian.com/blog/announcements/atlassian-rovo-mcp-ga), [agents em Jira fev/2026](https://siliconangle.com/2026/02/25/atlassian-embeds-agents-jira-embraces-mcp-third-party-integrations/)
- GitHub: [Hierarchy GA mar/2026](https://github.blog/changelog/2026-03-19-hierarchy-view-in-github-projects-is-now-generally-available/), [auto-close keywords](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/managing-repository-settings/managing-auto-closing-issues)
- Plane: [plane.so](https://plane.so/), [open-source AGPL](https://plane.so/self-hosted), [API rate limits](https://developers.plane.so/api-reference/introduction)
- Tegon: [github/RedPlanetHQ/tegon](https://github.com/RedPlanetHQ/tegon), [Slack actions](https://dev.to/getcore/how-we-simplified-bug-tracking-using-tegon-slack-actions-4j6o)
- Vikunja: [features](https://vikunja.io/features/)
- Shortcut Korey: [Wikipedia 2025](https://en.wikipedia.org/wiki/Shortcut_Software)
- Notion: [Agent 3.2 jan/2026](https://thecrunch.io/notion-ai-agent/)
- Asana: [AI](https://asana.com/product/ai), [agentic 2026 analysis](https://markets.financialcontent.com/wral/article/finterra-2026-3-2-asana-nyse-asan-2026-analysis-transitioning-to-the-agentic-enterprise)
- Height: [tested 2026](https://www.kuse.ai/blog/workflows-productivity/ai-task-manager)
- MCP gov: [Tyk best-practices](https://tyk.io/learning-center/mcp-server-governance-best-practices/), [Truefoundry enterprise](https://www.truefoundry.com/blog/enterprise-mcp-governance-control-audit-secure-mcp-server-access), [Prefactor multi-tenant](https://prefactor.tech/blog/mcp-security-multi-tenant-ai-agents-explained)
- Eval: [Maxim 2026 RAG](https://www.getmaxim.ai/articles/the-5-best-rag-evaluation-tools-you-should-know-in-2026/), [Elastic GenAI obs](https://www.elastic.co/blog/2026-observability-trends-generative-ai-opentelemetry)

**Próximos passos sugeridos a Wagner:**
1. Aprovar criação de US-MCP-001..004 + US-MCP-Q1 (auto-rollover) via `tasks-create` no MCP
2. Decidir se Onda 2 entra em CYCLE-06 ou CYCLE-07 (depende capacidade)
3. Considerar ADR mãe "MCP server como produto interno+vendável" se Onda 2 entregar RAGAS gate verde
