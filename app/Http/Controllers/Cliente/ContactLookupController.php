<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Rules\BR\CpfCnpj;
use App\Services\BR\BrasilApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Endpoint AJAX que faz proxy de lookup CNPJ via BrasilApiService.
 *
 * Rota: GET /contacts/lookup/cnpj/{cnpj}
 *
 * Segurança:
 *   - Sob middleware ['auth', 'verified'] (definido em routes/web.php)
 *   - Permission check: usuário precisa ter create OU update de customer/supplier
 *   - Validação mod-11 SEFAZ (Rule BR\CpfCnpj) ANTES de bater na API externa
 *
 * Multi-tenant (ADR 0093):
 *   - Tier 0 IRREVOGÁVEL é sobre tabelas tenant-scoped — este endpoint NÃO
 *     toca tabela com business_id; só proxy informativo público.
 *   - Cache do service é global (CNPJ é dado público gov.br), não vaza PII
 *     entre tenants.
 *
 * LGPD: Log NÃO grava CNPJ plain (delegado pro service que loga só length).
 */
class ContactLookupController extends Controller
{
    public function __construct(private BrasilApiService $brasilApi) {}

    public function cnpj(Request $request, string $cnpj): JsonResponse
    {
        $user = auth()->user();

        if ($user === null) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        // Permissão: qualquer um que pode cadastrar/editar customer ou supplier
        // pode usar o lookup. Lista explícita evita match acidental.
        $hasPermission = $user->can('customer.create')
            || $user->can('supplier.create')
            || $user->can('customer.update')
            || $user->can('supplier.update');

        if (! $hasPermission) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $validator = Validator::make(
            ['cnpj' => $cnpj],
            ['cnpj' => ['required', new CpfCnpj]]
        );

        if ($validator->fails()) {
            return response()->json([
                'error' => 'cnpj_invalido',
                'message' => 'CNPJ inválido (verificação mod-11 SEFAZ).',
            ], 422);
        }

        // Rule aceita CPF tb (11d); aqui exigimos especificamente CNPJ (14d).
        $digits = preg_replace('/\D/', '', $cnpj);
        if (strlen((string) $digits) !== 14) {
            return response()->json([
                'error' => 'cnpj_invalido',
                'message' => 'Este endpoint aceita apenas CNPJ (14 dígitos).',
            ], 422);
        }

        $result = $this->brasilApi->lookupCnpj($cnpj);

        if ($result === null) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'CNPJ não encontrado na BrasilAPI.',
            ], 404);
        }

        return response()->json(['data' => $result]);
    }
}
