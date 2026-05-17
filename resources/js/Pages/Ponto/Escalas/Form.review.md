---
tela: Ponto/Escalas/Form
controller: PontoWr2\Http\Controllers\EscalasController@create/edit (inferido)
charter: null
current_round: 1
status: awaiting-smoke-browser
created_at: 2026-05-17
created_by: bulk-w31-agent-static
ux_targets:
  first_paint_ms: 800
  no_console_errors: true
  responsive_1440: true
  responsive_1280: true
---

## Round 1 — 2026-05-17 (bulk review estática)

**Status:** awaiting-smoke-browser

**Análise estática:**
- Charter: ausente
- AppShellV2: sim
- Inertia::defer: 0 props (`escala` único objeto — eager ok)
- localStorage: ausente
- Tailwind tokens canon: ok (DIAS array semântico PT-BR)
- useMemo/useCallback: 0
- Imports: lucide (ArrowLeft, CalendarDays, Save) + useForm + shadcn (Card/Input/Label/Select)

**Risco prévio:**
- Charter ausente
- Edit com 7 turnos (Dom-Sáb) — risco UX de digitação repetitiva sem "copiar dia anterior" (smoke vai mostrar se Wagner reclama)
- Horários sem máscara (HH:mm) — input type? precisa ver source completo

**Smoke pendente:**
- screenshot 1440 + 1280
- preencher escala 12x36 (alternância dias) — UI ajuda ou só lista?
- responsivo 1280 — 7 cards de turno empilha ou vira grid?

**Decisão Wagner:** [pendente]
