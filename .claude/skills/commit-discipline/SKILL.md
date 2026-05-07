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

## Exceções autorizadas (não viram commit-discipline alert)

- **Rebuild de assets** (`public/build-inertia/`) — auto-gerado, ignorar diff size
- **Migration grande** (criação tabela com índices) — single intent, OK ser >300
- **Refactor PR-9 oficialmente declarado** (ex: rename copiloto_* → jana_*) — ADR justificando

## Métricas (skill telemetria)

- `mcp_skill_telemetry`: total commits onde commit-discipline disparou
- Helpful: % das vezes Wagner aceitou alerta vs ignorou
- Anti-padrão capturado: % commits >300 linhas pré-skill vs pós

## Referências

- [ADR 0094](../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (princípio #5 SoC)
- [ADR 0095](../../../memory/decisions/0095-skills-tiers-convencao-interna.md) — Skills Tiers
- [Anthropic 2026 Agentic Coding Trends](https://resources.anthropic.com/hubfs/2026%20Agentic%20Coding%20Trends%20Report.pdf) — diff size best-practice
