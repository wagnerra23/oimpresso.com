<?php

declare(strict_types=1);

namespace Modules\Ponto\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Ponto\Entities\BancoHorasSaldo;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Services\MobileMarcacaoService;
use RuntimeException;
use Throwable;

/**
 * MobileMarcacaoController — API do REP-P (bloco 2 de `Http/routes.php`, prefixo
 * `/ponto/api`, `auth:api` Passport). Thread 06 do playbook do Ponto.
 *
 * O colaborador é SEMPRE o do usuário autenticado: `ponto_colaborador_config`
 * com `business_id` E `user_id` do próprio user. Nada do body escolhe de quem é a
 * marcação — antes `funcionario_id` vinha do request e caía em `$user->id` (id de
 * USUÁRIO gravado como `colaborador_config_id`).
 *
 * Tier 0 IRREVOGAVEL:
 *   - business_id deduzido do user autenticado, filtrado EXPLICITAMENTE em toda
 *     query ([ADR 0093]). Numa chamada Passport não há sessão, e o
 *     ScopeByBusiness só filtra com `session('user.business_id')` — então o
 *     global scope aqui NÃO protege nada; o `where` explícito é a defesa.
 *   - SEM BIOMETRIA ([ADR 0383]): nao recebe imagem facial. Dado biometrico e
 *     sensivel (LGPD Art. 5o, II; tratamento pelo Art. 11) e o anti-fraude nao
 *     depende dele (GPS + clock-skew + NSR).
 *   - anti-fraude é do MobileMarcacaoService — aqui só se expõe, não se reescreve.
 *   - response retorna apenas IDs + hash truncado (sem PII).
 *
 * Os métodos leem só `$request->user()`: servem o guard `api` hoje e o `web`
 * quando as telas Inertia do colaborador ganharem rota (não há
 * CreateFreshApiToken no app — sessão web não alcança `auth:api`).
 *
 * @see MobileMarcacaoService::registrarMarcacaoMobile
 * @see memory/decisions/0383-ponto-interno-nao-coleta-biometria.md
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
     * POST /ponto/api/marcar — `ponto.api.marcar`
     *
     * Body JSON:
     * {
     *   "tipo": "ENTRADA",
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
                'mensagem' => 'Token invalido ou ausente.',
            ], 401);
        }

        $businessId = (int) $user->business_id;
        $colab = $this->colaboradorDoUsuario($request);
        if (! $colab) {
            return $this->semColaborador();
        }

        // Validacao basica (Service faz validacao profunda anti-cheat)
        $validated = $request->validate([
            'tipo'             => 'required|string|in:ENTRADA,SAIDA,ALMOCO_INICIO,ALMOCO_FIM',
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
                (int) $colab->id,
                $payload
            );
        } catch (RuntimeException $e) {
            // Erros de anti-cheat / validacao: 422 (cliente corrige). Nao existe
            // "bater mesmo assim" — sinal ruim e recusa (ADR 0383, W5).
            return response()->json([
                'erro' => 'validacao_falhou',
                'mensagem' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            // Erros inesperados: 500 + log sem PII
            Log::error('ponto.mobile.marcacao.erro', [
                'business_id' => $businessId,
                'colaborador_config_id' => (int) $colab->id,
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
                // Geofence SINALIZA, nao recusa: a marcacao ja entrou.
                'revisar'      => $this->foraDoGeofence($marcacao, $businessId),
            ],
        ], 201);
    }

    /** GET /ponto/api/marcacoes/hoje — `ponto.api.marcacoes.hoje` */
    public function marcacoesHoje(Request $request): JsonResponse
    {
        $colab = $this->colaboradorDoUsuario($request);
        if (! $colab) {
            return $this->semColaborador();
        }
        $businessId = (int) $colab->business_id;

        $marcacoes = Marcacao::query()
            ->where('business_id', $businessId)
            ->where('colaborador_config_id', $colab->id)
            ->whereDate('momento', now()->toDateString())
            ->orderBy('momento')
            ->get();

        return response()->json([
            'data' => now()->toDateString(),
            'marcacoes' => $marcacoes->map(fn (Marcacao $m) => [
                'id'         => (string) $m->id,
                'nsr'        => (int) $m->nsr,
                'tipo'       => (string) $m->tipo,
                'origem'     => (string) $m->origem,
                'hora'       => $m->momento?->format('H:i'),
                'hash_trunc' => substr((string) $m->hash, 0, 16),
                'revisar'    => $this->foraDoGeofence($m, $businessId),
            ])->values(),
        ]);
    }

    /** GET /ponto/api/saldo — `ponto.api.saldo` (banco de horas, em minutos) */
    public function saldo(Request $request): JsonResponse
    {
        $colab = $this->colaboradorDoUsuario($request);
        if (! $colab) {
            return $this->semColaborador();
        }

        $saldo = BancoHorasSaldo::query()
            ->where('business_id', $colab->business_id)
            ->where('colaborador_config_id', $colab->id)
            ->first();

        return response()->json([
            'usa_banco_horas'     => (bool) $colab->usa_banco_horas,
            // Sem linha de saldo = nunca houve movimento: 0, e a data fica nula.
            'saldo_minutos'       => (int) ($saldo->saldo_minutos ?? 0),
            'ultima_movimentacao' => $saldo?->ultima_movimentacao?->toDateString(),
        ]);
    }

    /**
     * Fila do gestor: marcacoes mobile dos ultimos 7 dias. Sem rota nesta PR —
     * a fila entra como filtro na tela viva de Aprovações (thread 06, passo 3).
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

    // ========================================================================
    // Helpers
    // ========================================================================

    /** O colaborador do usuário autenticado, no empregador DELE, que controla ponto. */
    protected function colaboradorDoUsuario(Request $request): ?Colaborador
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        return Colaborador::query()
            ->where('business_id', (int) $user->business_id)
            ->where('user_id', (int) $user->id)
            ->where('controla_ponto', true)
            ->first();
    }

    protected function semColaborador(): JsonResponse
    {
        return response()->json([
            'erro' => 'sem_colaborador',
            'mensagem' => 'Seu usuario nao tem cadastro de ponto neste empregador.',
        ], 403);
    }

    /** Geofence é opt-in por empregador: sem coordenada ou sem config, não sinaliza. */
    protected function foraDoGeofence(Marcacao $m, int $businessId): bool
    {
        if ($m->latitude === null || $m->longitude === null) {
            return false;
        }

        return ! $this->service->validarGeolocation((float) $m->latitude, (float) $m->longitude, $businessId);
    }
}
