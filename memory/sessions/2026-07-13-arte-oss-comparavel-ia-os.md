---
date: "2026-07-13"
hour: "12:20 BRT"
topic: "Existe OSS parecido com o IA-OS? Pesquisa profunda (deep-research: 105 agentes, 23 fontes, 20 claims confirmadas por votação adversarial 0 refutadas)"
authors: [C]
outcomes:
  - "Veredito: NÃO existe OSS que monte o TODO integrado dos 5 blocos; SIM existe cobertura por-peça madura"
  - "Bloco 5 (auto-aplicação recursiva) e a INTEGRAÇÃO dos 5 num loop-fechado-por-métrica = vazio de mercado (nada equivalente encontrado)"
  - "Roubar: Betterer (ratchet de referência), Conftest/OPA (policy-as-code), validar Constituição vs /speckit.constitution"
related_adrs: [0334-emenda-0330-modelo-3-camadas-anti-atrofia, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes, 0256-conhecimento-derivado-enforcado]
---

# Sessão 2026-07-13 — Existe OSS parecido com o IA-OS?

> Pergunta do Wagner após o dia de auto-medição (grade de réguas + 2 adversários + incidente GT-G3). Rodado via `deep-research` (harness fan-out + verificação adversarial 3-votos + síntese). **105 agentes, 23 fontes, 113 claims extraídas → 25 verificadas → 20 CONFIRMADAS (0 refutadas) + 5 não-verificadas** (OCR/OPA — corte de sessão às 12:30; síntese feita à mão). Run `wf_53aa5343`.

## Veredito curto

**Não existe OSS parecido com o TODO. Existe cobertura madura por PEÇA.** O que ninguém tem: o **bloco 5** (sistema medindo/corrigindo a si mesmo) e a **integração dos 5 blocos num loop fechado por métrica** aplicada a um ERP vertical multi-tenant em produção. Confirma, de fonte externa, o "à-frente-por-integração 8/8" que o adversário da grade deu em 2026-07-12.

## (a) Candidatos a "agent governance OS" completo — TODOS parciais

| Projeto | Cobre | Para em | Maturidade |
|---|---|---|---|
| **agentic-os** (KbWen) | gates de evidência por git-hook+CI (b1), skill Red-Team (b2), estado `.agentcortex/` sobrevive handoff (b3) | ratchets, multi-agente refutador/juiz, ADR append-only, scorecards, b5 | ~75★ MIT v1.8.12 jul/26 — jovem, sem produção |
| **Spec-Flow** (marcusgoll) | pipeline 6 fases (b1), voting MAKER 3-temp + quality gates bloqueantes (b2), NOTES/error-log versionados (b3) | âncora US↔código por lint, ratchets, ADR Nygard, MCP, scorecards, b5 | MIT, Claude Code |
| **GitHub Spec Kit** | `/constitution`+specify+plan+tasks+implement (b1 geração), `.specify/memory/` git (b3 parcial) | **enforcement** (é geração não gate), ratchets, adversarial, scorecards, b5 | oficial GitHub, MIT |
| **OpenSpec** (Fission-AI) | delta→spec canônica arquivada datada (b1 alinhamento + trilha) | posiciona-se **contra** phase-gates ("fluid not rigid") — oposto das catracas; b2-b5 ausentes | MIT |
| **Agent OS** (Builder Methods) | injeta standards + shape-spec | tudo mais — nem CI, nem b2-b5 | popular, escopo estreito |

Os mais próximos (agentic-os, Spec-Flow) cobrem b1+b2+b3 de forma **mais leve** e param aí. BMAD/Archon/claude-flow/task-master: mesma família SDD, não verificados a fundo (mesma classe = geração+alinhamento, não enforcement+recursão).

## (b) Por peça — o maduro que dá pra roubar

| Bloco IA-OS | OSS que cobre | Nota |
|---|---|---|
| **1 · ratchet/baseline** | **Betterer** (MIT) — snapshot `.betterer.results` versionado, melhora atualiza/piora falha. **É literalmente o nosso ratchet.** | ⭐ ler impl de referência |
| **1 · policy-as-code** | **Conftest/OPA** — validação determinística de artefatos versionados em CI (recomendação oficial OPA) | maduro |
| **2 · adversarial** | **Open Code Review** (modos AGREE/CHALLENGE/CONNECT/SURFACE — _não-verificado_); Spec-Flow MAKER voting | escopo = só code review |
| **3 · memória** | frameworks acima têm memória-em-git; **log4brains/adr-tools** pro ADR Nygard append-only | peças soltas |
| **4 · evals/observ.** | RAGAS, DeepEval, promptfoo, **Langfuse OSS**, Phoenix | maduro; **já adotado** (Langfuse) |
| **5 · auto-aplicação recursiva** | **— nada —** (pesquisa de self-evolving agents é acadêmica: Awesome-Self-Evolving-Agents, arXiv — não é ferramenta de processo) | 🔴 vazio de produto |

## (c) Roubar vs. o que não existe

**Roubar (não reinventar):**
- **Betterer** como referência do ratchet — o `sdd-scorecard-baseline.json` é um Betterer artesanal.
- **Conftest/OPA** se um dia doer manter `.mjs` imperativo em vez de policy declarativa.
- O `/speckit.constitution` do Spec Kit **valida** o conceito de Constituição — convergência independente, não estamos sozinhos no design.

**O que não existe em lugar nenhum (material de open-sourcear ao contrário):**
1. **Bloco 5 — auto-aplicação recursiva.** Nenhum OSS tem "avaliador adversarial do próprio processo" (`/sdd-avaliar`, grades de réguas, os adversários rodados contra a própria grade em 2026-07-12/13). Existe só como PAPER (self-evolving agents), não como ferramenta.
2. **Integração dos 5 num loop fechado por métrica**, num ERP vertical multi-tenant em prod.
3. **Lápides/proibições append-only** (ideias mortas que não voltam) — sem equivalente encontrado.

**Recomendação:** não adotar framework inteiro (todos mais fracos em b1-b2 e param antes de b3-b5). Roubar conceitos pontuais. Bloco 5 + integração = o diferencial publicável, se um dia quiser reputação/contribuição OSS.

## Honestidade (Tier 0)

- Síntese automática + verificação de 5 claims (OCR, OPA) caíram no **limite de sessão** (reset 12:30). As 5 estão `unverified` — tratadas como "promissor, a confirmar", não fato. Re-rodável via `resumeFromRunId: wf_53aa5343` (cache aproveita 91 agentes).
- Stars/datas são snapshot de julho/2026 (envelhece por design). 0 claims refutadas nesta rodada — mas 0-refutado num corte não é o mesmo que "tudo robusto"; a síntese não passou pelo integrador.

## Fontes-chave (23 total)
github.com/KbWen/agentic-os · marcusgoll/Spec-Flow · github/spec-kit · Fission-AI/OpenSpec · buildermethods/agent-os · phenomnomnominal/betterer · openpolicyagent.org/docs/cicd · spencermarx/open-code-review · thomvaill/log4brains · adr.github.io/adr-tooling · basicmachines-co/basic-memory · XMUDeepLIT/Awesome-Self-Evolving-Agents · getdx.com/research/measuring-ai-code-assistants · comparativos SDD (hackernoon/reenbit/medium).
