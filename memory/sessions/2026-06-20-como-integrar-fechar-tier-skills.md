---
date: 2026-06-20
topic: "como-integrar — T7 fechar campo tier nas skills + loop telemetria→promoção"
type: session
---

# como-integrar — T7: fechar campo `tier` nas skills sem tier + loop telemetria→promoção

> Agente `como-integrar` (introspectivo). Data 2026-06-20. ADR mãe: 0095, emendada por 0225 + errata 0229.
> Veredito: **parcial — estende o que existe (ADR 0225/0229 já decidiram os tiers; falta só gravar no frontmatter + fechar o loop de relatório).**

---

## Fase 1 — INVENTÁRIO

| O que procurei | Onde achei | Status |
|---|---|---|
| Skills sem `tier:` no frontmatter | 18 de 70 SKILL.md (grep awk no FS) | **medido** — lista abaixo |
| Convenção tier A/B/C | `memory/decisions/0095-skills-tiers-convencao-interna.md` | canon (mãe) |
| Reconciliação Tier A pós-4.8 | `memory/decisions/0225-skills-tier-a-recalibracao-claude-4.8.md` | canon — **decide 5 Tier A núcleo + rebaixa 7** |
| Medição empírica do drift | `memory/decisions/0229-errata-0225-medicao-empirica-25-66-skills.md` | canon — 25/66 skills auto-marcam "BLOQUEADOR/eager" mas só 5 são Tier A |
| Fonte de verdade runtime do roster Tier A | `.claude/hooks/tier-a-banner.ps1` (SessionStart) | nomeia 5 Tier A + 6 auto-trigger |
| Tabela telemetria | `database/schema/mysql-schema.sql:5703` + migration `2026_05_06_170045_create_daily_brief_schema.php:41` | em prod (skill_name, agent_id, triggered_at, success, tokens_saved_estimate, context_payload) |
| Consumidor telemetria existente | brief aggregator SP `fix_brief_procedure_real_schema.php:106` → `v_skills_7d` + `v_skills_poda` | **parcial** — calcula uso 7d e poda, mas NÃO é tier-aware nem trimestral |
| Critério promoção/rebaixamento | ADR 0095 §"Critério pra promover/rebaixar tier" (B→A ≥80%/30d; B→C <10%/60d; C→arquivar >90d; A→B regressão) | canon, mas **nunca operacionalizado** (sem relatório que o aplique) |
| Hook de freshness de skills | `.claude/hooks/check-skills-fresh.ps1` | existe, mas NÃO parseia `tier:` (só detecta SKILL.md tocado) |
| Página de skills | `resources/js/Pages/ads/Admin/MetaSkills.tsx` | existe (ads module) — candidato a render do relatório |

### As 18 skills sem `tier:` + tier proposto (derivado de ADR 0225/0229, NÃO inventado)

Regra de derivação: (1) se nomeada no `tier-a-banner.ps1` como núcleo → **A**; (2) se description = "Use ao/quando..."/"ATIVAR quando user pedir..." (path/intenção) → **B**; (3) se só slash command/uso pontual → **C**. "BLOQUEADOR Tier A" na description NÃO basta — é exatamente o drift que 0229 mede (25/66 gritam, só 5 valem).

