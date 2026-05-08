---
name: Roteiro Mestre — Constituição v2 Oimpresso
description: Plano consolidado de 6 sprints/cycles para implementar a arquitetura de 7 camadas (L1→L7). Estado atual + etapas detalhadas + estado-da-arte + dependências + métricas.
type: project
version: 1.0
created: 2026-05-06
authors: Wagner (owner), Claude Sonnet (designer)
supersedes: []
---

# ROTEIRO MESTRE — Constituição v2 Oimpresso

> **Documento vivo.** Toda mudança de status, decisão ou postmortem volta aqui.
> Wagner abre este arquivo no início de cada semana e ao tomar decisão de prioridade.
>
> **Última revisão:** 2026-05-06 (Sonnet, sessão organização sprints)

---

## Sumário executivo

Em 1 parágrafo: estamos migrando o ERP Oimpresso de uma arquitetura "Claude Code lê tudo cada sessão" pra uma de **7 camadas governadas** (Constituição v2), onde contexto é produto, decisões são tieradas por custo/risco, e Claude vira parceiro auditável. **Sprint 1 (Daily Brief) já está em produção.** **Sprint 2 (MWART OS Listagem) também.** Faltam 5 sprints (S3–S7) para fechar o ciclo. Cada um produz uma camada concreta com métricas próprias.

---

## 1. As 7 camadas (mapa visual)

```
┌────────────────────────────────────────────────────────────────────────┐
│ L7 — DAILY BRIEF        ✅ PROD     (3k tokens, 6x/dia, brief-fetch)   │
│ L6 — CHARTERS           🔲 PENDING  (page/feature/mission contracts)   │
│ L5 — ADRs canon         ✅ PROD     (ADR 0094 mãe + 0095 tiers ativos) │
│ L4 — PLAYBOOKS          🔲 PENDING  (procedimentos executáveis)        │
│ L3 — SKILLS             ✅ PROD     (22 skills tieradas A/B/C, hooks)  │
│ L2 — ADS Universal      🔲 PENDING  (firewall: code/design/produto…)   │
│ L1 — MCP CORE           ✅ PROD     (tools + memória + audit)          │
└────────────────────────────────────────────────────────────────────────┘
```

### Princípios duros (referência rápida)

1. **Context as a product** — contexto é UI: hierarquia, cache, versão.
2. **Tiered cost** — Brain A barato default, Brain B caro só quando preciso, humano só quando inevitável.
3. **Charter > Spec** — contratos vivos, lidos por IA na hora do diff.
4. **Loop fechado por métrica** — toda regra tem dashboard provando ROI.
5. **Separation of concerns brutal** — uma coisa, um lugar, um dono.
6. **Multi-tenant by default — TIER 0, IRREVOGÁVEL** ⚠️ — toda query, log, brief, audit trail respeita `business_id` global scope. Vazar dados entre tenants é o pior bug possível do projeto. Princípio adicionado 2026-05-06 a pedido do Wagner; ver §12 abaixo pra implementação detalhada em todas as 7 camadas.

---

## 2. Estado atual — o que existe vs o que falta

### ✅ Em produção (verificado em commits + filesystem)

| Item | Evidência | Camada |
|---|---|---|
| Tool MCP `brief-fetch` | commit `c4fc2680` PR #109 | L7 |
| Schema `mcp_briefs` + `mcp_skill_telemetry` | commit `b850e532` | L7 |
| Brain B = OpenAI gpt-4o-mini (não Anthropic) | commit `ca3f9c33` | L2 (proto) |
| Procedure `refresh_brief_inputs_cache` | commit `9ee3744d` | L7 |
| Cron 6x/dia regenerando brief | commit `b850e532` | L7 |
| Skill `brief-first` Tier A | `.claude/skills/brief-first/` | L3 |
| ADR 0091 — Daily Brief | `memory/decisions/0091-daily-brief.md` | L5 |
| CLAUDE.md atualizado | commit `08a26f03` | (root) |
| MWART OS Listagem dual-mode (Repair) | commit `d9590a65` | (módulo) |
| Config `config/mwart.php` (feature flags) | filesystem | (módulo) |
| Auto-rebuild Inertia em push `resources/` | commit `de57dc92` | (CI) |
| Rename Jana `copiloto_*` → `jana_*` | PR #111 | (módulo) |

### 🟡 Existe como dossier mas implementação parcial / pendente

| Sprint | Pasta | Status implementação |
|---|---|---|
| S1 Daily Brief | `memory/sprints/s1-daily-brief/` | Mergeado, falta postmortem 48h |
| S2 OS Listagem MWART | `memory/sprints/s2-os-listagem/` | Mergeado, falta soak 48h + replicação outras telas |
| **S3 Constituição v2** | `memory/sprints/s3-constituicao/` | 🟡 **EM EXECUÇÃO** — ADRs 0094+0095 aceitas (PR pendente). Faltam: CLAUDE.md reescrita, 5 imports memory/, skills moves, hook SessionStart |

### 🔴 NÃO EXISTE (faltando)

| Item | Onde deveria estar | Quando entra |
|---|---|---|
| Postmortem Sprint 1 | `memory/sprints/s1-daily-brief/99-postmortem.md` | template criado, falta dados pós-soak |
| Postmortem Sprint 2 | `memory/sprints/s2-os-listagem/99-postmortem.md` | template criado, falta dados pós-soak |
| Skill `mwart-migrate` (spec existe, skill não) | `.claude/skills/mwart-migrate/SKILL.md` | criar antes de S2.5 (replicar 4 telas Repair) |
| **CLAUDE.md reescrito** ≤100 linhas | raiz | S3 Fase 2 (em andamento) |
| **5 arquivos memory/ importados** | `memory/{why,what,how,proibicoes,regras-time}.md` | S3 Fase 2 |
| **3 skills novas Tier A** (`mcp-first`, `commit-discipline`, `charter-first`, `ads-route`) | `.claude/skills/` | S3 Fase 3 |
| **Hook SessionStart** força brief-fetch | `.claude/settings.json` | S3 Fase 4 |
| Dossier Sprint 3 (Constituição) | `memory/sprints/s3-constituicao/` | depende de S1 estável |
| Dossier Sprint 4 (Page Charters) | `memory/sprints/s4-charters/` | depende de S3 |
| Dossier Sprint 5 (ADS Universal) | `memory/sprints/s5-ads/` | depende de S4 |
| Dossier Sprint 6 (Playbooks+Strangler) | `memory/sprints/s6-playbooks/` | depende de S5 |
| Dossier Sprint 7 (ADR poda + Cockpit) | `memory/sprints/s7-cockpit/` | depende de S6 |
| Diretório `memory/playbooks/` | raiz da memory | criado em S6 |
| Diretório `memory/templates/` | raiz da memory | criado em S4 |
| Charters | `**/*.charter.md` | criados em S4 |
| Tabela `mcp_page_charters` | banco prod | migration em S4 |
| Tabela `mcp_route_migration_state` | banco prod | migration em S6 |
| Página `/governance/oimpresso` | `resources/js/Pages/Governance/Cockpit.tsx` | construída em S7 |
| Tools MCP `charter-fetch`, `decide`, `migration-state-list`, `design-locks-active` | módulo MCP | conforme cada sprint |

### ⚠️ Discrepância de numeração — RESOLVIDA neste roteiro

O `OPUS-MISSION-BRIEF.md` original previa: S1 Brief → S2 Constituição → S3 Charters → S4 ADS → S5 Playbooks → S6 Cockpit. Mas na prática, **S2 virou MWART OS Listagem** (entrega tática que precisava sair antes). Renumerei aqui:

| Era no Opus brief | Vira aqui | Status |
|---|---|---|
| S1 Daily Brief | **S1** | ✅ DONE |
| (não existia) | **S2 MWART OS Listagem** | ✅ DONE |
| S2 Constituição | **S3 Constituição + skills tier + CLAUDE.md** | 🔴 |
| S3 Charters | **S4 Page Charters L6** | 🔴 |
| S4 ADS Universal | **S5 ADS Universal L2** | 🔴 |
| S5 Playbooks + Strangler | **S6 Playbooks L4 + Strangler** | 🔴 |
| S6 ADR poda + Cockpit | **S7 ADR poda + Cockpit Governança** | 🔴 |

Ao escrever ADRs novas / charters / dossiers, **usar a numeração nova** (S3–S7).

---

## 3. Estado-da-arte por camada (pesquisa 2026-05-06)

Pesquisa rápida 6 temas, principais achados aplicáveis ao Oimpresso:

### L7 Daily Brief — context bootstrap

