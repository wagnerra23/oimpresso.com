# Review Round 1 — ProjectMgmt/Board/Index.tsx

**Tela:** `/project-mgmt/board` · **Stories:** US-TR-201, PMG-001/004 · **Charter:** ✅ existe (`Index.charter.md`)
**Reviewer:** W31 bulk · **Data:** 2026-05-17 · **Modo:** análise estática

## Resumo

Kanban Jira-style 7 colunas com drag-drop optimistic + 409 conflict handling + atalhos J/K/E/A/`/` + filtros (cycle/epic/owner) + busca client-side + polling 10s + on-focus reload + Detail Sheet via URL `?task=ID`. Tela mais sofisticada da família.

## Pontos fortes

- 409 Conflict com refetch silencioso + banner amber auto-dismiss 5s (PMG-001 pattern excelente)
- Optimistic update + revert em erro
- `expected_updated_at` enviado pra optimistic-lock no backend
- Atalhos teclado completos (lista linear `linearTasks` derivada)
- Polling 10s + `window.addEventListener('focus', reload)` (anti-stale)
- Detail Sheet via URL param (deep-link friendly + back button works)
- Charter existe — único da família com gate F3 cumprido

## Riscos / gaps (top 5)

1. **R1 — Polling 10s SEM `document.hidden` guard.** Mesmo problema do Activity: aba em background continua reload. Pattern: `if (!document.hidden) router.reload(...)`.
2. **R2 — `eslint-disable-next-line react-hooks/exhaustive-deps`** em 2 useEffect (linha 322 e 243). `patchStatus`/`linearTasks` mudam dependências reais. Refatorar com `useCallback` ou aceitar lint.
3. **R3 — `Inertia::defer` audit.** `kanban` (Record por status) pode ser pesado em projetos grandes — Controller deve usar defer. Validar.
4. **R4 — Atalho `/` foca busca mas SEM escape pra des-focar.** Pressionar Esc no input não tira foco programaticamente. UX power-user incompleto.
5. **R5 — `setConflictMessage('Erro de rede...')`** em catch é genérico — não diferencia timeout, offline, CSRF expirado. Pra Larissa (1280px) banner sem ação fica órfão.

## Veredito round 1

Tela referência da família — bem arquitetada, PMG-001 (conflict resolution) e PMG-004 (Detail Sheet) bem implementados. **Pendências:** polling guard (R1), defer audit (R3).

**Status:** APROVA com pendências P3 (polish).
