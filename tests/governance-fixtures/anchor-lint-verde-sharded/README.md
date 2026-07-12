# fixtures `anchor-lint-verde-sharded` + `anchor-lint-verde-partial` (V6-B · consumir a nightly sharded)

Provam que `anchor-lint` consome o summary da **nightly full-suite sharded**
(`fullsuite-summary-sharded/v1`, produzido por `scripts/tests/shards-merge.mjs`) — a ÚNICA fonte que
cobre as ~42 US nightly-only — e honra `all_shards_measured` (noite parcial → sem false-red).

Ambas reusam o SPEC+Service+Test da fixture `anchor-lint-verde/bad` (US-SLFV-001 implementada+coberta,
teste **in-lane** via `.github/ci-sqlite-pest.list`); a única variável de cada par é o `junit/`.

## `anchor-lint-verde-sharded` — aceitação do schema (isola passed × skipped)

- **good/** → summary `fullsuite-summary-sharded/v1`, `all_shards_measured:true`, o arquivo-de-teste **verde**
  (`passed:1`) → 0 `req_teste_vermelho` → **exit 0**. Prova que o schema sharded é CONSUMIDO (sem V6-B seria
  `behavior_unknown` → "0 US" nunca apareceria como veredito real).
- **bad/** → MESMO schema, `all_shards_measured:true`, arquivo só `skipped` → não-verde → **exit 1** (🟥).
  Prova que o gate MORDE o skipped na fonte sharded.

## `anchor-lint-verde-partial` — noite PARCIAL (isola `all_shards_measured`)

Mesmo teste **AUSENTE** do summary (`files:[]`) nos dois; a única variável é `all_shards_measured`:

- **good/** → `all_shards_measured:false` (shard 2 morto) → `ausente` é ambíguo (shard caído ≠ teste que não
  rodou) → NÃO avermelha → **exit 0**. Fecha o false-red da noite parcial.
- **bad/** → `all_shards_measured:true` (noite completa) → `ausente` = o teste devia ter rodado e não rodou →
  **exit 1** (🟥). Prova que ausência numa noite COMPLETA continua sendo vermelho legítimo.

## fs-puro (invariante ADR 0303)

O `anchor-lint` NUNCA roda teste: lê só o JSON que a nightly já produz (`shards-merge.mjs`, mesmo `files[]`
{file,passed,failed,errors,skipped} do `junit-summary/v1`). Advisory de nascença; arming = flip Wagner por
calendário (ADR 0275). Camada 2 do `gate-selftest` (GT-G6). Design: avaliação SDD 2026-07-12 · V6-B.
