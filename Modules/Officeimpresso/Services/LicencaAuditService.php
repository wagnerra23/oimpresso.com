<?php

namespace Modules\Officeimpresso\Services;

use Illuminate\Support\Str;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\Officeimpresso\Entities\LicencaLog;

/**
 * Service de audit log de licencas (endpoint opcional Delphi POST /api/officeimpresso/audit).
 *
 * Extraido de AuditController (Wave 16 governance D4 Architecture). Encapsula
 * a logica de:
 *  - separar payload conhecido vs metadata
 *  - aplicar PiiRedactor (defense in depth — ADR 0094 LGPD Tier 0)
 *  - fallback redacted quando PiiRedactor indisponivel
 *  - persistir LicencaLog
 *
 * Multi-tenant Tier 0 (ADR 0093): business_id sempre extraido do usuario
 * autenticado pelo caller; metodo recebe ja resolvido.
 */
class LicencaAuditService
{
    /**
     * Campos reconhecidos do payload — o resto vai pra metadata.
     */
    private const CAMPOS_CONHECIDOS = [
        'event', 'licenca_id', 'error_code', 'error_message', 'endpoint',
        'http_method', 'http_status', 'duration_ms',
    ];

    public function __construct(private ?PiiRedactor $piiRedactor = null)
    {
        // PiiRedactor injetado via container. Pode ser null se binding falhar —
        // fallback explicito abaixo (defense in depth).
    }

    /**
     * Registra audit log a partir de payload Delphi (todos campos opcionais).
     *
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $contextoRequest  ['user_id','business_id','ip','user_agent','http_method']
     */
    public function registrar(array $payload, array $contextoRequest): LicencaLog
    {
        [$errorMessage, $metadata] = $this->extrairEMascarar($payload);

        return LicencaLog::create([
            'event'         => $payload['event']         ?? 'desktop_audit',
            'user_id'       => $contextoRequest['user_id']     ?? null,
            'licenca_id'    => $payload['licenca_id']    ?? null,
            'business_id'   => $contextoRequest['business_id'] ?? null,
            'ip'            => $contextoRequest['ip']          ?? null,
            'user_agent'    => Str::limit($contextoRequest['user_agent'] ?? '', 500, ''),
            'endpoint'      => $payload['endpoint']      ?? null,
            'http_method'   => $payload['http_method']   ?? ($contextoRequest['http_method'] ?? null),
            'http_status'   => $payload['http_status']   ?? null,
            'duration_ms'   => $payload['duration_ms']   ?? null,
            'error_code'    => $payload['error_code']    ?? null,
            'error_message' => $errorMessage,
            'metadata'      => $metadata ?: null,
            'source'        => 'desktop_audit',
            'created_at'    => now(),
        ]);
    }

    /**
     * Separa metadata desconhecida + aplica PiiRedactor (LGPD defense in depth).
     *
     * @return array{0:?string,1:array<string,mixed>}
     */
    private function extrairEMascarar(array $payload): array
    {
        $metadata = [];
        foreach ($payload as $chave => $valor) {
            if (! in_array($chave, self::CAMPOS_CONHECIDOS, true)) {
                $metadata[$chave] = $valor;
            }
        }

        $errorMessage = $payload['error_message'] ?? null;
        if ($errorMessage !== null && $this->piiRedactor !== null) {
            $errorMessage = $this->piiRedactor->redact((string) $errorMessage);
        } elseif ($errorMessage !== null) {
            $errorMessage = '[REDACTED:PII_FALLBACK]';
        }

        if (! empty($metadata) && $this->piiRedactor !== null) {
            $metadata = $this->piiRedactor->redactArray($metadata);
        } elseif (! empty($metadata)) {
            $metadata = ['_redacted' => '[REDACTED:METADATA_PII_FALLBACK]'];
        }

        return [$errorMessage, $metadata];
    }
}
