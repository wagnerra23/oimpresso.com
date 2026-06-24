# fixture `doneness-baseline` (ARMING · grandfather no-new-lie · doneness-lint · ADR 0302/0275)

Prova que `doneness-lint --check --baseline <baseline>` ISENTA o conflito status×âncora
LEGADO grandfatherado **mas continua MORDENDO o conflito NOVO** — o coração do arming
(ADR 0302/0275 advisory→required, com baseline grandfather do legado):

- **good/** → US-SLDB-001 em conflito (`conflito_done_sem_ancora`) **MAS** está no baseline → **exit 0**.
- **bad/**  → mesma US-SLDB-001 em conflito; o baseline só grandfathera um decoy (US-SLDB-999) → **exit 1**.

O par isola UMA variável: estar-ou-não no baseline. Mesma SPEC, mesmo conflito; só o conteúdo
do `governance/doneness-baseline.json` muda. Prova que o grandfather **não** é um "desligar tudo" —
é per-conflito (no-new-lie): mentira velha isenta, mentira nova morde.

Sem isso, armar o `doneness-lint --check` avermelharia os 84 conflitos legados de uma vez
(medido em `origin/main`). Com o baseline (ratchet só-desce — crescer exige trailer
`BASELINE-GROW`, ver `baseline-tamper-guard.mjs`), o gate só pega conflito novo/tocado.

Camada 2 do `gate-selftest` (GT-G6, ADR 0256/0303): a catraca prova que MORDE antes de
qualquer promoção a required. Complementa `doneness` (que prova o gate cru, sem baseline).
