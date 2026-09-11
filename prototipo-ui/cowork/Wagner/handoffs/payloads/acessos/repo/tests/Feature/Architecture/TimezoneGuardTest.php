<?php

/**
 * Guardas de fuso horário (G1–G3) — o bug reincidente das 3 h.
 *
 * Contexto lido no main em 2026-08-19:
 *  - config/app.php: 'timezone' => env('APP_TIMEZONE', 'Europe/London')  ← o fallback é LONDRES;
 *  - app/Http/Middleware/Timezone.php corrige por request, mas só onde há sessão;
 *  - app/Console/Kernel.php tem ~50 ->timezone('America/Sao_Paulo') hardcoded;
 *  - RecurringExpense.php:60 e RecurringInvoice.php:66 fazem date_default_timezone_set DENTRO do loop,
 *    sem restaurar → o fuso do último negócio vaza para o próximo;
 *  - NfeController.php:881 e SocialAuthController.php:162 usam o literal.
 *
 * Estes testes são de ARQUITETURA: leem o código-fonte. A baseline existe para "não piorar" —
 * cada ofensor conhecido está listado; ao corrigir um, remova-o da lista (o teste passa a exigir zero).
 *
 * Escrito por [CC] em 2026-08-19 e NÃO executado aqui.
 */

use Illuminate\Support\Facades\File;

function fontesDe(string $dir): array
{
    $base = base_path($dir);
    if (! File::isDirectory($base)) {
        return [];
    }

    return collect(File::allFiles($base))
        ->filter(fn ($f) => $f->getExtension() === 'php')
        ->map(fn ($f) => ['path' => str_replace(base_path().'/', '', $f->getPathname()), 'src' => File::get($f->getPathname())])
        ->values()
        ->all();
}

// ── G1 · nunca mais Europe/London ───────────────────────────────────────────
it('G1: o default de app.timezone não é Europe/London', function () {
    $src = File::get(config_path('app.php'));

    expect($src)->not->toContain("'Europe/London'");
});

it('G1: APP_TIMEZONE está declarado no .env.example', function () {
    $exemplo = base_path('.env.example');
    expect(File::exists($exemplo))->toBeTrue();
    expect(File::get($exemplo))->toContain('APP_TIMEZONE=');
});

it('G1: em runtime o fuso efetivo é brasileiro', function () {
    expect(config('app.timezone'))->toStartWith('America/');
    expect(date_default_timezone_get())->toStartWith('America/');
});

// ── G2 · estado global de fuso só no middleware ──────────────────────────────
it('G2: date_default_timezone_set só existe no middleware Timezone', function () {
    $permitidos = [
        'app/Http/Middleware/Timezone.php',
        // Ofensores conhecidos (baseline 2026-08-19) — remover conforme as PRs 1 e 2 entrarem:
        'app/Console/Commands/RecurringExpense.php',
        'app/Console/Commands/RecurringInvoice.php',
        'app/Http/Controllers/NfeController.php',
    ];

    $ofensores = collect(fontesDe('app'))
        ->filter(fn ($f) => str_contains($f['src'], 'date_default_timezone_set'))
        ->pluck('path')
        ->reject(fn ($p) => in_array($p, $permitidos, true))
        ->values();

    expect($ofensores->all())->toBe([]);
});

it('G2: nenhum date_default_timezone_set dentro de foreach sem restaurar', function () {
    $suspeitos = [];
    foreach (fontesDe('app') as $f) {
        if (! str_contains($f['src'], 'date_default_timezone_set')) {
            continue;
        }
        $temLoop = preg_match('/foreach|while|->chunk(/', $f['src']) === 1;
        $restaura = str_contains($f['src'], 'finally') || substr_count($f['src'], 'date_default_timezone_set') >= 2;
        if ($temLoop && ! $restaura) {
            $suspeitos[] = $f['path'];
        }
    }

    // Baseline: os dois comandos de recorrentes. Ao corrigir (PR-2), esta lista some.
    expect($suspeitos)->toBe([
        'app/Console/Commands/RecurringExpense.php',
        'app/Console/Commands/RecurringInvoice.php',
    ]);
});

// ── G3 · fuso não é literal espalhado ───────────────────────────────────────
it('G3: o literal America/Sao_Paulo não se espalha por app/ (fora do agendador)', function () {
    $permitidos = [
        'app/Console/Kernel.php', // ~50 schedules — alvo da PR-2/G3, sai depois
        'app/Http/Controllers/NfeController.php',
        'app/Http/Controllers/Auth/SocialAuthController.php',
        'app/Console/Commands/RecurringExpense.php',
        'app/Console/Commands/RecurringInvoice.php',
    ];

    $ofensores = collect(fontesDe('app'))
        ->filter(fn ($f) => str_contains($f['src'], "'America/Sao_Paulo'"))
        ->pluck('path')
        ->reject(fn ($p) => in_array($p, $permitidos, true))
        ->values();

    expect($ofensores->all())->toBe([]);
});

// ── G4 · ida e volta: mesmo instante em dois fusos ──────────────────────────
it('G4: instante gravado em SP é o mesmo lido em Manaus', function () {
    $sp = \Carbon\Carbon::parse('2026-08-19 23:30:00', 'America/Sao_Paulo');
    $manaus = $sp->copy()->setTimezone('America/Manaus');

    expect($manaus->format('H:i'))->toBe('22:30');
    expect($manaus->getTimestamp())->toBe($sp->getTimestamp());
    expect($sp->utc()->format('Y-m-d H:i'))->toBe('2026-08-20 02:30');
});
