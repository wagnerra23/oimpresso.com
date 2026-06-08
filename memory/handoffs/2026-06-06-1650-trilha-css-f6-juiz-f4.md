---
date: "2026-06-06"
slug: trilha-css-f6-juiz-f4
tldr: "Sessão-épico da trilha CSS/design (6 PRs --admin). Consolidei o plano no MANUAL+SSOT (anti-duplicação: quase criei ADR/doc redundante — o SSOT INDEX-DESIGN-MEMORIAS já existia). F6 lint: os '17 parser_errors' eram 28 unused-disable-directives + 129 no-undef eram falsos-positivos de no-undef-em-TS → baseline ESLint 1340→1073. Destravei o juiz de design (gpt-4o→gpt-4o-mini, projeto OpenAI sem acesso ao gpt-4o) + verifiquei E2E. F4: 'unificar PageHeader' = migrar 104 telas → ratchet pra congelar o antigo (infra, sem migração visual). Lição-mãe: meu tree local (32 commits atrás + eslint Windows≠CI) me fez errar premissa 4×; worktree fiel (origin/main+npm ci) é obrigatório pra frontend."
hour: "16:50 BRT"
topic: "Trilha CSS/design — consolidação no SSOT + F6 (lint) + fix do juiz de design + F4 (ratchet PageHeader)"
duration: "~6h"
authors: [C, W]
---

# Handoff — Trilha CSS/design (F6 lint + juiz + F4)

> Sessão longa, autônoma ("vai/faça/autonomo" repetido). Pedido-raiz: *"quando você atacar CSS/design (parte da sua trilha)"* → virou a execução do roadmap de convergência CSS.

## Estado MCP no momento
- Cycle: **CYCLE-08 Receita — Onda A** (22d restantes). Esta trilha é **off-cycle** (dívida de design ≠ receita) — tratar como backlog.
- Branch worktree origem: `docs/handoff-parecer-pr2270`; trabalho landeado direto na `main` via 6 PRs.
- ⚠️ As **8 US-_DESIGNSYSTEM-019..026** (roadmap F0–F7) criadas via `tasks-create` **NÃO aparecem** em `my-work`/`tasks-list` — o write foi pro SPEC.md da cópia do servidor MCP, sync pro DB não materializou. Roadmap garantido no **MANUAL §5**.

## O que aconteceu (6 PRs, todos merged --admin)
1. **#2324** — Consolidei a trilha no [`MANUAL-CSS-JS.md`](../requisitos/_DesignSystem/MANUAL-CSS-JS.md) (v1.1, subordinado ao SSOT) + registrei no [`INDEX-DESIGN-MEMORIAS.md`](../requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md) + rule [`.claude/rules/css.md`](../../.claude/rules/css.md). **Descartei** um `PLANO-` standalone + um ADR 0251 redundante — a identidade/governança JÁ é canon (DS v6 ADR 0235/0249, git-SSOT 0239).
2. **#2326** — F6: os "17 `__parser_error__`" eram **28 unused eslint-disable directives** (`eslint-baseline.mjs:43` faz `ruleId || '__parser_error__'`). Removidas via `--fix-type directive`. Baseline 1340→1202.
3. **#2327** — F6: **129 `no-undef`** eram falsos-positivos (`React`/`EventListener`/tipos TS). Desliguei `no-undef` no bloco TS (canônico typescript-eslint). Baseline 1202→**1073**.
4. **#2328** — **Juiz de design destravado**: `PrUiJudgeAgent` estava `#[Model('gpt-4o')]` mas o projeto OpenAI dá 403 → falhava todo PR `.tsx`. → `gpt-4o-mini` + anúncio por reflexão (anti "mentira do modelo" do parecer #2270). **Verificado E2E** (dispatch #2309 → Score 100/100 approve).
5. **#2329** — `copiloto.openai.model_suggestions` default `gpt-4o`→`gpt-4o-mini` (último footgun de config; advisor/clarify já estavam ok + OFF).
6. **#2330** — **F4 "F0"**: `pageheader-gate` (ratchet) congela o `shared/PageHeader` antigo (**104 telas**); MANUAL §5 vira política incremental + números atualizados (CSS **20.294**, ESLint **1.073**).

## Artefatos gerados (canon na main)
- Docs: `MANUAL-CSS-JS.md` v1.1 · `INDEX-DESIGN-MEMORIAS.md` (+linha MANUAL) · `.claude/rules/css.md`
- Gates novos/tocados: `scripts/pageheader-migration-guard.mjs` + `config/pageheader-shared-baseline.json` (104) + `.github/workflows/pageheader-gate.yml`
- Lint: `eslint.config.js` (no-undef off em TS) · `config/eslint-baseline.json` (1073) · 17 `.tsx` (directives removidas)
- Juiz: `Modules/Jana/Ai/Agents/PrUiJudgeAgent.php` + `app/Console/Commands/UiJudgePrCommand.php` + `pr-ui-judge.yml` + test R-JANA-UI-JUDGE-002

## Persistência
- **git:** 6 PRs merged na `main` (#2324/#2326/#2327/#2328/#2329/#2330). Webhook→MCP propaga docs.
- **MCP:** 8 US-_DESIGNSYSTEM criadas (sync incerto — validar em `tasks-list` depois; senão recriar do MANUAL §5).

## Próximos passos pra retomar
```
gh pr list --repo wagnerra23/oimpresso.com --state merged --limit 6   # contexto
cat memory/requisitos/_DesignSystem/MANUAL-CSS-JS.md                   # roadmap §5 (F0–F7)
```
Pendências — **quase tudo precisa do Wagner**:
- **F4 migração visual** (104 telas antigo→canon) + **F2 token único** → exigem aprovação visual por tela (gate MWART/juiz).
- **BoletoOcr gpt-4o** (`Modules/Financeiro/Services/BoletoOcrService.php`) → OCR Vision dá 403 em prod (fallback gracioso). Ação: **conceder acesso gpt-4o ao projeto OpenAI** (dashboard), não baixar modelo.
- **F3 primitivos de layout** (`Box/Stack/Grid/...`) → redigir **ADR** antes (governança M-AP-6 "não inventar componente").
- **#2270** ainda aberto (decisão estratégica de qualidade do juiz: Sonnet c/ crédito ou gpt-4o c/ acesso).

## Lições catalogadas
- **L (mãe):** meu working-tree local estava **32 commits atrás da `main`** + **eslint no Windows diverge do CI** → errei premissa **4×** (F0 "não-feito" mas feito; parser_errors "stale" mas reais; no-undef "bug" mas falso-positivo; F4 "bounded" mas 104 telas). **Worktree fiel (`origin/main` + `npm ci`) é OBRIGATÓRIO** pra trabalho frontend/lint — só ele bate com o CI.
- **Anti-duplicação salvou 2×:** quase criei ADR 0251 + PLANO standalone; o SSOT `INDEX-DESIGN-MEMORIAS` já tinha identidade/governança. Sempre carregar o SSOT antes de criar doc de design.
- **`__parser_error__` é misnomer** no `eslint-baseline.mjs` (conta `ruleId=null`, dominado por unused-directives). Nome engana auditoria.
- O **gate é a rede de segurança**: o eslint-gate pegou minha premissa errada (#2325 fechado sem dano).

## Pointers detalhados
- Roadmap completo: `MANUAL-CSS-JS.md §5` (F0–F7) · SSOT: `INDEX-DESIGN-MEMORIAS.md`
- Parecer do juiz: `memory/handoffs/2026-06-05-1430-parecer-pr2270-julgamento-ia-design.md`
