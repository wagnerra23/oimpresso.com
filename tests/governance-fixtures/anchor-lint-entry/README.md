# fixture `anchor-lint-entry` (G1b · gate de entrada)

Prova que `anchor-lint --check-entry` MORDE a "regra de entrada":

- **good/** → US implementada (`anchored_ok`) **COM** DoD/aceite **E** teste que declara `@covers-us US-SLFE-001` → 0 req → **exit 0**.
- **bad/** → US implementada **SEM** aceite/DoD **E** sem teste que a cobre → **exit 1** (`req_sem_aceite` + `req_sem_covering_test`).

A regra de entrada (Wagner: *"não pode ser feito e refeito por cada pessoa que mexer no sistema"*): uma US não nasce dizendo **"implementada"** sem (1) aceite definido e (2) um teste que a cobre — pra ninguém precisar refazer o que já está provado. `_pendente_` é exceção (tela não construída, honesta). ADVISORY em produção (`--check` normal não inclui); armar a required com **baseline grandfather** do legado (ADR 0275). Camada 2 do `gate-selftest` (GT-G6, ADR 0256).
