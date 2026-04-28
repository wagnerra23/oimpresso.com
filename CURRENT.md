# CURRENT — estado vivo do projeto

> **Sobrescrito a cada handoff.** Pra histórico, ver `memory/sessions/` e `memory/08-handoff.md`.

**Sprint atual:** camada administrativa do Copiloto — Onda 1 (ROI direto), ADR [`requisitos/Copiloto/adr/arq/0003`](memory/requisitos/Copiloto/adr/arq/0003-administracao-roi-governance.md).

**Em andamento:** US-COPI-070 — dashboard de custo de IA (`/copiloto/admin/custos`). Branch `claude/nervous-burnell-f497b8`. Código pronto, autoload regenerado, bundle Inertia compilado. **Aguardando validação visual do Wagner.**

**Próximo passo concreto:**
1. Wagner abre `https://oimpresso.test/copiloto/admin/custos` (login WR23) e valida a UI.
2. Se OK → merge dessa branch na branch principal e segue pra US-COPI-071 (orçamento mensal de IA).
3. Se ajustes → iterar antes do merge.

**Bloqueios:** nenhum.

**Última sessão (2026-04-28):** implementação Onda 1 do ADR ARQ-0003 (CustosController + CustosService + Page Inertia + permissão + lang) + criação de DESIGN.md como hub visual + estruturação memória (CURRENT.md, /continuar, INFRA.md, skill multi-tenant-patterns).
