---
id: audits-2026-05-pre-sales-05-skills-audit-2026-05-09
name: Audit skills .claude/skills — 2026-05-09
description: Auditoria pré-sales das 29 skills do projeto oimpresso. Aplica 4 testes ROI da meta-skill (substitui? humano repetitivo? ROI mensurável? acelera ERP autônomo R$ [redacted Tier 0]M/24m?) + cruza convenção tier ADR 0095 + audit anterior s3-constituicao/03-skills-audit.md (que projetava 20 skills, hoje temos 29).
type: audit
status: draft
created: 2026-05-09
authors: [opus-audit-agent]
related_adr: 0095
sprint: pre-sales
---

# Audit skills `.claude/skills/` — 2026-05-09

> **Contexto:** auditoria pós-S3+S6, comparando estado real (29 skills) contra projeção do `s3-constituicao/03-skills-audit.md` (que projetava 20).
> **Critério primário:** os 4 testes da skill `meta-skill-roi-erp-autonomo` + convenção tier ADR 0095.
> **Restrição:** apenas Read+audit. Wagner aprova plano antes de qualquer mv/rm/edit.

---

## Sumário executivo

- **Skills totais:** 29 (vs 20 projetadas no audit anterior — +9 organic growth em 3 dias)
- **Por tier (atual):**
  - Tier A: 8 (`brief-first`, `mcp-first`, `multi-tenant-patterns`, `commit-discipline`, `mwart-process`, `mwart-comparative`, `charter-first`, `session-start-check` + dormente `ads-route`)
  - Tier B: 14 (ads-decision-flow, automem-pending, charter-write, comparativo-do-modulo, criar-modulo, jana-arch, jana-recall-flow, memory-sync, migrar-modulo, mwart-quality, publication-policy, runtime-rules-hostinger-ct100, sidebar-menu-arch, ui-component-creator)
  - Tier C: 6 (cockpit-runbook, meta-skill-roi-erp-autonomo, oimpresso-cc-watcher-setup, oimpresso-stack, oimpresso-team-onboarding, proxmox-docker-host)
  - Sem tier explícito: 1 (verificar charter-write — tem `type: process-skill` mas falta `tier:`)
- **Decisão proposta:**
  - ✅ **Manter como está:** 16
  - 🟡 **Refatorar (description, rebaixar/promover tier, rename):** 9
  - ❌ **Remover ou arquivar:** 4
  - ➕ **Sugerir nova:** 7

> **Achado central:** Tier A inflou de 6 (projetado) → 8 (real) em 3 dias. `mwart-comparative` + `mwart-process` juntas carregam ~600 linhas de body Tier A always-on quando só ~5% das sessões fazem MWART. Custo estimado: **~12-18 kb por sessão sem MWART** carregados sem necessidade. Esta é a maior oportunidade da auditoria.

---

## Tabela master (todas as skills)

