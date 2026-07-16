---
slug: 0230-metodo-governance-scorecard
number: 230
title: "Método Governance Scorecard — pontuar regras vs estado-da-arte + anti-regressão justificada + rastreabilidade teste→memória"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
proposed_at: "2026-05-28"
decided_at: "2026-05-28"
module: governance
quarter: 2026-Q2
tags: [governance, scorecard, score-as-code, anti-regressao, ratchet, rastreabilidade, rtm, benchmark, pensamento-estruturado, agent-reliability]
supersedes: []
related:
  - 0160-governance-v4-scoped-scorecards-buckets
  - 0153-module-grade-rubrica-v1
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0195-feedback-relevance-scoring-decay-adaptativo
authors:
  - W
  - C
---

# 0230 — Método Governance Scorecard

## Contexto

O oimpresso já tem um **fundamento de pensamento estruturado**: pesquisar os melhores do mundo → comparar → dar **nota** → procurar quem se destaca → roadmap pro topo. Esse método está implementado em `module-grade-v4` ([ADR 0160](0160-governance-v4-scoped-scorecards-buckets.md)) — score-as-code baseado em **Cortex / OpsLevel / Port / Backstage Soundcheck** (pesquisa canônica em `memory/sessions/2026-05-16-arte-scorecards-alta-2026-benchmark.md`).

**O problema (sessão 2026-05-28):** esse fundamento **nunca foi aplicado à governança das REGRAS do agente** (PROTOCOLO-WAGNER-SEMPRE R1-R12 + Tier 0). A camada de "validar as próprias regras" foi medida em **38/100** — a mais baixa de todas: só 2 de 12 regras tinham teste, **nenhuma** tinha nota vs estado-da-arte. Resultado concreto: o agente deletou memória canônica, abriu PRs sem aprovação e afirmou fatos sem verificar — porque as regras eram **orientação** (skill), não **enforcement medido**. Wagner: *"a fundação de grade comparativa dos melhores com notas se perdeu — isso é fundamento de pensamento estruturado."*

## Decisão

Adotar o **Método Governance Scorecard** — o mesmo lineage do `module-grade-v4`, aplicado à governança, com **4 etapas** + **2 invariantes** que faltavam.

### As 4 etapas (toda dimensão de governança passa por elas)

1. **Dividir em etapas.** Decompor a governança em unidades pontuáveis (cada regra R1-R12, cada princípio Tier 0). Uma unidade = uma "regra avaliável".
2. **Classificar pontuando.** Nota `0-100` weighted por sub-dimensão (detecção · enforcement por máquina · cobertura · recuperação · verificabilidade do próprio gate). **Paired indicators** anti-gaming (herdado de [0160](0160-governance-v4-scoped-scorecards-buckets.md)). Níveis legíveis estilo OpsLevel/Port (Bronze/Silver/Gold ≈ <50 / 50-79 / ≥80). **Score-as-code:** a rubrica vive em YAML versionado (`memory/scorecards/governance.yaml`), não em código.
3. **Pesquisar quem é o melhor — E POR QUÊ.** Cada regra é comparada com **≥3 best-of-class do mundo** (ex: Anthropic Constitutional AI, NIST AI RMF, OPA/Cedar, LangGraph/OpenAI HITL, Letta/Zep memória), **sempre registrando a JUSTIFICATIVA do porquê eles são melhores** — não basta citar, tem que explicar o mecanismo. Operacionalizado pelo agente `audit-research-expert`.
4. **Roadmap pro ponto mais alto.** Gaps priorizados por impacto×esforço → ondas → meta por dimensão (aspiracional ≥95, calibrada pelo case OpsLevel 22→89%). Decisão explícita CONSOLIDAR (paradigma certo, fechar furos) vs EVOLUIR (mudar paradigma).

### Invariante A — Anti-regressão JUSTIFICADA (ratchet com medição)

> **Toda evolução cria um teste anti-regressão que calcula POR QUE não se pode voltar ao estado anterior já estudado e medido.**

- O baseline é uma **catraca (ratchet)**: a nota só sobe. Igual aos baselines `eslint`/`phpstan` do projeto ("falha só em regressão vs baseline").
- Mas o teste não é só pass/fail — ele carrega a **justificativa medida**: `{nota_antes → nota_depois, incidente/custo que motivou, data}`. Ex: *"R9 subiu 33%→100% após o incidente 2026-05-28 (agente deletou ADR canônica); voltar reabre o vetor de perda de conhecimento — custo medido: perda irreversível de decisão."*
- Inspiração: Braintrust (eval bloqueia merge se qualidade cai vs baseline) + ratchet de baselines do próprio projeto.

