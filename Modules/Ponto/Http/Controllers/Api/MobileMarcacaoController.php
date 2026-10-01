<?php

declare(strict_types=1);

namespace Modules\Ponto\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Ponto\Entities\BancoHorasSaldo;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Escala;
use Modules\Ponto\Entities\EscalaTurno;
use Modules\Ponto\Entities\Intercorrencia;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Http\Controllers\EspelhoController;
use Modules\Ponto\Http\Controllers\IntercorrenciaController;
use Modules\Ponto\Http\Requests\StoreIntercorrenciaRequest;
use Modules\Ponto\Services\IntercorrenciaService;
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
        return response()->json([
            'data' => now()->toDateString(),
            'marcacoes' => $this->listaHoje($colab),
        ]);
    }

    /**
     * GET /ponto/mobile — `ponto.mobile` (web). A tela do colaborador: Bater ponto,
     * Meu espelho e Justificar. Sem cadastro de ponto (ex.: o gestor abrindo a aba),
     * a tela mostra o estado vazio — `colaborador: null`, nada inventado.
     */
    public function tela(Request $request): InertiaResponse
    {
        $colab = $this->colaboradorDoUsuario($request)?->loadMissing('user');
        $podeVerModulo = \Modules\Ponto\Http\Middleware\CheckPontoAccess::permite($request->user());

        // Contagens das abas e linha de contexto do header: as outras telas recebem isso do
        // CheckPontoAccess, que esta rota não passa. Só pra quem vê o módulo — o colaborador
        // sem `ponto.access` não recebe número nenhum da empresa (pendências, total de
        // intercorrências, quantos colaboradores).
        if ($podeVerModulo) {
            $bizId = (int) (session('business.id') ?? $request->user()->business_id);
            \Modules\Ponto\Http\Middleware\CheckPontoAccess::compartilharCabecalho($bizId);
        }

        $espelho = app(EspelhoController::class);
        $ano = (int) now()->year;
        $mes = (int) now()->month;

        return Inertia::render('Ponto/Mobile/Index', [
            'colaborador' => $colab ? [
                'nome'      => trim(optional($colab->user)->first_name . ' ' . optional($colab->user)->last_name) ?: '—',
                'matricula' => $colab->matricula,
            ] : null,
            'marcacoes_hoje' => $colab ? $this->listaHoje($colab) : [],
            'hoje'   => now()->toDateString(),
            'mes'    => now()->format('Y-m'),
            // Mesmos builders do Espelho/Show (US-PONTO-012 já corrigida lá) — não recalcula.
            'totais' => Inertia::defer(fn () => $colab ? $espelho->buildTotaisEspelho((int) $colab->business_id, (int) $colab->id, $ano, $mes) : null),
            'linhas' => Inertia::defer(fn () => $colab ? $espelho->buildLinhasEspelho((int) $colab->business_id, (int) $colab->id, $ano, $mes) : []),
            'tipos'  => IntercorrenciaController::tiposDisponiveis(),
            // A rota fica fora do `ponto.access` ([W] 2026-09-29); o cabeçalho do módulo (abas de
            // RH) só aparece pra quem pode abrir o módulo — senão cada aba seria um 403.
            'pode_ver_modulo' => $podeVerModulo,
            'limites' => [
                'accuracy_max' => MobileMarcacaoService::GPS_ACCURACY_MAX_METROS,
                'drift_max'    => MobileMarcacaoService::TIMESTAMP_DRIFT_MAX_SEG,
            ],
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

    /** GET /ponto/api/intercorrencias — `ponto.api.intercorrencias.index` (só as minhas) */
    public function intercorrencias(Request $request): JsonResponse
    {
        $colab = $this->colaboradorDoUsuario($request);
        if (! $colab) {
            return $this->semColaborador();
        }

        $itens = Intercorrencia::query()
            ->where('business_id', $colab->business_id)
            ->where('colaborador_config_id', $colab->id)
            ->orderByDesc('data')
            ->limit(50)
            ->get();

        return response()->json([
            'intercorrencias' => $itens->map(fn (Intercorrencia $i) => $this->intercorrenciaResumo($i))->values(),
        ]);
    }

    /**
     * POST /ponto/api/intercorrencias — `ponto.api.intercorrencias.store`
     *
     * Justificar: cria a intercorrência e SUBMETE (RASCUNHO → PENDENTE) no mesmo
     * ato — o colaborador envia para o gestor, não guarda rascunho. Regras de
     * campo = as do StoreIntercorrenciaRequest (fonte única), menos o
     * `colaborador_config_id` (é o do user) e o anexo (o app não anexa).
     */
    public function criarIntercorrencia(Request $request, IntercorrenciaService $intercorrencias): JsonResponse
    {
        $colab = $this->colaboradorDoUsuario($request);
        if (! $colab) {
            return $this->semColaborador();
        }

        $regras = Arr::except((new StoreIntercorrenciaRequest())->rules(), ['colaborador_config_id', 'anexo']);
        $dados = $request->validate($regras, (new StoreIntercorrenciaRequest())->messages());
        $dados['business_id'] = (int) $colab->business_id;
        $dados['colaborador_config_id'] = (int) $colab->id;

        $i = DB::transaction(function () use ($intercorrencias, $dados, $request) {
            $i = $intercorrencias->criar($dados, (int) $request->user()->id);
            $intercorrencias->submeter($i);

            return $i->refresh();
        });

        return response()->json([
            'sucesso' => true,
            'intercorrencia' => $this->intercorrenciaResumo($i),
        ], 201);
    }

    /** GET /ponto/api/escala/hoje — `ponto.api.escala.hoje` */
    public function escalaHoje(Request $request): JsonResponse
    {
        $colab = $this->colaboradorDoUsuario($request);
        if (! $colab) {
            return $this->semColaborador();
        }

        $escala = $colab->escala_atual_id
            ? Escala::query()->where('business_id', $colab->business_id)->whereKey($colab->escala_atual_id)->first()
            : null;

        // `dia_semana` = Carbon::dayOfWeek (dom=0..sáb=6), mesma convenção do Dashboard.
        $turno = $escala
            ? EscalaTurno::query()->where('escala_id', $escala->id)->where('dia_semana', now()->dayOfWeek)->first()
            : null;

        return response()->json([
            'data'   => now()->toDateString(),
            'escala' => $escala ? ['id' => (int) $escala->id, 'nome' => (string) $escala->nome] : null,
            'turno'  => $turno ? [
                'hora_entrada'       => $turno->hora_entrada,
                'hora_almoco_inicio' => $turno->hora_almoco_inicio,
                'hora_almoco_fim'    => $turno->hora_almoco_fim,
                'hora_saida'         => $turno->hora_saida,
            ] : null,
        ]);
    }

    /**
     * GET /ponto/api/dashboard/kpis — `ponto.api.dashboard.kpis`
     *
     * KPIs DO COLABORADOR (não do empregador): a API é do app de bolso, e o
     * painel do gestor já existe na web com permissão própria.
     */
    public function dashboardKpis(Request $request): JsonResponse
    {
        $colab = $this->colaboradorDoUsuario($request);
        if (! $colab) {
            return $this->semColaborador();
        }

        return response()->json([
            'marcacoes_hoje' => Marcacao::query()
                ->where('business_id', $colab->business_id)
                ->where('colaborador_config_id', $colab->id)
                ->whereDate('momento', now()->toDateString())
                ->count(),
            'intercorrencias_pendentes' => Intercorrencia::query()
                ->where('business_id', $colab->business_id)
                ->where('colaborador_config_id', $colab->id)
                ->where('estado', Intercorrencia::ESTADO_PENDENTE)
                ->count(),
            'saldo_minutos' => (int) (BancoHorasSaldo::query()
                ->where('business_id', $colab->business_id)
                ->where('colaborador_config_id', $colab->id)
                ->value('saldo_minutos') ?? 0),
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

    /**
     * GET /ponto/api/me — `ponto.api.me`. Quem é o colaborador do token: o cabeçalho do Ponto
     * no app (nome + matrícula) e os limites que o app aplica antes de mandar a marcação.
     * Sem cadastro de ponto → 403 `sem_colaborador` (mesmo contrato das demais rotas).
     */
    public function me(Request $request): JsonResponse
    {
        $colab = $this->colaboradorDoUsuario($request)?->loadMissing('user');
        if (! $colab) {
            return $this->semColaborador();
        }

        $empresa = DB::table('business')->where('id', (int) $colab->business_id)->value('name');

        return response()->json([
            'nome'      => trim(optional($colab->user)->first_name . ' ' . optional($colab->user)->last_name) ?: '—',
            'matricula' => $colab->matricula,
            'empresa'   => (string) $empresa,
            'limites'   => [
                'accuracy_max' => MobileMarcacaoService::GPS_ACCURACY_MAX_METROS,
                'drift_max'    => MobileMarcacaoService::TIMESTAMP_DRIFT_MAX_SEG,
            ],
        ]);
    }

    /**
     * GET /ponto/api/espelho?mes=YYYY-MM — `ponto.api.espelho`. O "Meu espelho" do app: os
     * MESMOS builders do Espelho/Show e da tela web /ponto/mobile (não recalcula). Sem `mes`,
     * o mês corrente. Mês futuro ou malformado → 422.
     */
    public function espelho(Request $request): JsonResponse
    {
        $colab = $this->colaboradorDoUsuario($request);
        if (! $colab) {
            return $this->semColaborador();
        }

        $mes = (string) $request->query('mes', now()->format('Y-m'));
        if (! preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $mes, $m) || $mes > now()->format('Y-m')) {
            return response()->json([
                'erro' => 'mes_invalido',
                'mensagem' => 'Informe o mes no formato AAAA-MM, ate o mes atual.',
            ], 422);
        }

        $espelho = app(EspelhoController::class);
        $bizId = (int) $colab->business_id;

        return response()->json([
            'mes'    => $mes,
            'totais' => $espelho->buildTotaisEspelho($bizId, (int) $colab->id, (int) $m[1], (int) $m[2]),
            'linhas' => $espelho->buildLinhasEspelho($bizId, (int) $colab->id, (int) $m[1], (int) $m[2]),
        ]);
    }

    /** GET /ponto/api/intercorrencias/tipos — `ponto.api.intercorrencias.tipos` (os motivos aceitos). */
    public function tiposIntercorrencia(): JsonResponse
    {
        return response()->json(IntercorrenciaController::tiposDisponiveis());
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

    /** Marcações de hoje do colaborador (sem as anulações — o espelho também as omite). */
    protected function listaHoje(Colaborador $colab): array
    {
        $businessId = (int) $colab->business_id;

        return Marcacao::query()
            ->where('business_id', $businessId)
            ->where('colaborador_config_id', $colab->id)
            ->whereDate('momento', now()->toDateString())
            ->where('origem', '!=', Marcacao::ORIGEM_ANULACAO)
            ->orderBy('momento')
            ->get()
            ->map(fn (Marcacao $m) => [
                'id'         => (string) $m->id,
                'nsr'        => (int) $m->nsr,
                'tipo'       => (string) $m->tipo,
                'origem'     => (string) $m->origem,
                'hora'       => $m->momento?->format('H:i'),
                'hash_trunc' => substr((string) $m->hash, 0, 16),
                'revisar'    => $this->foraDoGeofence($m, $businessId),
            ])->values()->all();
    }

    /** Geofence é opt-in por empregador: sem coordenada ou sem config, não sinaliza. */
    protected function foraDoGeofence(Marcacao $m, int $businessId): bool
    {
        if ($m->latitude === null || $m->longitude === null) {
            return false;
        }

        return ! $this->service->validarGeolocation((float) $m->latitude, (float) $m->longitude, $businessId);
    }

    protected function intercorrenciaResumo(Intercorrencia $i): array
    {
        return [
            'id'               => (string) $i->id,
            'codigo'           => $i->codigo,
            'tipo'             => $i->tipo,
            'estado'           => $i->estado,
            'data'             => optional($i->data)->format('Y-m-d'),
            'dia_todo'         => (bool) $i->dia_todo,
            'intervalo_inicio' => $i->intervalo_inicio,
            'intervalo_fim'    => $i->intervalo_fim,
        ];
    }
}
