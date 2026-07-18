---
date: "2026-07-18"
hour: "20:37"
topic: "C-F3 fechado por evidência (lápide §5) + DR-2a bite-log dos gates de design construído"
authors: [C]
prs: [4511, 4522]
related_adrs: [0336-gates-design-promocao-por-mordida-provada-emenda-0314, 0314-poda-gates-onda-2-lei-fusoes]
outcomes: ["Lápide §5 fecha C-F3: promover component-registry a required é beco sem saída (0 mordidas medido, canon congelado)", "DR-2a construída: bite-log dos gates de design (recorder + ledger + self-test + coleta via ZELADOR)"]
---

# C-F3 (lápide §5) + DR-2a bite-log

## TL;DR
Fechei o chip **C-F3** medindo em vez de presumir: promover `component-registry-check` a required é **beco sem saída** — recibo git mostra **0 mordidas** (desde #3215 nenhum dos 32 arquivos referenciados mudou; canon congelado), a 0336 DR-2 pede ≥2, e o evento que pegaria já cai no `Vite build` required. [W] escolheu **manter advisory** → lápide §5 ([#4511](https://github.com/wagnerra23/oimpresso.com/pull/4511)). Depois [W] mandou **"faça"** o desbloqueio real: construí a **DR-2a** que a 0336 deixou pendente — o **bite-log dos gates de design** ([#4522](https://github.com/wagnerra23/oimpresso.com/pull/4522)): `design-gate-bites.mjs` (`--scan`/`--tally`/`--selftest`) + ledger append-only (`memory/governance/design-gate-bites.jsonl`, nasce vazio pois os 5 gates estão verdes no main) + job CI advisory + coleta via ZELADOR. 2 PRs merged, 0 required-fail.

## Como foi (narrativa)
1. **Veredito adversarial sobre o próprio chip.** Verifiquei o mecanismo (`component-registry-check --check` determinístico / `--roles` heurístico), o enforcement (advisory desde a fusão F2/0314) e a lei (0336 DR-2 = ≥2 mordidas reais). Medi na história: `component-registry.json` tem 2 commits, e **nenhum** dos 32 arquivos referenciados mudou desde o nascimento → 0 mordidas, estrutural (canon congelado). O bite-log (DR-2a) nem existia. Conclusão: **não promover**.
2. **Adversário sobre mim.** Corrigi 4 afirmações imprecisas minhas (dei "0 mordidas" por inferência antes de medir; rodei check no worktree stale) e reforcei 2 pontos (overclaim de eixo componente≠tela; `--roles` inelegível). Conclusão sobreviveu, reforçada.
3. **[W] "1" (manter advisory)** → PR #4511: lápide §5 em `proibicoes.md`.
4. **[W] "faça"** o bite-log → PR #4522: **DR-2a**. Recorder config-driven (5 gates escaneáveis; `ds-tokens-build-sync` fora por precisar `npm run tokens:build`), dedup por assinatura (violação persistente não infla), self-test hermético (7 asserções: morde/libera/crash≠mordida/dedup/sig-novo/tally). Coleta reusa o **ZELADOR** (sessão diária que já abre PRs) em vez de um workflow novo com secret.

## Verificações (recibos)
- `--selftest` 7/7 local e **verde no CI Linux** (job `bite-log`, 15s).
- 5 gates confirmados executando, **todos verdes no main** (pt-conformance 128 OK, design-coverage 183≥93, ds-token-version v1.0.0…) → ledger vazio é honesto.
- Diffs verificados contra `origin/main` (o resumo "8 files/4 del" do `gh pr merge` era enganoso; o real era 4 files/+297/0 del).

## Anti-padrões evitados
- Não promovi gate não-Tier-0 a required sem evidência (lápide `foundation-ratchet` §5 2026-07-01 / 0336 DR-3 "teatro ao quadrado").
- Não escrevi emenda-0314 per-gate (a 0336 já generalizou a classe — duplicaria régua).
- Não construí workflow-secret paralelo pra coleta (reusei o ZELADOR).
