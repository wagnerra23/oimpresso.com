---
date: "2026-06-22"
topic: "Poda da malha de CI por funil adversarial (3 fusões mergeadas) + o achado-mãe: taxa de criação de governança > taxa de poda → o teto é a alavanca, não o balde"
authors: [W, C]
prs: [3202, 3203, 3204]
related_adrs:
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0256-knowledge-survival-catraca-sentinela-gate-cadencia
  - 0271-required-readiness-onda-2
  - 0275-sdd-scorecard-promocao-gates
---

# Poda de governança via funil adversarial — e por que podar não basta

## Como começou
Wagner: "pode criar um MAP do sistema? quero ver se tem algo quebrado" → mapa de saúde (nada quebrado em runtime; **439 erros TS sem gate de typecheck**; PR Tier-0 #3162 aberto). Depois: "está ficando grande e bagunçado, o que preciso pra me organizar?" → escolheu **podar a governança**.

## Diagnóstico
- 81 workflows, 19 `required` no main (enforce_admins=true). ~600 runs CI/dia.
- Catches por workflow (90d): a maioria dos gates de conteúdo = 0 catches; `modules-pest` morde 68% (210/307); `memory-schema` 61; `knowledge-ghost` 32; `infra-contract` 48%.

## Os 3 erros (mesmo padrão)
Antes de acertar, errei 3× — sempre **confundindo ausência de sinal numa métrica cega com ausência de valor**:
1. v1: "deletar 6 one-shots" → ao LER, eram escape hatches de incidente (force-clean-rebuild, quick-sync, phpstan-baseline-regen…). Invalidada.
2. v2: "mandar `knowledge-ghost` pro cron (0 failure)" → a medição mostrou **32 catches** reais. Invalidada.
3. v3: "rebaixar 4 advisory (0 failure em 90d)" → **adversário** provou: os 4 têm **1-6 dias de vida** (plan-health nasceu ontem) e plan-health é **cego** (continue-on-error 7×). A "janela de 90 dias" não existia. Invalidada.

Wagner: "já errou, quero um adversário antes" → instituímos o funil.

## O funil (método reutilizável)
proposta → **adversário tenta refutar** → só executa o que sobrevive → **adversário no diff** antes do merge → **CI auto-valida** (o próprio gate fundido roda no PR).

## As 3 fusões (Movimento 1 — consolidação)
- **#3202** casos-meta + dominio-meta → `guards-meta` (1 job, 1 `npm ci`). MERGED.
- **#3203** dsih + scheme → `xss-content` (anti-XSS, 1 runner, `if: success()||failure()` no 2º). MERGED.
- **#3204** jana ×3 lógica pura → `jana-logica-pura` (1 `composer install`, 4 suites). `jana-pest` (MySQL/required-ready ADR 0271) ficou FORA de propósito. MERGED.
- Cada uma: adversário **MERGE-OK** + gate verde no próprio PR. **−7 workflows.** `memory-schema` ficou fora (morde 59×, risco de cobertura).

## O achado-mãe
Removi 7. **Outras sessões criaram ~10 em 24h** (quase tudo meta-governança). O contador foi **81→85 APESAR da poda**. A **taxa de criação > taxa de poda**. Podar é balde; o que resolve é a torneira = **teto de governança** (proposta `2026-06-22-teto-de-gate-governanca.md`).

## Lições catalogadas
1. **Ausência de sinal numa métrica cega ≠ ausência de valor** — em uso/failure-de-workflow, 0 não prova inútil. LER o artefato antes de cortar.
2. **Advisory não falha por design** → `conclusion=failure` é cega pra advisory. Pra cortar advisory: medir **catches reais** (parse output/baseline) + janela **≥14d** (ADR 0275 §5). Nunca cortar gate com dias de vida.
3. **Taxa de criação de governança supera a poda manual** → o **teto** (regra + check que exige classe terminal + âncora de custo) é a alavanca.
4. Fusão que toca check `required` muda o nome do check → travaria o branch protection. Só fundir não-`required` (verificado via `gh api .../protection`).
5. Worktree full (não `--no-checkout`) + `gh`/`ls-tree` com `-- path` (git-bash Win mastiga `rev:path`).

## Próximos passos
- Proposta do **teto** aguarda Wagner (regra + dono do check).
- Movimento 2 (rebaixar advisory) só ~5/jul (14d ADR 0275) + métrica de catches.
- Bônus: promover `xss-content` a `required` (anti-XSS) — mexe em branch protection, Wagner.