### Invariante B — Rastreabilidade TESTE→MEMÓRIA (RTM)

> **Todo teste/caso da grade cita a MEMÓRIA que o originou — pra que, se precisar, se ache o contexto correto do porquê foi feito.**

- Cada caso de teste tem um campo `origin`: o ADR, a sessão, ou o incidente que o gerou (ex: `origin: "incidente 2026-05-28 PR #1908 — merge sem aprovação"`, `origin: "ADR 0094 Art.3 append-only"`).
- É uma **Requirements Traceability Matrix** viva: teste ↔ regra ↔ memória de origem. Se um teste falhar e ninguém lembrar por que existe, o `origin` leva ao contexto.
- **Os testes se BASEIAM na memória rica** (sessões de auditoria, incidentes, ADRs) — não em casos inventados. Memória rica = a fonte; o teste = a guarda executável dela.

### Auto-aprimoramento

O método se reavalia: revisão periódica da rubrica (a própria grade tem nota), e adoção da tendência **AI-driven scorecard** (LLM lê código+telemetria e atualiza score — Cortex MCP). A rubrica YAML evolui por PR (score-as-code), nunca editando Service.

## Onde vive

| Peça | Local | Indexado MCP? |
|---|---|---|
| **O método** (este ADR) | `memory/decisions/0230-*.md` | ✅ |
| **A rubrica** (score-as-code) | `memory/scorecards/governance.yaml` | (a criar) |
| **A grade executável** | `.claude/governance-eval/grade.mjs` | (código) |
| **Os testes anti-regressão** | `.claude/hooks/*.test.mjs` + casos com `origin` | (código) |
| **A memória rica de origem** | `memory/sessions/2026-05-28-licoes-*` + as 4 auditorias | ✅ |

## Consequências

### Positivas
- **Regra deixa de ser opinião** — vira nota medida vs os melhores, com roadmap. Pensamento estruturado restaurado.
- **Skill só pode ser rebaixada com teste de regressão de governança verde** (a regra sobrevive sem ela) — fecha o erro do [ADR 0225](0225-skills-tier-a-recalibracao-claude-4.8.md).
- **Anti-regressão justificada** impede "desfazer" melhorias sem medir o custo — a catraca só sobe.
- **Rastreabilidade** garante que nenhum teste vira órfão sem contexto.

### Negativas / custo
- Cada regra exige uma rodada de pesquisa (audit-research-expert) — custo de tokens. Mitigação: roda 1× por regra + revisão trimestral, não a cada sessão.
- Rubrica YAML + grade exigem manutenção. Mitigação: score-as-code = PR review, barato.

### Neutras
- 4 dimensões já pontuadas (R1=58, R9=68, R10=58, meta=38) — baseline inicial gravado em 2026-05-28. R2-R8 + R12 entram nas próximas ondas.

## Estado da arte (fontes)

- **Score-as-code / scoped scorecards:** [Cortex](https://www.cortex.io) (`.cortex/scorecards/*.yaml`, MCP AI-driven), [OpsLevel Rubric](https://www.opslevel.com/product/maturity/rubric-and-scorecards) (Bronze/Silver/Gold, passa-todos-pra-subir), [Port](https://docs.port.io/scorecards/overview/) (rules+levels, GitOps), [Backstage Soundcheck](https://backstage.spotify.com/) (adoção real ~10% → motiva scoped).
- **Ratchet/baseline:** eslint-baseline + phpstan-baseline do próprio projeto; Braintrust (eval bloqueia regressão).
- **Rastreabilidade:** Requirements Traceability Matrix (RTM) — teste↔requisito como living document.
- **Benchmark de governança de agente (auditorias 2026-05-28):** Anthropic Constitutional AI, NIST AI RMF 600-1, OPA/Cedar, LangGraph/OpenAI HITL, Letta/Zep/MemGPT, NeMo Guardrails.
- **Fundação interna:** [ADR 0160](0160-governance-v4-scoped-scorecards-buckets.md) module-grade-v4 + `memory/sessions/2026-05-16-arte-scorecards-alta-2026-benchmark.md`.

## Validação

- Grade executável `.claude/governance-eval/grade.mjs` roda os exemplos reais contra os hooks atuais — rodada inicial 2026-05-28: 7/12 casos protegidos (58%), expôs os furos R9 (rm/mv de canon) e R10 (escopo de aprovação).
- Teste de regressão de governança `block-pr-without-approval.test.mjs` prova que R10 sobrevive sem a skill (9/9).
