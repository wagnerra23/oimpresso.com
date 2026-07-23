---
id: requisitos-ads-adr-arq-arq-0007-learning-loop-tres-niveis
slug: ARQ-0007-learning-loop-tres-niveis
title: "Learning Loop — 3 níveis, cadência, gates de segurança"
status: accepted
date: 2026-05-03
deciders: [Wagner]
category: arq
module: ADS
relates_to: [ARQ-0005, ARQ-0008, ARQ-0009]
---

# ARQ-0007 — Learning Loop: 3 níveis com gates de segurança

## Contexto

Um sistema que não aprende com seus acertos e erros exige intervenção humana crescente no tempo,
em vez de decrescente. O objetivo do Learning Loop é que Wagner precise revisar cada vez menos
tarefas rotineiras, concentrando sua atenção nas decisões que realmente importam.

O aprendizado tem três níveis de profundidade, cada um com cadência e gate de segurança próprios.
Nenhum nível aplica mudanças automaticamente sem aprovação para mudanças estruturais.

## Decisão

### Nível 1 — Pattern Learning (a cada execução)

**O que faz:** registra o resultado de cada execução em Decision Memory.

**Cadência:** tempo real, após cada tarefa concluída.

**Algoritmo:**
```
task concluída
→ gravar outcome em mcp_dual_brain_decisions
→ atualizar mcp_confidence_scores[domínio][tipo]
→ se outcome == wagner_modified: extrair diff e gravar em mcp_decision_patterns
→ nenhuma mudança de threshold ainda
```

**Gate de segurança:** nenhum. Este nível só grava dados, não muda comportamento.

---

### Nível 2 — Decision Learning (semanal, sexta 18h)

**O que faz:** analisa os outcomes da semana, recalibra Confidence Engine, identifica padrões
recorrentes de erro ou acerto.

**Cadência:** semanal, integrado ao `SinteseSemanalAgent` existente.

**Algoritmo:**
```
SinteseSemanalAgent (sexta 18h):
  1. Busca mcp_dual_brain_decisions da semana
  2. Agrupa por (domínio × tipo)
  3. Para cada grupo:
     - taxa_sucesso = success / total
     - taxa_modificacao = wagner_modified / total
     - delta_confiança = calcular_delta(taxa_sucesso, taxa_modificacao)
  4. Se |delta_confiança| < 0.10 → aplica automaticamente
  5. Se |delta_confiança| >= 0.10 → cria task pendente Wagner com:
     - "Confidence de (NFSe, db_schema_change) subiu de 0.50 para 0.68 (+0.18)
       baseado em 8 execuções. Aprovar recalibração?"
  6. Identifica padrões: tipo de evento com ≥5 ocorrências e taxa_modificacao > 40%
     → gera rascunho de nova regra para Wagner revisar
```

**Gate de segurança:** delta >= 0.10 exige aprovação Wagner. Delta negativo (confiança caindo)
é sempre aplicado automaticamente (conservador).

---

### Nível 3 — Meta Learning (mensal, primeiro domingo do mês)

**O que faz:** aprende a melhorar o próprio sistema de decisão — quando chamar humano, qual
modelo usar, quais thresholds do Decision Router ajustar.

**Cadência:** mensal. Mínimo de 50 registros em `mcp_dual_brain_decisions` para rodar.

**Algoritmo:**
```
Brain B (Sonnet) com histórico de 30 dias:

  Análise 1: "Quando estamos chamando humano desnecessariamente?"
    - Eventos que foram para Wagner E ele aprovou sem modificação
    - Se taxa > 30%: propõe baixar threshold de escalação para esse domínio

  Análise 2: "Brain B está sendo usado para tarefas que Brain A poderia fazer?"
    - Eventos resolvidos por Brain B com confiança histórica > 0.80
    - Se quantidade > 20%: propõe elevar gate de Brain A para esse tipo

  Análise 3: "Qual padrão Wagner modificou mais de 3 vezes?"
    - Extrai padrão do diff
    - Propõe nova regra ALLOW_BRAIN_A ou REQUIRE_BRAIN_B no Policy Engine

  Output: task pendente Wagner com relatório completo + propostas específicas
  Nunca aplica mudança estrutural sozinho
```

**Gate de segurança (crítico):**
- Meta Learning NUNCA modifica Policy Engine diretamente
- Meta Learning NUNCA aplica mudança de threshold > 0.15 sem Wagner
- Meta Learning NUNCA promove regra para BLOCK_ALWAYS → isso é responsabilidade de Wagner
- Toda proposta tem: justificativa em dados, reversão proposta se der errado, prazo de teste

---

### Promoção de padrão para regra hardcoded

Quando um padrão aparece em ≥10 execuções com taxa_sucesso ≥ 0.80:

```
1. Learning Loop L2 detecta o padrão
2. Cria task pendente Wagner: "Padrão candidato a regra: [descrição]"
3. Wagner aprova
4. Claude Code abre PR adicionando à lista ALLOW_BRAIN_A em PolicyEngine.php
5. PR é revisado e mergeado por Wagner
```

Isso garante que regras hardcoded são criadas por evidência, não por suposição.

### O que o Learning Loop nunca aprende a fazer

- Contornar Policy Engine
- Agir em domínios BLOCK_ALWAYS com mais autonomia
- Reduzir o peso de veto humano em Decision Memory
- Aprender que "Wagner aprova tudo rapidamente" = pode ignorar a fila de revisão

## Consequências

**Positivas:**
- Autonomia cresce organicamente: em 3 meses, espera-se que 60% das tarefas sejam HiTL-0
- Wagner passa de "revisor de tudo" para "supervisor de exceções"
- Padrões que Wagner repete manualmente viram regras automáticas com base em evidência

**Negativas:**
- L3 (mensal) tem latência alta para detectar problemas sistêmicos. Mitigação: L2 semanal
  já captura anomalias de curto prazo
- Se Wagner aprova tudo sem ler por pressa, o sistema aprende padrões errados. Não há
  mitigação técnica — é responsabilidade operacional de Wagner usar os botões corretamente
