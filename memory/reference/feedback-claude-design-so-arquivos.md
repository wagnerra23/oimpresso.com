---
id: reference-feedback-claude-design-so-arquivos
name: Claude Design só enxerga ARQUIVOS — nunca o MCP
description: Tudo que o Claude Design (Cowork) consome (golden, pré-flight, índice, método, ledger) tem que ser arquivo git bem organizado; o MCP é do Claude Code/time, o Design não tem acesso
type: feedback
---
O **Claude Design** (Cowork / claude.ai design) opera só sobre **arquivos** — conectores GitHub + file-server. Ele **não tem acesso ao MCP do oimpresso** (`mcp.oimpresso.com`). O MCP é canal do **Claude Code (eu) e do time** (tasks, decisions-search, memoria-search).

**Regra:** qualquer artefato que o Claude Design precise **consumir** — golden de arquétipo, pré-flight resolver, índice-mestre, método screen-grade, **ledger de pedidos de design**, score-as-code — tem que ser **arquivo bem organizado em git**. Pôr isso só numa tabela do MCP = invisível pro agente que faz o design.

**Why:** Wagner 2026-05-30, ao revisar a proposta do Design Request Ledger (que liderava com tabela `mcp_design_requests` no CT 100): *"lembre que no design não tem o MCP. tem que ser arquivos bem organizados."* O handoff #1994 já tinha registrado que a própria sessão de design rodou **sem MCP** (só GitHub + file-server). É estrutural, não circunstancial.

**How to apply:**
- Ledger de design = **arquivos** (`memory/governance/design-requests/REQ-NNN.md` + `LEDGER.md`), **não** tabela MCP.
- O webhook git→MCP indexa esses arquivos de graça → Claude Code/time buscam via `memoria-search`. Isso é **espelho de leitura**, nunca dependência nem canal pro Design.
- Alinha [ADR 0061](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) — git é SSOT, MCP é índice sobre o git.
- Vale pra qualquer feature futura de governança de design: **arquivo primeiro; MCP só espelha.**
