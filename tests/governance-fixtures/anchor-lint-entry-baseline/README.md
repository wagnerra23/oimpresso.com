# fixture `anchor-lint-entry-baseline` (ARMING · grandfather no-new-lie · SA-A2-ter)

Prova que `anchor-lint --check-entry --baseline <baseline>` ISENTA a dívida LEGADA
grandfatherada **mas continua MORDENDO a mentira NOVA** — o coração do arming
(ADR 0275 advisory→required, com baseline grandfather do legado):

- **good/** → US-SLEB-001 viola (sem aceite / sem teste-que-cobre) **MAS** está no baseline → **exit 0** (grandfather).
- **bad/**  → mesma US-SLEB-001 viola; o baseline só grandfathera um decoy (US-SLEB-999) → **exit 1** (`regra de entrada`).

O par isola UMA variável: estar-ou-não no baseline. Mesmo SPEC, mesma violação, mesmo
teste; só o conteúdo do `governance/anchor-entry-baseline.json` muda. Prova que o
grandfather **não** é um "desligar tudo" — é per-US (no-new-lie): mentira velha isenta,
mentira nova morde.

Sem isso, armar o `--check-entry` avermelharia as 43 + 56 US legadas sem aceite/teste de
uma vez (medido em `origin/main`). Com o baseline (ratchet só-desce — crescer exige trailer
`BASELINE-GROW`, ver `baseline-tamper-guard.mjs`), o gate só pega US nova/tocada.

Camada 2 do `gate-selftest` (GT-G6, ADR 0256/0303): a catraca prova que MORDE antes de
qualquer promoção a required. Complementa `anchor-lint-entry` (que prova o gate cru, sem baseline).
