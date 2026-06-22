---
date: "2026-06-22"
topic: "Anchor-fidelity brick (SA-A2-bis): wired/zombie + testado-check + reconciliação repo-wide"
authors: [W, C]
prs: [3223]
related_adrs: ["0297-anchor-lint-wired-testado-sa-a2-bis", "0273-anchor-spec-codigo-formato-canonico-fluxo-novo", "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes"]
outcomes:
  - "anchor-lint apertado: estado anchored_zombie (wired-check) + testado-check; ambos no --check (exit 1)"
  - "ADR 0297 aceita; gate-selftest cobre o detector (good/bad)"
  - "repo-wide: zombie 0, dead_anchors 0, dead_tests 0 (12 SPECs reconciliados)"
  - "2 bugs do proprio lint encontrados e corrigidos (falso-positivo de rota + falso-negativo de teste)"
  - "PR #3223 mergeado em docs/onda-0-rede-seguranca"
---

# Anchor-fidelity brick (SA-A2-bis)

## TL;DR

Wagner pediu pra "ver o SDD do Financeiro" → avaliação achou drift grave (US-FIN-013 com `verificado@` carimbado numa tela **deprecada**, ~13 testes-fantasma). Raiz: a rede de governança media mas não governava, e o `anchor-lint` só fazia `existsSync` (cego pra tela desligada). Apertamos a regra (estado `anchored_zombie` via roteador + `testado-check`), provamos por contrafactual, reconciliamos o repo inteiro (zombie/dead/dead_tests = 0) e mergeamos via [PR #3223](https://github.com/wagnerra23/oimpresso.com/pull/3223). No caminho, **apertar a regra expôs 2 bugs da própria regra** — ambos corrigidos. Resta `onda-0 → main` antes de promover o gate a `required` + re-armar baseline do scorecard.

## Por que aconteceu (o drift do Financeiro)

`anchor-lint` (ADR 0273) classificava `anchored_ok` por `existsSync` puro. Pontos-cegos:
1. **Zumbi**: `Dashboard/Index.tsx` existia no disco mas `/financeiro` virou 301→/unificado (deprecado 2026-06-06). Arquivo presente ≠ tela viva → o lint dava 🟢.
2. **`Testado em:` sem governança**: o lint só lia `Implementado em:`; os testes citados nas regras Gherkin nunca eram checados.
3. **Toda a rede era advisory** ("mede e não governa" — Onda 0).

## O que foi feito

- **anchor-lint.mjs**: `anchored_zombie` = Page existe + renderizada só por controller NÃO referenciado nas rotas (verdade vem do roteador, fs-puro). `testado-check` = ref de teste citada inexistente. Ambos no `--check` (exit 1); zumbi sai da cobertura.
- **CI** `anchor-drift.yml`: `--check` diff-aware nos SPECs tocados (no-new-lies); cron full-tree segue report.
- **gate-selftest**: fixture good/bad (`tests/governance-fixtures/anchor-lint/`) — o detector de mentira agora é testado. 12/12.
- **ADR 0297** (aceito 2026-06-22).
- **Reconciliação**: Financeiro (→ Unificado), Jana (paths moveram), Accounting/Essentials/Manufacturing/Repair/Crm/NfeBrasil/RecurringBilling/LaravelAI/_DesignSystem (testes-fantasma → real ou lacuna honesta).

## Lições / honestidade

- **MemCofre não estava morto** — virou `Modules/SRS/` (10 anchors reapontados). Premissa "descontinuado" era falsa.
- **A regra apertada pegou a si mesma 2x**: (a) falso-positivo — `renderGraph` só lia `Routes/`+`use`/`::class`; módulos com rota em `Http/routes.php` + sintaxe string (`'BoardController@index'`) davam falso-zumbi em massa (os "8 zumbis" eram bug; zumbi real = 1, o Dashboard). (b) falso-negativo — `testado-check` pulava ref path-like sem `.php`, escondendo +31 fantasmas. **Falso-positivo força distorção do dado pra agradar regra bugada = tão ruim quanto regra frouxa.**
- **Nunca inventar**: crase = só ref real; dúvida = `_pendente_`/lacuna em itálico.
- **Sessões paralelas na mesma branch**: 6 commits remotos (charter-refs) divergiram; em vez de rebase numa working-tree suja de outras sessões, abri branch nova + PR. 0296 (de outra sessão) parqueado/devolvido intacto pro regen do índice.

## Estado final

`anchor-lint --json` (full-tree): **zombie 0 · dead_anchors 0 · dead_tests 0**. `gate-selftest` 12/12.

## Pendente (atos deliberados — pós onda-0→main)

Verificado: `main` ainda tem o lint antigo (`anchored_zombie` ×0) e as SPECs sujas. Logo:
1. **Promover `anchor-drift` a `required`** só faz sentido depois que `onda-0` (lint novo + reconciliação) chega na `main` — senão roda o lint antigo / quebra PRs contra SPECs ainda sujas. Flip de branch protection = Wagner (ADR 0275 §5).
2. **Re-armar baseline do `sdd-scorecard`** (que JÁ é required na main) ao landar na main — verificar se `anchor_coverage` mudou; re-armar pra não travar PRs do time. Atenção: `governance/sdd-scorecard.json` estava com WIP de outra sessão.
