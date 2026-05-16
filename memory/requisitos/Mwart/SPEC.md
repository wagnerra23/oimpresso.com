---
module: Mwart
na_justified:
  D5: "Mwart é meta-processo de governança (enforcement do caminho canônico Blade→Inertia — ADR 0104) — NÃO é módulo de features cliente-facing. Não há biz=4 ROTA LIVRE consumindo features Mwart; consumidores são `Modules/*` que migram para Inertia. D5 cliente real não aplica por design."
  D4.b: "Mwart não tem state machine FSM (ADR 0143). É processo administrativo de gating (skill Tier A + hook PreToolUse + CI workflow), não fluxo de negócio com transições Eloquent. D4.b FSM canônica N/A."
na_justified_v3:
  D6.a: "Mwart é meta-processo (enforcement skill + hook + CI) — sem Controllers Inertia próprios. Inertia::defer N/A por ausência de telas geradas pelo módulo."
related_adrs: [0104, 0106, 0153, 0154, 0155, 0156]
---

# Especificação funcional — MWART (processo canônico)

> **N/A justificado** D5 + D4.b + D6.a — meta-processo de enforcement (skill + hook + CI gate), sem features cliente nem FSM nem Controllers Inertia próprios. Detalhes em [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md).

> **Convenção do ID:** `US-MWART-NNN` para user stories de meta-processo.
> **Origem:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — Wagner 2026-05-08 pediu único caminho de migração com enforcement, falhas inaceitáveis.
> **Skill mãe:** [mwart-process](../../../.claude/skills/mwart-process/SKILL.md) (Tier A always-on).
> **Estimates recalibradas:** [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) — fator 10x em codáveis + margem 2x. Total 28h → 5.5h reais.

## 1. Glossário

- **MWART** — Module Web App React Transition (Blade → Inertia/React)
- **Camadas de enforcement:** (1) skill Tier A, (2) hook PreToolUse, (3) CI workflow gate
- **Score audit:** 0-100 produzido pelo `cockpit-runbook` modo B (CHECKLIST §G)
- **Override autorizado:** comentário `/mwart-override <razão>` em PR — registra exceção em ADR per-tela

## 2. User stories — meta-processo de enforcement

### US-MWART-001 · Camada 2+3 enforcement — Hook + CI workflow

> owner: wagner · priority: p0 · estimate: 1.5h · status: todo · type: story · origin: adr-0104
> blocked_by: —

**Contexto.** ADR 0104 define 3 camadas de enforcement. A camada 1 (skill Tier A `mwart-process`) já está ativa. Faltam 2 e 3 — sem elas, o processo depende exclusivamente do agent lembrar (pode falhar em sessão longa, dev humano sem Claude Code, ou agent novo). Esta US implementa as travas em runtime e merge.

**Escopo:**
- [ ] Hook PreToolUse `.claude/hooks/block-mwart-violation.ps1` — bloqueia `Edit`/`Write` em:
  - `resources/js/Pages/<Mod>/<Tela>.tsx` se `memory/requisitos/<Mod>/RUNBOOK-<tela-kebab>.md` não existe (F1 incompleta)
  - controller chamando `Inertia::render('<Mod>/<Tela>')` se SPEC.md não tem US `<MOD>-002` com status `done` (F2 incompleta)
  - Mensagem de erro PT-BR explica qual fase pular gerou bloqueio + comando pra corrigir
- [ ] Registrar hook em `.claude/settings.json` (skill `update-config` cobre)
- [ ] CI workflow `.github/workflows/mwart-gate.yml` — trigger em PR que toca `resources/js/Pages/**/*.tsx`:
  - Verifica RUNBOOK existe em `memory/requisitos/<Mod>/RUNBOOK-<tela-kebab>.md`
  - Verifica SPEC.md tem ≥1 US do tipo MWART migration referenciando esta tela
  - Roda `php artisan cockpit-runbook:audit <path>` e exige score ≥ 70 (CRITICAL=0)
  - Roda Pest baseline da F2 (filtro por nome do controller)
  - Aceita override via comentário PR `/mwart-override <razão>` que cria ADR per-tela
- [ ] Atualizar `mwart-quality` SKILL.md com referência a [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) e a este SPEC
- [ ] Atualizar `cockpit-runbook` SKILL.md idem (modo B vira gate canônico de F3 e F4)
- [ ] Atualizar `memory/proibicoes.md` — adicionar "❌ Caminho alternativo de MWART (sem F1+F2 completas)"

