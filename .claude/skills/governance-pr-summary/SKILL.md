---
name: governance-pr-summary
description: Use ANTES de `gh pr create` em qualquer branch que toque Modules/<X>/. Lê módulos afetados via `git diff --name-only origin/main...HEAD`, infere bucket de cada módulo, executa `php artisan module:grade-v4 --bucket=<inferido> --json` (Wave 27, scoped scorecards) com fallback automático pra v3 se v4_enabled=false ou ScopedScorecardEvaluator ausente, computa Module Grade v4 (core + bucket dimensions + paired cap 50%) e INJETA seção `## Module Grade v4 (Scoped Scorecards)` na descrição do PR. Reduz adoption time de "Wagner precisa abrir 3 dashboards" pra "PR já vem com módulo + nota + bucket + meta + status". Tier B auto-trigger.
trust_level: L1
owner: wagner
parent_mission: meta-skill-roi-erp-autonomo
charter_adr: ""
tier: B
parent_adr: 0094
---

# Governance PR Summary v2 — auto-injetar Module Grade v4 em descrição de PR

## Quando ativa

Auto-trigger ANTES de `gh pr create` (ou quando Wagner pede "PR
description com módulo + nota"). Detecta:

- `git diff --name-only origin/main...HEAD` contém `Modules/<X>/...`
- PR title começa com `feat(<modulo>)`, `fix(<modulo>)`, `perf(<modulo>)`,
  `refactor(<modulo>)`, `chore(<modulo>)`
- Variable de contexto inclui slug de módulo
- Branch tem prefixo `claude/<modulo>-wave-N-...`

## Regra de ouro

> Wagner não deve precisar abrir 3 dashboards (Module Grade UI + scorecard YAML
> + BRIEFING.md) pra entender o estado do módulo. **PR description é a fonte
> primária de signal.**

## Como aplicar (6 passos — Wave 27 v2)

### 1. Detectar módulos afetados

```powershell
git diff --name-only origin/main...HEAD | Select-String -Pattern '^Modules/(\w+)/' `
  | ForEach-Object { $_.Matches.Groups[1].Value } | Sort-Object -Unique
```

### 2. Inferir bucket de cada módulo (Wave 27 novo)

Bucket fica em `Modules/<X>/module.json` em `governance.bucket`. Mapeamento
canônico:

| Bucket | Módulos típicos | Meta |
|---|---|---:|
| `vertical_client_facing` | Vestuario, ComunicacaoVisual, OficinaAuto, Repair, ProductCatalogue, Crm | ≥85 |
| `cross_cutting_infra` | Governance, Auditoria, TeamMcp, Superadmin, Admin, KB, Connector | ≥80 |
| `ai_central` | Jana, Brief | ≥85 |
| `functional_horizontal` | Financeiro, NfeBrasil, RecurringBilling, Whatsapp, Accounting, ADS | ≥80 |

### 3. Computar nota atual (v4 preferida com fallback v3)

```bash
# v4 (Wave 21+, scoped scorecards — preferida quando ScopedScorecardEvaluator existe):
php artisan module:grade-v4 <X> --json 2>/dev/null \
  || php artisan module:grade <X> --json  # fallback v3

# Múltiplos do mesmo bucket de uma vez:
php artisan module:grade-v4 --bucket=<inferido> --json

# Tool MCP (cache 5min, quando rede CT 100 disponível):
mcp__oimpresso__module-grade module:<X>
```

Sentinela do fallback: se `class_exists('Modules\\Governance\\Services\\ScopedScorecardEvaluator')` retorna `false` no command, ele já printa erro e exita 1 — capture e cai pro v3 silenciosamente.

### 4. Montar seção `## Module Grade v4 (Scoped Scorecards)` em markdown

```markdown
## Module Grade v4 (Scoped Scorecards)

| Módulo | Bucket | Score | Meta | Status |
|---|---|---:|---:|---|
| Vestuario | vertical_client_facing | 88/100 | ≥85 | ✓ |
| Jana | ai_central | 96/100 | ≥85 | ✓ |
| Governance | cross_cutting_infra | 92/100 | ≥80 | ✓ |

**Dimensões bucket afetadas:**
- Vestuario · F1_pest_e2e: 18/20 (+2 — `VendaCreateE2E` adicionado)
- Jana · A2_safety: 14/15 (sem regressão)

**Paired violations:** nenhuma · Cap 50%: não acionado

Scorecards canon: `memory/governance/scorecards/{vestuario,jana,governance}.yaml`.
ADRs mãe: [0155](../decisions/0155-module-grade-rubrica-v3-final.md), [0160](../decisions/0160-skill-governance-pr-summary-tier-b.md).
```

### 5. Fallback v3 (formato compacto se v4 indisponível)

```markdown
## Module Grade (v3 fallback — v4 não disponível neste ambiente)

| Módulo | Score | Bucket | Δ vs anterior | Target |
|---|---|---|---|---|
| Governance | 92/100 | Excelente | +18 | ≥90 ✅ |
```

### 6. Injetar via `--body` heredoc

```bash
gh pr create --title "feat(vestuario): ..." --body "$(cat <<'EOF'
## Summary
- ...

## Module Grade v4 (Scoped Scorecards)
| Módulo | Bucket | Score | Meta | Status |
| Vestuario | vertical_client_facing | 88/100 | ≥85 | ✓ |
...

## Test plan
- [ ] ...
EOF
)"
```

## Detection de bucket per file changed (Wave 27)

Mapping rápido quando 1 PR toca múltiplos módulos:

```bash
git diff --name-only origin/main...HEAD \
  | grep -oE '^Modules/[^/]+' | sort -u \
  | while read p; do
      mod=$(basename "$p")
      bucket=$(jq -r '.governance.bucket // "unknown"' "$p/module.json" 2>/dev/null)
      echo "$mod:$bucket"
    done
```

Se bucket = `unknown`, módulo não está classificado → escalar pro
Wagner ("falta `governance.bucket` em `Modules/<X>/module.json`").

## Anti-patterns proibidos (Tier 0)

- ⛔ NÃO inventar score sem chamar tool MCP / comando artisan
- ⛔ NÃO inflar "Δ vs anterior" — se ScoreSnapshotReader retorna `null`,
  reportar honestamente "primeira medição"
- ⛔ NÃO esconder módulos que regrediram — `Δ` negativo precisa aparecer
- ⛔ NÃO pular esta skill em PR que toca código de módulo (Wagner perde
  ground truth)
- ⛔ NÃO mostrar v4 se ScopedScorecardEvaluator ausente — fallback pra v3
  silencioso (`|| php artisan module:grade`)
- ⛔ NÃO declarar `Status: ✓` se total < meta_bucket — calcular contra meta
  real lida do bucket YAML, não chutar

## Trigger phrases

- "abre PR"
- "/gh pr create"
- "manda PR"
- "cria pull request"
- diff contém `Modules/<X>/` E branch != main
- branch nome contém `wave-N` (Waves Governance canônicas)

## Charter

Não aplica (skill orchestration, não Page Inertia).

## Referências

- ADR 0094 — Constituição v2 (mãe)
- ADR 0155 — Module Grade v3
- ADR 0156 — Scorecards YAML canon
- ADR 0160 — Skill governance-pr-summary Tier B
- Wave 27 (2026-05-17) — expansão v2: detection bucket + v4-first com fallback v3
