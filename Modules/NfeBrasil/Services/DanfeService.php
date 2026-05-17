<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use App\Util\OtelHelper;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Arquivos\Services\VaultEncryptionService;
use Modules\NfeBrasil\Models\NfeEmissao;
use NFePHP\DA\NFe\Danfe;
use RuntimeException;

/**
 * US-NFE-044 · Renderização do DANFE PDF a partir do XML autorizado.
 *
 * Pipeline:
 *   NfeEmissao autorizada (xml_path em storage)
 *     ↓
 *   DanfeService::salvar(NfeEmissao)
 *     ↓
 *   sped-da Danfe::render() → PDF binary
 *     ↓
 *   Storage::put('nfe-brasil/{biz}/danfe/{chave}.pdf') + update danfe_path
 *
 * Falha de render NÃO derruba a emissão (já está autorizada na SEFAZ) —
 * apenas loga warning. DANFE é regerável a qualquer momento via XML.
 */
class DanfeService
{
    /**
     * @param Closure|null $danfeFactory Override pra testes.
     *                                   Assinatura: fn(string $xml): Danfe
     */
    public function __construct(
        private readonly ?Closure $danfeFactory = null,
    ) {}

    /**
     * Renderiza DANFE a partir do XML autorizado e retorna bytes do PDF.
     *
     * Logo do business (`business.logo`) é resolvido automaticamente quando
     * o arquivo existe em `storage/app/business_logos/{filename}` (convenção
     * UPos via `BusinessUtil::uploadFile`). Se ausente ou ilegível, fallback
     * silencioso pra DANFE sem logo (default sped-da).
     *
     * @throws RuntimeException Se XML ausente em storage ou render falhar
     */
    public function renderizar(NfeEmissao $emissao): string
    {
        return OtelHelper::span('nfe.danfe_render', [
            'business_id' => (int) $emissao->business_id,
            'emissao_id' => (int) $emissao->id,
            'modelo' => $emissao->modelo ?? null,
        ], function () use ($emissao) {
            $xml = $this->obterXmlContents($emissao);

            $danfe = $this->danfeFactory !== null
                ? ($this->danfeFactory)($xml)
                : new Danfe($xml);

            $logo = $this->resolverLogoPath((int) $emissao->business_id);

            return $danfe->render($logo ?? '');
        });
    }

    /**
     * Lê XML autorizado preferindo Modules/Arquivos backbone (ADR 0123),
     * fallback pra coluna legacy `xml_path` se accessor `xml_arquivo` retorna null.
     *
     * Sprint 1 dia 4 US-ARQ-022. Após US-ARQ-021 remover coluna legacy,
     * este método simplifica pra ler só de arquivos.
     *
     * Suporte transparente a disk=vault (encrypted-at-rest) via VaultEncryptionService.
     */
    private function obterXmlContents(NfeEmissao $emissao): string
    {
        // Caminho preferido: arquivos backbone (PR #404 double-write garante popularidade pra emissões novas;
        // PR #398 backfill cobriu históricas).
        $arquivo = $emissao->xml_arquivo;
        if ($arquivo !== null) {
            $diskName = $arquivo->disk ?: 'arquivos';
            if ($arquivo->encrypted) {
                $vault = app(VaultEncryptionService::class);
                $contents = $vault->getDecrypted($diskName, $arquivo->storage_path);
            } else {
                $contents = Storage::disk($diskName)->exists($arquivo->storage_path)
                    ? Storage::disk($diskName)->get($arquivo->storage_path)
                    : null;
            }
            if (is_string($contents) && $contents !== '') {
                return $contents;
            }
            // Cai no fallback legacy abaixo (xml_arquivo achou row, mas file físico ausente).
        }

        // Fallback legacy — coluna xml_path direto. Mantém durante Sprint estabilização (US-ARQ-021).
        if (! $emissao->xml_path) {
            throw new RuntimeException(
                "NfeEmissao {$emissao->id} sem xml_path — nem arquivos backbone nem coluna legacy."
            );
        }
        if (! Storage::exists($emissao->xml_path)) {
            throw new RuntimeException(
                "XML não encontrado em storage: {$emissao->xml_path}"
            );
        }
        return Storage::get($emissao->xml_path);
    }

    /**
     * Resolve caminho absoluto do logo do business pra passar pro Danfe::render.
     *
     * Convenção UPos: `business.logo` armazena só o filename; arquivo físico
     * em `storage/app/business_logos/{filename}` (Storage default disk via
     * `BusinessUtil::uploadFile($request, 'business_logo', 'business_logos', 'image')`).
     *
     * Defensivo:
     *   - tabela `business` ausente em testes isolados → null
     *   - business sem coluna logo / null / vazio → null
     *   - filename setado mas arquivo físico ausente → null + log warning
     *
     * Retorna path absoluto OK, ou null pra fallback no caller.
     */
    private function resolverLogoPath(int $businessId): ?string
    {
        if (! Schema::hasTable('business')) {
            return null;
        }

        try {
            $logoFilename = DB::table('business')->where('id', $businessId)->value('logo');
        } catch (\Throwable) {
            return null;
        }

        if (empty($logoFilename)) {
            return null;
        }

        // Storage::path('business_logos/{file}') retorna caminho absoluto
        $absPath = Storage::disk(config('filesystems.default'))->path('business_logos/' . $logoFilename);

        if (! is_file($absPath)) {
            Log::info('DanfeService: business tem logo cadastrado mas arquivo não existe — DANFE sem logo', [
                'business_id'   => $businessId,
                'logo_filename' => $logoFilename,
                'expected_path' => $absPath,
            ]);
            return null;
        }

        return $absPath;
    }

