<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Tests\Contract\AutosaveContractRunner;

/**
 * Contract test do CRUD NCM rules NFe (Modules/NfeBrasil/Tributacao).
 *
 * Wagner 2026-05-27 — proxima onda do framework canon ADR 0205 cobrindo
 * subdominio "Regras NCM por UF" (diferente de nfe_config.php que cobre
 * config DEFAULT do business). Cobre POST store + PUT update.
 *
 * Endpoints retornam RedirectResponse — test usa custom loop inline (como
 * NfeConfigAutosaveContractTest + ServiceOrderEditAutosaveContractTest) em
 * vez de AutosaveContractRunner::run. Read-back via Eloquent NfeFiscalRule
 * (casts float aplicados — source of truth direto, espelha pattern
 * nfe_config `read_back=model`).
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGAVEL):
 *   - setupContext autentica user + popula user.business_id
 *   - +session['business.id'] (TributacaoController + validator leem dessa chave)
 *   - +permission superadmin (FormRequest authorize por can('nfe.tributacao.manage'))
 *
 * Mutex CSOSN/CST tratado no fixture: 2 tabs PUT separadas (csosn default,
 * cst caminho separado com csosn=null). Validator::withValidator rejeita
 * ambos preenchidos.
 *
 * EXCLUIDOS: DELETE (destrutivo), Import CSV (multipart), campos NT 2025.002
 * (validator ainda nao aceita c_class_trib/cst_ibs/etc).
 *
 * @see Modules\NfeBrasil\Http\Controllers\TributacaoController::store
 * @see Modules\NfeBrasil\Http\Controllers\TributacaoController::update
 * @see Modules\NfeBrasil\Http\Requests\UpsertRegraTributariaRequest
 * @see ADR 0205 (contract tests autosave canon)
 * @see tests/Feature/Contract/NfeConfigAutosaveContractTest.php — fixture relacionado
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Pre-flight 1: DB driver — schema NfeBrasil exige MySQL.
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: requer schema MySQL UltimatePOS (ADR 0101) + tabelas NfeBrasil');
    }
    // Pre-flight 2: tabela alvo. Skip se modulo nao migrado neste env.
    if (! Schema::hasTable('nfe_fiscal_rules')) {
        $this->markTestSkipped('Schema NfeBrasil ausente — rode `php artisan module:migrate NfeBrasil`');
    }

    $ctx = AutosaveContractRunner::setupContext($this);
    $this->business = $ctx['business'];
    $this->user = $ctx['user'];

    // TributacaoController + validator leem `business.id` da session.
    // setupContext popula `user.business_id` mas controller usa chave diferente.
    session(['business.id' => $this->business->id]);

    // FormRequest exige `nfe.tributacao.manage` (validator authorize).
    // superadmin e wildcard — skip gracioso se role nao existir no env.
    try {
        $this->user->givePermissionTo('superadmin');
    } catch (\Throwable $e) {
        $this->markTestSkipped('Permission `superadmin` indisponivel — rode seeder de roles antes');
    }

    // Cria regra BASE pra PUT updates. POST iteracoes criam rows proprias.
    // NCM/UF distintos do payload de teste pra evitar colisao de read-back
    // (test query especifica por id, mas mantemos higiene de fixture data).
    $this->regraId = DB::table('nfe_fiscal_rules')->insertGetId([
        'business_id'     => $this->business->id,
        'ncm'             => '00000000',           // sentinela — diferente de '87082999' de teste
        'uf_origem'       => 'SP',
        'uf_destino'      => 'RJ',
        'cfop'            => '5102',
        'csosn'           => '102',
        'cst'             => null,
        'aliquota_icms'   => 0,
        'aliquota_pis'    => 0,
        'aliquota_cofins' => 0,
        'aliquota_ipi'    => 0,
        'mva'             => null,
        'fcp'             => null,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);
});

it('NCM rules NFe — POST + PUT persistem TODOS os campos do fixture (round-trip via Eloquent read-back)', function () {
    $fixture = require __DIR__ . '/../../Contract/Fixtures/ncm_rules.php';

    $passed = 0;
    $total = 0;
    $failures = [];

    foreach ($fixture as $tabName => $tabSpec) {
        $method = strtolower($tabSpec['method'] ?? 'post');
        $expectStatus = $tabSpec['expectStatus'] ?? 302;
        $baseFields = $tabSpec['baseFields'] ?? [];

        // Resolve endpoint — substitui {id} pela regra base (so usado em PUT).
        $endpoint = str_replace('{id}', (string) $this->regraId, $tabSpec['endpoint']);

        // POST cria nova row 1 vez antes de iterar fields (validator filtra
        // chaves desconhecidas — read-back de campo por campo expoe drop silencioso).
        // PUT atualiza row base — 1 PUT por iteracao com baseFields + 1 campo.
        $createdRegraId = null;
        if ($method === 'post') {
            // 1 POST com baseFields completo — todos campos enviados juntos.
            $response = $this->post($endpoint, $baseFields);
            $status = $response->status();
            $statusOk = $status === $expectStatus || in_array($status, [200, 302], true);

            if (! $statusOk) {
                // Falha de POST e fatal pra este tab — registra falha unica e segue.
                $failures[] = [
                    'tab' => $tabName, 'endpoint' => $endpoint, 'method' => $method,
                    'send' => '(POST payload completo)', 'value_sent' => json_encode($baseFields),
                    'recv' => '(N/A — POST falhou)', 'value_received' => null,
                    'status' => $status, 'match_mode' => 'status_check',
                ];
                $total += count($tabSpec['fields']);
                continue;
            }

            // Acha row criada pelo POST — query por business_id + NCM (combinacao
            // dos baseFields que torna unique nesta sessao test).
            $row = NfeFiscalRule::where('business_id', $this->business->id)
                ->where('ncm', $baseFields['ncm'])
                ->where('uf_origem', $baseFields['uf_origem'])
                ->where('uf_destino', $baseFields['uf_destino'])
                ->orderByDesc('id')
                ->first();

            if (! $row) {
                $failures[] = [
                    'tab' => $tabName, 'endpoint' => $endpoint, 'method' => $method,
                    'send' => '(read-back nao achou row criada)', 'value_sent' => json_encode($baseFields),
                    'recv' => '(N/A)', 'value_received' => null,
                    'status' => $status, 'match_mode' => 'row_not_found',
                ];
                $total += count($tabSpec['fields']);
                continue;
            }

            $createdRegraId = $row->id;
        }

        // Itera campos validando read-back.
        foreach ($tabSpec['fields'] as $field) {
            $total++;
            $sendKey = $field['send'];
            $recvKey = $field['recv'];
            $match = $field['match'] ?? 'equals';
            $sent = $field['value'];
            $column = $field['column'] ?? $recvKey;

            $statusForFailure = 200;
            $statusOk = true;

            if ($method === 'put') {
                // PUT envia baseFields + 1 campo alterado — pattern service_order_edit.
                $payload = array_merge($baseFields, [$sendKey => $sent]);
                $response = $this->put($endpoint, $payload);
                $statusForFailure = $response->status();
                $statusOk = $statusForFailure === $expectStatus
                    || in_array($statusForFailure, [200, 302], true);
            }

            // Read-back via Eloquent (casts float aplicados).
            $rowId = $method === 'post' ? $createdRegraId : $this->regraId;
            $regra = NfeFiscalRule::where('business_id', $this->business->id)
                ->where('id', $rowId)
                ->first();
            $received = $regra ? data_get($regra->toArray(), $column) : null;

            $valueOk = matchesNcmRuleValue($sent, $received, $match);

            if (! $statusOk || ! $valueOk) {
                $failures[] = [
                    'tab' => $tabName, 'endpoint' => $endpoint, 'method' => $method,
                    'send' => $sendKey, 'value_sent' => is_scalar($sent) ? (string) $sent : json_encode($sent),
                    'recv' => $recvKey, 'value_received' => is_scalar($received) ? (string) $received : json_encode($received),
                    'status' => $statusForFailure, 'match_mode' => $match,
                ];
            } else {
                $passed++;
            }
        }
    }

    if ($passed !== $total) {
        $msg = "Contract test FALHOU — {$passed}/{$total} OK.\n\nBugs silenciosos detectados (POST/PUT aceito mas valor nao bate em DB read-back):\n";
        foreach ($failures as $f) {
            $msg .= sprintf(
                "  [%s] %s %s — send=%s value_sent=%s recv=%s value_received=%s status=%d match=%s\n",
                $f['tab'], strtoupper($f['method']), $f['endpoint'], $f['send'], var_export($f['value_sent'], true),
                $f['recv'], var_export($f['value_received'], true), $f['status'], $f['match_mode']
            );
        }
        $msg .= "\nADR 0205 — todo PR que regrida contract test bloqueia merge.\n";
        $msg .= "Fix: alinhe UpsertRegraTributariaRequest + TributacaoController + schema nfe_fiscal_rules.\n";
        expect($failures)->toBeEmpty($msg);
    }

    expect($passed)->toBe($total);
});

/**
 * Match modes — estende AutosaveContractRunner::matches com modo 'float'
 * (tolerancia 1e-6 pra DECIMAL(7,4) roundtrip via Eloquent cast float).
 * Espelha matchesNfeConfigValue() (NfeConfigAutosaveContractTest).
 */
function matchesNcmRuleValue(mixed $sent, mixed $received, string $match): bool
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
