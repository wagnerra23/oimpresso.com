---
slug: 0157-module-grade-v3-d2-detection-hardening
number: 0157
title: "module-grade-v3 — endurecimento D2 detection (parser XML + verificação subpastas Pest)"
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
tags: [governance, rubrica, d2-pest-coverage, hardening, anti-gaming]
supersedes: []
supersedes_partially: [0155]
superseded_by: []
related: [0155, 0156, 0154, 0153, 0094, 0070, 0093, 0101]
pii: false
review_triggers:
  - Se >5 módulos baixarem nota >5 pts após adoção (sinal que baseline estava ENTUPIDO de inflação)
  - Se gate CI bloquear >3 PRs/semana por D2 (falso positivo da nova heurística)
  - Quando 100% dos módulos tiverem ambos Tests/Feature + Tests/Unit registrados em phpunit.xml (sub-checagem subpasta deixa de ser diferenciador)
---

# ADR 0157 — module-grade-v3: endurecimento D2 detection (parser XML + verificação subpastas Pest)

## Contexto

Audit Pest registration read-only (Wave 2, sessão 2026-05-16) identificou **três vetores de fragilidade** na heurística D2 implementada em [`Modules/Governance/Services/ModuleGradeService.php`](../../Modules/Governance/Services/ModuleGradeService.php) (linhas 290-367) — método `dim2PestCoverage()`:

### Vetor 1 — D2.c substring match (linha 346)

```php
$isRegistered = str_contains($phpunit, "./Modules/{$name}/Tests");
```

Caso real **ADS** confirma o gap: [phpunit.xml](../../phpunit.xml) linha 30 registra `./Modules/ADS/Tests/Unit`, mas o filesystem tem **4 arquivos órfãos** em [`Modules/ADS/Tests/Feature/`](../../Modules/ADS/Tests/Feature/) (BrainBIsolationTest.php, MultiTenantDecisionTest.php, ScaffoldTest.php, SmokeRoutesTest.php) que **nunca rodam em CI**. Substring `./Modules/ADS/Tests` casa via prefixo → D2.c marca 4 pts cheios → ADS aparece "registrado" mesmo com Tests/Feature inteiro órfão.

### Vetor 2 — D2.a filesystem inflation (linhas 300-309)

```php
$testFiles = array_filter($this->phpFiles($testsPath, recursive: true), ...);
$ratio = count($testFiles) / count($controllers);
```

Conta TODO `*.php` debaixo de `Modules/<X>/Tests/` (recursivo), independente de estar registrado no testsuite. Qualquer arquivo criado pelo scaffold (mesmo nunca chamado pelo CI) infla a razão → nota D2.a infla sem teste real correndo.

### Vetor 3 — D2.b nome de arquivo (linhas 322-332)

```php
foreach (['MultiTenant', 'Smoke', 'Scaffold'] as $p) {
    foreach ($testFiles as $f) {
        if (str_contains($f, $p)) { $canonicalCount++; break; }
    }
}
```

Substring match em NOME de arquivo. Scaffold cria `MultiTenantTest.php` vazio → marca 8/3 pts (canonicalCount=1) sem **uma única asserção** no corpo. Para Tier 0 IRREVOGÁVEL ([ADR 0093](0093-multi-tenant-isolation-tier-0.md) + [ADR 0101](0101-tests-business-id-1-nunca-cliente.md)) isso é vetor de **multi-tenant verde falso**.

### Risco real

- **Baseline v3 inflado em ~3-8 pts/módulo** (estimativa empírica: ADS, possíveis outros). Gate CI [ADR 0155](0155-module-grade-v3-sub-dimensoes-gate-ci.md) bloqueia regressão de nota inflada → **falso positivo** em PR legítimo que apenas corrige (move arquivo pra registro real).
- **Multi-tenant pode estar "verde" via nome de arquivo** sem teste real — violação Tier 0 mascarada por rubrica.
- **Princípio Constituição v2 #4** (loop fechado por métrica — [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md)) exige que a métrica seja **fiel à realidade que mede**. Heurística substring é fraca demais pra dimensão que pesa 17 pts em /118.

## Decisão

Endurecer detection D2 em **três frentes simultâneas** via Service v3.1, mantendo backward-compat dual-mode (config flag) e plano de switch progressivo.

### D2.a v3.1 — filesystem dentro de pastas REGISTRADAS

Contar test files **apenas** dentro das pastas listadas em `<directory>` da testsuite ativa do phpunit.xml. Arquivos fora (Tests/Feature órfão se só Tests/Unit registrado, por ex) **não contam**.

```php
// pseudo-código
$registeredDirs = $this->parsePhpunitDirsForModule($name); // ['Modules/ADS/Tests/Unit']
$testFiles = [];
foreach ($registeredDirs as $dir) {
    $testFiles = array_merge($testFiles, $this->phpFiles($dir, recursive: true));
}
```

### D2.b v3.1 — nome **+** asserção real no corpo

Além de `str_contains` no nome do arquivo, verificar que o arquivo contém **pelo menos uma asserção Pest/PHPUnit** (`expect(`, `assert*(`, `->assert*(`, ou `$this->assert*(`).

