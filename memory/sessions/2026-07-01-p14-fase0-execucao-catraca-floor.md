---
date: "2026-07-01"
topic: "P14 Fase 0 EXECUTADO — catraca do floor=298 MORDE no required (materialização + fail-red + counterfactuals) + rename dos 6 required sem '(advisory)' + 2 métricas armadas de carona (n_quarantine, sqlite_corruptors)"
authors: [C]
type: execucao-sdd
gatilho: "Wagner — 'Fase 0 — P14, o fix de dias que destrava a confiança em tudo (Fable 5)'"
prs: [3535, 3536, 3537, 3548, 3550, 3552]
related_adrs:
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0279-sdd-medir-governar-floor-nightly
  - 0303-anchor-wired-zombie-teste-fantasma
  - 0314-poda-gates-onda-2-lei-fusoes
---

# P14 Fase 0 — execução (2026-07-01, noite)

## TL;DR

Catraca do floor=298 agora **MORDE** no required GT-G3 (órfã materializada + fail-red `armed` sem fonte + counterfactuals no selftest 46/46); 6 required renomeados sem "(advisory)" via dança zero-window; +2 métricas armadas de carona (`n_quarantine=27`, `sqlite_corruptors=0` por fusão — sem gate novo). 2 sessões paralelas coordenadas, 6 PRs, tudo mergeado na mesma noite.

## Resultado

**Resultado:** o defeito nº 1 da [avaliação adversarial 67/100](2026-07-01-sdd-avaliacao-adversarial.md) está fechado. O check required `SDD scorecard ratchet (GT-G3)` agora **enxerga a fonte do floor** (materializa a órfã `governance/nightly-floor` antes do `--ratchet`) e **avermelha em vez de pular** quando uma métrica armada vem sem fonte (`armed:true ∧ ¬measured ⇒ exit 1`). Floor=298 deixou de ser lei só no papel.

## Duas sessões paralelas, coordenadas sem duplicação

Wagner replicou o prompt; detectei a sessão irmã (nostalgic-matsumoto) já com fix (a)+(b) no diff e **dividi o escopo em vez de correr por cima** (nota de memória "sessões paralelas na mesma branch" aplicada):

| Entrega | PR | Sessão |
|---|---|---|
| Core (a) materialização HARD no `sdd-scorecard-ratchet.yml` + (b) fail-red `sdd-scorecard.mjs` + (c) 2 catracas floor no gate-selftest + (e) `.gitignore` coverage | [#3536](https://github.com/wagnerra23/oimpresso.com/pull/3536) | paralela |
| Rename (d) PR-1 — 6 shims (`needs`+`if:always()`) sob o nome definitivo | [#3535](https://github.com/wagnerra23/oimpresso.com/pull/3535) | esta |
| Rename (d) flips — swap direto no vivo (velhos out, novos in; shims seguraram = zero window) | gh api | Wagner/paralela |
| Rename (d) PR-2 — jobs renomeados + shims fora + `required-checks-baseline` swap + watchdog P14b | [#3550](https://github.com/wagnerra23/oimpresso.com/pull/3550)+[#3552](https://github.com/wagnerra23/oimpresso.com/pull/3552) | paralela (meu #3551 consolidado no par tamper-safe — dup-detector pegou, Dedup-ack coordenado) |
| Carona 1 — `n_quarantine` ARMADO (27, `valid_measurements:8`) | [#3537](https://github.com/wagnerra23/oimpresso.com/pull/3537) | esta |
| Carona 2 — `sqlite_corruptors` ARMADO (0) — **fusão no GT-G3 required, sem gate novo** (lei ADR 0314) | [#3548](https://github.com/wagnerra23/oimpresso.com/pull/3548) | esta |

## Evidência (counterfactuals, não narração)

1. **Checkout sem a órfã → `--ratchet` exit 1** acusando `full_suite_pass_rate` com o one-liner de materialização; materializada → exit 0. Provado live nesta sessão (e o primeiro try provou o BUG antigo por acidente: rodei no checkout defasado pré-#3536 e o ratchet PASSOU em silêncio — a demonstração perfeita do defeito).
2. **gate-selftest 46/46** (23 catracas × good/bad): `sdd-scorecard-floor` (bad 299>298 morde), `sdd-scorecard-floor-ausente` (bad fonte-ausente morde), `sdd-scorecard-corruptors` (bad `Schema::drop('business')` não-guardado tier S → 1>0 morde) — as 3 pelo caminho REAL do `b.armed===true`, sem `SDD_RATCHET_ARM`.
3. **Zero required com "(advisory)" no nome** (gh api live) e `protection-drift` **sem nenhum 🔴** pós-#3550 (restam 🟡 de watchdog não-mapeado que o P14b do #3552 fecha).
4. **Auditoria anti-auto-sabotagem da fixture**: a fixture corruptora como `.php` real sob `tests/` era contada pelo auditor DO REPO (`corruptors: 1`) e avermelharia a métrica armada contra si mesma — vive como `.php.txt` e vira `.php` só no sandbox (`corruptors: 0` pós-rename, provado). REGRA DURA do `tests/governance-fixtures/README.md` respeitada.

## Lições

- **Squash + branch empilhada = PR CONFLICTING silencioso**: #3548 nasceu empilhado na branch original do PR A; quando o #3536 entrou por squash, o GitHub marcou DIRTY. Fix: `git rebase --onto origin/main <head-antigo>^` (1 commit, limpo).
- **`baseline-tamper-guard` morde rename legítimo** se o swap do `required-checks-baseline` vier pareado com código — o caminho certo foi o da sessão paralela: baseline isolado (#3550) + código (#3552). O escape `BASELINE-ABSORB` existiria, mas isolar é mais limpo que justificar.
- **dup-detector funcionou em produção real**: pegou #3551×#3552 tocando os mesmos 3 workflows entre sessões paralelas e forçou a coordenação (Dedup-ack + divisão de escopo).

## Residual honesto

- `full_suite`/`n_quarantine`/`sqlite_corruptors` medidas mas sem watchdog mapeado (🟡) — P14b no #3552 (em CI ao fechar esta sessão).
- Runs locais de `--ratchet` exigem materialização manual da órfã (a mensagem de erro traz o one-liner).
- Write-side CT100 (deploy key) segue não-verificado pelo required — residual perene do transporte ADR 0279, fora de escopo.
- **Risco red-until-fixed coletivo ATIVO e consciente** (decisão Wagner nº 3 do plano): floor noturno >298 agora trava TODOS os merges até descer ou subir baseline via PR visível. Trajetória 274→295→298 torna provável. É a catraca funcionando.
