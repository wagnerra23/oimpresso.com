# BRIEFING — Purchase · ⚰️ REPARTIDO (KL-E2)

> ⚠️ **Pare aqui — os docs desta pasta foram repartidos** (decisão E1 · frente KL · 2026-06-15).
> - Telas de compra (UI-CATALOG + visual-comparisons) → [`Compras/_telas/`](../Compras/_telas/). Verdade viva → [`Compras/BRIEFING.md`](../Compras/BRIEFING.md).

**Tipo:** redirect/repartição (KL-E2). Pastas-alvo: ver acima.

---

> **Nota Onda 0 (2026-07-02) — repartição de compra NÃO está 100% concluída (P5 segue ADIADO).**
> Verificação contra `origin/main` **inverteu a premissa** do mapa interno: os RUNBOOKs que ainda vivem
> nesta pasta — [`RUNBOOK-create.md`](RUNBOOK-create.md) (`last_validated 2026-06-22`),
> [`RUNBOOK-index.md`](RUNBOOK-index.md) (`2026-06-17`) e [`create-visual-comparison.md`](create-visual-comparison.md)
> (`2026-06-22`) — **NÃO são "versões antigas" a descartar**: são os **mais novos/canônicos** (o `/purchases`
> real, live). As cópias em `Compras/_telas/` (`RUNBOOK-purchase-create.md`/`-edit.md`, geradas 2026-05-15) são
> **mais velhas MAS com conteúdo complementar** (tabela de Props, Persona) — logo **não dá pra sobrescrever
> nem tombar cegamente** (perderia conteúdo dos dois lados). `RUNBOOK-index.md` (`/purchases`) também **não**
> tem par em Compras: `Compras/RUNBOOK-compras-index.md` é do cockpit greenfield `/compras` (scaffold, tela
> ainda não existe) — telas diferentes.
>
> **Pendente (fica com Wagner — é a fatia ADIADA "alto custo/risco"):** um **merge** revisado dos RUNBOOKs
> de compra pra `Compras/_telas/` (mantendo o conteúdo novo + o complementar antigo, com correção de
> profundidade dos links) + trazer os `Compras/_telas/RUNBOOK-purchase-*.md` legados pro schema
> (`title`/`owner`/`last_validated`, `status` no enum) e corrigir seu `spec_ref` (hoje aponta pra
> `Inventory/SPEC.md`; o correto é `Compras/SPEC.md` US-COM-005). **Onda 0 fez só a higiene segura:**
> corrigiu os ponteiros `runbook:` mortos das visual-comparisons (apontavam pra `Inventory/RUNBOOK-*`
> inexistentes). Ver plano `memory/sessions/2026-07-02-plano-consolidacao-estoque.md` §4 Onda 0.
