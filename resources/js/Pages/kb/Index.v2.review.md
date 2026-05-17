# Review estática — `resources/js/Pages/kb/Index.v2.tsx`

**Round:** W31-R1 · **Data:** 2026-05-17 · **Modo:** análise estática
**Charter:** `Index.v2.charter.md` (presente, v1.0 — 2026-05-15)
**RUNBOOK MWART:** não confirmado (verificar `memory/requisitos/KB/RUNBOOK-index-v2.md`)
**Seed W30 menção:** prompt referencia `Index.v2.review.md` seed W30 (não presente no FS — primeira escrita, append-only respeitado)

## Resumo

Port tri-pane (`CategorySidebar` | `NodeList` | `NodeReader`) do protótipo Cowork `prototipo-ui/prototipos/kb/kb-page.jsx` → Inertia React 19 + TS estrito + AppShellV2 + tokens OKLCH hue 240. Roda em paralelo a `Index.tsx` V3 em `/kb/v2` enquanto Wagner aprova gate visual ADR 0114 antes do cutover. Overlays: CommandPalette (⌘K), PathsDialog, TroubleshooterDialog, HealthPanel. Hooks customizados: `useKbFavorites`, `useKbRecent`, `useKbKeyboardNav`.

## Pontos fortes

- Charter v1.0 dedicado (Index.v2.charter.md presente) — F3 MWART canônico atendido formalmente
- Decisão clara: NÃO substitui V3, roda em paralelo (line 13-14) — cutover seguro
- Fallback mock completo (`MOCK_NODES`, `MOCK_CATEGORIES`, `MOCK_PATHS`, `MOCK_TROUBLESHOOTERS`, `computeMockKpis`, `computeMockTagsTop`) — desenvolvimento independente de Agent A backend
- Capabilities granulares via `can: { write, publish_path, publish_troubleshoot, ai_ask, graph_view, favorite, comment }` (line 80) — RBAC fino na UI
- Toast feedback em todas ações mock (`voteHelpful`, `voteOutdated`, `reverify`, `attachToOS`, `summarizeAI`, `onPresent`, `onPrint`, `onHistory`) — UX honesta sobre estado pending
- Mobile-aware tri-pane via `data-mobile-view={mobileView}` (line 520) — responsive sem JS branch
- TODOs[CL] catalogados por ONDA (1/3/4/5/6) — roadmap explícito inline
- KpiGrid size="compact" + 4 KPIs (most_read/pinned/fresh_last_14d/outdated) — coerente
- Tipos consumíveis exportados pra Agent A backend (`@/Pages/kb/_lib/types`, `mockData`) — handoff documentado
- `aria-hidden` no ícone search + `aria-label` no Input (line 502) — a11y atendida no campo crítico
- Search clear button com `aria-label="Limpar busca"` (line 510)

## Riscos / gaps

1. **R-A (P0) — `usingMock = !props.nodes`** silencioso (line 73). Header já mostra "MOCK (Agent A pendente)" no description, mas falta badge visual destacada como `Graph.tsx` faz (line 232 daquele).
2. **R-B (P1) — Filtragem client-side completa em mock mode** (lines 144-197): quando `props.nodes` vier paginado do server, esta lógica ainda roda em cima do `.data` parcial — sort/tag/category filter podem ficar inconsistentes. Decisão pendente: server filtra tudo ou client refina.
3. **R-C (P1) — `Inertia::defer` regra Tier 0 não verificável.** Backend `/kb/v2` ainda não existe; quando entrar, props `nodes` (paginate), `categories`, `subcategories`, `paths`, `kpis`, `tags_top`, `pinned` são todos candidatos a defer.
4. **R-D (P2) — `pickByRef` compat antigo Cowork (`kb-{ref}-*`)** (line 256) — depende de slug pattern legacy; pode causar match errado em slugs novos ADR/session que comecem com `kb-`.
5. **R-E (P2) — `import '../../../css/kb.css'`** (line 63) — caminho relativo profundo; refactor de pasta quebra. Considerar alias `@/css/kb.css`.
6. **R-F (P2) — TODOs[CL] críticos pendentes** sem deadline: ONDA 1 (vote/reverify real), ONDA 3 (composer + versions), ONDA 4 (AI dialog), ONDA 5 (presenter/print/grafo), ONDA 6 (attach OS). Toast.info "em breve" pode frustrar usuário em prod.
7. **R-G (P3) — `mobile-view` state machine** (cats|list|reader) muda só em `openNode` (line 224) — botão back do mobile pra voltar de `reader→list` não documentado visivelmente.

## Score parcial

| Eixo | Nota |
|---|---|
| Charter presente | OK (v1.0 explícito) |
| MWART F3 documentado | OK |
| Inertia::defer | INCONCLUSIVO (backend pendente) |
| Multi-tenant Tier 0 | INCONCLUSIVO (depende Controller) |
| A11y (input/keyboard) | OK |
| PT-BR | OK |
| Fallback mock honesto | OK |

**Recomendação Round 2:** rodar quando Agent A entregar `/kb/v2` backend + screenshot gate ADR 0114 Wagner-approved.