| # | Skill | Sinais | Tier proposto | Justificativa |
|---|---|---|---|---|
| 1 | `incident-done-checklist` | banner núcleo #3; "BLOQUEADOR" | **A** | ADR 0225 lista explícito como núcleo (DoD smoke real R1). Frontmatter só está stale. |
| 2 | `memory-first-secret-search` | banner núcleo #4; "BLOQUEADOR Tier A" | **A** | ADR 0225 núcleo (Wagner-instituído 2026-05-28, segurança). |
| 3 | `hostinger-dns-autonomy` | banner núcleo #5; "BLOQUEADOR Tier A" | **A** | ADR 0225 núcleo (Wagner-instituído 2026-05-28, anti-helpdesk). |
| 4 | `personas-resolve` | "BLOQUEADOR Tier A — ANTES de Edit/Write tsx" | **B** | NÃO está no banner núcleo (0225). Dispara por path `Pages/**/*.tsx`. "BLOQUEADOR" aqui é drift 0229 → rebaixar p/ B (não afrouxa: bind real é hook `charter-validate` + ADR UI-0016). ⚠️ confirmar c/ Wagner se ele quer manter binding via hook. |
| 5 | `pageheader-canon` | "Tier B auto-trigger por description" (texto da própria desc) | **B** | Autodeclara B; dispara em Edit `Pages/<Mod>/<Tela>/Index.tsx`. |
| 6 | `feedback-capture` | "Skill Tier B auto-trigger" | **B** | Autodeclara B; ATIVAR quando Wagner cola feedback cliente. |
| 7 | `feedback-dashboard` | "Skill Tier B" | **B** | Autodeclara B; ATIVAR em "/feedback-dashboard"/review backlog. |
| 8 | `design-memoria-reprocess` | "Tier B auto-trigger" (na desc) | **B** | Autodeclara B; dispara em edição doc design / handoff Claude Design. |
| 9 | `cliente-discovery` | "ATIVAR quando Wagner pedir /cliente-discovery..." | **B** | Auto-trigger por intenção (entrevistar cliente / call presencial). |
| 10 | `design-deep-analysis` | "ATIVAR quando Wagner pedir /design-deep..." | **B** | Auto-trigger por intenção (refator visual). |
| 11 | `cowork-prototype-replication` | tem `trigger_intensity: B`; dispara em Edit Pages com visual-source.html | **B** | Já sinaliza B via `trigger_intensity`. |
| 12 | `ticket-triage` | tem `type: process-skill`, `trust_level: L1`; "ATIVAR quando..." + slash | **B** | Auto-trigger por intenção (analisar ticket/conversa). |
| 13 | `wagner-request-refiner` | "ATIVAR quando Wagner manda múltiplos pedidos curtos" | **B** | Auto-trigger por padrão de input (CLAUDE.md UI v2 cita como operacionalizador). |
| 14 | `curador` | "ATIVAR quando user pedir 'ingerir conhecimento'.../curador <subcomando>" | **B** | Auto-trigger por intenção + tem subcomando slash. (Borderline B/C — pende uso real; default B.) |
| 15 | `module-grades-gate` | a própria desc diz "Tier C (slash command)" + dispara em CI fail | **C** | Autodeclara C. Slash `/module-grades-gate` + reação a CI. |
| 16 | `sdd-avaliar` | "Use ANTES de promover gate SDD... OU /sdd-avaliar"; dispara workflow | **C** | On-demand (slash + checkpoint quinzenal manual). Borderline B; default C por uso pontual/cerimonial. |
| 17 | `officeimpresso-financial-snapshot` | `type: tier-b-auto-trigger`, `status: draft` | **B** | Frontmatter já diz tier-b-auto-trigger (só falta o campo canônico `tier:`). |
| 18 | `officeimpresso-source-analysis` | `type: tier-b-auto-trigger`, `status: draft` | **B** | Idem #17. |

Resumo proposta: **3 Tier A** (1-3) · **13 Tier B** (4-14, 17-18) · **2 Tier C** (15-16). Borderline a confirmar c/ Wagner: `personas-resolve` (A→B é rebaixamento de "BLOQUEADOR"), `curador` (B vs C), `sdd-avaliar` (B vs C).

> NÃO está 80% feito → não PARO. A **decisão** (que tier) já existe em 0225/0229; o que falta é **gravar no frontmatter** (mecânico) + **fechar o loop de relatório** (feature pequena). É estender, não criar do zero.

---

## Fase 2 — PEGADINHAS APLICÁVEIS

