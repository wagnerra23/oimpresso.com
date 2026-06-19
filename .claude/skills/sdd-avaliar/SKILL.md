---
name: sdd-avaliar
description: >
  Use ANTES de promover qualquer gate SDD a required (calendário ADR 0275), AO
  FECHAR cada onda do programa SDD (Semana 0/1-2/2-4/4-6), ou em checkpoint
  quinzenal de honestidade do processo — OU quando Wagner pedir "rode o avaliador
  adversarial do SDD", "nota do processo SDD", "scorecard SDD", "o que falta de
  ondas", "/sdd-avaliar". Dispara o workflow `.claude/workflows/sdd-avaliador-processo.js`
  (7 skeptics adversariais, 1 por stream SA/FV/KL/GT/Charters/Fase2b/Promoções),
  que VERIFICA o estado REAL em git+gh+MCP (não o plano), pontua sucesso 0-100 por
  stream, lista modos de falha, e escreve um session log `YYYY-MM-DD-sdd-avaliacao-*.md`.
  É o sistema imunológico do SDD: caça "a suite mente" (gate que não morde, baseline
  nunca armado, métrica de forma não de correção, feito-que-depende-de-algo-que-nunca-rodou).
---

# sdd-avaliar — avaliador adversarial do processo SDD

## Por que existe
A noite 2026-06-13 provou: análise sozinha errou a diagnose **3× seguidas**; só a
revisão adversarial + reprodução acertaram. O risco-mãe do programa SDD é **declarar
vitória sobre estrutura-pronta em vez de problema-resolvido** ("a suite mente").
Este avaliador é a defesa mecânica contra isso, recorrente — não um one-off.
Ref: [`memory/sessions/2026-06-13-sdd-avaliacao-adversarial-processo.md`](../../../memory/sessions/2026-06-13-sdd-avaliacao-adversarial-processo.md).

## Como rodar
```
Workflow({ name: "sdd-avaliador-processo" })
```
(ou via este skill quando o trigger casar). Read-only — não edita/commita.
Demora ~6-10min, ~700k-800k tokens (7 agents Opus + síntese).

## Quando rodar (cadência — gate de processo)
1. **Antes de promover gate a required** (ADR 0275 calendário): se o stream do gate
   tem score < 70 OU o gate é advisory-que-não-morde, **NÃO promove**. Fecha o risco
   "gate que mede mas não morde" + "diagnose-thrash".
2. **Ao fechar cada onda** (Semana 0/1-2/2-4/4-6): audita "feito-verificado vs
   ilusório" antes de declarar a onda done.
3. **Checkpoint quinzenal**: re-mede o `score_composto`; alvo é subir, não cair.

## O que faz (mecânica)
- 1 skeptic por stream, cada um **verifica o artefato em origin/main** (gh/git/git show)
  e **roda o script local** (anchor-lint, sdd-scorecard, foundation-ratchet, gate-selftest)
  pra medir LIVE — não confia em número de documento (que envelhece).
- Para o nightly full-suite: mede o **FLOOR** (interseção de ≥2 runs no CT 100), nunca
  1 run (o número é não-determinístico: floor ± banda).
- Saída: scorecard por stream (0-100) + etapas com nota+modo-de-falha + "o que falta de
  ondas" + top-5 riscos sistêmicos + veredito + nota composta do processo.

## Salvar a saída
Sempre escrever o scorecard num session log `memory/sessions/YYYY-MM-DD-sdd-avaliacao-*.md`
(schema session.schema.json) e commitar — é o registro de honestidade do processo ao
longo do tempo. O `score_composto` é candidato a 11ª métrica do scorecard SDD (ADR 0275).

## Trio do sistema imunológico SDD
- **sdd-avaliar** (este) — audita o PROCESSO (estrutura vs correção).
- **Refutador G5** (`PROTOCOLO-REFUTADOR-BACKFILL.md`) — valida cada LOTE IA antes do merge.
- **Reprodução** (DB scratch / counterfactual) — valida cada DIAGNOSE custosa antes de virar P0.

Regra de ouro: nenhuma onda fecha, nenhum gate vira required, e nenhuma diagnose vira
decisão custosa sem passar pelo membro certo do trio.
