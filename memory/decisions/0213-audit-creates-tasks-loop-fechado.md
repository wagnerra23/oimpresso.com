---
slug: 0213-audit-creates-tasks-loop-fechado
number: 213
title: "Audit docs com gaps criam MCP tasks automaticamente — loop fechado audit → backlog → ação"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: '2026-05-28'
module: Infra
quarter: 2026-Q2
tags: [audit, backlog-automation, hook, skill, mcp, prevencao-orphans]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0105-cliente-como-sinal-guiar-sem-mandar
pii: false
review_triggers:
  - "Audit doc com >5 ❌ sem MCP tasks linkadas em 30d"
  - "MCP server schema mudar (tasks-create API change)"
---

# ADR 0213 — Audit docs com gaps criam MCP tasks (loop fechado)

## Contexto

R10 da sessão Larissa 2026-05-28: o audit `memory/sessions/2026-05-27-audit-sells-create-vs-blade-larissa.md` (criado dia anterior) catalogou 20 dimensões cruzando Sells/Create.tsx vs Blade legacy, com:

- ✅ 2 APROVADO
- 🟡 7 PARCIAL
- ❌ 11 AUSENTE

R5/R6 atacou **5 dos 11 AUSENTE** (PR #1778, #1784, etc 2026-05-27). As outras **6 ficaram só no doc** — fora do backlog MCP. Resultado: 24h depois, Wagner perguntou "quais erros ainda podem acontecer" e meu primeiro diagnóstico **listou 2 gaps que JÁ estavam fechados em commits do mesmo dia 27/05** (PR2 saldo devedor, PR5 quick-add Drawer) porque consultei o audit doc snapshot estático, não o backlog vivo.

Causa raiz: **audit doc é estático, MCP backlog é vivo**. Sem ponte, gaps catalogados em doc viram órfãos invisíveis pra próxima sessão.

Skill [`comparativo-do-modulo`](../../.claude/skills/comparativo-do-modulo/SKILL.md) ([ADR 0089](0089-comparativo-do-modulo.md)) já faz **exatamente isso** pra **audits Capterra de módulo** — cruza CAPTERRA-FICHA.md + SPEC.md + código → propõe `tasks-create` batch → Wagner aprova → MCP tasks. **Mas não cobre audits internos** (Sells/Create vs Blade, audit de processo, audit de bug).

Estado-da-arte 2026 ([dossier session](../sessions/2026-05-28-arte-prevencao-bugs-mwart-larissa.md) Frente 4): estado-da-arte é menos maduro publicamente — Shopify/Linear/Notion fazem custom solutions internas. Patterns emergentes: LLM-judge claim extractor, dotted task notation parseável (`<!-- TASK[owner]: ... -->`), Linear/Notion webhooks bi-direcionais.

oimpresso já tem infra parcial:
- MCP server `tasks-create` tool funcionando
- Skill `comparativo-do-modulo` é template para generalização
- Hooks PowerShell ativos (29) — pattern `PostToolUse` viável

## Decisão

**Adotar loop fechado "audit doc → MCP task" em 4 mecanismos complementares:**

**Mecanismo 1 — Convenção markdown nova `TASK[<owner>]: <desc>`** (S 1h):

Items de audit usam convenção parseável em vez de `❌` solto:

```markdown
- [ ] TASK[claude](P1): Saldo devedor visível ao trocar cliente (Dor 5)
  - Onde: resources/js/Pages/Sells/_components/CustomerSearchAutocomplete.tsx:13-18
  - Esforço: S (~50 LOC)
  - Impact: Larissa não vende fiado às cegas
```

Pattern: `TASK[<owner>](<priority P0-P3>): <descrição>` + bullets opcionais com Onde/Esforço/Impact.

**Mecanismo 2 — Hook `audit-creates-tasks.ps1` (PostToolUse Write)** (M 4h):

`.claude/hooks/audit-creates-tasks.ps1` ativa quando Claude faz `Write` em path matching `memory/sessions/*-audit-*.md` ou `memory/requisitos/*/AUDIT-*.md`. Comportamento:

1. Parse linhas `- [ ] TASK[<owner>](P\d): <desc>`
2. Lista itens detectados em prompt pro Claude
3. Sugere: "Detectei N tasks em audit. Criar via tools-create MCP em batch? Sim/Não/Editar"
4. Wagner confirma 1× (não 1-a-1) — Claude executa tasks-create batch
5. Hook escreve no audit doc: `<!-- TASK_CREATED: COPI-NNNN -->` ao lado de cada item criado
6. Logger: `storage/logs/audit-orphan-tracker.log` registra parses + batches

**Mecanismo 3 — Skill `audit-to-backlog` Tier B** (M 5h):

`.claude/skills/audit-to-backlog/SKILL.md` — generaliza `comparativo-do-modulo` pra qualquer audit interno. Description: "ATIVAR quando user pedir 'transformar audit em tasks', 'levar audit X pro backlog', `/audit-to-backlog <doc>`, OU em PostToolUse Write em `memory/sessions/*-audit-*.md`".

Ações da skill:
1. Lê audit doc
2. Identifica itens `TASK[...]` OU itens `❌`/`🟡` se convenção não usada
3. Cruza com MCP backlog (`tasks-list`) pra detectar duplicatas
4. Propõe batch `tasks-create` com `parent_audit:<slug>` metadata
5. Wagner aprova batch ou edita 1-a-1
6. Escreve cross-link no audit + cria tasks

**Mecanismo 4 — Workflow CI `audit-orphan-check.yml`** (M 5h):

`.github/workflows/audit-orphan-check.yml`:
- Dispara em PR tocando `memory/sessions/*-audit-*.md` ou `memory/requisitos/**/AUDIT-*.md`
- Parse `❌` + `🟡` sem `<!-- TASK_CREATED: ... -->` correspondente
- Comenta no PR listando órfãos:
  > ⚠️ Audit doc contém 3 itens `❌`/`🟡` sem TASK_CREATED linkado:
  > - Linha 47: "configure-search modal" 
  > - Linha 62: "weighing scale modal"
  > - Linha 78: "lote/expiry coluna"
  > Use skill `audit-to-backlog` ou ignore com `<!-- TASK_IGNORED: razão -->`
- **Não bloqueia merge** (warning only) — opt-in pro autor

**Mecanismo 5 — Health-check `audits_with_orphan_findings`** (S 2h):

`jana:health-check` ganha check novo: conta `❌`/`🟡` em `memory/sessions/*-audit-*.md` (últimos 30d) sem TASK_CREATED. Métrica diária 06:00 BRT. Primeiro hit dispara alerta laravel.log.

## Justificativa

**Por que múltiplos mecanismos vs 1 forte:** loop fechado precisa cobertura em depth:
- Mecanismo 1 (convenção) = humano lê e escreve melhor
- Mecanismo 2 (hook) = enforcement passivo no momento do Write
- Mecanismo 3 (skill) = invocação on-demand
- Mecanismo 4 (workflow) = safety-net CI
- Mecanismo 5 (health-check) = auditoria periódica do existente

Falha 1 mecanismo, outros pegam. Pattern análogo ao "fail loud" do [ADR 0212](0212-defensive-logging-fallback-paths.md).

**Por que generalizar `comparativo-do-modulo` em vez de extender:** Capterra-style audits têm shape diferente (3 buckets: APROVADO/PARCIAL/AUSENTE) vs audits internos diversos. Skill nova permite formato livre. Não-quebra do existente.

**Por que warning-only no CI (não bloqueia merge):** [ADR 0105 cliente-como-sinal](0105-cliente-como-sinal-guiar-sem-mandar.md) — Wagner pode decidir que algum gap fica dormente até sinal qualificado. Forçar TASK_CREATED em todo `❌` violaria isso. `TASK_IGNORED: <razão>` permite documentar decisão.

**Por que metadata `parent_audit:<slug>` em task:** Drill-down "qual audit motivou esta task?" + reverse lookup "quantas tasks vieram deste audit?". Reusável pra retros e métricas.

## Consequências

**Positivas:**

- **R10 raiz eliminado:** audit `❌` que não vira task = aparece em PR comment + health-check alarm
- **MCP backlog passa a ser source-of-truth** das pendências; audit docs servem como contexto, não como backlog
- **Próximo diagnóstico Wagner-style "quais erros podem acontecer?"** consulta MCP backlog vivo, não snapshot estático = zero "diagnose outdated"
- **Time futuro** (Felipe/Maiara/Eliana/Luiz) onboarding acelerado: audit docs sempre em sincronia com MCP
- Pattern análogo ao `comparativo-do-modulo` Capterra mas mais amplo (audit interno, bug investigation, postmortem)

**Negativas / Trade-offs:**

- **5 mecanismos = 5 manutenções:** atualizar 1 sem outros = drift. Mitigação: hook + workflow são automatizados; skill + convenção dependem de IA-pair seguir
- **False-positives `TASK_IGNORED`:** Wagner pode ignorar gap legítimo. Mitigação: health-check periódico relembra
- **Custo dev inicial:** ~M+M+M+M+S ≈ 16h IA-pair total. Não-trivial mas amortiza ao longo dos próximos 6+ meses
- **Convention churn:** items antigos `❌` solto continuam órfãos. Pattern: backfill em batches pós-merge

**Riscos mitigados:**

- R10-class (audit gap perdido)
- Diagnose outdated (sessão atual consulta doc, não backlog vivo)
- Time onboarding lento (audits dispersos vs backlog centralizado)
- M-AP-7 novo "Bug catalogado vira só doc, não vira gate" (catalogado paralelo no LICOES)

**Riscos não-mitigados:**

- Bug não-catalogado (descobre primeira vez em prod) — fora do escopo deste ADR
- Audit doc muito ruim (sem `❌`, prosa solta) — exige skill `audit-to-backlog` ler com LLM. Aceito como gap

## Referências

- ADR 0070 — Jira-style task management
- ADR 0089 — Skill comparativo-do-modulo (template generalização)
- ADR 0094 — Constituição v2 §princípio 1 (Context as a product)
- ADR 0105 — Cliente como sinal qualificado (justifica `TASK_IGNORED`)
- [`memory/sessions/2026-05-27-audit-sells-create-vs-blade-larissa.md`](../sessions/2026-05-27-audit-sells-create-vs-blade-larissa.md) — audit que originou R10
- [`memory/sessions/2026-05-28-arte-prevencao-bugs-mwart-larissa.md`](../sessions/2026-05-28-arte-prevencao-bugs-mwart-larissa.md) — Frente 4
- [Shopify Engineering — Fine-tuning agent flow 2026 (LLM judge)](https://shopify.engineering/fine-tuning-agent-shopify-flow)
