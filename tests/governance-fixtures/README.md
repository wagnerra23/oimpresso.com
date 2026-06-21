# governance-fixtures — pares boa/ruim do gate-selftest (GT-G6)

Fixtures VERSIONADAS de `scripts/governance/gate-selftest.mjs` ("quem vigia os
vigias"): pra cada catraca de governança, 1 caso **good** (exit 0) + 1 caso
**bad** que DEVE falhar (exit 1). Se o caso ruim passar, a catraca parou de
morder — e o selftest avermelha. Salda a dívida de evidência do PR #2588
(fixtures do ledger-check não tinham sido commitadas).

| catraca | fixtures |
|---|---|
| `knowledge-drift --check` | `knowledge-drift/{good,bad}/` (sandbox via cwd) |
| `foundation-ratchet` | reuso de `scripts/tests/fixtures/foundation-ratchet/` (já em main — zero duplicação) |
| `ledger-check --enforce` | `ledger-check/files.txt` compartilhado + `{good,bad}/ledger.json` |
| `sdd-scorecard --ratchet` | `sdd-scorecard/{good,bad}/` (sandbox temp + scripts reais copiados) |
| `memory-health` | `memory-health/{good,bad}/` (sandbox temp + script real copiado) — Check A colisão ADR não-registrada |
| `baseline-tamper-guard` | `baseline-tamper-guard/{base,good,bad}/` (sandbox **git** real: commit base apertado → commit head afrouxado; bad pareia com `code-touched.txt`) — anti-grandfather, vetor #2848 |
| `anchor-lint --check` | `anchor-lint/{good,bad}/` (sandbox via cwd) — good = anchor p/ path existente (anchored_ok); bad = anchor p/ path morto (anchored_dead · ADR 0273 §2 · P08) |

REGRA DURA: NENHUM `.php` aqui — o foundation-ratchet real varre `tests/`
recursivamente e contaria fixture como teste do repo (poluiria os contadores).
Conteúdo 100% fictício (DemoMod/RealDemo/Ghost*) — zero PII, repo é público.
O caso `bad` do tamper-guard usa `.txt` (não `.php`/`.mjs`) de propósito como
"código tocado" — sinaliza o pareamento sem virar teste/símbolo de nenhum scanner.
