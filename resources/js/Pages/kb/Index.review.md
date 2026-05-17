# Review estática — `resources/js/Pages/kb/Index.tsx`

**Round:** W31-R1 · **Data:** 2026-05-17 · **Modo:** análise estática
**Charter:** `Index.charter.md` (presente)
**RUNBOOK MWART:** não confirmado (verificar `memory/requisitos/KB/RUNBOOK-index.md`)

## Resumo

KB browser V3 — lista paginada + preview panel split (12 cols 5/7) com markdown enriquecido (`react-markdown` + `remark-gfm`). Histórico: portado de `Copiloto/Admin/Memoria/Index.tsx` → `/kb` (Etapa 2 modularização 2026-05-03). Bate em `/kb/{slug}/show` (JSON), `/kb/{slug}` DELETE (soft), `/kb/{slug}/restore` POST. Permissão original `copiloto.mcp.memory.manage` (TODO rename `kb.manage`).

## Pontos fortes

- Keyboard nav completo: `/` foca busca, `j/k` navega lista, `Enter` abre, `Esc` fecha preview — guard `isTyping` consistente
- Debounce 350ms search (line 159-164) — UX padrão do projeto
- `localStorage` preserva estado do preview panel aberto/fechado (PANEL_STORAGE_KEY line 135)
- Scroll-to-top do preview em troca de doc (line 167-171) — detalhe que evita confusão
- Soft-delete LGPD com confirmação textual `CONFIRMO` (linha 691) — fricção apropriada pra ação destrutiva
- Badges informativos: `scope_required` (Spatie), `admin_only`, `pii_redactions_count`, `deleted_at`, módulo
- KPIs no header (4 cols): total, com_pii, tipos, ultimo_sync — coerente com KpiGrid canon
- Restore handler reabre preview se for o slug atual (line 312) — UX cuidadosa
- External links com `target="_blank"` + `rel="noopener noreferrer"` (line 543) — segurança correta

## Riscos / gaps

1. **R-A (P0) — `dangerouslySetInnerHTML` em paginator labels** (line 420). Vem de `docs.links[].label` (Laravel paginator) — geralmente texto HTML escapado, mas se Controller injetar HTML user-controlled vira XSS. Confirmar fonte sanitizada server-side.
2. **R-B (P1) — `Inertia::defer` não usado.** Página recebe `docs: Paginator<DocRow>` síncrono — ADR Tier 0 defer (RUNBOOK-inertia-defer-pattern) exige paginate em defer closure. First render carrega N rows de banco antes de TTI.
3. **R-C (P1) — `fetch('/kb/{slug}/show')` direto sem AbortController.** Trocar doc rápido (j/k) dispara N requests simultâneos sem cancelamento; último a responder ganha (race condition pode mostrar conteúdo errado).
4. **R-D (P2) — `useEffect` keyboard handler depende de `docs.data`, `selectedSlug`, `previewOpen`** mas lint suppression (line 219 `// eslint-disable-next-line`) — re-registra listener em toda mudança; aceitável mas frágil.
5. **R-E (P2) — Permissão legada `copiloto.mcp.memory.manage`** ainda usada (line 7) — TODO rename `kb.manage` em PR separado; meanwhile rota deve checar permissão server-side.
6. **R-F (P3) — Acessibilidade ícones emoji** (`📋`, `✕`, `🗑️`, `♻️`, `📂`, `📜`, `🔒`, `⚠️`). Aria-label ausente nos botões (titles ok). Screenreader recita emoji literal.
7. **R-G (P3) — `kbd` shortcuts visíveis só >md** (line 332 `hidden md:flex`) — mobile não descobre `j/k/Esc/`/`.

## Score parcial

| Eixo | Nota |
|---|---|
| Charter presente | OK |
| MWART F3 padrão | OK |
| Inertia::defer | NOK |
| XSS hazard | REVISAR (paginator labels) |
| A11y (keyboard nav) | OK |
| A11y (aria-label) | NOK parcial |
| PT-BR | OK |

**Recomendação Round 2:** auditar Controller `KbController@index/show/destroy/restore` (defer + scope + permission).
