---
slug: task-ledger-git-native-cowork-code
title: "Task ledger git-native + handoff-por-tarefa + fechamento por evidência — loop Cowork↔Code"
type: adr
status: proposed
authority: canonical
amends: [0070, 0114]
related: [0130, 0209, 0084, 0091, 0119, 0239]
module: Governance
date: 2026-05-30
proposed_by: "Claude Code [CL] + agente estado-da-arte"
decided_by: "Wagner (pendente)"
dossier: memory/sessions/2026-05-30-arte-task-system-cowork-code.md
---

# ADR (proposto) — Task ledger git-native + fechamento por evidência (loop Cowork↔Code)

> **Status: proposto.** Aguarda Wagner cravar (aceitar/editar). NÃO implementar antes do aceite.
> Dossier SOTA completo: [2026-05-30-arte-task-system-cowork-code.md](../../sessions/2026-05-30-arte-task-system-cowork-code.md).

## Contexto

O loop Cowork↔Code (ADR 0114, formalizado no `PROTOCOL.md` §10) precisa de um **sistema de tarefas bidirecional** — Wagner pediu um `index_task_list` + cada *task* com handoff próprio, onde o `sync now` faz o Cowork **conferir a evidência do Code e fechar / criticar / derivar** a tarefa.

**Fato topológico que decide a arquitetura:** o **Cowork só lê git** (diff + output de scripts commitados + print). O MCP tasks (ADR 0070) vive no MySQL do CT100 — **cego pro Cowork**. Logo a fonte de verdade do *loop* tem que ser git; não é escolha estética.

**Princípio-chave (Wagner, 2026-05-30 — NÃO violar):** nem Code nem Cowork fecham tarefa por OPINIÃO — **ambos podem estar STALE** (provado 2× nesta sessão: o prompt da faxina do Cowork mandava re-numerar o ADR 0238 já existente; e o próprio agente de pesquisa afirmou que `ds:report`/§10 não existem quando existem). Tarefa fecha por **EVIDÊNCIA OBJETIVA**. Respaldo acadêmico: *"Gaming the Judge"* (arXiv 2601.14691, jan/2026) prova que juiz que confia na narrativa do executor é **gameável**; a defesa publicada é **exigir artefato observável**.

## Decisão (proposta)

1. **Fonte de verdade dupla.** MCP tasks (0070) = verdade do **projeto** (intocado: cycle, epic, owner, prazo). **Ledger git-native** (novo, `prototipo-ui/tasks/`) = projeção operacional do **loop Cowork↔Code**, com a evidência inline. Ponte: campo `mcp_ref: <KEY>-NNNN` + commit `Refs: <KEY>-NNNN` → o webhook GitHub→MCP que **já existe** (0070) reconcilia em ~2min. **Amenda 0070, não supersede** (mesmo padrão da 0130).

2. **Anatomia da task** — `prototipo-ui/tasks/T-NNN-<slug>/`:
   | Arquivo | Papel | Mutabilidade | Escreve |
   |---|---|---|---|
   | `TASK.md` | identidade + `mcp_ref` + módulo-alvo + **DoD = a evidência exata que fecha** (qual comando, qual número-alvo) + `depends_on` | quase-imutável | [CC] |
   | `events.ndjson` | **event-log append-only** (`{ts, actor, type:[handoff\|evidence\|critique\|spawn\|close\|reopen], ref, payload}`) — herda imutabilidade 0084/0130 | append-only | ambos |
   | `STATE.md` | estado **derivado** dos eventos (progress ledger legível) | sobrescrito (nunca fonte) | projeção |
   | `evidence/` | artefatos: `ds-report.json` do módulo, run-id Pest, screenshot/golden | append-only | [CL] |
   - `prototipo-ui/tasks/INDEX.md` = o **`index_task_list`** (derivado): tasks vivas + status + última evidência + pergunta aberta. Gerado por `npm run tasks:index`, nunca editado à mão.

3. **Evidência objetiva (catálogo fechado).** Só artefato no git/print, reproduzível por terceiro, fecha. **Narrativa de agente NUNCA fecha:**
   | Tipo de task | FECHA com | NÃO fecha (vira crítica/filha) |
   |---|---|---|
   | Migração DS | `ds:report --module=<X> --json` ⇒ `ds/*`=0 (`pass:true`) commitado | "migrei a tela" sem report |
   | Backend | Pest verde do arquivo-alvo (run-id no SYNC_LOG) | "testei manual" |
   | Visual | screenshot == golden (F2 no SYNC_LOG) | tabela markdown |
   | Tier 0 | Pest GUARD biz→99 bloqueado + `doBusiness` no diff | "scope global cobre" |