    /**
     * Renderiza + persiste DANFE no storage. Atualiza o model com `danfe_path`.
     *
     * Defensivo: try/catch — falha de render NÃO altera status da emissão.
     * Retorna o path em storage ou null se falhou.
     */
    public function salvar(NfeEmissao $emissao): ?string
    {
        return OtelHelper::span('nfe.danfe_salvar', [
            'business_id' => (int) $emissao->business_id,
            'emissao_id' => (int) $emissao->id,
            'chave_44_present' => (bool) $emissao->chave_44,
        ], fn () => $this->salvarInterno($emissao));
    }

    private function salvarInterno(NfeEmissao $emissao): ?string
    {
        if (! $emissao->isAutorizada()) {
            Log::info('DanfeService: emissão não autorizada — pulando DANFE', [
                'emissao_id' => $emissao->id,
                'status'     => $emissao->status,
            ]);
            return null;
        }

        if (! $emissao->chave_44) {
            Log::warning('DanfeService: emissão autorizada sem chave_44 — não pode salvar DANFE', [
                'emissao_id' => $emissao->id,
            ]);
            return null;
        }

        try {
            $pdfBytes = $this->renderizar($emissao);
        } catch (\Throwable $e) {
            Log::warning('DanfeService: falha ao renderizar DANFE — emissão permanece autorizada', [
                'emissao_id' => $emissao->id,
                'chave_44'   => $emissao->chave_44,
                'error'      => $e->getMessage(),
            ]);
            return null;
        }

        $danfePath = sprintf(
            'nfe-brasil/%d/danfe/%s.pdf',
            $emissao->business_id,
            $emissao->chave_44,
        );
        Storage::put($danfePath, $pdfBytes);

        $emissao->update(['danfe_path' => $danfePath]);

        // US-ARQ-021 (ADR 0123) — double-write DANFE pra Modules/Arquivos backbone.
        $this->writeArquivoDanfe($emissao, $danfePath, $pdfBytes);

        Log::info('DanfeService: DANFE salvo', [
            'emissao_id' => $emissao->id,
            'danfe_path' => $danfePath,
            'bytes'      => strlen($pdfBytes),
        ]);

        return $danfePath;
    }

    /**
     * US-ARQ-021 — double-write DANFE PDF pra Modules/Arquivos.
     *
     * Idempotente, try/catch graceful (nunca quebra fluxo emit).
     * Mantém danfe_path coluna legacy. sub_destination='nfe-danfe'.
     *
     * @see memory/decisions/0123-modules-arquivos-backbone.md Sprint 4
     */
    private function writeArquivoDanfe(\Modules\NfeBrasil\Models\NfeEmissao $emissao, string $danfePath, string $pdfBytes): void
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('arquivos')) {
                return;
            }

            $arquivableType = 'Modules\\NfeBrasil\\Models\\NfeEmissao';

            $exists = \Illuminate\Support\Facades\DB::table('arquivos')
                ->where('arquivable_type', $arquivableType)
                ->where('arquivable_id', $emissao->id)
                ->where('sub_destination', 'nfe-danfe')
                ->where('storage_path', $danfePath)
                ->exists();

            if ($exists) {
                return;
            }

            \Illuminate\Support\Facades\DB::table('arquivos')->insert([
                'business_id'         => $emissao->business_id,
                'arquivable_type'     => $arquivableType,
                'arquivable_id'       => $emissao->id,
                'disk'                => 'local',
                'storage_path'        => $danfePath,
                'original_name'       => basename($danfePath),
                'mime_type'           => 'application/pdf',
                'size_bytes'          => strlen($pdfBytes),
                'md5'                 => md5($pdfBytes),
                'bucket'              => 'active',
                'sub_destination'     => 'nfe-danfe',
                'sensitive_flags'     => null,
                'classified_by'       => 'danfe-service-double-write',
                'classified_at'       => now(),
                'uploaded_by_user_id' => null,
                'visibility'          => 'private',
                'encrypted'           => false,
                'retention_days'      => null,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            Log::info('DanfeService.double_write.ok', [
                'emissao_id' => $emissao->id,
                'danfe_path' => $danfePath,
            ]);
        } catch (\Throwable $e) {
            Log::warning('DanfeService.double_write.fail', [
                'emissao_id' => $emissao->id ?? null,
                'error'      => substr($e->getMessage(), 0, 200),
            ]);
        }
    }

    /**
     * Lê DANFE PDF do storage como bytes (pra anexar em email/download).
     *
     * Se ainda não foi gerado, dispara `salvar()` lazy. Útil pra fluxos que
     * precisam de DANFE on-demand sem depender de ter rodado o pipeline.
     *
     * @return string|null bytes do PDF, ou null se falhou em gerar
     */
    public function lerOuGerar(NfeEmissao $emissao): ?string
    {
        if ($emissao->danfe_path && Storage::exists($emissao->danfe_path)) {
            return Storage::get($emissao->danfe_path);
        }

        $path = $this->salvar($emissao);
        if (! $path) return null;

        return Storage::get($path);
    }
}
