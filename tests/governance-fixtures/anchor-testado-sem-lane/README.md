# fixture — anchor-lint `--check-lane` (G1c · item b: covers só conta com teste em lane de JUnit)

Par good/bad que prova que `anchor-lint.mjs --check-lane` **morde** (doutrina ADR 0303). Rodado pelo `gate-selftest.mjs` (sandbox por cwd: o script REAL roda com a fixture como `process.cwd()`).

Ambos têm a MESMA US implementada+coberta (`US-SLLN-001`: `**Implementado em:** app/SelftestLane.php` → anchored_ok; `**Testado em:** tests/Feature/SelftestLaneTest.php` com `// @covers-us US-SLLN-001`). O que varia é só a lane:

- **good/** — `.github/ci-sqlite-pest.list` lista o teste → `inLane` true → `req_sem_lane` 0 → **exit 0** (`fora de lane … : 0 US`).
- **bad/** — lista vazia (e o teste não está sob `Modules/{Financeiro,Jana,NfeBrasil}/Tests`) → `inLane` false → `req_sem_lane` 1 → **exit 1** (`🚦 … NENHUM numa lane de JUnit`).

Por quê: na reconciliação Cliente 2026-06-24, covers passaram verdes com `@covers-us` em testes **fora de qualquer lane de JUnit** (ex.: `br-inputs.test.tsx` vitest, `ClienteDrawerRowsCanonBrPayloadTest` quarantinado) → o gate verde (G1b) nunca poderia confirmá-los. Este gate mecaniza a recusa da cobertura de fachada. Proposta: `memory/decisions/proposals/2026-06-24-charter-live-derivado-sinal-prod-anti-fachada.md` (item b).
