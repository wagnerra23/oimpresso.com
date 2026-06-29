# fixture: handoff-changed (gate-selftest)

Prova que o portão `prototipo-ui/handoff-changed.mjs` MORDE e LIBERA certo.

- `baseline.json` — gerado de `good/` via `--update` (NÃO editar à mão).
- `good/` — staging idêntico ao baseline → `--staging good --baseline baseline.json` = exit 0 ("IDÊNTICO ao baseline").
- `bad/` — staging com `components/Button.jsx` ALTERADO vs o MESMO baseline → exit 1 ("MUDOU").

Regenerar o baseline (se mudar os arquivos de `good/`):
```
node prototipo-ui/handoff-changed.mjs --staging tests/governance-fixtures/handoff-changed/good \
  --baseline tests/governance-fixtures/handoff-changed/baseline.json --update
```
