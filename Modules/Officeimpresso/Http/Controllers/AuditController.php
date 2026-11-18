<?php

namespace Modules\Officeimpresso\Http\Controllers;

use Modules\Officeimpresso\Http\Requests\StoreDesktopAuditRequest;
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
     * Todos os campos sao opcionais; `event` so da lista fechada do desktop e
     * `licenca_id` so da empresa do usuario (StoreDesktopAuditRequest). Campos nao
     * conhecidos seguem para metadata, com o valor validado prevalecendo.
     */
    public function store(StoreDesktopAuditRequest $request)
    {
        $log = $this->auditService->registrar(
            array_merge($request->all(), $request->validated()),
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
