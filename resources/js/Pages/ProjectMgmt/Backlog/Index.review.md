# Review Round 1 — ProjectMgmt/Backlog/Index.tsx

**Tela:** `/project-mgmt/backlog` · **Stories:** US-TR-202 · **Charter:** ❌ ausente
**Reviewer:** W31 bulk · **Data:** 2026-05-17 · **Modo:** análise estática

## Resumo

Backlog filtrável (status/prio/owner/epic/cycle/sprint) + bulk edit (status/prio/owner) + persistência localStorage + busca debounced (350ms) + 5 KPIs + tabela com selected highlight. Pattern Jira-style maduro.

## Pontos fortes

- Persistência localStorage com prefixo `oimpresso.backlog.*` ([DESIGN.md §12](../../../../memory/requisitos/_DesignSystem/))
- Debounce 350ms em busca via `qDebounceRef` (não-bloqueante)
- Bulk action toolbar sticky `top-2 z-20` com tom blue contextual
- `STATUS_BADGE`/`PRIORITY_BADGE` reutilizados de `@/Components/board/badges` (SoC)
- Empty state na tabela inline
- Bulk POST com CSRF + reload parcial `only:['tasks','kpis']`

## Riscos / gaps (top 5)

1. **R1 — Charter ausente** ([ADR 0104](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)). Próxima Edit bloqueada por hook.
2. **R2 — `fetch` bulk SEM tratamento de erro de rede ou 422/403.** `r.json()` em response não-JSON quebra silenciosamente. Adicionar `if (!r.ok) toast.error(...)` + try/catch + revert otimista.
3. **R3 — `Inertia::defer` audit.** `tasks` (limit 500) + KPIs agregados podem ser pesados — verificar Controller usa defer pro `tasks` payload (RUNBOOK Tier 0 2026-05-15).
4. **R4 — Limite 500 tasks hardcoded** sem paginação no UI. Mensagem rodapé "Mostrando {counts.total} tasks (limite 500)" assume mas não pagina se >500. Roadmap PMG escalando vira blocker.
5. **R5 — Bulk action SEM confirmação** pra status destrutivo (`cancelled`). 1 click acidental cancela N tasks. Adicionar `confirm()` ou Dialog quando `fields.status === 'cancelled'`.

## Veredito round 1

Tela madura, melhor da família ProjectMgmt em features. **Pendências:** charter (R1), error handling bulk (R2), defer audit (R3). R4/R5 evolutivos.

**Status:** APROVA com pendências P2.
