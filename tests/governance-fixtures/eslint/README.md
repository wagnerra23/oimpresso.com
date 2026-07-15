# Fixtures do auto-teste da catraca `eslint` (gate-selftest)

Alimentam a entrada `eslint` no `scripts/governance/gate-selftest.mjs`, provando que
`scripts/eslint-baseline.mjs` **morde** (exit 1 + acusação) quando há regressão e
**passa** (exit 0) quando não há — pelo MESMO code path de produção.

## Por que os arquivos-alvo são `.js` (e não `.tsx`/`.ts`)

Pegadinha do `eslint.config.js`: as regras TS/React/a11y/`ds/*` só são aplicadas ao
glob `resources/js/**/*.{ts,tsx}` (e `ds/*` só em `Pages/**`+`Modules/**`). Um
`.tsx`/`.ts` colocado sob `tests/` **não casa nenhum `files`** — o ESLint 9 responde
`"File ignored because no matching configuration was supplied"` (nenhuma regra roda).
Como este auto-teste só pode tocar `tests/governance-fixtures/eslint/**` (nunca
`resources/js/**`), o único glob que a config lina fora de `resources/js/` é o bloco
`files: ['**/*.{js,mjs,cjs}']`, que aplica `js.configs.recommended` + `no-unused-vars`.
Por isso a fixture usa `.js` com uma violação real e determinística de `no-unused-vars`.

## Layout

- `good/` — `absorvida.js` (1 `no-unused-vars`) + `baseline.json` que **absorve** essa
  violação → delta 0 → `✅ Sem regressões vs baseline` (exit 0).
- `bad/` — `base.js` (absorvida) + `regressao.js` (1 `no-unused-vars` NOVA, fora do
  baseline) → delta +1 → `❌ REGRESSÃO` (exit 1).

## Rodar à mão (cwd = ROOT do repo)

```
node scripts/eslint-baseline.mjs --baseline tests/governance-fixtures/eslint/good/baseline.json --target tests/governance-fixtures/eslint/good
node scripts/eslint-baseline.mjs --baseline tests/governance-fixtures/eslint/bad/baseline.json  --target tests/governance-fixtures/eslint/bad
```