| # | Skill | Tier atual | Tier sugerido | Status | ROI declarado | Justificativa curta |
|---|---|---|---|---|---|---|
| 1 | `brief-first` | A | A | ✅ | 27k tokens/sessão | Hook SessionStart real, ROI medido. Manter. |
| 2 | `mcp-first` | A | A | ✅ | governance + audit log | Folder ainda chamado `oimpresso-mcp-first` no description (drift name vs path) — ajustar |
| 3 | `multi-tenant-patterns` | A | A | ✅ | tenant leak prevention | Princípio duro #6 IRREVOGÁVEL. Manter. |
| 4 | `commit-discipline` | A | A | ✅ | reduz risk score | Princípio duro #5. Manter. |
| 5 | `session-start-check` | A | A | ✅ | conflito Claude-A vs Claude-B | ADR 0119 — manter. Verificar `whats-active` tool deployed |
| 6 | `mwart-process` | A | **B** | 🟡 | -1 PR corretivo/migração | Carregada SEMPRE mas usada só em sessões MWART (~5%). description já é específica → rebaixar. Salva ~3 kb/sessão |
| 7 | `mwart-comparative` | A | **B** | 🟡 | -1 retrabalho design | Carregada SEMPRE com ~25 linhas de description (orquestra plugin Anthropic). Mesma lógica do #6. Salva ~5 kb/sessão |
| 8 | `charter-first` | A | A | ✅ | charter ≠ doc morto | Recém-ativada (2026-05-08). Hook PreToolUse real. Manter |
| 9 | `ads-route` | A dormente | A dormente | ✅ | firewall custo Brain B | enabled: false até S5. Manter dormente |
| 10 | `ads-decision-flow` | B | B | ✅ | -18 ADRs leitura | Auto-trigger funciona. Manter |
| 11 | `automem-pending` | B | B | 🟡 | block stale auto-mem | description tem 6 gatilhos amplos — pode false-positivar. Reduzir escopo após 30d telemetria |
| 12 | `charter-write` | (sem tier) | C | 🟡 | charter manual → 2min | Tem `type: process-skill` mas frontmatter falta `tier:`. Adicionar `tier: C` (slash command `/charter-write`) |
| 13 | `comparativo-do-modulo` | B | B | ✅ | gap analysis manual | v2.0 atualizada 2026-05-08. Manter |
| 14 | `criar-modulo` | B | B | ✅ | -1 ADR + 1 receita | Manter |
| 15 | `jana-arch` | B | B | ✅ | -18 ADRs leitura | Manter |
| 16 | `jana-recall-flow` | B | B | ✅ | -14 gotchas leitura | Manter |
| 17 | `memory-sync` | B | B | ✅ | webhook visibility | Manter |
| 18 | `migrar-modulo` | B | B | ✅ | drift safe pattern | Manter |
| 19 | `mwart-quality` | B | B | 🟡 | -3 PRs corretivos | description menciona "PRs #138-#141" muito específicos — generalizar (vai envelhecer rápido) |
| 20 | `publication-policy` | B | B | ✅ | -1 pergunta/sessão | Manter |
| 21 | `runtime-rules-hostinger-ct100` | B | B | ✅ | shared hosting safety | Manter |
| 22 | `sidebar-menu-arch` | B | B | 🟡 | sidebar arch | description ainda começa "Reconhecer, auditar..." (não "Use ao") — audit anterior mandou reescrever, não foi feito |
| 23 | `ui-component-creator` | B | B | ✅ | reuso shared components | Recém-criada 2026-05-08. Monitorar telemetria 30d |
| 24 | `cockpit-runbook` | C | C | ✅ | -1 RUNBOOK manual | Slash command real. Manter |
| 25 | `meta-skill-roi-erp-autonomo` | C | C | ✅ | filtro skill nova | Meta-governance. Manter |
| 26 | `oimpresso-cc-watcher-setup` | C | C | ✅ | one-time setup | Manter |
| 27 | `oimpresso-stack` | C | **arquivar** | ❌ | leitura CLAUDE.md | CLAUDE.md já tem ≤100 linhas + @imports (best-practice 2026). Skill virou redundante — `brief-first` já carrega contexto. Telemetria ≥0 disparos esperada |
| 28 | `oimpresso-team-onboarding` | C | C | ✅ | one-time setup MCP | Manter (uso real ao entrar dev novo) |
| 29 | `proxmox-docker-host` | C | C | ✅ | -INFRA.md leitura | Manter |

---

## Skills Tier A — análise individual

