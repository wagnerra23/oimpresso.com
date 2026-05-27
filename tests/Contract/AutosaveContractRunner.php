<?php

declare(strict_types=1);

namespace Tests\Contract;

use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;

/**
 * Runner generico pra contract tests de endpoints autosave (PATCH /resource/{id}/tab).
 *
 * Wagner 2026-05-27 origem: bateria exaustiva drawer Cliente descobriu 3 bugs
 * silenciosos (Razao social/CNPJ/Tel principal nao salvavam — aliases PT-BR
 * vs canon EN nao mapeados). Esses bugs PASSARIAM SEM SER DETECTADOS por
 * tests unitarios convencionais — PATCH retornava 200 + badge "Salvo" verde
 * mas Eloquent::update([]) jogava chave fora silenciosamente.
 *
 * Padrao canonico (ADR 0205): toda tela com autosave PATCH endpoints DEVE
 * ter fixture contract test associada. CI roda automaticamente a cada PR
 * que toque Controllers ou Pages cobertos por fixtures.
 *
 * USO (em test file Pest):
 *
 *   it('cliente drawer persists all autosave fields', function () {
 *       $fixture = require __DIR__ . '/../../Contract/Fixtures/cliente_drawer.php';
 *       AutosaveContractRunner::run($this, $fixture, $this->contactId);
 *   });
 *
 * FIXTURE FORMAT (array PHP):
 *
 *   return [
 *     'identificacao' => [  // chave = label do tab/seccao (so pra debug)
 *       'endpoint' => '/cliente/{id}/identificacao',  // {id} substituido
 *       'fields' => [
 *         [
 *           'send' => 'fantasia',         // chave que frontend envia (pode ser PT-BR alias)
 *           'value' => 'CT-{stamp}',      // valor teste ({stamp} substituido por timestamp)
 *           'recv' => 'fantasia',         // chave que backend retorna no shapeContactResponse
 *           'match' => 'equals',          // 'equals' (default) | 'partial' | 'bool' | 'int'
 *         ],
 *         [
 *           'send' => 'nome',             // alias PT-BR
 *           'value' => 'CT-{stamp}',
 *           'recv' => 'name',             // backend retorna canon EN
 *         ],
 *         // ...
 *       ],
 *     ],
 *     'contato' => [...],
 *     // ...
 *   ];
 *
 * MATCH MODES:
 *   - 'equals' (default): toEqual($sent, $received)
 *   - 'partial': str_contains($received, $sent) - util pra mask CPF/CNPJ
 *   - 'bool': cast bool comparison
 *   - 'int': cast int comparison
 *   - 'array_eq': JSON serialize both, compare
 */
class AutosaveContractRunner
{
    /**
     * Roda todos campos do fixture contra endpoint(s).
     *
     * @param  object  $testCase  $this do Pest (pra acessar $testCase->patchJson etc)
     * @param  array<string, array{endpoint: string, fields: array<int, array{send: string, value: mixed, recv: string, match?: string}>}>  $fixture
     * @param  int  $resourceId  ID do recurso (contact_id, etc) substituindo {id} no endpoint
     * @return array{passed: int, total: int, failures: array<int, array>}
     */
    public static function run(object $testCase, array $fixture, int $resourceId): array
    {
        $stamp = 'CT' . substr((string) microtime(true), -4);
        $passed = 0;
        $total = 0;
        $failures = [];

        foreach ($fixture as $tabName => $tabSpec) {
            $endpoint = str_replace('{id}', (string) $resourceId, $tabSpec['endpoint']);

            foreach ($tabSpec['fields'] as $field) {
                $total++;
                $sendKey = $field['send'];
                $recvKey = $field['recv'];
                $match = $field['match'] ?? 'equals';

                // Substitui {stamp} no valor pra tornar unico por run.
                $sent = is_string($field['value'])
                    ? str_replace('{stamp}', $stamp, $field['value'])
                    : $field['value'];

                /** @var TestResponse $response */
                $response = $testCase->patchJson($endpoint, [$sendKey => $sent]);

                $status = $response->status();
                $received = data_get($response->json(), "contact.{$recvKey}");

                $ok = self::matches($sent, $received, $match);
                if (! $ok || $status !== 200) {
                    $failures[] = [
                        'tab' => $tabName,
                        'endpoint' => $endpoint,
                        'send' => $sendKey,
                        'value_sent' => is_string($sent) ? substr($sent, 0, 50) : $sent,
                        'recv' => $recvKey,
                        'value_received' => is_string($received) ? substr((string) $received, 0, 50) : $received,
                        'status' => $status,
                        'match_mode' => $match,
                    ];
                } else {
                    $passed++;
                }
            }
        }

        return ['passed' => $passed, 'total' => $total, 'failures' => $failures];
    }

    /**
     * Verifica se backend retornou o valor esperado conforme match mode.
     */
    private static function matches(mixed $sent, mixed $received, string $match): bool
    {
        return match ($match) {
            'partial' => $received !== null && is_string($received) && str_contains((string) $received, is_string($sent) ? $sent : (string) $sent),
            'bool' => (bool) $received === (bool) $sent,
            'int' => (int) $received === (int) $sent,
            'array_eq' => json_encode($received) === json_encode($sent),
            default => $received === $sent || (string) $received === (string) $sent,
        };
    }

    /**
     * Helper pra setup multi-tenant Tier 0 (ADR 0093) padrao.
     * Cria contact base num business existente + autentica user + popula session.
     *
     * @return array{business: \App\Business, user: \App\User, contactId: int}
     */
    public static function setupContext(object $testCase): array
    {
        if (! Schema::hasTable('contacts')) {
            $testCase->markTestSkipped('Schema UltimatePOS ausente — rode com DB_CONNECTION=mysql.');
        }
        if (! Schema::hasColumn('contacts', 'tipo')) {
            $testCase->markTestSkipped('Migration drawer Wave B nao rodou.');
        }

        $business = \App\Business::first();
        if (! $business) {
            $testCase->markTestSkipped('Sem business em DB.');
        }
        $user = \App\User::where('business_id', $business->id)->first();
        if (! $user) {
            $testCase->markTestSkipped('Sem user no business.');
        }

        $now = now();
        $contactId = \Illuminate\Support\Facades\DB::table('contacts')->insertGetId([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'type' => 'customer',
            'tipo' => 'PJ',
            'name' => 'CT Setup Test ' . substr((string) microtime(true), -4),
            'mobile' => '11999999999',
            'contact_status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $testCase->actingAs($user);
        session(['user.business_id' => $business->id]);

        return ['business' => $business, 'user' => $user, 'contactId' => $contactId];
    }
}
