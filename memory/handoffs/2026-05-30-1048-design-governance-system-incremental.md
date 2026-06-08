---
date: '2026-05-30'
time: '10:48 BRT'
slug: design-governance-system-incremental
tldr: "Sistema de governança de design pro Claude Design (golden + pré-flight + grade 16-dim + índice mestre) construído e mergeado (PR #1991); 4 goldens de arquétipo PT-02..05 + ADR 0236 aceito + briefings refrescados; camada incremental (mcp_design_requests) proposta — retomar com MCP ativado."
topic: "Sistema de governança de design pro Claude Design (golden + pré-flight + grade + índice + reprocesso) + camada incremental proposta"
duration: 5h
prs: [1991, 1992, 1993, 1994]
decided_by: [W]
authors: [W, C]
---

# Handoff — Sistema de governança de design + camada incremental

## Estado MCP no momento do fechamento
⚠️ **MCP do oimpresso NÃO conectado nesta sessão** (só GitHub + file-server). Snapshot via git. Wagner pediu explicitamente **retomar com MCP ativado**. Sem `brief-fetch`/`my-work`/`decisions-search` ao vivo — próxima sessão começa por eles.

## O que aconteceu
Sessão longa partindo de "Wagner fraco em design, Claude Design não entende as regras". Construído o **sistema completo** que faz o Claude Design *criar dentro do que existe sem inventar nem repetir erro*:
1. **PR #1991 MERGEADO** → `GOLDEN-REFERENCE` (tela-ouro + 10 regras binárias) · `PRE-FLIGHT-TELA` (resolver) · `SCREEN-GRADE-METODO` (16 dim, níveis Beginner→Champion) · piloto 6 telas × 3 sims (T2/T3 ✅, T1 parcial) · `INDEX-DESIGN-MEMORIAS` (índice mestre positivo+negativo + regra de ouro, 88 docs reconciliados por 4 agentes) · `CLAUDE_COWORK_PRIMER` header canon · skills `design-memoria-reprocess` + `screen-grade` (esta de sessão paralela).
2. **PR #1992 ABERTO (ready)** → 4 goldens de arquétipo: PT-02 Form/Drawer (Cliente/Create) · PT-03 Detalhe (Sells/Show) · PT-04 Dashboard (governance/Dashboard) · PT-05 Kanban (OficinaAuto/ProducaoOficina). Fecha o gap "só PT-01 existia".
3. **PR #1993 ABERTO (ready)** → ADR 0236 `aceito` (governança evolução doc: append-only + ratchet + freshness + gatilhos) + 2 briefings stale refrescados (DS v4 roxo/sidebar light/multi-vertical).

## Artefatos gerados
- `prototipo-ui/{GOLDEN-REFERENCE,PRE-FLIGHT-TELA}.md` + `CLAUDE_COWORK_PRIMER`/`CLAUDE_DESIGN_BRIEFING` headers canon (PR #1991 merged)
- `memory/requisitos/_DesignSystem/{INDEX-DESIGN-MEMORIAS,SCREEN-GRADE-METODO,BRIEFING_CLAUDE_DESIGN}.md` + `padroes-tela/PT-02..PT-05`
- `memory/governance/screen-grades-pilot.md` (ledger piloto)
- `memory/decisions/0236-governanca-evolucao-doc-design.md` (aceito)
- `memory/decisions/proposals/design-request-ledger-incremental.md` ← **a continuar com MCP**
- `.claude/skills/{design-memoria-reprocess,screen-grade}/`

## Persistência
- **Git:** PR #1991 merged em main; PR #1992 + #1993 abertos ready (verdes — Module Grades Gate ✅, Pest ✅). Branch handoff `claude/session-handoff-2026-05-30`.
- **MCP:** webhook git→MCP propaga merged; #1992/#1993 só após merge.

## Próximos passos pra retomar (com MCP ativado)
1. `brief-fetch` + `decisions-search since:2026-05-30` (validar ADR 0236 aceito chegou ao MCP).
2. **Mergear #1992 (goldens) + #1993 (ADR+briefings)** se Wagner aprovar.
3. **Continuar a camada incremental** → `memory/decisions/proposals/design-request-ledger-incremental.md`: tabela `mcp_design_requests` (idempotency key REQ-NNN + delta manifest ancorado + checkpoint + resultado). Grade da ideia do Wagner = 80/100 (SOTA: Stripe idempotency + Temporal checkpoint + TraceLLM RTM). Validar anti-dup via `decisions-search`, depois emenda ADR 0236 OU ADR novo + spec DDL multi-tenant.
4. **Pendência não-feita (PR-C):** ligar os 2 enforcements de código — freshness-checker de design (estender ADR 0220) + hook do handoff `new_design_memories`→dispara skill. Precisa runtime PHP/vendor (container atual era clone sem vendor).

## Lições catalogadas
- **MCP desconectado** o tempo todo → operei via git/docs; OK pra docs, mas tasks/estado-vivo ficaram sem registro MCP (corrigir na retomada).
- **`git add` com pathspec de arquivo já movido (`git mv`) aborta o add inteiro** → commit `0fb40ec` pegou só o rename sem as edições; corrigi com commit complementar `abb3c77`. Lição: após `git mv`+`Edit`, conferir `git show HEAD:arquivo` antes de declarar feito.
- **Sessão paralela** commitou a skill `screen-grade` no mesmo branch (#1991) — benigna/complementar, entrou no merge. `whats-active` na retomada.

## Pointers detalhados
Session log: `memory/sessions/2026-05-30-screen-grade-metodo-estado-arte.md`. Método: `SCREEN-GRADE-METODO.md §7`. Índice mestre + regra de ouro: `INDEX-DESIGN-MEMORIAS.md §0`.
