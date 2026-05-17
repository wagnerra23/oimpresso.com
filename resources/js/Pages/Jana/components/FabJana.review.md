---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: Jana/components/FabJana
file: resources/js/Pages/Jana/components/FabJana.tsx
charter_present: false
charter_file: null
runbook_present: false
runbook_notes: componente compartilhado (NÃO é página Inertia) — escopo charter discutível
append_only: true
---

# Review estática — `Jana/components/FabJana.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).
> **Nota:** este é um COMPONENTE compartilhado (não página Inertia). Tier 0 charter aplica-se a `<Tela>.tsx` Inertia. Review feito por escopo da task W31.

## Sinais técnicos

- Componente simples (22 linhas): Floating Action Button → `/copiloto?context=<route>`
- `Link` Inertia ✓ · ícone `MessageSquare` lucide
- `fixed bottom-6 right-6 z-50` — posição global fixa
- `aria-label="Conversar com Copiloto"` ✓ A11Y
- `focus-visible:ring-2 focus-visible:ring-ring` ✓ tokens semanticos

## Riscos Tier 0

1. **LOCATION/M3 — Componente em `Pages/Jana/components/`**: convenção canon é `_components/` (underscore prefix). `components/` (sem underscore) pode ser indexado por router Vite — risco de URL `/jana/components/...` ser tratada como página.
2. **Z-INDEX/L3 — `z-50` hardcoded**: sem token semantic. Risco overlap com Sheet/Dialog/Toast.
3. **CONTEXT/L4 — `encodeURIComponent(contextRoute)`**: ✓ XSS-safe.
4. **MOBILE/L4 — `bottom-6 right-6` colidir com mobile bottom-nav** se módulo tiver.
5. **TIER 0 SCOPE/L4 — Componente sem charter aceito**: revisar política W31 charters para componentes shared.

## Top 5 recomendações

1. P0 — Mover `Pages/Jana/components/` → `Pages/Jana/_components/` (underscore canon Inertia/Vite).
2. P1 — Z-index token: extrair `z-fab` em design system.
3. P2 — Política: componentes em `_components/` precisam charter? Sub-charter inline?
4. P3 — Mobile bottom-nav collision check.
5. P3 — Tests Vitest unit `FabJana` (Link href correto, aria-label, encode).
