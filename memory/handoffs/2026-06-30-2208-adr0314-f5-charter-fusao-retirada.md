---
date: "2026-06-30"
time: "22:08 BRT"
slug: adr0314-f5-charter-fusao-retirada
tldr: "ADR 0314 F5 (charter): avaliei a fusão charter-refs+charter-us e RETIREI — réguas ortogonais (integridade de PATHS required vs rastreabilidade related_us advisory). #3463 merged. Caso F3 RAGAS repetido."
cycle: CYCLE-08
prs: [3463, 3465]
decided_by: [W]
related_adrs: [0314-poda-gates-onda-2-lei-fusoes]
next_steps: ["D-1 LEI (mexe em required — branch protection no mesmo PR)", "fechar F1 #3456 + demo-seeder do D-3"]
---

# Handoff — ADR 0314 F5 charter-fusão RETIRADA

## Estado MCP no momento do fechamento

- **Cycle ativo:** CYCLE-08 "Receita — Onda A" (100% decorrido, 0 dias restantes). Trabalho desta sessão é **governança/poda de gates** — fora dos goals de receita do cycle.
- **my-work:** MCP timeout (servidor flaky nesta sessão — brief-fetch também caiu no SessionStart, curl exit 28).
- **PR aberto:** [#3463](https://github.com/wagnerra23/oimpresso.com/pull/3463) — aguarda merge [W].

## O que aconteceu

Wagner pré-aprovou executar o **bloco F5** da poda ADR 0314 (fundir as 2 réguas de charter `charter-refs-gate` + `charter-us-gate` → 1). Instrução crítica: ler os 2 INTEIROS primeiro e, se não forem genuinamente a mesma régua (como a F3 RAGAS retirada), **não forçar — reportar e parar**.

Li YAML + scripts + baseline + branch protection API. **Veredito: réguas ortogonais — fusão RETIRADA.**
- `charter-refs` = integridade de **PATHS** (refs resolvem on-disk?), árvore inteira vs teto numérico (`ceiling=2`, ratchet), espelha `CharterHealthChecker.php`, self-test HARD + `--fix`, **REQUIRED** (`charter_refs_broken <= teto` confirmado na API).
- `charter-us` = **rastreabilidade** (`related_us`?), diff-aware sem floor (129 legados não avermelham), CI-only, **ADVISORY**-de-nascença (`promote_by 2026-09-30`).
- Compartilham só o glob `*.charter.md`. Fundir acoplaria 1 required + 1 advisory → ou arma o advisory antes do soak (viola ADR 0275 §5 + footgun ADR 0261), ou rebaixa a catraca required. Caso F3 RAGAS repetido.

Wagner escolheu **(a)**: anotar a retirada no doc da proposta, espelhando a F3.

## Artefatos gerados

- `memory/decisions/proposals/0314-poda-gates-onda-2-lei-fusoes.md` (+8/−6): bloco F5 marcado `[~~ ~~] ❌ FUSÃO-CHARTER RETIRADA` + Consequências/Métricas/Adversário/Ratificação/Log atualizados (F5 não funde nada, 6→6).

## Persistência

- **git:** PR #3463 (branch `claude/adr0314-f5-charter-retirada`), CI 62 pass / 2 skipping. **NÃO mergeado** (R10).
- **MCP:** propaga via webhook após merge.
- **BRIEFING:** n/a (governança, não módulo).

## Próximos passos pra retomar

1. Wagner mergeia [#3463](https://github.com/wagnerra23/oimpresso.com/pull/3463).
2. Poda ADR 0314 restante: **D-1 LEI** (mexe em required — branch protection atômico no mesmo PR), fechar **F1** ([#3456](https://github.com/wagnerra23/oimpresso.com/pull/3456)), e o **demo-seeder** acoplado do D-3.

## Lições catalogadas

- **"Mesmo arquivo-alvo ≠ mesma régua"** — 2 gates sobre `*.charter.md` mediam coisas ortogonais (integridade de path vs rastreabilidade de US). Aplicar o adversário a si mesmo (lição F3 RAGAS) pegou a fusão errada antes de codar.
- **Fundir required + advisory corrompe postura de risco** em qualquer direção — não fazer sem que ambos estejam na mesma classe.

## Pointers detalhados

- ADR: `memory/decisions/proposals/0314-poda-gates-onda-2-lei-fusoes.md` (bloco F5 + §Log de execução)
- Gates lidos: `.github/workflows/charter-refs-gate.yml`, `charter-us-gate.yml` + scripts `scripts/governance/charter-refs.mjs`, `charter-us-lint.mjs`
