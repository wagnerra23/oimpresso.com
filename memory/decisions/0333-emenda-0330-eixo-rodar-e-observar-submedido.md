---
slug: 0333-emenda-0330-eixo-rodar-e-observar-submedido
number: 333
title: "Emenda ao mapa 0330 — eixo RODAR-E-OBSERVAR sub-medido pela grade de réguas (ponto cego + 4 dimensões de medição)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: meta
decided_by: [W]
decided_at: "2026-07-10"
module: governance
quarter: 2026-Q3
tags: [governanca, reguas-do-sistema, observabilidade, drift, seguranca-agente, custo, ponto-cego, mapa]
supersedes: []
superseded_by: []
supersedes_partially: [0330-mapa-dos-niveis-estado-real-2026-07-constituicao]
related: [0094-constituicao-v2-7-camadas-8-principios, 0329-doutrina-documentacao-de-processo-executavel, 0330-mapa-dos-niveis-estado-real-2026-07-constituicao]
pii: false
review_triggers: []
---

# ADR 0333 — Emenda ao mapa 0330: o eixo RODAR-E-OBSERVAR estava sub-medido

> **Status:** `proposto` (2026-07-10, redação [CC]). Aguarda ratificação de Wagner. **Não altera nenhuma decisão da [0330] nem da [0094]** — é emenda parcial (`supersedes_partially: [0330]`, [0317]): a base permanece `lifecycle: ativo`, o corpo da 0330 NÃO é reescrito (append-only respeitado; o gate `governance-gate.yml` bloqueia edição de ADR aceito, então o mecanismo correto é a sucessora — que a própria 0330 §"Regra de manutenção" já manda usar). **Advisory / doc-only:** adiciona a MEDIÇÃO do eixo, não constrói observabilidade/drift/gate (isso é Tier-0 à parte, decisão de custo do Wagner).

## Contexto

A grade de réguas ([`reguas-do-sistema`], skill + `.claude/workflows/reguas-do-sistema.js`) mede o IA OS contra quem põe a barra do mercado. Suas 9 linhas de evolução (v1→v3) só puseram numa régua o loop de **CONSTRUIR-E-GOVERNAR**: spec-driven, design→código, fidelidade visual, crítica adversarial, frescor de conhecimento, cross-platform, governança, evals-outcome (DORA/agente-DEV) e ERP-IA-produto.

A reanálise de 2026-07-10 achou um ponto cego **estrutural**: a grade NUNCA pôs numa régua o loop de **RODAR-E-OBSERVAR a IA que o sistema produz** — a Jana viva em produção. Traces/custo/latência do agente, alucinação/drift da resposta ao cliente, defesa a prompt-injection e custo-por-tarefa como número nunca foram dimensões de medição.

**Prova de que é gap real, não teoria:** o próprio rastreador do projeto (rotina "FECHAR O LOOP DO IA-OS", audit 2026-05-29, injetada no SessionStart) marca há tempo como P0 pendentes:
- **#4 P0** — Ligar Langfuse + OTel collector (painel custo/latência/halluc);
- **#3 P0** — Drift sentinel / canary semanal (recall<80% ou halluc>5%).

O sistema já tinha a DOR catalogada, mas a régua de MEDIÇÃO que deveria mantê-la visível e comparável contra o mercado não existia. Isto é a Propriedade 5 da doutrina [0329] (auto-fresca) fechando sobre a própria grade: o instrumento de medição se corrige quando descobre que mediu de menos.

## Decisão

1. **Registrar o ponto cego** onde ele fica achável (esta ADR + emenda ao mapa via `supersedes_partially`): *o eixo RODAR-E-OBSERVAR ficou sub-medido pela grade de réguas até 2026-07-10*.