| # | Pegadinha | Aplica? | Como respeitar aqui |
|---|---|---|---|
| 1 | **Multi-tenant Tier 0** (ADR 0093) | Telemetria SIM | `mcp_skill_telemetry` é tabela de governança (NÃO toca `business_id` — não tem coluna; é cross-tenant por design, escopo MCP). Qualquer Model novo sobre ela vive em `Modules/Brief` ou módulo MCP, **sem** global scope de business — documentar que é exceção SUPERADMIN/governança. NÃO criar Model com `business_id` esperado. |
| 7 | **Tasks via MCP** (ADR 0070) | SIM | Esta entrega vira task via `tasks-create`, nunca TASKS.md. |
| — | **Append-only ADRs CANON** (Tier 0) | SIM | NÃO editar 0095/0225/0229. Se a decisão de tier de alguma das 3 borderline mudar a regra, criar ADR nova `supersedes_partially`. Gravar `tier:` no frontmatter NÃO é mudar ADR (é executar a decisão dela). |
| — | **0229 anti-drift de atenção** | SIM (núcleo do risco) | NÃO promover skill a Tier A só porque a description diz "BLOQUEADOR". 0229 prova: 25/66 gritam, só 5 valem. Default conservador = B. Promoção a A exige ADR específica (0095 §"Como propor"). |
| 8 | **Identifiers MySQL ≤64 chars** | SE criar índice/coluna nova na telemetria | Se a Fase-loop adicionar índice (ex p/ agregação trimestral), passar nome explícito ≤64. |
| 3 | **Hostinger ≠ CT 100** (ADR 0062) | SIM (no relatório) | Geração do relatório trimestral roda como **command artisan / SP no MySQL** — agendar em `Kernel.php` (schedule daily 06:00 já existe p/ health-check). NÃO depender de daemon/octane no Hostinger. Se for job, **passar contexto explícito** (sem global scope a herdar). |
| — | **PII redactor** | SIM (leve) | `context_payload` (longtext JSON) pode conter trechos de sessão. Relatório agregado NÃO deve vazar payload bruto — só agregados (counts/%). |
| 4 | **MWART F1-F5** (ADR 0104) | SE renderizar relatório em `MetaSkills.tsx` | Se expor na UI ads, passa pelo processo MWART + charter. Recomendação: **começar headless** (command + JSON/markdown), UI é fase 2 opcional. |

NÃO há pegadinha catalogada específica de "editar frontmatter de skill" — observação separada: o frontmatter `tier:` é **convenção interna** (ADR 0095 §"convenção interna, NÃO Anthropic-padrão"); Claude Code core ignora o campo, então gravá-lo é puramente documental/auditável (não muda comportamento runtime — o runtime é hook + description). Logo gravar o campo é **baixo risco** e auto-shippável; o que precisa review Wagner são as 3 borderline + qualquer mudança de roster Tier A.

---

## Fase 3 — PONTO DE PLUGUE

### Parte A — gravar `tier:` nos 18 frontmatters (mecânico)

| Peça | Arquivo | Ação |
|---|---|---|
| Frontmatter de cada skill | `.claude/skills/<slug>/SKILL.md` (18 arquivos, lista Fase 1) | inserir `tier: A|B|C` no bloco frontmatter (após `name`/`description`). Para os que têm `type: tier-b-auto-trigger` (officeimpresso-*), adicionar `tier: B` canônico mantendo o `type`. |
| Verificação | (script ad-hoc) | re-rodar o grep `^tier:` p/ confirmar 0 sem tier. |

### Parte B — fechar o loop telemetria→promoção (feature pequena)

| Peça | Arquivo + âncora | Ação |
|---|---|---|
| Tabela telemetria (fonte) | `mcp_skill_telemetry` (`mysql-schema.sql:5703`) | **reusar** — já tem skill_name/triggered_at/success/tokens_saved. Sem migration nova p/ o relatório base. |
| Command de relatório trimestral | ⚠️ **criar** `Modules/Brief/Console/Commands/SkillTierReviewCommand.php` (ou `Modules/TeamMcp` se houver) | sig `skills:tier-review {--since=90} {--apply-suggestions}`. Lê telemetria 90d, cruza com `tier:` do frontmatter, aplica as 4 regras da ADR 0095, emite sugestões promo/rebaixa/arquivar. |
| Leitor de frontmatter | ⚠️ **criar** helper (parsear `tier:` dos 70 SKILL.md) — pode viver no Command | mapear slug→tier atual p/ comparar com o que a telemetria sugere. |
| Schedule | `app/Console/Kernel.php` (já tem daily 06:00 health-check) | agendar `skills:tier-review` **quarterly** (1º dia do trimestre) — emite relatório, NÃO aplica sozinho (Wagner aprova). |
| Output do relatório | `memory/governance/skill-tier-review-YYYY-QN.md` (append-only) ou JSON em `governance/` | grava sugestões + métricas; promoção A exige ADR (não auto-aplica). |
| Render UI (opcional, fase 2) | `resources/js/Pages/ads/Admin/MetaSkills.tsx` | exibir tabela tier atual vs sugerido. ⚠️ passa por MWART + charter se for. |
| Brief aggregator (já existe) | SP em `fix_brief_procedure_real_schema.php:106` (`v_skills_7d`/`v_skills_poda`) | **opcional**: estender `v_skills_poda` p/ ser tier-aware (hoje é só "não usada 30d"). Mexer em SP = migration nova idempotente. |

