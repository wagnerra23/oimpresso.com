---
date: 2026-05-31
hour: "13:54 BRT"
topic: "Worklist de auditoria paralela (Cowork feature A) — scaffold + scorer mecanizado determinístico + PR #2035 isolado pro main"
duration: "~2h"
authors: [wagner, claude-opus-4-8]
branch: feat/staging-ct100
pr: 2035
---

# Worklist de auditoria paralela — scaffold + scorer mecanizado (Cowork feature A)

## Estado MCP no momento
Snapshot leve (sessão curta). Não rodei `cycles-active`/`my-work` no fechamento — trabalho foi tooling de design-governance, não task de cycle. `tasks-list ProjectMgmt` mais cedo: US-TR-305/306/307 review · US-TR-308/PROJECT-2/3 todo · **US-TR-309..314 (ondas design) ainda NÃO sincronizaram pro MCP** (só no SPEC git, branch staging não foi pro main → webhook não disparou).

## O que aconteceu
1. **Decodifiquei pergunta crua** "lista de auditoria paralela já foi feita telas?" → a **auditoria** (board 222 telas, 2026-05-30) está feita; das 44 telas <70 só **Onda 0 (4 telas)** está codada (working tree, não commitada). Ondas 1-4 = `todo`.
2. **Handoff Cowork** ("Fetch this design... implement... acho que pediu a lista" + URL `Oimpresso ERP - Chat.html` 10MB): WebFetch estourou limite → li a cópia local (`cowork-2026-05-26-comunicacao-visual/`). Design Chat tem histórico de rejeição (24/100, é WhatsApp-style não Jana). **Perguntei A/B/C via AskUserQuestion** → Wagner escolheu **"Worklist auditoria paralela"** (não era o Chat — era a feature A do Cowork).
3. **Construí a worklist** em `prototipo-ui/audit/` (frente paralela-segura, read-only): GOLDEN-REFERENCE (10 regras = AP1-AP10 do PRE-MERGE-UI + ds/*) + schema + consolidate.mjs + README com prompt-template.
4. Wagner pediu **B** (Cowork dispara) → commit isolado `1cce79e03` (staging) + **PR #2035 limpo pro main** (worktree off origin/main + cherry-pick).
5. Wagner: "tem como melhorar?" → apontei o furo honesto: as regras "mecanizadas" eram **só rótulo**. Escolheu **A** → construí `score-mechanized.mjs` (determinístico, zero LLM, 7/10 regras por regex + ds/* real do baseline). Rodou nas **239 telas**. **Calibrei R6** (pegava dingbats ✓✕★ como emoji — FP nos goldens). Validado vs board (Inbox 91→88, Sells/Index 90→72 por R1/R2/R4 que o board já notava). Commit `157dd0cf8` → cherry-pick no PR #2035.

## Artefatos gerados (canon: `prototipo-ui/audit/`)
- `GOLDEN-REFERENCE.md` (~60L) · `design-report.schema.json` · `consolidate.mjs` · **`score-mechanized.mjs`** (Fase 1 determinística) · `README.md` (2 fases + prompt-scorer) · `example.design-report.json` · `CONSOLIDADO.md` (placar 239 telas, versionado) · `.gitignore` (reports/ + CONSOLIDADO.json regeneráveis)
- **2 commits staging:** `1cce79e03` (scaffold) + `157dd0cf8` (scorer+calibra) · **PR #2035** (feat/worklist-auditoria-paralela→main, +837/-0, 8 arq net, MERGEABLE)

## Persistência
- **git:** staging (2 commits) + branch `feat/worklist-auditoria-paralela` pushada (PR #2035)
- **MCP:** sincroniza quando PR #2035 ou staging chegar no main (webhook)
- **Cowork:** lê `prototipo-ui/audit/` do git pra disparar Fase 2

## Próximos passos pra retomar
1. **Wagner reconcilia** GOLDEN-REFERENCE com as "10 regras" da cópia do Cowork (COWORK_NOTES→Pendentes, fora do git).
2. **Mergear PR #2035** pro main (decisão Wagner — webhook então sincroniza).
3. **Fase 2:** Cowork dispara N agentes LLM só pras 3 regras julgadas (R5/R8/R10) + refino nota.
4. **R7 precisão:** hoje heurística ampla (80/239) — estreitar pra badge de status (precisa AST/contexto).

## Lições catalogadas
- **Li o screenshot de uso errado:** disse "perto do limite, conservar token" quando Wagner é **Max 20x** (81% semanal reseta em 2h, 5h em 0%). Wagner corrigiu 2×. **Não inferir escassez de budget de screenshot — perguntar/assumir abundância no Max.**
- **GOLDEN-REFERENCE não existia em git** — Cowork referenciou como se existisse. Mapeei pra AP1-AP10 (canon real) em vez de inventar (regra Tier 0 "não inventar").
- **R6 emoji regex:** range BMP `2600-27BF`/`2B00-2BFF` pega dingbats de UI (✓✕★✦⚙⬇), não só emoji → FP. Calibrado pro plano suplementar `1F000-1FAFF`.
- **Evidência > opinião (dossier 2026-05-30):** rótulo "mechanized:true" sem o check é opinião disfarçada. Só virou evidência quando `score-mechanized.mjs` rodou de verdade.
- **PR isolado pro main com worktree dirty:** branch off origin/main + cherry-pick (não dá pra `checkout` com Onda-0 WIP no working tree).

## Pointers detalhados
- Worklist: [`prototipo-ui/audit/README.md`](../../prototipo-ui/audit/README.md) · [`GOLDEN-REFERENCE.md`](../../prototipo-ui/audit/GOLDEN-REFERENCE.md)
- Dossier-mãe: [`memory/sessions/2026-05-30-arte-task-system-cowork-code.md`](../sessions/2026-05-30-arte-task-system-cowork-code.md) (evidência > opinião, Gap #1 ds:report)
- Board origem: [`memory/governance/scorecards/SCREEN-GRADE-BOARD-2026-05-30.md`](../governance/scorecards/SCREEN-GRADE-BOARD-2026-05-30.md) · plano 44 telas [`PLANO-DESIGN-TELAS-2026-05-31.md`](../governance/scorecards/PLANO-DESIGN-TELAS-2026-05-31.md)
- PRE-MERGE-UI AP1-AP10: [`memory/requisitos/_DesignSystem/PRE-MERGE-UI.md`](../requisitos/_DesignSystem/PRE-MERGE-UI.md)
