# CURRENT — estado vivo do projeto

> **Sobrescrito a cada handoff.** Pra histórico, ver `memory/sessions/` e `memory/08-handoff.md`.

**Sprint atual:** camada administrativa do Copiloto — Onda 1 (ROI direto), ADR [`requisitos/Copiloto/adr/arq/0003`](memory/requisitos/Copiloto/adr/arq/0003-administracao-roi-governance.md).

**Em andamento:** US-COPI-070 — dashboard de custo de IA (`/copiloto/admin/custos`). Branch `claude/nervous-burnell-f497b8`. Código pronto, autoload regenerado, bundle Inertia compilado. **Aguardando validação visual do Wagner.**

**Próximo passo concreto:**
1. Wagner abre `https://oimpresso.test/copiloto/admin/custos` (login WR23) e valida a UI.
2. Se OK → merge da branch `claude/nervous-burnell-f497b8` na `main` e segue pra US-COPI-071 (orçamento mensal de IA).
3. Se ajustes → iterar antes do merge.

**Bloqueios:** nenhum.

**Última sessão (2026-04-28):** reorganização da memória/state do repo + publication policy. Mergeado em `main` via [PR #56](https://github.com/wagnerra23/oimpresso.com/pull/56) (`f5b72f75`). Entregas: CLAUDE.md slim 349→160, novos CURRENT/INFRA/DESIGN, `/continuar`, hook SessionStart, skills `multi-tenant-patterns` e `publication-policy`, ADR 0040 (Claude supervisiona, Wagner escala). US-COPI-070 da sessão anterior segue pendente de validação visual.