**Acceptance criteria:**
- [ ] Tentativa de Edit em `Pages/Sells/Create.tsx` sem RUNBOOK existir → hook bloqueia com mensagem clara
- [ ] PR que adiciona Page Inertia sem RUNBOOK falha CI com link pro processo
- [ ] PR com `/mwart-override` registrado por Wagner passa CI + cria ADR per-tela
- [ ] Pest test `MwartGateWorkflowTest` valida o pipeline completo (mock PR)

**Refs:** [ADR 0104 §Enforcement (3 camadas)](../../decisions/0104-processo-mwart-canonico-unico-caminho.md), [skill update-config](../../../.claude/skills/update-config/SKILL.md)

### US-MWART-002 · Backfill — audit das 78 telas Inertia já existentes

> owner: wagner · priority: p1 · estimate: 3h · status: todo · type: story · origin: adr-0104
> blocked_by: US-MWART-001

**Contexto.** O ADR 0104 estabelece processo canônico, mas as ~78 telas Inertia existentes foram migradas antes do processo formalizar. Backfill garante: cada tela tem RUNBOOK retroativo + score audit registrado + SPEC com US `done` (status histórico).

**Escopo:**
- [ ] Tabela nova `mcp_pages_audits` (migration) — `page_path` (PK), `module`, `runbook_exists`, `score_total`, `score_ds`, `score_adr`, `score_ux`, `audit_date`, `audited_by`
- [ ] Comando artisan `mwart:backfill-audit` — itera por `resources/js/Pages/**/*.tsx`, roda audit modo B, grava em `mcp_pages_audits`
- [ ] Job assíncrono se demorar (78 telas × ~30s/audit = ~40min)
- [ ] Para telas com score < 70: registrar em SPEC.md do módulo como US-MOD-NNN `mwart-debt` (priority p2, type debt)
- [ ] Para telas com score ≥ 70 e sem RUNBOOK: gerar RUNBOOK retroativo via `cockpit-runbook` (modo Generate forçado)
- [ ] Dashboard `/copiloto/admin/qualidade` mostra trend dos scores (já existe estrutura — só plugar nova tabela)
- [ ] Marcar todas USs históricas como `done` no SPEC retroativo (lifecycle `historical`)

**Acceptance criteria:**
- [ ] Comando `mwart:backfill-audit` roda em prod e grava 78+ rows em `mcp_pages_audits`
- [ ] Dashboard mostra distribuição de scores (% verde/amarelo/laranja/vermelho)
- [ ] Telas <50 score viram tasks p2 explicitamente — Wagner decide ordem do refactor backlog
- [ ] Próximas migrações já entram com RUNBOOK + SPEC desde o dia 1

**Refs:** [CHECKLIST.md §G — Score 0-100](../../../.claude/skills/cockpit-runbook/CHECKLIST.md), dashboard existente em `Modules/Copiloto/Http/Controllers/Admin/QualidadeController.php`

### US-MWART-003 · Métricas de adoção do processo

> owner: wagner · priority: p2 · estimate: 1h · status: todo · type: story · origin: adr-0104
> blocked_by: US-MWART-002

**Contexto.** "Não pode falhar" exige observabilidade. Métricas chave que respondem se o processo está sendo seguido:

**Escopo:**
- [ ] Health-check novo em `php artisan jana:health-check`: `mwart_process_compliance_24h`
- [ ] SQL: `SELECT COUNT(DISTINCT pr_url) FROM mcp_pages_audits WHERE audit_date > NOW() - INTERVAL 24 HOUR AND score_total >= 70`
- [ ] Alert se houver merge sem audit nas últimas 24h (campo `score_total IS NULL` em PR mergeado)
- [ ] Brief inclui linha: "MWART compliance 24h: X/Y PRs com audit ≥70"
- [ ] Tela `/copiloto/admin/qualidade` mostra:
  - Score médio das telas Inertia (trend 30d)
  - # PRs MWART com `/mwart-override` no quarter (alerta se > 3)
  - Top 5 telas com score baixo (refactor backlog)

**Acceptance criteria:**
- [ ] Wagner abre `/copiloto/admin/qualidade` e vê trend dos últimos 30d
- [ ] Health-check falha visível em alert log se compliance < 100% por >24h
- [ ] Brief diário (06:00 BRT) carrega métrica MWART junto das outras

**Refs:** [ADR 0094 — Constituição V2 §Loop fechado por métrica](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md), [ADR 0091 — Daily Brief](../../decisions/0091-daily-brief.md)

---

**Última atualização:** 2026-05-08
