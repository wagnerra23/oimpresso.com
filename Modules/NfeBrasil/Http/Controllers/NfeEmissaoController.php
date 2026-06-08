<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\Arquivos\Services\ArquivosService;
use Modules\NfeBrasil\Events\NFCeAutorizada;
use Modules\NfeBrasil\Events\NFeAutorizada;
use Modules\NfeBrasil\Http\Requests\StoreEmissaoRequest;
use Modules\NfeBrasil\Listeners\EnviarDanfeNFCePorEmail;
use Modules\NfeBrasil\Listeners\EnviarDanfePorEmail;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\DanfeService;
use Modules\NfeBrasil\Services\NfeService;

/**
 * US-NFE-MANUAL · Endpoints pra emissão fiscal manual a partir da UI Sells.
 *
 * Wagner pediu botão fiscal "Emitir NFC-e/NFe" no SaleSheet drawer + lista /sells.
 * Backend já tem auto-emissão via listener (SellCreatedOrModified → EmitirNfceJob);
 * estes endpoints cobrem o caso "auto falhou" ou "quero emitir manualmente".
 *
 * Endpoints:
 *   POST /nfe-brasil/transactions/{tx}/emitir          (modelo via body)
 *   POST /nfe-brasil/emissoes/{id}/reenviar-email      (manual dispatch listener)
 *   GET  /nfe-brasil/emissoes/{id}/danfe-pdf           (download DANFE)
 *
 * Auth: stack web normal + business scope.
 * Cancelar NFC-e/NFe → PR separado (US-NFE-CANCEL — exige novo service evento 110111).
 */
class NfeEmissaoController extends Controller
{
    public function __construct(
        private readonly NfeService $nfeService,
        private readonly DanfeService $danfeService,
        private readonly ArquivosService $arquivosService,
    ) {}

    /**
     * POST /nfe-brasil/transactions/{tx}/emitir
     *
     * Emite NFC-e (65) ou NFe (55) manualmente pra uma Transaction.
     * Reusa NfeService::emitirParaTransaction com modelo configurável.
     *
     * Body:
     *   modelo: '65' (default — NFC-e B2C) | '55' (NFe B2B)
     */
    public function emitir(StoreEmissaoRequest $request, int $tx): JsonResponse
    {
        $businessId = (int) $request->session()->get('business.id', 0);
        if ($businessId === 0) {
            return response()->json(['error' => 'no_business_context'], 400);
        }

        $modelo = (string) $request->input('modelo', '65');
        if (! in_array($modelo, ['55', '65'], true)) {
            return response()->json(['error' => 'modelo_invalido', 'message' => 'Modelo deve ser 55 (NFe) ou 65 (NFC-e).'], 422);
        }

        // Cross-tenant guard: tx deve pertencer ao business da sessão.
        $transaction = Transaction::where('business_id', $businessId)
            ->where('id', $tx)
            ->where('type', 'sell')
            ->first();
        if (! $transaction) {
            return response()->json(['error' => 'transaction_not_found'], 404);
        }

        // Idempotência: se já existe emissão autorizada deste modelo, retorna ela.
        $existing = NfeEmissao::where('business_id', $businessId)
            ->where('transaction_id', $tx)
            ->where('modelo', (int) $modelo)
            ->whereIn('status', ['autorizada', 'pendente'])
            ->orderByDesc('id')
            ->first();
        if ($existing && $existing->status === 'autorizada') {
            return response()->json([
                'emissao' => $this->serializeEmissao($existing),
                'message' => 'Emissão já existe e está autorizada. Idempotência respeitada.',
            ]);
        }

        try {
            $emissao = $this->nfeService->emitirParaTransaction($transaction, $modelo);
        } catch (\Throwable $e) {
            Log::error('[NfeEmissao] emitirManual falhou', [
                'transaction_id' => $tx,
                'business_id'    => $businessId,
                'modelo'         => $modelo,
                'erro'           => $e->getMessage(),
            ]);
            return response()->json([
                'error'   => 'emissao_falhou',
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'emissao' => $this->serializeEmissao($emissao),
        ]);
    }

    /**
     * POST /nfe-brasil/emissoes/{id}/reenviar-email
     *
     * Re-dispatch do listener EnviarDanfeNFCePorEmail (modelo 65) ou EnviarDanfePorEmail (55).
     * Usado quando primeira tentativa não foi enviada (cliente sem email no momento) OU
     * cliente perdeu o email original.
     */
    public function reenviarEmail(Request $request, int $id): JsonResponse
    {
        $businessId = (int) $request->session()->get('business.id', 0);
        if ($businessId === 0) {
            return response()->json(['error' => 'no_business_context'], 400);
        }

        $emissao = NfeEmissao::where('business_id', $businessId)->find($id);
        if (! $emissao) {
            return response()->json(['error' => 'emissao_not_found'], 404);
        }

        if ($emissao->status !== 'autorizada') {
            return response()->json([
                'error'   => 'emissao_nao_autorizada',
                'message' => 'Apenas emissões autorizadas podem ter DANFE reenviado.',
            ], 422);
        }

        try {
            // Re-dispatch listener correspondente baseado no modelo.
            if ((int) $emissao->modelo === 65) {
                $event = new NFCeAutorizada($emissao);
                app(EnviarDanfeNFCePorEmail::class)->handle($event);
            } else {
                $event = new NFeAutorizada($emissao);
                app(EnviarDanfePorEmail::class)->handle($event);
            }
        } catch (\Throwable $e) {
            Log::error('[NfeEmissao] reenviarEmail falhou', [
                'emissao_id'  => $id,
                'business_id' => $businessId,
                'erro'        => $e->getMessage(),
            ]);
            return response()->json([
                'error'   => 'reenvio_falhou',
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'DANFE reenviada por email.',
        ]);
    }

    /**
     * GET /nfe-brasil/emissoes/{id}/danfe-pdf
     *
     * Retorna PDF do DANFE pra visualização inline ou download.
     * Lazy generation: se danfe_path não está populado, gera via DanfeService.
     */
    public function danfePdf(Request $request, int $id): Response
    {
        $businessId = (int) $request->session()->get('business.id', 0);
        if ($businessId === 0) {
            abort(400);
        }

        $emissao = NfeEmissao::where('business_id', $businessId)->find($id);
        if (! $emissao) {
            abort(404);
        }

        if ($emissao->status !== 'autorizada') {
            abort(422, 'Apenas emissões autorizadas têm DANFE.');
        }

        $pdf = $this->danfeService->lerOuGerar($emissao);

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => sprintf(
                'inline; filename="danfe-%s.pdf"',
                $emissao->chave_44 ?: $emissao->id,
            ),
            'Cache-Control'       => 'private, max-age=300',
        ]);
    }

