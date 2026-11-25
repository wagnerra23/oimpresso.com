<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Http\Controllers\NfeLoteController;
use Modules\NfeBrasil\Services\DanfeService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * UC-FCKP-22..24 (Cockpit.casos.md) — a barra de lote do Painel Fiscal.
 *
 * Os três botões apareciam ao marcar notas e não faziam nada (débito de 2026-11-19). Aqui se
 * prova o que cada um entrega e, em todos, que nota de outra empresa NUNCA entra no lote: o id
 * vem do navegador, então um id alheio tem de sair contado como "fora", não como arquivo.
 * Tenant 98 (ADR 0358) contra o cliente fictício 99. DanfeService é mockado: o que se testa é o
 * corte do lote, não a geração da DANFE (essa tem teste próprio em DanfeServiceTest).
 */
beforeEach(function () {
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('nfe_emissoes ausente neste schema.');
    }
    $this->a = (int) $this->seededTenant()->id;
    $this->b = (int) $this->seededSupportClientTenant()->id;
});

function loteEmissao(int $biz, int $numero, string $status = 'autorizada'): int
{
    return (int) DB::table('nfe_emissoes')->insertGetId([
        'business_id' => $biz, 'modelo' => '55', 'serie' => '977', 'numero' => $numero,
        'chave_44' => str_pad((string) $numero, 44, '9', STR_PAD_LEFT), 'status' => $status,
        'valor_total' => 10.00, 'emitido_em' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function loteRequest(int $biz, array $ids): Request
{
    $req = Request::create('/nfe-brasil/emissoes/lote/xml', 'POST', ['ids' => $ids]);
    $req->setLaravelSession(app('session.store'));
    $req->session()->put('business.id', $biz);

    return $req;
}

it('UC-FCKP-22 · o ZIP leva o XML de cada nota autorizada da empresa e deixa fora a de outra empresa e a rejeitada', function () {
    $minha = loteEmissao($this->a, 9_770_001);
    $rejeitada = loteEmissao($this->a, 9_770_002, 'rejeitada');
    $alheia = loteEmissao($this->b, 9_770_003);

    $danfe = Mockery::mock(DanfeService::class);
    $danfe->shouldReceive('obterXmlContents')->once()
        ->withArgs(fn ($e) => (int) $e->id === $minha)
        ->andReturn('<nfeProc>minha</nfeProc>');

    $res = (new NfeLoteController($danfe))->xml(loteRequest($this->a, [$minha, $rejeitada, $alheia]));

    expect($res->getStatusCode())->toBe(200);
    expect($res->headers->get('X-Lote-Fora'))->toBe('2');

    $zip = new ZipArchive();
    expect($zip->open($res->getFile()->getPathname()))->toBeTrue();
    expect($zip->numFiles)->toBe(1);
    expect($zip->getFromIndex(0))->toBe('<nfeProc>minha</nfeProc>');
    $zip->close();
});

it('UC-FCKP-22 · lote só com nota de outra empresa não gera arquivo nenhum', function () {
    $alheia = loteEmissao($this->b, 9_770_004);
    $danfe = Mockery::mock(DanfeService::class);
    $danfe->shouldNotReceive('obterXmlContents');

    $res = (new NfeLoteController($danfe))->xml(loteRequest($this->a, [$alheia]));

    expect($res->getStatusCode())->toBe(422);
    expect(json_decode($res->getContent(), true)['fora'])->toBe(1);
});

it('UC-FCKP-23 · as DANFEs saem num PDF só, sem a nota de outra empresa', function () {
    $minha = loteEmissao($this->a, 9_770_005);
    $alheia = loteEmissao($this->b, 9_770_006);

    $um = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir()]);
    $um->WriteHTML('<p>DANFE de teste</p>');
    $pdf = $um->Output('', \Mpdf\Output\Destination::STRING_RETURN);

    $danfe = Mockery::mock(DanfeService::class);
    $danfe->shouldReceive('lerOuGerar')->once()
        ->withArgs(fn ($e) => (int) $e->id === $minha)
        ->andReturn($pdf);

    $res = (new NfeLoteController($danfe))->danfe(loteRequest($this->a, [$minha, $alheia]));

    expect($res->getStatusCode())->toBe(200);
    expect($res->headers->get('X-Lote-Fora'))->toBe('1');
    expect(substr((string) file_get_contents($res->getFile()->getPathname()), 0, 5))->toBe('%PDF-');
});

it('UC-FCKP-24 · o reenvio conta as notas da empresa e não dispara e-mail de nota alheia', function () {
    $alheia = loteEmissao($this->b, 9_770_007);
    $rejeitada = loteEmissao($this->a, 9_770_008, 'rejeitada');

    $res = (new NfeLoteController(Mockery::mock(DanfeService::class)))
        ->reenviarEmail(loteRequest($this->a, [$alheia, $rejeitada]));

    expect($res->getData(true))->toBe(['enviadas' => 0, 'falhas' => 0, 'fora' => 2]);
});

it('UC-FCKP-22 · lote acima de 200 notas é recusado antes de ler o banco', function () {
    $danfe = Mockery::mock(DanfeService::class);
    $danfe->shouldNotReceive('obterXmlContents');

    expect(fn () => (new NfeLoteController($danfe))->xml(loteRequest($this->a, range(1, NfeLoteController::MAX_IDS + 1))))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});
