---
slug: 0254-design-identity-grade-deterministico
number: 254
title: "Grade de identidade visual DETERMINÍSTICO — rubrica binária anti-alucinação (endurece SCREEN-GRADE §7 T1) + ratchet gate"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-06-06"
module: _DesignSystem
quarter: 2026-Q2
tags: [design-system, identidade, grade, ratchet, anti-alucinacao, screen-grade, ds-v6, governanca-ui]
supersedes: []
amends: []
superseded_by: []
related:
  - "0230-metodo-governance-scorecard"
  - "0209-eslint-baseline-ratchet"
  - "0155-rubrica-module-grade-v3"
  - "0253-primitivos-layout"
  - "0235-ds-v4-accent-roxo-universal"
pii: false
---

# ADR 0254 — Grade de identidade visual DETERMINÍSTICO (anti-alucinação)

> Origem 2026-06-06 (Wagner): *"preciso ter especificado o que auditar e como auditar — isso vai
> evitar alucinação. fazer grade. pode aplicar o método 9.75."* Operacionaliza o
> [`SCREEN-GRADE-METODO §7 T1`](../requisitos/_DesignSystem/SCREEN-GRADE-METODO.md).

## Contexto

O `PrUiJudgeAgent` (LLM gpt-4o-mini) deu **91/100 e depois 71/100 no MESMO PR #2335**, inventando
um `[critical]` de "cor hardcoded" que **não existia** (o `eslint-baseline` provou: as cores estavam
tokenizadas, delta −1). Test-retest σ=14 ≫ 3 → **falha o teste de confiabilidade T1 do próprio método**
SCREEN-GRADE, que prescreve a cura: *"se diverge muito → rubrica subjetiva → endurecer critérios binários."*

Um grade de design **vale o que vale a rubrica**. Critério subjetivo ("parece premium?") = opinião de
LLM = alucina e não reproduz. Critério **binário/medível** ("quantos `text-[Npx]` crus? grep") = a
máquina conta, σ=0, não alucina.

## Decisão ([W], 2026-06-06)

1. **Rubrica de identidade DETERMINÍSTICA** — `scripts/design-identity-grade.mjs`. 8 dimensões, cada
   uma medida por regex sobre `resources/js` (% de conformidade com o token). **Mesmo código → mesma
   nota.** Config de allowlist explícito no topo do script (auditável = o "o quê auditar" especificado).

   | Dim | Peso | O QUÊ auditar | COMO (medida) |
   |---|:---:|---|---|
   | tipografia | 3 | escala de tipo é token? | `text-(xs..3xl)` ÷ (token + `text-[Npx]`) |
   | cor | 2 | cor é token? (status allowlisted) | token semântico ÷ (token + cor-drift neutra/azul/roxo cru) |
   | espaco | 2 | espaço é token? | `gap/p-(N)` ÷ (token + `gap-0.5`/`[px]`) |
   | forma | 1 | radius é token? | `rounded-*` ÷ (token + `rounded-[px]`) |
   | movimento | 2 | há transição? | (shared @/ui c/ transition embutido + raw c/ transition) ÷ interativos |
   | foco | 2 | assinatura a11y? | (shared @/ui c/ focus-ring embutido + raw c/ ring) ÷ interativos |
   | icone | 1 | lucide-only? | 100 − densidade de emoji pictográfico no JSX |
   | layout | 2 | composto por primitivo? | `<Stack/Inline/Grid/Box>` ÷ (primitivo + flex/grid cru) |

2. **Calibração v1** (Wagner 2026-06-06): (a) **status-color allowlist** — `emerald/green/amber/yellow/
   rose/red/sky` são convenção semântica (soft pills), NÃO contam como drift; só neutros + azul/roxo
   cru contam. (b) **emoji** conta só pictográfico `\u{1F300}-\u{1FAFF}` fora de comentário, por
   densidade (não zera o projeto por 1 tela poluída).

