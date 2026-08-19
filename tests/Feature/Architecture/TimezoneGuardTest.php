<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse.

/**
 * ARCHITECTURE TEST — guardas de fuso horário (G1–G4). O bug reincidente das 3 h.
 *
 * Estado MEDIDO no main em 2026-08-19 (cada número abaixo veio de `git grep`, não de leitura):
 *  - config/app.php:69 tinha `env('APP_TIMEZONE', 'Europe/London')` — todo caminho SEM sessão
 *    (job de fila, cron, API, emissão) rodava em Londres;
 *  - o env de exemplo da raiz declarava `APP_TIMEZONE="Asia/Kolkata"` (herança UltimatePOS) —
 *    ou seja, eram DOIS fusos errados, não um;
 *  - app/Http/Middleware/Timezone.php corrige por request, mas só onde há sessão;
 *  - 4 arquivos em app/ chamam date_default_timezone_set fora do middleware, nenhum restaura.
 *
 * Estes testes são de ARQUITETURA: leem o código-fonte. As baselines existem para "não piorar" —
 * cada ofensor conhecido está listado; ao corrigir um, REMOVA-O da lista (o teste passa a exigir
 * menos). Nunca ADICIONE nome à lista para calar o teste: isenção que cresce vira allowlist morta
 * (memory/proibicoes.md §5 2026-08-04).
 *
 * Derivado de um rascunho do [CC] (2026-08-19) que NÃO havia sido executado. Três defeitos
 * corrigidos aqui, todos medidos antes:
 *   1. o regex '/foreach|while|->chunk(/' não compila ("Unterminated group") — o parêntese não
 *      estava escapado, então o G2b nunca mediu nada;
 *   2. o G1 exigia base_path('.env.example'), arquivo que NÃO existe neste repo (o exemplo da raiz
 *      chama-se ".env - Copia.example"); a busca aqui é por sufixo e enxerga dotfile;
 *   3. as baselines omitiam app/Utils/Util.php (G2) e inflavam o G3 com 2 arquivos que não têm o
 *      literal — allowlist que não corresponde ao real esvazia o gate.
 */

use Illuminate\Support\Facades\File;

/** Fontes .php sob um diretório do projeto, com path relativo normalizado em '/'. */
function fontesDeAppTz(string $dir): array
{
    $base = base_path($dir);
    if (! File::isDirectory($base)) {
        return [];
    }

    $raiz = str_replace('\', '/', base_path()).'/';

    return collect(File::allFiles($base))
        ->filter(fn ($f) => $f->getExtension() === 'php')
        ->map(fn ($f) => [
            'path' => str_replace($raiz, '', str_replace('\', '/', $f->getPathname())),
            'src' => File::get($f->getPathname()),
        ])
        ->values()
        ->all();
}

/** Arquivos *.example na raiz — via scandir porque glob('*') NÃO enxerga dotfile. */
function exemplosDeEnvNaRaiz(): array
{
    return collect(scandir(base_path()) ?: [])
        ->filter(fn ($n) => str_ends_with($n, '.example'))
        ->filter(fn ($n) => is_file(base_path($n)))
        ->values()
        ->all();
}

// ── G1 · nunca mais Europe/London ───────────────────────────────────────────
it('G1: o default de app.timezone não é Europe/London', function () {
    expect(File::get(config_path('app.php')))->not->toContain("'Europe/London'");
});

it('G1: todo env de exemplo que declara APP_TIMEZONE usa fuso brasileiro', function () {
    $exemplos = exemplosDeEnvNaRaiz();
    // se não houver *.example na raiz, o teste perdeu o alvo — falha aqui, não em silêncio
    expect($exemplos)->not->toBeEmpty();

    $declaram = [];
    $errados = [];
    foreach ($exemplos as $nome) {
        $src = File::get(base_path($nome));
        if (! str_contains($src, 'APP_TIMEZONE=')) {
            continue;
        }
        $declaram[] = $nome;
        if (preg_match('/^APP_TIMEZONE=.*America\//m', $src) !== 1) {
            $errados[] = $nome;
        }
    }

    // nenhum exemplo declara APP_TIMEZONE = a guarda não tem o que medir
    expect($declaram)->not->toBeEmpty();
    expect($errados)->toBe([]);
});

it('G1: em runtime o fuso efetivo é brasileiro', function () {
    expect(config('app.timezone'))->toStartWith('America/');
    expect(date_default_timezone_get())->toStartWith('America/');
});

// ── G2 · estado global de fuso só no middleware ──────────────────────────────
it('G2: date_default_timezone_set em app/ só nos arquivos conhecidos', function () {
    $permitidos = [
        'app/Http/Middleware/Timezone.php',   // legítimo: corrige por request
        // Chamam, mas RESTAURAM o fuso anterior (corrigido na PR-2, 2026-08-19):
        'app/Console/Commands/RecurringExpense.php',
        'app/Console/Commands/RecurringInvoice.php',
        'app/Utils/Util.php',
        // Ainda não restaura — fiscal, fica para PR própria:
        'app/Http/Controllers/NfeController.php',
    ];

    $ofensores = collect(fontesDeAppTz('app'))
        ->filter(fn ($f) => str_contains($f['src'], 'date_default_timezone_set'))
        ->pluck('path')
        ->reject(fn ($p) => in_array($p, $permitidos, true))
        ->values();

    expect($ofensores->all())->toBe([]);
});

it('G2: nenhum date_default_timezone_set novo dentro de loop sem restaurar', function () {
    $suspeitos = [];
    foreach (fontesDeAppTz('app') as $f) {
        if (! str_contains($f['src'], 'date_default_timezone_set')) {
            continue;
        }
        $temLoop = preg_match('/foreach|while|->chunk\(/', $f['src']) === 1;
        $restaura = str_contains($f['src'], 'finally')
            || substr_count($f['src'], 'date_default_timezone_set') >= 2;
        if ($temLoop && ! $restaura) {
            $suspeitos[] = $f['path'];
        }
    }
    sort($suspeitos);

    // Baseline APERTADA pela PR-2: eram 4 medidos, sobrou 1. Só o NfeController segue sem
    // restaurar — é caminho fiscal e sai em PR própria. NUNCA somar nome aqui para calar o teste.
    expect($suspeitos)->toBe([
        'app/Http/Controllers/NfeController.php',
    ]);
});

// ── G3 · fuso não é literal espalhado ───────────────────────────────────────
it('G3: o literal America/Sao_Paulo não se espalha por app/', function () {
    $permitidos = [
        'app/Console/Kernel.php',                            // agendador: ~50 schedules
        'app/Http/Controllers/NfeController.php',
        'app/Http/Controllers/Auth/SocialAuthController.php', // default de cadastro de negócio
    ];

    $ofensores = collect(fontesDeAppTz('app'))
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
    expect($sp->copy()->utc()->format('Y-m-d H:i'))->toBe('2026-08-20 02:30');
});
