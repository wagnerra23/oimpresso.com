---
name: module-grades-gate
description: ATIVAR quando user pedir "checar grades antes de PR", "rodar gate de notas local", "atualizar baseline module-grade", "como override regressão grades", "adicionar módulo novo gate CI", "reconciliar baseline module-grades", "/module-grades-gate", OU quando o CI `Module Grades Gate (anti-regressão)` falhar num PR (regressão OU módulo novo sem aprovação). Workflow CI bloqueia merge em DOIS cenários (Wave 2 endurecimento): (1) nota de QUALQUER módulo diminuir vs `governance/module-grades-baseline.json`; (2) módulo NOVO entrando na rubrica sem label de aprovação. Skill explica como rodar local, atualizar baseline conscientemente, aplicar override por label, e reconciliar drift de renames (ex `PontoWr2`→`Ponto`). Tier C (slash command).
updated_at: 2026-05-16
version: 1.1.0
related_adrs:
  - 0155-module-grade-v3-sub-dimensoes-gate-ci
  - 0154-module-grade-v2-na-justificado
  - 0153-module-grade-rubrica-v1
  - 0093-multi-tenant-isolation-tier-0
  - 0101-tests-business-id-1-nunca-cliente
triggers_on:
  - "checar grades antes de PR"
  - "rodar gate de notas local"
  - "atualizar baseline module-grade"
  - "atualizar baseline conscientemente"
  - "override regressão grades"
  - "adicionar módulo novo gate CI"
  - "reconciliar baseline module-grades"
  - "renomear módulo baseline"
  - "diagnosticar falha CI module grades"
  - "/module-grades-gate"
---

# Module Grades Gate — anti-regressão da rubrica v3

> **ADR 0155 — rubrica module-grade v3** (9 dimensões: D1 multi_tenant · D2 pest_coverage · D3 documentation · D4 architecture · D5 client_real · D6 performance · D7 lgpd · D8 security · D9 observability).
>
> Wave 2 (em curso 2026-05-16): gate agora bloqueia em **dois** cenários — regressão E módulo novo sem aprovação. Veja `## Cenário: módulo novo aparecendo no gate` abaixo.

## Arquitetura

| Peça | Caminho | Função |
|---|---|---|
| Workflow CI | [`.github/workflows/module-grades-gate.yml`](../../../.github/workflows/module-grades-gate.yml) | Roda em todo PR (opened/sync/reopened/labeled/unlabeled). Gera grades do head + compara com baseline + bloqueia merge se alguma nota diminuir OU se módulo novo entrar sem label. |
| Baseline canônica | [`governance/module-grades-baseline.json`](../../../governance/module-grades-baseline.json) | Snapshot autorizado das notas de prod (formato v3.1: chave `modules` map nome→score). Editar conscientemente quando uma regressão for justificável OU quando módulo novo for aprovado. |
| Composer script | `composer module-grades-check` | Alias local pra `php artisan module:grade --all --json` — mesma chamada que o CI faz. |
| Override label (regressão) | `module-grades-allowed-regression` | Permite merge mesmo com nota baixando (exige justificativa em comentário/ADR). |
| Override label (novo módulo) | `module-grades-new-module-allowed` | Aprova entrada de módulo novo na rubrica (Wave 2 — gap P1 fechado: Admin/Connector/Arquivos/Essentials não passam mais silencioso). |

## Rodar local antes de abrir PR

```bash
# Vê o que CI vai ver — JSON completo das 9 dimensões
composer module-grades-check

# Equivalente direto (ranqueado em tabela colorida)
php artisan module:grade --all

# Drill-down de um módulo específico (mostra dimensões + sub-checks)
php artisan module:grade Repair --detail
```

> ⚠️ Use `--detail` NUNCA `--verbose` (Symfony reservado — ver [`.claude/rules/commands.md`](../../rules/commands.md)).

## Cenário: módulo novo aparecendo no gate

Quando `Modules/<X>/` novo aparece (key não existe em `governance/module-grades-baseline.json`), gate detecta como `✨ new`.

| Comportamento | Quando | Resultado |
|---|---|---|
| **Atual (pré-Wave 2)** | Antes de 2026-05-16 | Passa silencioso ❌ — risco de Admin/Connector/Arquivos entrando sem nota mínima |
| **Wave 2 endurecimento (LIVE)** | A partir de 2026-05-16 | Bloqueia merge se sem label `module-grades-new-module-allowed`. Wagner aprova explicitamente entrada na rubrica |

