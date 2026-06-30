# Handoff 2026-06-30 22:00 — Poda F1 (DS gate) CONCLUÍDO (swap+merge feitos)

> Fecha o handoff [2026-06-30-2145](2026-06-30-2145-poda-gates-f1-ds-gate-swap-pendente.md), que ficou com moldura "swap **pendente**". **Não está mais pendente** — Wagner deu "merge" (R10) e o swap atômico + merge foram executados às 21:56. Nada a reabrir.

## Estado final (verificado)

- **F1 [#3456](https://github.com/wagnerra23/oimpresso.com/pull/3456) MERGED** em main (`b1e4f559d1`) — `ds-gate.yml` no ar, 7 workflows DS/cor deletados.
- **Swap atômico (classic protection):** removidos `Conformance · cor-crua ratchet vs baseline` + `UI Lint · ratchet vs baseline`, adicionado `DS gate`. **29 contexts classic** + ruleset `Governance Gate` (ruleset NÃO continha os 2 LEI → swap foi só no classic). `strict:false` + `enforce_admins:true` preservados.
- **Verificação:** `DS gate` verde em main HEAD · `protection-drift` 🟢 (live==baseline, nenhum demovido) · `memory-health` 🟢 + paridade file↔registry **80=80** no main combinado (F1+F2) · counterfactual (#3457, fechado) provou `DS gate` vermelho quando ui-lint regride.
- **Sem dano colateral:** F2 (#3459) mergeou 21:40, F1 squash-mergeou limpo em cima (chaves de registry diferentes); #3458 e docs #3461 já mergeados. Zero PR preso em `DS gate`.

**Líquido:** 91 → 80 workflows · required 31 → 30 (os 2 LEI viraram 1 `DS gate`, sem perder proteção — o agregador bloqueia cor-crua+ui-lint via `needs`+exit-1).

## Descoberta perene (registrar pra próximos blocos da poda)

**Required vive em DOIS lugares no main:** classic protection (`/branches/main/protection/required_status_checks`, 29 contexts) **+** 1 ruleset (`/rules/branches/main`, só `Governance Gate`). Ao swapar/demover required (F5, D-1), conferir os DOIS — `protection-drift.mjs` já lê ambos, mas o PATCH precisa mirar onde o contexto realmente vive. `required-checks-baseline.json` separa `classic_protection.contexts` de `rulesets.contexts`.

## Próximos blocos ADR 0314 (não nesta sessão)

F5 (trio-tela) · D-1 (demoções de required) — **tocam branch protection**, não rodar em paralelo. F2 já executado (#3459). D-3 já executado (#3455).
