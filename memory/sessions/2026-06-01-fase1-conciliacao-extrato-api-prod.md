# Sessão 2026-06-01 — Fase 1 ADR 0236 em produção + bug CI ui:judge-pr

> De um bug de race condition num upload até uma feature em produção. Sessão longa,
> ciclo fechado fim-a-fim (código → ADR → staging → PR → CI → merge → deploy prod).

## O que entregou

| Etapa | Resultado |
|---|---|
| Bug original | Race condition no upload OFX (`ConciliacaoController::upload` check-then-insert → 500 no clique-duplo) → `insertOrIgnore` idempotente |
| Descoberta | Extrato bancário vivia em 2 tabelas separadas pelo eixo errado (origem do dado, não responsabilidade) — `fin_bank_statement_lines` (OFX) vs `fin_extrato_lancamentos` (API) |
| [ADR 0236](../decisions/0236-extrato-conciliacao-modelo-unificado.md) | Modelo unificado: origem como atributo + conciliação como camada. **Aceita** por Wagner. Plano faseado (gate por fase) |
| Fase 1 | Conciliação passa a ler/conciliar as 2 origens + coluna **Origem** (chip Banco/OFX). Migration aditiva, Tier 0 preservado. ZERO migração de dado |
| Validação | Staging (`staging.oimpresso.com`) — Wagner aprovou screenshot |
| [PR #2060](https://github.com/wagnerra23/oimpresso.com/pull/2060) | Squash em `main` (commit `5f6727ec5`). 24/25 checks verdes |
| Deploy prod | `deploy.yml` (run 26731185922) — backup + 5 migrations + assets + smoke. Migration `[164] Ran`. Colunas confirmadas no DB prod. Smoke `/financeiro/conciliacao` → 302 (não 500) |

## Problemas reais do meu código que o CI pegou (e eu consertei)

Defesa em profundidade funcionando — cada um era bug real, corrigido na raiz (não silenciado):
1. **PII scan** — CNPJ literal `12.345.678/0001-99` no teste → `00.000.000/0000-00` + `# pii-allowlist`
2. **UI Lint R1** — cor crua `bg-sky-50`/`bg-violet-50` no chip → tokens semânticos `bg-accent`/`bg-transparent`
3. **PHPStan** — `!== null` redundante após `isset()` em `normalizeApi` → removido
4. **Charter schema** — `last_validated` sem aspas (virou date) + `related_adrs` com zero-leading → string + integers
5. **MWART gate** — tela nunca teve charter/visual-comparison → criados (nome derivado do basename `Index.tsx` → `index-visual-comparison.md`, status `approved`)

## 🐞 BUG CONHECIDO — CI `ui:judge-pr` quebra com UTF-8 malformado

**Sintoma:** o check **"PR UI Judge · Claude Sonnet 4.5"** (workflow `pr-ui-judge.yml`) falhou no PR #2060 com:
```
PrUiJudgeAgent falhou: json_encode error: Malformed UTF-8 characters, possibly incorrectly encoded
##[error]Process completed with exit code 1.
```
Saiu com exit 1 em ~1s — **antes** de avaliar a UI (não calculou score). NÃO é qualidade da tela.

**Causa raiz provável:** `Modules/Jana/Ai/Agents/PrUiJudgeAgent.php` recebe o `git diff` filtrado (.tsx/.jsx/.css) no user prompt e serializa via `json_encode` pra mandar pro Sonnet. Se **qualquer byte** do diff não for UTF-8 válido, o `json_encode` aborta (sem flag de tolerância). Fontes possíveis do byte ruim: arquivo de terceiros no range do diff, ou conteúdo com encoding latin-1.

**Por que não bloqueou o merge:** o check **não é required** na branch protection de `main` (único required: `ADR frontmatter`, que estava verde). Merge via `gh pr merge --admin` com Wagner autorizando.

**Fix sugerido (quando alguém pegar):** no `PrUiJudgeAgent` (ou no `UiJudgePrCommand`), sanitizar o diff antes do `json_encode`:
- `mb_convert_encoding($diff, 'UTF-8', 'UTF-8')` (descarta bytes inválidos), OU
- `json_encode(..., JSON_INVALID_UTF8_SUBSTITUTE)` (PHP 7.2+, troca byte ruim por U+FFFD), OU
- filtrar arquivos binários/não-UTF8 do diff antes de montar o prompt.

**Refs:** workflow `.github/workflows/pr-ui-judge.yml` · `app/Console/Commands/UiJudgePrCommand.php` · `Modules/Jana/Ai/Agents/PrUiJudgeAgent.php`.

## Pendências (pós-sessão)

- **Fase 2** (ADR 0236): migração de dado `fin_bank_statement_lines` → `fin_extrato_lancamentos` (backfill idempotente + flag + canary biz=1→biz=4). Gate Wagner. Só com sinal.
- **Fase 3**: unificação de UI + deprecação da tabela antiga (ADR de deprecação dedicada).
- **Bug CI acima**: corrigir o `json_encode` do `ui:judge-pr`.
- Backup de prod pré-deploy: `~/backup-predeploy-*.tar.gz` no servidor Hostinger.
