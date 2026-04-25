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

**Branch atualizada:** `origin/6.7-bootstrap` — Laravel **13.6** + PHP 8.4 (upgrade cascade 9→13 feito em 2026-04-23, ver `project_roadmap_milestones.md`).

**Convenções do projeto (UltimatePOS legado):**
- **Models em namespace flat `App\`** — não existe `app/Models/`. Todos os modelos ficam direto em `app/` (ex.: `App\Business`, `App\User`, `App\Product`). 3.7 já era assim; migração para 6.7 mantém a convenção.
- Módulos nwidart seguem namespace próprio (`Modules\Officeimpresso\Entities\*`, etc.)
- Ao importar modelo em controller novo ou restaurado, usar `use App\Business` — **nunca** `use App\Models\*` (namespace não existe).

**ADRs em `memory/decisions/`** (relevantes pro trabalho Officeimpresso 2026-04-23/24):
- 0017 — Restauração Officeimpresso 3.7 → 6.7
- 0018 — Log acesso via listener+middleware (substituiu triggers MySQL)
- 0019 ✅ — Delphi auth pós-upgrade (3 fixes: enablePasswordGrant + re-hash secrets + provider='users')
- 0020 — Grupo econômico (matriz + filiais) — proposto, não implementado
