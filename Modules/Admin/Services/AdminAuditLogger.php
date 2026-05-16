<?php

namespace Modules\Admin\Services;

use App\Util\OtelHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * AdminAuditLogger — append-only audit pra mutations Admin Center.
 *
 * Toda mutation (apply Curador / regenerate token / run-now health) gera
 * linha em `mcp_admin_audit_log` com user_id + business_id + action + payload + IP.
 *
 * **D7.a LGPD (Wave 14, 2026-05-16):** PiiRedactor aplicado recursivamente
 * no payload ANTES de gravar. Apesar do Admin Center ser Wagner-only CT 100
 * (acesso restrito), payload pode conter `reason` ad-hoc digitado por
 * Wagner que mencione CPF/CNPJ/email de cliente (ex: "rotacionando token
 * porque maria@cliente.com.br perdeu acesso"). Defesa em profundidade
 * pra LGPD Art. 7º + Constituição v2 §6.
 *
 * **D9.a OTel (Wave 14):** span `admin.audit.log` envolve INSERT pra
 * detectar slow audit + correlacionar com trace request. Zero-cost
 * quando `otel.enabled=false` (default Hostinger).
 *
 * @see memory/decisions/0122-admin-center-ct100.md §3
 * @see memory/decisions/0155-module-grade-v3-anti-injustica-na-justified.md D7+D9
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 * @see app\Util\OtelHelper
 */
class AdminAuditLogger
{
    public function __construct(
        protected ?PiiRedactor $pii = null,
    ) {
        $this->pii ??= app(PiiRedactor::class);
    }

    public function log(string $action, array $payload = [], ?Request $request = null): void
    {
        $businessId = session('user.business_id') ?? session('business.id') ?? 0;

        OtelHelper::span('admin.audit.log', [
            'business_id'  => $businessId,
            'admin.action' => $action,
        ], function () use ($action, $payload, $request, $businessId) {
            try {
                // D7.a — redact PII recursivo no payload antes de persistir.
                $payloadRedacted = $this->pii->redactArray($payload);

                DB::table('mcp_admin_audit_log')->insert([
                    'user_id'     => Auth::id(),
                    'business_id' => $businessId,
                    'action'      => $action,
                    'route'       => $request?->path(),
                    'ip'          => $request?->ip(),
                    'payload'     => json_encode($payloadRedacted),
                    'created_at'  => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('admin.audit.fail', [
                    'action' => $action,
                    'error'  => $e->getMessage(),
                ]);
            }
        });
    }
}
