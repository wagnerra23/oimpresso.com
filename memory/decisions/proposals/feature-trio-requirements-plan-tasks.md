---
title: "Feature-trio — requirements/plan/tasks com blocked_by: o degrau 'spec por feature' entre a US do SPEC e a task MCP (operacionaliza delta-spec+EARS da ADR 0306)"
status: proposed
date: "2026-07-09"
decisores: [Wagner (aprova), Claude Code (autor)]
related_adrs:
  - 0306-strangler-spec-anchored-reconstrucao-sdd
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0302-fonte-unica-doneness-anchor-aposenta-status-spec
  - 0070-jira-style-task-management-current-md-removed
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0264-governanca-executavel-trio-dominio-e2e
origem: "Grade v3, fraqueza 'spec por feature' (6/10; régua GitHub Spec Kit specify→plan→tasks / Kiro EARS+deps). O programa SDD cobre US↔código (âncora 0273/0302) e workflow (MCP 0070), mas a FEATURE nasce como US de bullets — sem requirements/acceptance estruturados nem plano de tasks com dependências encadeadas que uma sessão execute em ordem."
prs: []
---

# Feature-trio: `requirements.md` + `plan.md` + `tasks.md` por feature

> **Estende** o programa SDD ([ADR 0306](../0306-strangler-spec-anchored-reconstrucao-sdd.md) já
> decidiu "importar delta-spec (OpenSpec) e EARS (Kiro), baratos e sem lock-in" — isto é a
> operacionalização). NÃO supersede nenhum ADR; NÃO compete com o SPEC.md.

## Decisão proposta

1. **Template canônico** [`memory/requisitos/_TEMPLATE_FEATURE/`](../../requisitos/_TEMPLATE_FEATURE/BRIEFING.md)
   — pasta `memory/requisitos/<Mod>/features/<slug>/` com o trio:
   - `requirements.md` — user story + acceptance criteria **EARS** (`AC-N`, cada um com forma de prova) + fora-de-escopo; frontmatter `us:` aponta pra US **existente** no SPEC (a pasta **detalha, aponta, nunca duplica** a decisão).
   - `plan.md` — decisões técnicas, plug-points (comparar-e-não-duplicar) e checklist de riscos Tier-0 (business_id · REGRA MESTRE valor/estoque · PII · casos-gate · runtime).
   - `tasks.md` — tarefas atômicas `T-NN` com `blocked_by:` explícito (grafo acíclico), `covers:` (quais ACs prova) e **DoD por task**. Formato parseável.
2. **Fronteiras duras** (anti-dual-source):
   - done-ness continua sendo **só** a âncora `**Implementado em:**` da US ([ADR 0273](../0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md)/[0302](../0302-fonte-unica-doneness-anchor-aposenta-status-spec.md)); a última task de toda feature fecha o loop atualizando-a. `tasks.md` NÃO carrega `status:`.
   - workflow continua **só** no MCP ([ADR 0070](../0070-jira-style-task-management-current-md-removed.md)): `tasks-create ... parent_plan:"<slug>"`; o `tasks.md` é o plano versionado (ordem/deps/DoD).
   - tela continua governada pelo casos-gate ([ADR 0264](../0264-governanca-executavel-trio-dominio-e2e.md)); o lint só lembra (advisory) quando o trio menciona `Pages/**.tsx` sem `.casos.md`.
3. **Mini-lint advisory** [`scripts/governance/feature-lint.mjs`](../../../scripts/governance/feature-lint.mjs)
   (node puro, idioma doneness-lint; self-test 21 checks): ERRO em `--check` = trio incompleto · US fora do SPEC · `blocked_by` quebrado/cíclico · task sem DoD/meta · `covers`→AC inexistente; AVISO (nunca morde) = AC sem task (buraco) · task sem covers · tela sem casos. Nasce **ADVISORY e FORA do CI** ([ADR 0271](../0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) + política 0314 required=só-Tier-0); wiring em CI/scorecard é promoção futura por calendário, não default.
4. **Schema-gate: começa FORA (decisão consciente).** Os globs de `scripts/memory-schemas/` não cobrem `features/**`; o tipo novo é validado só pelo feature-lint. Entrar no schema-gate = decisão futura com backfill.
5. **Quando usar:** feature multi-sessão (≥3 tasks, ≥1 dependência real). Fix de 1 arquivo NÃO ganha trio (cerimônia). Criar o trio é opt-in por feature — nenhum gate força.

## Piloto (a prova, não o template)

[`RecurringBilling/features/gateway-ativacao/`](../../requisitos/RecurringBilling/features/gateway-ativacao/requirements.md)
— US-RB-052 (109 assinaturas com cobrança dormente, maior ROI do batch-34): 6 ACs EARS,
plano ancorado no código real (`conta_bancaria_id` override + `InvoiceGeneratorService` L162 —
zero coluna nova), 6 tasks encadeadas T-01→T-06 incluindo **gate humano T-05** (REGRA MESTRE
valor: Wagner aprova o dry-run antes→depois antes de qualquer `--apply`) e T-06 fechando o
loop na âncora da US. Lint: 🟢 0 erros · 0 avisos.

## Alternativas consideradas

1. **Engordar a US no SPEC** (acceptance+tasks inline) — rejeitado: SPEC vira log de execução; o batch-34 já mostrou US de 20+ linhas ilegível; e o SPEC é schema-gated (cada formato novo quebraria o gate).
2. **Só tasks MCP com blocked_by** — rejeitado: o MCP guarda workflow, não o raciocínio (plan/plug-points/riscos); sessão nova não reconstrói o COMO a partir de subject+prioridade.
3. **Adotar Spec Kit/Kiro como ferramenta** — rejeitado na própria [ADR 0306](../0306-strangler-spec-anchored-reconstrucao-sdd.md): "downgrade com lock-in"; importa-se o formato (EARS/delta-spec), não a ferramenta.
