---
slug: 0240-task-ledger-git-native-cowork-code
number: 240
title: "Task ledger git-native + handoff-por-tarefa + fechamento por evidência + antifragilidade — loop Cowork↔Code"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
proposed_at: "2026-05-30"
decided_at: "2026-05-30"
module: governance
quarter: 2026-Q2
tier: CANON
tags: [task-ledger, handoff, verificacao-adversarial, evidence-based, cowork-loop, git-native, antifragilidade]
related:
  - 0070-jira-style-task-management-current-md-removed
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0130-handoff-append-only-mcp-first
  - 0209-eslint-9-flat-config
  - 0084-triggers-mysql-imutabilidade-mcp-audit-log
  - 0236-governanca-evolucao-doc-design
  - 0239-governanca-design-system-git-ssot-regressao-ia
related_adrs: [0070, 0114, 0130, 0209, 0084, 0236, 0239]
amends_adrs: [0070, 0114]
supersedes: []
authors: [wagner, claude-code]
dossier: memory/sessions/2026-05-30-arte-task-system-cowork-code.md
---

# ADR 0240 — Task ledger git-native + fechamento por evidência + antifragilidade (loop Cowork↔Code)

> **Status: aceito** (Wagner, 2026-05-30). Implementação liberada (plano no fim).
> Dossier SOTA: [2026-05-30-arte-task-system-cowork-code.md](../sessions/2026-05-30-arte-task-system-cowork-code.md).

## Contexto

O loop Cowork↔Code (ADR 0114, formalizado no `PROTOCOL.md` §10) precisa de um **sistema de tarefas bidirecional** — `index_task_list` + cada *task* com handoff próprio, onde o `sync now` faz o Cowork **conferir a evidência do Code e fechar / criticar / derivar** a tarefa.

**Fato topológico que decide a arquitetura:** o **Cowork só lê git** (diff + output de scripts commitados + print). O MCP tasks (ADR 0070) vive no MySQL do CT100 — **cego pro Cowork**. Logo a fonte de verdade do *loop* tem que ser git; não é escolha estética.

**Princípio-chave (Wagner, NÃO violar):** nem Code nem Cowork fecham tarefa por OPINIÃO — **ambos podem estar STALE** (provado 2× nesta sessão: o prompt da faxina do Cowork mandava re-numerar o ADR 0238 já existente; e o próprio agente de pesquisa afirmou que `ds:report`/§10 não existem quando existem). Tarefa fecha por **EVIDÊNCIA OBJETIVA**. Respaldo: *"Gaming the Judge"* (arXiv 2601.14691, jan/2026) — juiz que confia na narrativa do executor é **gameável**; defesa = **exigir artefato observável**.

## Decisão

1. **Fonte de verdade dupla.** MCP tasks (0070) = verdade do **projeto** (intocado). **Ledger git-native** (`prototipo-ui/tasks/`) = projeção operacional do **loop**. Ponte: `mcp_ref: <KEY>-NNNN` + commit `Refs: <KEY>-NNNN` → webhook GitHub→MCP existente reconcilia ~2min. **Amenda 0070, não supersede.**

2. **Anatomia da task** — `prototipo-ui/tasks/T-NNN-<slug>/`:
   | Arquivo | Papel | Mutabilidade | Escreve |
   |---|---|---|---|
   | `TASK.md` | identidade + `mcp_ref` + módulo + **DoD = evidência exata que fecha** + `depends_on` | quase-imutável | [CC] |
   | `events.ndjson` | event-log append-only (`{ts, actor, type, ref, payload}`) — herda 0084/0130 | append-only | ambos |
   | `STATE.md` | estado **derivado** (progress ledger legível) | sobrescrito | projeção (script) |
   | `evidence/` | `ds-report.json`, run-id Pest, screenshot/golden — **o que o juiz olha** | append-only | [CL] |
   - `prototipo-ui/tasks/INDEX.md` = `index_task_list` (derivado por `npm run tasks:index`, nunca à mão).

3. **Evidência objetiva (catálogo fechado).** Só artefato no git/print, reproduzível, fecha. Narrativa NUNCA fecha:
   | Tipo | FECHA com | NÃO fecha |
   |---|---|---|
   | Migração DS | `ds:report --module=X --json` ⇒ `ds/*`=0 (`pass:true`) | "migrei" sem report |
   | Backend | Pest verde do arquivo-alvo (run-id) | "testei manual" |
   | Visual | screenshot == golden (F2 no SYNC_LOG) | tabela markdown |
   | Tier 0 | Pest GUARD biz→99 bloqueado + `doBusiness` no diff | "scope global cobre" |

4. **Ciclo no `sync now` — 5 perguntas (Magentic-One-style) contra a evidência:**
   Code reporta (evento `evidence` + artefato) → push → Wagner digita `sync now` → Cowork `git pull` → confere DoD: (Q1) artefato existe? (Q2) bate o alvo? (Q3) regressão colateral? (Q4) medido contra `origin/main` atual [§10.4]? (Q5) se não, próxima instrução objetiva? → escreve evento `close`/`critique`/`spawn` → devolve `PROMPT_PARA_CODE` → Wagner cola → **[CL] passa pelo gate §10.4** → age. Fecha por `evidence/`, nunca por quem falou.

