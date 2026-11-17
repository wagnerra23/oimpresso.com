<?php

declare(strict_types=1);

// @covers-us US-FISCAL-004 — as ações de escrita sobre nfe_emissoes ficam na empresa da sessão.

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Fiscal\Http\Controllers\AcoesController;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Models\NfeEvento;
use Modules\NfeBrasil\Models\NfeInutilizacao;
use Modules\NfeBrasil\Services\NfeCartaCorrecaoService;
use Modules\NfeBrasil\Services\NfeInutilizacaoService;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * UC-FNFE-63 · UC-FNFE-64 — cancelar, CC-e, retransmitir e inutilizar, com duas empresas.
 *
 * Invoca o AcoesController de verdade (mesmo desenho do AcoesDevolucaoTest). Os serviços
 * que falam com a SEFAZ são trocados por falsos que só anotam o que receberam — o que se
 * prova aqui é o que o controller manda para eles e o que ele recusa antes.
 *
 * Tenant 98 é a empresa da sessão; o 99 é a vizinha (ADR 0358). Cada recusa tem o seu
 * controle: a mesma ação, na nota da própria empresa, chega ao serviço.
 * Números 9_391_xxx e série 938 reservados para este arquivo.
 */

function escUsuario(): void
{
    Auth::setUser(new class extends \App\User
    {
        public function can($abilities, $arguments = []): bool
        {
            return true;
        }
    });
}

function escNfeServico(ArrayObject $log): NfeService
{
    return new class($log) extends NfeService
    {
        public function __construct(private ArrayObject $log) {}

        public function cancelar(int $businessId, int $nfeEmissaoId, string $justificativa): NfeEvento
        {
            $this->log[] = ['cancelar', $businessId, $nfeEmissaoId];
            $e = new NfeEvento();
            $e->forceFill(['id' => 1, 'cstat_evento' => '135']);

            return $e;
        }

        public function retransmitir(int $businessId, int $nfeEmissaoId): NfeEmissao
        {
            $this->log[] = ['retransmitir', $businessId, $nfeEmissaoId];
            $n = new NfeEmissao();
            $n->forceFill(['id' => 1, 'numero' => 1, 'status' => 'autorizada', 'cstat' => '100']);

            return $n;
        }
    };
}

function escCceServico(ArrayObject $log): NfeCartaCorrecaoService
{
    return new class($log) extends NfeCartaCorrecaoService
    {
        public function __construct(private ArrayObject $log) {}

        public function aplicar(int $businessId, int $nfeEmissaoId, string $textoCorrecao, int $nSeqEvento): NfeEvento
        {
            $this->log[] = ['cce', $businessId, $nfeEmissaoId];
            $e = new NfeEvento();
            $e->forceFill(['id' => 1, 'cstat_evento' => '135']);

            return $e;
        }
    };
}

function escInutServico(ArrayObject $log): NfeInutilizacaoService
{
    return new class($log) extends NfeInutilizacaoService
    {
        public function __construct(private ArrayObject $log) {}

        public function inutilizar(int $businessId, string $modelo, string $serie, int $numeroDe, int $numeroAte, string $justificativa): NfeInutilizacao
        {
            $this->log[] = ['inutilizar', $businessId, $serie, $numeroDe, $numeroAte];
            $i = new NfeInutilizacao();
            $i->forceFill(['id' => 1, 'cstat' => '102', 'status' => 'autorizado']);

            return $i;
        }
    };
}

function escNota(int $bizId, int $numero, string $status): int
{
    return (int) DB::table('nfe_emissoes')->insertGetId([
        'business_id' => $bizId, 'modelo' => '55', 'serie' => '938', 'numero' => $numero,
        'status' => $status, 'valor_total' => 10.00, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function escLimpar(): void
{
    DB::table('nfe_emissoes')->whereBetween('numero', [9_391_000, 9_391_999])->where('serie', '938')->delete();
}

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('precisa do schema MySQL de nfe_emissoes');
    }
    $this->bizA = (int) $this->seededTenant()->id;
    $this->bizB = (int) $this->seededSupportClientTenant()->id;
    escLimpar();
    escUsuario();
    session(['user.business_id' => $this->bizA]);
});

