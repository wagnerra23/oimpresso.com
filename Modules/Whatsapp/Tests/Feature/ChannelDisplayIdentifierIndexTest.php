<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Regression test: garante que migration de hardening
 * `2026_05_13_205208_add_index_display_identifier_to_channels` rodou e o
 * índice composto `(display_identifier, type)` existe.
 *
 * Por que: query `withoutGlobalScopes()->whereIn('type', [...])
 * ->where('display_identifier', $X)` no `ChannelRequest::withValidator()`
 * (PR #814) precisa do índice pra performance ao crescer a tabela. Sem o
 * índice, full table scan = lento.
 *
 * Este test só roda quando estamos em MySQL (não SQLite memory). Em SQLite
 * (ambiente CI default Pest) skipa graciosamente — a migration tem guard
 * `if (! Schema::hasTable('channels'))` e a checagem via SHOW INDEX é
 * MySQL-specific.
 *
 * @see Modules/Whatsapp/Database/Migrations/2026_05_13_205208_add_index_display_identifier_to_channels.php
 * @see Modules/Whatsapp/Http/Requests/ChannelRequest.php (PR #814)
 */
it('R-WA-IDX-001 — índice composto (display_identifier, type) existe em channels (MySQL only)', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Índice MySQL-specific — skip em SQLite memory.');
    }
    if (! Schema::hasTable('channels')) {
        $this->markTestSkipped('Tabela channels não existe — módulo Whatsapp não migrado.');
    }

    $rows = DB::select(
        "SHOW INDEX FROM channels WHERE Key_name = ?",
        ['channels_display_identifier_type_idx']
    );

    expect($rows)->not->toBeEmpty()
        ->and(count($rows))->toBe(2); // composite index = 2 rows (1 por coluna)

    // Confirma colunas no índice na ordem esperada (column 1: display_identifier; column 2: type)
    $columns = collect($rows)->sortBy('Seq_in_index')->pluck('Column_name')->all();
    expect($columns)->toBe(['display_identifier', 'type']);
});
