---
slug: 0158-module-grade-v3-d1-heuristica-hardening
number: 0158
title: "module-grade-v3 — endurecimento heurística D1 (recursive + scope singular + Job $entityId pattern)"
type: adr
status: accepted
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-16
accepted_at: 2026-05-16
review_at: 2026-05-23
module: Governance
quarter: 2026-Q2
tags: [governance, rubrica, d1-multitenancy, hardening, falso-positivo, falso-negativo]
supersedes: []
supersedes_partially: [0155]
superseded_by: []
related: [0155, 0156, 0157, 0154, 0153, 0094, 0093]
pii: false
review_triggers:
  - Quando >3 módulos beneficiados/penalizados pelo recursive caírem na ressalva
  - Se Wagner introduzir 4º padrão multi-tenant não-detectado pela heurística
---

# ADR 0158 — module-grade-v3: endurecimento heurística D1 (recursive + scope singular + Job $entityId)

## Contexto

Agents Wave 7 (auditoria cruzada `governance-v3-wave8-quickwins`) catalogaram **3 bugs** na heurística D1 Multi-tenant do `ModuleGradeService` (ADR 0155 v3) que produzem **falsos positivos** (módulos pontuam mais do que merecem) e **falsos negativos** (módulos pontuam menos do que merecem). Estão todos em `Modules/Governance/Services/ModuleGradeService.php`.

### Bug #1 — `phpFiles()` chamado sem `recursive: true` em D1.a/D1.c

Linhas ~191-193 (D1.a Entities) e ~256 (D1.c Jobs):

```php
$entities = array_merge(
    $this->phpFiles($entitiesPath),       // não-recursivo
    $this->phpFiles($modelsPath)          // não-recursivo
);
// ...
$jobFiles = $this->phpFiles($jobsPath);   // não-recursivo
```

O helper `phpFiles()` (linha 1135) **já suporta** `bool $recursive = false` desde a v3 — mas D1.a/D1.c esqueceram de passar `true`. D1.b (testFiles, linha 228) já usa `recursive: true` — assimetria não-intencional.

**Impacto medido (Jana):** `Modules/Jana/Entities/Mcp/` tem **28 arquivos** (`McpAlerta.php`, `McpAuditLog.php`, `McpCcBlob.php`, `McpCcMessage.php`, `McpCcSession.php`, etc.) que são invisíveis ao `glob('Entities/*.php')` não-recursivo. Resultado: Jana é pontuada considerando só ~11 Entities top-level, das quais várias têm BusinessScope → D1.a falsamente perto de 10/10. Re-rodando com `recursive: true` espera-se Jana D1.a cair de ~9/10 para ~5/10 (gap real Mcp/ revelado), score final 61 → ~56.

### Bug #2 — `withoutGlobalScope` singular não detectado em `isCrossTenantTestFile()`

Linha 1192:

```php
$hasWithout = str_contains($content, 'withoutGlobalScopes');  // só plural
```

Tests Pest canônicos do projeto usam tanto `Model::withoutGlobalScope(BusinessScope::class)` (singular — sintaxe Eloquent canônica `Builder::withoutGlobalScope($scope)`) quanto `withoutGlobalScopes(...)` plural. Service só detecta plural.

**Impacto medido (Financeiro):** Critério (d) do `isCrossTenantTestFile()` exige `withoutGlobalScopes` plural — Financeiro D1.b cai pra 3/15 mesmo com test files legítimos usando singular. Crm também afetado.

### Bug #3 — Job constructor `$entityId` + lookup `->business_id` não-detectado em D1.c

Linha 260:

```php
if (preg_match('/__construct\s*\([^)]*\$business/', $content)) { ... }
```

Regex exige parâmetro literal começando com `$business*`. Pattern legítimo em `Modules/Financeiro/Jobs/CriarTituloDeVendaJob.php`: constructor recebe `$transactionId`, e o método `handle()`/`execute()` carrega `Transaction::find($transactionId)->business_id`. Multi-tenant correto, mas heurística marca como gap.

**Impacto medido (Financeiro):** Jobs `CriarTituloDeVendaJob`, `AtualizarSaldoJob` (entre outros) são falsos negativos. D1.c cai pra ~2/5 quando deveria ser 5/5.

## Decisão

Endurecimento da heurística D1 em 3 fixes paralelos, todos com backwards-compat via flag dual-mode (similar ADR 0157):

### Fix #1 — `dim1MultiTenant` passa `recursive: true` em D1.a/D1.c

```php
$entities = array_merge(
    $this->phpFiles($entitiesPath, recursive: true),
    $this->phpFiles($modelsPath, recursive: true)
);
// ...
$jobFiles = $this->phpFiles($jobsPath, recursive: true);
```

Sem mudança de assinatura. Helper já suporta. Re-rodar `gradeAllModules` pós-fix gera novo baseline.

### Fix #2 — Regex aceita `withoutGlobalScope` singular OU plural

Linha 1192 substituído por preg_match alternativa:

```php
$hasWithout = (bool) preg_match('/withoutGlobalScopes?\b/', $content);
```

O `s?` opcional cobre singular E plural; `\b` evita matchar nomes maiores. Critério (d) do `isCrossTenantTestFile()` continua exigindo a constante BIZ_* + literal pra evitar falso positivo de test que só usa o escape valve sem 2 tenants.

