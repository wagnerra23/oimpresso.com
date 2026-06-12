---
date: "2026-06-12"
topic: "Auditoria SDD com pesquisa real 2026 — nota composta 59/100 + reclassificação independente das prioridades"
authors: [W, C]
related_adrs: ["0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento", "0271-revisao-gates-ci-estado-real-dos-required"]
prs: []
---

# Auditoria SDD 2026-06-12 — pesquisa real + reclassificação de prioridades

> Pedido do Wagner: "quero uma pesquisa real e uma reclassificação das prioridades, não considere minhas opiniões — como VOCÊ faria seu sistema? pontue e reclassifique."
> Método: 2 agents `audit-research-expert` em paralelo (13 buscas web 2025-2026 + inventário read-only do repo real). Ranking técnico independente, sem ancorar nas decisões anteriores (design-gargalo, congelamento TDAD/PBT, F1-primeiro).

## Notas (weighted, evidência no repo)

| Tema | Nota | Pior área | Melhor área |
|---|:---:|---|---|
| SDD core + knowledge lifecycle | **71/100** | Traceability & compilation **30** (`Implementado em` = 0/43 preenchido; sem spec→tasks) | Governança & enforcement **88** (memory-schema-gate, DriftChecker required — acima de Kiro/Spec Kit) |
| Verificação agêntica (testes/gates/evals) | **47/100** | TIA/coverage **8-10** (subset CI hardcoded, `coverage: none`) | Cross-tenant biz=99 **88** + no-mock ratchet **80** (defesas de incidente real, acima do mercado) |
| **Sistema SDD composto** | **≈59/100** | — | — |

A nota 90/100 de 2026-06-05 media ESTRUTURA (ter as peças). Medindo traceability ativa + freshness + correção funcional sob mudança, o número honesto é 59.

## 3 achados que mudam o quadro (descobertos na auditoria, verificáveis)

1. **A spec mente:** campo `Implementado em` = **0/43** preenchido nos SPECs. O sistema é spec-first operando como se fosse spec-anchored. arXiv 2602.00180: spec-anchored é o sweet spot pra brownfield — exige anchor + teste que falha no drift. Temos 5 das 6 pré-condições; falta ativar o anchor.
2. **A suite mente:** full-suite NÃO roda verde em DB real; `ci.yml` ancora subset manual hardcoded. `mutation-gate` é `--min=0` advisory em 1 arquivo. RAGAS canary diário compara **mock com mock** (scores fixos 0.85/0.78). Muita catraca de FORMA, pouca catraca de CORREÇÃO FUNCIONAL.
3. **O que já é best-of-class:** Check 9 anti-tautologia + biz=99 cross-tenant + no-mock ratchet + 17 required gates com enforce_admins — mais maduro que o mercado. Não investir mais aqui (saturado).

## Reclassificação das prioridades (como eu faria o sistema)

**P0 — fundação (sem isso, todo o resto é fachada):**
1. **Full-suite verde em DB real no merge** (M) — destrava TIA, mutation real, coverage. Pré-requisito declarado no próprio `mutation-gate.yml`.
2. **Coverage instrumentado (pcov) como dado de 1ª classe** (S-M) — habilitador do grafo código↔teste.

**P1 — maiores alavancas contra regressão (risco nº1 com ~78 commits/semana de IA):**
3. **TDAD / Test Impact Analysis** (M, depende de P0) — grafo código↔teste; "impactados no PR + full no merge". Evidência: −70% regressão (arXiv 2603.17973).
4. **Spec↔código anchoring + drift-em-CI** (M) — estilo Fiberplane Drift (âncora AST + provenance); preencher/gerar `Implementado em` e fazer CI gritar quando código muda sob spec. Transforma spec-first→spec-anchored.
5. **Red-first hook BLOQUEADOR** (S) — infra `block-*.ps1` já existe; nudge advisory vira gate. Prevenção na origem vs Check 9 a posteriori.

**P2 — quick wins (S, alto valor/custo):**
6. **RAGAS canário em modo REAL diário** (~$1.80/mês) — trocar mock-default por real no cron.
7. **Time-decay no recall** (ADR 0270 F4; toca Tier 0 → CT 100) — mata "responde sobre dado morto".
8. **knowledge-drift.mjs em CI + codemod identity-drift** — 39/61 módulos citam `Modules/X` inexistente; detector já lista, falta corrigir e barrar ghost novo.

**P3 — multiplicadores (depois da fundação):**
9. **Spec→tasks compilation** (estilo Kiro/Spec Kit) — `us_list` + DoD já são meio caminho.
10. **Mutation real `--min` em módulos críticos** (Fiscal, multi-tenant, FSM).
11. **PBT em invariantes de negócio** (fiscal/saldo/FSM) — dual-agent, ancorado em contrato (anti-piloto-tautológico).
12. **Porta única nos 22 módulos órfãos (ADR 0270 F1)** — importante, mas read-path importa menos que spec/teste dizerem a verdade. **Reclassificado de #1 → #12.**

## Contraste com a priorização anterior

| Antes (decisão 06-11) | Agora (pesquisa independente) |
|---|---|
| #1 F1 portas + onda 2 gates; congelar TDAD/PBT até sinal de cliente | #1 fundação de verificação (suite verde+coverage) → TDAD/anchoring; portas caem pra P3 |
| Racional: leitura é o gargalo do conhecimento | Racional: com 78 commits/semana de IA, o gargalo é PROVA de correção — conhecimento limpo não salva merge quebrado |

## Fontes principais

- arXiv 2602.00180 (Spec-Driven Development with AI — níveis spec-anchored/spec-as-source) · arXiv 2603.17973 (TDAD, −70% regressão)
- Kiro specs docs · GitHub Spec Kit (71k stars) · Fiberplane Drift (doc-rot linter AST-anchored)
- Meta ACH mutation-guided test generation (engineering.fb.com) · Infection PHP
- mem0 State of AI Agent Memory 2026 (decay/distillation) · Braintrust LLM-judge vs deterministic
- Detalhe completo (matrizes 15+17 dimensões, URLs): outputs dos agents a1384e018e3fbcb58 / ae684f1199a23961a desta sessão
