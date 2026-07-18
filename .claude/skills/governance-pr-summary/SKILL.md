---
name: governance-pr-summary
description: Use ANTES de `gh pr create` em qualquer branch que toque Modules/<X>/. Lê módulos afetados via `git diff --name-only origin/main...HEAD`, infere bucket de cada módulo, executa `php artisan module:grade-v4 --bucket=<inferido> --json` (Wave 27, scoped scorecards) com fallback automático pra v3 se v4_enabled=false ou ScopedScorecardEvaluator ausente, computa Module Grade v4 (core + bucket dimensions + paired cap 50%) e INJETA seção `## Module Grade v4 (Scoped Scorecards)` na descrição do PR. Reduz adoption time de "Wagner precisa abrir 3 dashboards" pra "PR já vem com módulo + nota + bucket + meta + status". APÓS o merge do PR (o tool só atribui custo a PR mergeado), injeta também — LOCAL, porque o CI não enxerga o JSONL — um bloco idempotente `<!-- agent-cost-per-pr -->` com o custo USD estimado DESTE PR via `node scripts/governance/agent-cost-per-pr.mjs --pr <N>` (advisory · RELATO, nunca gate · sem valores em R$). Tier B auto-trigger.
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

## Como aplicar (7 passos — Wave 27 v2 + custo por PR)

> Passos **1-6** são **PRÉ**-`gh pr create` — compõem o corpo (Module Grade).
> Passo **7** é **PÓS-merge**: o tool só atribui custo a PR **mergeado**
> (`gh pr list --state merged` + match da sessão local por branch/citação), então
> o número só materializa **depois do `gh pr merge`**, na mesma máquina. Ordem:
> merge → `--pr <N>` local → `gh pr edit --body-file`.

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

### 7. Injetar bloco de **Custo estimado do PR** (LOCAL · advisory · PÓS-merge)

O custo é diferente da nota em duas coisas:

1. **Só na máquina que abriu o PR** — o CI não enxerga o JSONL local
   (`~/.claude/projects`, gap G5). Colar um agregado do cron seria teatro (não é o
   custo *deste* PR). Por isso roda LOCAL.
2. **Só para PR mergeado** — o tool lê `gh pr list --state merged` e casa a sessão
   local ao PR por branch/citação; um PR ainda **aberto** não está na lista, então
   `--pr <N>` sai honesto como `não medido`. O número materializa **depois do
   merge**. Rode este passo **logo após `gh pr merge`** (mesma sessão — o JSONL
   ainda está na máquina): é aí que o número chega no PR. O marcador torna a
   re-execução idempotente, então injetar cedo (placeholder) e refrescar pós-merge
   não duplica.

> **RELATO, não gate** (ADR 0271/0314): nunca bloqueia merge. Sem valores em R$ —
> o bloco é USD/tokens por construção (Tier 0).

```bash
PR=<numero do PR recém-mergeado>

# 1) gera o bloco DESTE PR (local). O tool degrada sozinho se não casar sessão.
BLOCO="$(node scripts/governance/agent-cost-per-pr.mjs --pr "$PR" 2>/dev/null)"

# 2) GUARD do marcador: só injeta se a 1ª linha for o marcador canônico.
#    Protege contra tool sem --pr (versão antiga cospe o RELATÓRIO HUMANO inteiro,
#    começa com "═══") ou erro/checkout sem a ferramenta → aí NÃO injeta nada.
case "$BLOCO" in
  "<!-- agent-cost-per-pr -->"*)
    TMP="$(mktemp)"
    # corpo atual SEM bloco antigo: awk corta no marcador E segura linhas em branco
    # (só imprime blank se vier conteúdo depois) → prefixo sem trailing-blank, então
    # re-rodar é byte-idempotente (nada de creep de linha em branco a cada push).
    gh pr view "$PR" --json body --jq .body | awk '
      /^<!-- agent-cost-per-pr -->[[:space:]]*$/{done=1} done{next}
      /^[[:space:]]*$/{blanks++; next}
      { while(blanks-->0) print ""; blanks=0; print }
    ' > "$TMP"
    printf '\n%s\n' "$BLOCO" >> "$TMP"   # bloco fresco, sempre no FIM do corpo
    gh pr edit "$PR" --body-file "$TMP"
    ;;
  *) : ;;  # sem bloco canônico → degrade limpo (nunca cola o relatório humano)
esac
```

O que o bloco diz:
- **PR mergeado + sessão local casada** → `**$X.XX** · NNNk tok · sinal branch|citacao`;
- **PR ainda aberto, ou sem sessão casada** → `_sem sessão local casada — não medido
  (G1/G3)_` (honesto, não inventa número — rode de novo após o merge);
- é **idempotente**: o marcador `<!-- agent-cost-per-pr -->` abre o bloco e ele fica
  sempre no fim do corpo; re-rodar (ex.: refresh pós-merge) substitui, não duplica.

**Pré-requisito**: o modo `--pr`/`renderPrBlockMd` mora em
`scripts/governance/agent-cost-per-pr.mjs`. Se o checkout ainda não tem, o guard do
marcador cai no `*)` e o passo vira no-op — o resto da skill segue normal.

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
- ⛔ NÃO injetar o bloco de custo em CI nem a partir do snapshot do cron — o
  número tem que ser o DESTE PR, medido LOCAL (G5); agregado = teatro
- ⛔ NÃO injetar saída que não comece com `<!-- agent-cost-per-pr -->` — versão
  antiga do tool cospe o relatório humano (`═══`); o guard do marcador existe
  pra isso, respeitar o `*)` (no-op)
- ⛔ NÃO tratar o custo como gate — é RELATO (ADR 0271/0314); nunca bloquear
  merge por custo alto, nunca "consertar" travando o PR
- ⛔ NUNCA converter o custo pra R$ — Tier 0; o bloco é USD/tokens por construção

## Trigger phrases

- "abre PR"
- "/gh pr create"
- "manda PR"
- "cria pull request"
- diff contém `Modules/<X>/` E branch != main
- branch nome contém `wave-N` (Waves Governance canônicas)
- logo após `gh pr merge` de PR de agente (`[CC]`/`[CL]`) → refrescar o bloco de
  custo (passo 7), que só materializa o número depois do merge

## Charter

Não aplica (skill orchestration, não Page Inertia).

## Referências

- ADR 0094 — Constituição v2 (mãe)
- ADR 0155 — Module Grade v3
- ADR 0156 — Scorecards YAML canon
- ADR 0160 — Skill governance-pr-summary Tier B
- ADR 0271 / 0314 — required = só Tier-0; custo por PR é advisory (RELATO)
- `scripts/governance/agent-cost-per-pr.mjs` — custo USD por PR (`--pr <N>`,
  bloco `<!-- agent-cost-per-pr -->`, advisory · fonte JSONL local)
- Wave 27 (2026-05-17) — expansão v2: detection bucket + v4-first com fallback v3
- 2026-07-17 — passo 7: bloco de custo estimado do PR (item 2 do mandato
  custo-por-PR — "fazer o número chegar no PR")