### Procedimento aprovar entrada de módulo novo

1. **Gerar nota inicial local:**
   ```bash
   php artisan module:grade <Nome> --detail
   php artisan module:grade <Nome> --json  # captura output pra entry no baseline
   ```

2. **PR adiciona entry no baseline:** editar `governance/module-grades-baseline.json` campo `modules` adicionando `"<Nome>": <nota_inicial>` (nota inicial pode ser baixa — embriões 0-20 são esperados, contanto que entrem rastreáveis).

3. **Aplicar label `module-grades-new-module-allowed`** no PR de introdução via GH UI ou:
   ```bash
   gh pr edit <pr-number> --add-label "module-grades-new-module-allowed"
   ```

4. **Justificar em comentário do PR:** módulo crítico (Admin/Connector/Arquivos/Essentials) ou intencional? Vira evidência audit.

5. **Wagner aprova** + merge. Próximos PRs comparam vs nota inicial registrada.

Caso entrada acidental (scan/heurística pegou `Modules/Vendor/` por exemplo) — **remover/renomear o módulo** em vez de aplicar label.

## Cenário: renomear módulo (drift fix)

Caso real catalogado v3.1 (2026-05-16): `PontoWr2` → `Ponto` (dir renomeado), `Project` → `ProjectMgmt` (alias).

### Procedimento drift fix

```bash
# 1. Renomear dir físico
git mv Modules/<Antigo> Modules/<Novo>

# 2. Atualizar module.json
# Editar Modules/<Novo>/module.json campo "name"

# 3. Atualizar baseline JSON
# Editar governance/module-grades-baseline.json:
#   - Remover key "<Antigo>"
#   - Adicionar key "<Novo>" com mesma nota (rename ≠ regressão)

# 4. Confirmar Service encontra
php artisan module:grade <Novo> --detail

# 5. ADR de drift fix se decisão arquitetural justifica
# Ex: memory/decisions/NNNN-rename-pontowr2-ponto.md
```

> ⚠️ Rename **não é regressão** — gate aceita se nota nova = nota antiga sob key novo. Se nota baixar no rename (ex: detecção de SPEC quebrada pelo path novo), tratar como regressão normal (corrigir OU `module-grades-allowed-regression`).

> ⚠️ Rename **não é módulo novo** se key antigo removido no MESMO PR — gate verá só substituição. Se rename split em 2 PRs (remove antigo PR1, adiciona novo PR2), PR2 dispara `new` e exige label.

## Atualizar baseline conscientemente

**Quando atualizar:**
- Pós-merge feature que sobe nota dum módulo → atualizar pra anti-regressão "subir junto"
- Pós-merge módulo novo aprovado → entry definitiva no baseline
- Pós-rename drift fix → key trocada
- **NUNCA** atualize baseline pra "passar gate" sem subir nota real — gaming detectável via diff PR (Wagner vê drop suspeito)

**Comando:**

```bash
# 1. Rodar contra main (ou branch baseline)
git checkout main
composer install --no-interaction
php artisan module:grade --all --json > /tmp/new-baseline-raw.json

# 2. Converter pro formato baseline.json (campo modules: nome → score)
php -r '
  $data = json_decode(file_get_contents("/tmp/new-baseline-raw.json"), true);
  $modules = [];
  foreach ($data as $g) { $modules[$g["module"]] = (int) $g["score"]; }
  ksort($modules);
  $baseline = [
    "generated_at" => gmdate("c"),
    "baseline_version" => "v3.1",
    "rubric_adr" => "0155",
    "notes" => "Snapshot regenerado pós-merge <feature>.",
    "modules" => $modules,
  ];
  file_put_contents("governance/module-grades-baseline.json", json_encode($baseline, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
'

# 3. Commit + push em PR dedicado
git add governance/module-grades-baseline.json
git commit -m "chore(governance): atualizar baseline module-grades pós-merge <XYZ>"
```

PR title canônico: `chore(governance): atualizar baseline module-grades pós-merge <XYZ>` — Wagner aprova.

## Diagnosticar falha CI

Status reportado pelo gate (coluna Status do quadro PR):

