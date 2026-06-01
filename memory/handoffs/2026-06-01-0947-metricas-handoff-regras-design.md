---
date: "2026-06-01"
time: "09:47 BRT"
slug: "metricas-handoff-regras-design"
topic: "Handoff Cowork→Code metricas.html — 2 proibições de design no canon (#2075)"
duration: "~1h"
authors: [claude-code, wagner]
prs: [2075]
tldr: "Bundle Cowork metricas.html processado: NÃO é port React (hub Cowork não-canon, fica Cowork-local). Tarefa real = COWORK_NOTES Pendentes (3 regras). Regras 1+2 (no-dup L-21 + trilha-do-tempo L-22) normalizadas em CLAUDE_DESIGN_BRIEFING §7.1 + proibicoes.md, mergeadas no #2075. Regra 3 (rename shell) = N/A. Passo 0 pegou bundle stale (#2069/#2073 já em main)."
us: []
next_steps:
  - "Loop Cowork: Wagner transporta CODE_NOTES.md 1× (carteiro §10.2) → [CC] marca as 3 regras [PROCESSADO]"
  - "Separado/disponível: 3 IA champion-makers (purge LGPD / OTel collector / gate RAGAS / Meilisearch) — prep já em #2073, go Tier 0 [W]"
related_adrs: []
---

# `metricas.html` handoff → 2 proibições de design no git (#2075)

## Estado MCP no momento
Snapshot leve (sessão 1-PR doc — heurística R12 "handoff curto", pula passo 1-2). Não puxei cycle/my-work fresco. `origin/main` HEAD pós-merge = `d7a7dc4e6`.

## O que aconteceu
Wagner: *"implemente os aspectos relevantes do design"* apontando o bundle Cowork **`metricas.html`** (claude.ai/design, chat33). Decodifiquei: **não é port React**. `metricas.html` é hub Cowork **não-canon** (3 relatórios meta — Governança 8.3 / Estrutura de IA 8.0 / Diagnóstico 7.6); por decisão do próprio chat33, fica **Cowork-local, não vira tela**. O que o [CC] preparou pra [CL] normalizar no git eram **3 regras** desta sessão (`COWORK_NOTES.md → 📥 Pendentes`).

**Passo 0 (§10.4 vs `origin/main` fresco):** bundle estava **stale** — Jana Pro `#2069` + prep IA Tier 0 `#2073` já em `main` (bundle dizia *"⏸️ não disparar"*). Bati cada item contra o main (L-09) → não recriei nada.

**Implementado (Regras 1+2 — Wagner confirmou escopo via AskUserQuestion):**
- **Regra 1** — no-duplicação de design (L-21) → `CLAUDE_DESIGN_BRIEFING §7.1` (1 tema = 1 doc, nunca `vN.html`).
- **Regra 2** — trilha-do-tempo / lápide (L-22) → `§7.1` + forward-ref em `proibicoes.md`.
- **Regra 3** — rename shell `Oimpresso ERP - Chat.html` → `oimpresso.com.html` = **N/A**: não há shell vivo no repo (só snapshots datados em `_arquivo/`, `cowork-2026-05-26-…/`). Nada a renomear.

## Artefatos gerados
- `prototipo-ui/CLAUDE_DESIGN_BRIEFING.md` §7.1 (sub-bloco novo, 2 bullets) — canon
- `memory/proibicoes.md` +1 forward-ref (Memória/governança → BRIEFING §7.1)
- `prototipo-ui/CODE_NOTES.md` retorno [CL]→[W] (§10.2)
- **PR [#2075](https://github.com/wagnerra23/oimpresso.com/pull/2075) MERGED** squash `d7a7dc4e6` · 13/13 CI verde · +27/−0 · `--admin` (conta única, `REVIEW_REQUIRED` insatisfazível; Wagner mandou "merge")

## Persistência
- **git:** mergeado em `main` (#2075). Webhook GitHub→MCP propaga ~2min (ou cron server-side).
- **Cowork (loop §10.2):** Wagner faz Share→Handoff → [CC] lê `CODE_NOTES.md` → marca as 3 regras `[PROCESSADO]` (1+2 done, 3 N/A) — fecha o L-09 (não re-propor).

## Próximos passos pra retomar
- **Loop Cowork:** Wagner transporta `CODE_NOTES.md` de volta 1× (carteiro §10.2).
- **Disponível, separado (não-feito):** 3 IA champion-makers (purge LGPD / OTel collector / gate RAGAS / resiliência Meilisearch) — prep já em `#2073`, espera go Tier 0 [W]. NÃO são "design do metricas.html".

## Lições catalogadas
- **L-09 confirmada:** bundle de design é snapshot — `git fetch` + checar `origin/main` HEAD antes de afirmar/executar (Jana Pro #2069 já estava feito apesar do "não disparar").
- README de bundle Cowork manda **ler os chats** (a intenção mora lá), não só o HTML — sem isso eu teria portado pra React contra a decisão do Wagner.
- **Adaptação §10.4** (não copiar literal): "layout único do shell" em vez de citar `Oimpresso ERP - Chat.html` (inexistente no repo).
- Worktree fresco off main mostra 2 arquivos "modificados" sem eu tocar (EOL/.gitattributes: `recurringbilling.php` + `Nfe-visual-comparison.md`) → deixar fora de PRs; possível higiene futura.

## Pointers detalhados
- Bundle Cowork (extraído em temp, não-versionado): `metricas.html` + `chats/chat33.md` + `project/{README,CLAUDE_CODE_BRIEFING,COWORK_NOTES}.md`.
- Canon vivo: [`prototipo-ui/CLAUDE_DESIGN_BRIEFING.md`](../../prototipo-ui/CLAUDE_DESIGN_BRIEFING.md) §7.1 · [`prototipo-ui/CODE_NOTES.md`](../../prototipo-ui/CODE_NOTES.md) última entrada.
