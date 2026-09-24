---
sessao: "02"
titulo: Arquivar pageheader-matriz-diferencas.md — recibo
autor: "[CL]"
data: "2026-09-23"
base: "wagnerra23/oimpresso.com@main 1061dbf2e"
---
# _saida-02 · Arquivar pageheader-matriz-diferencas.md

## O que foi feito
- `memory/requisitos/_DesignSystem/pageheader-matriz-diferencas.md`: frontmatter ganhou
  `status: accepted-historical` + `lifecycle: arquivado` + `superseded_by` (ADR 0189 + `PageHeader.tsx` canon v3.8)
  + `morreu_porque`, na convenção de `_DesignSystem/AUTOMATION-ROADMAP.md`. Banner de arquivamento no topo.
- Corpo **intacto** (fato datado de 2026-05-21/25 — append-only da memória).

## Medição que sustenta o motivo (2026-09-23)
- `resources/js/Components/PageHeader/PageHeader.tsx:150` → `text-[22px]` (matriz F10 dizia 16px).
- `grep -nE "fontFamily|ui-sans" PageHeader.tsx` → 0 ocorrências (matriz F13 dizia forçar `ui-sans-serif`).

## Prova (índice)
`contem` `lifecycle: arquivado` em `memory/requisitos/_DesignSystem/pageheader-matriz-diferencas.md` → satisfeita.

## Placar
entregue 1 de 1 (arquivamento com motivo + superseded_by) · ausentes: repontamento da skill por fora do prefixo.

## Resíduo — FORA do prefixo desta thread (não tocado, de propósito)
O `02-arquivar-matriz.md` pede "conferir e repontar" a skill. Conferido; **não repontado**, porque a skill não
está no `prefixo` da thread (Lei 1). Quem ainda cita a matriz como fonte viva:
- `.claude/skills/pageheader-canon/SKILL.md:554` — "Matriz diferenças — F1-F12 fixas + V1-V9 variáveis".
- `memory/requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md:100` — índice positivo (e
  `tests/Feature/Design/DesignIndexSingleSourceTest.php:79` exige a presença dela ali).
- `memory/requisitos/_DesignSystem/GUIA-SIDEBAR-V3-PASSO-A-PASSO.md:170,179,298`.
Repontar esses três (e o par índice↔teste, que andam juntos) é uma thread nova com esse prefixo — decisão de escopo [W]/[CC].
