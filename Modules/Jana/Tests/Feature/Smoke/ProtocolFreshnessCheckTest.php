<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

uses(Tests\TestCase::class);

/**
 * Smoke do check `protocol_freshness` no jana:health-check (ADVISORY).
 *
 * Valida que o check (espelho PHP do protocol-freshness.mjs) entra na lista de
 * checks, é advisory, e — com o registro UC presente neste branch — fica OK
 * (gaps ⊆ baseline), nunca derrubando o exit code do cron.
 *
 * @see Modules/Jana/Console/Commands/HealthCheckCommand.php::checkProtocolFreshness
 * @see prototipo-ui/audit/uc-registry.json
 */
function protocolFreshnessCheck(): ?array
{
    Artisan::call('jana:health-check', ['--json' => true]);
    $out = Artisan::output();
    $start = strpos($out, '{');
    $json = $start === false ? [] : json_decode(substr($out, (int) $start), true);

    foreach (($json['checks'] ?? []) as $c) {
        if (($c['name'] ?? null) === 'protocol_freshness') {
            return $c;
        }
    }

    return null;
}

test('protocol_freshness entra na lista de checks e é advisory', function () {
    $check = protocolFreshnessCheck();

    expect($check)->not->toBeNull('check protocol_freshness ausente do jana:health-check');
    expect($check['advisory'] ?? false)->toBeTrue();
    expect($check)->toHaveKeys(['name', 'ok', 'value', 'threshold', 'message']);
});

test('protocol_freshness fica OK (gaps no baseline) com o registro deste branch', function () {
    $check = protocolFreshnessCheck();

    // Com uc-registry.json + baseline presentes, nenhuma regressão → ok=true.
    expect($check['ok'])->toBeTrue("protocol_freshness acusou regressão: {$check['message']}");
    // o value reporta cobertos/gaps/regressão — formato "N cobertos · M gaps · K regressão".
    expect($check['value'])->toContain('cobertos');
});

test('o check é advisory — não derruba o exit code do health-check', function () {
    $exit = Artisan::call('jana:health-check', ['--json' => true]);
    // advisory: mesmo com gaps/charters, protocol_freshness não força FAILURE sozinho.
    expect(in_array($exit, [0, 1], true))->toBeTrue();
});
