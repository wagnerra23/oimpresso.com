---
tela: Financeiro/ContasBancarias/Index
controller: Modules\Financeiro\Http\Controllers\ContaBancariaController@index
charter: ./Index.charter.md
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

# Screen Review — Financeiro/ContasBancarias/Index

> Append-only — rounds anteriores NUNCA editados. Skill `tela-smoke-pos-merge` (Tier B) auto-cria próximo round após merge que toque a tela.

---

## Round 1 — 2026-05-17 (criação estática — W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Escopo análise:** estática. Smoke browser MCP pendente.

**Observações estáticas:**
- ✓ AppShellV2 (Cockpit ADR 0039) + breadcrumb `Financeiro › Contas Bancárias`
- ✓ Charter EXISTE (`./Index.charter.md`)
- ✓ Tela enxuta (179 linhas)
- ⚠ Controller `ContaBancariaController@index` tem 1 paginate/with mas **0 `Inertia::defer`** — dataset pequeno (contas por business raramente >20), aceitável
- ✓ Sub-componente `ConfigurarBoletoSheet.tsx` separado
- ✓ 3 ocorrências `useMemo`/`useCallback` proporcionais
- ⚠ Sem `localStorage` prefix `oimpresso.contas-bancarias.*` — UX miss em telas operacionais (busca/sort não persistem)
- ✓ Sem `bg-*-N` crus problemáticos

**Riscos identificados (sem smoke):**
- 🟢 P2: dataset pequeno mitiga ausência de defer
- 🟡 P1: localStorage prefix ausente

**Pest GUARD recomendado próximo round:**
- CRUD conta bancária + configurar boleto (linkar provider Inter/Asaas)
- Cross-tenant biz=1 vs biz=99
- Validar isolamento Vaultwarden tokens (NÃO logar token no Pest)

**Decisão Wagner:** [pendente smoke browser MCP]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `ContasBancarias/Index.tsx` ou `ContaBancariaController@index`.
