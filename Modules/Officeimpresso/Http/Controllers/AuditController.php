<?php

namespace Modules\Officeimpresso\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Officeimpresso\Entities\LicencaLog;

/**
 * Endpoint OPCIONAL que o Delphi futuro pode chamar pra registrar eventos
 * que o backend nao tem visibilidade (erros de conexao locais, ocorrencias
 * de hardware, timeouts percebidos).
 *
 * NUNCA obrigatorio — Delphi atual nao conhece esse endpoint e continua
 * funcionando normalmente. Adicionar compatibilidade e iniciativa do time
 * Delphi, opt-in.
 *
 * Autenticado via Passport (auth:api, ja existe no group /api/*).
 */
class AuditController extends Controller
{
    /**
     * POST /api/officeimpresso/audit
     *
     * Body JSON esperado:
     * {
     *   "event":     "custom_name",         // opcional, default 'desktop_audit'
     *   "licenca_id": 123,                  // opcional
     *   "hostname":  "BOOK-GV80BF5507",     // metadata
     *   "serial":    "ABC123",              // metadata
     *   "exe_versao":"2026.1.1.7",          // metadata
     *   "error_code":"conn_timeout",        // quando aplicavel
     *   "error_message":"Nao conectou em X" // quando aplicavel
     * }
     *
     * Todos os campos sao opcionais. Endpoint e tolerante — aceita qualquer
     * estrutura de payload e grava o que conseguir interpretar.
     */
    public function store(Request $request)
    {
        $payload = $request->all();

        // Campos reconhecidos — o resto vai pra metadata
        $known = ['event', 'licenca_id', 'error_code', 'error_message', 'endpoint', 'http_method', 'http_status', 'duration_ms'];
        $metadata = [];
        foreach ($payload as $k => $v) {
            if (! in_array($k, $known, true)) {
                $metadata[$k] = $v;
            }
        }

        $log = LicencaLog::create([
            'event'         => $payload['event']         ?? 'desktop_audit',
            'user_id'       => $request->user()->id ?? null,
            'licenca_id'    => $payload['licenca_id']    ?? null,
            'business_id'   => $request->user()?->business_id ?? null,
            'ip'            => $request->ip(),
            'user_agent'    => \Illuminate\Support\Str::limit($request->userAgent() ?? '', 500, ''),
            'endpoint'      => $payload['endpoint']      ?? null,
            'http_method'   => $payload['http_method']   ?? $request->method(),
            'http_status'   => $payload['http_status']   ?? null,
            'duration_ms'   => $payload['duration_ms']   ?? null,
            'error_code'    => $payload['error_code']    ?? null,
            'error_message' => $payload['error_message'] ?? null,
            'metadata'      => $metadata ?: null,
            'source'        => 'desktop_audit',
            'created_at'    => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $log->id], 201);
    }
}
