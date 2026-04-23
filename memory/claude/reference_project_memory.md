---
name: Project has its own memory/ folder
description: The oimpresso.com repo has a comprehensive memory/ folder — always read it before acting
type: reference
originSessionId: 3f332cf1-9ebd-4bb2-8b41-a6a1fd23c222
---
O repo `D:\oimpresso.com` (site oimpresso.com, UltimatePOS v6.7 + módulo Ponto WR2) mantém sua própria memória em `memory/` com:

- `memory/INDEX.md` — índice mestre
- `memory/08-handoff.md` — **ler SEMPRE ao retomar trabalho** (estado atual, pendências, próximo passo)
- `memory/00-user-profile.md` → `09-modulos-ultimatepos.md` — contexto numerado
- `memory/decisions/NNNN-*.md` — ADRs
- `memory/sessions/YYYY-MM-DD-session-NN.md` — append-only

**Como aplicar:** Antes de responder qualquer pergunta técnica sobre PontoWR2, UltimatePOS, CLT, AFD, banco de horas, eSocial ou sobre o site oimpresso.com, ler `memory/08-handoff.md` + `CLAUDE.md` do projeto. Essa memória é o "cérebro do projeto" e é mais autoritativa que qualquer inferência feita a partir do código.

**Branch atualizada:** `origin/6.7-bootstrap` (última atividade 2026-04-21, sessão 09 — upgrade Laravel 9.51/PHP 8.3, PontoWR2 corrigido).