```php
private function hasRealAssertions(string $filePath): bool
{
    $body = @file_get_contents($filePath) ?: '';
    return preg_match('/\b(expect\(|assert[A-Z]\w*\(|->assert[A-Z]\w*\(|\$this->assert[A-Z]\w*\()/', $body) === 1;
}
```

Arquivo vazio scaffold-only com nome `MultiTenantTest.php` **não conta** mais.

### D2.c v3.1 — parser XML estruturado (SimpleXMLElement)

Substituir `str_contains` por parser XML real. Verificar que cada `<directory>` declarado bate path real do módulo, com normalização (trailing slash, leading `./`).

```php
private function parsePhpunitDirsForModule(string $name): array
{
    $xml = @simplexml_load_file($this->phpunitXmlPath);
    if (! $xml) return [];
    $modulePrefix = "./Modules/{$name}/";
    $dirs = [];
    foreach ($xml->testsuites->testsuite as $suite) {
        foreach ($suite->directory as $dirNode) {
            $dir = trim((string) $dirNode);
            if (str_starts_with($dir, $modulePrefix)) {
                $dirs[] = ltrim($dir, './');
            }
        }
    }
    return array_unique($dirs);
}
```

**D2.c v3.1 scoring:**
- 0 dirs registrados → 0 pts (D2.c=0)
- 1 dir registrado (parcial — ex só Tests/Unit) → 2 pts (metade)
- 2+ dirs (Tests/Feature **e** Tests/Unit, ou cobertura integral) → 4 pts

Granularidade nova distingue "parcial órfão" de "registro integral".

### Backward-compat dual-mode

Service v3.1 introduz config flag `governance.module_grade.d2_detection_mode`:

- `legacy` — heurística antiga (str_contains substring) — atual ADR 0155
- `hardened` — heurística nova desta ADR
- `both` — calcula ambos, retorna ambos no breakdown, score oficial = `hardened` mas log diff vs legacy pra auditoria

Default fase 1 = `both`. Default final (Fase 3+) = `hardened`.

## Consequências

### Positivas

- ✅ **Anti-gaming real**: scaffold vazio não infla nota mais
- ✅ **Multi-tenant não infla por nome**: D2.b exige asserção real → Tier 0 deixa de ser teatro
- ✅ **Baseline confiável**: drift entre nota e realidade CI fecha
- ✅ **Granularidade D2.c**: distingue registro integral vs parcial (incentivo claro a registrar ambos Feature + Unit)
- ✅ **Princípio Constituição v2 #4** honrado — métrica fiel à realidade

### Negativas

- ❌ **Drop estimado em 3-10 módulos** com cobertura fantasma:
  - ADS (Tests/Feature órfão) → -2 a -4 pts em D2
  - Possíveis outros (auditar Wave 3 com novo Service v3.1 dual-mode em `both`)
- ❌ **PRs follow-up necessários** pra cada módulo afetado:
  - Adicionar entry `./Modules/<X>/Tests/Feature` em phpunit.xml
  - Adicionar asserção real em test files scaffold vazios (ou remover arquivos vazios)
- ❌ **Gate CI [ADR 0155](0155-module-grade-v3-sub-dimensoes-gate-ci.md) bloqueia** PRs que ainda não acomodaram a heurística nova durante Fase 1 — exige rebaseline coordenado

### Mitigações

| Risco | Mitigação |
|---|---|
| Drop massivo médias surpresas time | Wagner anuncia em DSign + session log antes Fase 2 |
| PRs follow-up viram backlog perpétuo | Spawn batch tasks via `tasks-create` automaticamente após Fase 2 baseline novo (template "module:bootstrap-pest-registration <X>") |
| Falso positivo asserção D2.b (test usa fake assertion não-padrão) | Lista de patterns ampliada conforme review trigger; override via comment `// grade-d2b-override:<razão>` no header do arquivo |
| Gate CI bloqueia PR durante Fase 1 | Modo `both` Fase 1 reporta diff mas mantém score legacy oficial — Wagner switcha pra `hardened` ao final Fase 1 com baseline atualizado no MESMO PR |

## Plano migração

### Fase 1 — Service v3.1 dual-mode (1 dia)

- Implementar `ModuleGradeService` v3.1 com 3 sub-heurísticas novas
- Config flag `governance.module_grade.d2_detection_mode` (default = `both`)
- Comando `php artisan module:grade <X> --d2-detail` mostra breakdown legacy vs hardened lado a lado
- Pest cobertura: fixtures dos 3 cenários (ver Test plan)
- Sem mudança de baseline ainda — só diagnóstico

### Fase 2 — Backfill baseline com nova heurística (1 dia)

- Wagner roda `php artisan module:grade --all --d2-mode=hardened --update-baseline`
- PR commitando novo `memory/governance/module-grade-baseline.json` + ADR 0157 status `proposed → accepted`
- Spawn batch tasks follow-up pra módulos afetados (Tests/Feature órfão, asserções faltantes)

### Fase 3 — Switch default (após 7d sem falso positivo)

