<?php

namespace Modules\Arquivos\Services;

use App\Util\OtelHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Arquivos\Entities\Arquivo;
use Modules\Arquivos\Services\Curador\CuradorEngine;
use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * ArquivosService — API canônica do DMS backbone (ADR 0123 §API).
 *
 * Métodos:
 * - attach(): upload + classify + dedupe + audit log
 * - classify(): chama CuradorEngine
 * - signedUrl(): URL temporária expiração 1h
 * - softDelete() / restore()
 * - dedupe(): lookup por MD5 dentro do mesmo business_id
 *
 * Sprint 1 MVP: implementação básica. Polimento (race conditions, atomicidade
 * cross-tabela, rollback) fica em US-ARQ-008.
 *
 * @see memory/decisions/0123-modules-arquivos-backbone.md
 */
class ArquivosService
{
    public function __construct(
        protected CuradorEngine $curador,
        protected VaultEncryptionService $vault,
    ) {}

    /**
     * Anexa arquivo a um Model (qualquer model com trait HasArquivos).
     *
     * @param Model        $owner Model que vai receber o arquivo
     * @param UploadedFile $file  Arquivo upload
     * @param array        $opts  ['context' => 'nfe-xml'|'ticket-anexo'|...] (futuro: MIME whitelist)
     */
    public function attach(Model $owner, UploadedFile $file, array $opts = []): Arquivo
    {
        $businessId = session('user.business_id') ?? session('business.id');
        if (! $businessId) {
            throw new \RuntimeException('attach: business_id ausente na sessão (multi-tenant Tier 0).');
        }

        return OtelHelper::spanBiz('arquivos.attach', function () use ($owner, $file, $opts, $businessId) {
            return $this->attachInternal($owner, $file, $opts, (int) $businessId);
        }, [
            'module'        => 'Arquivos',
            'owner_type'    => $owner::class,
            'size_bytes'    => (int) $file->getSize(),
            'mime_type'     => $file->getMimeType() ?? 'application/octet-stream',
            'extension'     => strtolower($file->getClientOriginalExtension() ?: 'bin'),
        ]);
    }

