---
name: Branch protection main — estado vivo e como mergear
description: Fonte enforced do required = governance/required-checks-baseline.json (protection-drift.mjs). enforce_admins:true desde ADR 0271 — gh --admin MORTO; merge = gh pr merge --squash com checks required verdes; reviews:0
type: reference
---
# Branch protection da `main` — estado vivo e como mergear

> ⚠️ **NÃO confie em contagem de checks cacheada em doc nenhum** (incluindo este). Entre jun–jul/2026 a contagem mudou 18→22→24 e três docs canon divergiram entre si por semanas. **Fontes vivas, nesta ordem:**
>
> 1. **[`governance/required-checks-baseline.json`](../../governance/required-checks-baseline.json)** — lista congelada dos required, **enforced** por [`scripts/governance/protection-drift.mjs`](../../scripts/governance/protection-drift.mjs) (workflow `protection-drift.yml`): required que some do vivo = 🔴 drift; novo = 🟡 aviso. Demoção só via PR editando o baseline + ADR ([ADR 0275](../decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) §5). Todo flip de promoção atualiza o baseline no MESMO PR.
> 2. `gh api repos/wagnerra23/oimpresso.com/branches/main/protection` — o vivo bruto (exige admin; agente sem admin usa `gh api repos/.../branches/main --jq .protection` + `/rules/branches/main`, como o próprio `protection-drift.mjs` faz).

## Estado verificado 2026-07-10 (snapshot ilustrativo — a fonte é o baseline acima)

- **`enforce_admins: true`** (desde 2026-06-11 · [ADR 0271](../decisions/0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md)) — admin **não** bypassa
- **`required_approving_review_count: 0`** — nenhuma aprovação exigida
- `required_linear_history: true` · `strict: false` · force-push/deleção bloqueados
- **24 required checks** (poda/LEI [ADR 0314](../decisions/0314-poda-gates-onda-2-lei-fusoes.md): required = só Tier-0/correção do núcleo)

## Como mergear

- **Caminho único:** `gh pr merge <N> --squash` com todos os required verdes. Nada mais é necessário (`reviews:0`).
- ❌ **`gh pr merge --admin` está MORTO** — `enforce_admins:true` torna o flag inoperante. (Este doc já ensinou o contrário na era pré-0271; se você leu `--admin` em handoff/session antigo, está defasado.)
- ❌ **Auto-merge (bot `grokwr2`) BLOQUEADO** ([ADR 0283](../decisions/0283-handoff-loop-zero-paste.md)) — pra `.tsx` em ERP multi-tenant, humano-no-merge é **estrutural**; Fase 0 (1-clique de [W]) é o ótimo atual.
- Casa declarada do estado de merge/enforcement no loop de design: [`prototipo-ui/AUTOMACAO-LOOP-AUTONOMO.md`](../../prototipo-ui/AUTOMACAO-LOOP-AUTONOMO.md) §2–§3.

## Pegadinhas que seguem valendo

- **Rate limit:** GraphQL 5000/h compartilhado. REST tem limite separado — quando GraphQL bate "API rate limit already exceeded", `gh api` REST ainda funciona.
- **Check que não roda ≠ check verde:** required com path-filter usa o padrão required-readiness (always-run + skip-as-pass, [ADR 0271](../decisions/0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) onda 2) — se um PR ficar `blocked` com check `none`, o workflow perdeu esse padrão; consertar o workflow, não bypassar.

## Histórico deste doc

- **2026-05-10 (v1):** documentava `enforce_admins:false` + `--admin` legítimo + 1 required check ("ADR frontmatter"). Estado morto desde 2026-06-11 (ADR 0271). Reescrito em 2026-07-10 porque a versão antiga ensinava ativamente o caminho errado.
