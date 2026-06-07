---
slug: 0255-contrato-view-deterministico-charter-design-spec
number: 255
title: "Contrato de view determinístico: charter (intenção) + design-spec.json derivado (estrutura) — consolida os 4 artefatos por-tela"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
proposed_at: "2026-06-06"
module: _DesignSystem
quarter: 2026-Q2
related_adrs:
  - 0239-governanca-design-system-git-ssot-regressao-ia
  - 0253-primitivos-layout
  - 0114-prototipo-ui-cowork-loop-formalizado
tags: [design-system, view-contract, design-spec, charter, derivado, deterministico, consolidacao]
---

# ADR 0255 — Contrato de view determinístico: charter + design-spec.json derivado

> **PROPOSTA — Wagner aprova pra aceitar (Tier 0: muda padrão de artefato canônico por-tela).**
> Origem: 2026-06-06, Wagner — *"esse protocolo deve ser o padrão spec da view, junto do page charter? assim consigo fazer testes? pesquise, consolide e rebaixe os conflitos — isso é evolução determinística."* Dossiê: [2026-06-06-arte-view-contract-deterministico](../sessions/2026-06-06-arte-view-contract-deterministico.md).

## Contexto

Hoje existem **4 artefatos por-tela**, todos **prosa, julgados por LLM**, sobrepostos:

| Artefato | Qtd | Cobre | Determinístico? |
|---|---|---|---|
| `.charter.md` | 146 | intenção (Mission/Goals/UX) | não (LLM-judge — ok pra intenção) |
| `<tela>-visual-comparison.md` | 68 | visual (15 dim) | não |
| `<Tela>.review.md` | 157 | design review | não |
| screen-grade scorecard | 222 | nota QA (16 dim) | não (LLM-as-judge) |

**O determinismo está no lado errado:** a estrutura de UI por-tela (componentes compostos, tokens usados, layout) é **pura e DERIVÁVEL** — mas é julgada por LLM, enquanto só os gates **globais** (foundation-guard, conformance, reuse-index, ui-lint) são determinísticos. Falta a **projeção por-tela** desses gates.

Estado-da-arte 2026 (DTCG 2025.10, Figma Code Connect, Storybook MCP, spec-anchored testing) converge: **código = SSOT; o contrato testável é DERIVADO**, não escrito à mão (que apodrece — [ADR 0239](0239-governanca-design-system-git-ssot-regressao-ia.md)). A infra de derivação **já existe** (`reuse-index.mjs` extrai imports/símbolos; `foundation-guard.mjs` tem ratchet só-desce).

## Decisão

**O contrato de view = DOIS artefatos canônicos por-tela:**

1. **`<Tela>.charter.md` (MANTÉM)** — contrato de **intenção** (Mission/Goals/Non-Goals/UX targets/Anti-hooks). LLM-judge é apropriado: intenção é subjetiva.
2. **`<Tela>.design-spec.json` (NOVO, DERIVADO)** — contrato de **estrutura**: shell + componentes (ui/shared/layout/local) + violações estruturais (oklch cru, px hardcoded, select/input nativo, inline-style). Gerado por `scripts/design-spec-gen.mjs` a partir da `.tsx`, com `measured_against_sha`. **Machine-checkable → teste determinístico por-tela**, não LLM-judge.

**Resolução de conflitos (rebaixar):**
- **`visual-comparison.md`** → vira **view derivada** do design-spec (não mais escrito à mão).
- **`review.md` (157)** → **REBAIXA a gerado-on-demand** (mata 157 `.md` que apodrecem; gera quando precisa, não persiste).
- **scorecard** → **CONSOME** o veredito determinístico do design-spec nas dimensões estruturais; LLM só nas subjetivas.

**Teste:** gate por-tela re-deriva o spec + compara com o baseline commitado (drift estrutural → falha; ratchet só-desce nas violações). Espelha `foundation-guard`/`reuse-gate`.

**Relação com o bundle Claude Design** ([PROTOCOL.md §10.5](../../prototipo-ui/PROTOCOL.md)): o bundle, quando chegar (F-C reativo, formato não publicado), vira o **ALVO** que o design-spec é testado contra — não o spec agora.

## Consequências

- **(+)** Estrutura de UI por-tela vira **deterministicamente testável** (não LLM-judge) — drift estrutural pego no CI.
- **(+)** Fragmentação **4 → 2** artefatos; mata 157 `review.md` que apodrecem.
- **(+)** Reusa infra existente (reuse-index + foundation-guard) — não é fundação nova, é projeção.
- **(+)** Derivado → não apodrece (ADR 0239).
- **(−)** Migrar 146 telas — mas **incremental e gerado** (custo baixo: derivado, não escrito).
- **(−)** v1 do gerador cobre composição + violações; **tokens-usados via classes Tailwind** (`bg-primary`, `gap-4`) = fase 2.
- **Tier 0:** muda padrão de artefato canônico → Wagner aprova esta ADR antes de migrar/rebaixar.

## Prova (PoC, 2026-06-06)

`Sells/Create.design-spec.json` derivado: 11 componentes canon (Button/Card/Input…) + 2 shared + 5 locais + **0 oklch cru** + 6 px hardcoded + 1 input nativo. Contrato estrutural real, machine-checkable, gerado em <1s. Ver `resources/js/Pages/Sells/Create.design-spec.json` + `scripts/design-spec-gen.mjs`.

## Roadmap (impacto×esforço)

1. **PoC** (feito) — gerador + Sells/Create.
2. **ADR aceita** (Wagner) → padrão canônico.
3. **Gate por-tela** `design-spec:check` (ratchet) + freshness — espelha os existentes.
4. **Migração incremental** — toda tela tocada ganha seu design-spec (gerado); rebaixa review.md no caminho.
5. **Fase 2** — tokens-usados (classes Tailwind) no spec; consolidar scorecard.
