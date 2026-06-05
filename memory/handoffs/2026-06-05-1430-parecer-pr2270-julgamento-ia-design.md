---
date: "2026-06-05"
slug: parecer-pr2270-julgamento-ia-design
tldr: "Review do PR #2270 (pesquisa LLM/VLM-as-judge pra avaliar UI). Validei claim-a-claim contra código: todos confirmam. Achado que o doc subestima (G3): o juiz se anuncia 'Claude Sonnet 4.5' mas roda gpt-4o-mini (PrUiJudgeAgent sem override) → fix de consistência, não bump opcional. Wagner: NÃO mergear ainda, PR #2270 OPEN. Caminho proposto: G2+G3 no PrUiJudgeAgent (provider→anthropic/sonnet + rationale-por-dimensão antes do score)."
hour: "14:30 BRT"
topic: "Parecer de review do PR #2270 (estado-da-arte julgamento de design por IA) — validação de claims contra código real"
duration: "~1h"
authors: [C, W]
---

# Handoff — Parecer PR #2270 (julgamento de design por IA)

> Sessão curta de review (Wagner: "pode assumir o #2270" → escolheu "revisar primeiro, não mergear ainda").
> Continua em **outra sessão** no mesmo assunto complementar.

## Estado MCP no momento

- Cycle ativo: **CYCLE-08 — Receita Onda A** (2026-05-31→06-28, 18% decorrido, 23 dias restantes). Assunto deste handoff é **off-cycle** (engenharia do juiz de UI ≠ receita) — tratar como pesquisa/backlog, não US ativa.
- Branch worktree: `main` @ `334145bda`. PR #2270 vive em `claude/ai-judgment-techniques-BlL12`.

## O que aconteceu

PR #2270 é **docs-only** (1 arquivo: `memory/sessions/2026-06-05-arte-julgamento-ia-design.md`, 170 linhas, pesquisa estado-da-arte de LLM/VLM-as-judge pra *avaliar* UI). Todos os checks verdes, mergeable. Wagner pediu **review antes de qualquer merge/implementação**.

Validei os claims do doc contra o código real — **todos confirmam** (PrUiJudgeAgent=gpt-4o-mini/openai/single-shot; juiz texto-só lê diff não screenshot; juiz DESLIGADO via kill-switch; `screen:grade` command não existe; `framework-15-dimensoes.md` existe; `ui:judge-pr` existe; visual-regression existe).

## Achados que vão ALÉM do doc (entregar na próxima sessão)

1. **🔴 G3 é mais urgente do que o doc pinta** — não é "upgrade opcional", é **inconsistência ativa**: o juiz se anuncia como "Claude Sonnet 4.5" em 3 lugares mas roda **gpt-4o-mini**:
   - [`.github/workflows/pr-ui-judge.yml:1,51`](../../.github/workflows/pr-ui-judge.yml) — nome/header "Claude Sonnet 4.5"
   - [`app/Console/Commands/UiJudgePrCommand.php:103`](../../app/Console/Commands/UiJudgePrCommand.php) — `$this->info("Enviando pra PrUiJudgeAgent (Claude Sonnet 4.5)...")`
   - mas [`UiJudgePrCommand.php:264`](../../app/Console/Commands/UiJudgePrCommand.php) `new PrUiJudgeAgent` sem override → usa `#[Provider('openai')] #[Model('gpt-4o-mini')]` ([`PrUiJudgeAgent.php:53-54`](../../Modules/Jana/Ai/Agents/PrUiJudgeAgent.php))
   - **Efeito:** se Wagner ligar o juiz hoje, vê "Sonnet 4.5" na tela mas recebe gpt-4o-mini ($0.002 vs $0.034). Logo G3 é **fix de consistência**, não só bump de qualidade.

2. **🟡 G6 com framing stale** — doc diz "visual-regression é INFRA-ONLY (bloqueado por migration order)", mas [`visual-regression.yml:101`](../../.github/workflows/visual-regression.yml) mostra `SCHEMA-SQUASH MODE (US-GOV-013, supersede INFRA-ONLY)` → o blocker já foi resolvido; esforço "~6-8h" provavelmente menor.

## Decisão do Wagner

**Não mergear ainda.** PR #2270 fica **OPEN**. Wagner decide com o parecer em mãos: mergear-como-está / corrigir-doc-+-mergear / mergear-+-implementar-G2+G3.

## Próximos passos pra retomar

```
gh pr view 2270   # PR ainda OPEN, docs-only, todos checks verdes
```
Caminho proposto pela própria pesquisa: **G2+G3 juntos** (alto-impacto, baixo-esforço, sem pré-req) no [`PrUiJudgeAgent.php`](../../Modules/Jana/Ai/Agents/PrUiJudgeAgent.php) — (a) trocar `#[Provider('openai')]/#[Model('gpt-4o-mini')]` → anthropic/claude-sonnet **resolvendo a inconsistência do achado #1**, e (b) reestruturar `instructions()` pra rationale-por-dimensão ANTES do score (G-Eval).

## Lições catalogadas

- Em review de pesquisa, **validar claim-a-claim contra código** revelou um achado que o próprio doc subestimou (a "mentira do Sonnet"). Review ≠ ler bonito; é cruzar com a fonte.

## Pointers detalhados

- Doc da pesquisa: `memory/sessions/2026-06-05-arte-julgamento-ia-design.md` (na branch do PR #2270)
- Juiz atual: `Modules/Jana/Ai/Agents/PrUiJudgeAgent.php` + `app/Console/Commands/UiJudgePrCommand.php` + `.github/workflows/pr-ui-judge.yml`
