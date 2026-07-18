---
date: "2026-07-18"
time: "20:37 BRT"
slug: cf3-lapide-e-dr2a-bite-log
tldr: "Chip C-F3 fechado por evidência: medi que promover component-registry-check a required é beco sem saída (0 mordidas, canon congelado, redundante com Vite build) → lápide §5 (#4511). Depois construí a DR-2a que a 0336 deixou pendente: o bite-log dos gates de design (#4522) — recorder + ledger + self-test + coleta via ZELADOR. 2 PRs merged, 0 required-fail."
decided_by: [W]
prs: [4511, 4522]
related_adrs: [0336-gates-design-promocao-por-mordida-provada-emenda-0314, 0314-poda-gates-onda-2-lei-fusoes, 0327-anchor-content-required-emenda-0314]
next_steps: ["ZELADOR roda design-gate-bites.mjs --scan no PR diário (coleta forward)", "quando --tally mostrar gate com >=2 PRs distintos, escalar promoção DR-3 com draft de emenda a 0314", "opcional: investigar qual módulo regrediu no advisory module-grades-gate (pré-existente, vermelho em todo PR)"]
---

# C-F3 fechado (lápide §5) + DR-2a bite-log construído

## Estado MCP no momento do fechamento
- **cycles-active:** nenhum cycle ativo em COPI (off-cycle).
- **my-work (@wagner):** 30 tasks (10 review, 8 blocked, 12 todo) — NENHUMA é do bite-log/C-F3 (foi governança direta, não task MCP).
- **decisions-search "bite-log DR-2a":** 0336 (mãe), 0339 (desvio soberano 3 gates), 0271/0314 (poda). Confirmam que a DR-2a era "PR próprio pós-aceite" nunca feito.
- **handoffs irmãos:** último = `2026-07-18-0024-dtcg-208-fieis-para-ponteiro.md`.

## O que aconteceu
Sessão começou no **chip C-F3** (grade design→código 2026-07-17): "promover `component-registry-check` a required — falta pouco". **Medi em vez de presumir:**
- **Veredito (adversário sobre mim mesmo):** promover é **beco sem saída**. Recibo git: desde que o registry nasceu (#3215), **nenhum dos 32 arquivos referenciados mudou** (canon congelado `@/Components/ui/*` + layout) → **0 mordidas**, e a 0336 **DR-2** exige ≥2. Agravantes: o evento raro que pegaria já derruba o required `Frontend / Vite build`; `--roles` é heurístico (nunca required); "emenda-0314 per-gate" duplica a 0336 (que já generalizou a classe); o chip confundia **eixo-componente** com **eixo-tela** (deconfliturado no #4021/ADR 0324).
- Corrigi 4 imprecisões minhas honestamente (afirmei "0 mordidas" antes de medir; rodei o check no worktree stale — re-verifiquei contra origin/main).
- **[W] escolheu "manter advisory"** → **PR #4511**: lápide na `proibicoes.md` §5.
- Depois **[W]: "faça"** o bite-log (o desbloqueio real que sobrou) → **PR #4522**: construí a **DR-2a**.

## Artefatos gerados (todos MERGED em main)
| PR | Arquivo | O que |
|---|---|---|
| [#4511](https://github.com/wagnerra23/oimpresso.com/pull/4511) | `memory/proibicoes.md` §5 (+5) | Lápide: não re-propor component-registry/`--roles` a required sem ≥2 mordidas; não confundir eixo-componente/tela |
| [#4522](https://github.com/wagnerra23/oimpresso.com/pull/4522) | `scripts/governance/design-gate-bites.mjs` (269) | Recorder: `--scan` (5 gates, exit≠0=mordida, dedup por `sig`), `--tally` (PRs distintos, ≥2=candidato DR-2), `--selftest` (7/7) |
| #4522 | `memory/governance/design-gate-bites.jsonl` (1) | Ledger append-only — **nasce vazio** (5 gates verdes no main = 0 violação de design mergeou) |
| #4522 | `.github/workflows/design-memory-gate.yml` (+26) | Job `bite-log` advisory (self-test HARD + tally) |
| #4522 | `scripts/governance/ZELADOR.md` (+1) | Coleta: ZELADOR roda `--scan` no PR diário |

## Persistência
- **git:** ambos PRs merged em `main` (`fc5e94d`, `15ca7279`). Diffs verificados contra `origin/main` (não confiei no resumo do gh).
- **MCP:** sem task associada (governança direta); este handoff + session log propagam via webhook GitHub→MCP (~2min).
- **CI:** 0 required-fail nos dois. Único vermelho = `module-grades-gate` (advisory, pré-existente, "1 módulo regrediu vs baseline" — não meu).

## Próximos passos pra retomar
`gh pr view 4522` + `node scripts/governance/design-gate-bites.mjs --tally` — o placar de mordidas por gate. Enquanto vazio, todos os gates de design seguem advisory **por dado**. Quando um gate morder ≥2 PRs distintos, o ZELADOR escala a promoção DR-3.

## Lições catalogadas
- **Recibo, não afirmação** (§5 2026-07-17): afirmei "0 mordidas" por inferência antes de medir; o adversário pegou — só vale com `git log` contado.
- **Base stale × validação de canon:** rodei o live-check no worktree stale contra o guard; corrigi re-verificando via `origin/main` (interseção zero).
- **Fronteira honesta declarada:** `ds-tokens-build-sync` fora do scan (precisa `npm run tokens:build`); ledger vazio é o estado honesto (main limpo), não "faltou medir".
- **Reuso do dono > workflow novo:** coleta via ZELADOR em vez de um workflow com `COWORK_BOT_PAT`+auto-merge (§5 "não duplicar dono"; `enforce_admins` rejeita push direto).

## Pointers detalhados (on-demand)
- [ADR 0336](../decisions/0336-gates-design-promocao-por-mordida-provada-emenda-0314.md) DR-2/DR-2a/DR-3 · [ADR 0314](../decisions/0314-poda-gates-onda-2-lei-fusoes.md) required=só Tier-0 · [ADR 0327](../decisions/0327-anchor-content-required-emenda-0314.md) precedente de exceção
- `proibicoes.md` §5 (lápide) · `scripts/governance/design-gate-bites.mjs` (header explica tudo)
