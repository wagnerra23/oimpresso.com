---
date: "2026-06-13"
topic: "Avaliação adversarial do processo SDD inteiro (scorecard ~52/100) + correção da diagnose C1 por reprodução + como o avaliador adversarial entra no fluxo permanente"
authors: [W, C]
related_adrs: ["0273-anchor-spec-codigo-formato-canonico-fluxo-novo", "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0276-decisao-pelo-fluxo-classes-pares-adversariais"]
prs: []
---

# Avaliação adversarial do processo SDD + correção C1 reproduzida + avaliador no fluxo

> Origem: noite 2026-06-13. Wagner pediu (1) status das ondas, (2) "crie um avaliador adversário de todo o processo com nota e modos de falha", (3) "como aproveitar essa análise e incluir no fluxo". Este doc é o artefato canônico do scorecard + a institucionalização do método.

## 1. Scorecard adversarial do processo — **~52/100** (composto ponderado; média simples 49)

7 streams verificados em git/gh (não no plano), por skeptics adversariais.

| Stream | Nota | Maior risco |
|---|:--:|---|
| **GT** governança | 84 | mede estrutura-pronta, não correção; G7→G8 sem row real em prod |
| **SA** anchors | 58 | DUAS definições contraditórias de `anchor_coverage` (lint 1.8% vs scorecard 2.8%), nenhuma armada |
| **Charters** | 56 | artefato sem enforcement: SPEC pode nascer sem anchor e nada barra (`v1_files=0`) |
| **KL** knowledge | 54 | os 4 renames "feitos" NÃO moveram `ghost_count` (segue 27); RAGAS baseline zerado |
| **FV** full-suite | 52 | número NÃO-determinístico (floor 1514, banda 683, faixa 1514–2197); toda catraca herda o ruído |
| **Fase 2b** harness | 31 | confunde diagnóstico mergeado com problema resolvido; Frentes A+C em 0% |
| **Semanas 4-6** promoções | 9 | a fundação que vira "required" é não-determinística E vive FORA do CI |

## 2. O que falta de ondas

