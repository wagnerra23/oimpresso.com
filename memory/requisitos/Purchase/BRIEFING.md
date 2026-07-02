# BRIEFING — Purchase · ⚰️ REPARTIDO (KL-E2)

> ⚠️ **Pare aqui — os docs desta pasta foram repartidos** (decisão E1 · frente KL · 2026-06-15).
> - Telas de compra (UI-CATALOG + visual-comparisons) → [`Compras/_telas/`](../Compras/_telas/). Verdade viva → [`Compras/BRIEFING.md`](../Compras/BRIEFING.md).

**Tipo:** redirect/repartição (KL-E2). Pastas-alvo: ver acima.

---

> **P5 CONCLUÍDA (2026-07-02) — repartição de compra 100% em `Compras/_telas/`.** Wagner desadiou a fatia.
> Os RUNBOOKs de compra viviam **duplicados/divergentes** entre esta pasta e `Compras/_telas/`. Resolvido por
> **merge** (não sobrescrita cega — os dois lados tinham conteúdo complementar):
> - [`Compras/_telas/RUNBOOK-purchase-create.md`](../Compras/_telas/RUNBOOK-purchase-create.md) — funde o operacional novo (dual-path + modo grade + etiquetas + smoke, era `Purchase/RUNBOOK-create.md` 2026-06-22) **com** o contrato de referência antigo (Props/Layout/Validação/fases MWART). Frontmatter compliant + `spec_ref` → `Compras/SPEC.md`.
> - [`Compras/_telas/RUNBOOK-purchase-index.md`](../Compras/_telas/RUNBOOK-purchase-index.md) — **novo**, do `/purchases` real (era `Purchase/RUNBOOK-index.md`; ≠ do cockpit greenfield `/compras`).
> - [`Compras/_telas/RUNBOOK-purchase-edit.md`](../Compras/_telas/RUNBOOK-purchase-edit.md) — frontmatter trazido pro schema + `spec_ref` corrigido.
> - [`Compras/_telas/purchase-create-visual-comparison.md`](../Compras/_telas/purchase-create-visual-comparison.md) — o gate visual do **modo grade** (US-COM-005) foi incorporado.
>
> Os 3 residuais desta pasta (`RUNBOOK-create.md`, `RUNBOOK-index.md`, `create-visual-comparison.md`) foram **removidos** (conteúdo preservado no merge). Pasta Purchase agora só carrega esta lápide. Verdade viva → [`Compras/BRIEFING.md`](../Compras/BRIEFING.md). Ver session log `memory/sessions/2026-07-02-p5-purchase-compras-merge.md`.
