---
tipo: proposta-promocao-required
gate: "baseline-tamper-guard (anti-grandfather)"
status: proposta
proposto_por: [C]
proposto_em: "2026-07-02"
decide: [W]
related_adrs: [0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes, 0273-anchor-spec-codigo-formato-canonico-fluxo-novo, 0263-require-safe-gates, 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura]
---

# Proposta — promover `baseline-tamper-guard` a required (fechar o furo "no-new-lie sobre guard não-required")

> Origem: risco sistêmico nº2 da [avaliação adversarial SDD 2026-07-02](../../sessions/2026-07-02-sdd-avaliacao-adversarial-pos-balde-d.md). Wagner aprovou atacar ("1 e 2"). Este doc é a proposta com evidência; **o flip da branch protection é clique do Wagner** (ADR 0275 §5 regra 3).

## Problema (o furo)

O gate **required** `anchor entry/covers gate` só morde "mentira nova" porque grandfathera 655 US legadas via `governance/anchor-entry-baseline.json`. A **única** defesa contra injetar grandfather novo (mentira nova) nesse baseline é `baseline-tamper-guard.mjs` — que exige o trailer auditável `BASELINE-GROW` pra crescer a lista e `BASELINE-ABSORB` quando o grow anda junto de código. **Mas o guard é ADVISORY, não required** (confirmado `gh api repos/.../branches/main/protection`: 0 matches `tamper`), e já falhou 2× em 2026-07-02T20:21 sem bloquear merge.

Logo: a integridade "no-new-lie" de um gate **required** repousa sobre um guard **não-required**. Um PR pode injetar chaves novas no baseline e, se o tamper-guard estiver vermelho, o merge ainda passa.

**Demonstração viva (hoje):** o backfill BALDE D (PRs #3661/#3662/#3664) *cresceu* o `anchor-entry-baseline.json` em +68 grandfather. Passou porque eu adicionei `BASELINE-GROW`+`BASELINE-ABSORB` e o guard ficou verde — mas como o guard é advisory, um PR que crescesse o baseline **sem** os trailers (ou com o guard vermelho) teria mergeado igual. O guard funcionou por conformidade voluntária, não por enforcement.

## O que já foi feito nesta proposta (arma o gate — pode landar já)

1. **Require-safe** (`.github/workflows/baseline-tamper-guard.yml`): removido o `paths:` filter → o guard passa a rodar em **todo PR** para `main`. Sem isso, promover a required trava PRs que não tocam baseline em "Expected — waiting for status" pra sempre (incidente 2026-06-08, padrão require-safe do anchor-drift). Verificado local: em PR que não toca baseline guardado → `nenhum baseline guardado afrouxado ✓` exit 0 (barato: 1 `git diff` + N `git show`).

## Critério objetivo pré-escrito pro flip (ADR 0275 §5 — tamper-guard não estava na tabela)

O §5 lista critérios pra R1/C2/T1/T2/A10/G3, mas **não** pro tamper-guard. Proponho o critério (a incorporar como emenda ao §5 no mesmo PR do flip):

| Gate | Critério objetivo pro flip |
|---|---|
| `baseline-tamper-guard` | (a) **require-safe** ativo (roda em todo PR) ✅ feito aqui; (b) `gate-selftest` prova que **morde** — fixture `bad/ledger`→exit 1, `good`→exit 0 (já no CI, 46/46 LIVE); (c) **7 dias advisory** rodando em todo PR com **0 falso-positivo** (bloqueio incorreto de PR que não afrouxou baseline). |

Justificativa do critério enxuto (vs os 14 dias do C2/A10): o guard é **determinístico e fs-puro** (compara JSON vs BASE, sem fonte externa instável nem não-determinismo) — a classe de risco que motivou "14 dias + FP<5%" (métrica recém-nascida sobre fonte instável, precedente visual-regression) **não se aplica**. 7 dias require-safe verde é suficiente pra provar que o require-safe não gera falso-positivo.

## O flip (clique do Wagner — ADR 0275 §5 regra 3)

Quando o critério bater (≥7 dias advisory verde) **e** a vaga de promoção da semana estiver livre (regra 2: máx 1/semana civil — a leva de 2026-06-30 pode ter consumido a semana atual; próxima vaga ~2026-07-07):

1. Adicionar o context **`baseline-tamper-guard (anti-grandfather)`** aos required status checks da branch protection de `main` (clique/`gh api`).
2. No **mesmo PR**, incorporar em `governance/required-checks-baseline.json`:
   - adicionar o context à lista viva (GT-G4);
   - `promocoes` += `"2026-07-NN: +\"baseline-tamper-guard (anti-grandfather)\" — fecha o furo no-new-lie do anchor-entry-baseline (guard que protege gate required estava advisory). Require-safe via este PR. Flip: Wagner (ADR 0275 §5)."`.
3. Citar este doc + ADR 0275 §5.

## Não-fiz de propósito

- **Não** editei `required-checks-baseline.json` agora: o `_meta.regra` manda atualizar "no MESMO PR do flip" — atualizar antes = drift 🔴 (json diz required, vivo não é).
- **Não** flipei branch protection: regra 3 (só Wagner).
- **Não** consumi a vaga da semana: só armei (require-safe). O flip decide a vaga.

## Risco de NÃO fazer

Enquanto advisory, o "no-new-lie" do anchor entry/covers é honra-do-autor, não lei. Um agente futuro (ou um humano com pressa) cresce o baseline sem trailer, o guard fica vermelho-advisory, e a mentira nova entra em `main` grandfatherada — exatamente o vetor que o BALDE D esbarrou hoje e passou só por conformidade voluntária.