    /**
     * Internal attach — extraído pra ser envelopado por OtelHelper::spanBiz (D9.a).
     * Log estruturado emite tamanho + tipo MIME (sem PII — filename redactado).
     */
    private function attachInternal(Model $owner, UploadedFile $file, array $opts, int $businessId): Arquivo
    {
        $md5 = md5_file($file->getRealPath());

        // Dedupe lookup por business — se já existe arquivo com mesmo MD5
        // no mesmo business, retorna ele em vez de duplicar storage.
        $dedupe = $this->dedupe($md5, (int) $businessId);
        if ($dedupe !== null) {
            $this->incrementDedupeCounter($md5);
            return $dedupe;
        }

        // Pré-classifica via CuradorEngine pra decidir disk (sensitive → vault)
        $stub = new Arquivo([
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes'    => $file->getSize(),
            'md5'           => $md5,
        ]);
        $classification = $this->curador->classify($stub);
        $disk = ($classification['bucket'] ?? 'active') === 'sensitive'
            ? config('arquivos.disk_vault', 'vault')
            : config('arquivos.disk_default', 'arquivos');

        // Path: biz-{id}/YYYY/MM/{md5_prefix}.{ext}
        $year  = now()->format('Y');
        $month = now()->format('m');
        $ext   = $file->getClientOriginalExtension() ?: 'bin';
        $rel   = "biz-{$businessId}/{$year}/{$month}/{$md5}.{$ext}";

        if ($disk === config('arquivos.disk_vault', 'vault')) {
            // Encryption-at-rest mandatório pra bucket=sensitive (ADR 0123 §3).
            // VaultEncryptionService usa Crypt::encryptString (APP_KEY AES-256-CBC).
            $stored = $this->vault->putFileEncrypted($disk, $rel, $file);
        } else {
            $stored = Storage::disk($disk)->putFileAs(
                "biz-{$businessId}/{$year}/{$month}",
                $file,
                "{$md5}.{$ext}",
            );
        }
        if ($stored === false) {
            throw new \RuntimeException("attach: falha ao escrever em disk={$disk}");
        }

        $arquivo = new Arquivo();
        $arquivo->business_id         = (int) $businessId;
        $arquivo->arquivable_type     = $owner::class;
        $arquivo->arquivable_id       = $owner->getKey();
        $arquivo->disk                = $disk;
        $arquivo->storage_path        = $rel;
        $arquivo->original_name       = $file->getClientOriginalName();
        $arquivo->mime_type           = $stub->mime_type;
        $arquivo->size_bytes          = $stub->size_bytes;
        $arquivo->md5                 = $md5;
        $arquivo->bucket              = $classification['bucket'] ?? 'active';
        $arquivo->sub_destination     = $classification['sub_destination'] ?? null;
        $arquivo->sensitive_flags     = $classification['sensitive_flags'] ?? null;
        $arquivo->classified_by       = $classification['rule_matched'] ?? 'curador-engine';
        $arquivo->classified_at       = now();
        $arquivo->uploaded_by_user_id = auth()->id();
        $arquivo->encrypted           = $disk === 'vault';
        $arquivo->save();

        $this->insertDedupe($md5);
        $this->audit($arquivo, 'upload', ['size' => $arquivo->size_bytes]);

        // D9.b — log estruturado upload: tamanho + tipo MIME + biz (sem PII).
        Log::info('arquivos.upload', [
            'business_id'  => $arquivo->business_id,
            'arquivo_id'   => $arquivo->id,
            'mime_type'    => $arquivo->mime_type,
            'size_bytes'   => $arquivo->size_bytes,
            'bucket'       => $arquivo->bucket,
            'encrypted'    => $arquivo->encrypted,
        ]);

        return $arquivo;
    }

    public function classify(Arquivo $arquivo): array
    {
        return OtelHelper::spanBiz('arquivos.classify', function () use ($arquivo) {
            return $this->classifyInternal($arquivo);
        }, [
            'module'      => 'Arquivos',
            'arquivo_id'  => $arquivo->id,
        ]);
    }

    private function classifyInternal(Arquivo $arquivo): array
    {
        $result = $this->curador->classify($arquivo);
        $arquivo->bucket          = $result['bucket'] ?? $arquivo->bucket;
        $arquivo->sub_destination = $result['sub_destination'] ?? $arquivo->sub_destination;
        $arquivo->sensitive_flags = $result['sensitive_flags'] ?? $arquivo->sensitive_flags;
        $arquivo->classified_by   = $result['rule_matched'] ?? $arquivo->classified_by;
        $arquivo->classified_at   = now();
        $arquivo->save();
        $this->audit($arquivo, 'reclassify', $result);
        return $result;
    }

    public function signedUrl(Arquivo $arquivo, int $expiresMinutes = 60): string
    {
        return OtelHelper::spanBiz('arquivos.signed_url', function () use ($arquivo, $expiresMinutes) {
            $url = URL::temporarySignedRoute(
                'arquivos.download',
                now()->addMinutes($expiresMinutes),
                ['arquivo' => $arquivo->id],
            );
            $this->audit($arquivo, 'signed_url_issued', ['expires_minutes' => $expiresMinutes]);
            return $url;
        }, [
            'module'           => 'Arquivos',
            'arquivo_id'       => $arquivo->id,
            'expires_minutes'  => $expiresMinutes,
        ]);
    }

    public function softDelete(Arquivo $arquivo): void
    {
        OtelHelper::spanBiz('arquivos.soft_delete', function () use ($arquivo) {
            $arquivo->delete();
            $this->audit($arquivo, 'soft_delete', []);
        }, [
            'module'      => 'Arquivos',
            'arquivo_id'  => $arquivo->id,
            'size_bytes'  => $arquivo->size_bytes,
        ]);
    }

