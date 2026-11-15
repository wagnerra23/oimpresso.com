<?php

declare(strict_types=1);

// @covers-us US-VEST-020

use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\Vestuario\Http\Controllers\EtiquetaTagController;
use Modules\Vestuario\Services\EtiquetaTagService;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

/**
 * Teto do lote e permissão real de /vestuario/etiquetas (US-VEST-020).
 *
 *   UC-VET-22 ← CU-VEST-02 item 6  (teto total de etiquetas por pedido)
 *   UC-VET-23 ← CU-VEST-08 item 4  (permissão atrás de chave, decisão [W] VEST-D1)
 *
 * Chama o controller com usuário fictício: a lane sqlite não sobe request autenticada e estes
 * casos não dependem de tabela. Nenhuma empresa real entra aqui.
 */

function ucVetUsuario(array $permissoes)
{
    return new class($permissoes) {
        public function __construct(private array $permissoes) {}

        public function can(string $perm): bool
        {
            return in_array($perm, $this->permissoes, true);
        }

        public function getAuthIdentifier(): int
        {
            return 98;
        }
    };
}

function ucVetRequest(array $body, array $permissoes): Request
{
    $user = ucVetUsuario($permissoes);
    $request = Request::create('/vestuario/etiquetas/lote/zpl', 'POST', $body);
    $request->setUserResolver(fn () => $user);

    return $request;
}

function ucVetController(): EtiquetaTagController
{
    return new EtiquetaTagController(new EtiquetaTagService());
}

function ucVetLote(int $itens, int $copias): array
{
    return [
        'copies' => $copias,
        'items'  => array_fill(0, $itens, ['product_id' => 1, 'nome' => 'Peça', 'sku' => 'SKU-1']),
    ];
}

/** Registra o que foi logado durante o caso. */
function ucVetCapturarLog(): ArrayObject
{
    $logs = new ArrayObject();
    Event::listen(MessageLogged::class, function (MessageLogged $e) use ($logs) {
        $logs->append(['msg' => $e->message, 'ctx' => $e->context]);
    });

    return $logs;
}

const UC_VET_CRIAR = ['vestuario.etiqueta.create'];

// ─────────────────────────────────────────────────────────────────────────────
// UC-VET-22 · Teto total de etiquetas por pedido
// ─────────────────────────────────────────────────────────────────────────────

it('UC-VET-22: pedido acima do teto é recusado com a conta na mensagem', function () {
    $res = null;
    try {
        $res = ucVetController()->storeZpl(ucVetRequest(ucVetLote(2, 251), UC_VET_CRIAR));
    } catch (ValidationException $e) {
        $msg = $e->errors()['items'][0];
        expect($msg)->toContain('502')->toContain('500');

        return;
    }
    $this->fail('Lote de 502 etiquetas passou: status '.$res->getStatusCode());
});

it('UC-VET-22: o teto vale no PDF antes de renderizar', function () {
    ucVetController()->storePdf(ucVetRequest(ucVetLote(500, 2), UC_VET_CRIAR));
})->throws(ValidationException::class);

it('UC-VET-22: exatamente no teto ainda gera, e 500 itens de uma remarcação cabem', function () {
    $res = ucVetController()->storeZpl(ucVetRequest(ucVetLote(500, 1), UC_VET_CRIAR));

    expect($res->getStatusCode())->toBe(200);
    expect(substr_count($res->getContent(), '^XA'))->toBe(500);
});

it('UC-VET-22: o lote deixa uma linha de log, não uma por etiqueta', function () {
    $logs = ucVetCapturarLog();

    ucVetController()->storeZpl(ucVetRequest(ucVetLote(3, 2), UC_VET_CRIAR));

    $mensagens = array_column($logs->getArrayCopy(), 'msg');
    expect($mensagens)->not->toContain('vestuario.etiqueta.gerada');
    expect(array_count_values($mensagens)['vestuario.etiqueta.lote.zpl'] ?? 0)->toBe(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-VET-23 · Permissão atrás de chave: desligada só registra, ligada bloqueia
// ─────────────────────────────────────────────────────────────────────────────

it('UC-VET-23: chave desligada deixa passar e registra quem seria bloqueado', function () {
    config(['vestuario.etiqueta.exigir_permissao' => false]);
    $logs = ucVetCapturarLog();

    $res = ucVetController()->storeZpl(ucVetRequest(ucVetLote(1, 1), []));

    expect($res->getStatusCode())->toBe(200);

    $registro = collect($logs->getArrayCopy())->firstWhere('msg', 'vestuario.etiqueta.seria_bloqueado');
    expect($registro)->not->toBeNull();
    expect($registro['ctx']['user_id'])->toBe(98);
    expect($registro['ctx']['permissao'])->toBe('vestuario.etiqueta.create');
    expect($registro['ctx']['bloqueou'])->toBeFalse();
});

it('UC-VET-23: a chave vem desligada por padrão', function () {
    expect(config('vestuario.etiqueta.exigir_permissao'))->toBeFalse();
});

it('UC-VET-23: chave ligada dá 403 em quem não tem a permissão, nas três rotas', function () {
    config(['vestuario.etiqueta.exigir_permissao' => true]);
    $controller = ucVetController();
    $sem = ucVetRequest(ucVetLote(1, 1), []);

    foreach ([
        fn () => $controller->storeZpl($sem),
        fn () => $controller->storePdf($sem),
        fn () => $controller->index($sem),
    ] as $rota) {
        try {
            $rota();
            $this->fail('Passou sem a permissão.');
        } catch (HttpException $e) {
            expect($e->getStatusCode())->toBe(403);
        }
    }
});

it('UC-VET-23: chave ligada não atrapalha quem tem a permissão nem não registra bloqueio', function () {
    config(['vestuario.etiqueta.exigir_permissao' => true]);
    $logs = ucVetCapturarLog();

    $res = ucVetController()->storeZpl(ucVetRequest(ucVetLote(1, 1), UC_VET_CRIAR));

    expect($res->getStatusCode())->toBe(200);
    expect(array_column($logs->getArrayCopy(), 'msg'))->not->toContain('vestuario.etiqueta.seria_bloqueado');
});