### A.1 `brief-first` — ✅ MANTER
- Hook `SessionStart` real (PR #129). ROI medido: 27k → 3k tokens.
- Description é blocker explícito; não compete com outras tools.
- **Sem ação.**

### A.2 `mcp-first` — 🟡 AJUSTAR (drift name vs path)
- Frontmatter ainda diz `name: oimpresso-mcp-first` mas pasta foi renomeada (audit anterior). Ler arquivo confirmou: `name: oimpresso-mcp-first` na linha 2.
- **Ação:** atualizar frontmatter `name: mcp-first` (5 min Wagner).

### A.3 `multi-tenant-patterns` — ✅ MANTER
- Tier 0 IRREVOGÁVEL. Hook condicional pode ser otimizado (só dispara se sessão toca Eloquent/Migration).
- **Sem ação imediata.**

### A.4 `commit-discipline` — ✅ MANTER
- Hook PreToolUse em git commit real. Princípio duro #5.
- **Sem ação.**

### A.5 `session-start-check` — ✅ MANTER (verificar deploy)
- ADR 0119 Tier 1. Depende de tool MCP `whats-active` estar deployed no MCP server.
- **Ação opcional:** verificar se `whats-active` está respondendo no `mcp.oimpresso.com` (curl smoke test).

### A.6 `mwart-process` — 🟡 REBAIXAR PRA B
- Description já é Anthropic-pattern ("Use SEMPRE que..." + paths específicos `Pages/<Mod>/<Tela>.tsx`).
- Auto-trigger por description funciona perfeitamente em B — não precisa always-on.
- Ganho: ~3 kb por sessão não-MWART (95% das sessões).
- Hook `block-mwart-violation.ps1` continua bloqueando independente de tier — segurança preservada.

### A.7 `mwart-comparative` — 🟡 REBAIXAR PRA B
- Mesma lógica que A.6. Description é específica o suficiente (15 dimensões, paths, /mwart-comparative).
- Ganho: ~5 kb por sessão (description gigante orquestrando 6 sub-skills do plugin Design).
- **Justificativa pra contestar:** Wagner pode argumentar que MWART é o trabalho dominante S6+. Se telemetria 30d mostrar ≥80% sessões fazem MWART, voltar pra A.

### A.8 `charter-first` — ✅ MANTER
- Recém-ativada (2026-05-08). Hook real. Charter prod = 5 telas Tier A.
- **Sem ação imediata.** Coletar 30d telemetria.

### A.9 `ads-route` — ✅ MANTER (dormente)
- enabled: false até S5 (jul/2026). Sem custo carregamento (skill não dispara).

---

## Skills Tier B — análise individual

| Skill | Description starts with "Use ao/quando..."? | Auto-trigger funciona? | Overlap |
|---|---|---|---|
| `ads-decision-flow` | ✅ "Use ao trabalhar em Modules/ADS/" | sim | nenhum |
| `automem-pending` | ✅ "BLOQUEADOR — quando..." | sim, mas 6 gatilhos amplos | nenhum |
| `charter-write` | ✅ "ATIVAR quando user pedir..." | sim (slash command) | parcial: charter-first é leitura, charter-write é escrita — OK distintas |
| `comparativo-do-modulo` | ✅ "ATIVAR quando user pedir..." | sim (slash `/comparativo`) | parcial: cockpit-runbook gera RUNBOOK, comparativo gera INVENTARIO — OK |
| `criar-modulo` | ✅ "Use ao criar novo módulo..." | sim | nenhum |
| `jana-arch` | ✅ "Use ao trabalhar em Modules/Copiloto/" | sim | sobrepõe parcialmente com jana-recall-flow (ela mesma diz "mais específica que copiloto-arch") — OK hierárquico |
| `jana-recall-flow` | ✅ "Use ao tocar Modules/Copiloto/Services/Memoria/" | sim | filha de jana-arch — distinção clara |
| `memory-sync` | ✅ "ATIVAR após criar/editar..." | sim | nenhum |
| `migrar-modulo` | ✅ "Use ao mover, renomear..." | sim | nenhum |
| `mwart-quality` | ✅ "Use ANTES de criar/editar tela MWART..." | sim, mas description menciona PRs específicos #138-#145 | overlap pesado com mwart-process + mwart-comparative — todas as 3 disparam em Edit `Pages/<Mod>/<Tela>.tsx` |
| `publication-policy` | ✅ "Use ANTES de qualquer git push..." | sim | parcial: commit-discipline (Tier A) também trigger em commit/push — OK complementares |
| `runtime-rules-hostinger-ct100` | ✅ "Use ANTES de SSH no Hostinger..." | sim | nenhum |
| `sidebar-menu-arch` | ❌ "Reconhecer, auditar e modificar..." | parcial (não Anthropic-pattern) | nenhum |
| `ui-component-creator` | ✅ "Use ao criar/modificar componentes React..." | sim | parcial overlap com mwart-comparative (ambas disparam em criar Page Inertia) |

**Top 3 issues Tier B:**
1. **`sidebar-menu-arch` description não foi reescrita** apesar do audit anterior s3 (Bloco D) mandar — débito técnico.
2. **`mwart-quality` description menciona PRs específicos #138-#145** que vão envelhecer rápido — generalizar pra "padrões de bug recorrentes em PRs Pages Inertia".
3. **3 skills MWART (process+comparative+quality) overlap em Edit Pages/<Mod>/<Tela>.tsx** — Claude carrega contexto de todas. Considerar consolidar quality dentro de process ou comparative.

---

## Skills Tier C — análise individual

| Skill | Slash command faz sentido? | Caso real conhecido | Veredito |
|---|---|---|---|
| `cockpit-runbook` | ✅ user pede "runbook" | sim — 13 RUNBOOKs gerados | manter |
| `meta-skill-roi-erp-autonomo` | parcial — também trigger ao "criar skill" | sim — usado em discussões skill nova | manter |
| `oimpresso-cc-watcher-setup` | ✅ one-time per dev | sim — setup MEM-CC-1 | manter |
| `oimpresso-stack` | ❌ overlap com brief-first + CLAUDE.md curto | improvável | **arquivar** |
| `oimpresso-team-onboarding` | ✅ dev novo | sim — Felipe/Maiara onboarding | manter |
| `proxmox-docker-host` | ✅ trabalho infra CT 100 | sim — uso recorrente subir subdomínio | manter |

---

## Skills redundantes/sobrepostas

### Cluster MWART (3 skills, overlap 80%)
- `mwart-process` (Tier A): processo 5 fases canônico
- `mwart-comparative` (Tier A): F1.5 visual gate
- `mwart-quality` (Tier B): F2+F3 pré-flight checks 9 itens

**Proposta:** rebaixar process+comparative pra B (já analisado). `mwart-quality` description deve referenciar `mwart-process` como "Use junto com" pra evitar Claude carregar 3 vezes a mesma narrativa MWART.

### Cluster Jana (2 skills, hierarquia OK)
- `jana-arch` (Tier B): arquitetura geral
- `jana-recall-flow` (Tier B): subset memória/recall

**Veredito:** distinção é explícita na própria description ("mais específica que copiloto-arch") — manter ambas.

### Cluster onboarding (3 skills, overlap parcial)
- `oimpresso-stack` (Tier C): primer stack
- `oimpresso-team-onboarding` (Tier C): setup MCP per-dev
- `oimpresso-cc-watcher-setup` (Tier C): setup CC watcher

**Proposta:** arquivar `oimpresso-stack` (CLAUDE.md já é primer 85 linhas + brief-first carrega contexto). Manter team-onboarding + cc-watcher (one-time per dev, casos distintos).

### Cluster commit/publish (2 skills, complementares)
- `commit-discipline` (Tier A): formato + tamanho + PII
- `publication-policy` (Tier B): escala Wagner ou direto

**Veredito:** complementares (formato vs decisão). Manter.

---

## Skills com baixa adoção (heurística — falta telemetria mcp_skill_telemetry)

> ⚠️ Sem dados telemetria 30d disponíveis nesta auditoria. Heurística baseada em (a) audit anterior projetar 20 skills, (b) frequência de menção em session logs `memory/sessions/2026-05-*.md`, (c) idade da skill.

**Skills sem disparos prováveis (≥30d):**
- `oimpresso-stack` — coberta por brief-first/CLAUDE.md
- `oimpresso-cc-watcher-setup` — one-time only, devs já configuraram
- `ads-decision-flow` — Modules/ADS/ ainda não existe em produção (S5 ~jul)

**Skills com baixa adoção mas válidas (manter dormentes):**
- `ads-route` (Tier A dormente — correto)
- `charter-first` (recém-ativa, 2 dias)

---

## Gaps detectados — skills que deveriam existir mas não existem

### 1. `pest-fixture-builder` (Tier B) — ALTO ROI
- **Dor:** Wagner exigiu 2026-05-09 que mudanças em scope/Controller/Model/migration multi-tenant **só** sejam aceitas com Pest local rodado pelo dev (auto-mem `feedback_tenancy_changes_require_pest_local`).
- **Substitui:** humano escrever fixture multi-tenant manualmente (~10 min/test).
- **Trigger:** "criar Pest test", "fixture biz=N", Edit em `tests/Feature/<Mod>/*Test.php`.
- **Output:** scaffold Pest test com biz factory + global scope assertion + biz=1 (não 4) por default (ADR 0101).

### 2. `pii-redactor-check` (Tier A always-on) — TIER 0
- **Dor:** PII (CPF/CNPJ ROTA LIVRE) em commit/PR/log = grave LGPD. Skill `commit-discipline` menciona mas não enforça em conteúdo de file.
- **Substitui:** revisão manual Wagner pré-commit.
- **Trigger:** PreToolUse em Write/Edit + scan regex CPF/CNPJ/email cliente real no payload.
- **Cobre lacuna:** princípio Tier 0 sem hook hoje.

### 3. `task-create-batch` (Tier C, slash) — MÉDIO ROI
- **Dor:** Após audit (ex: este aqui, ou comparativo-do-modulo), Wagner aprova 20+ tasks no MCP — hoje é loop manual `tasks-create` × 20.
- **Substitui:** loop manual de criação de task.
- **Trigger:** `/task-batch <arquivo-yaml>` ou após output de comparativo.

### 4. `mwart-cutover-runner` (Tier C, slash) — ALTO ROI
- **Dor:** F5 cutover MWART tem checklist de 7 itens (aviso prévio, canary 7d, monitor 30d, smoke biz=1, backup DB, flag ON cliente, remove Blade) — proibida ser pulada (Tier 0 ADR 0104).
- **Substitui:** Wagner conferindo checklist mental.
- **Trigger:** `/mwart-cutover <Mod>/<Tela>` lê RUNBOOK + executa em sequência com gates.

### 5. `adr-author` (Tier C, slash) — MÉDIO ROI
- **Dor:** ADRs canônicas seguem template Nygard estrito (status/decided_at/parent_charter/supersedes). Hoje copiar de outra ADR e ajustar = 15min.
- **Substitui:** boilerplate manual.
- **Trigger:** `/adr-author <slug>` + Wagner valida proposta.
- **Cobertura:** complementa `comparativo-do-modulo` (que cria tasks) mas não cria ADR.

### 6. `health-check-interpret` (Tier B) — BAIXO ROI mas alto valor
- **Dor:** Daily 06:00 BRT roda `php artisan jana:health-check` (5 checks SQL: multi_tenant, brief_uptime, custo_brain_b, pii_leak, profile_distiller_drift). Quando falha, Wagner abre laravel.log manualmente.
- **Substitui:** investigação humana ALERT entries.
- **Trigger:** "health check falhou", "alerta jana:", ou Bash output de `jana:health-check`.

### 7. `cliente-rotalivre-context` (Tier B) — MÉDIO ROI
- **Dor:** auto-mem `cliente_rotalivre.md` tem 5+ quirks (monitor 1280px, format_date +3h, retroativo OK, biz=4 99% volume) que Claude esquece e gera bug.
- **Substitui:** lookup auto-mem manual.
- **Trigger:** mention "Larissa", "ROTA LIVRE", "biz=4", Edit em customizações cliente-específicas.
- **Nota:** parcial overlap com `automem-pending` — esta seria mais focada/ativa.

---

## Plano de ação 30 dias

### Fase 1 — Quick wins (hoje, ~30min Wagner)
1. **Arquivar `oimpresso-stack`** → mover pra `_archive/`. Salva ~2 kb/sessão. ADR HISTORICAL.
2. **Atualizar frontmatter `mcp-first`** → `name: mcp-first` (drift name vs path). 2 min.
3. **Reescrever description `sidebar-menu-arch`** com pattern "Use ao mexer no sidebar...". 5 min.
4. **Adicionar `tier: C` em `charter-write`** (frontmatter incompleto). 2 min.
5. **Generalizar description `mwart-quality`** removendo "#138-#145" (envelhece rápido). 5 min.

### Fase 2 — Decisão arquitetural (~1h Wagner + ADR)
6. **Rebaixar `mwart-process` + `mwart-comparative` de A → B** se telemetria 30d mostrar <80% sessões fazem MWART. Salva ~8 kb/sessão. **Precisa ADR específico** (Tier A → B) per ADR 0095.
7. **Reduzir gatilhos `automem-pending`** após 30d telemetria (atualmente 6 gatilhos, alguns false-positivam).

### Fase 3 — Criar skills novas (~3-5h espalhadas, prioridade Wagner)
8. **Criar `pii-redactor-check` Tier A** (alta prioridade — Tier 0 sem hook).
9. **Criar `pest-fixture-builder` Tier B** (alta prioridade — Wagner exigiu 2026-05-09).
10. **Criar `cliente-rotalivre-context` Tier B** (média prioridade — bugs recorrentes).
11. **Criar `mwart-cutover-runner` Tier C** (média — aciona em cada cutover).
12. **Criar `task-create-batch` Tier C** (baixa — convenience).
13. **Criar `adr-author` Tier C** (baixa — convenience).
14. **Criar `health-check-interpret` Tier B** (baixa — diagnóstico).

### Fase 4 — Telemetria (passivo, 30d)
15. Coletar `mcp_skill_telemetry` 30d pra validar:
    - Skills Tier A com <80% disparos → candidatas a B
    - Skills Tier B com 0 disparos → candidatas a archive
    - Falsa-positiva rate de `automem-pending` (overlap múltiplo trigger)

---

## ROI estimado da auditoria

| Item | Ganho |
|---|---|
| **Contexto liberado por sessão (Fase 1+2 aplicadas)** | **~10-13 kb** (8 kb mwart A→B + 2 kb oimpresso-stack archive + 1-3 kb description trims) |
| **Redução tokens repetidos** | ~12-15% sessões não-MWART; ~3-5% sessões MWART |
| **Tempo Wagner economizado (skills novas)** | `pii-redactor-check` ~30min/semana (revisão pré-commit) + `pest-fixture-builder` ~30min/PR multi-tenant (~3 PRs/sem) + `mwart-cutover-runner` ~20min/cutover (~2/sem) ≈ **~3-4h/semana** |
| **Risco LGPD reduzido** | `pii-redactor-check` Tier A previne PII em commit (hoje só `commit-discipline` lembra, não enforça) |

---

## Notas finais

1. **Audit anterior (s3-constituicao/03-skills-audit.md) projetava 20 skills.** Em 3 dias (06→09 maio) cresceu pra 29 — taxa de 3 skills/dia. Saudável mas merece governança: meta-skill já filtra novas (4 testes), mas precisa também filtrar **carga acumulada** (Tier A inflation).
2. **Sub-decisões pendentes do audit anterior** que ainda não foram fechadas:
   - [ ] Renames `copiloto-` → `jana-` (parece feito por arquivos `jana-arch/SKILL.md` mas frontmatter ainda diz `name: copiloto-arch` — drift)
   - [ ] `sidebar-menu-arch` reescrita description (não foi feito)
3. **3 skills com `name:` no frontmatter ≠ pasta:** mcp-first, jana-arch, jana-recall-flow. **Ação obrigatória:** alinhar nome (Anthropic harness usa `name:`, não path).
4. **Telemetria `mcp_skill_telemetry` é dependência crítica** pra Fase 2. Se schema/coleta não estiver vivo em prod, esta auditoria é o último ground-truth disponível.

---

**Status:** draft pendente revisão Wagner. Próximo passo: Wagner aprova/ajusta Fases 1+2 antes de qualquer mv/edit em `.claude/skills/`.
