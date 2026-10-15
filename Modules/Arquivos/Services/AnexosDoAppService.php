<?php

declare(strict_types=1);

namespace Modules\Arquivos\Services;

use App\Contracts\Arquivos\AnexosDoApp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Implementação de App\Contracts\Arquivos\AnexosDoApp (app das lojas, P2). Grava pelo mesmo
 * caminho do anexo da web (ArquivosService) e serve o conteúdo da URL assinada do app.
 *
 * Tier 0 (ADR 0093): business sempre explícito — os global scopes leem a sessão, que não
 * existe numa chamada com token.
 */
final class AnexosDoAppService implements AnexosDoApp
{
    public function __construct(
        private ArquivosService $arquivos,
        private VaultEncryptionService $vault,
    ) {}

    public function anexar(int $businessId, string $tipoDono, int $donoId, UploadedFile $arquivo): ?int
    {
        if (! is_subclass_of($tipoDono, Model::class)) {
            return null;
        }
        /** @var Model $modelo */
        $modelo = new $tipoDono();
        // where explícito no business: não depende do global scope do dono.
        $dono = $modelo->newQuery()->where($modelo->getTable() . '.business_id', $businessId)
            ->whereKey($donoId)->first();
        if ($dono === null) {
            return null;
        }

        return (int) $this->arquivos->attachNoNegocio($dono, $arquivo, $businessId)->id;
    }

    public function conteudo(int $businessId, int $arquivoId): ?array
    {
        $a = DB::table('arquivos')->where('business_id', $businessId)->where('id', $arquivoId)
            ->whereNull('deleted_at')
            ->first(['id', 'business_id', 'disk', 'storage_path', 'original_name', 'mime_type', 'encrypted']);
        if ($a === null || trim((string) $a->storage_path) === '') {
            return null;
        }

        $disco = $a->disk ?: 'arquivos';
        if (! Storage::disk($disco)->exists($a->storage_path)) {
            return null;
        }
        $corpo = (bool) $a->encrypted
            ? $this->vault->getDecrypted($disco, $a->storage_path)
            : Storage::disk($disco)->get($a->storage_path);
        if ($corpo === null) {
            return null;
        }

        try {
            DB::table('arquivos_audit_log')->insert([
                'arquivo_id' => (int) $a->id, 'business_id' => (int) $a->business_id, 'user_id' => null,
                'action' => 'signed_url_consumed', 'payload' => json_encode(['via' => 'app']),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('arquivos.app.audit_failed', ['arquivo_id' => (int) $a->id]);
        }

        return ['corpo' => $corpo, 'tipo' => (string) ($a->mime_type ?: 'application/octet-stream'), 'nome' => (string) $a->original_name];
    }
}
