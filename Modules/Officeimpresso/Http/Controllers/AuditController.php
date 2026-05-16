<?php

namespace Modules\Officeimpresso\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Officeimpresso\Services\LicencaAuditService;

/**
 * Endpoint OPCIONAL que o Delphi futuro pode chamar pra registrar eventos
 * que o backend nao tem visibilidade (erros de conexao locais, ocorrencias
 * de hardware, timeouts percebidos).
 *
 * Wave 16 governance D4 Architecture: Controller magro — toda regra de
 * sanitizacao PII + persistencia delegada a LicencaAuditService.
 */
class AuditController extends Controller
{
    public function __construct(private LicencaAuditService $auditService)
    {
    }

    /**
     * POST /api/officeimpresso/audit
     * Body JSON tolerante (todos campos opcionais).
     */
    public function store(Request $request)
    {
        $log = $this->auditService->registrar(
            $request->all(),
            [
                'user_id'     => $request->user()->id ?? null,
                'business_id' => $request->user()?->business_id ?? null,
                'ip'          => $request->ip(),
                'user_agent'  => $request->userAgent() ?? '',
                'http_method' => $request->method(),
            ]
        );

        return response()->json(['ok' => true, 'id' => $log->id], 201);
    }
}
