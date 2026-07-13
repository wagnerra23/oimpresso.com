---
date: "2026-07-13"
hour: "12:20 BRT"
topic: "Existe OSS parecido com o IA-OS? Pesquisa profunda (deep-research: 2 rodadas, 25 claims confirmadas por votação adversarial 0 refutadas, síntese completa)"
authors: [C]
outcomes:
  - "Veredito: NÃO existe OSS que monte o TODO integrado dos 5 blocos; SIM existe cobertura por-peça madura"
  - "Bloco 5 (auto-aplicação recursiva) + INTEGRAÇÃO dos 5 + anchor-lint código-ancorado + lápides append-only = vazio de mercado (inéditos)"
  - "Roubar: já temos equivalente de tudo roubável wholesale (Betterer/OPA/Danger/Sphinx-Needs) → migrar=retrabalho. Única peça cheap: protocolo de debate AGREE/CHALLENGE/CONNECT/SURFACE do Open Code Review, como padrão a adaptar nos workflows adversariais"
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

## ATUALIZAÇÃO 2026-07-13 14h — retomada completou (síntese + OCR/OPA verificados)

A 1ª rodada cortou no limite de sessão (12:30); retomada via `resumeFromRunId: wf_53aa5343` fechou: **25 claims confirmadas, 0 refutadas, 0 unverified, 11 achados sintetizados** (1 único erro: v1 OpenSpec rate-limit — v0+v2 passaram, claim resolveu). Duas correções materiais ao quadro acima:

**Bloco 2 (adversarial) EXISTE em OSS maduro — corrige o "só paper" da 1ª rodada:** **Open Code Review** (Apache-2.0, v2.4.0 jun/2026, ~299★, 28 personas em 4 tiers) faz revisores independentes **DEBATEREM** antes da síntese via fase estruturada `AGREE / CHALLENGE / CONNECT / SURFACE` — "pega falso-positivo que revisor único perderia" (README + paper OpenReview "Adversarial Review: Cooperative Code Review through Structured Disagreement"). É o padrão refutador/juiz do oimpresso, **escopado a code-review**, sem lápides append-only. Família acadêmica ativa (agent-as-a-judge → debate multi-agente, surveys 2508.02994 / 2601.05111) confirma consenso: avaliação por agente **COMPLEMENTA, não substitui** HITL — casa com o Wagner-approval.

**Mais OSS roubável confirmado:** **Danger JS** (MIT, convention-as-code em PR via Dangerfile) · **Sphinx-Needs** (v8.3.0 safety-critical, rastreabilidade req↔teste — mas ancora na DOC, não no CÓDIGO como o anchor-lint).

### Veredito de ROUBO (honesto, impacto÷esforço)

| Item | Existe pronto | Roubar? |
|---|---|---|
| ratchet/baseline | Betterer (~630★, match 1:1) | ❌ **já temos funcionando** (22 gates + desarme-por-PR) — trocar = reescrita sem ganho |
| policy-as-code | OPA/conftest, Danger JS | ❌ já temos (`.mjs`+hooks+trailers); migração prematura |
| rastreabilidade | Sphinx-Needs | ❌ o nosso é **melhor** pro caso (ancora no código) |
| **debate adversarial** | **Open Code Review** ⭐ | ✅ **ler+adaptar** o protocolo `AGREE/CHALLENGE/CONNECT/SURFACE` nos templates dos nossos workflows adversariais (refutador→juiz→**debate**→síntese). Barato, afia o diferencial. NÃO adotar a ferramenta (é code-review-only) |

**Conclusão de roubo:** já temos equivalente funcional de tudo que é roubável wholesale → adotar = retrabalho. A **única** peça cheap+valiosa é o protocolo de debate do OCR, como padrão a incorporar (não ferramenta a instalar). Bloco 5 (auto-aplicação recursiva) + integração dos 5 + anchor-lint código-ancorado + lápides append-only + scorecards-do-próprio-processo com paired-indicators seguem **inéditos** — material de open-sourcear ao contrário.

## Honestidade (Tier 0)

- Stars/datas são snapshot de julho/2026 (envelhece por design). Rodada completa: 25 confirmadas / 0 refutadas — robusto, síntese passou pelo integrador (11 achados alta-confiança, exceto o veredito de originalidade que é `medium`, inferência-por-ausência).
- BMAD/Archon/claude-flow/task-master não foram verificados a fundo (mesma família SDD dos verificados = geração+alinhamento, não enforcement+recursão) — se algum ganhar tração como "governance OS", re-medir.

## Fontes-chave (23 total)
github.com/KbWen/agentic-os · marcusgoll/Spec-Flow · github/spec-kit · Fission-AI/OpenSpec · buildermethods/agent-os · phenomnomnominal/betterer · openpolicyagent.org/docs/cicd · spencermarx/open-code-review · thomvaill/log4brains · adr.github.io/adr-tooling · basicmachines-co/basic-memory · XMUDeepLIT/Awesome-Self-Evolving-Agents · getdx.com/research/measuring-ai-code-assistants · comparativos SDD (hackernoon/reenbit/medium).

## ATUALIZAÇÃO 2026-07-13 — ROUBO EXECUTADO: fase Debate (OCR) adaptada ao harness

A única peça que esta pesquisa marcou como "roubar vale a pena, e é barato" — o protocolo de
discourse estruturado do **Open Code Review** (AGREE/CHALLENGE/CONNECT/SURFACE) — foi **adaptada**
(não instalada; o OCR é code-review-only) ao nosso harness `Workflow`, como fase `Debate` colável
entre a refutação e o juiz dos workflows adversariais. Isso mata a **falácia de composição** (regra
dura #7 de `reguas-do-sistema`) **por construção**: o modo `SURFACE alvo="TODO"` força cada cético a
olhar os achados JUNTOS e apontar a falha do conjunto integrado que nenhum slice isolado pega.

**Entregáveis (1 PR):**
- **Template + referência runnable:** [`.claude/workflows/debate-adversarial.js`](../../.claude/workflows/debate-adversarial.js)
  — bloco `BEGIN/END TEMPLATE` com `REACAO_SCHEMA` + `faseDebate(achados, opts)` (colável, sem
  imports) + demo barato (3 lentes → debate → juiz) que valida o padrão 1×.
- **RUNBOOK de como plugar:** [`memory/requisitos/_Governanca/RUNBOOK-fase-debate-adversarial.md`](../requisitos/_Governanca/RUNBOOK-fase-debate-adversarial.md)
  — contrato, 3 passos, candidatos, anti-padrões.

**Onde plugar (não migrar tudo de uma vez — commit-discipline):** `sdd-avaliador-processo.js`
(entre `Avaliar` e `Síntese` — o SURFACE pega risco sistêmico ENTRE os 7 streams, hoje invisível
ao juiz que só agrega); `reguas-do-sistema.js` (a Fase `Integração` já é um debate manual da mesma
falácia — migrar pro protocolo tipado unifica); `scripts/adr-0296-adversarial-*.js`. Cada migração
é um chip próprio quando doer.

**Nada rejeitado** (é adoção, não recusa — sem entrada em proibições §5). O resto do quadro de roubo
(Betterer/OPA/Sphinx-Needs/Danger) segue como "já temos equivalente → não migrar".
