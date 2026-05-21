<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use Illuminate\Support\Facades\Blade;

/**
 * Regressão hotfix 500 superadmin (2026-05-21):
 *
 * As diretivas Blade `@format_date`, `@format_datetime` e `@format_now_local`
 * usavam `session('business.date_format')` sem fallback. Em rotas como
 * `/superadmin` (sem middleware `SetSessionData`), a session não tem
 * `business.date_format` → null → `Carbon::format(null)` lança `TypeError`
 * → 500 na view do header (resources/views/layouts/partials/header.blade.php:189).
 *
 * Fix: fallback `config('constants.default_date_format', 'd/m/Y')`.
 *
 * @see app/Providers/AppServiceProvider.php boot()
 */
function renderBladeDirective(string $template): string
{
    $compiled = Blade::compileString($template);
    ob_start();
    eval('?>' . $compiled);
    return ob_get_clean();
}

it('@format_date com session vazia renderiza sem TypeError', function () {
    session()->forget('business');

    $rendered = renderBladeDirective("{{ @format_date('now') }}");

    expect($rendered)->not->toBeEmpty();
});

it('@format_datetime com session vazia renderiza sem TypeError', function () {
    session()->forget('business');

    $rendered = renderBladeDirective("{{ @format_datetime('now') }}");

    expect($rendered)->not->toBeEmpty();
});

it('@format_now_local com session vazia renderiza sem TypeError', function () {
    session()->forget('business');

    $rendered = renderBladeDirective('{{ @format_now_local }}');

    expect($rendered)->not->toBeEmpty();
});

it('@format_date usa date_format da session quando presente', function () {
    session(['business' => ['date_format' => 'Y-m-d']]);

    $rendered = renderBladeDirective("{{ @format_date('2026-01-15') }}");

    expect($rendered)->toBe('2026-01-15');
});

it('@format_date faz fallback pra config constants.default_date_format', function () {
    session()->forget('business');
    config(['constants.default_date_format' => 'Y-m-d']);

    $rendered = renderBladeDirective("{{ @format_date('2026-01-15') }}");

    expect($rendered)->toBe('2026-01-15');
});
