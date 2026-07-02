---
date: "2026-07-02"
hour: "22:10 BRT"
topic: "P5 desadiado (Wagner) — merge Purchase→Compras concluído + Tabela B P5/P6/P7 marcadas resolvidas"
authors: [C]
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0121-oimpresso-modular-especializado-por-vertical
---

# P5 Purchase→Compras — merge concluído (2026-07-02)

## TL;DR

Wagner desadiou a fatia que a Onda 0 tinha deixado flagada ("tire o status de adiada"). Executei o **merge revisado dos RUNBOOKs de compra pra `Compras/_telas/`** — o que NÃO dava pra fazer cegamente na Onda 0 porque a premissa tinha invertido (os docs de `Purchase/` eram os **novos**, e os de `Compras/_telas/` eram velhos **mas complementares**). Merge preserva os dois lados. `Purchase/` vira só lápide. Docs-only. Tabela B P5/P6/P7 marcadas ✅.

## O que foi feito

**Merge (não sobrescrita) em `Compras/_telas/`:**
- `RUNBOOK-purchase-create.md` — funde o operacional novo (dual-path + modo grade US-COM-005 + endpoint grade-matrix + etiquetas + smoke curl, era `Purchase/RUNBOOK-create.md` 2026-06-22) **com** o contrato de referência antigo (Props table, Layout F3, Validação, fases MWART F2-F5). Frontmatter trazido pro schema (title/owner/last_validated/status enum), `spec_ref` → `Compras/SPEC.md`, links com profundidade corrigida (`../../../decisions/`, `../SPEC.md`).
- `RUNBOOK-purchase-index.md` — **novo** (o `/purchases` real não tinha par em Compras; `RUNBOOK-compras-index.md` é do cockpit greenfield `/compras`, scaffold). Do `Purchase/RUNBOOK-index.md`, links corrigidos.
- `RUNBOOK-purchase-edit.md` — frontmatter legado trazido pro schema + `spec_ref` corrigido (`Inventory/SPEC.md` → `Compras/SPEC.md`).
- `purchase-create-visual-comparison.md` — incorporou o gate visual do **modo grade** (era `Purchase/create-visual-comparison.md`) como seção adicional; a comparação base do form permanece.

**Removidos** (`git rm`, conteúdo preservado no merge): `Purchase/RUNBOOK-create.md`, `Purchase/RUNBOOK-index.md`, `Purchase/create-visual-comparison.md`. `Purchase/BRIEFING.md` atualizado (lápide "P5 CONCLUÍDA").

**Governança:** `_TRIAGEM-IDENTIDADE-2026-06.md` — Tabela B **P5** ✅ feito, **P6** ✅ (Produto já é porta; Inventory não tinha doc de produto residual), **P7** ✅ parcial-por-design (StockAdjustment/StockTransfer já stubs; `Inventory/SPEC.md` **permanece** em `Inventory/` por restrição do anchor-lint — só varre `<Mod>/SPEC.md` — mas reframado como roadmap do Estoque em #3677). Linha "ADIADO cluster Estoque" riscada/DESADIADO.

## Por que merge e não sobrescrita/tombar

Verificação contra `origin/main`: `Purchase/RUNBOOK-create.md` (2026-06-22) e o de `Compras/_telas/` (2026-05-15) tinham conteúdo **complementar** — o novo trazia dual-path/grade/etiquetas/smoke; o antigo trazia Props table + Layout + Validação + fases MWART. Sobrescrever perderia o antigo; tombar o novo perderia o novo. Merge é o único caminho sem perda.

## Constraint honesto (não é bug, é design)

`Inventory/SPEC.md` **não** foi movido pra `Estoque/` apesar do P7 dizer "consolidar em Estoque": o `anchor-lint.mjs` só conta âncoras em `memory/requisitos/<Mod>/SPEC.md`; mover perderia as 25 âncoras `_pendente_`. Resolução: o SPEC fica fisicamente em `Inventory/` mas é **conceitualmente** o roadmap de evolução do Estoque (reframe #3677) — Estoque é dono via DOC-RAIZ.

## Pendente (não neste PR)

- **Comentário stale** em `resources/js/Pages/Purchase/Create.tsx:9` (`// Runbook: memory/requisitos/Purchase/RUNBOOK-create.md`) aponta pro caminho antigo — trivial, mas é `.tsx` (dispara hooks de Page/UI-smoke), fica pra um ajuste à parte pra não misturar código nesta PR docs-only.
- **Onda 1** (código `stock_movements` + custo médio ponderado móvel) — Tier 0 valor/estoque; precisa da decisão do método de valoração (§6 do plano) + tabela de impacto antes de migration. Não é docs.

## Pointers

- Onda 0 (base): [session log](2026-07-02-onda0-consolidacao-estoque.md) + PRs #3677/#3678/#3679.
- Plano-mãe: [plano](2026-07-02-plano-consolidacao-estoque.md) §4 Onda 0 item 3.
