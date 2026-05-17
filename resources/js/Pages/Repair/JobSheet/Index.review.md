---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: Repair/JobSheet/Index
file: resources/js/Pages/Repair/JobSheet/Index.tsx
charter_present: true
charter_file: Index.charter.md
runbook_present: true
runbook_file: memory/requisitos/Repair/RUNBOOK-jobsheet-index.md
append_only: true
---

# Review estática — `Repair/JobSheet/Index.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · PageHeader+Card shared ✓ · charter+RUNBOOK ✓
- `datatable_url` injetado como string + comment textContent (anti-XSS explícito) ✓
- **DEBT crítico**: `useEffect` cria mensagem placeholder "DataTables AJAX legacy" — listagem AINDA NÃO migrada (F3 incompleto)
- Sem `Deferred`, sem useForm — só shell react com placeholder
- `filterCount` calc + `flags` props pequenos OK

## Riscos Tier 0

1. **MWART/M1 — F3 INCOMPLETO há tempo**: tela é só "shell react" com placeholder DataTables. Viola caminho único MWART (ADR 0104). Maior débito do módulo Repair F3.
2. **UX/M2 — Usuário vê mensagem técnica em prod**: "Listagem ainda usa DataTables AJAX legacy" é UX vergonhosa pra cliente.
3. **CSRF/L4 — Embed DataTables externo**: se DataTables endpoint legacy não tem mesmo middleware CSRF, risco.
4. **A11Y/L4 — `replaceChildren` DOM manipulado fora React**: inacessível, React DevTools não vê.
5. **CHARTER/L4 — Charter diz uma coisa, código entrega placeholder**: drift severo.

## Top 5 recomendações

1. P0 — Abrir US-REPAIR-JS-INDEX-MIGRATE: migrar pra TanStack Table com endpoint `/repair/job-sheet/data` Inertia (deferred).
2. P0 — Remover placeholder UX visível em prod até migração — embedar Blade direto ou ocultar trecho técnico.
3. P1 — Charter atualizar refletindo "placeholder DataTables ainda ativo" se decidir manter temporariamente.
4. P2 — Pest GUARD que verifica `datatable_url` não escapa fora origin.
5. P3 — A11Y se manter DOM manipulado: `role="region" aria-label`.
