<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\RecurringBilling\Dto\StatementLineDto;
use Modules\RecurringBilling\Services\Banking\Drivers\InterStatementDriver;
use Modules\RecurringBilling\Services\Banking\InterBankingClient;

uses(Tests\TestCase::class);

/**
 * US-RB-046 · InterStatementDriver — adapta extrato Inter v2 → StatementLineDto[].
 *
 * Cobertura via `Http::fake()` (Banking API v2 usa Laravel Http nativo).
 */
function interStmtConfig(): array
{
    return [
        'client_id'           => 'cid',
        'client_secret'       => 'csec',
        'certificado_crt_b64' => base64_encode("-----BEGIN CERTIFICATE-----\nfake\n-----END CERTIFICATE-----\n"),
        'certificado_key_b64' => base64_encode("-----BEGIN PRIVATE KEY-----\nfake\n-----END PRIVATE KEY-----\n"),
        'conta_corrente'      => '12345678',
    ];
}

beforeEach(function () {
    Cache::flush();
});

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/inter_crt_*.pem') as $f) {
        @unlink($f);
    }
    foreach (glob(sys_get_temp_dir().'/inter_key_*.pem') as $f) {
        @unlink($f);
    }
});

it('parsa transação PIX recebida (crédito)', function () {
    Http::fake([
        '*/oauth/v2/token' => Http::response(['access_token' => 'tk']),
        '*/banking/v2/extrato/completo*' => Http::response([
            'ultimaPagina' => true,
            'transacoes'   => [[
                'idTransacao'   => 'tx-pix-001',
                'dataInclusao'  => '2026-05-07',
                'dataTransacao' => '2026-05-07',
                'tipoTransacao' => 'PIX',
                'tipoOperacao'  => 'C',
                'valor'         => '150.00',
                'titulo'        => 'PIX RECEBIDO',
                'descricao'     => 'Mensalidade',
                'detalhes'      => [
                    'tipoDetalhe'    => 'PIX',
                    'endToEndId'    => 'E182361202026050709',
                    'nomePagador'   => 'Fulano de Tal',
                    'cpfCnpjPagador' => '12345678900',
                ],
            ]],
        ]),
    ]);

    $driver = new InterStatementDriver(new InterBankingClient(interStmtConfig(), 4));
    $linhas = $driver->fetchStatement(Carbon::parse('2026-05-01'), Carbon::parse('2026-05-07'));

    expect($linhas)->toHaveCount(1)
        ->and($linhas->first())->toBeInstanceOf(StatementLineDto::class);

    $linha = $linhas->first();
    expect($linha->valor)->toBe(150.0)
        ->and($linha->tipo)->toBe('C')
        ->and($linha->descricao)->toContain('PIX RECEBIDO')
        ->and($linha->descricao)->toContain('Mensalidade')
        ->and($linha->contraparteNome)->toBe('Fulano de Tal')
        ->and($linha->contraparteDocumento)->toBe('12345678900')
        ->and($linha->idempotencyKey)->toBe('tx-pix-001');
});

it('parsa débito (boleto pago) e mantém raw_payload pra análise futura', function () {
    Http::fake([
        '*/oauth/v2/token' => Http::response(['access_token' => 'tk']),
        '*/banking/v2/extrato/completo*' => Http::response([
            'ultimaPagina' => true,
            'transacoes'   => [[
                'idTransacao'   => 'tx-bol-002',
                'dataInclusao'  => '2026-05-06',
                'tipoTransacao' => 'BOLETO',
                'tipoOperacao'  => 'D',
                'valor'         => '300.50',
                'titulo'        => 'BOLETO PAGO',
                'descricao'     => 'Fornecedor X',
                'detalhes'      => [
                    'tipoDetalhe' => 'BOLETO_PAGAMENTO',
                    'codigoBarras' => '12345...',
                ],
            ]],
        ]),
    ]);

    $driver = new InterStatementDriver(new InterBankingClient(interStmtConfig(), 4));
    $linhas = $driver->fetchStatement(Carbon::parse('2026-05-01'), Carbon::parse('2026-05-07'));

    $linha = $linhas->first();
    expect($linha->tipo)->toBe('D')
        ->and($linha->valor)->toBe(300.5)
        ->and($linha->raw)->toHaveKey('detalhes.codigoBarras');
});

it('fallback idempotency_key via hash quando idTransacao + endToEndId ausentes', function () {
    Http::fake([
        '*/oauth/v2/token' => Http::response(['access_token' => 'tk']),
        '*/banking/v2/extrato/completo*' => Http::response([
            'ultimaPagina' => true,
            'transacoes'   => [[
                'dataInclusao'  => '2026-05-05',
                'tipoOperacao'  => 'C',
                'valor'         => '50.00',
                'titulo'        => 'TED',
                'descricao'     => 'X',
                'detalhes'      => [],
            ]],
        ]),
    ]);

    $driver = new InterStatementDriver(new InterBankingClient(interStmtConfig(), 4));
    $linha  = $driver->fetchStatement(Carbon::parse('2026-05-01'), Carbon::parse('2026-05-07'))->first();

    expect($linha->idempotencyKey)->toMatch('/^[a-f0-9]{40}$/'); // sha1
});

it('paginação: lê 2 páginas quando ultimaPagina=false na primeira', function () {
    $callCount = 0;
    Http::fake(function (\Illuminate\Http\Client\Request $req) use (&$callCount) {
        if (str_contains($req->url(), '/oauth/v2/token')) {
            return Http::response(['access_token' => 'tk']);
        }
        $callCount++;
        if ($callCount === 1) {
            return Http::response([
                'ultimaPagina' => false,
                'transacoes'   => [['idTransacao' => 'p1-tx1', 'dataInclusao' => '2026-05-05', 'tipoOperacao' => 'C', 'valor' => 10, 'titulo' => 'A']],
            ]);
        }

        return Http::response([
            'ultimaPagina' => true,
            'transacoes'   => [['idTransacao' => 'p2-tx1', 'dataInclusao' => '2026-05-06', 'tipoOperacao' => 'C', 'valor' => 20, 'titulo' => 'B']],
        ]);
    });

    $driver = new InterStatementDriver(new InterBankingClient(interStmtConfig(), 4));
    $linhas = $driver->fetchStatement(Carbon::parse('2026-05-01'), Carbon::parse('2026-05-07'));

    expect($linhas)->toHaveCount(2)
        ->and($linhas->pluck('idempotencyKey')->all())->toBe(['p1-tx1', 'p2-tx1']);
});
