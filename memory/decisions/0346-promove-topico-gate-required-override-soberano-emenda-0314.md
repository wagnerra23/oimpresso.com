---
slug: 0346-promove-topico-gate-required-override-soberano-emenda-0314
number: 346
title: "Promove o gate Tópico a required — override soberano [W] (emenda à 0314; critérios do piloto NÃO batidos, foundation-ratchet consciente)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-07-21"
module: governance
kind: meta
supersedes: []
supersedes_partially: []
related:
  - 0238-soberania-constituicao-wagner
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0261-enforcement-faseado-gates-ci
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0327-anchor-content-required-emenda-0314
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
  - 0343-promove-adr-gate-required-emenda-0341
  - 0345-topicos-vivos-aprendizado-por-critica-revisada
pii: false
---

# ADR 0346 — Promove o gate `Tópico` a required (override soberano [W])

> Emenda à [ADR 0314](0314-poda-gates-onda-2-lei-fusoes.md) (mesmo rito da 0327/0341/0343: emenda +
> flip [W] explícito). **Diferente das anteriores em um ponto que esta ADR não esconde:** os critérios
> objetivos de promoção **NÃO foram batidos**. Esta é uma **exceção soberana consciente** ([ADR 0238](0238-soberania-constituicao-wagner.md)),
> registrada como o **anti-padrão `foundation-ratchet`** aceito de olhos abertos — não como cumprimento de critério.

## Contexto

O schema de tópico ([ADR 0345](0345-topicos-vivos-aprendizado-por-critica-revisada.md)) nasceu em
grace/forward-only em **2026-07-21** e, no mesmo dia, foi promovido a **advisory-que-morde** (removido
o `grace: true` → STRICT vermelho em tópico malformado, fora do required — [PR #4640](https://github.com/wagnerra23/oimpresso.com/pull/4640)).

A medição do piloto ([relatório](../sessions/2026-07-21-medicao-piloto-topico-flip-grace-required.md),
[PR #4638](https://github.com/wagnerra23/oimpresso.com/pull/4638)) — com **limiar objetivo pré-registrado
antes de medir** — recomendou **ESPERAR** o flip a required. Números do git:

- **3 tópicos**, todos nascidos no commit fundador da própria 0345 (PR #4617); **0 PRs posteriores** tocaram tópico.
- **0 dias** de janela advisory (aceito hoje).
- **0 mordidas** — nenhum tópico malformado jamais chegou ao gate; a única exposição foi o seed autoral (FP=0 **vacuamente**, não provado sobre autoria independente).

[W] leu o relatório e as 3 ressalvas do agente e decidiu **"flip"** — promover a required mesmo assim.

## Decisão

Promover o check **`Tópico (memory/requisitos/*/topicos/*.md)`** (matriz do `memory-schema-gate.yml`)
a **required** na branch protection de `main`. Rename do label no mesmo PR — tirou o sufixo
`[advisory]` — porque um label não declara o próprio enforcement em tempo presente ([lápide §5
2026-07-16](../proibicoes.md): *"segue advisory"/"não bloqueia" apodrece no 1º flip*; precedente P14
`renames` no baseline). `classic_protection.contexts` 31 → 32.

## Honestidade sobre os critérios (o ponto que separa esta ADR da 0343/0339)

A 0343 e a 0339 se apoiaram em **"o gate mordeu de fato"** (o check ADR validou 140 ADRs reais; os
ratchet DS tinham 1-2 fails na janela). **Esta não tem esse apoio.** Aqui:

- **Nenhum critério objetivo do calendário ([ADR 0275](0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) §5) foi atingido:** 0 dias advisory (o padrão é 14), 0 mordidas reais, 3 tópicos só do seed.
- **Nenhuma reincidência dura** (o justificador que a [ADR 0327](0327-anchor-content-required-emenda-0314.md) usou) — 0 tópicos podres na história.
- Portanto esta promoção **É** o anti-padrão `foundation-ratchet` que o [§5 proibicoes](../proibicoes.md)
  cataloga (*"armar gate que nunca mordeu, no zero-day"*). Não maquio isso: é **override soberano de [W]**
  ([ADR 0238](0238-soberania-constituicao-wagner.md)) sobre a recomendação técnica de esperar, e o §5 permite
  exatamente este caminho quando é **nova emenda + flip [W] explícito, NÃO merge no calado** — que é o que este PR faz.

**O que limita o dano** (por que o `foundation-ratchet` aqui é menos arriscado que o caso genérico da lápide):

- **Greenfield:** tópico não tem dívida de legado (diferente de reference 122/124, briefing 66/77, session 283/451 que a 0341 deixou advisory JUSTAMENTE por dívida). O required não acorda legado nenhum.
- **Schema determinístico, headless, zero-FP no válido:** AJV puro; o seed (3/3) valida limpo. Não é catraca de forma nem LLM.
- **Diff-aware + forward-only + always-run:** só valida tópico **novo/tocado**; PR que não toca `topicos/*.md` passa vacuamente (require-safe, sem deadlock — [ADR 0261](0261-enforcement-faseado-gates-ci.md)).
- **Reversível** (gate de reversão abaixo).

O risco real residual: um autor futuro escreve um tópico à mão fora do template e é **bloqueado** — que é
o comportamento **pretendido** (é pra isso que o [W] pediu o flip), mas sem a janela advisory ninguém
provou empiricamente a taxa de falso-positivo sobre autoria independente. **Se aparecer FP, o gate de
reversão abaixo rebaixa.**

## Rito (mesmo da 0327/0341/0343)

1. **Este PR:** esta emenda + rename do label no `memory-schema-gate.yml` + `required-checks-baseline.json`
   (contexts 31→32, `+Tópico (memory/requisitos/*/topicos/*.md)`, entrada em `_meta.promocoes` com a
   medição honesta) + `renames` do baseline (drop `[advisory]`).
2. **Após o merge:** flip do vivo via `gh api --input <arquivo UTF-8 sem BOM>` — **NUNCA** payload inline
   (proibições §Ambiente: shell Windows re-encoda → mojibake deadlockou merge em 2026-07-02). O context
   `Tópico (memory/requisitos/*/topicos/*.md)` tem `ó` (U+00F3) não-ASCII → a disciplina do `--input`
   gerado a partir do baseline é **obrigatória**.
3. **Validação:** `node scripts/governance/protection-drift.mjs` (baseline↔vivo string-exato) = 🟢.

## Gate de reversão

Falso-positivo (o gate bloqueou um tópico legítimo bem-formado, OU um autor legítimo é travado por dívida
de schema que a janela advisory teria pego) → rebaixar via `gh api` re-remove do context + PR editando o
baseline (32→31) + nota. Reversível 100%. Como o soak foi pulado, a barra pra reverter é **baixa de propósito**:
o 1º FP real já é sinal suficiente pra rebaixar de volta a advisory-que-morde.

## Refs

- Gate: `.github/workflows/memory-schema-gate.yml` (matriz `Tópico`, always-run + STRICT)
- Baseline: `governance/required-checks-baseline.json` + `scripts/governance/protection-drift.mjs`
- Medição do piloto: [`memory/sessions/2026-07-21-medicao-piloto-topico-flip-grace-required.md`](../sessions/2026-07-21-medicao-piloto-topico-flip-grace-required.md)
- Origem: Wagner 2026-07-21, "flip" (override explícito após o relatório ESPERAR + 3 ressalvas do agente)
