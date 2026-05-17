---
tela: Financeiro/Unificado/Novo
controller: Modules\Financeiro\Http\Controllers\UnificadoController@novo
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

# Screen Review — Financeiro/Unificado/Novo

> Append-only — rounds anteriores NUNCA editados. Skill `tela-smoke-pos-merge` (Tier B) auto-cria próximo round após merge que toque a tela.

---

## Round 1 — 2026-05-17 (criação estática — W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Escopo análise:** estática. Smoke browser MCP pendente.

**Observações estáticas:**
- ✓ AppShellV2 via `Novo.layout = page => <AppShellV2>{page}</AppShellV2>` (padrão alternativo aceito)
- ⚠ Charter AUSENTE (`./Novo.charter.md` não existe) — **recomenda-se criar** (skill `mwart-process` ADR 0104)
- ✓ Tela MUITO enxuta (81 linhas) — form one-shot novo lançamento
- ✓ Controller `UnificadoController@novo` — props leves (form vazio + categorias + contas); defer não-crítico aqui
- ⚠ 2 ocorrências `useMemo`/`useCallback` — proporcional
- ⚠ Sem `localStorage` prefix `oimpresso.unificado-novo.*` — draft form não persiste (UX miss: usuário perde dados se fecha sem submit)
- ✓ Sem `bg-*-N` crus problemáticos

**Riscos identificados (sem smoke):**
- 🟢 P2: tela pequena, baixo risco UX
- 🟡 P1: draft form não persiste (perda dados acidental)
- 🟡 P1: charter ausente

**Pest GUARD recomendado próximo round:**
- POST `/financeiro/unificado/novo` valida payload obrigatório
- Lançamento criado scopa business_id correto
- Cross-tenant biz=1 vs biz=99

**Decisão Wagner:** [pendente smoke browser MCP]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `Unificado/Novo.tsx` ou `UnificadoController@novo`.
