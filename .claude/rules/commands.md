---
paths:
  - "app/Console/Commands/**/*.php"
  - "Modules/**/Console/**/*.php"
---

# Rule path-scoped — Artisan Commands

> Carrega quando Claude lê/edita Artisan Command. Convenções específicas pra evitar bugs históricos.

## `--detail` NUNCA `--verbose` (Symfony reserved)

Lição cara catalogada [handoff 2026-05-14 18:34](../../memory/handoffs/2026-05-14-1834-whatsapp-purge-fix-verbose.md) — PR #851 fix obrigatório.

> Symfony Console define `--verbose` (`-v`/`-vv`/`-vvv`) como flag padrão de TODOS os commands. Declarar `--verbose` custom no signature CRASHA o command na execução (`LogicException: An option named "verbose" already exists`). `whatsapp:channels-reconcile` ficou 100% quebrado desde 13/mai/2026 até PR #851 — cron daily falhando silenciosamente.

### Pattern OBRIGATÓRIO

```php
// ✅ --detail (custom, sem conflito)
protected $signature = 'whatsapp:scan-drift {--detail : Log detalhado por canal}';

// ❌ PROIBIDO — colide com Symfony default
protected $signature = 'whatsapp:scan-drift {--verbose : ...}';
```

Reservados Symfony Console: `--verbose`, `--quiet`, `--help`, `--version`, `--ansi`, `--no-ansi`, `--no-interaction`, `--env`.

## Multi-tenant em commands Tier 0

Commands CLI rodam fora de HTTP — `session()` retorna null, `auth()` retorna null. Pattern obrigatório pra dados scoped por business:

```php
public function handle(): int
{
    $bizId = (int) $this->option('business-id');
    if ($bizId <= 0) {
        $this->error('--business-id obrigatório (multi-tenant Tier 0 ADR 0093)');
        return 1;
    }

    Auth::loginUsingId(/* superadmin biz=1 */);
    session(['business' => Business::find($bizId)]);
    // agora queries global scope respeitam o tenant
}
```

Ver [ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) §"Commands & Jobs sem HTTP context".

## Schedule em `app/Console/Kernel.php`

- Health checks recurring: `php artisan jana:health-check` daily 06:00 BRT (5 checks SQL)
- Commands cross-business: iterate `Business::active()->each()` dentro do command, NUNCA dispatch parallel scheduler (race condition session())

## Skills relacionadas

`multi-tenant-patterns` (Tier A) · `preflight-modulo` (Tier A) · `runtime-rules-hostinger-ct100` (Tier B)
