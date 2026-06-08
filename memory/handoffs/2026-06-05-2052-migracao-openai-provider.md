---
date: '2026-06-05'
time: '20:52 BRT'
slug: migracao-openai-provider
tldr: Migração definitiva do provider LLM Anthropic → OpenAI (sem crédito Anthropic). Runtime já estava em OpenAI via config; fechados os resíduos (model_used honesto, eval/judge commands, baseline Jana) em 3 PRs merged + ADR 0252 aberta pra aprovação.
decided_by:
  - W
cycle: CYCLE-08
prs:
  - 2303
  - 2304
  - 2305
  - 2307
  - 2272
related_adrs:
  - 0252-provider-llm-default-openai-camada-a
  - 0035-stack-ai-canonica-wagner-2026-04-26
next_steps:
  - Revisar e mergear ADR PR #2307 (0252 provider OpenAI) — aprovação Wagner pendente
  - Confirmar secret OPENAI_API_KEY no GitHub Actions se ligar pr-ui-judge
  - Mergear #2272 (docs handoff parecer) — gate destravado
---
# Handoff 2026-06-05 20:52 BRT — Migração definitiva do provider LLM pra OpenAI

## Estado MCP no momento

- **Cycle CYCLE-08** — "Receita — Onda A (monetizar carteira legacy)", 23 dias restantes (18% decorrido). Nenhum goal desta sessão (trabalho foi infra/IA, não receita).
- **my-work @wagner:** 30 tasks ativas (6 REVIEW · 6 BLOCKED dormentes NFE Gold · 18 TODO). Nada desta sessão tocou backlog ativo — foi resposta a pedido cru do Wagner ("pode passar pra api da openai, foi passado pra claude mas não tem crédito").

## O que aconteceu

Wagner reportou que a conta **Anthropic ficou sem crédito** e pediu migração **definitiva pra OpenAI** no app Laravel (`laravel/ai`). Diagnóstico: o **runtime já estava em OpenAI** — `config('ai.default')='openai'`, `.env` com `ANTHROPIC_API_KEY` vazia + `OPENAI_API_KEY` válida (164 chars), e nenhum pino incondicional de Anthropic (o prompt-caching Anthropic-específico é condicional → no-op gracioso). O que restava eram **resíduos de rótulo/observabilidade + ferramental de eval**.

Fechado em 3 PRs merged + 1 ADR aberta:

1. **#2303** — `BrainBService.model_used` gravava `claude-sonnet-4-6` hardcoded mesmo rodando OpenAI; passou a resolver o provider ativo (gpt-4o-mini).
2. **#2304** — `module-grades-gate` vermelho por **drift Jana 72→71** (não-regressão real); baixou o floor no baseline pra destravar.
3. **#2305** — `eval:adr-discovery` ganhou auto-detect OpenAI; `ui:judge-pr`/`PrUiJudgeAgent` migraram `anthropic/claude-sonnet-4-6` → `openai/gpt-4o`; catraca `PrUiJudgeAgentTest` 001/002 revertida pro novo canon + workflows `ci.yml`/`pr-ui-judge.yml` atualizados.
4. **#2307** (ADR 0252, **aberta — aguarda Wagner**) — formaliza provider default = OpenAI. **NÃO supersede a 0035** (arquitetura de camadas intacta), só fixa o provider concreto da camada A.

Bônus: **#2272** (docs handoff parecer PR #2270) estava com `module-grades-gate` vermelho pelo mesmo drift Jana — destravado via `gh pr update-branch` (pegou o baseline 71 do #2304). Feito server-side de propósito: Wagner tem `MANUAL-CSS-JS.md` untracked local que um merge local sobrescreveria.

## Artefatos gerados

- `Modules/ADS/Services/BrainBService.php` (+8/-1) · `Modules/ADS/Ai/Agents/BrainBAgent.php` (docstring) — #2303
- `governance/module-grades-baseline.json` (Jana 72→71, v3.5.0) — #2304
- `app/Console/Commands/EvalAdrDiscoveryCommand.php` (+auto-detect OpenAI) · `app/Console/Commands/UiJudgePrCommand.php` (provider-aware) · `Modules/Jana/Ai/Agents/PrUiJudgeAgent.php` (openai/gpt-4o) · `Modules/Jana/Tests/Feature/Ai/PrUiJudgeAgentTest.php` · `.github/workflows/{ci,pr-ui-judge}.yml` — #2305
- `memory/decisions/0252-provider-llm-default-openai-camada-a.md` (canon path) — #2307

## Persistência

- **git:** 3 PRs merged em `main` (#2303 `117ff69`, #2304 `b4940c4`, #2305 `d683156`); ADR PR #2307 + handoff PR pushed.
- **MCP:** webhook GitHub→MCP propaga este handoff em ~2min após push.
- **BRIEFING:** não atualizado — sessão não mudou capabilities de módulo cliente-facing (infra IA).

## Lições catalogadas

- **`module-grades-gate` é frágil a drift de baseline** entre runs CI/local (Jana ±1pp sistêmico, já documentado em v3.4.7). PRs antigos (#2272, 28 commits atrás) re-disparam o gate até `update-branch`. Padrão: re-baselinar o módulo que dropou (não usar label de exceção como muleta recorrente).
- **ADR 0035 ≠ decisão de provider.** Trata da arquitetura de camadas (`laravel/ai`+Vizra+Mem0). Provider concreto vivia implícito em config/atributos — formalizado agora na 0252 sem supersede.
- **Catracas podem ser conscientemente revertidas.** `PrUiJudgeAgentTest` foi criada HOJE (parecer #2270) pra travar Anthropic; revertida no mesmo dia pro canon OpenAI com nota de auditoria.

## Próximos passos pra retomar

```
gh pr view 2307   # revisar ADR 0252 → merge (aprovação Wagner) OU pedir ajuste
```

## Pointers detalhados

- ADR 0252: `memory/decisions/0252-provider-llm-default-openai-camada-a.md`
- Config provider: `config/ai.php` (`default => 'openai'`)
- PRs: [#2303](https://github.com/wagnerra23/oimpresso.com/pull/2303) · [#2304](https://github.com/wagnerra23/oimpresso.com/pull/2304) · [#2305](https://github.com/wagnerra23/oimpresso.com/pull/2305) · [#2307](https://github.com/wagnerra23/oimpresso.com/pull/2307)
