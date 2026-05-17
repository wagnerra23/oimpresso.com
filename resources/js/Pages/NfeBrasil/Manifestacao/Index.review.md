---
review_round: W31-R1
tela: /nfe-brasil/manifestacao
component: resources/js/Pages/NfeBrasil/Manifestacao/Index.tsx
charter: PRESENTE
reviewer: claude (W31 bulk static)
review_date: 2026-05-17
modulo: NfeBrasil
status: live
loc: 525
---

# Review estático — NfeBrasil/Manifestacao/Index

## Cabeçalho
- US: US-NFE-052 (manifestação destinatário UI)
- ADRs: 0039 (cockpit), 0093 (multi-tenant Tier 0), 0116 (caso Gold)
- Visual-comparison `manifestacao-visual-comparison.md` approved 2026-05-09
- Runbook `RUNBOOK-manifestacao.md`

## Pontos fortes
- Master/Detail layout xl: lista esquerda + Linked apps (Fornecedor/Itens/Histórico) direita
- KPIs: Pendentes / Vencendo 7d / Confirmadas no mês — alinhado com ADR 0116 Gold
- Atalhos canon: J/K navegar + C/D/R verbos manifestação + / busca
- Bulk toolbar sticky com contador grande tabular-nums
- `PrazoBadge` com cores semânticas (vermelho<7d, amber<30d)
- `nsuState.ultimo_check_em` + count "novas no último lote" — observabilidade SEFAZ
- "Buscar agora" disabled se `!permissions.canManage` — RBAC OK
- Justificativa min 15 chars validation client (alinhado NT 2014.002)
- `localStorage` filter persistence (DESIGN.md §12)
- EmptyState com hint do cron (06:15 BRT)

## Riscos / gaps
1. `confirm()` nativo browser em `cienciar/confirmar/desconhecer/naoRealizada/bulkConfirmar` — UX inferior a `AlertDialog` shadcn (padrão Show NFSe). Charter aprovou mas é débito UX. P2
2. `prompt()` nativo browser pra justificativa — pior ainda. Sem textarea, sem counter, sem cancel claro. P1
3. Atalho `c/d/r` dispara só com `permissions.canManage && status===pendente`, mas sem feedback visual de quê teclado tá pressionado (vs hover row). P2
4. `LinkedItens`/`LinkedHistorico` fetch sem `AbortController` — se troca foco rápido (J/K spam), N requests pendentes. P2
5. Bulk confirmar não chunk — confirmar 100+ NF-e cai em single POST grande. Backend tem que tratar timeout. P2
6. Filter buttons render sem aria-pressed — accessibility leve. P3
7. `selecionados` Set serializa via `Array.from` mas não persiste em refresh — sem grande issue. P3
8. Sync now sem feedback de progresso real (apenas `submitting` boolean) — pode demorar 30s+ SEFAZ. P2
9. `toast.error` na Justificativa <15 chars mas o `prompt` já foi fechado → user precisa re-clicar. P2

## Multi-tenant
- Backend assumido scopa `dfes.where('business_id', $bizId)`. Charter indica ADR 0093 IRREVOGÁVEL.

## Recomendação
1. Substituir `prompt()` por shadcn Dialog com Textarea + counter (P1)
2. Substituir `confirm()` por `AlertDialog` (P2)
3. AbortController nos LinkedItens/Historico (P2)
4. Chunk bulk confirmar em batches 25 (P2)
