# 08 — Handoff (índice)

> **Este arquivo é índice, não narrativa.** Cada sessão de fechamento cria handoff próprio em `memory/handoffs/` (append-only — nunca editado depois).
>
> **Estado VIVO** (cycle ativo, tasks DOING/REVIEW, métricas, ADRs aceitas) está nas **tools MCP** — chame `brief-fetch` primeiro. Este índice só aponta pra narrativa interpretativa de cada sessão.
>
> Convenção fixada em **[ADR 0130](decisions/0130-handoff-append-only-mcp-first.md)** (2026-05-10).

---

## Últimos handoffs

- [2026-05-11 17:30 — Sells Grade Avançada + Modules/OficinaAuto qualificada + 5 agents paralelos](handoffs/2026-05-11-1730-sells-grade-oficinaauto-paralelizacao.md) (11 PRs · ADR 0136 + 0137 · 5 agents paralelos · pivot estratégico OfficeImpresso legacy)
- [2026-05-10 23:40 — Audit adversarial pós-Langfuse + Runbooks infra canônicos](handoffs/2026-05-10-2340-audit-adversarial-runbooks-infra.md) (3 PRs + 4 ADRs + 7 tasks MCP) — modo especialista SRE adversarial
- [2026-05-10 23:30 — Officeimpresso clickável + cascata Superadmin removida + MWART knowledge](handoffs/2026-05-10-2330-officeimpresso-sidebar-cascata.md) (4 PRs #505/#511/#512/#516)
- [2026-05-10 22:30 — Cycle higiene + Pivot fiscal + Cadeia FSM (4 PRs + ADR 0129)](handoffs/2026-05-10-2230-cycle-higiene-pivot-fsm.md)

> Handoffs anteriores a 2026-05-10 22:30 viviam em `memory/sessions/` (era pré-ADR-0130). Consultar `ls -t memory/sessions/ | head -10` pra histórico narrativo.

---

## Como retomar uma sessão

1. **`brief-fetch`** (Tier A always-on — Skill `brief-first`) → estado consolidado ~3k tokens
2. **`my-work`** → tasks DOING/REVIEW reais do owner autenticado
3. **`my-inbox`** → mentions/assignments pendentes
4. Ler **handoff mais recente** acima
5. (se suspeita de sessão paralela ativa) **`whats-active`** ([ADR 0119](decisions/0119-paralelismo-sessoes-whats-active-tier-1.md))
6. Skill **`continuar`** automatiza esses 5 passos + pede confirmação antes de agir

---

## Como fechar uma sessão (ANTES de criar handoff novo)

1. **MCP-first OBRIGATÓRIO** — chamar nessa ordem e capturar resultado:
   - `cycles-active` (cycle + goals + drift)
   - `my-work` (tasks reais)
   - `sessions-recent limit:3` (handoffs/sessions irmãs)
   - `decisions-search since:<data-último-handoff>` (ADRs aceitas no intervalo)
2. Criar **arquivo novo** em `memory/handoffs/YYYY-MM-DD-HHMM-<slug-kebab>.md`
   - Slug curto descritivo: `cycle-higiene-pivot-fsm`, `us-sell-011-fsm-tabelas`, etc
   - Incluir seção `## Estado MCP no momento do fechamento` com snapshot do passo 1 (prova de consulta)
3. Adicionar linha no topo da lista "Últimos handoffs" acima — apontando pro novo arquivo
4. **NUNCA editar handoffs antigos** (append-only enforced culturalmente; hook P2 dormente — ativa se houver reincidência)

Detalhes da skill em [`.claude/skills/memory-sync/SKILL.md`](../.claude/skills/memory-sync/SKILL.md). Detalhes do protocolo em [`memory/how-trabalhar.md`](how-trabalhar.md) §"Ao terminar uma sessão".

---

**Última atualização do índice:** 2026-05-10 — ADR 0130 aceita; 08-handoff.md reescrito de narrativa pra índice; conteúdos noite-2 e noite-3 preservados em `handoffs/2026-05-10-2230-*.md` e `handoffs/2026-05-10-2330-*.md`.
