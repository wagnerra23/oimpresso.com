---
sessao: "01"
titulo: ADR 0395 — merge + flip do required — recibo
autor: "[CC]"
data: "2026-09-24"
base: "wagnerra23/oimpresso.com@main 4807395dc"
---
# _saida-01 · ADR 0395 — merge + flip do required

## Achado que muda o escopo: 3 dos 4 passos da ADR JÁ estavam feitos
A thread pedia *merge → flip → protection-drift → update-branch*. Medido em 2026-09-24 sobre `origin/main` 4807395dc:

| passo da ADR | estado | recibo |
|---|---|---|
| 1. merge do PR da ADR (workflow always-run + baseline) | ✅ feito | [#7132](https://github.com/wagnerra23/oimpresso.com/pull/7132) (`9d1fa67ac`). `pageheader-gate.yml` sem `paths:` e com `synchronize` no `types:`; context presente em `governance/required-checks-baseline.json` |
| 2. flip do vivo (`required_status_checks/contexts`) | ✅ feito | `gh api .../branches/main/protection/required_status_checks --jq '.contexts[]'` lista `PageHeader · ratchet (header antigo só decresce)` (posição 45). **Quando** foi aplicado não medi — nenhum handoff registra o ato |
| 3. validar com `protection-drift.mjs` | ✅ verde | `baseline: 47 required (46 classic + 1 ruleset) · vivo: 47` · `🟢 nenhum required demovido` · rc=0 — string-exata, logo sem mojibake no `·`/`ó` |
| 4. `gh pr update-branch` nos PRs abertos | ✅ desnecessário | os 10 PRs abertos têm o check no head SHA: 6 `success` · 3 `queued` · 1 `cancelled` · **0 ausente · 0 failure** |

O único resíduo era o **`status: proposto`** da própria ADR — um required vivo há dias sob uma ADR que o `decisions-search` default ainda não enxerga (`scopePorStatusAtivo`).

## Evidência DR-2 (0336), re-medida
`gh api --paginate .../pageheader-gate.yml/runs?branch=main` → 1000 runs (teto da API): **726 success · 23 failure · 250 cancelled · 1 em curso**. As 23 falhas estão todas em 2026-09-05 e 2026-09-08/09 — as duas janelas dos PRs #6779 e #7040 que a ADR cita. **Zero falha no `main` desde 2026-09-09 13:31Z**, inclusive depois do #7722 (ADR 0409, que endureceu o guard: tocar tela com dívida exige migrar).

## O que foi feito
- `memory/decisions/0395-pageheader-ratchet-required-emenda-0314.md`: **só** a linha `status: proposto → aceito` (valor conferido: 366 ADRs usam `aceito`). Corpo intacto.
- `memory/decisions/_INDEX-GENERATED.md` regenerado (`adr-index-generate.mjs --check` rc=0, 417 ADRs).
- PR com label `adr-metadata-normalization`. **Merge = ratificação [W]** (R10).

## Prova (índice)
`contem` `status: aceito` em `memory/decisions/0395-pageheader-ratchet-required-emenda-0314.md` → satisfeita no branch; vira satisfeita no `main` com o merge.

## Placar
entregue 1 de 1 do que faltava (status) · os outros 3 passos já estavam no ar — conferidos, não refeitos · ausentes: 0.