afterEach(function () {
    if (DB::getDriverName() === 'mysql' && Schema::hasTable('nfe_emissoes')) {
        escLimpar();
    }
});

it('UC-FNFE-63 · cancelar: a nota de outra empresa não é achada; a da empresa vai ao serviço', function () {
    $idA = escNota($this->bizA, 9_391_001, 'autorizada');
    $idB = escNota($this->bizB, 9_391_002, 'autorizada');
    $log = new ArrayObject();
    $c = new AcoesController();
    $corpo = ['motivo' => 'Erro de digitação no destinatário da nota'];

    expect(fn () => $c->cancelarNfe(Request::create('/x', 'POST', $corpo), escNfeServico($log), $idB))
        ->toThrow(ModelNotFoundException::class); // o handler responde 404
    expect(count($log))->toBe(0);
    expect(DB::table('nfe_emissoes')->where('id', $idB)->value('status'))->toBe('autorizada');

    $c->cancelarNfe(Request::create('/x', 'POST', $corpo), escNfeServico($log), $idA);
    expect($log->getArrayCopy())->toBe([['cancelar', $this->bizA, $idA]]);
});

it('UC-FNFE-63 · CC-e: a nota de outra empresa não é achada; a da empresa vai ao serviço', function () {
    $idA = escNota($this->bizA, 9_391_003, 'autorizada');
    $idB = escNota($this->bizB, 9_391_004, 'autorizada');
    $log = new ArrayObject();
    $c = new AcoesController();
    $corpo = ['texto_correcao' => 'Corrige o endereço de entrega do destinatário', 'n_seq_evento' => 1];

    expect(fn () => $c->cartaCorrecao(Request::create('/x', 'POST', $corpo), escCceServico($log), $idB))
        ->toThrow(ModelNotFoundException::class);
    expect(count($log))->toBe(0);

    $c->cartaCorrecao(Request::create('/x', 'POST', $corpo), escCceServico($log), $idA);
    expect($log->getArrayCopy())->toBe([['cce', $this->bizA, $idA]]);
});

it('UC-FNFE-63 · retransmitir: a nota de outra empresa não é achada; a da empresa vai ao serviço', function () {
    $idA = escNota($this->bizA, 9_391_005, 'rejeitada');
    $idB = escNota($this->bizB, 9_391_006, 'rejeitada');
    $log = new ArrayObject();
    $c = new AcoesController();

    expect(fn () => $c->retransmitir(Request::create('/x', 'POST'), escNfeServico($log), $idB))
        ->toThrow(ModelNotFoundException::class);
    expect(count($log))->toBe(0);
    expect(DB::table('nfe_emissoes')->where('id', $idB)->value('status'))->toBe('rejeitada');

    $c->retransmitir(Request::create('/x', 'POST'), escNfeServico($log), $idA);
    expect($log->getArrayCopy())->toBe([['retransmitir', $this->bizA, $idA]]);
});

it('UC-FNFE-64 · inutilizar usa a empresa da sessão, mesmo que o corpo traga outra', function () {
    $log = new ArrayObject();
    $c = new AcoesController();

    $pedido = [
        'modelo' => '55', 'serie' => '938', 'numero_de' => 9_391_010, 'numero_ate' => 9_391_012,
        'justificativa' => 'Números pulados por falha de comunicação',
    ];

    $c->inutilizar(Request::create('/x', 'POST', $pedido + ['business_id' => $this->bizB]), escInutServico($log));
    expect($log->getArrayCopy())->toBe([['inutilizar', $this->bizA, '938', 9_391_010, 9_391_012]]);

    // Controle: com a sessão na outra empresa, o mesmo pedido vai para ela.
    session(['user.business_id' => $this->bizB]);
    $c->inutilizar(Request::create('/x', 'POST', $pedido), escInutServico($log));
    expect($log[1][1])->toBe($this->bizB);
});
