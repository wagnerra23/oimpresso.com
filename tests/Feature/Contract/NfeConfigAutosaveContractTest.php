<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Tests\Contract\AutosaveContractRunner;

/**
 * Contract test do dominio NFe/Config (Fiscal/Config.tsx unificado + endpoints
 * NfeBrasil/Configuracao + NfeBrasil/Tributacao). Wagner 2026-05-27 — extensao
 * do ADR 0205 (contract tests autosave canon) pra dominio fiscal.
 *
 * Endpoints aqui retornam RedirectResponse — test usa custom loop inline (como
 * ServiceOrderEditAutosaveContractTest) em vez de AutosaveContractRunner::run.
 * Read-back via DB column lookup (source of truth direto).
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGAVEL):
 *   - setupContext autentica user + popula user.business_id
 *   - +session['business.id'] (validator NfeBrasil le dessa chave)
 *   - +permission superadmin (FormRequests autorizam por can('nfe.tributacao.manage'))
 *
 * EXCLUIDOS: upload .pfx (multipart, fixture proprio), testar SEFAZ (action),
 * senhas/CSC/cert (Vaultwarden — NUNCA DB).
 *
 * @see Modules\NfeBrasil\Http\Controllers\CertificadoController::updateAmbiente
 * @see Modules\NfeBrasil\Http\Controllers\TributacaoController::toggleAutoEmission
 * @see Modules\NfeBrasil\Http\Controllers\ConfigDefaultController::upsert
 * @see ADR 0205 (contract tests autosave canon) · ADR 0142 (ambiente 1/2)
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: requer schema MySQL UltimatePOS (ADR 0101) + tabelas NfeBrasil');
    }
    if (! Schema::hasTable('nfe_business_configs')) {
        $this->markTestSkipped('Schema NfeBrasil ausente — rode `php artisan module:migrate NfeBrasil`');
    }
    if (! Schema::hasColumn('business', 'ambiente')) {
        $this->markTestSkipped('Coluna business.ambiente ausente — ADR 0142 migration nao rodou');
    }
    if (! Schema::hasColumn('nfe_business_configs', 'auto_emission_enabled')) {
        $this->markTestSkipped('Coluna auto_emission_enabled ausente — migration 2026_05_08 nao rodou');
    }

    $ctx = AutosaveContractRunner::setupContext($this);
    $this->business = $ctx['business'];
    $this->user = $ctx['user'];

    // Validator NfeBrasil le `business.id` da session (setupContext popula apenas user.business_id).
    session(['business.id' => $this->business->id]);

    // FormRequests exigem `nfe.tributacao.manage` (validator authorize).
    // superadmin e wildcard — skip gracioso se role nao existir no env.
    try {
        $this->user->givePermissionTo('superadmin');
    } catch (\Throwable $e) {
        $this->markTestSkipped('Permission `superadmin` indisponivel — rode seeder de roles antes');
    }

    // Seed baseline nfe_business_configs — toggleAutoEmission + upsert dependem da row.
    DB::table('nfe_business_configs')->updateOrInsert(
        ['business_id' => $this->business->id],
        [
            'regime' => 'simples',
            'tributacao_default' => json_encode([
                'ncm_default' => '00000000', 'cfop_default' => '5102', 'cfop' => '5102',
                'csosn' => '102', 'aliquota_icms' => 0.0, 'aliquota_pis' => 0.0, 'aliquota_cofins' => 0.0,
            ]),
            'auto_emission_enabled' => false,
            'updated_at' => now(), 'created_at' => now(),
        ]
    );
});

it('NFe/Config — 3 endpoints autosave persistem TODOS os campos do fixture (round-trip DB read-back)', function () {
    $fixture = require __DIR__ . '/../../Contract/Fixtures/nfe_config.php';

    $passed = 0;
    $total = 0;
    $failures = [];

    foreach ($fixture as $tabName => $tabSpec) {
        $endpoint = $tabSpec['endpoint'];
        $method = strtolower($tabSpec['method'] ?? 'post');
        $expectStatus = $tabSpec['expectStatus'] ?? 302;
        $readBack = $tabSpec['read_back'] ?? 'db';
        $table = $tabSpec['table'];
        $baseFields = $tabSpec['baseFields'] ?? [];

        $where = [];
        foreach (($tabSpec['where'] ?? []) as $col => $val) {
            $where[$col] = is_string($val)
                ? str_replace('{business_id}', (string) $this->business->id, $val)
                : $val;
        }

        foreach ($tabSpec['fields'] as $field) {
            $total++;
            $sendKey = $field['send'];
            $recvKey = $field['recv'];
            $match = $field['match'] ?? 'equals';
            $sent = $field['value'];

            $payload = array_merge($baseFields, [$sendKey => $sent]);

            $response = match ($method) {
                'put' => $this->put($endpoint, $payload),
                'patch' => $this->patch($endpoint, $payload),
                default => $this->post($endpoint, $payload),
            };
            $status = $response->status();
            $statusOk = $status === $expectStatus || ($expectStatus === 302 && in_array($status, [200, 302], true));

            // Read-back source of truth direto.
            $received = null;
            if ($readBack === 'model' && $table === 'nfe_business_configs') {
                // Eloquent → tributacao_default cast como array.
                $model = NfeBusinessConfig::where($where)->first();
                $received = $model ? data_get($model->toArray(), $recvKey) : null;
            } elseif (! empty($field['json_path'])) {
                $row = (array) DB::table($table)->where($where)->first();
                $jsonCol = explode('.', $field['json_path'])[0];
                $rawJson = $row[$jsonCol] ?? null;
                $decoded = is_string($rawJson) ? json_decode($rawJson, true) : $rawJson;
                $nested = substr($field['json_path'], strlen($jsonCol) + 1);
                $received = $nested === '' ? $decoded : data_get($decoded, $nested);
            } elseif (! empty($field['column'])) {
                $received = DB::table($table)->where($where)->value($field['column']);
            }

            $valueOk = matchesNfeConfigValue($sent, $received, $match);

            if (! $statusOk || ! $valueOk) {
                $failures[] = [
                    'tab' => $tabName, 'endpoint' => $endpoint, 'method' => $method,
                    'send' => $sendKey, 'value_sent' => is_scalar($sent) ? (string) $sent : json_encode($sent),
                    'recv' => $recvKey, 'value_received' => is_scalar($received) ? (string) $received : json_encode($received),
                    'status' => $status, 'match_mode' => $match,
                ];
            } else {
                $passed++;
            }
        }
    }

    if ($passed !== $total) {
        $msg = "Contract test FALHOU — {$passed}/{$total} OK.\n\nBugs silenciosos detectados (POST aceito mas valor nao bate em DB read-back):\n";
        foreach ($failures as $f) {
            $msg .= sprintf(
                "  [%s] %s %s — send=%s value_sent=%s recv=%s value_received=%s status=%d match=%s\n",
                $f['tab'], strtoupper($f['method']), $f['endpoint'], $f['send'], var_export($f['value_sent'], true),
                $f['recv'], var_export($f['value_received'], true), $f['status'], $f['match_mode']
            );
        }
        $msg .= "\nADR 0205 — todo PR que regrida contract test bloqueia merge.\n";
        expect($failures)->toBeEmpty($msg);
    }

    expect($passed)->toBe($total);
});

/**
 * Match modes — estende AutosaveContractRunner::matches com modo 'float'
 * (tolerancia 1e-6 pra comparar floats DB roundtrip string vs PHP float).
 */
function matchesNfeConfigValue(mixed $sent, mixed $received, string $match): bool
{
    return match ($match) {
        'partial' => $received !== null && is_string($received)
            && str_contains((string) $received, is_string($sent) ? $sent : (string) $sent),
        'bool' => (bool) $received === (bool) $sent,
        'int' => (int) $received === (int) $sent,
        'float' => is_numeric($received) && is_numeric($sent)
            && abs((float) $received - (float) $sent) < 1e-6,
        'array_eq' => json_encode($received) === json_encode($sent),
        default => $received === $sent || (string) $received === (string) $sent,
    };
}
