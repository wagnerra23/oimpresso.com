---
date: "2026-07-16"
topic: "Avaliação adversarial para promover a cobertura de telas a required"
type: session
tldr: "Avaliação adversarial aprovou a promoção do screen-coverage-gate a required com score 92/100."
---

# Avaliação SDD — promoção da cobertura de telas

## TL;DR

O `screen-coverage-gate` obteve **92/100** e está apto a virar required. A promoção só foi autorizada depois de fechar três falsos-verdes: check ausente por filtro de paths, componentes auxiliares contados como telas e compensação de cobertura entre telas com total agregado estável.

O workflow automatizado citado pela skill `sdd-avaliar` (`.Codex/workflows/sdd-avaliador-processo.js`) não existe no checkout. Esta avaliação é o fallback adversarial verificável em `origin/main@ddbc3696a5`.

## Scorecard

| Critério | Peso | Nota | Evidência |
|---|---:|---:|---|
| Métrica corresponde a telas reais | 20 | 20 | Universo alinhado ao classificador visual: 234 Pages Inertia; 45 auxiliares excluídos. |
| Catraca morde regressão | 20 | 20 | Selftest bloqueia queda agregada e troca A→B com o mesmo total E2E. |
| Check sempre presente | 15 | 15 | `pull_request` sem filtro de paths desde o PR #4350. |
| Determinismo | 10 | 10 | Node 22.17.0 fixo e execução Node pura. |
| Recorrência real | 15 | 15 | Check verde nos PRs #4350, #4351 e #4352. |
| Identidade preservada | 15 | 15 | Baseline guarda conjuntos compactos e impede perder uma tela já coberta. |
| Diagnóstico e manutenção | 5 | 2 | Mensagem identifica a dimensão; ainda não lista no log cada tela removida. |
| **Total** | **100** | **92** | **APROVADO (mínimo 70).** |

## Contraprovas executadas

1. Redução de `e2e: 2 → 1` retorna regressão `e2e`.
2. Totais iguais com conjunto anterior `A|B` e atual `B|C` também retornam regressão `e2e`.
3. Execução real `node scripts/qa/screen-coverage-map.mjs --check` passa em 234 telas e baseline vigente.
4. Três PRs consecutivos publicaram o check; não há deadlock por status ausente.

## Limites honestos

- O gate protege não-regressão; não declara que 8/234 E2E ou 1/234 a11y sejam cobertura suficiente.
- Charter está em 234/234 e scorecard em 223/234; os pisos individuais não podem regredir.
- A promoção a required deve preservar todos os contextos já existentes da branch protection.

## Veredito

**PROMOVER.** O stream supera 70, possui controles negativos e evidência recorrente. Uma futura melhoria deve listar nominalmente as telas perdidas no erro, sem retirar o bloqueio atual.
