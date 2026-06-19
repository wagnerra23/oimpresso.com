---
date: "2026-06-15"
time: "16:14"
slug: sdd-prready-af-aplicados
tldr: "§A-F do ledger #2767 viraram 4 PRs (#2770-2773), verificados por par adversarial (pegou 2 regressões reais), CI verde; D1/D2/D4/D5 PARKED seguem"
type: handoff
prs: [2770, 2771, 2772, 2773]
authors: [W, C]
related_adrs:
  - "0273-anchor-spec-codigo-formato-canonico-fluxo-novo"
  - "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes"
  - "0276-decisao-pelo-fluxo-classes-pares-adversariais"
---

# Handoff — 6 PR-READY do ledger SDD (§A-F) aplicados como PRs

## TL;DR

> Os **6 PR-READY** do [ledger firme de conclusão SDD](../sessions/2026-06-15-sdd-conclusao-ledger-firme.md) (#2767, §A-F = F1-2/F1-3/F1-4/F1-5/F2-8/F2-9#11) viraram **4 PRs** (#2770-2773), todos off `origin/main` 3b281d864, **verificados por par adversarial** (workflow 5 agents) e **CI verde (0 fail)**. A verificação pegou **2 regressões reais** que eu não tinha visto. Números re-derivados live (anti-stale): o ledger congelou `7.9/15/63.9`, a fonte real dá `5.4/14/100`. **Anti-vazamento fechado:** §A-F saem do balde "PR-READY" → "em review". Off-cycle (CYCLE-08 = Receita).

## Estado MCP no momento do fechamento
- **Cycle CYCLE-08** (Receita — Onda A, ~13d). Esta sessão foi **off-cycle/governança** — drift conhecido (métrica-mãe é receita, não suite-verde). Nenhuma task do cycle tocada.
- my-work: 30 tasks (4 review / 6 blocked-dormente Gold / 20 todo) — inalterado por esta sessão.
- HITL Wagner: 6 pendentes (inalterado).

## O que aconteceu
`/continuar` → Wagner confirmou aplicar os 6 PR-READY. Numa worktree limpa off `origin/main` (já removida):
- **§A+§B+§C** ([#2770](https://github.com/wagnerra23/oimpresso.com/pull/2770)) — `sdd-scorecard.mjs` `measureAnchors()` delega a `anchor-lint.mjs` (fonte única, ADR 0273 §2); baseline re-armado live (`ghost 27→14`, `front_door 63.9→100`); `sdd-scorecard.json` regenerado rodando o script. Aceitação: `--ratchet` exit 0, scorecard.anchor == anchor-lint (5.4), regeneração byte-idêntica.
- **§D** ([#2771](https://github.com/wagnerra23/oimpresso.com/pull/2771)) — step `ledger-check` advisory (GT-G5) no `governance-gate-umbrella.yml`, após `test:memory-health`, `continue-on-error`, sem `--enforce`. Guard `if: pull_request` (umbrella tb roda em `workflow_dispatch`).
- **§E** ([#2772](https://github.com/wagnerra23/oimpresso.com/pull/2772)) — drift doc↔código US-GOV-018: removido dead-code `FULLSUITE_FK_OFF` + import órfão `DB` em `Tests\TestCase`; SPEC `status todo→review` + strike do FK-off (revertido em US-GOV-020 A.2).
- **§F** ([#2773](https://github.com/wagnerra23/oimpresso.com/pull/2773)) — fixtures de moeda NumUf reconstruídas (git nunca teve cópia limpa); inputs `R$ 80,00`/`R$ 2.500,80`/`R$ 80` **traçados + rodados** pelo `num_uf` real → batem.

## Verificação adversarial (workflow 5 agents) — pegou 2 regressões reais
1. **`violations.json`** (artefato do validador de schema) varrido por `git add -A` no §E → removido por amend (§E voltou a 2 arquivos).
2. **Regressão de verdade no §A:** `sdd-scorecard.mjs` passou a `execSync` o `anchor-lint.mjs`; o sandbox do `gate-selftest` (GT-G6) não copiava essa dep → 2/8 catracas quebravam. Corrigido no commit `423b7c26e` (copiar a dep + fixture BOA na gramática ADR 0273) → **8/8 catracas mordem**, confirmado verde no CI.

## Anti-vazamento — os 6 PR-READY saíram do balde
| Item ledger | § | PR | Status |
|---|---|---|---|
| F1-2 anchor fonte única | §A | #2770 | em review |
| F1-3 re-armar ratchets | §B | #2770 | em review |
| F1-4 regenerar scorecard.json | §C | #2770 | em review |
| F1-5 ledger-check advisory | §D | #2771 | em review |
| F2-8 drift US-GOV-018 | §E | #2772 | em review |
| F2-9 #11 fixtures NumUf | §F | #2773 | em review |

> Qualquer sessão futura: **NÃO re-aplicar §A-F** — estão em PR. Confirmar `gh pr view <n>` antes de tocar.

## Próximos passos pra retomar
- **Revisar/mergear os 4 PRs.** ⚠️ #2770 §A **muda a definição de uma métrica** (D3 já aprovado, mas pede olho do [W] antes do merge). Nada mergeado nesta sessão (governança + métrica = decisão Wagner).
- **PARKED seguem abertos** (do handoff [2026-06-15 13:30](2026-06-15-1330-sdd-floor-zero-scorecard-arquitetura-duravel.md)): **D1** versão-mínima da arquitetura (`mcp_work_leases` — toca camada canônica MCP, **sessão fresca**); **D2** read-side `full_suite`; **D4/D5** FeedbackRelevance/ContactObserver (precisam Pest); **numerar os 2 proposals** #2765/#2766 (ação [W]).
- Background task spawnado: gitignore do artefato `violations.json` do validador.

## Lições
- **Anti-stale não é teatro:** os 3 números do ledger driftaram em 1 dia (`7.9/15/63.9`→`5.4/14/100`); re-derivar da fonte no momento da execução pegou todos.
- **Mudar a dependência de um script de governança quebra os selftests que o sandboxam** — quem adiciona um `execSync` novo precisa atualizar o sandbox do `gate-selftest` (copiar a dep) + as fixtures pra nova definição.
- **`git add -A` em worktree onde rodou validador = artefato varrido** (`violations.json`) — o par adversarial pegou; usar add por path.

## Pointers (on-demand)
- Ledger fonte: [sessions/2026-06-15-sdd-conclusao-ledger-firme.md](../sessions/2026-06-15-sdd-conclusao-ledger-firme.md) (#2767)
- Proposals a numerar: #2765 (`decisions/proposals/sdd-medir-governar-floor-nightly.md`) · #2766 (`decisions/proposals/arquitetura-rede-ia-duravel-anti-vazamento.md`)
