---
name: commit-discipline
description: Use ANTES de git commit ou git push em qualquer PR do oimpresso. Garante 1 PR = 1 intent, ≤300 linhas, conventional commits format, refs sprint/cycle, sem PII em código/log/commit message. Tier A always-on — princípio duro #5 da Constituição v2 (ADR 0094) + critério Anthropic 2026 best-practice.
tier: A
tier_enforce: hook-pre-commit
parent_adr: 0095
related_adrs: [0094, 0093]
---

# commit-discipline — Tier A always-on

> **Quando ativar:** ANTES de qualquer `git commit`, `git push`, `gh pr create`, `gh pr merge`.

## Princípios duros (Constituição v2)

1. **1 PR = 1 intent.** Não misturar refactor com feature, fix com docs.
2. **≤300 linhas diff** preferível. Acima disso, justificar (refactor amplo planejado).
3. **Conventional commits format:** `type(scope): description`
4. **Refs cycle/sprint** quando aplicável: `Refs: SPRINT-N PASSO M` ou `Refs: COPI-NN`
5. **Zero PII em commit message/code/log.** Use `[REDACTED]` ou `PiiRedactor`.

## Tipos canônicos (conventional commits)

```
feat(scope):     nova funcionalidade
fix(scope):      correção de bug
docs(scope):     documentação
refactor(scope): mudança de código sem alterar funcionalidade
test(scope):     adicionar/corrigir testes
chore(scope):    atualizar dependência, limpeza
build(scope):    build system, CI
perf(scope):     melhoria de performance
revert:          revert de commit anterior
```

Scope típico oimpresso: `jana`, `repair`, `nfe-brasil`, `recurring-billing`, `governance`, `ct100`, `mcp`, `claude-md`, `s3` (sprint), etc.

## Pre-commit checklist obrigatório

- [ ] **1 intent:** posso descrever em 1 sentença? Se "e" mais de 1×, separar em 2 PRs
- [ ] **Diff ≤300 linhas:** `git diff --stat HEAD | tail -1`. Se >300, justificar no body
- [ ] **Sem PII:** `git diff | grep -iE 'cpf|cnpj|@.*\.com|\d{2}.\d{4,}' | head -5` deve não retornar dados reais
- [ ] **Sem secrets:** `git diff | grep -iE 'sk-|api_key|password|token=' | head -5`
- [ ] **Sem `.env` ou `composer.lock`** se não for objetivo do PR
- [ ] **Test plan no body:** se feature/fix, descrever como validar
- [ ] **Refs sprint:** se trabalho de sprint ativo, citar passo

## Anti-padrões (NÃO fazer)

- ❌ "fix various stuff" (sem scope, sem intent claro)
- ❌ Commit gigante (>1000 linhas) sem justificativa
- ❌ `git add .` sem revisar (pode pegar `.env`, build artifacts)
- ❌ Force push em main (use `--force-with-lease` em branches só)
- ❌ Skip hooks (`--no-verify`) sem ADR justificando
- ❌ Mergear PR sem o autor (Wagner ou tech lead) ter visto
- ❌ Branch `claude/<slug>` ter mais de 1 PR aberto (cascata virar pesadelo)

## Como o hook PreToolUse enforça

`.claude/hooks/commit-discipline-check.ps1` (configurado em `.claude/settings.json`):
1. Em `git commit` ou `git add`: roda `git diff --cached --stat` e alerta se >300 linhas
2. Procura PII com regex CPF/CNPJ/email no diff staged
3. Procura secrets (sk-*, api_key, password=)
4. Se detecta, pede confirmação antes de continuar (não bloqueia hard)

## Auto-update tasks-update após commit/merge (regra MCP)

> **Aprendizado CYCLE-01 retro:** tasks ficaram stale 1-3 dias entre PR mergear e status mudar no MCP. Próxima vez NÃO acontece.

Quando o commit message ou PR title contém `Refs: <TASK-ID>`, `Closes: <TASK-ID>` ou `Fixes: <TASK-ID>` (ex: `COPI-21`, `US-NFE-044`, `JANA-12`):

1. **Após `git push` bem-sucedido** que avança trabalho da task → `tasks-comment task_id=<ID>` com link do commit/PR
2. **Após `gh pr merge` bem-sucedido** → `tasks-update task_id=<ID> status=done` (ou `review` se ainda não finalizado)
3. **Bloqueio descoberto** → `tasks-update task_id=<ID> status=blocked` + `tasks-comment` explicando

Regex usado pelo `GitTaskLinkerService` (case-insensitive):
```
/(refs|fixes|closes|resolves|fix|close|resolve):?\s+([A-Z]{2,8})-(\d+)/i
```

Aceita: `Refs: COPI-21`, `Closes COPI-1`, `Fixes: NFSE-99`, `resolves: INFRA-7`. Padrão GitHub.

