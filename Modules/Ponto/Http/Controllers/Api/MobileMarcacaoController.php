<?php

declare(strict_types=1);

namespace Modules\Ponto\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Ponto\Services\MobileMarcacaoService;
use RuntimeException;
use Throwable;

/**
 * MobileMarcacaoController — W28-8 API REST mobile Ponto.
 *
 * Endpoint POST /api/v1/ponto/marcacao-mobile autenticado via Sanctum
 * (token per-funcionario, escopo 'ponto:marcar'). Recebe payload Tangerino-like
 * (selfie + lat/lng + device_uuid + timestamp) e delega ao MobileMarcacaoService.
 *
 * Tier 0 IRREVOGAVEL:
 *   - business_id deduzido do user autenticado (Sanctum) ([ADR 0093]).
 *   - selfie_base64 NUNCA logado em laravel.log (PII LGPD — apenas tamanho).
 *   - response retorna apenas IDs + hash truncado (sem PII).
 *
 * @see MobileMarcacaoService::registrarMarcacaoMobile
 * @see routes/api.php (registrar rota com middleware auth:sanctum + abilities)
 */
class MobileMarcacaoController extends Controller
{
    /** @var MobileMarcacaoService */
    protected $service;

    public function __construct(MobileMarcacaoService $service)
    {
        $this->service = $service;
    }

    /**
     * POST /api/v1/ponto/marcacao-mobile
     *
     * Body JSON:
     * {
     *   "tipo": "ENTRADA",
     *   "selfie_base64": "...",
     *   "lat": -28.336,
     *   "lng": -48.926,
     *   "accuracy": 12.5,
     *   "device_uuid": "abc-123-...",
     *   "timestamp_device": "2026-05-17T08:00:00-03:00"
     * }
     */
    public function registrar(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'erro' => 'nao_autenticado',
                'mensagem' => 'Token Sanctum invalido ou ausente.',
            ], 401);
        }

        $businessId = (int) $user->business_id;
        $funcionarioId = (int) ($request->input('funcionario_id') ?? $user->id);

        // Validacao basica (Service faz validacao profunda anti-cheat)
        $validated = $request->validate([
            'tipo'             => 'required|string|in:ENTRADA,SAIDA,ALMOCO_INICIO,ALMOCO_FIM',
            'selfie_base64'    => 'required|string|min:100000', // ~75KB binario
            'lat'              => 'required|numeric|between:-90,90',
            'lng'              => 'required|numeric|between:-180,180',
            'accuracy'         => 'required|numeric|min:0|max:5000',
            'device_uuid'      => 'required|string|max:128',
            'timestamp_device' => 'required|string|max:64',
        ]);

        $payload = array_merge($validated, [
            'usuario_criador_id' => (int) $user->id,
        ]);

        try {
            $marcacao = $this->service->registrarMarcacaoMobile(
                $businessId,
                $funcionarioId,
                $payload
            );
        } catch (RuntimeException $e) {
            // Erros de anti-cheat / validacao: 422 (cliente corrige)
            return response()->json([
                'erro' => 'validacao_falhou',
                'mensagem' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            // Erros inesperados: 500 + log sem PII
            Log::error('ponto.mobile.marcacao.erro', [
                'business_id' => $businessId,
                'funcionario_id' => $funcionarioId,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'erro' => 'erro_interno',
                'mensagem' => 'Falha ao registrar marcacao. Tente novamente.',
            ], 500);
        }

        return response()->json([
            'sucesso' => true,
            'marcacao' => [
                'id'           => (string) $marcacao->id,
                'nsr'          => (int) $marcacao->nsr,
                'tipo'         => (string) $marcacao->tipo,
                'momento'      => $marcacao->momento->toIso8601String(),
                'hash_trunc'   => substr((string) $marcacao->hash, 0, 16),
                'origem'       => (string) $marcacao->origem,
            ],
        ], 201);
    }

    /**
     * GET /api/v1/ponto/marcacao-mobile/pendentes-validacao
     * Lista marcacoes mobile que precisam de revisao humana (gestor/RH).
     */
    public function pendentesValidacao(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['erro' => 'nao_autenticado'], 401);
        }

        $pendentes = $this->service->listarMarcacoesMobilePendentesValidacao(
            (int) $user->business_id
        );

        return response()->json([
            'total' => $pendentes->count(),
            'marcacoes' => $pendentes->map(fn ($m) => [
                'id'        => (string) $m->id,
                'momento'   => $m->momento?->toIso8601String(),
                'tipo'      => (string) $m->tipo,
                'lat'       => $m->latitude,
                'lng'       => $m->longitude,
                'hash_trunc' => substr((string) $m->hash, 0, 16),
            ])->values(),
        ], 200);
    }
}
