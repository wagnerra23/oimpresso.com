<?php

declare(strict_types=1);

/**
 * Guarda de regressão do bug 2026-07-23 (Fase #5): o `KBServiceProvider` não
 * chamava `loadMigrationsFrom`, então o `php artisan migrate --force` do deploy
 * (path default) PULAVA as migrations de `Modules/KB/` — a coluna
 * `code_drift_state` (Fase A1) ficou encalhada e o surface HealthPanel/NodeReader
 * não tinha o que renderizar em prod.
 *
 * Este teste morde se alguém remover o registro do path: o `migrator` precisa
 * conhecer `Modules/KB/Database/Migrations` pra o deploy aplicar as pendentes.
 *
 * NÃO usa auth/DB (só o container) → imune ao flake 403 random-order da lane KB.
 */

use Illuminate\Support\Collection;

it('registra o path de migrations do modulo KB no migrator (senao o deploy pula)', function () {
    $kbPath = realpath(base_path('Modules/KB/Database/Migrations'));

    expect($kbPath)->not->toBeFalse('pasta de migrations do KB deve existir');

    $registrados = (new Collection(app('migrator')->paths()))
        ->map(fn ($p) => realpath($p))
        ->filter()
        ->all();

    expect($registrados)->toContain($kbPath);
});
