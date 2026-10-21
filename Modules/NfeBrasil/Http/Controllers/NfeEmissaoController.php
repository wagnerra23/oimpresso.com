<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Modules\Arquivos\Services\ArquivosService;
use Modules\NfeBrasil\Events\NFCeAutorizada;
use Modules\NfeBrasil\Events\NFeAutorizada;
use Modules\NfeBrasil\Exceptions\NcmPadraoAusenteException;
use Modules\NfeBrasil\Http\Requests\StoreEmissaoRequest;
use Modules\NfeBrasil\Listeners\EnviarDanfeNFCePorEmail;
use Modules\NfeBrasil\Listeners\EnviarDanfePorEmail;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\DanfeService;
use Modules\NfeBrasil\Services\NfeService;
use Modules\NfeBrasil\Services\SeparacaoNotasDaVenda;

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

        // US-NFE-069 · UC-FLX-27: "Emitir NF-e + NFS-e" manda separada=1; a NF-e leva só a parte
        // mercadoria. Quem dispara a divisão precisa poder emitir a NFS-e também.
        $separada = $request->boolean('separada');
        if ($separada) {
            if (! $request->user()->can('nfse.emit')) {
                return response()->json(['error' => 'sem_permissao', 'message' => 'Emitir NF-e + NFS-e exige a permissão de emitir NFS-e.'], 403);
            }
            $bloqueio = $this->bloqueioDaSeparacao($businessId, $tx);
            if ($bloqueio !== null) {
                return $bloqueio;
            }
        }

        try {
            $emissao = $this->nfeService->emitirParaTransaction($transaction, $modelo, $separada);
        } catch (NcmPadraoAusenteException $e) {
            // R-NFE-022: bloqueio de configuração, não falha do servidor. Devolve a saída de 1 clique.
            return response()->json([
                'error'   => NcmPadraoAusenteException::ERRO,
                'message' => $e->getMessage(),
                'acao'    => $e->acao(),
            ], 422);
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

    /**
     * GET /nfe-brasil/api/transactions/{tx}/separacao — US-NFE-069 · UC-FLX-27.
     *
     * Mostra as duas partes da venda (NF-e e NFS-e) antes de o usuário confirmar a emissão.
     * Só lê: a conta é a de SeparacaoNotasDaVenda (regra de rateio decidida por [W] 2026-10-21).
     */
    public function separacao(Request $request, int $tx): JsonResponse
    {
        $businessId = (int) $request->session()->get('business.id', 0);
        if ($businessId === 0) {
            return response()->json(['error' => 'no_business_context'], 400);
        }
        if (! $request->user()?->can('nfse.emit')) {
            return response()->json(['error' => 'sem_permissao'], 403);
        }

        try {
            $sep = app(SeparacaoNotasDaVenda::class)->separar($businessId, $tx);
        } catch (ModelNotFoundException) {
            return response()->json(['error' => 'transaction_not_found'], 404);
        } catch (\RuntimeException $e) {
            return response()->json(['separavel' => false, 'message' => $e->getMessage()]);
        }

        $parte = fn (array $p) => [
            'itens'     => array_map(fn (array $l) => [
                'descricao'      => $l['descricao'],
                'quantidade'     => $l['quantidade'],
                'bruto'          => $l['bruto'],
                'codigo_servico' => $l['codigo_servico'] ?? null,
            ], $p['linhas']),
            'bruto'     => $p['bruto'],
            'desconto'  => $p['desconto'],
            'acrescimo' => $p['acrescimo'],
            'frete'     => $p['frete'],
            'total'     => $p['total'],
        ];

        return response()->json([
            'separavel'   => $sep[SeparacaoNotasDaVenda::MERCADORIA]['total'] > 0 && $sep[SeparacaoNotasDaVenda::SERVICO]['total'] > 0,
            'regra'       => $sep['regra'],
            'final_total' => $sep['final_total'],
            'mercadoria'  => $parte($sep[SeparacaoNotasDaVenda::MERCADORIA]),
            'servico'     => $parte($sep[SeparacaoNotasDaVenda::SERVICO]),
            'pendentes'   => $sep['pendentes'],
        ]);
    }

    /** Recusa a emissão separada quando a venda não tem parte mercadoria ou tem natureza pendente. */
    private function bloqueioDaSeparacao(int $businessId, int $tx): ?JsonResponse
    {
        try {
            $sep = app(SeparacaoNotasDaVenda::class)->separar($businessId, $tx);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'separacao_invalida', 'message' => $e->getMessage()], 422);
        }
        if ($sep['pendentes'] !== []) {
            return response()->json([
                'error'   => 'natureza_pendente',
                'message' => 'Produto(s) ' . implode(', ', $sep['pendentes']) . ' com natureza fiscal a decidir: o contador decide antes de faturar.',
            ], 422);
        }
        if ($sep[SeparacaoNotasDaVenda::MERCADORIA]['total'] <= 0) {
            return response()->json(['error' => 'sem_mercadoria', 'message' => 'A venda não tem parte mercadoria — emita só a NFS-e.'], 422);
        }

        return null;
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
