---
name: governance-pr-summary
description: Use ANTES de `gh pr create` em qualquer branch que toque Modules/<X>/. Lê módulos afetados via `git diff --name-only origin/main...HEAD`, lê `Modules/<X>/module.json` + `memory/governance/scorecards/<x>.yaml` (se existir), computa nota Module Grade resumida e INJETA seção `## Module Grade` na descrição do PR. Reduz adoption time de "Wagner precisa abrir 3 dashboards" pra "PR já vem com módulo + nota + bucket". Tier B auto-trigger.
trust_level: L1
owner: wagner
parent_mission: meta-skill-roi-erp-autonomo
charter_adr: ""
tier: B
parent_adr: 0094
---

# Governance PR Summary — auto-injetar Module Grade em descrição de PR

## Quando ativa

Auto-trigger ANTES de `gh pr create` (ou quando Wagner pede "PR
description com módulo + nota"). Detecta:

- `git diff --name-only origin/main...HEAD` contém `Modules/<X>/...`
- PR title começa com `feat(<modulo>)`, `fix(<modulo>)`, `perf(<modulo>)`,
  `refactor(<modulo>)`, `chore(<modulo>)`
- Variable de contexto inclui slug de módulo

## Regra de ouro

> Wagner não deve precisar abrir 3 dashboards (Module Grade UI + scorecard YAML
> + BRIEFING.md) pra entender o estado do módulo. **PR description é a fonte
> primária de signal.**

## Como aplicar (5 passos)

### 1. Detectar módulos afetados

```powershell
git diff --name-only origin/main...HEAD | Select-String -Pattern '^Modules/(\w+)/' `
  | ForEach-Object { $_.Matches.Groups[1].Value } | Sort-Object -Unique
```

### 2. Ler scorecard YAML do módulo (se existir)

`memory/governance/scorecards/<modulo-lowercase>.yaml`. Se ausente, deferir
pro template `_template.yaml` + warn pro Wagner ("crie scorecard pra esse
módulo").

### 3. Computar nota atual (via tool MCP ou comando artisan)

```bash
# Opção MCP (preferida, cache 5min):
mcp__oimpresso__module-grade module:<X>

# Opção CLI fallback:
php artisan module:grade <X> --json
```

### 4. Montar seção `## Module Grade` em markdown

```markdown
## Module Grade

| Módulo | Score | Bucket | Δ vs anterior | Target |
|---|---|---|---|---|
| Governance | 92/100 | Excelente | +18 | ≥90 ✅ |
| Auditoria | 91/100 | Excelente | +19 | ≥90 ✅ |

**Dimensões afetadas:**
- D3 Charter: +4 (Audit/Index.charter.md)
- D2 Pest: +4 (AuditEntryReversibilityTest)
- D5 LGPD: +3 (pii_leak_in_activity_log enforce)

Scorecard canon: `memory/governance/scorecards/auditoria.yaml`.
ADR mãe: [0127](../decisions/0127-modulo-auditoria-ui-undo.md).
```

### 5. Injetar via `--body` heredoc

```bash
gh pr create --title "feat(auditoria): ..." --body "$(cat <<'EOF'
## Summary
- ...

## Module Grade
| Módulo | Score | Bucket | Δ | Target |
...

## Test plan
- [ ] ...
EOF
)"
```

## Anti-patterns proibidos (Tier 0)

- ⛔ NÃO inventar score sem chamar tool MCP / comando artisan
- ⛔ NÃO inflar "Δ vs anterior" — se ScoreSnapshotReader retorna `null`,
  reportar honestamente "primeira medição"
- ⛔ NÃO esconder módulos que regrediram — `Δ` negativo precisa aparecer
- ⛔ NÃO pular esta skill em PR que toca código de módulo (Wagner perde
  ground truth)

## Trigger phrases

- "abre PR"
- "/gh pr create"
- "manda PR"
- "cria pull request"
- diff contém `Modules/<X>/` E branch != main

## Charter

Não aplica (skill orchestration, não Page Inertia).

## Referências

- ADR 0094 — Constituição v2 (mãe)
- ADR 0155 — Module Grade v3
- ADR 0156 — Scorecards YAML canon
- ADR 0160 — Skill governance-pr-summary Tier B
