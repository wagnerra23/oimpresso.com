---
date: "2026-07-21"
hour: "01:10 BRT"
topic: "Arrume da fixture nao-circular: twins dificeis e kappa, re-grade validacao-nao-circular de 6,5 para 7,5"
authors: ["C"]
tags: [funcao-scorecard, fixture, calibracao, kappa, grade, arrume]
outcomes:
  - "3 twins DIFÍCEIS (armadilhas) + κ chance-corrected; 3 juízes cegos passaram 6/6 famílias, κ=1, T1 100%."
  - "Re-grade honesta: validação-não-circular 6,5→7,5 (armadilhas provam discriminação real; 100% em caso óbvio provava pouco)."
---

# Arrume da fixture — twins difíceis + κ

## TL;DR

[W]: "arrume". Eu tinha deixado a fixture com twins **fáceis** (100% em defeito óbvio prova pouco) + uma pergunta em aberto. Arrumei: **3 twins DIFÍCEIS** (armadilhas onde um juiz preguiçoso erra) + **κ (Cohen, chance-corrected)** no runner. **3 juízes cegos frescos passaram: 6/6 famílias, κ=1, T1 100%, 0 falso-positivo** — não caíram em nenhuma armadilha. Re-grade: validação-não-circular **6,5 → 7,5**.

## Contexto

Depois da 1ª fixture (PR #4619, twins fáceis, non-circular 4→6,5) eu mesmo listei que 100% em caso óbvio é evidência fraca e listei gaps + perguntei. [W] cortou: **"Arrume"** — fecha, não pergunta.

## O que arrumei

**Twins DIFÍCEIS** (`t09`/`t10`/`t11`) — cada um é uma armadilha que testa uma distinção fina do §1 pós-4617:
- `t09-partial-scope-nao-tenant` — escopa por `location_id`; **parece** escopado mas não é `business_id` (location de outro tenant vaza) → C1 **discordo**.
- `t10-golden-vetor-errado` — cita um golden que cobre **SOMA**; a função faz **DIVISÃO** → C2 **discordo** ("existe golden" não basta, tem que cobrir O VETOR — o refinamento C2 do 4617).
- `t11-nullable-tipado-ok` — retorno `?Coupon` tipado = contrato explícito, **não** é o empty-string silencioso do t05 → C3 **concordo** (quem carimba todo null-on-missing erra aqui).

**κ (Cohen, chance-corrected)** no runner + bar ≥80% famílias + κ≥0,6. Self-test 5/5 (juiz-carimbo FALHA).

## Resultado (reprodutível do git)

`--score calibracao-2026-07-21/judge-hard-a{1,2,3}.json`

| Rodada | famílias | κ | over-flag | incerto | falso-discordo | Veredito |
|---|---|---|---|---|---|---|
| a1/a2/a3 | 6/6 | 1,0 | 0 | ✓ | 0 | ✅ CALIBRADO |

**T1 = 100%** (0 flips/10) no set difícil. Os 3 acertaram as 2 armadilhas (t09/t10) E não carimbaram o t11.

## Re-grade (honesta, não pra parecer melhor)

Validação-não-circular do juiz: **6,5 → 7,5.** As armadilhas + κ=1 provam discriminação REAL (não só em caso óbvio). **Falta pra 8-9** (registrado, não inflado): braço-incidente com função REAL (hoje sintética), κ vs gold HUMANO (hoje κ vs rótulo objetivo — defensável, mas a barra nomeou humano), N maior (11 twins), braço-vazado rodado (baixo valor em fixture sintética — a cegueira é por construção). Média da grade: ~6,4 → ~6,5.

## Persistência
Ledger da rodada no METODO §5 (append). O retrato formal no ledger de réguas (`memory/reguas/`) entra no próximo grade full (o mecanismo dobra re-medidas parciais).
