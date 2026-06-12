# knowledge-ghosts-baseline/ — catraca anti-ghost (KL-A2 · Semana 0 SDD)

> Plano-mãe: [`memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md`](../../memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md) (Semana 0, frente KL — "catraca anti-ghost, baseline POR módulo").

**1 JSON por módulo citante** — anti conflito: os 6 streams de correção (codemod Classe A/B/C) nunca editam o mesmo arquivo. Cada JSON congela os nomes-fantasma (docs de `memory/requisitos/<Mod>/` citando `Modules/<X>` que NÃO existe no disco) na data do freeze.

## Regras da catraca

- `node scripts/governance/knowledge-drift.mjs --check` → **exit 1 SÓ com ghost NOVO** fora do baseline. Ghost legado (congelado aqui) passa.
- **Baseline SÓ DIMINUI.** Corrigiu o doc? Rode `--write-baseline` — rescreve como interseção (encolhe). Ghost novo **nunca é absorvido**: o write recusa (exit 1) e manda corrigir o doc ou criar o módulo.
- Módulo nunca-construído (Classe B do plano): a correção é lápide "(planejado — não existe)" no doc, não substituição inventada.
- Gate: [`.github/workflows/knowledge-ghost-gate.yml`](../../.github/workflows/knowledge-ghost-gate.yml) — **ADVISORY** (gate novo nunca nasce required).

Freeze inicial 2026-06-12: **39 módulos citantes · 27 nomes distintos** (medido em origin/main na execução, não copiado do plano — regra anti-stale).
