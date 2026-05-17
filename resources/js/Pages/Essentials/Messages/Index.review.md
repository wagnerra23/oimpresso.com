---
tela: Essentials/Messages/Index
controller: Modules\Essentials\Http\Controllers\EssentialsMessageController@index
charter: (ausente — recomendado criar)
current_round: 1
status: awaiting-smoke-browser
created_at: 2026-05-17
created_by: W31 Bulk Review Round 1 (Essentials)
ux_targets:
  first_paint_ms: 800
  fcp_ms: 1200
  no_console_errors: true
  responsive_1440_no_scroll_horizontal: true
  responsive_1280_no_scroll_horizontal: true
---

# Screen Review — Essentials/Messages/Index

> Append-only — rounds anteriores NUNCA editados. Skill `tela-smoke-pos-merge` (Tier B) auto-cria próximo round após merge que toque a tela.

---

## Round 1 — 2026-05-17 (criação estática — W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Escopo análise:** estática. Smoke browser MCP pendente.

**Observações estáticas:**
- ✓ AppShellV2 (Cockpit ADR 0039) + breadcrumb `Essentials › Mensagens`
- ⚠ Charter AUSENTE (`./Index.charter.md` não existe) — **recomenda-se criar** (skill `mwart-process` ADR 0104); pattern Inbox merece spec formal
- ✅ Controller `EssentialsMessageController@index` usa `Inertia::defer` **3×** — boas práticas (lista + threads + counts)
- ⚠ Tela grande (292 linhas) — Inbox pattern, verificar partial reload (lição D-14 Wagner 2026-05-15)
- ✅ 2 ocorrências `useMemo` — uso correto pra lista de mensagens
- ⚠ Sem `localStorage` prefix `oimpresso.messages.*` — conversa selecionada/filtros não persistem (Inbox crítico — switch conversa pode parecer "carregando página inteira")
- ✓ Sem `bg-*-N` crus problemáticos

**Riscos identificados (sem smoke):**
- 🟢 defer usado → first_paint provavelmente ok
- 🟡 P1: pattern Inbox sem charter — risco de divergir do blueprint Inbox canônico (omnichannel)
- 🟡 P1: localStorage prefix ausente

**Pest GUARD recomendado próximo round:**
- Aderência `Inertia::defer` permanece (NÃO regredir — lição D-14 partial reload)
- Cross-tenant biz=1 vs biz=99 (CRÍTICO — mensagens contém PII)
- Threads scopadas business_id

**Decisão Wagner:** [pendente smoke browser MCP]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` (Tier B) auto-cria Round 2 após próximo merge que toque `Messages/Index.tsx` ou `EssentialsMessageController@index`.
