---
data: "2026-07-13 08:25 BRT"
slug: testes-orfaos-ci-guards-mergeado
autor: "[CC]"
status: accepted
tipo: handoff
pr: 4205
merged_by: wagnerra23
related_prs: [4193, 4195, 4201, 4210, 4213]
related_adrs: [0314, 0275, 0291, 0271]
tags: [governance, ci, testes-orfaos, sdd, encoding]
---

# Handoff — testes órfãos registrados no CI + guards (PR #4205, MERGED)

## O que foi feito

Fechadas as ações #3 e resíduos #4 do juiz adversarial de verificação 2026-07-13
(wf_33e38126) — reencarnação em `.mjs`/`.sh` da proibição catalogada
_"Modules/X/Tests sem phpunit.xml = falsa cobertura"_. **1 PR, 1 intent.**

| Frente | Entrega | Prova local |
|---|---|---|
| (a) `sdd-distiller-freshness.test.mjs` (PR #4201) órfão de CI | step no `governance-script-tests.yml` | 15/15 ✓ |
| (b) `ct100-sdd-scorecard-snapshot.sh` (PR #4193) sem selftest | novo `scripts/tests/ct100-sdd-scorecard-snapshot.test.sh` (docker MOCK via seam `SDD_TEST_BIN`) + registrado no mesmo workflow | 9/9 ✓ |
| (c) guard staleness no wrapper | `git log -1 %ct` do artefato > `SDD_MAX_AGE_DAYS` (default 3) → WARNING alto e SEGUE (row honesta > dia sem row; documentado no header) | coberto por (b) |
| (d) fixture não-ASCII no `agent-cost-per-pr.test.mjs` | título PT-BR atravessa report→top_prs→snapshot em disco; asserts anti-mojibake + anti-BOM | 32/32 ✓ |

Auditoria do snapshot commitado do #4195 (resíduo (d)): **limpo** — charset dump só com
chars PT-BR legítimos + detector cp1252 double-encoding + 20/20 títulos idênticos ao
`gh pr view` ground-truth. O resíduo real era ausência de teste, fechada em (d). Nada a
corrigir no snapshot.

Diff: 4 arquivos, +174/−3. **Nada promovido a required** (ADR 0314/0275).

## Percalços de CI (todos diagnosticados e resolvidos)

1. **4 required davam internal error do GitHub Actions** (PII scan, Append-only canon,
   crons watchdog, Estoque Pest) — "log not found", sem conteúdo → verdes no `gh run
   rerun --failed`. Flake de infra, não o diff.
2. **dup-detector (advisory)** flagava overlap com **#4210** no `governance-script-tests.yml`.
   É COORDENADO: o corpo do #4210 aponta este PR como canônico pro órfão
   (_"`sdd-distiller-freshness` [fecha com PR #4205/A3]"_). #4210 traz o MECANISMO
   (catraca `selftest-registry-check`), #4205 FECHA o órfão. Resolvido com `Dedup-ack`
   honesto no corpo do PR.
3. **SDD scorecard ratchet (required)** falhou por branch STALE, não pelo diff: o `main`
   desarmou `full_suite_pass_rate` no **#4213** (`chore(sdd): desarma ... regime v1→v2
   sharded` — o floor 358 do harness v2 não é comparável ao 298 do v1). Meu branch-point
   era anterior. Fix = `git merge origin/main` (higiene, baseline `armed:false` veio do
   main, não autorado por mim) → ratchet local exit 0 → verde.

Sequência do erro do ratchet, útil pra próxima sessão que topar isto:
`not_yet_measured` (órfã `nightly-floor` não materializada, ~02:36–10:48 quebra repo-wide)
→ infra destravou ~10:48 → `baseline 298 → 358 (só pode DESCER)` (branch stale vs main
desarmado) → merge de main → ✓.

## Desfecho

CI **100% verde** (63 pass, 2 skip, 0 fail). **PR #4205 MERGED por wagnerra23** às
2026-07-13 11:21Z. Respeitei R10 (não mergeei — Wagner mergeou).

## Estado MCP no momento do fechamento

- `cycles-active` (COPI): **nenhum cycle ATIVO**.
- `my-work` (@wagner): 30 tasks — 9 REVIEW (US-SELL-036 FSM rollout p0, triage US-TR-*,
  US-PG-008, US-FIN-023), 8 BLOCKED (FIN-4, trilha NfeBrasil Gold dormente, FORJA-136),
  13 TODO (US-RECURRINGBILLING-002/003 p0, US-OFICINA-026 outreach Martinho, US-COM-011
  E2E custo/estoque, US-PROD-020/021, US-FISCAL-018, US-SELL-009 cutover, FORJA-142
  Sells/Create). Nada desta sessão entrou como task MCP (trabalho de governança/CI).
- `decisions-search` ("testes órfãos selftest registry"): 0264 (governança executável
  trio/casos↔teste — o guarda-chuva conceitual disto), 0101 (tests biz=1), 0208
  (larastan ratchet), 0223 (npm-audit). Nenhuma nova ADR necessária.

## Próxima ação

Nenhuma pendência deste trabalho — mergeado e verde. Item vizinho para quem seguir: o
**#4213 desarmou `full_suite_pass_rate`** temporariamente; re-armar após 3 nightlies v2
válidas consecutivas (~2026-07-15/16) via PR editando `governance/sdd-scorecard-baseline.json`
(valor = pior das 3 + `armed:true` + `valid_measurements:3`), conforme a `nota_armamento`
do próprio campo. Não é meu escopo — só sinalizo pra não re-armar cedo/absorver 358 como v1.
