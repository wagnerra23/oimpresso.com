# fixture `anchor-lint-verde` (G1b · Phase B · gate verde)

Prova que `anchor-lint --junit <summary.json> --check-verde` MORDE a regra **verde-por-arquivo**:

- **good/** → US implementada (`anchored_ok`) + DoD + teste que declara `@covers-us US-SLFV-001`,
  e o JUnit summary marca o arquivo-de-teste como **verde** (`passed:1`) → 0 `req_teste_vermelho`
  → **exit 0**.
- **bad/** → tudo igual ao good, MAS o JUnit summary marca o arquivo só como `skipped:1`
  (`passed:0`) → pela regra dura `skipped != passed` NÃO é verde → `req_teste_vermelho` → **exit 1**.

A diferença good↔bad está **só** no `test-results/pest-verde-junit.summary.json` (o `SPEC.md`, o
`Service.php` e o `Test.php` são byte-a-byte idênticos) — isso isola a regra verde, sem confundir
com o gate de entrada (aceite/cobertura, fixture `anchor-lint-entry`).

## Por que o caso `skipped` (e não `failed`)

`req_teste_vermelho` cobre três status NÃO-verde idênticos: **vermelho** (`failed/errors>0`),
**ausente** (arquivo não rodou nesse lane) e **skipped** (só `markTestSkipped`). O `failed` é trivial
— qualquer implementação ingênua pega. O `skipped` é a mentira sutil que a regra existe pra fechar:
o lane sqlite do NfeBrasil tem **34/45 testes chamando `markTestSkipped`** (regras fiscais MySQL-only
viram no-op), então um `verde@` ingênuo certificaria verde uma suite que **não rodou** as regras
— ×150 clientes fiscais. A fixture `bad` usa `skipped` de propósito pra provar que o gate trata
`skipped → não-verde`. `failed` e `ausente` mordem do mesmo jeito (mesmo branch `!some(verde)`).

## fs-puro (invariante ADR 0303)

O `anchor-lint` **nunca roda teste/PHP/DB**: o JUnit entra como JSON (gerado pelo CI via
`scripts/tests/junit-summary.mjs`, schema `junit-summary/v1`, agrega POR ARQUIVO) e é só lido com
`JSON.parse`. Sem `--junit` → `behavior_unknown` (advisory, nunca avermelha legado).

ADVISORY em produção (`anchor-drift.yml` roda `--check` normal, sem `--junit`/`--check-verde`);
arming a required = flip do Wagner por calendário com baseline grandfather (ADR 0275). Camada 2 do
`gate-selftest` (GT-G6, ADR 0256) — "quem vigia os vigias". Design: `memory/sessions/2026-06-23-ancora-improvada-design-final.md` §1b.
