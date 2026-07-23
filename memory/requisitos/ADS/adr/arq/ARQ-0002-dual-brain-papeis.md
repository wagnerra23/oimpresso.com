---
id: requisitos-ads-adr-arq-arq-0002-dual-brain-papeis
slug: ARQ-0002-dual-brain-papeis
title: "Dual Brain — Papéis de Brain A (Ollama) e Brain B (Claude API)"
status: accepted
date: 2026-05-03
deciders: [Wagner]
category: arq
module: ADS
relates_to: [ARQ-0001, ARQ-0003]
---

# ARQ-0002 — Dual Brain: papéis de Brain A e Brain B

## Contexto

O sistema precisa de capacidade cognitiva disponível 24/7 a custo próximo de zero para tarefas
rotineiras, e capacidade de raciocínio profundo sob demanda para decisões complexas.

Usar Claude API para tudo = custo alto + latência desnecessária em tarefas simples.
Usar Ollama para tudo = qualidade insuficiente em decisões arquiteturais.

A solução é baseada na Teoria dos Dois Sistemas de Kahneman (2011):
- System 1: rápido, automático, barato — erros de confiança excessiva
- System 2: lento, deliberado, caro — erros de paralisia/over-escalation

## Decisão

O ADS opera com dois cérebros com papéis exclusivos e não intercambiáveis.

### Brain A — Ollama Local (System 1)

| Atributo | Valor |
|---|---|
| Modelo | `qwen2.5-coder:14b` (código) / `mistral:7b` (triage texto) |
| Disponibilidade | 24/7, sempre ligado na máquina Wagner |
| Custo | ~$0/mês |
| Latência | <1s |
| Implementação | Daemon Node.js `scripts/dual-brain/brain-a-daemon.js` |

**Responsabilidades exclusivas do Brain A:**
- Monitoramento contínuo: git log, laravel.log, `copiloto_memoria_metricas`
- Triage inicial de eventos (score complexidade + risco)
- Execução autônoma de tarefas `HiTL-0` (risco <0.2, confiança >0.85, Policy Allow)
- Chamar Brain B quando evento supera seu threshold

**Erro típico do Brain A:** falso negativo — confia demais em padrão conhecido quando contexto
mudou. Mitigação: Confidence Engine pesa similaridade de contexto, não só tipo de tarefa.

**Gate de uso Brain A:**
```
risco < 0.3  AND  confiança > 0.7  AND  policy ≠ BLOCK  AND  policy ≠ REQUIRE_BRAIN_B
```

### Brain B — Claude API (System 2)

| Atributo | Valor |
|---|---|
| Modelo padrão | `claude-sonnet-4-6` |
| Modelo crítico | `claude-opus-4-7` (apenas decisões irreversíveis) |
| Disponibilidade | On-demand, acionado por Brain A ou Decision Router |
| Custo | ~$0.01–0.10 por chamada com prompt caching |
| Implementação | `BrainBService.php` em `Modules/ADS/Services/` |

**Responsabilidades exclusivas do Brain B:**
- Decisões de complexidade 0.3–0.7 (domínio médio)
- Geração de instrução detalhada para Claude Code
- Code review e análise de impacto
- Preparação de resumo assistido para revisão HiTL-2
- Drafting de ADRs a partir de padrões detectados
- Síntese mensal para Meta Learning (L3)

**Erro típico do Brain B:** falso positivo — escala para Wagner quando poderia decidir sozinho.
Mitigação: Confidence Engine com histórico por domínio baixa o threshold de escalação
progressivamente conforme acertos acumulam.

**Gate de uso Brain B:**
```
(risco >= 0.3  OR  confiança <= 0.7)  AND  risco < 0.7  AND  policy ≠ BLOCK
```

**Quando usar Opus vs Sonnet:**
```
Opus apenas quando:
  - irreversibilidade = 1.0 (sem rollback possível)
  - criticidade_sistema >= 0.8 (auth, billing, LGPD)
  - Meta Learning L3 propondo mudança em Policy Engine
```

### Regra de ouro

> Brain A nunca executa o que não sabe fazer com alta confiança medida. Quando em dúvida, escala.
> Brain B nunca escala para baixo. Se Brain B recebeu o evento, entrega instrução completa ou
> cria task pendente Wagner — nunca devolve para Brain A.

### O que nenhum dos dois faz

- Modificar arquivos diretamente → isso é Claude Code
- Criar tasks no TaskRegistry sem passar pelo Decision Router → violaria auditoria
- Acessar produção (Hostinger SSH) → fora do escopo, sempre task pendente Wagner

## Consequências

**Positivas:**
- 80% dos eventos resolvidos por Brain A a $0
- Brain B acionado apenas quando necessário (~20% dos eventos)
- Separação clara evita que Brain A tente raciocinar além de sua capacidade

**Negativas:**
- Ollama exige hardware local adequado (mínimo 16GB RAM para qwen2.5-coder:14b)
- Se máquina Wagner estiver desligada, Brain A para — Brain B fica como fallback via cron
