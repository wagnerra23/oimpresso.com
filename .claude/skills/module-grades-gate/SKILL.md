---
name: module-grades-gate
description: ATIVAR quando user pedir "checar grades antes de PR", "rodar gate de notas local", "atualizar baseline module-grade", "como override regressão grades", "/module-grades-gate", OU quando o CI `Module Grades Gate (anti-regressão)` falhar num PR. Workflow CI bloqueia merge se nota de QUALQUER módulo diminuir vs `governance/module-grades-baseline.json` (ADR 0155 rubrica v3). Skill explica como rodar local, atualizar baseline conscientemente, e aplicar override por label. Tier C (slash command).
---

# Module Grades Gate — anti-regressão da rubrica v3

> **ADR 0155 — rubrica module-grade v3** (9 dimensões: D1 multi_tenant · D2 pest_coverage · D3 documentation · D4 architecture · D5 client_real · D6 performance · D7 lgpd · D8 security · D9 observability).

## Arquitetura

| Peça | Caminho | Função |
|---|---|---|
| Workflow CI | [`.github/workflows/module-grades-gate.yml`](../../../.github/workflows/module-grades-gate.yml) | Roda em todo PR (opened/sync/reopened/labeled). Gera grades do head + compara com baseline + bloqueia merge se alguma nota diminuir. |
| Baseline canônica | [`governance/module-grades-baseline.json`](../../../governance/module-grades-baseline.json) | Snapshot autorizado das notas de prod. Editar conscientemente quando uma regressão for justificável. |
| Composer script | `composer module-grades-check` | Alias local pra `php artisan module:grade --all --json` — mesma chamada que o CI faz. |
| Override label | `module-grades-allowed-regression` | Aplicar label no PR pra deixar passar mesmo com regressão (exige justificativa em comentário/ADR). |

## Rodar local antes de abrir PR

```bash
# Vê o que CI vai ver — JSON completo das 9 dimensões
composer module-grades-check

# Equivalente direto (ranqueado em tabela colorida)
php artisan module:grade --all

# Drill-down de um módulo específico (mostra dimensões + sub-checks)
php artisan module:grade Repair --detail
```

Se sua mudança fez alguma nota DIMINUIR, o CI vai bloquear o merge. 3 caminhos:

1. **Corrigir a regressão** — rodar `module:grade <Modulo> --detail` pra identificar qual dimensão caiu, e fixar
2. **Atualizar baseline conscientemente** — editar [`governance/module-grades-baseline.json`](../../../governance/module-grades-baseline.json) no mesmo PR, justificando em commit body / PR description / ADR
3. **Override por label** — aplicar `module-grades-allowed-regression` no PR (exige justificativa em comentário)

## Atualizar baseline (regenerar do prod)

```bash
# 1. Rodar contra main (ou branch baseline)
git checkout main
composer install --no-interaction
php artisan module:grade --all --json > /tmp/new-baseline-raw.json

# 2. Converter pro formato baseline.json (módulo → score)
php -r '
  $data = json_decode(file_get_contents("/tmp/new-baseline-raw.json"), true);
  $modules = [];
  foreach ($data as $g) { $modules[$g["module"]] = (int) $g["score"]; }
  ksort($modules);
  $baseline = [
    "generated_at" => gmdate("c"),
    "baseline_version" => "v3",
    "rubric_adr" => "0155",
    "notes" => "Snapshot regenerado.",
    "modules" => $modules,
  ];
  file_put_contents("governance/module-grades-baseline.json", json_encode($baseline, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
'

# 3. Commit + push em PR dedicado
git add governance/module-grades-baseline.json
git commit -m "chore(governance): regenerar baseline module-grade v3"
```

## Override responsável (label)

Quando aplicar `module-grades-allowed-regression`:

- ✅ Refactor estrutural justificado (ex: removendo código deprecated quebra cobertura Pest temporariamente)
- ✅ Migração de framework que altera dimensão D4 arquitetura por design
- ❌ "Não tenho tempo de fixar" — NÃO. Atualizar baseline conscientemente é melhor (deixa trilha).

Após aplicar a label, escreva 1 comentário no PR com a justificativa. Vira evidência audit.

## Comportamento do bot do gate

O workflow comenta no PR um quadro tipo:

```
📊 Module Grades Gate — ❌ regressão detectada

> 2 módulo(s) com nota MENOR vs baseline

| Módulo | Baseline | Atual | Δ | Status |
|---|---:|---:|---:|---|
| `Repair` | 68 | 65 | -3 | 🔻 down |
| `Sells` | 58 | 56 | -2 | 🔻 down |
| `Crm` | 55 | 57 | +2 | 🟢 up |
...
```

E falha o job (exit 1) se houver regressão sem override.

## ADRs relacionadas

- [ADR 0155 — rubrica module-grade v3](../../../memory/decisions/0155-rubrica-module-grade-v3.md)
- [ADR 0093 — multi-tenant Tier 0](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) (dimensão D1)
- [ADR 0101 — tests biz=1 nunca cliente](../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md) (dimensão D2)

## Skills relacionadas

`avaliar-modulo` (Tier B — formata output module:grade individual com batch tasks sugeridas) · `module-completeness-audit` (Tier B — gate antes de marcar US done) · `commit-discipline` (Tier A) · `audit-and-fix` (Tier C — ciclo pesquisar→fixar quando gate falhar em vários módulos)