⚠️ Plugues que **não existem** e precisam criar como sub-tarefa: o Command `skills:tier-review`, o helper de parse de frontmatter, e a entrada no schedule. O resto é reuso.

---

## Fase 4 — CHECKLIST PRÉ-CÓDIGO

```markdown
## Pré-código checklist — T7 fechar tier + loop telemetria→promoção

### Antes de Edit/Write
- [ ] Ler ADR 0095 + 0225 + 0229 (feito neste doc) — tiers já decididos, NÃO reabrir
- [ ] Confirmar feature flag necessária? NÃO (governança interna, sem rota cliente)
- [ ] Schema migration necessária? NÃO p/ relatório base (reusa mcp_skill_telemetry).
      SÓ se estender SP v_skills_poda tier-aware → migration idempotente + down()
- [ ] ADR nova necessária? NÃO p/ gravar tier (executa 0225/0229).
      SÓ se mudar roster Tier A ou rebaixar personas-resolve formalmente → ADR supersedes_partially

### Pegadinhas a respeitar (filtradas)
- [ ] 0229 anti-drift: NÃO promover a Tier A por "BLOQUEADOR" na desc — só os 3 do banner (incident-done, memory-first-secret, hostinger-dns)
- [ ] Append-only: não tocar 0095/0225/0229
- [ ] Multi-tenant: mcp_skill_telemetry é governança cross-tenant — NÃO esperar business_id; documentar exceção
- [ ] Hostinger ≠ CT100: relatório via command/schedule (Kernel.php daily existe), sem daemon/octane
- [ ] PII: relatório agregado, nunca context_payload bruto
- [ ] MySQL ≤64 char se criar índice
- [ ] Tasks via MCP (0070), commit ≤300 linhas 1-intent

### Pontos de plugue (em ordem)
- [ ] 18× frontmatter: .claude/skills/<slug>/SKILL.md — inserir `tier: A|B|C`
- [ ] Confirmar c/ Wagner as 3 borderline: personas-resolve (A→B?), curador (B/C), sdd-avaliar (B/C)
- [ ] Backend: criar Modules/Brief/Console/Commands/SkillTierReviewCommand.php — regras 0095
- [ ] Schedule: app/Console/Kernel.php — quarterly skills:tier-review (emite, não aplica)
- [ ] Output: memory/governance/skill-tier-review-YYYY-QN.md (append-only)
- [ ] (opcional) UI: resources/js/Pages/ads/Admin/MetaSkills.tsx via MWART
- [ ] Test: tests/Feature/Brief/SkillTierReviewCommandTest.php (Pest, biz=1)

### Smoke pós-deploy
- [ ] biz=1 (test/CI): seed telemetria fixture → command emite sugestão correta (B→A ≥80%/30d etc.)
- [ ] grep `^tier:` em todos 70 SKILL.md = 0 sem tier
- [ ] biz=4 (ROTA LIVRE): N/A (governança, não toca tenant) — smoke = rodar command em prod read-only

### Estimativa total (IA-pair, ADR 0106)
- Parte A (18 frontmatters): ~20 min
- Parte B (command + schedule + Pest): ~1.5-2 h
- UI opcional: +1 h (fase 2)
- Total núcleo: ~2-2.5 h
```

---

## Test plan (Pest — roda em "PHP / Pest (Jana)")

- `tests/Feature/Brief/SkillTierReviewCommandTest.php` (biz=1, ADR 0101):
  1. seed `mcp_skill_telemetry` com skill B usada 85% sessões/30d → command sugere **B→A** (+ exige ADR).
  2. seed skill B usada <10%/60d → sugere **B→C**.
  3. seed skill C sem uso >90d → sugere **arquivar**.
  4. command NÃO aplica mudança sem `--apply-suggestions` (idempotente, read-only default).
  5. relatório NÃO contém `context_payload` bruto (PII guard).
- Parser de frontmatter: teste unit que lê os 70 SKILL.md e garante 0 sem `tier:` (catraca anti-regressão futura).
<!-- schema-allowlist: salvo de feat/governance-ds-rollout-ledger (branch shallow-orfanada 2026-06-20); output de subagente/legacy, schema estrito de secao nao se aplica -->
