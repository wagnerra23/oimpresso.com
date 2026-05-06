<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
     * @throws RuntimeException Se XML ausente em storage ou render falhar
     */
    public function renderizar(NfeEmissao $emissao): string
    {
        if (! $emissao->xml_path) {
            throw new RuntimeException(
                "NfeEmissao {$emissao->id} sem xml_path — não há XML autorizado pra renderizar DANFE."
            );
        }

        if (! Storage::exists($emissao->xml_path)) {
            throw new RuntimeException(
                "XML não encontrado em storage: {$emissao->xml_path}"
            );
        }

        $xml = Storage::get($emissao->xml_path);

        $danfe = $this->danfeFactory !== null
            ? ($this->danfeFactory)($xml)
            : new Danfe($xml);

        return $danfe->render();
    }

    /**
     * Renderiza + persiste DANFE no storage. Atualiza o model com `danfe_path`.
     *
     * Defensivo: try/catch — falha de render NÃO altera status da emissão.
     * Retorna o path em storage ou null se falhou.
     */
    public function salvar(NfeEmissao $emissao): ?string
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

        Log::info('DanfeService: DANFE salvo', [
            'emissao_id' => $emissao->id,
            'danfe_path' => $danfePath,
            'bytes'      => strlen($pdfBytes),
        ]);

        return $danfePath;
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
