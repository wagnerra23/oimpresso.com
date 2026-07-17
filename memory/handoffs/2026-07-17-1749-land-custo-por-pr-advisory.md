---
date: "2026-07-17"
time: "17:49 BRT"
slug: land-custo-por-pr-advisory
tldr: "Landing dos 5 commits de custo-por-PR (agent-cost-per-pr, advisory) rebaseados no main fresco (base estava 60 atrás) + 1 commit de snapshot ao vivo. PR #4488 aberto, NÃO mergeado (R10). Re-verificado na base fresca: 90 checks, invariantes exatos."
decided_by: [W]
prs: [4488]
related_adrs:
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0314-poda-gates-onda-2-lei-fusoes
next_steps:
  - "[W] revisa + mergeia PR #4488 (advisory, não bloqueia ninguém)"
  - "Chip C3 aberto (não deste PR): drift-sentinel roda mas é cego (baseline mock) — ver handoff 13:30"
---

# Landing custo-por-PR — rebase no main fresco + PR #4488

## O que foi feito

Os **5 commits** de governança **custo-por-PR** (`agent-cost-per-pr`, advisory) estavam prontos+testados numa branch (`claude/competent-bassi-60b5cb`) cuja base estava **60 commits atrás** do `main`. Landing:

1. Criada `claude/land-custo-por-pr` off `origin/main` fresco (worktree `silly-varahamihira`, zero criação/remoção de worktree → sem risco de junction Windows).
2. Cherry-pick dos 5 commits em ordem. **Conflitos** (ambos append-only, resolvidos mantendo main + branch):
   - `proibicoes.md §5` — mantidas as 2 entradas (main "Razão de fidelidade" + branch "Casar custo→PR por SHA").
   - `gates-registry.json` — auto-merge limpo (adições não-sobrepostas).
3. **Diff vs main fresco = exatamente 5 arquivos** (sem falsas deleções da base stale, como o pedido alertava).
4. **6º commit de higiene**: snapshot regenerado **ao vivo** na base fresca (cumpre a própria lápide que o PR carrega — "antes de consertar um medidor, RODE-O ao vivo"): cobertura 79,25% → **80,67%**, `generated: 2026-07-17`.

## Re-verificação (local — .mjs Node, NÃO CT100)

- `agent-cost-per-pr.test.mjs` → **90 checks · SELFTEST OK**
- `agent-pr-outcomes.test.mjs` (irmão DORA, import compartilhado) → **SELFTEST OK**
- `selftest-registry-check.mjs` → **zero órfãos**
- Snapshot regenerado — invariantes exatos: 1ª chave `_LEIA_PRIMEIRO` · sem BOM · `cobertura_alocacao_pct` **80,67 ≤ 100** · `atribuido + residuo == escaneado` (16660,57 + 3991,22 = **20651,79** ao centavo) · `cobertura + residuo.pct == 100` · **USD-only, zero R$** (Tier 0).

## O coração aguentou

Revisão adversarial (2 agentes) confirmou que o join de 2 sinais (unidade=sessão · branch vence citação · nada vaza) é sólido; os defeitos corrigidos (H1 cobertura >100%, tautologia da defesa de idade, H2/H4/H5/H6, crash latente) eram todos na **borda de saída**. Controle-negativo provado nas 2 correções críticas.

## Estado / pendências

- **PR [#4488](https://github.com/wagnerra23/oimpresso.com/pull/4488)** aberto contra `main`. **Advisory de nascença** (ADR 0271/0314) — não é gate, não bloqueia merge. **NÃO mergeado — R10, só [W] mergeia.**
- `gh pr checks #4488` reportado ao [W] no fechamento desta sessão.

## Estado MCP no momento do fechamento

⚠️ **MCP oimpresso NÃO conectado nesta sessão** (o brief veio do hook SessionStart local, não da tool viva; `ToolSearch` por `cycles-active`/`sessions-recent`/`decisions-search` retornou vazio). Checklist de fechamento por **fallback filesystem** (how-trabalhar §"Fallback"):

- **sessions-recent** (`ls -t memory/sessions`): `2026-07-17-reguas-memoria-conhecimento-pos-c8-c12` · `-contrafactual-corpus-c1-agents-md` · `-piso-context-recall-e-schedule-fantasma`.
- **handoffs** (append-only base): último = `2026-07-17-1356-check-p-registry-ref-viva.md`.
- **decisions** (recentes): 0339 (promoção soberana 3 gates) · 0337 (emenda 0144 forward-close) · 0336 (gates design por mordida provada).
- **Brief SessionStart** (#372): sem cycle ativo · HITL pending [W]: 2 · Brain B 0% · 0 incidentes 24h.

Off-cycle. Worktree `silly-varahamihira-e2babf` @ base `origin/main`.
