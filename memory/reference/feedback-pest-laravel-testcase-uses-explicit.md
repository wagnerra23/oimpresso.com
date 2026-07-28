---
id: reference-feedback-pest-laravel-testcase-uses-explicit
type: feedback
domain: testing
date: 2026-05-28
discovered_in: ADR 0216 Drift Framework implementation (PR #1874)
status: confirmed
---

# Pest 4 + Laravel — `uses(Tests\TestCase::class)` explicit obrigatório por file

## Sintoma

Test em `Modules/<X>/Tests/Feature/<Y>Test.php` que chama `app(...)`, `config(...)`, `Artisan::call(...)`, ou qualquer facade lança:

```
RuntimeException: A facade root has not been set.
```

## Por que acontece

Pest 4 reseta container entre tests. Mesmo com `Modules/<X>/Tests/Pest.php` declarando `uses(TestCase::class)->in(__DIR__);`, o boot da application pelo TestCase não acontece sem `uses(Tests\TestCase::class);` declarado **no topo do file específico** (após `use` imports).

Causa: o Pest `->in(__DIR__)` é genérico ao diretório; binding individual ao TestCase só acontece quando declarado por file.

Confirmado empiricamente 2026-05-28: 10 testes do `GovernanceAuditCommandTest.php` falhando com facade error → após adicionar `uses(Tests\TestCase::class);` → 10/10 verde imediato.

## Como aplicar

Sempre que criar Pest test em `Modules/<X>/Tests/Feature/*.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;  // ou outras facades
use Modules\Foo\Services\MyService;
use Tests\TestCase;

uses(Tests\TestCase::class);  // <-- OBRIGATÓRIO no topo do file

// ... rest of test
```

Adicional: `beforeEach()` NÃO consegue chamar `app()->instance(...)` confiavelmente porque facade root pode ainda não estar setado naquele callback. Padrão que funciona é **helper função inline reset** em cada `it()`:

```php
function resetMyService(): MyService
{
    app()->forgetInstance(MyService::class);
    $s = new MyService();
    app()->instance(MyService::class, $s);
    return $s;
}

it('teste exemplo', function () {
    $service = resetMyService();
    // ...
});
```

## Refs

- PR #1874 commit 066cd96bf
- Tests com pattern correto: `Modules/Governance/Tests/Feature/GovernanceAuditCommandTest.php` + `DriftCheckerRegistryTest.php`
- Tests sem pattern (erro provável): ver `git log -p` antes do fix em `Modules/Governance/Tests/Feature/`
