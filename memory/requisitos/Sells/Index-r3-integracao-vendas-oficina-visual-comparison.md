---
session: 2026-05-25 Ondas 3+4 — coluna Origem + saved tree + KPI breakdown + listener
page: /sells
component: resources/js/Pages/Sells/Index.tsx
visual_source: prototipo-ui/vendas-page.jsx (cross-source pontos · INTEGRACAO_VENDAS_OFICINA.md)
canon_method: Cowork KB-9.75 + Integração Vendas × Oficina A1 (ADR 0192)
related_adrs: [0093, 0104, 0107, 0114, 0178, 0192]
charter_impact: Sells/Index.charter.md v4 → v5
---

# Visual Comparison — Sells/Index Ondas 3+4 (Integração Vendas × Oficina)

> **Escopo:** adição cirúrgica dos 4 pontos de costura cross-source (coluna Origem · stripe oficina · saved tree "Por origem" · KPI hero breakdown · listener `oimpresso:open-venda`). NÃO é rewrite. Skill mwart-comparative V4 deliverable.

## Contexto

Backend Ondas 0-2 já mergeadas (ADR 0192 + migration `source/os_ref/commission_split` + JobSheetObserver + payload `source`/`source_label`/`os_ref`). Ondas 3+4 são frontend Worker A em paralelo com Worker B (Onda 5 Repair drawer card).

## 15 dimensões (skill mwart-comparative V4)

| # | Dimensão | Cowork (canon) | Implementação Inertia | Status |
|---|---|---|---|---|
| 1 | Wrapper class | `.sells-cowork .vendas-aplus` (já existe) | mantido idêntico | ✅ paridade |
| 2 | Coluna Origem header | `<th className="vd-col-source" style={{width:138}}>Origem</th>` entre "Atendido por" e "Pipeline" | adicionado em `COL_HEADERS.source` + ordem em presets via `addSourceAfterSeller` | ✅ paridade |
| 3 | Pill `<VdSource>` | `<span className="vd-src vd-src-{id}"><span className="vd-src-dot"/><span className="vd-src-lbl">{label}</span></span>` | componente novo `VdSource.tsx` com props `{source, sourceLabel, osRef, onPickOs?}` | ✅ paridade |
| 4 | Link OS clicável | `<a className="vd-src-os" onClick={(e)=>{e.stopPropagation(); onPickOs(osRef)}}>↗ #{osRef}</a>` quando `source==='oficina'` | replicado verbatim em `VdSource` | ✅ paridade |
| 5 | Stripe linha oficina | `.os-row.vd-row-oficina` border-left azul inset 2px | render row com `className+=' vd-row-oficina'` + `data-source={v.source}` | ✅ paridade |
| 6 | Tokens CSS source | `--vd-src-{balcao,oficina,online}` + `-soft` (oklch 155/230/50) | adicionado em `sells-cowork.css` no escopo `.sells-cowork .vendas-aplus` | ✅ paridade |
| 7 | KPI hero header tag | `Faturado hoje` + `<small className="vd-kpi-tag">· todas origens</small>` quando `vista==='faturamento'` | render condicional `foco==='faturamento'` | ✅ paridade |
| 8 | KPI breakdown markup | `.vd-kpi-breakdown` grid + 3 `.vd-kpi-b.{balcao,oficina,online}` com `<small>● Balcão</small><b>R$X</b>` | replicado verbatim, render só dos sources com `total>0` | ✅ paridade |
| 9 | Breakdown só quando `foco==='faturamento'` | `vista==='faturamento' && (bySource.oficina>0||bySource.online>0)` | replicado: condicional `foco==='faturamento' && (bySource.oficina>0||bySource.online>0)` | ✅ paridade |
| 10 | Saved tree branch "Por origem" | item `origem` no SAVED_VIEWS com `expandable:'source'` + arrow `›` clicável + filhos `balcao/oficina/online` com contadores `● Label` | adicionado: branch só renderiza no dropdown (não vira SavedView base), arrow expand + filhos derivados em `rows.reduce((counts,r)=>...)` | ✅ paridade |
| 11 | Persist visão_origem localStorage | `localStorage.setItem("oimpresso.sells.visao_origem", source)` quando seleciona filho | replicado via `ls.set('visao_origem', source)` (Tier 0 per-business) | ✅ paridade + Tier 0 |
| 12 | Listener `oimpresso:open-venda` | `window.addEventListener("oimpresso:open-venda", e => { const id=e.detail.id; if (VENDAS_LIST.find(v=>v.id===id)) setOpenId(id); })` | replicado em useEffect: `e.detail.venda_id` (canonical naming) abre `setOpenSaleId` | ✅ paridade |
| 13 | Responsive break 1280 | `.vd-src-os{display:none}` esconde #OS-NNNN abaixo de 1280px | replicado em CSS | ✅ paridade |
| 14 | Responsive break 1100 | esconde coluna Origem; stripe vira `inset 2px 0 0 var(--vd-src-oficina)` no `td:first-child` | replicado em CSS | ✅ paridade |
| 15 | Visões dropdown não-quebra | branch "Por origem ▾" preserva separator + ordem das outras saved views | branch adicionado entre `Faturadas` e demais items (renderiza dropdown menu sem afetar atalho `currentSavedView.label` no botão) | ✅ paridade |

## Anti-patterns (UX charter Goals/Anti-patterns Sells)

- ✅ Sem cor crua Tailwind dentro do TSX — todos os tokens vivem em `.sells-cowork .vendas-aplus` no `sells-cowork.css`
- ✅ Sem modal/Dialog — link OS dispara `onPickOs` callback (Onda 5 wirea com listener Repair)
- ✅ Sem `font-bold` em h1 — apenas pequenos elementos
- ✅ Persiste em `localStorage` com prefix `oimpresso.sells.b<bizId>.` (Tier 0 ADR 0093)
- ✅ Charter v4 → v5 documentado (campo `visao_origem` em Goals; non-goals intactos)

## Cross-references

- Backend payload já entrega `source`/`source_label`/`os_ref` (Onda 2 commit `e98649989`)
- Worker B (Onda 5) consome callback `onPickOs` indiretamente: Repair drawer card dispara `window.dispatchEvent(new CustomEvent('oimpresso:open-venda', {detail:{venda_id}}))` e o listener Worker A escuta
- Caso `source` ausente no payload (legacy) → backend default 'balcao' (migration default), frontend nunca renderiza null

## Gate F2 (Wagner aprova)

Wagner aprovou via plano F3 PR #1497 + ADR 0192 (review_triggers documentam quando reabrir): Worker A pode prosseguir paralelo com Worker B. Sem screenshot live nesta worktree (sem servidor rodando) — gate visual depende de smoke pós-merge em prod biz=1 (canary Wagner WR2).
