---
slug: 0249-ds-v6-naming-amends-0235
number: 249
title: "DS v6 — nome canônico único da camada de tokens semânticos (resolve divergência v4×v5×v6); amends 0235 (roxo 295 permanece âncora)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-06-04"
accepted_at: "2026-06-04"
accepted_via: "Wagner 2026-06-04 — 'merge' (Opção A do draft 2026-06-04-ds-v6-naming-amends-0235): nome oficial DS v6, amends 0235, não supersede."
module: _DesignSystem
quarter: 2026-Q2
tags: [design-system, ds-v6, naming, semantic-tokens, amends-0235, gap-a, governanca-ui]
supersedes: []
amends:
  - "0235-ds-v4-accent-roxo-universal"
superseded_by: []
related:
  - 0235-ds-v4-accent-roxo-universal
  - 0244-ds-v5-canon-oficina-padrao
  - 0246-sessao-2026-05-30-ds-harmonizacao
  - 0247-ratificacao-constituicao-design
pii: false
---

# ADR 0249 — Um nome só pro Design System: **DS v6**

> Fecha o **GAP-A** do [plano de design 2026-06-04](../sessions/2026-06-04-plano-design-canon-um-estilo-grounded-main.md).
> `amends 0235` (append-only · Art. 3) — **não** supersede: o roxo `oklch(0.55 0.15 295)` continua a âncora de cor.

## Contexto

A camada de tokens semânticos (`--pos/--neg/--warn/--stage-*/--origin-*`) já está no `main`
(PRs #2128–#2200) e já é cobrada por máquina (`conformance-gate`). Faltava **nome único**.
A mesma coisa tinha 4 nomes: `INDEX` dizia "DS v4", ADR 0244 "DS v5", ADR 0246 "v4.2",
os protótipos Cowork (`prototipo-ui/ds-v6/`) "v6". Divergência de nome = a doença
"diferente = errado" no nível do próprio padrão.

## Decisão ([W], 2026-06-04 — "merge")

1. **Nome oficial = "DS v6"** — designa a camada de tokens semânticos que assenta **sobre**
   a cor roxa do ADR 0235.
2. Esta ADR **`amends 0235`**, não supersede. **Cor canon permanece** `primary` roxo
   `oklch(0.55 0.15 295)`; azul de marca = débito a migrar.
3. `INDEX-DESIGN-MEMORIAS.md` (§4 regra de ouro #2 + §5 hierarquia) passa a dizer **DS v6**,
   citando 0235 como fonte da cor e 0249 como o nome da camada.
4. Cowork e repo canon usam **um nome só: DS v6**. `DesignIndexSingleSourceTest` segue a polícia.

## Não-decidido aqui (decisão própria do [W], se/quando)
- **5 origins → 11 hues**: UI-0013 lista como lacuna ("abrir ADR se decidido"). **Não** entra
  nesta ADR — abrir ADR à parte se sim.

## Consequências
- ✅ Para a divergência de nome na fonte (repo = Cowork = "DS v6").
- ✅ Append-only respeitado (0235 intacto; este estende).
- 🔜 Lápides nos docs stale (§6) = **GAP-C**, fase VARRER (ADR separada/PR seguinte).