### Fix #3 — D1.c heurística estendida: `$xxxId` + body com `->business_id`

Novo passo: se regex literal `\$business` não bater, fallback pra detectar pattern derivado:

```php
$hasLiteralBusiness = (bool) preg_match('/__construct\s*\([^)]*\$business/', $content);
$hasDerivedBusiness = false;
if (! $hasLiteralBusiness && preg_match('/__construct\s*\([^)]*\$(\w+Id)\b/', $content)) {
    // Constructor aceita $xxxId — verifica se método handle/execute referencia ->business_id
    $hasDerivedBusiness = (bool) preg_match(
        '/function\s+(handle|execute|__invoke)\s*\([^)]*\)\s*[:\s\S]*?->business_id/',
        $content
    );
}
if ($hasLiteralBusiness || $hasDerivedBusiness) {
    $jobsBusinessId++;
}
```

Evidence string fica: `"X/Y Jobs com \$businessId (Z derivados via \$entityId->business_id)"` pra transparência.

## Consequências

### Positivas

- **Jana:** 61 → ~56 — drop honesto, revela gap Mcp/ que estava mascarado (28 Entities sem BusinessScope confirmado top-down)
- **Financeiro:** 56 → ~65 — sobe com correção D1.b/c que estava penalizando indevidamente
- **Crm:** ~57 mantém — heurística não cobre legacy `where('business_id', ...)` ad-hoc (gap conhecido, fora do escopo deste ADR)
- Score reflete realidade — Wagner+time MCP confiam mais no número
- Backwards-compat preservada via flag (dual-mode F1-F3)

### Negativas

- Alguns módulos vão cair (Jana especificamente — Mcp/ é gap real revelado, não bug da rubrica)
- Pode disparar onda de tarefas P0 de hardening Jana Mcp/ — esperado e desejável

### Mitigações

- `na_justified_v3.D1.a` continua opt-in pra módulos legacy/complex (ADR 0154/0156) — Jana pode marcar `Mcp/*.php` como N/A justificado se Wagner avaliar que dataset Mcp é cross-tenant by design (MCP server CT 100 multi-business)
- Flag `MODULE_GRADE_D1_HARDENED=false` mantém comportamento legacy por 60d (sunset path)

## Plano migração (4 fases dual-mode)

| Fase | Duração | Default | Comportamento |
|---|---|---|---|
| **F1** Service v3.2 dual-mode | 7d | `false` | Heurística hardened atrás de `config('governance.d1_hardened')`. Tests cobrem ambos lados. Wagner aprova manualmente em staging |
| **F2** Backfill baseline | 1d | `true` em snapshot job | `gradeAllModules` com hardened gera novo baseline em `governance_grade_baselines` table — sem impacto UI ainda |
| **F3** Switch default | 30d | `true` produção | Service usa hardened por default; UI mostra delta vs baseline anterior. Flag `false` ainda disponível pra rollback |
| **F4** Sunset legacy | +60d | `true` (only) | Remove path legacy. Atualiza ADR 0155 pra `supersedes_partially: [0155]` formalizado |

## Test plan

Pest scenarios em `Modules/Governance/Tests/Feature/Heuristica/`:

- **`D1aRecursiveEntitiesTest`** — fixture módulo fake com `Modules/Fake/Entities/Sub/Model.php` SEM BusinessScope: hardened detecta (recursive=true), legacy não detecta. Assert score delta.
- **`D1bWithoutGlobalScopeSingularTest`** — fixture test file usando `Model::withoutGlobalScope(BusinessScope::class)`: hardened bate critério (d), legacy não bate.
- **`D1cJobDerivedBusinessIdTest`** — fixture Job com `__construct(int $transactionId)` + handle ref `->business_id`: hardened conta como Job correto, legacy marca como gap.
- **Backwards-compat suite**: roda `gradeAllModules` com flag `false` e assert que todos scores módulos atuais permanecem ≥ baseline-1 (sem regressão).
- **N/A interaction**: módulo com `na_justified.D1.a` ainda recebe pontuação máxima mesmo após hardening — confirma que N/A não foi quebrado.

## Referências

- ADR [0093](0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- ADR [0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 §6 SoC brutal
- ADR [0153](0153-module-grade-rubrica-v1.md) — Rubrica v1 (D1 30 pts original)
- ADR [0154](0154-module-grade-v2-na-justificado.md) — N/A justificado v2
- ADR [0155](0155-module-grade-v3-sub-dimensoes-gate-ci.md) — v3 sub-dimensões + gate CI (`supersedes_partially` aqui)
- ADR [0156](0156-na-justified-v3-dimensoes-novas.md) — N/A justificado v3 dims novas
- Service code: `Modules/Governance/Services/ModuleGradeService.php` linhas 188-289 (dim1MultiTenant), 1135-1152 (phpFiles helper), 1185-1230 (isCrossTenantTestFile)
- Agents Wave 7 reports — auditoria cruzada `governance-v3-wave8-quickwins` 2026-05-16

---

**Status:** `proposed` — Wagner decide aceitar (status `accepted`) → spawn PR Service v3.2 dual-mode com 5 Pest novos + backfill baseline. Sem aceite, Service v3 atual segue (Jana 61 falsamente protegido).
