# tests/Feature/Modules — batch 7 (legados)

Testes Pest para os módulos legados cobertos no batch 7.

## Pré-requisitos (uma vez)

```bash
composer require --dev pestphp/pest:^6 pestphp/pest-plugin-laravel:^2
php artisan vendor:publish --provider="NunoMaduro\Collision\Adapters\Laravel\CollisionServiceProvider"
```

## Como rodar

Por módulo (filtro pelo nome do diretório/arquivo):

```bash
vendor/bin/pest --filter=Repair
vendor/bin/pest --filter=Help
vendor/bin/pest --filter=Officeimpresso
vendor/bin/pest --filter=Superadmin
vendor/bin/pest --filter=Woocommerce
```

Tudo de uma vez:

```bash
vendor/bin/pest tests/Feature/Modules
```

Em fallback PHPUnit puro (já configurado em `phpunit.xml`):

```bash
vendor/bin/phpunit --testsuite=Feature --filter=Modules
```

## Convenções

- Cada subpasta cobre um módulo (`Repair/`, `Help/`, ...).
- Estilo Pest funcional (`it()`, `expect()`, `beforeEach()`).
- Helpers compartilhados em `tests/Pest.php` (`routeExists`,
  `routeMiddleware`, `moduleRoute`).
- Base `ModuleTestCase` para criar `Business`/`User` rapidamente
  (multi-tenant UltimatePOS).

## SPEC.md por módulo

- `Modules/Repair/SPEC.md`
- `Modules/Help/SPEC.md`
- `Modules/Officeimpresso/SPEC.md`
- `Modules/Superadmin/SPEC.md`
- `Modules/Woocommerce/SPEC.md`
- `Modules/WRITEBOT.md` (módulo ausente — recomendar deprecar)
