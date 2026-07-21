---
date: "2026-07-21"
hour: "23:40 BRT"
topic: "Fixture não-circular do juiz funcao-scorecard — o gargalo da grade (4/10) fechado; 3 juízes cegos CALIBRADO"
authors: ["C"]
tags: [funcao-scorecard, fixture, nao-circular, calibracao, juiz, mutacao, grade, pr-4617]
outcomes:
  - "O gargalo da grade (validação-não-circular = 4/10) tem prova: 3 juízes frescos cegos = CALIBRADO, T1 100%, T2 4/4 famílias + 0 over-flag + incerto certo."
  - "Não-circular por construção: twins sintéticos + rótulo por mutação (objetivo) + juiz cego. Complementa (NÃO estende) o ledger tipo:juiz humano-só."
  - "Investigação corrigiu minha decisão: o ledger rejeita rótulo sintético DE PROPÓSITO — a fixture é mecanismo separado."
---

# Calibração não-circular do juiz `funcao-scorecard`

## TL;DR

O gargalo que a grade 2026-07-21 apontou (validação-não-circular do juiz = **4/10**, o bite-test invalidado por circular) agora tem **prova**: 3 juízes frescos **cegos** deram **CALIBRADO** contra uma fixture não-circular-por-construção — **T1 100%** (0 flips/20), **T2 4/4** famílias com o critério certo, **0 discordo** no controle, **incerto** no sem-âncora.

## Contexto

Pedido [W]: refinar o [PR 4617](https://github.com/wagnerra23/oimpresso.com/pull/4617), pontuar vs os melhores, e **construir integrando o que já existe**. A grade provou que o único gargalo real era a validação não-circular do juiz. [W]: *"investigue bem por que acho que já tenho boa parte feita, integre."* — e tinha.

## O que integrei (não reinventei)

Investigação achou ~80% pronto: padrão **corruptor** `.php.txt` (`governance-fixtures/`), contrato **bom/ruim** do `gate-selftest.mjs`, regra **`_quem_monta_nao_exibe`** do `sdd-verification-ledger`, incidentes já rotulados por teste (`IncidentValorInfladoNumUfTest`, `UpdateCrossTenantIdorTest`), e o próprio **§5 do FUNCAO-SCORECARD-METODO** que **já escrevia a spec** desta fixture.

## Correção de rota (a investigação me fez mudar de ideia — honesto)

Eu tinha decidido "estender o ledger `tipo:"juiz"` pra `label_source: mutation`". A investigação mostrou que o schema **rejeita rótulo sintético DE PROPÓSITO** (`_custo_real`: *"um modelo rotulando o veredito de outro modelo é o mesmo viés duas vezes"*). Mas o motivo é sobre **modelo-rotula-modelo** — minha mutação é **operador determinístico** (o gabarito da CodeJudgeBench). São **2 mecanismos pra 2 ground-truths**: o ledger humano pra juízo (status/prosa, sem verdade objetiva); a fixture de mutação pro defeito mecânico. **Não estendi o ledger** — a fixture é complementar.

## O mecanismo (não-circular por construção)

- **Twins SINTÉTICOS** (`tests/governance-fixtures/funcao-scorecard/twins/*.php.txt`) — código fabricado (`Widget`/`Gadget`/`PriceRow`): o juiz não pode saber a resposta do contexto do repo.
- **Rótulo = a mutação** (`manifesto-SELADO.json`, `label_source: mutation`) — objetivo, não opinião de modelo.
- **Juiz cego** — roda `--pack` (gerado sem o manifesto), nunca abre o selado.
- **Runner** `scripts/governance/funcao-scorecard-calibracao.mjs` (`--pack`/`--score`/`--selftest`) + self-test 5/5 (juiz-carimbo FALHA).

## Resultado (reprodutível do git)

`node scripts/governance/funcao-scorecard-calibracao.mjs --score tests/governance-fixtures/funcao-scorecard/calibracao-2026-07-21/judge-r{1,2,3}.json`

| Rodada | famílias | over-flag controle | incerto | falso-discordo bom | Veredito |
|---|---|---|---|---|---|
| r1 | 4/4 | 0 | ✓ | 0 | ✅ CALIBRADO |
| r2 | 4/4 | 0 | ✓ | 0 | ✅ CALIBRADO |
| r3 | 4/4 | 0 | ✓ | 0 | ✅ CALIBRADO |

**T1 test-retest = 100% (0 flips/20).** Diferente da rodada 1 (96,9% que mediu só repetibilidade): aqui mede **correção** porque o rótulo é objetivo+externo.

## Fronteira honesta

Calibra o **INSTRUMENTO** (o juiz discrimina defeito mecânico não-circularmente). **NÃO** re-valida os vereditos da função REAL (`ProductUtil`) — esses seguem o review central do 4617 + a âncora de intenção por-função do tópico. O `validation_status: invalidado` do scorecard do ProductUtil **não muda** por esta rodada; o que muda é: o juiz agora tem prova de não-circularidade.

## Próximos (follow-up)
Braço-incidente (twins dos defeitos REAIS já rotulados) · κ chance-corrected por cima do % · mais twins por critério (C4/C5/C7).
