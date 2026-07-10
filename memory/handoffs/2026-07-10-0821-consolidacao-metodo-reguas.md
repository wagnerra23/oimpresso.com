# 2026-07-10 08:21 — Consolidação: método `reguas-do-sistema` + reanálise (ponto cego RODAR-E-OBSERVAR)

> Handoff append-only ([ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md)). Narrativa completa: `memory/sessions/2026-07-10-consolidacao-metodo-reguas-vivos.md`.

## Resumo (3 frases)

Wagner pediu pra canonizar "como pesquisar e documentar as réguas do sistema" — virou skill+workflow `reguas-do-sistema` (PR #4050, MERGED), irmão do `capterra-senior` apontado pro processo. Ao pedir reanálise, achamos que a grade de réguas tinha ponto cego estrutural: mediu só a fábrica (construir-e-governar), nunca o produto (a Jana rodando — observabilidade/drift/segurança do agente/custo). **7 dos 9 chips despachados na sessão fecharam e mergearam** durante o próprio fechamento, incluindo a emenda ao mapa dos níveis (ADR 0333) e o upgrade do pr-critic.

## O que mudou (PRs MERGED confirmados via `git log origin/main`)

| PR | O quê |
|---|---|
| #4050 | Método `reguas-do-sistema` (skill Tier B + workflow) — canoniza o ciclo MEDIR→VERIFICAR→CORRIGIR→TRAVAR→OPERAR |
| #4064 | **ADR 0333** — emenda ao mapa 0330: eixo RODAR-E-OBSERVAR sub-medido + 4 dimensões adicionadas ao método |
| #4058 | pr-critic ganha lentes-diversas (verificação por perspectivas distintas) + medidor de precisão |
| #4042 | Proto-baselines operacionalizados (Sells+Compras reais) + nudge de compare no design-memory-gate |
| #4053 | DORA + flag ADR-pendente no Daily Brief |
| #4045 | `rb:backfill-gateway` — 109 assinaturas dormentes (default dry-run; execução real é decisão Wagner) |
| #4048 | map.json Fase 4 (consome) + sha por conteúdo |
| #4043 + #4051 | Sinal "servido" (coleta de hits por rota, flag OFF) + lints consomem o ledger |
| #4047 | RUNBOOKs Jana (chat/cockpit) — frescor V2, refs corrigidas |
| #4044 + #4066 | Feature-trio Kiro-style (requirements/plan/tasks + blocked_by) — piloto US-RB-052 |
| #4046 / #4049 / #4052 | Hooks .ps1→.mjs: pii-redactor, block-destructive, block-memory-drift |
| #4039 / #4040 / #4054 / #4055 / #4059 | Ratificações: 0299, 0314, importers 0332, 0319, +16 ADRs lei-viva |
| #4062 / #4063 / #4065 | Housekeeping: regen `_SKILLS-INDEX.md`, fix ghost-ref MOD_REF_RE, baseline anti-ghost Infra |
| #4061 | Re-destila Jana+RecurringBilling (distiller_freshness volta a 0) |

## Pendente / não fechado

> **Correção 2026-07-10 (reavaliação pós-outage, worktree `reavaliar` @ origin/main fresco):** este handoff afirmava que a auditoria de segurança do agente "não apareceu". **ERRADO** — ela apareceu e mergeou como **#4070** (`prompt-injection-corpus` — 1º red-team do agente, advisory) + session log `memory/sessions/2026-07-10-arte-seguranca-agente.md` + `.claude/governance-eval/prompt-injection-corpus.mjs`. O claim era stale (escrito durante o outage sem re-verificar o main). Fica registrado como o próprio tipo de erro que a sessão combatia — pego na reavaliação, não confiando na memória.

- **ADR 0333** (emenda RODAR-E-OBSERVAR ao mapa 0330) nasceu `proposto` e **segue proposto** — aguarda ratificação [W] (flip in-place, merge = ato). Não conta no check-C da sentinela ainda (é de hoje). Já ratificados na onda: 0299, 0314, 0319, 0332, 0334 + 16 lei-viva.
- **#3/#4 do loop IA-OS** (drift-sentinel Jana recall<80%/halluc>5% + Langfuse/OTel) seguem **P0 pendentes** — custam infra recorrente, decisão é do Wagner (hook da sessão obriga perguntar antes de começar). O #4070 abriu a dimensão de segurança (red-team advisory); o drift-sentinel de QUALIDADE (recall/halluc) é complementar e continua não-feito. Recomendação: #3 primeiro (mais barato, é o "próximo pendente" da rotina).
- PRs antigos ainda abertos sem relação com esta sessão: #3906 (DRAFT, precisa e2e CT100), #3914/#3916 (aguardam Wagner), #3986/#3987/#3994 (visreg/financeiro).

## Estado MCP no momento do fechamento

- `git log origin/main` HEAD: `9a9ecc2789` (re-destila Jana+RB, #4061).
- PRs abertos residuais (não desta sessão): #3906, #3914, #3916, #3986, #3987, #3994, #4055 (ratificação 0319, pode já ter fechado), #4066 (complementos feature-trio, aberto).
- Brief (lido no início da sessão): HITL pending 2 (runbook on-prem, FIN-004 cobrança ROTA LIVRE) — inalterado por esta sessão.

## Lições

- **Ciclo > checklist.** "Deixar sempre arrumado" não é uma lista de réguas — é MEDIR (periódico) + TRAVAR/APONTAR (contínuo, já máquina) fechando em loop.
- **A régua da fábrica não vê o produto.** Ponto cego de dimensão inteira sobrevive a 3 rodadas de grade até alguém perguntar "faltou o quê" de novo — vale reforçar o hábito de completeness-critic sobre o próprio método de medição.
- **Outage do classificador (Opus) bloqueou Bash/Write ~15min** durante o fechamento; sessão trocou pra Sonnet 5 e resolveu. Nenhum dado perdido — os PRs dos chips já tinham salvo o trabalho de código antes do handoff ser escrito.

## Próxima sessão — começar por

1. `brief-fetch` + `my-work` (padrão).
2. Ratificar **ADR 0333** (RODAR-E-OBSERVAR, ainda `proposto` — flip in-place + label `adr-metadata-normalization`, merge [W] = ato).
3. Se Wagner topar custo de infra: chip #3 (drift-sentinel Jana recall/halluc) primeiro, depois #4 (Langfuse/OTel). O red-team de segurança (#4070) já rodou — próximo passo lá é rodar o corpus em cadência e ampliar os vetores.
4. Método `reguas-do-sistema` (#4050) pronto pra próxima grade — agora com as 11 dimensões (as 4 do RODAR-E-OBSERVAR + inteligência-de-negócio já embutidas via #4064/#4066).