- **Semana 0 (fundação) — ~80% rodou.** GT G1-G8, SA A1-A3, KL gates/baselines, Charters template. Resta: armar baselines DE VERDADE (3 runs reais, não nº-de-ADR) + atualizar skill memory-schema-preflight + regenerar scorecard.json stale.
- **Semana 1-2 (FV burn-down) — ~40%, RE-DIAGNOSTICADA.** F1/F2/Q1 infra, B1/B4 lanes, C1 (#2632) flipou MySQL e REFUTOU a triage. Q3 quarentena REVERTIDA. **Caminho crítico real = US-GOV-018, Frente A (~850) = 0% ← MAIOR ALAVANCA · Frente C = 0%.**
- **Semana 2-4 (backfill IA + identidade) — ~15%.** Infra refutador G5 mergeada (#2588) mas ledger vazio (lote IA só na máquina Felipe). SA-A4 (#2611) bloqueado em review. KL-E2/E3 parado na decisão Wagner das 34 órfãs (15min). 1-clique alto-ROI: armar baseline RAGAS.
- **Semana 4-6 (promoções a required) — 0%, integralmente bloqueado.** Zero flips. Múltiplas semanas do 1º flip (depende de tudo acima).

## 3. Top 5 riscos sistêmicos
1. **Não-determinismo do nightly contamina toda medição** (floor 1514 ± 683, executionOrder random, eixo ERROR). Foi por isso que a análise errou 3× e só a reprodução acertou. "7 nightlies verdes" sobre esse número = teatro.
2. **Gates que medem mas não mordem** (advisory-permanente: anchor-drift, foundation-ratchet, ghost-gate, scorecard, ledger-check, RAGAS).
3. **Diagnose-thrash**: diagnóstico mergeado confundido com problema resolvido; docs canon (#2631/#2635) com conclusão refutada em main. O método anti-ilusão (reprodução > análise) foi aplicado só ao diagnóstico, nunca à execução.
4. **Métrica-manchete não move com a correção** + definições contraditórias (`ghost_count`=27 pós-rename; coverage 1.8% vs 2.8%).
5. **Dependência de CT 100 / Felipe / secrets / Wagner** — "feito que depende de algo que nunca rodou" em 4 dos 7 streams.

## 4. Veredito
Estruturalmente no caminho, mas **perigosamente perto de declarar vitória sobre estrutura-pronta em vez de problema-resolvido.** Semana-0 (governança) feita com qualidade rara e auto-crítica verificável. Mas o motor de que tudo depende — **suite determinística** — está quebrado, com ~56% do vermelho (Frente A) em 0%. **Maior alavanca: consertar o harness (US-GOV-018 A+C) até a suite ser determinística e rodar DENTRO do CI** — destrava R1/T1/T2/coverage e torna cada diagnose um fato reproduzível.

## 5. A LIÇÃO-MÃE da noite (reprodução > análise)
A diagnose da Fase 2b foi corrigida **3× seguidas** — isolamento → schema-incompleto → harness/mysql-binary. Cada erro foi pego pela **revisão adversarial**; o terceiro, decisivo, só por **reprodução byte-a-byte** (DB scratch no CT 100). Três rodadas de análise minha não acharam o que uma reprodução achou em minutos. **Regra:** nenhuma diagnose vira P0/decisão custosa sem refutador (≥ modelo gerador, sessão fresca) e, quando há ambiente, sem reprodução. Vale tanto pro Fable 5 quanto pro Opus — é o seguro que cobre o gap de qualquer modelo.

## 6. COMO INCLUIR NO FLUXO (a institucionalização)
O avaliador adversarial vira parte recorrente do SDD, não um one-off:

- **Mecanismo:** workflow re-rodável [`.claude/workflows/sdd-avaliador-processo.js`](../../.claude/workflows/sdd-avaliador-processo.js) + skill [`sdd-avaliar`](../../.claude/skills/sdd-avaliar/SKILL.md) que o dispara. Verifica estado REAL em git/gh (não o plano), pontua 0-100 por stream, lista modos de falha, calcula `score_composto`.
- **Cadência (gate de processo):**
  1. **Antes de promover QUALQUER gate a required** (calendário ADR 0275) — rodar o avaliador; se o stream do gate < 70 OU o gate é advisory-que-não-morde, NÃO promove. Fecha o risco #2 e #3.
  2. **Ao FECHAR cada onda** (Semana 0/1-2/2-4/4-6) — o avaliador audita "feito-verificado vs ilusório" antes de declarar a onda done. Fecha o risco "declarar vitória sobre estrutura".
  3. **Checkpoint dated** (ex quinzenal) — re-mede o score_composto; alvo subir, não cair.
- **Saída canônica:** cada run escreve um session log `YYYY-MM-DD-sdd-avaliacao-*.md` (como este) com o scorecard. O `score_composto` pode virar a 11ª métrica do scorecard SDD (ADR 0275) — "honestidade do processo".
- **Regra de ouro herdada:** o avaliador é cético por construção (caça "a suite mente"); o refutador (G5) valida lote IA; a reprodução valida diagnose custosa. Os três são o sistema imunológico do SDD.

## Estado da noite (para retomada)
- **Em main:** Fase 2 SDD completa (#2587-2610, #2612, #2615-2630), C1 (#2632), ghost-fix (#2633), triage (#2631), US docs (#2634/#2635), Frente B config_json (#2636).
- **Trava em Wagner:** #2611 (code-owner fiscal SA-A4).
- **Em execução paralela:** Frente A do US-GOV-018 (harness — outra aba).
- **Decisões de produto abertas:** US-SELL-045 (totals morto), 046 (grade-avancada órfão).
- **Backlog:** US-SELL-047 (teste isolamento real), 048 (higiene snapshots), US-GOV-018 Frentes A/C, dívida residual ~490 (assertions+app-bugs).