| Emoji | Status | Significado | Ação |
|---|---|---|---|
| 🔻 | `down` | Nota baixou | **Corrigir código** (rodar `module:grade <X> --detail` pra ver dimensão D1-D9 que caiu) OU label `module-grades-allowed-regression` + ADR justificando OU atualizar baseline se drop legítimo |
| ✨ | `new` | Módulo novo entrou (Wave 2) | Label `module-grades-new-module-allowed` SE intencional, OU aceitar nota baixa SE drop heurístico OU remover/renomear se entrada acidental |
| ⚠️ | `removed` | Módulo deletado/renomeado | ADR de drift fix + atualizar baseline. Se rename, garantir key novo presente no mesmo PR |
| 🟢 | `up` | Nota subiu | OK — atualizar baseline em PR dedicado pós-merge pra fixar novo patamar |
| ⚪ | `eq` | Neutro | OK — sem ação |

### Combinações Wave 2 (workflow comenta título específico)

- **Regressão + módulo novo sem aprovação:** "❌ regressão + módulo novo sem aprovação" — resolver ambos
- **Override regressão ativo + módulo novo sem aprovação:** "❌ módulo novo sem aprovação" — aplicar segunda label
- **Módulo novo aprovado:** "✨ módulo(s) novo(s) aprovado(s)" — lembrar atualizar baseline pós-merge
- **All clear:** "✅ all clear" — merge livre

## Override responsável (labels)

### Label `module-grades-allowed-regression` (regressão)

Quando aplicar:
- ✅ Refactor estrutural justificado (ex: remove código deprecated quebra cobertura Pest temporariamente)
- ✅ Migração de framework que altera dimensão D4 arquitetura por design
- ❌ "Não tenho tempo de fixar" — NÃO. Atualizar baseline conscientemente é melhor (deixa trilha audit).

### Label `module-grades-new-module-allowed` (módulo novo, Wave 2)

Quando aplicar:
- ✅ Módulo novo intencional (criado via skill `criar-modulo`, scaffold completo)
- ✅ Módulo crítico que estava silencioso (Admin/Connector/Arquivos/Essentials retroativos)
- ❌ Entrada acidental (scan pegou pasta `Modules/Vendor/`, `Modules/legacy_backup/`) — **remover/renomear** em vez de aplicar label

Após aplicar QUALQUER label, escreva 1 comentário no PR com a justificativa. Vira evidência audit pro time MCP.

## Comportamento do bot do gate (Wave 2)

```
📊 Module Grades Gate — ❌ regressão + módulo novo sem aprovação

> 1 módulo(s) regrediram E 2 módulo(s) novo(s) sem aprovação vs baseline.

### Como resolver
- Regressão: corrigir, atualizar baseline, OU label `module-grades-allowed-regression`
- Módulo novo: confirmar entrada do módulo na rubrica e aplicar label `module-grades-new-module-allowed` (Wagner aprova)

| Módulo | Baseline | Atual | Δ | Status |
|---|---:|---:|---:|---|
| `Repair` | 68 | 65 | -3 | 🔻 down |
| `NovoMod` | — | 42 | — | ✨ new |
| `OutroNovo` | — | 28 | — | ✨ new |
| `Sells` | 58 | 60 | +2 | 🟢 up |
```

E falha o job (exit 1) se houver regressão sem override OU módulo novo sem aprovação.

## Skill complementar

- **`avaliar-modulo`** (Tier B) — calcular nota módulo individual + batch tasks evolução
- **`comparativo-do-modulo`** (Tier B) — gaps vs mercado (concorrentes Capterra)
- **`module-completeness-audit`** (Tier B) — checklist binário antes de marcar US `done`
- **`commit-discipline`** (Tier A) — força PII redactor (D7.a)
- **`audit-and-fix`** (Tier C) — ciclo pesquisar→fixar quando gate falhar em vários módulos

## ADRs relacionadas

- [ADR 0155 — rubrica module-grade v3 + gate CI](../../../memory/decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md) **(parent)**
- [ADR 0154 — v2 N/A justificado](../../../memory/decisions/0154-module-grade-v2-na-justificado.md) (backward-compat N/A em D6-D9)
- [ADR 0153 — module-grade v1 (rubrica 5 dim)](../../../memory/decisions/0153-module-grade-rubrica-v1.md) (origem)
- [ADR 0093 — multi-tenant Tier 0](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) (dimensão D1)
- [ADR 0101 — tests biz=1 nunca cliente](../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md) (dimensão D2)

## Runbook complementar

- [`memory/requisitos/Infra/RUNBOOK-governance-gate-ci.md`](../../../memory/requisitos/Infra/RUNBOOK-governance-gate-ci.md) — gate genérico governance (ADR canon append-only, PII scan, cascade review). Este skill é específico de **module-grades-gate** que roda em workflow separado e checa rubrica v3.
