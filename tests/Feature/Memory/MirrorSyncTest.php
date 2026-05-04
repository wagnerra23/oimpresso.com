<?php

/**
 * Camada 1 — Eval Suite (Opção C).
 *
 * Garante que `php artisan memcofre:sync-memories --dry` não detecta drift
 * entre auto-mem (~/.claude/projects/D--oimpresso-com/memory/) e memory/claude/
 * versionado no git.
 *
 * Output do comando usa prefixos:
 *   "  + path" → adicionado (drift)
 *   "  ~ path" → modificado (drift)
 *   "  - path" → removido (sync já cobriu deletions, não é drift remanescente)
 *
 * Falha se houver `+` ou `~` (drift pendente que precisa commit+push).
 */

use Illuminate\Support\Facades\Artisan;

// TestCase aplicado pelo tests/Pest.php pra Feature/

it('Test 6: memcofre:sync-memories --dry exit 0', function () {
    $exit = Artisan::call('memcofre:sync-memories', ['--dry' => true]);
    expect($exit)->toBe(0, 'Comando memcofre:sync-memories falhou');
});

it('Test 7: memcofre:sync-memories --dry não reporta drift (+ ou ~)', function () {
    Artisan::call('memcofre:sync-memories', ['--dry' => true]);
    $output = Artisan::output();

    $drift = [];
    foreach (preg_split('/\r\n|\n|\r/', $output) as $line) {
        if (preg_match('/^\s*[+~]\s+\S/', $line)) {
            $drift[] = trim($line);
        }
    }

    expect($drift)->toBeEmpty(
        "Drift detectado entre auto-mem e memory/claude/. " .
        "Rode: php artisan memcofre:sync-memories\n" .
        "Linhas:\n  - " . implode("\n  - ", $drift)
    );
});
