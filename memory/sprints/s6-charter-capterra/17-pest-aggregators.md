# 17 — Pest aggregators (M2 + M3 implementados)

> **Spec dos testes Pest que computam M2 (GUARD pass rate) + M3 (charter coverage Tier A).**
> Implementação em [tests/Charter/CharterMetricsTest.php](../../../tests/Charter/CharterMetricsTest.php).

---

## CharterMetricsTest — formato

Pest test agregador (não usa class). Roda em CI + localmente:

```bash
./vendor/bin/pest tests/Charter/CharterMetricsTest.php
```

3 grupos de assertions:

### M3 — Coverage Tier A

```php
it('cobre todas as telas Tier A propostas com charter', function () {
    $expectedTierA = [
        'resources/js/Pages/Repair/Dashboard',
        'resources/js/Pages/Repair/JobSheet',
        'resources/js/Pages/Financeiro/Extrato',
        'resources/js/Pages/Repair/Status',
        'resources/js/Pages/Financeiro/ContasBancarias',
    ];

    foreach ($expectedTierA as $dir) {
        $charters = glob(base_path($dir).'/*.charter.md');
        expect($charters)->toHaveCount(
            atLeast: 1,
            and: 'tela esperada Tier A sem charter: '.$dir,
        );
    }
});
```

Sobre `atLeast`: Pest tem soft assertions; aqui qualquer >=1 charter aceito (incluindo supersedes `*-v2.md`).

### M2 — GUARD pass rate (frontmatter + 8 sections)

```php
it('todos os charters passam Tier 1 GUARD (frontmatter + 8 sections)', function () {
    $charters = collect(...);

    foreach ($charters as $charter) {
        expect($charter['frontmatter'])->toHaveKeys([
            'page', 'component', 'owner', 'status',
            'last_validated', 'parent_module', 'tier', 'charter_version',
        ]);

        expect($charter['sections'])->toContain(
            'Mission', 'Goals', 'Non-Goals', 'UX Targets',
            'UX Anti-patterns', 'Automation Hooks',
            'Automation Anti-hooks', 'Métricas vivas',
        );
    }
});
```

### M2 (parcial) — Non-Goals usam ❌

```php
it('todo Non-Goal e Anti-hook tem prefixo ❌', function () {
    // Mais Tier 1 GUARD: regex sobre seções específicas.
});
```

---

## Onde Pest agregadores diferem do `charter:audit`

| | `charter:audit` | `tests/Charter/CharterMetricsTest.php` |
|---|---|---|
| **Quando roda** | sob demanda (Wagner) ou cron daily 06:30 | em todo PR (CI) |
| **Output** | tabela ou JSON pra humano | red/green pra CI |
| **Falha bloqueia** | nada (exit 1 no terminal) | merge se rodar em hard mode |
| **Granularidade** | charter por charter | resumo agregado |

São complementares — audit é introspectivo; Pest é gate.

---

## Como F4 fecha M2 + M3

1. ✅ `tests/Charter/CharterMetricsTest.php` criado (deste PR)
2. ✅ Roda em CI via `charter-gate.yml` workflow (já existe — F1)
3. ✅ Resultado vai pra `mcp_audit_log` indireto (PR comment do workflow)
4. 🔲 Wagner em F5/S7 promove `charter-gate.yml` de soft → hard

---

## Critério de aceite F4 (M2 + M3)

- [x] Test agregador em `tests/Charter/CharterMetricsTest.php` 
- [x] Roda local: `./vendor/bin/pest tests/Charter/CharterMetricsTest.php`
- [x] Roda no CI quando PR toca `*.charter.md` ou `Pages/**/*.tsx`
- [x] Falha graciosamente em modo soft (workflow `continue-on-error: true`)
- [ ] PR comment mostra resultado por charter (já entregue em F1)