⚠️ **Sintaxe que NÃO funciona:**
- `feat(jana): COPI-43` (ID standalone, sem verb prefix → não detecta)
- `(#150)` (PR number GitHub, regex precisa task key COPI-NN não #NN)
- `closes #150` (mesma razão)

**Exemplo prático:**

```
git commit -m "feat(jana): MEM-S8-2 ConversationSummarizer

Refs: COPI-41
- Trigger >15 turns em ConversationContext
- Resumo <200 tokens via LaravelAiSdkDriver
- Tests Pest 8/8 passed
"

# após push:
gh pr create ...
# após merge:
# Claude DEVE chamar:
# mcp tasks-update task_id=COPI-41 status=done
# mcp tasks-comment task_id=COPI-41 comment="Mergeado em PR #NNN commit <SHA>"
```

**Quando NÃO auto-update:**
- PR rascunho/exploratório (ainda em iteração)
- Múltiplas tasks no mesmo commit (mover só as confirmadas done)
- Task tem dependência que ainda não fechou (manter `doing` ou `review`)

## Merge seguro (sha-pinned — anti-desync headRefOid)

> **Todo `gh pr merge` vira `scripts/gh/safe-merge.sh <PR>`** (default squash). Motivo: o merge
> pode ENGOLIR commits silenciosamente quando o GitHub está com `headRefOid` stale — o merge
> diz "success" mas um commit pushado não landa em `main`. Aconteceu 2026-07-03 (#3763 comeu o
> handoff da régua; mesmo padrão #3732). Detecção pós-merge é cega pra isso; a única garantia é
> **pinar o SHA no merge** (a API 409s se o head mexeu). Detalhe: [feedback-merge-desync-headrefoid.md](../../../memory/reference/feedback-merge-desync-headrefoid.md).

```bash
scripts/gh/safe-merge.sh 3767          # pré-check headRefOid==HEAD + merge pinado + verify pós-merge
```

- **Nunca** `gh pr merge` cru quando houve push recente no branch. Se precisar do cru (ex: gh sem API), no mínimo cheque `gh pr view <PR> --json headRefOid -q .headRefOid` == `git rev-parse HEAD` **antes** de mergear.
- **Merge pela UI do GitHub:** dar **F5 na página antes** de clicar (a aba pode ter head velho) — camada humana pro time que cresce.
- **Sinal de que engoliu:** `gh pr merge` diz "was already merged" com `mergeCommit` ≠ teu último push, ou um arquivo pushado não está em `origin/main`. Recuperação: re-landar num PR novo + `git ls-tree origin/main` pra confirmar.

## Exceções autorizadas (não viram commit-discipline alert)

- **Rebuild de assets** (`public/build-inertia/`) — auto-gerado, ignorar diff size
- **Migration grande** (criação tabela com índices) — single intent, OK ser >300
- **Refactor PR-9 oficialmente declarado** (ex: rename copiloto_* → jana_*) — ADR justificando

## Pré-req adicional pra PR que cria ADR canon (`memory/decisions/NNNN-*.md`)

Quando o PR adiciona arquivo novo em `memory/decisions/NNNN-*.md`, antes do commit:

1. Skill [pre-adr-introspect](../pre-adr-introspect/SKILL.md) deve ter rodado (verifica `git grep` de patterns canon + diagnóstico prod se ADR depende de estado DB)
2. Commit body OU §Contexto da ADR cita pelo menos 1 pattern canon investigado (mesmo que conclua "não existe — criar novo")
3. Se ADR `amends` ou `supersedes` outras → `related_adrs` no frontmatter lista TODAS as relacionadas

**Origem:** sessão 2026-05-27 — 3 ADRs erratas mesmo dia (#1731, #1735, #1744) por não introspectar antes. Documentado em ADR 0200 §Lição arquitetural.

## Métricas (skill telemetria)

- `mcp_skill_telemetry`: total commits onde commit-discipline disparou
- Helpful: % das vezes Wagner aceitou alerta vs ignorou
- Anti-padrão capturado: % commits >300 linhas pré-skill vs pós

## Referências

- [ADR 0094](../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (princípio #5 SoC)
- [ADR 0095](../../../memory/decisions/0095-skills-tiers-convencao-interna.md) — Skills Tiers
- [ADR 0200](../../../memory/decisions/0200-contacts-sync-canon-amends-0197-0199.md) — §Lição arquitetural (origem do pré-req pre-adr-introspect)
- [pre-adr-introspect skill](../pre-adr-introspect/SKILL.md) — Tier B pareada
- [Anthropic 2026 Agentic Coding Trends](https://resources.anthropic.com/hubfs/2026%20Agentic%20Coding%20Trends%20Report.pdf) — diff size best-practice