    public function restore(Arquivo $arquivo): void
    {
        OtelHelper::spanBiz('arquivos.restore', function () use ($arquivo) {
            $arquivo->restore();
            $this->audit($arquivo, 'restore', []);
        }, [
            'module'      => 'Arquivos',
            'arquivo_id'  => $arquivo->id,
        ]);
    }

    /**
     * Dedupe lookup — retorna Arquivo existente com mesmo MD5 no mesmo business.
     * Não vaza cross-business (Agent E security review §dedupe leak).
     *
     * Wave 26 D9 — span observável separa hit/miss vs latência da query.
     */
    public function dedupe(string $md5, int $businessId): ?Arquivo
    {
        return OtelHelper::spanBiz('arquivos.dedupe_lookup', function () use ($md5, $businessId) {
            return Arquivo::where('md5', $md5)
                ->where('business_id', $businessId)
                ->first();
        }, [
            'module'      => 'Arquivos',
            'business_id' => $businessId,
            // md5 NÃO incluído (não é PII mas é hash de conteúdo — defesa em profundidade)
        ]);
    }

    private function insertDedupe(string $md5): void
    {
        DB::statement(
            'INSERT INTO arquivos_dedupe (md5, first_seen_at, occurrences) VALUES (?, NOW(), 1)
             ON DUPLICATE KEY UPDATE occurrences = occurrences + 1',
            [$md5],
        );
    }

    private function incrementDedupeCounter(string $md5): void
    {
        DB::statement(
            'UPDATE arquivos_dedupe SET occurrences = occurrences + 1 WHERE md5 = ?',
            [$md5],
        );
    }

    private function audit(Arquivo $arquivo, string $action, array $payload): void
    {
        try {
            // D7 LGPD (Wave 10): payload redactado antes de persistir.
            // Filename pode conter CPF/CNPJ (ex: "contrato-cliente-123.456.789-00.pdf");
            // payload de classificação pode conter sub_destination com PII.
            // Audit log em si NÃO precisa armazenar PII bruta — só metadados.
            $redacted = $this->redactPayload($payload);

            DB::table('arquivos_audit_log')->insert([
                'arquivo_id'  => $arquivo->id,
                'business_id' => $arquivo->business_id,
                'user_id'     => auth()->id(),
                'action'      => $action,
                'payload'     => json_encode($redacted),
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // D7 LGPD: erro NÃO pode vazar filename/path em log → PiiRedactor.
            Log::warning('arquivos.audit_failed', [
                'arquivo_id' => $arquivo->id ?? null,
                'action'     => $action,
                'error'      => $this->redactPiiSafe($e->getMessage()),
            ]);
        }
    }

    /**
     * D7 LGPD — redactor PII em payload de audit log antes de persistir.
     *
     * Filename pode trazer CPF/CNPJ embutido (ex: "rg-123.456.789-00.pdf",
     * "contrato-12.345.678-0001-90.pdf"). Email/telefone podem aparecer em
     * sub_destination ou metadados livres. PiiRedactor (Modules\Jana) cobre
     * todos padrões BR canônicos (CPF, CNPJ, email, phone, CEP).
     *
     * Fail-open: se PiiRedactor não puder ser resolvido (boot/teardown), retorna
     * payload original. Auditoria SEMPRE acontece, redaction é defesa em profundidade.
     */
    private function redactPayload(array $payload): array
    {
        try {
            return App::make(PiiRedactor::class)->redactArray($payload);
        } catch (\Throwable) {
            return $payload; // fail-open: audit > redaction
        }
    }

    /**
     * D7 LGPD — redactor seguro pra strings de log (mensagens de erro).
     * Fail-open por design — não pode quebrar o fluxo de auditoria.
     */
    private function redactPiiSafe(?string $input): string
    {
        if ($input === null || $input === '') {
            return '';
        }

        try {
            return App::make(PiiRedactor::class)->redact($input);
        } catch (\Throwable) {
            return $input;
        }
    }
}
