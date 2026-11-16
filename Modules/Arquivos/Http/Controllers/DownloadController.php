<?php

namespace Modules\Arquivos\Http\Controllers;

use App\Tenancy\ContextoEmpresa;
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
 *    a empresa vem de `ContextoEmpresa::atual()` (ADR 0453: sessão, depois usuário autenticado)
 *    e a busca filtra por ela de forma explícita. Arquivo de outra empresa → 404, mesmo
 *    com URL signed válida. Sem empresa no contexto → 404, nada é servido.
 *    O global scope de `Arquivo` sozinho não bastava: ele só filtra quando a sessão tem
 *    `user.business_id`, e esta rota não roda `SetSessionData` (sessão restaurada por
 *    "lembrar de mim" chegava aqui sem empresa e o `find` não filtrava nada).
 * 3. Audit log (`signed_url_consumed`) — toda download registra
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
            // Aqui foco em business_id + audit + stream.

            // Empresa pelo contexto (ADR 0453), não pela sessão: a rota não tem
            // SetSessionData. Sem empresa, não serve nada.
            $businessId = app(ContextoEmpresa::class)->atual();
            if ($businessId === null) {
                abort(404);
            }

            // Filtro explícito: o global scope de Arquivo não filtra quando a sessão
            // não tem empresa, então a checagem não pode depender só dele.
            $row = Arquivo::where('arquivos.business_id', $businessId)->find($arquivo);

            if (! $row) {
                abort(404);
            }

            $this->audit($row, 'signed_url_consumed', $request);

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
