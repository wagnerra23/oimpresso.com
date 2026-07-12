# fixture `anchor-lint-verde-resilient` (V6-A · resiliência do gate verde)

Prova que `loadJunit` de `anchor-lint.mjs` é **resiliente**: um `--junit` de run
**inválido/incoerente/ausente** NÃO faz crash (exit 2) NEM avermelha (exit 1) — degrada a
`behavior_unknown` (advisory). E prova, no mesmo par, que essa degradação **não desarmou** o gate:
um JUnit **coerente** que marca o teste-que-cobre como não-verde ainda MORDE.

- **good/** → o `--junit` aponta pra um **marcador de run inválido** (`fullsuite-summary-invalid/v1`,
  `invalid:true` — exatamente o que `scripts/tests/junit-summary.mjs` grava pro run 0-byte/morto).
  Com `--check-verde` ARMADO ⇒ `behavior_unknown` ⇒ 0 `req_teste_vermelho` ⇒ **exit 0**
  (a acusação `Gate verde (advisory): behavior_unknown — --junit …` sai no stdout). Sem resiliência,
  o `loadJunit` antigo saía **exit 2** (crash) — false-red do gate quando enfim armado.
- **bad/** → MESMO SPEC/Service/Test, mas o `--junit` é um summary **coerente** (`junit-summary/v1`)
  que marca o arquivo-de-teste só como `skipped` (não-verde) ⇒ `req_teste_vermelho` ⇒ **exit 1**
  (`🟥 US-SLFV-001`). Garante que a resiliência NÃO fez o gate parar de morder o vermelho real.

## Reúso do SPEC/Service/Test da fixture `anchor-lint-verde`

A diferença good↔bad aqui é **só** o `junit/pest-verde-junit.summary.json`. O SPEC (`SelftestVerde`,
US implementada + coberta), o `Service.php` e o `Test.php` são os MESMOS da fixture irmã
`anchor-lint-verde/good` — o runner (`runAnchorLintVerdeResilient` em `gate-selftest.mjs`) copia
aquela árvore e só sobrepõe o `junit/` daqui. Isola a variável **estado do JUnit** (válido-verde
já é a fixture `anchor-lint-verde`; inválido↔coerente-red é esta), sem duplicar o SPEC.

> O summary vive em **`junit/`**, não em `test-results/` — esse path é gitignored e o JSON precisa
> estar committed pra catraca rodar no CI (mesma razão da fixture `anchor-lint-verde`).

## Por que existe (avaliação SDD 2026-07-12 · risco sistêmico nº 2)

O veredito mais forte da âncora — "US implementada + teste que a cobre está VERDE" — arma sobre um
JUnit que a materialização sharded (chip harness) pode entregar 0-byte/parcial. V6-A garante que,
quando o gate enfim for armado, um run morto/parcial vira `behavior_unknown` honesto — nunca um
falso-vermelho que barra o merge de US que passaram noutro shard. `run inválido = "não sei"`, não
`"vermelho"`. fs-puro (só `JSON.parse`, invariante ADR 0303). Camada 2 do `gate-selftest` (GT-G6).
