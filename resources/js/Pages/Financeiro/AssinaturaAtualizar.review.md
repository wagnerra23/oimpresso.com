---
tela: Financeiro/AssinaturaAtualizar
controller: Modules\Financeiro\Http\Controllers\AssinaturaController@form
charter: (ausente — recomendado criar)
current_round: 1
status: awaiting-smoke-browser
created_at: 2026-05-17
created_by: W31 Bulk Review Round 1 (Financeiro)
ux_targets:
  first_paint_ms: 800
  fcp_ms: 1200
  no_console_errors: true
  responsive_1440_no_scroll_horizontal: true
  responsive_1280_no_scroll_horizontal: true
---

# Screen Review — Financeiro/AssinaturaAtualizar

> Append-only — rounds anteriores NUNCA editados. Skill `tela-smoke-pos-merge` (Tier B) auto-cria próximo round após merge que toque a tela.

---

## Round 1 — 2026-05-17 (criação estática — W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Escopo análise:** estática (lint visual + checks canon ADR 0039 Cockpit / RUNBOOK Inertia::defer / Tailwind tokens). Smoke browser MCP pendente.

**Observações estáticas:**
- ✓ AppShellV2 (`@/Layouts/AppShellV2`) — Cockpit ADR 0039 ok
- ✓ Layout enxuto (179 linhas) — render direto sem subcomponentes pesados
- ⚠ `Inertia::defer` NÃO usado no Controller `AssinaturaController@form` — props leves (config Asaas + dados assinatura). Aceitável se queries < 50ms; precisa confirmar via smoke.
- ⚠ `useMemo` / `useCallback` NÃO usados — 2 ocorrências apenas (provavelmente lista pequena). Sem risco P0.
- ⚠ Charter AUSENTE (`./AssinaturaAtualizar.charter.md` não existe) — **recomenda-se criar** antes do próximo edit (skill `mwart-process` ADR 0104)
- ✓ Sem `bg-blue-N` / `bg-red-N` crus detectados — Tailwind tokens semânticos ok
- ⚠ Sem `localStorage` prefix `oimpresso.*` — form não-persistente (aceitável pra tela one-shot atualizar)

**Riscos identificados (sem smoke):**
- Tela one-shot de atualização — risco baixo de first_paint > 800ms
- Sem Charter dificulta validação de aderência ao blueprint canônico

**Pest GUARD recomendado próximo round:**
- Testa POST `/financeiro/assinatura/atualizar` com payload válido vs inválido
- Cross-tenant biz=1 vs biz=99 (multi-tenant Tier 0 — ADR 0093)

**Decisão Wagner:** [pendente smoke browser MCP]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `AssinaturaAtualizar.tsx` ou Controller `AssinaturaController@form`.
