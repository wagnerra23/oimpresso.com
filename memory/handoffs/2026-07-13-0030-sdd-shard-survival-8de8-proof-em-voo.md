---
date: "2026-07-13"
time: "00:30 BRT"
slug: sdd-shard-survival-8de8-proof-em-voo
tldr: "2 PRs merged (V4 #4170 lane de coverage bounded + shard-survival #4188 os 3 modos de morte de shard). A 1a nightly sharded REAL provou a tese do V1 (1 shard rodou 1382 testes sem OOM) mas so 1/8 sobrevivia; #4188 conserta os 3 modos. PROVA 8/8 EM VOO no CT100 (run 20260712-212018) — proximo turno DEVE conferir."
prs: [4170, 4188]
decided_by: [W]
related_adrs: [0279-sdd-medir-governar-floor-nightly, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes]
next_steps:
  - "Conferir a prova 8/8 em voo: tailscale ssh root@ct100-mcp — run /opt/oimpresso-fullsuite/runs/20260712-212018 — grep 'shards vivos:' run.log + summary.json (coherent + all_shards_measured) + floor-compute (floor fresco != 291 stale)"
  - "Se <8/8: ler shard-<i>-out.txt dos mortos, catalogar novos modos, iterar (mecanismo killer_from_events + exclude ja generico)"
---

# Handoff — SDD OOM: lane de coverage bounded (V4) + shards sobrevivem (todos os modos) + prova 8/8 em voo

Sessão chip-driven (avaliação SDD 2026-07-12, composto 69/100 · gargalo-mãe OOM da nightly). Enxame de sessões paralelas landou o fix inteiro; minha contribuição: **V4** (lane de coverage) + **shard-survival** (os 3 modos de morte de shard) + **a 1ª nightly sharded REAL** que diagnosticou o resto.

## Estado MCP no momento
- `cycles-active`: **nenhum cycle ativo** em COPI (off-cycle).
- `my-work` @wagner: 30 tasks (9 review / 8 blocked / 13 todo) — **nenhuma** mapeia este trabalho (chip-driven, não US MCP).
- Handoffs irmãos hoje: `2245-jana-medicao-honesta`, `2200-matriz-onboarding-maquina-canario-chips` (cobre o fix OOM em alto nível + V4 #4170 citado), `1250-cobertura-charters`.

## O que aconteceu (o que NÃO está no #4179)
1. **V4 (#4170 merged, live, provado):** a lane de coverage (pcov single-core) rodava **16h+** e travava a ~77%; `timeout -s TERM` NÃO matava o `docker run` do pcov → runaway segurava o `.lock` → nightly seguinte pulava ("outro run em andamento" ×3; run 20260707 sumiu). Fix: `timeout -k` nas 2 lanes (SIGKILL no teto) + `COV_TIMEOUT_S` próprio + `coverage-compute` rejeita clover **truncado** (sem `</coverage>` → nunca mente baixo). Provado live: `timeout -k` mata container TERM-ignoring em 8s (exit 137).
2. **1ª nightly sharded REAL** (rodei eu, run `20260712-202236`, com o `cd $CODE` do #4183): **tese do V1 VALIDADA** — shard 5 rodou **1382 testes, junit válido, zero OOM**. Mas só **1/8** sobrevivia.
3. **shard-survival (#4188 merged):** os 7 shards mortos, 3 modos distintos → todos fixados:
   - `tests/Browser/*` (3,4,6) → `PlaywrightNotInstalledException` → **`shards-plan --exclude`** (Browser + governance-fixtures; o `pest` sem-args só roda os `<testsuite>` do phpunit.xml, o sharded passa dirs explícitos = mais amplo);
   - loader-blocker esgotado (2,7) → **`SHARD_MAX_ATTEMPTS=12`** (era 3);
   - **killer-test** mid-run sem fatal (0,1: `Wave23SaturationTest`@37=não-OOM, `SeedAdrsMetadataTest`@986) → **`killer_from_events()`** deriva o teste em voo do events-log e quarentena+retenta (+ loop-guard anti-giro-em-falso).

## ⚠️ EM VOO (o próximo turno DEVE conferir)
**Run de prova 8/8** disparado no CT100 pós-merge #4188: pid **3021233**, run **`20260712-212018`**, monitor bg **bt9uzvwn9**. Script canônico (dd6c8c97) deployado no /opt (flock protege vs sessões paralelas). Confirma se **8/8 shards vivos** → `summary coherent + all_shards_measured=true` → **floor FRESCO** (não o 291 stale). **NÃO declarei "pronto"** — falta esse número (DoD R1).

## Persistência
- **git:** #4170 (`56e955b266`) + #4188 (`dd6c8c9774`) em main. Este handoff via PR.
- **MCP:** webhook GitHub→MCP propaga ~2min pós-push.
- **CT100:** `/opt/oimpresso-fullsuite/ct100-fullsuite.sh` sincronizado (self-update */15) com a canon.

## Próximos passos pra retomar
`tailscale ssh root@ct100-mcp "grep 'shards vivos:' /opt/oimpresso-fullsuite/runs/20260712-212018/run.log; cat /opt/oimpresso-fullsuite/runs/20260712-212018/summary.json"` → se 8/8 + coherent + all_shards_measured, o floor destrava (R1/C2/T1/P13). Se <8/8, iterar com os artefatos dos mortos.

## Lições catalogadas
- **"Land então nightly prova" deixa bug passar:** #4172 (wiring) tinha CWD bug; #4183 (cwd) ainda deixava 7/8 morrendo. Cada camada só apareceu numa **nightly real**. Rodar a nightly real ANTES de declarar é o único gate honesto (R1).
- **Enxame de N sessões rodando nightlies no mesmo CT100 colide** — matar processos interfere em runs paralelas. O `flock` serializa com segurança **se você só RODA** (não mata). Uma sessão deve ser dona do loop de prova.
- **Backslash através de tailscale ssh** mangla PCRE — usar `tr '\134' '/'` + sed slash-based (sem literal `\` no pattern) foi o que funcionou pra derivar o arquivo do teste em voo.

## Pointers detalhados
- Proposta: [`memory/decisions/proposals/2026-07-12-nightly-fullsuite-sharding-harness.md`](../decisions/proposals/2026-07-12-nightly-fullsuite-sharding-harness.md)
- RUNBOOK (atualizado): [`memory/requisitos/Infra/RUNBOOK-ct100-fullsuite.md`](../requisitos/Infra/RUNBOOK-ct100-fullsuite.md)
- Avaliação origem: [`memory/sessions/2026-07-12-sdd-avaliacao-adversarial-processo.md`](../sessions/2026-07-12-sdd-avaliacao-adversarial-processo.md)