2b. **Refino justo v1.1** (4 ondas, Wagner 2026-06-06 "refine mais 3-4 ondas"): o grade cru punia o
   **padrão BOM** (Goodhart ao contrário) — um `<Button>` shared que JÁ tem `transition-all
   focus-visible:ring` embutido contava como 0 cobertura. Ondas: (1) **Foco** credita os 1.823
   controles shared de `@/Components/ui`; (2) **Movimento** idem; (3) **Tipografia** credita o
   primitivo `<Text>`; (4) **acionável** — o grade lista os top-5 arquivos ofensores por dimensão
   (vira roadmap). Efeito: foco 13→71, movimento 24→77, **nota 51→66** (medida mais fiel da realidade,
   não generosidade — controles shared genuinamente têm a assinatura).

3. **Ratchet gate** (espelha [ADR 0209](0209-eslint-baseline-ratchet.md)): `config/design-identity-baseline.json`
   congela o estado. `--check` falha se a NOTA ou qualquer dimensão CAIR. **Só sobe.**

4. **Rollout soft→hard:** o gate nasce **soft** (reporta, não bloqueia) até a rubrica provar T1/T2/T3
   em uso. Quando Wagner ratificar os pesos → vira **blocking** + status `aceito`.

5. **Aposenta o LLM-judge nas dimensões medíveis.** `PrUiJudgeAgent` deixa de pontuar cor/tipo/foco/
   layout/espaço (a máquina faz, σ=0). O LLM fica só nas dimensões genuinamente subjetivas (estética,
   brand-feel) com **peso baixo** — exatamente o que SCREEN-GRADE §7 T1 manda.

## Baseline inaugural (2026-06-06)

**NOTA 66/100 · Developing** (pós refino justo v1.1). Piores REAIS: `layout 0` (primitivos recém-nascidos
— migração via codemod) e `tipografia 61` (2.230 `text-[px]` — o token `2xs` resolve). Foco/Movimento
saíram do vermelho ao creditar os controles shared (a cobertura já existia, o grade cru não enxergava).
Estas são as metas mensuráveis da Manual de Identidade "Clareza Confiante": 66 → 85.

## Escopo / não-decidido

- **NÃO substitui** o SCREEN-GRADE 16-dim (UX/usabilidade amplo) — é o subconjunto **identidade/craft**
  endurecido em determinístico. Complementa.
- Dimensões subjetivas (estética) **continuam** com LLM, peso baixo — fora deste grade.
- Pesos da tabela = v1; Wagner ratifica/ajusta na promoção soft→hard.

## Alternativas consideradas

- **(a) Confiar no LLM-judge.** Rejeitado: σ=14 provado, alucina (#2335).
- **(b) Grade humana manual.** Rejeitado: não reproduz, não escala, não vira gate.
- **(c) Não medir identidade.** Rejeitado: "não dá pra melhorar o que não se mede" — Wagner.

## Consequências

- ✅ Grade de identidade **reprodutível** (σ=0) — o juiz nunca mais dá 91-depois-71.
- ✅ Placar que **sobe a cada PR** (ratchet) — materialização vira número, não vibe.
- ✅ Conecta com Clareza Confiante (ADR Manual de Identidade): mede o progresso 51 → 85.
- ➖ Mede só o que é greppável — estética fina continua precisando de olho humano (por design).
- ➖ Rubrica v1 vai precisar de mais calibração (ex: per-arquétipo) conforme uso.

## Refs

- [`SCREEN-GRADE-METODO.md`](../requisitos/_DesignSystem/SCREEN-GRADE-METODO.md) §7 (T1 confiabilidade — a cura)
- [ADR 0209 — eslint-baseline ratchet](0209-eslint-baseline-ratchet.md) (padrão do gate)
- [ADR 0230 — método governance scorecard](0230-metodo-governance-scorecard.md) · [ADR 0253 — primitivos](0253-primitivos-layout.md)
- `scripts/design-identity-grade.mjs` · `config/design-identity-baseline.json`
- Origem PR #2335 (juiz 91→71) · Manual de Identidade "Clareza Confiante" (draft)
