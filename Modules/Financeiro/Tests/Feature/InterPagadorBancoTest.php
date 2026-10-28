<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\PagamentoBanco;
use Modules\Financeiro\Services\Banking\InterPagadorBanco;
use Modules\RecurringBilling\Contracts\BoletoCredentialResolverInterface;
use Modules\RecurringBilling\Models\BoletoCredential;

uses(Tests\TestCase::class);

/**
 * US-FIN-079 — adaptador Inter do pagamento pelo banco. HTTP simulado (Http::fake), nada sai.
 * Contrato: resources/js/Pages/Financeiro/Unificado/Index.casos.md (UC-PAGBAN-09..10).
 *
 * @covers-us US-FIN-079
 */
beforeEach(function () {
    Cache::flush();
    $resolver = Mockery::mock(BoletoCredentialResolverInterface::class);
    $resolver->shouldReceive('decryptConfig')->andReturn([
        'client_id' => 'id', 'client_secret' => 'segredo', 'conta_corrente' => '123',
        'certificado_crt_b64' => base64_encode('crt-teste'), 'certificado_key_b64' => base64_encode('key-teste'),
    ]);
    $this->pagador = new InterPagadorBanco($resolver);

    $this->conta = new ContaBancaria(['business_id' => 98]);
    $this->conta->setRelation('gatewayCredential', new BoletoCredential(['banco' => 'inter', 'ativo' => true, 'config_json' => []]));
});

function pagbanInterPedido(string $meio, ?string $codigo = null): PagamentoBanco
{
    return new PagamentoBanco([
        'business_id' => 98, 'meio' => $meio, 'valor' => '1234.5600',
        'destino' => $meio === 'boleto' ? '03399123456789012345678901234567890120000123456' : 'chave@exemplo.test',
        'idempotency_key' => '11111111-2222-3333-4444-555555555555', 'codigo_transacao' => $codigo,
    ]);
}

it('UC-PAGBAN-09 · o envio nunca devolve pago, mesmo com o banco dizendo realizado', function () {
    Http::fake([
        '*/oauth/v2/token' => Http::response(['access_token' => 'tok']),
        '*/banking/v2/pagamento' => Http::response(['codigoTransacao' => 'TX-9', 'statusPagamento' => 'REALIZADO']),
    ]);

    $r = $this->pagador->enviar(pagbanInterPedido('boleto'), $this->conta);

    expect($r['status'])->toBe('aguardando_banco');
    expect($r['codigo_transacao'])->toBe('TX-9');
    Http::assertSent(fn ($req) => str_contains($req->url(), '/banking/v2/pagamento')
        && $req->header('x-id-idempotente')[0] === '11111111-2222-3333-4444-555555555555'
        && (float) $req['valorPagar'] === 1234.56);
});

it('UC-PAGBAN-10 · na consulta, só status conhecido de débito vira pago; desconhecido segue aguardando', function () {
    Http::fake([
        '*/oauth/v2/token' => Http::response(['access_token' => 'tok']),
        '*/banking/v2/pix/PX-1' => Http::sequence()
            ->push(['codigoSolicitacao' => 'PX-1', 'status' => 'EM_PROCESSAMENTO'])
            ->push(['codigoSolicitacao' => 'PX-1', 'status' => 'PAGO'])
            ->push(['codigoSolicitacao' => 'PX-1', 'status' => 'REJEITADO']),
    ]);
    $p = pagbanInterPedido('pix', 'PX-1');

    expect($this->pagador->consultar($p, $this->conta)['status'])->toBe('aguardando_banco');
    expect($this->pagador->consultar($p, $this->conta)['status'])->toBe('pago');
    expect($this->pagador->consultar($p, $this->conta)['status'])->toBe('recusado');
});

it('UC-PAGBAN-10 · conta sem credencial Inter ativa não chama o banco', function () {
    Http::fake();
    $this->conta->setRelation('gatewayCredential', new BoletoCredential(['banco' => 'inter', 'ativo' => false]));

    expect(fn () => $this->pagador->enviar(pagbanInterPedido('pix'), $this->conta))->toThrow(DomainException::class);
    Http::assertNothingSent();
});
