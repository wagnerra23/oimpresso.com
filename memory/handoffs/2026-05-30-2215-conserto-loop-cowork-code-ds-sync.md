---
date: 2026-05-30
hour: "22:15 BRT"
topic: "Conserto do loop Cowork↔Code (PROTOCOL §10 + ds:report + prompts versionados) + pesquisa estado-da-arte do sync design↔code↔git"
duration: "~sessão média · 1 PR mergeado (#2013) + pesquisa SOTA"
authors: ["Claude Opus 4.8 (1M)", "Wagner"]
---

# Conserto do loop Cowork↔Code + estado-da-arte do sync DS

## Estado MCP no momento
- **Cycle CYCLE-07** "Fundações pós-4.8 … DS v3 enforced" (12d restantes, 14% decorrido). Goal "DS v3 + MWART camada 2+3 enforced · alvo 90" — este conserto reforça o canal de governança DS.
- my-work @wagner: 30 tasks (5 REVIEW, 6 BLOCKED dormentes Gold, 19 TODO). Nenhuma task ds-loop dedicada (veio de pedido direto Wagner).

## O que aconteceu
Wagner colou o roadmap "DS até zero" do Cowork (6 fases A-F, migração `ds/*`→0) + perguntou *"não botou um gatilho pra Code criar o protocolo pra comunicar contigo? olhe e conserte os gatilhos"*. **Diagnóstico provado:** o roadmap chegou via 2 snippets genéricos do Claude Design ("Fetch this design file"/"Implement the designs") que **não** carregam o `PROTOCOL.md` nem abrem retorno `[CL]→[CC]`. Evidência no git: `HANDOFF.md` 15d stale (parado 15/05), `SYNC_LOG.md` parado 25/05, `CODE_NOTES.md` morto (1 entrada 09/05), `npm run ds:report` (que o roadmap manda rodar 4×) **não existia**.

**Conserto (PR #2013 mergeado --admin c/ aprovação Wagner "aprovo merge"):**
- `PROTOCOL.md §10` — gatilho de IDA (substitui genéricos) + canal de RETORNO via 3 canais (`DS_ADOCAO_INDICE`/`SYNC_LOG`/`HANDOFF`, propagam webhook GitHub→MCP). Ancora ADR 0114+0239.
- `scripts/ds-report.mjs` + `npm run ds:report` — placar `ds/*` por **regra×módulo** (o baseline só agrega sob `no-restricted-syntax`). Medido vivo: **`ds/*` = 616** (era 639; −23 Financeiro #1982).
- 6 prompts do roadmap **versionados** em `prototipo-ui/PROMPT_PARA_CODE_DS-*.md` (ADR 0239 git=SSOT, mata URLs claudeusercontent 1h).
- `HANDOFF.md` reancorado 30/05 + `SYNC_LOG.md` append.

**Pesquisa SOTA (pedido "alguém conseguiu? estado da arte?"):** auto-sync git→Claude Design **não existe** — issue [#180](https://github.com/anthropics/claude-ai-mcp/issues/180) "Auto-sync connector via webhook" **fechada not-planned**; 6+ issues abertas pedindo API/sync sem solução oficial. Confirmei no app (Chrome, logado): o repo **JÁ está conectado** ("GitHub connected"), mas o sync é **manual "Sync now"**. SOTA real = **Figma** (sync bidirecional git↔design + drift detection + PR pela UI). **Veredito:** oimpresso já está no estado-da-arte na **governança** (guard `ds/*` + ratchet + `ds:report` + §10 — guardrail que poucos têm); o gap (auto-sync) é limite da **ferramenta** Claude Design (Research Preview), não do setup. "Sync now manual pós-batch" é o melhor disponível hoje + gate humano saudável.

## Artefatos gerados
- **PR #2013** (mergeado `c954ec736`): PROTOCOL §10 + `ds-report.mjs` + `package.json` ds:report + 6 prompts + HANDOFF/SYNC_LOG. CI 100% verde (Vite/ESLint ratchet/Pest/Design index).
- Este handoff + linha de índice.

## Persistência
- **git:** #2013 em main (`c954ec736`). Este handoff em `feat/handoff-2026-05-30`.
- **MCP:** webhook GitHub→MCP propaga ~2min pós-merge.
- **Cowork:** o §10 só entra no contexto do Cowork **após "Sync now"** (manual) — não automático. Projeto: `claude.ai/design/p/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58`.

## Próximos passos pra retomar
`brief-fetch` → se retomar DS: `npm run ds:report` (placar) → disparar **Fase A pelo Sells** (1 módulo=1 branch=1 PR, para no gate visual) via `prototipo-ui/PROMPT_PARA_CODE_PR-C-WORKLIST.md`. Cada PR reporta de volta via PROTOCOL §10.2. **Pendente Wagner:** dar "Sync now" no projeto Cowork pra ele enxergar o §10.

## Como buscar novos conhecimentos (re-pesquisa)
> Pedido explícito Wagner 2026-05-30. Pra fechar o loop 100% automático quando a ferramenta permitir:
- **Monitorar (gatilho de re-pesquisa):** issues [claude-code #47744](https://github.com/anthropics/claude-code/issues/47744) (API project knowledge) + [#25983](https://github.com/anthropics/claude-code/issues/25983) (sync CLI) + [#39051](https://github.com/anthropics/claude-code/issues/39051). **Quando QUALQUER uma sair de "open" → automação viável.**
- **Quando a Project Knowledge API sair:** automatizar via **Claude Code Routines** (trigger on-merge → script `claude project sync`). Docs: `code.claude.com/docs/en/routines`.
- **Queries que funcionaram:** "Claude Design auto sync GitHub webhook" · "Claude Projects connector sync API endpoint workaround" · "design to code closed loop Figma Code Connect tokens git drift".
- **Benchmark SOTA (o alvo):** Figma design-to-code loop (`figma.com/blog/what-the-design-to-code-loop-unlocks`) — bidirecional + drift detection + PR pela UI.
- **Conhecimento atual vive em:** `prototipo-ui/PROTOCOL.md §10` (o loop) + este handoff. Re-rodar com agente `estado-da-arte` se quiser doc comparativo completo.

## Lições catalogadas
- **Gatilhos genéricos do Claude Design não carregam protocolo** — o "Fetch this design file/Implement the designs" é handoff automático burro; precisa do gatilho canônico §10.1.
- **O lado que apodrece é o RETORNO `[CL]→[CC]`** — HANDOFF/SYNC_LOG/CODE_NOTES viram letra morta sem o gatilho §10.2. Canal é git→MCP; `memory/handoffs/` o Cowork **não lê**.
- **Sync Claude Design é MANUAL** — "deixa o git resolver" não é automático (issue #180 not-planned). Não é falha local, é a ferramenta.
- **`frosty-*` é subdir git-ignored** (não worktree real) — mexer em `prototipo-ui/`+`memory/` exige worktree de `origin/main` (o toplevel está em `feat/staging-ct100`, onde paths relativos confundem `check-ignore`).

## Pointers detalhados
- `prototipo-ui/PROTOCOL.md §10` · `scripts/ds-report.mjs` · ADR 0239 (governança DS git SSOT) · ADR 0114 (loop formalizado) · `prototipo-ui/PROMPT_PARA_CODE_DS-ROADMAP-ATE-ZERO.md`