4. **Ciclo no `sync now` — 5 perguntas (Magentic-One-style), respondidas contra a evidência, não a narrativa:**
   Code reporta (evento `evidence` + artefato em `evidence/`) → push → Wagner digita `sync now` → Cowork `git pull` (leitura) → confere a DoD: **(Q1)** o artefato existe? **(Q2)** bate o alvo? **(Q3)** regressão colateral? **(Q4)** medido contra `origin/main` atual [§10.4]? **(Q5)** se não, qual a próxima instrução objetiva? → escreve 1 evento `close`/`critique`/`spawn` → devolve `PROMPT_PARA_CODE` → Wagner cola → **[CL] passa pelo gate §10.4** → age. **Fecha por `evidence/`, nunca por quem falou.**

5. **Handoff per-task coexiste com per-session.** ADR 0130 (per-session, narrativa "por que pivotei", append-only) **intocada** (Tier 0). O `events.ndjson` é o handoff **per-task** (evidência+crítica de 1 tarefa). A narrativa de sessão **referencia** as tasks (`Refs: T-NNN`), não duplica.

6. **§10.5 nova no `PROTOCOL.md`** formaliza o ciclo (4) + o catálogo (3) — estende o §10.4 existente.

## Consequências
- (+) Cowork e Code compartilham working memory legível por ambos, com árbitro objetivo.
- (+) Anti-stale by design (gate §10.4 vira pré-passo do ciclo) — **supera o A2A** (que assume emissor confiável).
- (+) Event-sourced → audit/replay grátis (alinha Temporal/LangGraph + ADR 0084).
- (−) `prototipo-ui/tasks/` cresce → `review_trigger` >100 dirs (espelha 0130).
- (−) Exige `ds:report --module --json` emitir `pass`/`measured_against_sha` (Gap #1 — base JÁ existe).

## O que JÁ existe (corrige o stale do dossier)
> O agente de pesquisa rodou sobre worktree stale e errou 3 fatos. Confirmado contra `origin/main`:
- `scripts/ds-report.mjs` com `--module/--json/--worklist/--write` + `npm run ds:report` / `ds:report:write` — **#2013/#2017**.
- `PROTOCOL.md` §10.1–§10.4 (incl. gate anti-stale) — **#2013/#2020**.
- `DS_ADOCAO_INDICE.md` placar ✅/☐ por módulo — **#2017**.
→ O **delta real** é: (a) `pass`/`target`/`measured_against_sha` no `--module --json`; (b) anatomia `tasks/` + `tasks:index`/`tasks:project`; (c) §10.5.

## Decisões pra Wagner cravar
- **Objetivas (já decididas pelo fato/SOTA — confirma):** fonte git-native (topologia: Cowork só lê git) · evidência fecha, não opinião (Gaming the Judge) · amenda 0070/0130 sem supersede.
- **Subjetiva (tua):** começar pelo **Gap #1** (`--module --json` com `pass`, ~20min, destrava tudo) e ir incremental, **ou** desenhar a anatomia `tasks/` inteira de uma vez? Recomendo **Gap #1 primeiro** — é a âncora de evidência; sem ela o resto é aspiracional.

## Plano (IA-pair, ADR 0106) — após aceite
F1 `ds:report --module --json` + `pass`/`sha` (~20min) · F2 anatomia `tasks/` + `tasks:index`/`project` (~3h) · F3 §10.5 + 5 perguntas (~1h) · F4 `tasks:report` no Code (~1h) · F5 aceitar + numerar este ADR. Hook bloqueador `events.ndjson` não-append = follow-up dormente (P2, critério 0130).

## Refs
- Dossier SOTA: `memory/sessions/2026-05-30-arte-task-system-cowork-code.md`
- ADR 0070 (MCP tasks) · 0114 (loop) · 0130 (handoff) · 0209 (ratchet) · 0084 (imutabilidade) · 0239 (gov DS) · `prototipo-ui/PROTOCOL.md` §10
- SOTA: Magentic-One (arXiv 2411.04468) · Reflexion (2303.11366) · Gaming the Judge (2601.14691) · OpenAI Swarm/A2A · Temporal/LangGraph
