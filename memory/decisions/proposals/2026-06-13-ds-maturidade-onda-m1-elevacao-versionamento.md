---
title: "DS Maturidade — Onda M1 (elevação 61→~65) + convenção de versionamento do DS"
status: proposed
date: "2026-06-13"
decisores: [Wagner (aprova), Claude Code (autor)]
related_adrs:
  - 0209-eslint-9-flat-config
  - 0240-task-ledger-git-native-cowork-code
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
related_adrs_ui: [UI-0013]
origem: "Wagner: 'só isso que um Adversário sugeriria? então que nota obtida pelo DS? em quantos pontos deve ser analisado e quantas ondas para elevar?' → auditoria sênior 61/100 (14 dimensões, 6 ondas) → 'terminar M1 à risca' → 'você escolhe, e vai'"
prs: ["#2639 (eleva tokens oklch)", "#2641 (badge/KpiCard/EmptyState)", "#2643 (StatusBadge)", "#2644 (catraca canon-color-guard)", "#2645 (motion tokens)", "#2651 (hsl→oklch)"]
---

# DS Maturidade — Onda M1 + convenção de versionamento do DS

## Contexto

Auditoria sênior de maturidade do DS ([dossier](../../sessions/2026-06-13-auditoria-senior-maturidade-ds-oimpresso.md)) graduou o **Design System em si** (não uma tela) em **14 dimensões ponderadas** vs estado-da-arte 2026 (Linear/Stripe/Vercel/Radix/shadcn/Material 3/Polaris/Primer): **61/100** ("Established", estágio 3 de 4 Sparkbox). Roadmap de **6 ondas de MATURIDADE** (distintas das ~16-18 ondas de ADOÇÃO do `DsRollout.tsx`).

A "arma fumegante": a camada canônica **se autocontradizia** — `ui/badge.tsx` hardcodava `bg-emerald-50`, a paleta crua que a regra `ds/no-adhoc-status-text` proíbe nas Pages. Os `ds/*` do eslint ignoram `Components/ui/**` (confiam, não forçam).

## Decisão — Onda M1 ("consolidar a fundação") executada

| Sub-item | Entrega | PR | Estado |
|---|---|---|---|
| **a** | camada canônica consome o DS (badge/KpiCard/EmptyState/StatusBadge → tokens) + **catraca** `ds-canon-color-guard` (im-regressível, provada que morde) | #2641 #2643 #2644 | ✅ |
| **b** | unifica hsl→oklch nos ~13 neutros legacy (conversão exata, pixel-idêntico provado: erro 8-bit máx 0.294) | #2651 | ✅ |
| **d** | tokens de motion (`--duration-*`/`--ease-*`) + body consome | #2645 | ✅ |
| **c** | nomear camada primitiva de cor (indireção `var()`) | — | ⏭️ **pulado deliberado** |
| **e** | semver + registro de mudança do DS | este doc | ✅ |

### (c) pulado — justificativa
Indireção `--primitive-*` → `--color-*` pra ~30 tokens é cerimônia de ROI duvidoso. shadcn (a referência) usa **tokens semânticos flat**. Com tudo unificado em oklch (item b), a dor "refatorar cor vira caça em 2 formatos" já cai. Não-meta consciente — reabrir só se o DS virar multi-brand.

## Convenção de versionamento do DS (o gap D9 "sem semver/changelog")

**Não** um `CHANGELOG.md` manual — apodrece (lei ADR 0240: "escrito + lembrado apodrece"). Em vez disso, o versionamento do DS é **derivado + enforcado**, casando com a infra que já existe:

1. **Baseline = versão.** O estado do DS é congelado em arquivos de baseline (`.foundation-guard-baseline.json`, `config/css-size-baseline.json`, `governance/ds-ledger.json`). Toda mudança de token aparece como **delta consciente no diff** desses baselines — isso É o changelog, versionado no git, não num .md paralelo.
2. **Mudança breaking = ADR.** Renomear/remover um token semântico (ex `--color-success`) quebra consumidores → exige ADR (append-only). Mudança aditiva/de-valor (novo token, refinar oklch) anda pelos baselines + commit conventional.
3. **Âncora de versão.** O DS hoje é **"M1-elevated · 2026-06-13"** (esta proposta). Próxima âncora = próxima onda de maturidade aceita.

## Placar M1 (estimativa)

| Dim | Antes | Depois | Como |
|---|---|---|---|
| D1 token architecture | 68 | ~74 | hsl/oklch unificado (b) |
| D8 motion | 20 | ~55 | vocabulário + body consome (d) |
| D10 anti-regressão | 88 | ~90 | catraca canon-color (a) |
| D3 component API | 78 | ~80 | canon consome o próprio DS (a) |
| **Ponderada** | **61** | **~65** | |

## Próximo (fora desta proposta)

- **M2** — tokens machine-readable (DTCG/W3C + Style Dictionary). Gap D2 = 15/100, o mais baixo.
- **M3** — VRT (snapshot pixel-diff por componente). Fecha o "verde do placar não cobre o visual".
- **Adoção** (DsRollout) — agora SEGURO: o alvo (canon) parou de se contradizer e está travado. Tokenizar Cliente = a "tela linda" pedida.

## Aprovação

Wagner aceita → vira ADR canônico (próximo número) OU fica como registro de proposta. As 5 PRs de código já mergearam (verde no placar); este doc só formaliza a decisão + a convenção de versionamento.