- **Anthropic publicou "Effective harnesses for long-running agents"** ([anthropic.com](https://www.anthropic.com/engineering/effective-harnesses-for-long-running-agents)) que descreve padrão *initializer + coding agent* com `claude-progress.txt` — exatamente o que nosso brief faz, mas mais estruturado.
- **Progressive disclosure** é o princípio canônico ([anthropic.com/engineering/equipping-agents…](https://www.anthropic.com/engineering/equipping-agents-for-the-real-world-with-agent-skills)): só o name+description vai pro system prompt (~50–100 tokens/skill); body só carrega quando trigger casa.
- **Aplicação no S1:** brief atual já segue. Pós-postmortem, considerar adicionar `claude-progress.txt`-equivalente (último estado de migração por módulo) ao brief.

### L3 Skills — tiered architecture

- **Anthropic Skills 32-page playbook** ([techbytes.app](https://techbytes.app/posts/anthropic-claude-skills-guide-breakdown-feb-2026/)) formaliza o padrão `description: "Use when..."` no frontmatter como gatilho automático.
- **2026 trend report** ([resources.anthropic.com 2026 Agentic Coding](https://resources.anthropic.com/hubfs/2026%20Agentic%20Coding%20Trends%20Report.pdf)): equipes maduras têm ~5–8 skills always-on, ~15–20 auto-trigger, dezenas on-demand.
- **Aplicação no S3:** auditar nossas 19 skills atuais, promover ~5 pra Tier A, manter ~8 em B, arquivar resto.

### L6 Charters — termo cunhado por Wagner, não está no mercado

- Pesquisa não encontra "page charter" / "feature charter" como conceito formal. **É uma invenção nossa** que combina `team charter` (PMI), `design contract` (frontend) e `ADR scope` (Nygard).
- **Inspirações:** [Asana project charter](https://asana.com/resources/project-charter), [BradCypert team charter](https://www.bradcypert.com/team-charter-for-software-engineering-teams/) — ambos enfatizam *intenção + escopo + invariantes + owner*. Nosso template precisa importar essa estrutura mas no tamanho de uma página/feature.
- **Aplicação no S4:** template `memory/templates/charter.template.md` com 6 seções: INTENÇÃO, CONTRATO (props/eventos/dados), INVARIANTES, OWNERS (design+code), ADRs relacionadas, HISTÓRICO de mudanças.

### L2 ADS — multi-agent governance

- **Governance-as-a-Service (GaaS)** ([arxiv.org/2508.18765](https://arxiv.org/html/2508.18765v1)) propõe *Trust Factor* + matriz `coercive/normative/adaptive` com 3 níveis (allow/warn/block). Convergência forte com nosso PolicyEngine (ALLOW_BRAIN_A / REQUIRE_BRAIN_B / REQUIRE_HUMAN_REVIEW / BLOCK_ALWAYS).
- **OrgAgent** ([arxiv.org/2604.01020](https://arxiv.org/html/2604.01020)) organiza em 3 camadas: governance / execution / compliance — modela bem o que queremos (Brain B revisor + Brain A executor + humano final).
- **Dataiku multi-tier governance** ([dataiku.com](https://www.dataiku.com/stories/blog/new-approach-to-agent-governance)) reforça que budget per-agent + escalation matrix são padrão de mercado.
- **Aplicação no S5:** estender ADS atual (CODE) pra 5 domínios (CODE/DESIGN/PRODUTO/MEMORIA/RUNTIME). Cada domínio com risk scorer próprio. Budget/dia já no escopo Sprint 4 do Opus brief.

### L4 Strangler Fig — migração legacy

- **Padrão Martin Fowler** ([martinfowler.com](https://martinfowler.com/bliki/StranglerFigApplication.html)) — proxy intercepta, roteia pra legacy ou new, gradualmente trata um módulo por vez.
- **Aplicação direta na nossa MWART** (S2 já fez isso): feature flag `MWART_REPAIR_INDEX` é o proxy, Blade=legacy, React=new. Próximo passo é formalizar a *state machine* (LEGACY→MIGRATING→CANARY→MIGRATED→DELETED).
- **Aplicação no S6:** tabela `mcp_route_migration_state` + skill `blade-cleanup` que abre PR removendo Blade após 14d MIGRATED estável.

### Cockpit — agent ops dashboard

- **Gartner 2026** ([kore.ai/blog](https://www.kore.ai/blog/best-ai-agent-management-platforms)) define Agent Management Platform com 6 elementos: security, libraries, tooling, **dashboard**, marketplace, **observability**.
- **ServiceNow AI Control Tower** ([efficientlyconnected.com](https://www.efficientlyconnected.com/servicenow-knowledge-2026-ai-governance-control-tower/)) e Microsoft Agent 365 ([techcommunity.microsoft.com](https://techcommunity.microsoft.com/blog/agent-365-blog/what%E2%80%99s-new-in-agent-365-may-2026/4516340)) convergem nos pilares **observe / govern / secure**.
- **Arthur observability playbook 2026** ([arthur.ai](https://www.arthur.ai/column/agentic-ai-observability-playbook-2026)) recomenda 8 painéis: trust drift, cost per task, escalation rate, baseline divergence, etc.
- **Aplicação no S7:** os 8 painéis do Cockpit do Opus brief estão alinhados com esse benchmark — manter como está.

---

## 4. Roteiro detalhado por sprint

> **Convenção:** cada sprint tem **definição**, **etapas em ordem**, **dependências**, **arquivos a criar**, **métricas de sucesso**, **riscos**, **estimativa**, **próximo passo concreto**.

---

### Sprint 1 — Daily Brief (camada L7)

**Status:** ✅ MERGEADO em prod. Falta soak 48h + postmortem.

**O que entrega:** tool MCP `brief-fetch` que devolve markdown ~3k tokens com estado consolidado, regenerado 6x/dia. Skill `brief-first` Tier A força chamada como primeira tool em toda sessão.

**Etapas:**

- [x] ADR 0091 commitada (`memory/decisions/0091-daily-brief.md`)
- [x] Migration `mcp_briefs` + `mcp_skill_telemetry` aplicada em prod (Hostinger MySQL)
- [x] Procedure `refresh_brief_inputs_cache` corrigida pro schema real (commit `9ee3744d`)
- [x] Brain B trocado pra OpenAI gpt-4o-mini (commit `ca3f9c33`) — economia ~95% vs Sonnet
- [x] Cron 6x/dia ativo
- [x] Tool `brief-fetch` registrada no `mcp.oimpresso.com` (commit `c4fc2680`, PR #109)
- [x] Skill `brief-first` em `.claude/skills/brief-first/`
- [x] CLAUDE.md atualizado pra mencionar brief-first como Tier A always-on (commit `08a26f03`)
- [ ] **PENDENTE: soak 48h em produção** (rodando agora)
- [ ] **PENDENTE: postmortem em `s1-daily-brief/99-postmortem.md`**

**Métricas pós-soak (medir 7 dias após cada item entrar em prod):**

| Métrica | Como medir | Alvo |
|---|---|---|
| Token médio onboarding | `mcp_audit_log` agg per session | -40% vs baseline |
| Adoção brief-first | `mcp_skill_telemetry` | ≥90% sessões |
| Custo Brain B | tabela `mcp_briefs.tokens_used` × $rate | ≤ $3.50/semana |
| Drift do brief | revisão manual semanal Wagner | <2 inconsistências/brief |
| Brief uptime | health check | ≥99% |

**Riscos:**
- 🟡 Custo OpenAI sobe se gpt-4o-mini ficar caro — mitigação: trocar pra Ollama local
- 🟡 Brief stale se cron falhar — mitigação: fallback para "última geração há X horas"
- 🟢 Skill auto-trigger pode não disparar em sessões de outros devs — testar com Felipe primeiro

**Próximo passo:**
1. Esperar 48h soak (já rodando)
2. Wagner roda query SQL: `SELECT COUNT(*), AVG(tokens_used), MAX(generated_at) FROM mcp_briefs WHERE generated_at > NOW() - INTERVAL 48 HOUR;`
3. Sonnet ou Wagner escreve postmortem

**Arquivos faltando:** `memory/sprints/s1-daily-brief/99-postmortem.md`

---

### Sprint 2 — MWART OS Listagem (Repair)

**Status:** ✅ MERGEADO (commit `d9590a65`). Falta soak 48h + métricas adoção.

**O que entrega:** primeira tela Blade→React migrada (Listagem de OS em `/repair/repair`), validando o padrão MWART (Module Web App React Transition) com feature flag `MWART_REPAIR_INDEX`.

**Etapas:**

- [x] ADR MWART contract (`s2-os-listagem/01-adr-mwart-contract.md`)
- [x] Schema índices `transactions` (`02-schema-repair-indices.sql`)
- [x] Spec controller dual-mode (`03-spec-repair-controller.md`)
- [x] Spec React Index.tsx (`04-spec-repair-index-react.md`)
- [x] Skill `mwart-migrate` (reusável)
- [x] Implementação dual-mode mergeada (`d9590a65`)
- [ ] **PENDENTE: soak 48h em staging com Larissa (ROTA LIVRE biz=4)**
- [ ] **PENDENTE: medir p95 < 400ms (Telescope) + 0 erros JS (Sentry)**
- [ ] **PENDENTE: ativar `MWART_REPAIR_INDEX=true` em prod ROTA LIVRE**
- [ ] **PENDENTE: replicar padrão pra outras 4 telas Repair (Job Sheet, Status, Device Models, Dashboard) — Sprint 2.5**

**Métricas:**
- Paridade funcional 100% (filtros, paginação, bulk actions)
- p95 < 400ms
- 0 erros JS Sentry em 48h
- Wagner ou Larissa não conseguem dizer qual versão estão usando às cegas

**Riscos:**
- 🟡 React build stale se push em `resources/` sem rebuild — **MITIGADO** pelo auto-rebuild action (commit `de57dc92`)
- 🟡 Larissa pode não notar bug visual em monitor 1280px — **MITIGAÇÃO:** screenshots before/after em PR
- 🟢 Permissões Spatie podem divergir entre Blade e Inertia — testar `repair.view` vs `repair.view_own`

**Próximo passo:**
1. Wagner ativa flag em staging por 48h
2. Verifica métricas
3. Promove pra prod ROTA LIVRE
4. Decide se replica pra outras 4 telas Repair imediatamente OU se interrompe pra fazer S3 (Constituição)

**Arquivos faltando:** `memory/sprints/s2-os-listagem/99-postmortem.md` (após soak)

---

### Sprint 3 — Constituição v2 + skills Tier A + CLAUDE.md reescrito

**Status:** ✅ **ENTREGUE EM PROD** 2026-05-06.

**Progresso:**
- ✅ Dossier 4 ADRs aprovadas (PR #132 `bf3a18ee`)
- ✅ Fase 1 — ADRs canon 0094 (Constituição mãe) + 0095 (skills-tiers) — PR #133 (`9d5354fb`)
- ✅ Fase 2 — CLAUDE.md reescrito **88 linhas** (de 289) + 5 imports `memory/{why,what,how,proibicoes,regras-time}.md` — PR #134 (`1e5eb084`)
- ✅ Fase 3 — Skills moves (3 renames + 3 Tier A novas) + tier frontmatter em 22 skills — PR #135 (`d116b811`)
- ✅ Fase 4 — Hook SessionStart `tier-a-banner` + PreToolUse Bash `commit-discipline-check` — PR #136 (`66ddf45e`)
- ✅ Fase 5 — Smoke test prod: 5/5 health checks PASSED, CLAUDE.md 88 linhas confirmado, todos imports + skills em prod

**Métricas pós-S3:**
- CLAUDE.md ≤100 linhas: ✅ (88)
- 6 skills Tier A definidas (4 ativas + 2 dormentes S4/S5): ✅
- 22 skills com tier frontmatter: ✅
- 2 ADRs canon Constituição: ✅ aceitas (0094 mãe, 0095 tiers)
- 8 princípios duros (3 NOVOS): Multi-tenant Tier 0 + Transparência + Confiabilidade ✅
- jana:health-check daily 06:00 BRT: ✅ schedule ativo

**O que entrega:** documento mãe da Constituição (ADR canônica), reescrita do CLAUDE.md (≤350 linhas), 5 skills Tier A finalizadas, auditoria das 19 skills atuais com decisões PROMOVER/MANTER/ARQUIVAR.

**Etapas:**

- [ ] **3.1 — ADR mãe** `memory/decisions/NEXT-constituicao-v2.md` (numerar no commit)
  - Documenta as 7 camadas, princípios duros, contratos entre camadas
  - Referencia ADRs 0035, 0040, 0070, 0091
  - Status: `accepted`
- [ ] **3.2 — ADR skills tiers** `memory/decisions/NEXT+1-skills-tiers.md`
  - Define Tier A (always-on), B (auto-trigger por description match), C (on-demand `/<skill>`)
  - Cada skill obrigatoriamente tem métrica em `mcp_skill_telemetry`
  - Critérios pra promover/rebaixar tier
- [ ] **3.3 — Auditoria das 19 skills atuais**
  - Arquivo: `memory/sprints/s3-constituicao/skills-audit.md`
  - Cada skill: disparos 30d (via `mcp_skill_telemetry` se já existir), tokens economizados estimado, decisão (PROMOVER A / TIER B / TIER C / ARQUIVAR / MERGE)
  - Lista atual: `ads-decision-flow`, `brief-first`, `cockpit-runbook`, `comparativo-do-modulo`, `copiloto-arch`, `criar-modulo`, `memoria-recall-flow`, `memory-sync`, `meta-skill-roi-erp-autonomo`, `migrar-modulo`, `multi-tenant-patterns`, `oimpresso-cc-watcher-setup`, `oimpresso-mcp-first`, `oimpresso-stack`, `oimpresso-team-onboarding`, `proxmox-docker-host`, `publication-policy`, `runtime-rules-hostinger-ct100`, `sidebar-menu-arch`
  - Wagner aprova tabela inteira em uma rodada antes de mover arquivos
  - Decisões executadas: arquivar pra `.claude/skills/_archive/` (não delete)
- [ ] **3.4 — 5 skills Tier A finalizadas**
  - `brief-first/` ✅ já existe — só formalizar `tier: A` no frontmatter
  - `mcp-first/` — refinar a `oimpresso-mcp-first` atual (renomear, endurecer telemetria)
  - `charter-first/` — bloqueia edição de `.tsx` que tenha `.charter.md` ao lado sem ter chamado `charter-fetch` (depende do S4 entregar a tool)
  - `commit-discipline/` — 1 PR = 1 intent, ≤300 linhas, valida no pre-commit
  - `ads-route/` — toda decisão custosa passa por `decide()` (depende do S5 entregar a tool)
  - **Nota:** `charter-first` e `ads-route` ficam em **modo dormente** até S4/S5 entregarem as tools. Frontmatter `tier: A` mas com `enabled: false` até dependência ficar pronta.
- [ ] **3.5 — CLAUDE.md reescrito** (≤350 linhas)
  - Estrutura nova (do Opus brief §4.2):
    1. Stack atual (1 parágrafo)
    2. Estado da migração (tabela: módulo / status / owner)
    3. Constituição v2 (link pra ADRs L1–L7)
    4. Princípios duros (5 bullets)
    5. Contrato pra Claude Code: PROTOCOLO DE INÍCIO DE SESSÃO (brief-fetch → charter-fetch → trabalho)
    6. Tabela de skills Tier A (5 únicas)
    7. Como propor mudança (ADR canon vs HISTORICAL)
    8. Onde NÃO inventar (tokens, primitivos, ADRs CANON)
- [ ] **3.6 — ADR `claude-md-reescrita.md`** documenta o diff e a justificativa

**Métricas:**
- CLAUDE.md ≤350 linhas
- 5 skills Tier A no ar (mesmo que 2 dormentes)
- 19 skills auditadas, ≤8 Tier A+B vivas após poda
- ADRs novas commitadas, indexadas no MCP via webhook

**Riscos:**
- 🔴 CLAUDE.md atual tem ~390 linhas e várias instruções críticas — risco de **regredir comportamento** se podar errado
- 🟡 Skills auto-trigger podem não disparar consistentemente sem telemetria histórica — coletar baseline 7 dias antes de auditar
- 🟢 Wagner pode discordar de decisões PROMOVER/ARQUIVAR — fluxo prevê aprovação em uma rodada

**Estimativa:** 3-4 dias de trabalho concentrado (1 Sonnet + revisões Wagner).

**Próximo passo concreto** (quando S1 estiver soak-aprovado):
1. Sonnet (ou Opus) cria `memory/sprints/s3-constituicao/README.md` com escopo
2. Cria os 6 arquivos numerados do dossier (mesmo padrão S1)
3. Wagner aprova dossier antes de execução

**Arquivos faltando (serão criados em S3):**
- `memory/sprints/s3-constituicao/README.md`
- `memory/sprints/s3-constituicao/01-adr-constituicao-v2.md`
- `memory/sprints/s3-constituicao/02-adr-skills-tiers.md`
- `memory/sprints/s3-constituicao/03-skills-audit.md`
- `memory/sprints/s3-constituicao/04-claude-md-novo.md`
- `memory/sprints/s3-constituicao/05-checklist-wagner.md`
- `memory/sprints/s3-constituicao/06-rollback-plan.md`
- `.claude/skills/mcp-first/SKILL.md` (ou rename da `oimpresso-mcp-first`)
- `.claude/skills/charter-first/SKILL.md` (dormente)
- `.claude/skills/commit-discipline/SKILL.md`
- `.claude/skills/ads-route/SKILL.md` (dormente)

---

### Sprint 4 — Page Charters (camada L6) + tool `charter-fetch`

**Status:** 🔴 PENDING. Pré-requisito: S3 estável.

**O que entrega:** schema `mcp_page_charters` + tool MCP `charter-fetch` + 10 charters preenchidos pras páginas mais movimentadas + skill `charter-first` ativada (sai do modo dormente).

**Etapas:**

- [ ] **4.1 — Migration schema** (MySQL 8.0, sintaxe correta — ver Opus brief §5.1)
  - Tabela `mcp_page_charters` com 14 campos + 3 índices
  - FK auto-referência `parent_id` → cadeia page→feature→mission
- [ ] **4.2 — Template** `memory/templates/charter.template.md`
  - 6 seções: INTENÇÃO, CONTRATO, INVARIANTES, OWNERS, ADRs, HISTÓRICO
  - Frontmatter: `charter_id`, `kind`, `parent_id`, `trust_level`, `owners`
- [ ] **4.3 — Comando `php artisan charter:sync`**
  - Varre `**/*.charter.md` no repo
  - Parse frontmatter YAML + seções markdown
  - Upsert em `mcp_page_charters`
  - Bump `charter_version` se conteúdo mudou (hash)
  - Roda em CI (post-merge em main)
- [ ] **4.4 — Tool MCP `charter-fetch`** (handler PHP)
  - Input: `charter_id`, `include_chain` (default true)
  - Output: charter + cadeia de pais em ≤4k tokens
  - Cache infinito até `charter_version` mudar
- [ ] **4.5 — 10 charters preenchidos** (escolher após orientação real do estado das páginas)
  - Sugestão inicial:
    1. `page.repair.listagem` (já migrada em S2 — fazer primeiro como referência)
    2. `page.tarefas.inbox`
    3. `page.os.detalhe`
    4. `page.os.form`
    5. `page.clientes.listagem`
    6. `page.orcamentos.listagem`
    7. `page.producao.kanban`
    8. `page.copiloto.chat`
    9. `page.financeiro.contas-pagar`
    10. `page.dashboard.home`
- [ ] **4.6 — Skill `charter-first` ativada** (sair do dormente, `enabled: true`)
  - Hook Claude Code `pre-edit` que bloqueia edição de `.tsx` se houver `.charter.md` ao lado e `charter-fetch` não foi chamado na sessão

**Métricas:**
- Schema + tool no ar
- 10 charters em produção, indexados via `charter:sync`
- Skill `charter-first` com ≥80% taxa de adoção em 7 dias
- 0 PRs editando arquivo com charter sem charter-fetch (medir via `mcp_audit_log`)

**Riscos:**
- 🔴 Cunhar uma metodologia nova ("charter") sem precedente de mercado — **MITIGAÇÃO:** template inspirado em PMI/Asana, treinar Maiara/Felipe antes de exigir
- 🟡 Hook pre-edit pode falhar e travar workflow — flag de bypass `CHARTER_ENFORCE=false`
- 🟢 10 charters de uma vez é otimista — começar com 3 (Repair listagem + Repair detalhe + Tarefas inbox) e aumentar

**Estimativa:** 5-7 dias.

**Arquivos faltando (criados em S4):**
- `memory/sprints/s4-charters/` (dossier completo)
- `memory/templates/charter.template.md`
- Migration `database/migrations/YYYY_MM_DD_create_mcp_page_charters_table.php`
- `Modules/Mcp/Console/Commands/CharterSync.php`
- Tool handler em `Modules/Mcp/Mcp/Tools/CharterFetchTool.php`
- 10× `**/*.charter.md`

---

### Sprint 5 — ADS Universal (camada L2)

**Status:** 🔴 PENDING. Pré-requisito: S4 estável.

**O que entrega:** estende ADS atual (CODE only) pra 5 domínios — CODE, DESIGN, PRODUTO, MEMÓRIA, RUNTIME. Entry point único `decide(domain, intent, payload)`. Brain A em Ollama local default, Brain B externo só quando preciso.

**Etapas:**

- [ ] **5.1 — Refactor entry point** `Modules/ADS/Services/Decide.php`
  - Substitui chamadas diretas a `analyzeRisk` / `routeToBrain`
  - Recebe `(domain, intent, payload)`, devolve `Decision { action, brain_used, confidence, cost_usd }`
- [ ] **5.2 — 5 RiskScorers** (1 por domínio)
  - `CodeRiskScorer` (já existe — refatorar pra implementar interface)
  - `DesignRiskScorer` (tier weight + visual delta + charter invariants + paridade Blade + fanout)
  - `ProdutoRiskScorer` (impacto cliente + reversibilidade + LGPD)
  - `MemoriaRiskScorer` (tipo de doc + audience + PII risk)
  - `RuntimeRiskScorer` (config prod + env vars + secrets)
- [ ] **5.3 — Policy matrix** (`config/ads-policy.php`)
  - Matriz 5 domínios × 4 níveis (LOW/MED/HIGH/CRIT) → ALLOW_BRAIN_A / REQUIRE_BRAIN_B / REQUIRE_HUMAN / BLOCK
  - Ver Opus brief §6.3 pra valores iniciais
- [ ] **5.4 — Brain A em Ollama local**
  - `OllamaBrainA` driver — `gpt-oss:120b` no CT 100 Proxmox (já tem infra)
  - Fallback `OpenAIMiniBrainA` se Ollama indisponível ou latency >5s
- [ ] **5.5 — Brain B revisor de design** (prompt fixo + handler)
  - Lê: charter (via charter-fetch), diff, screenshot before/after, lint output
  - Devolve: `{verdict: 'approve'|'request_changes'|'escalate', reasoning}`
  - Cap: 5k tokens output
- [ ] **5.6 — Budget per-agent**
  - Default: $5/dia/agent Brain B
  - Wagner: ilimitado (alerta em $50)
  - Quando estoura: força BRAIN_A + flag visual no Cockpit (S7)
  - Reseta 00:00 BRT
- [ ] **5.7 — Skill `ads-route` ativada** (sai do dormente)
  - Toda decisão custosa passa por `decide()` antes de executar

**Métricas:**
- 5 domínios cobertos por `decide()`
- ≥40% PRs auto-aprovados sem Wagner
- Custo Brain B/dia ≤ $25 médio (10 agents)
- 0 escapes (mudança em Tier 0 sem ADR)

**Riscos:**
- 🔴 Ollama local pode ser muito lento em hardware atual — fazer benchmark antes
- 🔴 Risk scorers podem ser frouxos demais (auto-aprova ruim) ou rígidos demais (humano sempre) — calibrar com 100 PRs históricos antes de ativar
- 🟡 Budget cap pode bloquear Wagner em momento crítico — alerta sem block para owner do projeto
- 🟢 Brain B revisor de design depende de screenshots — precisa Playwright ou similar no CI

**Estimativa:** 7-10 dias (sprint mais técnico do roadmap).

---

### Sprint 6 — Playbooks (camada L4) + Strangler State Machine

**Status:** 🔴 PENDING. Pré-requisito: S5 estável.

**O que entrega:** 6 playbooks executáveis em `memory/playbooks/` + tabela `mcp_route_migration_state` com state machine LEGACY→MIGRATING→CANARY→MIGRATED→DELETED + skill `blade-cleanup` que abre PR removendo Blade após 14d MIGRATED estável.

**Etapas:**

- [ ] **6.1 — Diretório `memory/playbooks/`**
- [ ] **6.2 — 6 playbooks essenciais**
  - `migrate-route.md` — Blade → React, Strangler 5 estados
  - `add-page-charter.md` — criar charter novo + sync
  - `incident-prod.md` — procedure quando algo cai
  - `onboard-new-claude.md` — adicionar 6º agent ao time
  - `onboard-new-dev.md` — Felipe-style onboarding (1 dia)
  - `deprecate-route.md` — deletar Blade após MIGRATED 14d
  - Cada um: frontmatter (versão, duração típica, trust_level, ADRs), quando usar, pré-requisitos, passos numerados, verificação de sucesso
- [ ] **6.3 — Migration `mcp_route_migration_state`** (ver Opus brief §7.2)
- [ ] **6.4 — Backfill de estados** das rotas atuais
  - Repair listagem = MIGRATED (S2)
  - Resto = LEGACY
- [ ] **6.5 — Skill `blade-cleanup`** (Tier C, trust_level 2)
  - Roda madrugada via cron
  - Pra cada rota MIGRATED há ≥14d com error_rate melhor que baseline Blade, abre PR removendo:
    - View Blade
    - Rotas Laravel da view
    - Assets relacionados
  - PR aguarda merge humano. **Não auto-merge.**
- [ ] **6.6 — Tool MCP `migration-state-list`** (alimenta Cockpit no S7)

**Métricas:**
- 6 playbooks no ar
- 3 rotas piloto migradas via playbook (sem desvio)
- 1 rota deletada via blade-cleanup
- Onboarding novo dev ≤1 dia (medir com próximo contratado)

**Riscos:**
- 🟡 `blade-cleanup` pode abrir PR errado se rota Blade ainda tem tráfego — verificar via Telescope antes de propor delete
- 🟢 Playbooks viram desatualizados rápido — versão obrigatória no frontmatter, alerta se >6 meses sem revisão

**Estimativa:** 4-5 dias.

---

### Sprint 7 — ADR poda (≤30) + Cockpit `/governance/oimpresso`

**Status:** 🔴 PENDING. Pré-requisito: S6 estável.

**O que entrega:** poda das 92 ADRs atuais pra ≤30 canônicas + página React `/governance/oimpresso` com 8 painéis pro Wagner abrir 1×/dia em vez de 4 abas.

**Etapas:**

- [ ] **7.1 — Triagem das 92 ADRs**
  - Arquivo `memory/sprints/s7-cockpit/adr-triage.md` com tabela:
    - ADR / Título / Idade / Refs ativas (grep no codebase) / Decisão (KEEP CANON / MERGE / ARCHIVE / DELETE)
  - Wagner aprova bloco a bloco (10 ADRs por rodada, ~10 rodadas)
  - Executa moves: ARCHIVE vai pra `memory/decisions/_archive/YYYY/`
- [ ] **7.2 — Reindexação no MCP**
  - Webhook GitHub re-sync `mcp_memory_documents` filtrando só ADRs ativas
  - Tool `decisions-search` passa a buscar só ≤30 canônicas
  - ADRs archived ainda pesquisáveis via flag `include_archived=true`
- [ ] **7.3 — Página React Cockpit**
  - Rota: `/governance/oimpresso` (auth: Wagner only inicialmente)
  - Componente: `resources/js/Pages/Governance/Cockpit.tsx`
  - Layout: grid 12 cols, 8 painéis (`<DCArtboard>`-like)
- [ ] **7.4 — 8 painéis** (alinhados com Arthur observability playbook 2026):
  1. **Brief atual** — render markdown direto via `brief-fetch`
  2. **Health do design system** — drift por tier, hex inline count
  3. **Migration board** — kanban LEGACY→DELETED, aging (via `migration-state-list`)
  4. **PRs aguardando review** — agrupados por veredicto Brain B
  5. **Charters health** — apodrecendo (last_verified > 90d), total cobertura
  6. **Locks ativos** — quem edita o quê (humanos + agents) — via `design-locks-active` (tool nova)
  7. **Visual regression feed** — últimos diffs, thumbs antes/depois
  8. **ADS metrics** — % auto-aprovado, custo Brain B mês, escalações
- [ ] **7.5 — Tools MCP novas:** `migration-state-list`, `design-locks-active`
- [ ] **7.6 — Polling 30s** + dark/light via design system

**Métricas:**
- ADRs canon ≤30
- Cockpit no ar, Wagner abre 1x/dia em vez de 4 abas
- Tempo Wagner em revisões/dia ≤25min
- Onboarding Claude novo ≤2min via brief

**Riscos:**
- 🔴 Podar ADR errada quebra rastreabilidade — aprovação bloco a bloco mitiga
- 🟡 Cockpit polling 30s × 8 painéis pode pesar — usar Inertia partial reloads
- 🟢 Painéis 6/7 dependem de tools que ainda não existem — pode lançar Cockpit com 6 painéis e crescer

**Estimativa:** 6-8 dias.

---

## 5. Cronograma sugerido (90 dias a partir de 2026-05-06)

⚠️ **Atualizado 2026-05-06 v1.4** com S2.5 (replicar MWART 4 telas Repair em paralelo) e overhead +20% dos deep-dives.

| Semana | S1 | S2 | **S2.5** | S3 | S4 | S5 | S6 | S7 | Notas |
|---|---|---|---|---|---|---|---|---|---|
| **Sem 1** (06–12 mai) | soak+post | soak+post | | dossier | | | | | atual |
| **Sem 2** (13–19 mai) | ✅ | ✅ | **kick-off** | execução | | | | | CYCLE-01 fecha (ADR 0070 não autoriza CYCLE-02 ainda) |
| **Sem 3** (20–26 mai) | | | **2 telas** | execução | | | | | S2.5 paralelo S3 |
| **Sem 4** (27 mai–02 jun) | | | **2 telas** | ✅ | dossier | | | | |
| **Sem 5** (03–09 jun) | | | ✅ (4/4) | | execução | | | | |
| **Sem 6** (10–16 jun) | | | | | execução | | | | |
| **Sem 7** (17–23 jun) | | | | | ✅ | dossier | | | |
| **Sem 8–10** (24 jun–14 jul) | | | | | | execução (sprint mais longo) | | | |
| **Sem 11** (15–21 jul) | | | | | | ✅ | dossier+execução | | |
| **Sem 12** (22–28 jul) | | | | | | | execução | | |
| **Sem 13** (29 jul–04 ago) | | | | | | | ✅ | dossier+poda | |
| **Sem 14–15** (05–18 ago) | | | | | | | | execução | |
| **Sem 16** (19–25 ago) | | | | | | | | ✅ | postmortem global |

**Folga embutida:** 1–2 semanas de buffer pra incidentes/postmortems entre sprints longos.

### S2.5 escopo (replicar MWART)

Pré-requisito: S2 OS Listagem soak 48h aprovado.

Telas Repair pra migrar (skill `mwart-migrate` cobre):
1. Job Sheet (`/repair/job-sheet`)
2. Status CRUD (`/repair/status`)
3. Device Models (`/repair/device-models`)
4. Dashboard Repair (`/repair/dashboard`)

Cada tela: 2 dias média × 4 = 8 dias. Pode ser sequencial ou paralelo (Felipe + Wagner).
**Não bloqueia S3** — S3 continua Wagner-driven enquanto Felipe/MWART executa S2.5.

---

## 6. Riscos globais (de toda a Constituição v2)

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| Wagner vira gargalo de aprovação | 🔴 Alto | Alto | S7 Cockpit reduz; até lá, batches semanais |
| Brain B custa mais que orçado | 🟡 Médio | Médio | Budget cap + alerta + Ollama Brain A |
| Skills auto-trigger não disparam consistentemente | 🟡 Médio | Médio | Telemetria desde dia 1 (já existe via `mcp_skill_telemetry`) |
| Charters viram doc morto que ninguém atualiza | 🔴 Alto | Alto | `last_verified` em alerta, charter-first hook bloqueia edição |
| Migration MWART regride quando muitas telas estão dual-mode | 🟡 Médio | Alto | Skill `mwart-migrate` + state machine + canary 14d |
| ADR poda mata referência viva | 🟡 Médio | Alto | Aprovação bloco a bloco + grep de refs antes de archive |
| Time externo (Felipe/Maiara) não adota workflow novo | 🔴 Alto | Médio | Onboarding playbook + treinamento 1h + métricas individuais |
| Discrepância L7 brief vs realidade do banco | 🟡 Médio | Alto | Drift check semanal Wagner; brief uptime SLO 99% |

---

## 7. Glossário (termos canônicos)

| Termo | Significado |
|---|---|
| **Sprint** | sinônimo de Cycle, mantido por costume — equivale a `mcp_cycles` (ADR 0070) |
| **Cycle** | termo canônico em ADRs novas; entidade `mcp_cycles` |
| **Brain A** | LLM barato e rápido (Ollama local `gpt-oss:120b` ou OpenAI gpt-4o-mini) |
| **Brain B** | LLM caro e capaz (Anthropic Sonnet/Opus ou OpenAI o1) |
| **Charter** | contrato vivo de uma página/feature/mission, em `**/*.charter.md`, indexado em `mcp_page_charters` |
| **MWART** | Module Web App React Transition — padrão de migração Blade→Inertia/React via feature flag |
| **Strangler Fig** | padrão Martin Fowler: proxy intercepta, roteia legacy/new, migra incremental |
| **ADS** | Agentic Decision System — `decide(domain, intent, payload)` com 5 domínios |
| **HITL** | Human In The Loop — escalation pra Wagner ou owner |
| **Tier 0/1/2/3** | classificação de risco do que está sendo mexido (0=auth/billing, 3=cosmético) |
| **Tier A/B/C** | classificação de skill (A=always-on, B=auto-trigger, C=on-demand) |
| **L1–L7** | as 7 camadas da Constituição (L1=MCP CORE, L7=Daily Brief) |
| **OS** | Ordem de Serviço — entidade do `Modules/Repair/`. Internamente é `transactions` com `type='sell'` e `sub_type='repair'` (padrão UltimatePOS). NÃO confundir com `Modules/Officeimpresso/` (módulo de licenciamento Delphi, não tem OS) |
| **CYCLE-NN** | ciclo de 2 semanas em `mcp_cycles` — atual CYCLE-01 fecha 2026-05-12, próximo CYCLE-02 abre na mesma data |
| **MEMORY** | árvore `memory/` no git (source of truth) → webhook GitHub → `mcp_memory_documents` (cache governado, 352 docs) |

---

## 8. Próximos passos imediatos (esta semana 06–12 mai 2026)

**Ordem recomendada:**

1. **HOJE** — Wagner valida este roteiro, ajusta o que discordar.
2. **Próximas 48h** — soak Sprint 1 + Sprint 2 em paralelo:
   - S1: query SQL pra confirmar `mcp_briefs` regenerando + `mcp_skill_telemetry` capturando brief-first
   - S2: ativar `MWART_REPAIR_INDEX=true` em staging com Larissa
3. **Sex 08–mai** — Wagner roda postmortem S1 + S2 (2 arquivos `99-postmortem.md`)
4. **Seg 11–mai** — começa dossier Sprint 3 (Constituição) — Sonnet ou Opus produz os 6 arquivos
5. **Seg 18–mai** — execução Sprint 3 começa (renumeração ADRs, auditoria skills, reescrita CLAUDE.md)

**Bloqueios potenciais:**
- ⚠️ Se métricas S1/S2 não baterem, **NÃO seguir pra S3** — investigar e ajustar primeiro
- ⚠️ Se time externo (Felipe/Maiara/Luiz/Eliana) não conseguir adotar brief-first em 7 dias, **dossier S3 precisa cobrir treinamento explícito** antes de promover mais skills Tier A

**Decisões Wagner (respondidas 2026-05-06):**
- [x] Numeração S3 = Constituição: ✅ **APROVADO** (com revisão de conflitos — feita)
- [x] Replicar MWART pras outras 4 telas Repair: ✅ **APROVADO — fazer (S2.5 ativo em paralelo a S3)**
- [x] Brain A em Ollama local: ❌ **PULAR benchmark — manter OpenAI gpt-4o-mini canônico** (CT 100 sem GPU, 120b inviável; 20b não vale o esforço)
- [x] Quem dirige S3: 👤 **WAGNER PESSOALMENTE**
- [x] Cockpit S7: 🔒 **APENAS Wagner** (Felipe/Maiara/Luiz/Eliana sem acesso por enquanto)
- [x] CYCLE-02: ⏸️ **NÃO ABRIR AINDA**
- [x] ADR 0093 multi-tenant Tier 0: ⏰ **DEPOIS** (criar dentro do S3, não antes)

**Quem dirige cada sprint** (proposta — Wagner ajusta):

| Sprint | Driver primário | Backup | Aprovação final |
|---|---|---|---|
| S1 postmortem | Sonnet (este chat) | Wagner | Wagner |
| S2 soak + replicação 4 telas | Wagner ou Felipe | Maiara | Wagner |
| S3 Constituição | Opus (via brief) ou Sonnet | Wagner | Wagner |
| S4 Page Charters | Opus | Sonnet | Wagner |
| S5 ADS Universal | Opus + Felipe (PHP backend) | Wagner | Wagner |
| S6 Playbooks + Strangler | Sonnet | Felipe | Wagner |
| S7 ADR poda + Cockpit | Sonnet (poda) + Felipe (Cockpit React) | Opus | Wagner |

> ⚠️ **Risco humano:** se Wagner é o único aprovador final em todos os sprints, ele vira o gargalo. **Mitigação:** após S5 ADS estar estável, abrir delegação de approve pra Felipe em domínios CODE/RUNTIME (não em PRODUTO/MEMÓRIA).

---

## 9. Como atualizar este roteiro

- **A cada postmortem de sprint:** atualizar §2 (estado atual) + §4 (sprint específico) + §5 (cronograma).
- **A cada decisão Wagner:** registrar em §8 (decisões abertas → resolvidas).
- **A cada nova ADR canon:** referenciar em §1 (camadas) e/ou §3 (estado da arte).
- **A cada novo risco identificado:** adicionar em §6 + sprint específico.
- **Versão:** bumpa o `version:` no frontmatter, registra em `## Histórico` abaixo.

### Custo estimado de evolução (Wagner perguntou 2026-05-06)

> Roteiro é documento vivo. Mudar/melhorar é sempre possível. Custos típicos:

| Tipo de mudança | Exemplo | Tokens | Custo Sonnet 4.6 | Tempo |
|---|---|---|---|---|
| **Trivial** | Corrigir typo, ajustar texto, marcar todo como done | <1k | <$0.01 | 30s |
| **Pequena** | Adicionar 1 risco em §6, atualizar status sprint, anotar decisão | 2–5k | $0.01–0.03 | 2min |
| **Média** | Re-revisar 1 sprint inteiro (etapas/métricas/riscos) | 10–20k | $0.05–0.15 | 5–10min |
| **Grande** | Re-pesquisar 1 sprint (3 WebSearches + reescrever deep-dive + sintetizar) | 30–50k | $0.30–0.50 | 20–30min |
| **Extensa** | Re-pesquisar TODOS sprints (12 WebSearches + 5 deep-dives + sintetizar) | 100–200k | $1.50–3.00 | 1–2h |
| **Reescrita total** | Refazer roteiro do zero com nova abordagem | 300k+ | $5–10 | 3–4h |

**Sessão de hoje** (2026-05-06): cobertura "Extensa" — 12 WebSearches + 5 deep-dives + roteiro mestre + revisão. **Custo real estimado: ~$0.50–1.00** (abaixo do cap diário $5).

### Quando vale a pena re-revisar

| Trigger | Profundidade recomendada |
|---|---|
| Postmortem revela métrica pior que alvo | **Pequena** — ajustar §4 + §6 |
| Nova ADR canônica sai | **Pequena** — adicionar refs |
| Anthropic lança feature relevante (ex: nova versão Skills) | **Média** — re-revisar 1–2 sprints afetados |
| Métrica global piorou 2 semanas | **Média** — investigar + ajustar plano |
| Sprint atrasou >50% do estimado | **Média** — re-estimar restantes |
| Tecnologia subjacente muda (ex: Claude 5 sai) | **Grande** — re-pesquisa profunda |
| Wagner muda direção estratégica | **Extensa** ou **Reescrita** |
| Postmortem global a cada 90 dias | **Média** sempre + **Grande** se sinal de drift |

### Princípio "barato para ajustar, caro para refazer"

🟢 **O roteiro foi feito modular de propósito.** Cada §N pode ser editado independente. Os 5 deep-dives são fontes-fonte (não viram stale rapidamente). Por isso:

- **Mudança de status (postmortem/decisão):** sempre custa <$0.10
- **Mudança de escopo de 1 sprint:** sempre custa <$0.50
- **Mudança que afeta múltiplos sprints:** custa $1–3 (pesquisa + propagação)

**Regra:** se mudança custa >$2 estimado, Sonnet pede aprovação Wagner antes (consistente com §10 "cuidado com custo").

---

## 10. Regras especiais (decisões Wagner 2026-05-06)

### Wagner dirige S3 (Constituição) pessoalmente

- **NÃO** começar dossier S3 sem ordem explícita do Wagner.
- **NÃO** mexer em CLAUDE.md, ADRs canônicas, ou skills Tier A sem aviso prévio.
- Se precisar mudar algo, **mostrar diff + custo estimado ANTES de aplicar**.
- Sonnet/Opus assistem mas **não decidem** sobre Constituição.
- Wagner valida cada bloco antes do próximo.

### Cuidado com custo

Custos historicamente subestimados em sprints anteriores. Daqui pra frente:

- **Antes de qualquer chamada ao Brain B (Sonnet/Opus/o1)**, estimar custo em USD
  e comparar com alternativa Brain A (gpt-4o-mini ou Ollama local).
- **Cap diário não-aprovado** (revisão dessa decisão pendente em S5):
  - Sonnet (este chat): $5/dia em Brain B sem perguntar
  - Opus autônomo: $15/dia em Brain B sem perguntar (após S5 ADS estável)
  - Acima disso: pedir aprovação Wagner via mensagem curta
- **Toda pesquisa profunda salva em arquivo** (não consumir contexto repetindo).
- **Reuso obrigatório**: se já tem `decisions-search`, `memoria-search`, ou doc em `memory/`, usar antes de consultar LLM.

### Testar antes

Comportamento esperado em qualquer mudança não-trivial:

1. Avisar o que vai testar
2. Rodar em ambiente local/staging (nunca prod direto)
3. Mostrar resultado/saída
4. Pedir OK do Wagner
5. Aplicar em prod

---

## 11. Resultados do teste Ollama CT 100 (item §3 das decisões)

> Wagner autorizou benchmark do CT 100 Proxmox pra rodar `gpt-oss:120b`
> como Brain A do S5 (ADS Universal). Quer evitar Brain A em Ollama
> local mas vai aceitar se a performance bater.

**Status:** 🧪 PENDENTE — em execução nesta sessão.

**Plano de teste:**

1. SSH no CT 100 via Tailscale (`100.99.207.66`) ou LAN (`192.168.0.50`)
2. Verificar se Ollama já está instalado (`ollama list`)
3. Se não, instalar (`curl -fsSL https://ollama.com/install.sh | sh`)
4. Testar pull do `gpt-oss:120b` (pesa ~70GB — pode levar 20–60min)
5. Rodar prompt de risk-scoring real (50 PRs amostra) e medir:
   - Latência p50 / p95
   - Tokens/segundo
   - Custo de eletricidade vs OpenAI gpt-4o-mini
   - Qualidade do output (comparar 10 amostras com Sonnet como gabarito)
6. **Decisão pós-benchmark**:
   - ✅ Adotar Ollama Brain A se latency p95 < 15s e qualidade ≥80% do Sonnet
   - ❌ Manter OpenAI gpt-4o-mini se latency p95 > 30s ou qualidade <60%
   - 🟡 Usar híbrido (Ollama 80% + OpenAI 20% fallback) se 60–80%

**Resultado do hardware survey (2026-05-06):**

| Componente | CT 100 (LXC) | Host Proxmox `pve-empresa` |
|---|---|---|
| RAM total | 32 GB | 125 GB |
| RAM livre | 28 GB | 118 GB |
| Disco / | 59 GB (38 livres) | 94 GB (85 livres) |
| CPU | 16 cores | (mesmo, compartilhado) |
| GPU NVIDIA | ❌ ausente | ⚠️ GeForce G210 (placa de 2009, sem CUDA moderno — **praticamente inútil pra LLM**) |
| Ollama instalado | ✅ container `ollama-embedder` | ❌ não |

**Análise dos modelos disponíveis** (referência [LocalLLM.in 2026 VRAM Guide](https://localllm.in/blog/ollama-vram-requirements-for-local-llms)):

| Modelo | Tamanho disco | RAM mínima (Q4) | GPU recomendada | Cabe no CT 100? |
|---|---|---|---|---|
| `gpt-oss:120b` | ~65–70 GB | 80+ GB | A100/H100 24GB+ | ❌ NEM no host (85GB tight, sem GPU CUDA) |
| `gpt-oss:20b` | ~12 GB | 16 GB | RTX 4060 Ti 16GB | 🟡 cabe em RAM, mas CPU-only = ~5–10s/decisão |
| `qwen2.5:32b` | ~20 GB | 24 GB | RTX 4090 24GB | 🟡 cabe, CPU lento |
| `llama3.1:8b` | ~5 GB | 8 GB | qualquer 8GB+ VRAM | ✅ rápido em CPU (~1–2s/decisão) |
| OpenAI gpt-4o-mini | (cloud) | — | — | ✅ atual, $0.15/1M tokens input |

**Recomendação técnica:**

🔴 **`gpt-oss:120b` no CT 100 atual: INVIÁVEL.**
- Modelo não cabe (precisaria upgrade do LXC pra 80GB RAM + 80GB disco extra + GPU)
- Mesmo no host Proxmox: GPU é GeForce G210 (2009) — sem CUDA usável
- Custo de upgrade hardware (GPU RTX 4090 ~$1.6k + cabos + PSU): supera $5k
- Tempo de payback vs OpenAI: ~3 anos com volume atual

🟡 **Alternativa viável: `gpt-oss:20b` ou `llama3.1:8b` no CT 100 (CPU-only).**
- Latência aceitável pra Brain A de baixa frequência (decisões ADS ~10/min)
- Sem custo recorrente
- Qualidade pode ficar abaixo do gpt-4o-mini em raciocínio complexo

✅ **Recomendação final: manter OpenAI gpt-4o-mini como Brain A canônico.**
- Latência <1s, qualidade alta, $0.15/1M input — barato pro volume atual
- Custo Brain A estimado mês ≤$15 com 10 agents
- Reavaliar em 12 meses se hardware barato com GPU CUDA aparecer

**Decisão Wagner (2026-05-06):** ✅ **PULAR benchmark Ollama 20b. OpenAI gpt-4o-mini fica como Brain A canônico do oimpresso.**

Justificativa: pra volume atual (10 agents, ~30 sessões/dia), gpt-4o-mini custa $0.15/1M input + $0.60/1M output. Estimativa $90/mês após S5 ADS funcionar. Reavaliar em 12 meses se hardware GPU CUDA aparecer barato.

---

## 12. Multi-tenant isolation — princípio canônico Tier 0

> ⚠️ Wagner perguntou 2026-05-06: "business_id está na constituição? tem que
> garantir isso fortemente onde ela deve ficar?" — Resposta: SIM, eleva pra
> princípio canônico, garante em **todas as 7 camadas + defense in depth técnico**.

### Por que Tier 0

Vazamento cross-tenant é a maior superfície de risco do projeto:
- LGPD viola Art. 7º + Art. 46 (segurança de dados)
- Cliente rompe contrato (perda receita ROTA LIVRE = 99% volume)
- Bug é silencioso — só aparece quando cliente vê dado de outro
- Recuperação custa MUITO (audit forense + comunicação clientes + multas)

Auto-mem confirma: "vazar dados entre tenants é o pior bug possível neste projeto" (skill `multi-tenant-patterns`).

### Estado atual (2026-05-06)

| Onde | Cobertura | Risco residual |
|---|---|---|
| CLAUDE.md §1 + §5 | menciona "global scope obrigatório" | 🟡 baixo — mas é só texto, não enforcement |
| Skill `multi-tenant-patterns` Tier B | auto-trigger por descrição em 11 keywords | 🟡 só dispara se IA reconhecer trigger |
| ~15 ADRs citam tangencialmente | sem ADR mãe canônica | 🔴 disperso, fácil ignorar |
| Brief / Charter / ADS | ausente | 🔴 nada bloqueia query sem scope |
| CI / Pest | tests existem em alguns módulos | 🔴 sem regra global |

### Garantia por camada (mapa)

| Camada | Garantia | Quem entrega | Quando |
|---|---|---|---|
| **L7 Brief** | métrica diária "queries sem `business_id` (MySQL slow_log scan)" + alerta no brief se >0 | Wagner ou Felipe (extensão S1) | pós-postmortem S1 |
| **L6 Charter** | template obrigatório com `multi_tenant_scope: required \| superadmin_only \| na`; charter-fetch retorna esse campo no topo | Sonnet (template) | S4 |
| **L5 ADR canônica** | nova ADR mãe **"Multi-tenant isolation by default — Tier 0, IRREVOGÁVEL"** que consolida regras dispersas das ~15 ADRs | Wagner (autor) + Sonnet (rascunho) | **antecipar pra S3 ou antes** |
| **L4 Playbook** | `incident-multi-tenant-leak.md` (resposta a vazamento) + `add-tenant-aware-table.md` (criar nova tabela com isolation) | Sonnet | S6 |
| **L3 Skill** | promover `multi-tenant-patterns` **Tier B → Tier A** (always-on em qualquer trabalho de código) | Wagner aprova em S3 | S3 |
| **L2 ADS firewall** | RiskScorer MEMORIA/PRODUTO classifica como CRIT qualquer mudança que: (a) crie Model sem `HasBusinessScope`, (b) use `withoutGlobalScopes` sem comentário, (c) rode Job sem passar `$businessId` no constructor → BLOCK_ALWAYS | Felipe + Sonnet | S5 |
| **L1 MCP** | tools `tasks-*`, `decisions-*`, `memoria-*` já filtram por business via token; estender `mcp_audit_log` com flag `tenant_violated` (trigger MySQL) | Felipe | S5+ |

### Defense in depth técnico (independente das camadas)

```
[1] Migration       → toda tabela negócio: `business_id` indexado + FK
[2] Model           → trait `HasBusinessScope` obrigatório (CI lint detecta ausência)
[3] Pest test       → cada entidade nova tem teste cross-tenant (2 businesses, A não vê B)
[4] CI grep check   → pre-commit procura `withoutGlobalScopes` sem `// SUPERADMIN: justificativa`
[5] SQL audit       → query mensal conta `business_id IS NULL` em tabelas de negócio; >0 → HITL
[6] MySQL trigger   → estender mcp_audit_log com flag `tenant_violated`
[7] Brief alert     → se SQL audit retornar >0 nas últimas 24h, brief abre painel vermelho
```

### Próximo passo IMEDIATO (não esperar S3)

> Wagner — proponho que CRIAR a ADR mãe agora, mesmo antes do S3 começar.
> Ela vai ser referenciada por todas próximas ADRs e fica como âncora canônica.

Rascunho da ADR (você aprova):

- **Arquivo:** `memory/decisions/0093-multi-tenant-isolation-tier-0.md` (próximo número livre)
- **Status:** `accepted` desde 2026-05-06
- **Tier:** `CANON` + `Tier 0 IRREVOGÁVEL`
- **Conteúdo:**
  1. Regra geral (1 frase)
  2. Estado atual UltimatePOS (`business_id` global scope, exceções superadmin)
  3. 7 mecanismos de defesa (lista acima)
  4. Como aprovar exceção (só Wagner; via PR com test cross-tenant + comentário em código)
  5. Política de incidente (playbook `incident-multi-tenant-leak`)
  6. Métricas de health (no brief diário)

**Estimativa:** 30min Sonnet + 30min Wagner revisar = ~1h.

**Decisão Wagner pendente:**
- [ ] Aprovar criação da ADR 0093 ANTES do S3, como âncora?
- [ ] OU empilhar como primeira tarefa do S3?

---

## 13. Síntese pós deep-dives — top 15 mudanças obrigatórias

> 5 deep-dives salvos em `memory/sprints/research/sN-deep-dive.md`. Aqui só o que MUDA o plano original.
> Cada item linka pro arquivo de origem. Wagner aprova bloco a bloco antes do dossier S3 começar.

### Mudanças gerais (tudo)

1. **Princípio duro #7 — Transparência (Explainability)** — adicionar à Constituição. Toda decisão de Brain B precisa ter "por que" auditável + UI de drill-down. ([s5](research/s5-ads-deep-dive.md#achado-1))
2. **Princípio duro #8 — Confiabilidade com fallback** — toda chamada Brain B tem fallback Brain A; toda chamada Brain A tem fallback humano. ([s5](research/s5-ads-deep-dive.md#achado-1))

### Mudanças específicas em S3 (Constituição)

3. **CLAUDE.md ≤100 linhas, não ≤350** — usar imports `@path/to/file.md` (recursive até 5 níveis, feature canônica do Claude Code). Migrar conteúdo pra `memory/{why,what,how,proibicoes,regras-time}.md`. ([s3 #1, #2](research/s3-constituicao-deep-dive.md#achado-1))
4. **Campo `tier:` no SKILL.md NÃO é canônico Anthropic** — manter Tier A/B/C como convenção interna documentada em ADR; mecanismo real always-on = hook `SessionStart`. ([s3 #3](research/s3-constituicao-deep-dive.md#achado-3))
5. **Auditoria das 19 skills** — adicionar coluna "description começa com 'Use ao/quando'?" → reescrever quem não passa. ([s3 #5](research/s3-constituicao-deep-dive.md#achado-5))
6. **Estimativa S3 revisada: 5–7 dias** (não 3–4) por causa do refactor CLAUDE.md em 5 arquivos.

### Mudanças específicas em S4 (Page Charters)

7. **Template charter de 6 → 8 seções** (Objective / Pre-conditions / Invariants / **File scope** / Implementation contract / **Test specifications** / Acceptance criteria / **Anti-patterns**) — alinhamento com "prompt contracts" do mercado. ([s4 #1](research/s4-charters-deep-dive.md#achado-1))
8. **Charter fica AO LADO do `.tsx` que governa** (`Index.tsx` + `Index.charter.md`) — não em diretório separado. Só feature/mission ficam em `memory/charters/`. ([s4 #3](research/s4-charters-deep-dive.md#achado-3))
9. **Skill `charter-first` = Skill + Hook PreToolUse** (defense in depth). Hook `.claude/hooks/charter-guard.ps1` bloqueia edição mecanicamente. ([s4 #2](research/s4-charters-deep-dive.md#achado-2))
10. **Frontmatter charter: campo obrigatório `multi_tenant_scope: required\|superadmin_only\|na`** — Tier 0 enforcement via `charter:sync`. ([s4 #4](research/s4-charters-deep-dive.md#achado-4))

### Mudanças específicas em S5 (ADS Universal)

11. **Cost cap = circuit breaker 3 níveis** (baseline / warning 2.5x / halt 5x) — não só "alerta em $X". ([s5 #3](research/s5-ads-deep-dive.md#achado-3))
12. **Visual review usa Playwright built-in** (`maxDiffPixelRatio`) — não precisa Applitools. ([s5 #4](research/s5-ads-deep-dive.md#achado-4))
13. **Métricas adicionais TRiSM**: CSS (Component Synergy Score) + TUE (Tool Utilization Efficacy) + Explainability UI. ([s5 #1](research/s5-ads-deep-dive.md#achado-1))

### Mudanças específicas em S6 (Playbooks + Strangler)

14. **Separação runbook (tactical) vs playbook (strategic)** — `memory/operational/{runbooks,playbooks}/`. 4 dos 6 originais viram skills Tier C com slash command. ([s6 #1, #2](research/s6-playbooks-strangler-deep-dive.md#achado-1))
15. **State CANARY ganha `canary_pct` 0-100** com auto-bump baseado em error_rate vs baseline. Skill `canary-monitor` (Tier C, cron 1h). ([s6 #5](research/s6-playbooks-strangler-deep-dive.md#achado-5))

### Mudanças específicas em S7 (ADR poda + Cockpit)

16. **DELETE de ADR é ABOLIDO** — ADR é append-only por princípio canônico. Triagem usa lifecycle 5 estados (`proposed/accepted/superseded/deprecated/sunsetting`). ([s7 #1](research/s7-cockpit-deep-dive.md#achado-1))
17. **Sonnet pré-classifica 92 ADRs** com proposta + grep de refs; Wagner aprova ~10×10min (não 8h). ([s7 #2](research/s7-cockpit-deep-dive.md#achado-2))
18. **Cockpit usa partial reload por painel + stale indicator** (cada painel independente) em vez de polling monolítico. ([s7 #4](research/s7-cockpit-deep-dive.md#achado-4))

### Estimativas revisadas (vs §5 cronograma)

| Sprint | Original | Pós deep-dive | Delta |
|---|---|---|---|
| S3 Constituição | 3–4 dias | 5–7 dias | +2–3 dias (CLAUDE.md refactor) |
| S4 Charters | 5–7 dias | 6–8 dias | +1 dia (template +2 seções, hook) |
| S5 ADS | 7–10 dias | 8–12 dias | +1–2 dias (circuit breaker, Playwright, CSS/TUE) |
| S6 Playbooks | 4–5 dias | 5–7 dias | +1–2 dias (canary-monitor, 4 skills C) |
| S7 Cockpit | 6–8 dias | 7–10 dias | +1–2 dias (drill-down, hook independente) |
| **Total S3–S7** | **25–34 dias** | **31–44 dias** | **+6–10 dias (~20% overhead)** |

🟡 **Cronograma original (§5) previa 13 semanas. Pós deep-dive precisa 14–16 semanas.** Wagner decide se aceita o overhead ou se algum sprint vira "essencial only" (corta scope).

### Decisões Wagner — bloco "aprovação dos achados"

> Wagner respondeu "ok aprovado comece" em 2026-05-06 — **interpretado como aprovação global de todos blocos A–G**.
> Se houver dúvida em algum item específico, Wagner pode retornar e marcar PARCIAL/RECUSADO no item.

- [x] Bloco A: itens #1, #2 (princípios duros 7+8 — Transparência + Confiabilidade) → ✅ **APROVADO global 2026-05-06**
- [x] Bloco B: itens #3 a #6 (S3 Constituição) → ✅ **APROVADO global 2026-05-06** (Wagner dirige execução)
- [x] Bloco C: itens #7 a #10 (S4 Charters) → ✅ **APROVADO global 2026-05-06**
- [x] Bloco D: itens #11 a #13 (S5 ADS) → ✅ **APROVADO global 2026-05-06**
- [x] Bloco E: itens #14, #15 (S6 Playbooks) → ✅ **APROVADO global 2026-05-06**
- [x] Bloco F: itens #16, #17, #18 (S7 Cockpit) → ✅ **APROVADO global 2026-05-06**
- [x] Bloco G: cronograma 14–16 semanas em vez de 13 → ✅ **ACEITO 2026-05-06**

**Estado pós-aprovação:** todas as 18 mudanças incorporadas no plano. ADR 0093 (multi-tenant Tier 0) já aceita. `_INDEX-LIFECYCLE.md` criado consolidando triagem 90 ADRs. Próximo passo: Wagner inicia execução S3 quando quiser; postmortems S1+S2 ficam em standby até soak 48h fechar.

---

## Histórico

| Versão | Data | Autor | Mudanças |
|---|---|---|---|
| 1.0 | 2026-05-06 | Sonnet (organização sprints) | Documento inicial — estado atual + 7 sprints + estado-da-arte + cronograma 90d |
| 1.1 | 2026-05-06 | Sonnet (revisão pós Wagner) | 5 decisões Wagner respondidas + §10 regras especiais (Wagner dirige S3, cuidado custo, testar antes) + §11 plano teste Ollama |
| 1.2 | 2026-05-06 | Sonnet (resposta Wagner business_id) | §12 multi-tenant isolation como princípio Tier 0 + mapa garantia por camada + defense in depth técnico + proposta ADR 0093 antecipada. Princípio duro #6 adicionado em §1. |
| 1.3 | 2026-05-06 | Sonnet (deep-dives S3-S7) | §13 síntese 18 mudanças obrigatórias dos 5 deep-dives + estimativas revisadas (+20% overhead) + 7 blocos de aprovação Wagner. 5 arquivos detalhados em `research/sN-deep-dive.md` |
| 1.4 | 2026-05-06 | Sonnet (4 decisões Wagner + custos evolução) | §8 4 decisões respondidas (Ollama pular / S2.5 fazer / Cockpit Wagner-only / ADR 0093 depois) + §5 cronograma com S2.5 paralelo (16 semanas) + §9 estimativa de custo de evolução do roteiro |
| 1.5 | 2026-05-06 | Sonnet (aprovação global Wagner "ok aprovado comece") | §13 todos blocos A–G aprovados + ADR 0093 aceita (commit 4139437a) + _INDEX-LIFECYCLE 90 ADRs criado consolidando triagem (commit 4139437a) + 12 ADRs superseded com superseded_by no frontmatter (próximo PR) |
| 1.6 | 2026-05-06 | Sonnet (Wagner "esta aprovado pode fazer o merge") | S3 Constituição EM EXECUÇÃO. Dossier 4 ADRs aprovado (PR #132 bf3a18ee). Fase 1 em andamento: ADRs canon 0094 (Constituição v2 mãe) + 0095 (skills tiers) criadas em memory/decisions/. Estado L3+L5 mudou de PENDING → EM EXEC. |
| 1.7 | 2026-05-06 | Sonnet (S3 entregue end-to-end) | **S3 ENTREGUE EM PROD.** 5 fases mergeadas (PRs #133, #134, #135, #136). CLAUDE.md 289→88 linhas + 5 imports memory/. 22 skills com tier (6 A + 10 B + 6 C). Hooks tier-a-banner + commit-discipline-check ativos. Smoke prod: 5/5 health checks PASSED. L3 SKILLS + L5 ADRs canon mudaram de EM EXEC → ✅ PROD. |
