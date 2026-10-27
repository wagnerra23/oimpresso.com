<?php

declare(strict_types=1);

// @covers-us US-NFE-064 — a NF-e de devolução ganha rota (R-NFE-039).

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Fiscal\Http\Controllers\AcoesController;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\DevolucaoService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

uses(Tests\TestCase::class);

/**
 * R-NFE-039 · POST /fiscal/acoes/nfe/{emissao}/devolucao — invoca o AcoesController de verdade.
 *
 * A regra do valor (base e alíquota da nota de origem) é do DevolucaoService e já tem prova em
 * NfeDevolucaoTest; aqui é só o que a rota acrescenta: permissão, quantidades e empresa da sessão.
 * Os casos de banco usam o tenant 98 e o vizinho 99 (ADR 0358), números 9_390_xxx reservados.
 */

function devUsuario(bool $pode): void
{
    Auth::setUser(new class($pode) extends \App\User
    {
        public function __construct(private bool $pode = true) {}

        public function can($abilities, $arguments = []): bool
        {
            return $this->pode;
        }
    });
}

/** DevolucaoService falso: grava o que recebeu e devolve uma nota autorizada. */
function devServicoFalso(ArrayObject $chamadas): DevolucaoService
{
    return new class($chamadas) extends DevolucaoService
    {
        public function __construct(private ArrayObject $log) {}

        public function emitirDevolucao(int $businessId, int $origemEmissaoId, array $quantidades, array $destOverride = []): NfeEmissao
        {
            $this->log[] = [$businessId, $origemEmissaoId, $quantidades];
            $n = new NfeEmissao();
            $n->forceFill(['id' => 1, 'numero' => 77, 'status' => 'autorizada', 'cstat' => '100']);

            return $n;
        }
    };
}

function devCapturar(callable $fn): ?Throwable
{
    try {
        $fn();

        return null;
    } catch (Throwable $e) {
        return $e;
    }
}

it('UC-FNFE-40 · sem a permissão fiscal.nfe.acoes a devolução é recusada (403)', function () {
    devUsuario(false);

    $e = devCapturar(fn () => (new AcoesController())->devolucao(
        Request::create('/x', 'POST', ['itens' => [1 => 1]]), devServicoFalso(new ArrayObject()), 1,
    ));

    expect($e)->toBeInstanceOf(HttpException::class);
    expect($e->getStatusCode())->toBe(403);
});

it('UC-FNFE-40 · sem item, ou com quantidade zero ou negativa, não passa da validação', function () {
    devUsuario(true);
    $c = new AcoesController();

    foreach ([[], ['itens' => []], ['itens' => [1 => 0]], ['itens' => [1 => -2]], ['itens' => [1 => 'x']]] as $corpo) {
        $e = devCapturar(fn () => $c->devolucao(Request::create('/x', 'POST', $corpo), devServicoFalso(new ArrayObject()), 1));
        expect($e)->toBeInstanceOf(ValidationException::class);
    }

    // Controle: quantidade válida passa da validação (o erro seguinte, se houver, é outro).
    $ok = devCapturar(fn () => $c->devolucao(Request::create('/x', 'POST', ['itens' => [1 => 2.5]]), devServicoFalso(new ArrayObject()), 1));
    expect($ok)->not->toBeInstanceOf(ValidationException::class);
});

it('UC-FNFE-41 · a nota de outra empresa não é achada; a da empresa vai ao serviço com as quantidades', function () {
    if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('precisa do schema MySQL de nfe_emissoes');
    }
    $a = $this->seededTenant()->id;
    $b = $this->seededSupportClientTenant()->id;
    DB::table('nfe_emissoes')->whereBetween('numero', [9_390_000, 9_390_999])->delete();

    try {
        $idA = DB::table('nfe_emissoes')->insertGetId([
            'business_id' => $a, 'modelo' => '55', 'serie' => '939', 'numero' => 9_390_001,
            'status' => 'autorizada', 'valor_total' => 10.00, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $idB = DB::table('nfe_emissoes')->insertGetId([
            'business_id' => $b, 'modelo' => '55', 'serie' => '939', 'numero' => 9_390_002,
            'status' => 'autorizada', 'valor_total' => 10.00, 'created_at' => now(), 'updated_at' => now(),
        ]);
        devUsuario(true);
        session(['user.business_id' => $a]);
        $chamadas = new ArrayObject();
        $c = new AcoesController();

        $e = devCapturar(fn () => $c->devolucao(Request::create('/x', 'POST', ['itens' => [1 => 1]]), devServicoFalso($chamadas), $idB));
        expect($e)->toBeInstanceOf(ModelNotFoundException::class); // o handler responde 404
        expect(count($chamadas))->toBe(0);

        $c->devolucao(Request::create('/x', 'POST', ['itens' => ['1' => '2', '3' => '0.5']]), devServicoFalso($chamadas), $idA);
        expect($chamadas[0])->toBe([$a, $idA, [1 => 2.0, 3 => 0.5]]);
    } finally {
        DB::table('nfe_emissoes')->whereBetween('numero', [9_390_000, 9_390_999])->delete();
    }
});

it('UC-FNFE-41 · a rota está registrada', function () {
    expect(\Illuminate\Support\Facades\Route::has('fiscal.acoes.nfe.devolucao'))->toBeTrue();
});
