---
date: 2026-06-30
hour: "17:15 BRT"
topic: "Tier-0 guards + anchor entry/covers ARMADOS a required (resolve o near-miss das 16:03)"
duration: "~2h"
authors: [Claude, Wagner]
---

# Tier-0 guards + anchor entry/covers → required (branch protection 25→27)

Continuação direta do handoff [16:03](2026-06-30-1603-anti-bifurcacao-armar-gates.md): aquele
catalogou o **near-miss** "tier0 recomendado ARM-NOW mas VERMELHO no main mascarado por
`continue-on-error`" (chip `task_0cbefece`). Esta sessão **fechou esse loop** — zerou as
violações, removeu a máscara e armou o gate de verdade. Depois, a pedido do Wagner, fez o mesmo
com o `anchor entry/covers` (drift pré-existente de 2026-06-24).

## Estado MCP no momento do fechamento
- **Cycle:** CYCLE-08 (Receita — Onda A) · 100% decorrido · 0 dias · **off-cycle** (trabalho é housekeeping de governança, não toca goals do cycle).
- **my-work:** 30 tasks ativas (8 review / 8 blocked / 14 todo) — nenhuma linkada a esta sessão (governança/CI, sem US).
- **decisions-search "tier-0 guards branch protection":** 0275 (calendário de promoções §5), 0307 (Onda 0 enforcement), 0303 (anchor SA-A2-bis), 0264, 0261 — nenhum ADR novo precisou ser criado; tudo cabe sob os existentes.

## O que aconteceu
1. **Zerar as 2 violações tier0** (#3438): 6 ocorrências em 5 arquivos.
   - `withoutGlobalScopes()` legítimo sem `// SUPERADMIN` → comentário adicionado em `AuditChainService::verificarIntegridade` (cadeia de auditoria GLOBAL cross-tenant, read-only) e `WhatsmeowHealthProbeCommand::hasRecentInbound` (cron sem session, escopado por `channel_id`).
   - `business_id=4` (RotaLivre cliente) em fixtures → tenant neutro: `AuditChainServiceTest` (4→1), `ProfileDistillCommandTest` (batch `[1,4,99]→[1,2,99]`), `AsaasWebhookIdempotencyTest` (seed+URL `4→1`).
2. **Remover a máscara + baseline** (#3441): `continue-on-error: true` removido (job+step) do `tier0-guards-advisory.yml` + context adicionado ao `required-checks-baseline.json`.
3. **Flip tier0** (gh api aditivo): branch protection 25→26, `Tier-0 guards (advisory · WithoutGlobalScopes + BusinessId)` agora required.
4. **Require-safe anchor-drift** (#3444): removido o `paths:` filter do `anchor-drift.yml` (path-filter + required = deadlock "Expected", incidente 2026-06-08 / ADR 0263) → roda em todo PR; os 5 jobs saem exit 0 no fallback (anchor-lint.mjs L623), sem ruído.
5. **Flip anchor** (gh api aditivo): 26→27, `anchor entry/covers gate (advisory)` required. **Resolve o drift pré-existente** (baseline tinha desde 2026-06-24, vivo não).

## Evidência (Pest real, não o status mascarado)
- tier0 antes: `Tests: 2 failed` (run 28465500317). Depois: `Tests: 2 passed (4 assertions)` — CT 100 (`oimpresso-staging`, worktree detached em origin/main) **e** runs pós-merge no `main`.
- anchor entry/covers: **pass** no PR #3444 (no-op no-SPEC) e no único PR aberto #3445.
- **Drift zero confirmado:** baseline (27) == vivo (27), `só no vivo: []` / `só no baseline: []`.
- Nenhum PR aberto deadlockado (#3445 tem os 2 novos checks verdes).

## Artefatos gerados
- PRs merged: **#3438** (zera violações, 5 files), **#3441** (máscara+baseline), **#3444** (require-safe anchor).
- Branch protection live: **25→27 required** (2 flips aditivos via `gh api`, nenhum check removido).
- Chip spawnado: `task_93bb8517` — `makeWmChannel()` redeclarado em 2 testes Whatsapp (`HealthProbeChannelsCommandTest:95` + `WhatsmeowResubscribeEventsCommandTest:58`) derruba `php artisan test --filter` da suite inteira (fatal `Cannot redeclare`). Fora de escopo, catalogado.

## Persistência
- **git:** 3 PRs merged no main + este handoff (PR próprio).
- **branch protection:** estado vivo alterado (gh api) — registrado no `required-checks-baseline.json` (git canon) + entradas em `_meta.promocoes`.
- **MCP:** webhook GitHub→MCP propaga o handoff em ~2min.

## Próximos passos pra retomar
- Os outros 3 jobs do `anchor-drift.yml` (`anchor-lint`, `doneness-lint`, `charter-live-signal`) **agora são require-safe** (compartilham o trigger sem `paths`). Promover quando o calendário do Wagner permitir (ADR 0275 §5, 1/semana) — só o flip aditivo `gh api`.
- Resolver o chip `task_93bb8517` (makeWmChannel) — destrava `php artisan test --filter` full-suite no CT 100.
- O chip `task_0cbefece` do handoff 16:03 (near-miss tier0) está **RESOLVIDO** por esta sessão.

## Lições catalogadas
- **`continue-on-error` mascara o status do job:** `gh pr checks` mostra "pass" mesmo com Pest vermelho. Validar SEMPRE pelo LOG REAL do step (`gh run view --log | grep Tests:`), não pela `conclusion`. (Mesma lição-mãe do handoff 16:03 — "leia o log, não a conclusion".)
- **path-filter + required = deadlock:** workflow precisa rodar em TODO PR antes de virar required (ADR 0263, incidente 2026-06-08). Conferir o `on:` ANTES de flipar. Os gates require-safe do repo (conformance/ui-lint/charter-refs) têm o comentário-padrão.
- **Flip aditivo, nunca PATCH da lista:** `POST .../required_status_checks/contexts` adiciona sem remover; extrair o context string do próprio job name + assertar `job==baseline` antes de postar (evita red-lock por nome torto, incl. o `·` U+00B7).
- **Ordem segura:** zerar violação → merge (remove máscara) → verificar verde-real no main → só então flipar (senão arma vermelho).

## Pointers detalhados (consultar on-demand)
- Workflow: [`.github/workflows/tier0-guards-advisory.yml`](../../.github/workflows/tier0-guards-advisory.yml) · [`.github/workflows/anchor-drift.yml`](../../.github/workflows/anchor-drift.yml)
- Baseline: [`governance/required-checks-baseline.json`](../../governance/required-checks-baseline.json) (`_meta.promocoes`)
- ADRs: [0263](../decisions/0263-require-safe-sem-paths-filter.md) (require-safe), [0275 §5](../decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) (calendário de flips), [0093](../decisions/0093-multi-tenant-isolation-tier-0.md) (SUPERADMIN), [0101](../decisions/0101-tests-business-id-1-nunca-cliente.md) (biz=1).
