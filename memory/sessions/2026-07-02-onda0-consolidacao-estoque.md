---
date: "2026-07-02"
hour: "21:39 BRT"
topic: "Onda 0 — consolidação documental do domínio Estoque (dedup SPEC Inventory, 3 drifts de canon, link-rot da repartição)"
authors: [C]
prs: [3677, 3678, 3679]
related_adrs:
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0121-oimpresso-modular-especializado-por-vertical
---

# Onda 0 do Estoque — consolidação documental (2026-07-02)

## TL;DR

Executada a **Onda 0** do [plano de consolidação do Estoque](2026-07-02-plano-consolidacao-estoque.md) — **docs-only, zero código de produto**, tudo verificado contra `origin/main` (worktree fresca; checkout local estava −4634). Entregou **3 PRs**: dedup do SPEC Inventory duplicado, correção de 3 drifts de canon, e limpeza do link-rot da repartição. Duas descobertas mudaram a execução (abaixo). **Não mergeado** (R10 — Wagner aprova). Gates change-relevant verdes nos 3.

## O que foi feito

### PR1 — [#3677](https://github.com/wagnerra23/oimpresso.com/pull/3677) · dedup SPEC Inventory
As duas cópias do SPEC divergiram: `Inventory/SPEC.md` recebeu as **25 âncoras `**Implementado em:** _pendente_`** ([#3654](https://github.com/wagnerra23/oimpresso.com/pull/3654)); a cópia `Estoque/_telas/SPEC-inventory-cross-vertical.md` ficou com **0**.
- **Descoberta decisiva:** o `anchor-lint.mjs` só varre `memory/requisitos/<Mod>/SPEC.md` (regex na seleção de SPECs). Portar as 25 âncoras pra `_telas/` as tornaria **invisíveis** (`sem_campo`/cobertura 0). Logo o sobrevivente **tinha** que ser `Inventory/SPEC.md`; a cópia `_telas/` foi tombada com lápide.
- Frontmatter migrado pra v1.0. Reframe conceitual: SPEC = **roadmap de evolução do Estoque** (não domínio paralelo — evita a regressão "roadmap paralelo" das proibições). DOC-RAIZ segue a fonte da verdade do saldo hoje.

### PR2 — [#3678](https://github.com/wagnerra23/oimpresso.com/pull/3678) · 3 drifts de canon (stacked em #3677)
Verificados **contra o código real** em `origin/main`:
- **(i)** DOC-RAIZ R2 dizia que a baixa de peça da OS foi "revertida/proposto" — **falso**. `ServiceOrderItemService::baixarEstoqueConclusao()` (linha 135) é chamada pelo `ServiceOrderObserver` (bloco P0-2) no `status→concluida` — **LIVE**. Corrigidos §4, §8 (R2 vira "refino"), §9 + nota datada no topo (doc é `type: reference`). Refino real que resta: location default (hoje `orderByDesc qty_available`) + plugar `BomResolver` p/ item-kit.
- **(ii)** Status do SPEC dizia "não-iniciado em código" — **stale**. BOM Fase 1 shipou (`app/Domain/Inventory/`: `product_bom` + `BomResolver` + `ProductBomController` API + resolução nos 3 side-effects FSM). Fases 2-5 seguem PROPOSED.
- **(iii)** Reconcilia o cabeçalho vs as 25 âncoras `_pendente_`: parking honesto (ADR 0273 §1), não "nada construído" — anchoring deferido de propósito com a repartição (P7 ADIADO).

### PR3 — [#3679](https://github.com/wagnerra23/oimpresso.com/pull/3679) · link-rot da repartição
- **6 ponteiros `runbook:` mortos** em `*-visual-comparison.md` apontavam pra `Inventory/RUNBOOK-*` (inexistentes — a pasta só tem BRIEFING+SPEC). Corrigidos pro RUNBOOK real ao lado (Compras/_telas ×2 + Estoque/_telas ×4). Alvos verificados existentes. Arquivos `*-visual-comparison.md` não batem glob de schema (gate diff-aware) → edição segura.
- **Premissa INVERTIDA:** o mapa interno assumia "Purchase = versões antigas a descartar". **Falso** — `Purchase/RUNBOOK-create.md` (2026-06-22) e `-index.md` (2026-06-17) são os **mais novos/canônicos** (`/purchases` real); as cópias `Compras/_telas/` (2026-05-15) são mais velhas **mas com conteúdo complementar** (Props/Persona). `RUNBOOK-index.md` (`/purchases`) nem tem par em Compras (`RUNBOOK-compras-index.md` é do cockpit greenfield `/compras`, scaffold). Sobrescrever/tombar cegamente perderia conteúdo → **merge revisado é a fatia ADIADA "alto custo/risco"**, fica com Wagner. Nota honesta em `Purchase/BRIEFING.md`.

## Escopo respeitado

- Docs-only; **zero código de produto**. P6/P7 (repartição pesada + Estoque avançado) seguem **ADIADOS** (gated por sinal, ADR 0105). Não desadiado nada.
- Produto (P6): no-op — `Inventory/` não tem RUNBOOK de produto. Stubs StockAdjustment/StockTransfer (P7): BRIEFINGs já são lápide "REPARTIDO KL-E2" — confirmado.

## Evidência

- `anchor-lint --check` nos 3 estados: `Inventory 🟢 pendente 25 · cov 100% · exit 0`.
- BOM Fase 1 + `baixarEstoqueConclusao` confirmados no disco (2 caminhos cada).
- Gates change-relevant verdes: SPEC, Schema SPEC.md, anchor-lint ADR 0273, anchor entry/covers, Append-only, Merge-marker, Secret scan, PII scan, memory-health, Governance Gate. Module Grades Gate: 0 regressões.

## Lições

- **Verificar a premissa antes de "terminar a repartição".** O mapa dizia Purchase-velho; o disco (origin/main) dizia Purchase-novo. A instrução da tarefa ("conferir se Compras/_telas é mais nova antes de sobrescrever") foi exatamente o guard-rail que pegou isso. Sem a verificação, eu teria descartado o RUNBOOK canônico.
- **anchor-lint é path-sensível:** só `<Mod>/SPEC.md` conta. Mover/renomear SPEC = perder cobertura silenciosamente. Sobrevivente de dedup se escolhe pela regra da ferramenta, não pela "pasta certa".
- **Gate diff-aware muda o cálculo de risco:** tocar um RUNBOOK legado não-compliant o puxa pro gate. Por isso os 2 `spec_ref` errados nos `Compras/_telas/RUNBOOK-purchase-*.md` ficaram de fora (entram no merge com o passe de compliance).

## Pointers

- Plano-mãe: [2026-07-02-plano-consolidacao-estoque.md](2026-07-02-plano-consolidacao-estoque.md) §4 Onda 0 (ainda no branch da [#3672](https://github.com/wagnerra23/oimpresso.com/pull/3672), não mergeado).
- Insumos: [arte-estoque](2026-07-02-arte-estoque-inventory-smb-2026.md) + [mapa-interno](2026-07-02-mapa-interno-estoque-verticais.md).
- Fila anchor: [`_ANCHOR-REVIEW-QUEUE.md`](../requisitos/_ANCHOR-REVIEW-QUEUE.md) §1 (parking Inventory) — consistente com a dedup.