    /**
     * GET /nfe-brasil/api/transactions/{tx}/emissoes
     *
     * Lista TODAS as emissões fiscais (NFC-e 65 + NFe 55) de uma transaction.
     * Substituiu o GET nfe-status que retornava só modelo 65.
     */
    public function listar(Request $request, int $tx): JsonResponse
    {
        $businessId = (int) $request->session()->get('business.id', 0);
        if ($businessId === 0) {
            return response()->json(['error' => 'no_business_context'], 400);
        }

        $transaction = Transaction::where('business_id', $businessId)->find($tx);
        if (! $transaction) {
            return response()->json(['error' => 'transaction_not_found'], 404);
        }

        $emissoes = NfeEmissao::where('business_id', $businessId)
            ->where('transaction_id', $tx)
            ->orderBy('modelo')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($e) => $this->serializeEmissao($e))
            ->values();

        return response()->json([
            'transaction_id' => $tx,
            'emissoes'       => $emissoes,
        ]);
    }

    private function serializeEmissao(NfeEmissao $emissao): array
    {
        $xmlArquivo   = $emissao->xml_arquivo;
        $danfeArquivo = $emissao->danfe_arquivo;

        return [
            'id'           => $emissao->id,
            'modelo'       => (string) $emissao->modelo,
            'modelo_label' => (int) $emissao->modelo === 65 ? 'NFC-e' : 'NFe',
            'serie'        => $emissao->serie,
            'numero'       => $emissao->numero,
            'chave_44'     => $emissao->chave_44,
            'status'       => $emissao->status,
            'cstat'        => $emissao->cstat,
            'motivo'       => $emissao->motivo,
            'valor_total'  => (float) $emissao->valor_total,
            'emitido_em'   => optional($emissao->emitido_em)->toIso8601String(),
            'is_terminal'  => in_array($emissao->status, ['autorizada', 'rejeitada', 'denegada', 'cancelada'], true),
            'is_cancelavel' => method_exists($emissao, 'isCancelavel') ? $emissao->isCancelavel() : false,
            // ADR 0123 — signed URLs backbone Arquivos (60min TTL). null quando arquivo ausente.
            'xml_url'      => $xmlArquivo !== null
                ? $this->arquivosService->signedUrl($xmlArquivo)
                : null,
            'danfe_url'    => $danfeArquivo !== null
                ? $this->arquivosService->signedUrl($danfeArquivo)
                : null,
        ];
    }
}
