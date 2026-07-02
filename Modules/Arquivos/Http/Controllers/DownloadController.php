<?php

namespace Modules\Arquivos\Http\Controllers;

use App\Util\OtelHelper;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Arquivos\Entities\Arquivo;
use Modules\Arquivos\Services\VaultEncryptionService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DownloadController — endpoint pra signed URL gerada por ArquivosService::signedUrl().
 *
 * Sprint 1 dia 4 (US-ARQ-008). Servir conteúdo de Arquivo via download autenticado:
 *
 * 1. URL temporária (Laravel temporarySignedRoute) com expiração 60min default
 * 2. Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 *    Arquivo::find aplica global scope `business_id` automaticamente — usuário
 *    autenticado biz=1 NUNCA acessa arquivo biz=4 mesmo com URL signed válida
 * 3. Audit log (action `download`) — toda consumação de signed URL registra.
 *    `download` é membro do enum `arquivos_audit_log.action` (o par de
 *    `signed_url_issued`, emitido no momento da geração da URL). O valor
 *    `signed_url_consumed` NÃO existe no enum e era truncado/rejeitado em
 *    MySQL strict → gap de auditoria silencioso (fix 2026-07-02).
 * 4. Disk routing: lê do disk `arquivos` ou `vault` baseado em `$arquivo->disk`
 *
 * @see memory/decisions/0123-modules-arquivos-backbone.md §6+§8
 */
class DownloadController extends Controller
{
    public function __invoke(Request $request, int $arquivo, VaultEncryptionService $vault): Response|StreamedResponse
    {
        // Wave 18 D9.a — span pra download (hot-path crítico audit + crypto).
        // Atributos sem PII: arquivo_id + bucket + encrypted flag.
        return OtelHelper::spanBiz('arquivos.download', function () use ($request, $arquivo, $vault) {
            // Signed URL middleware (Laravel) já valida expiração + assinatura HMAC.
            // Aqui foco em business_id scope + audit + stream.

            $row = Arquivo::find($arquivo);

            if (! $row) {
                abort(404);
            }

            // Ação `download` (membro do enum) — registra a consumação da signed URL.
            // NUNCA usar `signed_url_consumed` aqui: não está no enum e o INSERT
            // é rejeitado em MySQL strict, engolido pelo try/catch de audit().
            $this->audit($row, 'download', $request);

            $diskName = $row->disk ?: 'arquivos';
            $disk = Storage::disk($diskName);

            if (! $disk->exists($row->storage_path)) {
                abort(404, 'Arquivo não encontrado no storage.');
            }

            // Bucket=sensitive em disk=vault: encrypted-at-rest, decrypt antes de servir
            // (ADR 0123 §3). Storage::download serviria ciphertext direto pro cliente.
            if ($row->encrypted) {
                $plain = $vault->getDecrypted($diskName, $row->storage_path);
                if ($plain === null) {
                    abort(404, 'Arquivo não encontrado no storage (decrypt).');
                }
                return response($plain, 200, [
                    'Content-Type'        => $row->mime_type ?: 'application/octet-stream',
                    'Content-Disposition' => 'attachment; filename="' . addslashes($row->original_name) . '"',
                ]);
            }

            return $disk->download(
                $row->storage_path,
                $row->original_name,
                [
                    'Content-Type' => $row->mime_type ?: 'application/octet-stream',
                ]
            );
        }, [
            'module'      => 'Arquivos',
            'arquivo_id'  => $arquivo,
        ]);
    }

    private function audit(Arquivo $arquivo, string $action, Request $request): void
    {
        try {
            DB::table('arquivos_audit_log')->insert([
                'arquivo_id'  => $arquivo->id,
                'business_id' => $arquivo->business_id,
                'user_id'     => Auth::id(),
                'action'      => $action,
                'payload'     => json_encode([
                    'ip'    => $request->ip(),
                    'agent' => substr((string) $request->userAgent(), 0, 200),
                ]),
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('arquivos.download.audit_failed', [
                'arquivo_id' => $arquivo->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