5. **Handoff per-task coexiste com per-session.** ADR 0130 (per-session, narrativa) **intocada** (Tier 0). `events.ndjson` = handoff per-task. Narrativa de sessão **referencia** tasks (`Refs: T-NNN`), não duplica.

6. **§10.5 nova no `PROTOCOL.md`** formaliza o ciclo (4) + catálogo (3) — estende o §10.4.

## Antifragilidade — sobreviver E evoluir sem depender do humano

> **Por que esta seção existe (Wagner, 2026-05-30: "esse protocolo vai sobreviver no tempo e evoluir?").** A evidência desta sessão é um cemitério: `HANDOFF` 15d stale, `SYNC_LOG` parado, `CODE_NOTES` morto, Cowork stale, o próprio agente de pesquisa stale, R12 precisou virar hook, auto-mem morta (0061), `CURRENT`/`TASKS` removidos (0070), 6 colisões ADR silenciosas.
> **Lei observada: o que é DERIVADO de fato + ENFORÇADO por máquina sobrevive; o que é ESCRITO à mão + depende de LEMBRAR apodrece.** Sem esta seção, este ADR morre em ~2 semanas igual os predecessores.

3 mecanismos — todos "máquina, não humano":

1. **Derivar, nunca escrever (regra dura).** Tudo que pode sair de fato é gerado por script: `INDEX.md`, `STATE.md`, placar, métrica de saúde. A superfície "agente escreve à mão" é **mínima e fechada** — só `TASK.md` (DoD, no nascimento da task), `events.ndjson` (1 linha/evento) e o corpo de uma crítica. **Se um artefato pode ser derivado e mesmo assim é escrito à mão, é bug de design.**

2. **Freshness gate (apodrecimento VISÍVEL).** Check em CI/cron (estende ADR 0236 `DesignDocsFreshnessChecker`) marca §stale e alerta quando: `INDEX/STATE` mais velho que o último `events.ndjson` (projeção não rodou); task `🔄` sem evento há > N dias; placar medido contra SHA ≠ `origin/main` atual. Torna o stale **gritante** em vez de silencioso — o que matou os predecessores foi apodrecer **em silêncio**.

3. **Métrica de saúde do loop (cutuca no lugar do Wagner).** Número derivado no `jana:health-check`: `% de tasks fechadas COM evidence/` vs total; `idade do INDEX vs último evento`; `nº de tasks 🔄 paradas > N dias`. Ao degradar além do limiar, **dispara revisão automática** (alerta + task de manutenção). **É isso que tira o Wagner do circuito de vigilância** — a evolução deixa de depender dele cutucar.

**O que NÃO dá pra blindar (honesto):** o **julgamento** do Cowork ao criticar é humano-like e pode degradar. A engenharia minimiza essa superfície (todo o resto é máquina), mas não a zera; o freshness gate (#2) detecta quando o julgamento parou (task `🔄` parada) — melhor proxy disponível.

## Consequências
- (+) Working memory legível por ambos os agentes, com árbitro objetivo + **auto-vigilância** (não morre em silêncio).
- (+) Anti-stale by design (§10.4 vira pré-passo) — supera A2A.
- (+) Event-sourced → audit/replay grátis (alinha Temporal/LangGraph + 0084).
- (−) `prototipo-ui/tasks/` cresce → `review_trigger` >100 dirs (espelha 0130).
- (−) Exige `ds:report --module --json` emitir `pass`/`measured_against_sha` (Gap #1 — base já existe).

## O que JÁ existe (corrige o stale do dossier)
- `scripts/ds-report.mjs` (`--module/--json/--worklist/--write`) + `npm run ds:report`/`ds:report:write` — #2013/#2017.
- `PROTOCOL.md` §10.1–§10.4 (gate anti-stale) — #2013/#2020.
- `DS_ADOCAO_INDICE.md` placar ✅/☐ por módulo — #2017.
→ **Delta real:** (a) `pass`/`target`/`sha` no `--module --json`; (b) anatomia `tasks/` + projeções; (c) §10.5; (d) freshness gate + métrica de saúde.

## Plano (IA-pair, ADR 0106) — implementação liberada
**Ordem cravada: Gap #1 primeiro** (âncora de evidência; sem ela o resto é aspiracional).
- **F1** `ds:report --module=X --json` + `pass`/`measured_against_sha` (~20min · base já existe).
- **F2** anatomia `tasks/T-NNN/` + `tasks:index`/`tasks:project` (derivam INDEX/STATE) (~3h).
- **F3** §10.5 no PROTOCOL (5 perguntas + catálogo) (~1h).
- **F4** `tasks:report` no Code (escreve evento + artefato + linha SYNC_LOG) (~1h).
- **F5 (antifragilidade)** freshness gate (estende 0236) + métrica de saúde no `jana:health-check` (~2h).
- **F6** hook bloqueador `events.ndjson`/`SYNC_LOG` não-append — **dormente** (P2, ativa na 1ª reincidência, critério 0130).

## Refs
- Dossier SOTA: `memory/sessions/2026-05-30-arte-task-system-cowork-code.md`
- ADR 0070 · 0114 · 0130 · 0209 · 0084 · 0236 (freshness) · 0239 · `prototipo-ui/PROTOCOL.md` §10
- SOTA: Magentic-One (arXiv 2411.04468) · Reflexion (2303.11366) · Gaming the Judge (2601.14691) · OpenAI Swarm/A2A · Temporal/LangGraph