- Default config muda pra `hardened`
- Modo `legacy` continua disponível via flag pra debug
- Dashboard `/copiloto/admin/module-grades` mostra novo baseline + diff histórico

### Fase 4 — Sunset legacy (após 60d)

- Modo `legacy` removido do Service
- ADR 0157 vira parte canônica da rubrica v3 (não substitui [ADR 0155](0155-module-grade-v3-sub-dimensoes-gate-ci.md) — refina)

## Test plan

**Pest fixtures (em `tests/Feature/Governance/ModuleGradeV31D2Test.php`):**

| Cenário | Fixture | Asserção |
|---|---|---|
| `it_does_not_credit_unregistered_tests_feature_dir` | Módulo fake com Tests/Feature contendo 3 arquivos + Tests/Unit registrado no phpunit.xml fictício, mas Tests/Feature NÃO registrado | D2.c hardened = 2 (parcial), D2.a hardened conta só arquivos Tests/Unit |
| `it_does_not_credit_scaffold_file_without_assertions` | `MultiTenantTest.php` registrado, mas corpo só `it('exists', fn() => null);` sem `expect`/`assert` | D2.b hardened canonicalCount NÃO incrementa pra MultiTenant |
| `it_credits_full_when_feature_and_unit_both_registered_with_assertions` | Módulo com Tests/Feature + Tests/Unit ambos no phpunit.xml + arquivos têm `expect(...)->toBe(...)` | D2.a=8, D2.b=8, D2.c=4 (total D2=20) |
| `it_parses_xml_with_simplexml_not_substring` | phpunit.xml com `./Modules/ADSExtra/Tests` comentado (XML comment) | parser ignora → D2.c=0 (substring match casava falso positivo) |
| `it_distinguishes_partial_registration_in_d2c` | Só Tests/Unit registrado, sem Tests/Feature | D2.c hardened = 2 (parcial), legacy = 4 (substring) — diff visível em modo `both` |
| `it_legacy_mode_preserves_old_score` | Flag `legacy` ativa | Service retorna score idêntico v3 ADR 0155 (regressão zero) |
| `it_hardened_mode_drops_score_for_ads_like_fixture` | Fixture imitando ADS (Tests/Feature órfão, Tests/Unit registrado, MultiTenantDecisionTest.php scaffold sem expect) | D2 total hardened ~10-12, legacy 18-20 |
| `it_both_mode_logs_diff_without_changing_score` | Flag `both` | Output JSON contém `d2_legacy` + `d2_hardened` + `d2_diff_pts` — score oficial = `d2_hardened` mas legacy disponível pra debug |

**Smoke manual pós-Fase 1:**

1. `php artisan module:grade ADS --d2-detail` — confirmar diff visível (legacy=4 pts em D2.c, hardened=2 pts)
2. `php artisan module:grade --all --d2-mode=both` — listar módulos com `d2_diff_pts > 3` (candidatos a drop em Fase 2)
3. Forçar fixture vazio + commitar PR → confirmar gate CI **não** bloqueia em Fase 1 (modo `both` preserva legacy) e **bloqueia** em Fase 3 (após switch)

## Referências

**ADRs anteriores:**
- [ADR 0153 — module-grade-v1 (D2 original)](0153-module-grade-rubrica-v1.md)
- [ADR 0154 — v2 N/A justificado](0154-module-grade-v2-na-justificado.md)
- [ADR 0155 — v3 sub-dim + gate CI](0155-module-grade-v3-sub-dimensoes-gate-ci.md) (parent — esta ADR refina)
- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 — Constituição v2 (princípio 4: loop fechado por métrica)](0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0101 — Tests biz=1 nunca cliente](0101-tests-business-id-1-nunca-cliente.md)
- [ADR 0070 — Jira-style task management](0070-jira-style-task-management-current-md-removed.md) (tasks follow-up via MCP)

**Código atual referenciado:**
- [`Modules/Governance/Services/ModuleGradeService.php`](../../Modules/Governance/Services/ModuleGradeService.php) linhas 290-367 (método `dim2PestCoverage`)
- [`phpunit.xml`](../../phpunit.xml) (testsuite Feature linhas 16-53)

**Achado origem:**
- Audit Pest registration Wave 2 read-only (sessão 2026-05-16) — confirmou ADS Tests/Feature órfão (`MultiTenantDecisionTest.php`, `ScaffoldTest.php`, `SmokeRoutesTest.php`, `BrainBIsolationTest.php` em filesystem mas só `./Modules/ADS/Tests/Unit` no phpunit.xml)

**Proibições relevantes:**
- [memory/proibicoes.md](../proibicoes.md) §"Código" — "Não criar `Modules/X/Tests/` sem registrar em `phpunit.xml`" (esta ADR enforce métrica pra essa regra)
- [memory/proibicoes.md](../proibicoes.md) §"Multi-tenant Tier 0 IRREVOGÁVEL" — D2.b hardened protege esta regra de teatro

---

**Próxima ação Wagner:** revisar ADR + ajustar pesos D2.c parcial (2 pts) ou modo `both` Fase 1 default antes de marcar `status: accepted` → habilita Service v3.1 implementar.