2. **Adicionar 4 dimensões de MEDIÇÃO** ao array `DIMS` do método `reguas-do-sistema` (só medir — não construir):

   | Dimensão (`key`) | O que a régua mede | Réguas de mercado | Sinal no projeto |
   |---|---|---|---|
   | `observabilidade-agente` | traces/custo/latência/alucinação do agente e da IA em prod (painel vivo, não log solto) | Langfuse, LangSmith, Braintrust, OpenTelemetry GenAI | #4 P0 "Ligar Langfuse+OTel" pendente |
   | `qualidade-drift-ia-producao` | recall/hallucination gold-set + canary de drift da **IA-produto (Jana)** — distinto de `evals-outcome` (DORA/agente-DEV) | RAGAS, DeepEval, continuous-eval | `jana-ragas-gate` JÁ existe + #3 P0 drift-sentinel pendente |
   | `seguranca-do-agente` | defesa a prompt-injection + fronteira instrução-vs-dado + permissão de tools/hooks | OWASP LLM Top 10, Anthropic agent-safety, Google SAIF | ~55 hooks + blockers Tier-0 existem, mas sem régua de injection |
   | `custo-eficiencia` | token/crédito por tarefa como MÉTRICA medida — hoje só valor cultural do Wagner, sem número | Cursor, Cognition/Devin cost-per-task | "economize crédito" é verbal, não observável |

3. A grade agora cobre **dois eixos** explicitamente (6 dims CONSTRUIR-E-GOVERNAR + 4 dims RODAR-E-OBSERVAR = 10). A distinção `evals-outcome` (outcome do agente-DEV / DORA) vs `qualidade-drift-ia-producao` (qualidade da resposta da Jana ao cliente) é intencional e fica na régua.

## Justificativa

- A grade é o instrumento que aponta "onde sou fraco vs mercado". Um instrumento que só olha metade do sistema mente por omissão — exatamente a patologia que a [0330] corrigiu para o mapa das camadas, aqui aplicada à própria grade.
- **Não é construção:** medir o eixo custa uma rodada de pesquisa+refutação+verificação; ligar Langfuse/OTel, o drift-sentinel e qualquer gate são trabalho Tier-0 com custo de infra recorrente — decisão do Wagner, fora do escopo desta ADR.
- Emenda parcial (não substituição): a 0330 segue o retrato válido das 7 camadas; esta ADR só acrescenta que a **grade que mede o sistema** tinha um eixo cego.

## Consequências

**Positivas:** a próxima rodada de `reguas-do-sistema` mede os 4 eixos novos e produz notas com evidência para observabilidade/drift/segurança/custo — os 2 P0 do "FECHAR O LOOP" ganham régua de mercado que os mantém visíveis; ponto cego fica achável (não redescobrível).
**Negativas / trade-off:** a grade fica mais cara por rodada (10 dims vs 6) — mitigado por `args.dimensoes` (rodada parcial por dimensão). Medir não fecha os P0; só os torna comparáveis.
**Riscos mitigados:** o loop RODAR-E-OBSERVAR seguir invisível na comparação contra o mercado enquanto o produto (Jana) opera em produção sem régua de qualidade/custo/segurança.

## Verificação

- `.claude/workflows/reguas-do-sistema.js` — array `DIMS` contém as 4 chaves novas (`observabilidade-agente`, `qualidade-drift-ia-producao`, `seguranca-do-agente`, `custo-eficiencia`) sob o comentário "Eixo RODAR-E-OBSERVAR".
- `.claude/skills/reguas-do-sistema/SKILL.md` — seção "Os dois eixos que a grade mede" + anti-padrão atualizado (10 dimensões).
- Rastreador SessionStart "FECHAR O LOOP DO IA-OS" — #3 e #4 seguem `[--]` (pendentes): esta ADR mede, não fecha.

## Referências

[0330] Mapa dos níveis (base emendada parcialmente — segue ativa) · [0329] Doutrina de documentação de processo executável (Propriedade 5, auto-fresca) · [0317] `supersedes_partially` (emenda sem matar a base) · [0094] Constituição v2 · rotina "FECHAR O LOOP DO IA-OS" (audit 2026-05-29) · método [`reguas-do-sistema`] (origem Wagner 2026-07-09).
