---
slug: 0231-processo-trabalho-canonico-especialista-por-area
number: 231
title: "Processo de Trabalho Canônico — dividir → especialista por área → Método Scorecard → consolidar (sempre)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-28"
proposed_at: "2026-05-28"
module: governance
quarter: 2026-Q2
tags: [governance, processo, modus-operandi, especialista-por-area, scorecard, threads, consolidacao, pensamento-estruturado]
supersedes: []
related:
  - 0230-metodo-governance-scorecard
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0160-governance-v4-scoped-scorecards-buckets
  - 0153-module-grade-rubrica-v1
authors:
  - W
  - C
---

# 0231 — Processo de Trabalho Canônico

## Contexto

A sessão 2026-05-28 contrastou dois modos de operar do agente:
- **Modo reativo** (errado): agir por reflexo, afirmar sem verificar, fazer um teste de 1, escalar sem aprovação. Produziu os erros catalogados em `memory/sessions/2026-05-28-licoes-memoria-governanca-confiabilidade.md`.
- **Modo estruturado** (certo): dividir o problema em áreas → um especialista por área (subagent) pesquisando os melhores do mundo + pontuando → consolidar. Produziu a grade de governança e o Método Scorecard ([ADR 0230](0230-metodo-governance-scorecard.md)).

Wagner: *"use esse conhecimento em cada parte das etapas que vai processar, crie especialista em cada área e consolide todos os resultados. **Sempre vai ser essas regras para poder trabalhar** — pode anotar isso?"* Este ADR é a anotação: torna o modo estruturado o **processo canônico permanente**.

## Decisão

Para toda **tarefa complexa** (auditoria, decisão custosa, problema multi-área, "como chegar ao estado da arte"), o agente segue **sempre** este processo — o Modus Operandi:

### 1. DIVIDIR em áreas/etapas
Decompor o problema em áreas isoladas e pontuáveis. Cada área = uma unidade que um especialista pode dominar.

### 2. ESPECIALISTA POR ÁREA (em threads)
Criar **um subagent especialista por área** (ex: `audit-research-expert`), rodando em paralelo (threads). Cada especialista aplica o **Método Governance Scorecard** ([ADR 0230](0230-metodo-governance-scorecard.md)):
1. pesquisar **quem é o melhor do mundo — e POR QUÊ** (≥3 best-of-class, com o mecanismo);
2. **pontuar** (nota /100 weighted, níveis Bronze/Silver/Gold);
3. **anti-regressão justificada** (baseline + a medição do porquê não pode voltar);
4. **rastreabilidade** (cada achado/teste cita a memória de origem).

### 3. CONSOLIDAR
O agente-pai integra os resultados dos especialistas num todo coerente — grade unificada, gaps priorizados, roadmap pro topo. Nunca despeja os outputs crus; sintetiza.

### Invariantes (herdados do ADR 0230, valem sempre)
- **A — Anti-regressão:** toda evolução cria teste que calcula por que não se pode voltar ao estado anterior medido (ratchet).
- **B — Rastreabilidade:** todo teste cita `origin` (a memória que o gerou) — pra achar o contexto do porquê.

### Quando NÃO aplicar
Tarefa trivial (1 edit, pergunta factual, correção mecânica). O processo é pra trabalho complexo — usar em tudo seria over-engineering.

## Vínculo — onde cada peça do conhecimento vive (fonte única)

| Peça | Papel | ADR/arquivo |
|---|---|---|
| **Método de pontuar** | etapas + score-as-code + invariantes | [0230](0230-metodo-governance-scorecard.md) |
| **Este processo** | como aplicar o método (especialista→consolida) | 0231 (este) |
| **Grade executável** | roda exemplos reais × hooks, dá nota | `.claude/governance-eval/grade.mjs` |
| **Testes anti-regressão** | guarda executável das regras | `.claude/hooks/*.test.mjs` |
| **Lineage de scorecard** | Cortex/OpsLevel/Port/Backstage | [0160](0160-governance-v4-scoped-scorecards-buckets.md) + `memory/sessions/2026-05-16-arte-scorecards-*` |
| **Memória rica de origem** | o porquê dos testes | `memory/sessions/2026-05-28-licoes-*` |
| **Princípios duros** | Tier 0 | [0094](0094-constituicao-v2-7-camadas-8-principios.md) + `memory/proibicoes.md` |

## Consequências

### Positivas
- **Pensamento estruturado vira padrão** — não depende do humor/estado do agente nem do modelo.
- **Qualidade composta** — N especialistas pesquisando os melhores > 1 agente reativo.
- **Rastreável e anti-regressivo** — toda conclusão tem origem e baseline.

### Negativas / custo
- Custo de tokens por especialista. Mitigação: só pra tarefa complexa; trivial é direto.
- Risco de over-process. Mitigação: regra "Quando NÃO aplicar".

### Neutras
- Sessão muito longa dilui a aplicação (lição da própria 2026-05-28) — preferir aplicar o processo em sessões focadas.

## Validação

Esta sessão é o caso de uso vivo: 4 `audit-research-expert` em paralelo → grade consolidada → Método 0230. O contraste (modo reativo no início → erros; modo estruturado no fim → resultado) é a evidência empírica.

## Agentes por etapa (quem executa cada parte das ondas)

| Etapa | Agente canônico | Papel |
|---|---|---|
| **Dividir** em áreas/ondas | `coordenador-paralelo` | decompõe em áreas isoladas Tier 0, sem overlap |
| **Pesquisar + pontuar** (por regra/dimensão) | `audit-research-expert` | estado-da-arte 2026 + nota %/100 + gaps + exemplos reais. Ondas grandes/estratégicas: `audit-senior-expert` |
| **Implementar / fechar o gap** | `audit-implement-expert` | código + testes Pest + RUNBOOK na área isolada, até o baseline subir |
| **Consolidar** | agente-pai (Claude principal) | integra os outputs num todo coerente (grade + roadmap) |

**Mapa por onda (R1-R12):** cada regra = 1 `audit-research-expert` (pontuar vs os melhores) → 1 `audit-implement-expert` (fechar o gap, criando o teste anti-regressão com `origin`). Orquestração via skill `/audit-and-fix` (Fase 1 research → Fase 3 implement) + `coordenador-paralelo` quando ≥3 regras em paralelo. Cada onda só fecha quando a grade (`.claude/governance-eval/grade.mjs`) sobe o baseline e não regride (Invariante A).
