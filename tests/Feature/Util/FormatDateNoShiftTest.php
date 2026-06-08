<?php

declare(strict_types=1);

use App\Utils\Util;

/**
 * Pest test — Util::format_date_no_shift retorna data DB SEM shift +3h.
 *
 * Bug Wagner @ Larissa 2026-05-27: recibo venda 2026/2986 mostrava 23:47
 * quando transaction_date no DB era 20:47 (+3h shift do format_date legacy).
 * Diagnóstico via workflow debug-tz-info.yml. Fix: novo helper sem shift.
 */

it('format_date_no_shift retorna valor exato do DB sem shift +3h', function () {
    $util = new Util();
    $bizDetails = (object)['date_format' => 'd/m/Y', 'time_format' => 24];
    // Input: timestamp DB (que era exibido como 23:47 pelo format_date com bug)
    $dbDate = '2026-05-27 20:47:00';
    $result = $util->format_date_no_shift($dbDate, true, $bizDetails);
    expect($result)->toBe('27/05/2026 20:47');
});

it('format_date_no_shift retorna null pra date vazio', function () {
    $util = new Util();
    expect($util->format_date_no_shift('', true))->toBeNull();
    expect($util->format_date_no_shift(null, true))->toBeNull();
});

it('format_date_no_shift sem show_time retorna apenas data', function () {
    $util = new Util();
    $bizDetails = (object)['date_format' => 'd/m/Y', 'time_format' => 24];
    expect($util->format_date_no_shift('2026-05-27 20:47:00', false, $bizDetails))
        ->toBe('27/05/2026');
});

it('format_date_no_shift respeita time_format=12 (AM/PM)', function () {
    $util = new Util();
    $bizDetails = (object)['date_format' => 'd/m/Y', 'time_format' => 12];
    $result = $util->format_date_no_shift('2026-05-27 20:47:00', true, $bizDetails);
    expect($result)->toContain('PM');
    expect($result)->toContain('27/05/2026');
});
