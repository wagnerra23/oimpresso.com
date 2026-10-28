<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PaymentGateway\Services\Tef\TefService;

/**
 * Endpoints JSON do caixa para a maquininha (US-SELL-075).
 *
 * Flag desligada para a empresa → 404 (a rota não existe para ela).
 * Valor chega em reais com no máximo 2 casas e vira centavos inteiros aqui
 * (regra mestre de valor: sem float ambíguo).
 */
class TefController extends Controller
{
    public function __construct(private readonly TefService $tef)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $businessId = $this->autorizar($request);

        $dados = $request->validate([
            'valor'           => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999.99'],
            'modalidade'      => ['required', 'in:credito,debito,pix'],
            'parcelas'        => ['nullable', 'integer', 'min:1', 'max:24'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'location_id'     => ['nullable', 'integer'],
            'terminal_id'     => ['nullable', 'string', 'max:80'],
        ]);

        $tef = $this->tef->iniciar($businessId, (int) $request->user()->id, [
            'valor_centavos'  => self::centavos((string) $dados['valor']),
            'modalidade'      => $dados['modalidade'],
            'parcelas'        => $dados['modalidade'] === 'credito' ? (int) ($dados['parcelas'] ?? 1) : 1,
            'idempotency_key' => $dados['idempotency_key'],
            'location_id'     => $dados['location_id'] ?? null,
            'terminal_id'     => $dados['terminal_id'] ?? null,
        ]);

        return response()->json($tef->paraCaixa(), $tef->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $businessId = $this->autorizar($request);

        $tef = $this->tef->consultar($businessId, $id);
        abort_if($tef === null, 404);

        return response()->json($tef->paraCaixa());
    }

    /** "12.5" → 1250 · "204.99" → 20499. Sem float: a validação já garantiu ≤2 casas. */
    public static function centavos(string $valor): int
    {
        [$inteiro, $fracao] = array_pad(explode('.', $valor, 2), 2, '0');

        return (int) $inteiro * 100 + (int) str_pad(substr($fracao, 0, 2), 2, '0');
    }

    private function autorizar(Request $request): int
    {
        $businessId = (int) $request->session()->get('user.business_id');
        abort_unless(TefService::habilitado($businessId), 404);

        $user = $request->user();
        abort_unless($user->can('sell.create') || $user->can('direct_sell.access'), 403);

        return $businessId;
    }
}
