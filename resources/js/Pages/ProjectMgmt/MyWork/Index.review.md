# Review Round 1 — ProjectMgmt/MyWork/Index.tsx

**Tela:** `/project-mgmt/my-work` · **Stories:** US-TR-204 · **Charter:** ❌ ausente
**Reviewer:** W31 bulk · **Data:** 2026-05-17 · **Modo:** análise estática

## Resumo

Dashboard pessoal split 2:1 — My Work (tasks atribuídas agrupadas por cycle) + Inbox (notificações). Atalhos J/K/E/Tab/R/Shift+R. Focus ring visual (blue-400/60 quando aba ativa). Polling 30s + on-focus. 7 tipos de notif (mention/assigned/review_requested/...).

## Pontos fortes

- **Bi-pane focus state** persistido em localStorage (`oimpresso.mywork.focus`) — UX coesa
- Atalho Tab pra alternar foco (pattern terminal-style)
- 7 tipos de inbox com icon + label tipados (`TYPE_ICON`/`TYPE_LABEL` Record)
- Filter toggle "só não-lidas" / "mostrar lidas" via URL param (deep-link OK)
- Optimistic markRead com revert no erro
- Empty states caprichados (My Work + Inbox separados)
- Greeting "Bom dia, {username}" — toque humano

## Riscos / gaps (top 5)

1. **R1 — Charter ausente** ([ADR 0104](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)). Bloqueador.
2. **R2 — Greeting estático "Bom dia"** não muda por hora. Após 12h vira "Boa tarde", após 18h "Boa noite". Polish trivial: `new Date().getHours() < 12 ? 'Bom dia' : ...`.
3. **R3 — Polling 30s SEM `document.hidden` guard** (idem Activity/Board). Padrão repetido — extrair hook `usePollingWhenVisible(callback, ms)`.
4. **R4 — Click em notif faz `router.visit('/project-mgmt/board?focus=...')`** mas Board não tem param `focus` documentado — só `?task=` (PMG-004 Detail Sheet). Bug latente: notif clicada não destaca task. Renomear `?focus=` → `?task=` (consistência) ou implementar `focus` no Board.
5. **R5 — `inbox` array exibido sem paginação** — em prod com semanas acumuladas pode ser 200+ items. Adicionar virtual scroll ou limit + "ver mais".

## Veredito round 1

Tela coesa, atalhos terminal-style ótimos pra Wagner/dev. **Pendências:** charter (R1), polling hook (R3), bug `?focus=` vs `?task=` (R4, bug latente).

**Status:** APROVA com pendências P2 (R4 é bug).
