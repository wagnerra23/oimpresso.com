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
| `sdd-scorecard --ratchet` (P14 · floor) | `sdd-scorecard-floor/{good,bad}/` (sandbox temp, SEM `SDD_RATCHET_ARM` — armed vem do baseline da fixture) — good = `nightly-floor.json` 298 = baseline (verde); bad = 299 > 298 (regressão de fonte externa morde) |
| `sdd-scorecard --ratchet` (P14 · fail-red) | `sdd-scorecard-floor-ausente/{good,bad}/` — good = floor presente 298; bad = **SEM** `nightly-floor.json` com métrica ARMADA ⇒ exit 1 (fonte ausente não passa em silêncio — defeito nº 1 da avaliação 2026-07-01) |
| `memory-health` | `memory-health/{good,bad}/` (sandbox temp + script real copiado) — Check A colisão ADR não-registrada |
| `baseline-tamper-guard` | `baseline-tamper-guard/{base,good,bad}/` (sandbox **git** real: commit base apertado → commit head afrouxado; bad pareia com `code-touched.txt`) — anti-grandfather, vetor #2848 |
| `baseline-tamper-grow` | `baseline-tamper-grow/{base,good,bad}/` (sandbox **git** real: casos-coverage-baseline cresce 1→2 violações; good leva o trailer `BASELINE-GROW:` no commit → exit 0; bad cresce ISOLADO sem trailer → exit 1) — fecha o bypass do audit 2026-06-22 #4 (crescer casos-coverage exige trailer, isolado não basta) |
| `anchor-lint --check` | `anchor-lint/{good,bad}/` (sandbox via cwd) — good = anchor p/ path existente (anchored_ok); bad = anchor p/ path morto (anchored_dead · ADR 0273 §2 · P08) |
| `doneness-lint --check` | `doneness/{good,bad}/` (sandbox via cwd) — good = status×âncora consistentes + zona-cinza tolerada (exit 0); bad = `status=done` sem âncora viva + `status=todo` com âncora viva (conflito status:×âncora · ADR 0302) |
| `detectar-telas --staging --repo` | `detectar-telas/{good,bad}/{staging,repo}/` (script REAL no lugar — não usa cwd) — good = vendas-page.jsx resolve via ALIAS → Sells/Index.tsx (SEMANTICO) ⇒ 0 órfãos (exit 0); bad = mistero-page.jsx órfão (sem charter nem alias) ⇒ exit 1 "GATE FALHOU" (gate de import Fase 0/0.5) |

| `sdd-scorecard --ratchet` (corruptors) | `sdd-scorecard-corruptors/{good,bad}/` (sandbox via cwd + `scripts/audit/sqlite-test-corruptors.mjs` copiado) — baseline arma `sqlite_corruptors=0` (armed:true, SEM SDD_RATCHET_ARM · P14 carona 2, fusão GT-G3 · ADR 0314); good = sem corruptor (0=0, exit 0); bad = `CorruptorDemoTest.php.txt` (renomeado pra `.php` SÓ no sandbox — regra dura abaixo) com `Schema::drop('business')` não-guardado ⇒ mede 1 > 0 ⇒ exit 1 |

REGRA DURA: NENHUM `.php` aqui — o foundation-ratchet real varre `tests/`
recursivamente e contaria fixture como teste do repo (poluiria os contadores).
Conteúdo 100% fictício (DemoMod/RealDemo/Ghost*) — zero PII, repo é público.
O caso `bad` do tamper-guard usa `.txt` (não `.php`/`.mjs`) de propósito como
"código tocado" — sinaliza o pareamento sem virar teste/símbolo de nenhum scanner.
