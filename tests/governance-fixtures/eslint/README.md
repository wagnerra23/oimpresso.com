# Fixtures do self-test — catracas eslint / stylelint

Usadas pelo `gate-selftest` (Node puro, SEM `node_modules` no CI). Por isso as catracas
`eslint` e `stylelint` rodam com `--counts-from <counts.json>`: pulam o linter real e
alimentam contagens `{"path|rule": n}` pré-computadas, provando que o **comparador ratchet**
morde (delta>0 → exit 1), que é o que pode apodrecer em silêncio. As regras do linter em si
são exercitadas pelos gates required próprios (`eslint-gate.yml` / `stylelint-gate.yml`, que
fazem `npm ci`). `good`: counts == baseline (exit 0). `bad`: counts = baseline +1 (exit 1).
