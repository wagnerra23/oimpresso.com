<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Domain\Oficina\TiposVeiculo;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Agenda de revisão da Oficina no app das lojas (decisão [W] 2026-10-06). Contrato:
 * memory/requisitos/AppMobile/api/oficina-agenda.md.
 *
 * Agendamento = veículo + cliente (sugerido pelo dono do veículo; o app pré-preenche) + dia e
 * hora + observação. "Abrir OS" no app chama POST /api/app/os com `agendamento_id`, que marca o
 * agendamento `atendido` e grava os_id na mesma transação (OficinaController@store).
 *
 * Padrões do gerente da fila (o [W] pode mudar): dois no mesmo horário são permitidos (mais de um
 * box); início em dia passado é 422 (hoje vale); permissões = as de criar OS. Listar = ver OS.
 *
 * Tier 0 (ADR 0093): business_id do usuário do token em toda consulta, explícito — o global scope
 * do model lê a sessão, que a chamada com token não tem. Sem valor, estoque nem cobrança.
 */
class AgendamentoController extends Controller
{
    /** Janela padrão do GET sem `ate`, em dias a partir de `de`. */
    private const JANELA_PADRAO = 30;

    /** Maior janela aceita no GET, em dias. */
    private const JANELA_MAX = 92;

    public function __construct(private OficinaController $oficina)
    {
    }

    /** GET /api/app/agendamentos?de=AAAA-MM-DD&ate=AAAA-MM-DD */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->oficina->podeVerOficina($user)) {
            return $this->semPermissao();
        }

        $v = Validator::make($request->query(), [
            'de' => ['nullable', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'de.date_format' => 'Use a data no formato AAAA-MM-DD.',
            'ate.date_format' => 'Use a data no formato AAAA-MM-DD.',
        ]);
        if ($v->fails()) {
            return $this->invalido(collect($v->errors()->toArray())->map(fn ($m) => $m[0])->all());
        }

        $de = $request->filled('de') ? Carbon::createFromFormat('Y-m-d', (string) $request->query('de'))->startOfDay()
            : now()->startOfDay();
        $ate = $request->filled('ate') ? Carbon::createFromFormat('Y-m-d', (string) $request->query('ate'))->startOfDay()
            : $de->copy()->addDays(self::JANELA_PADRAO);
        if ($ate->lt($de)) {
            return $this->invalido(['ate' => 'A data final é anterior à inicial.']);
        }
        if ($de->diffInDays($ate) > self::JANELA_MAX) {
            return $this->invalido(['ate' => 'Período máximo de ' . self::JANELA_MAX . ' dias.']);
        }

        $bizId = (int) $user->business_id;
        $linhas = $this->consulta($bizId)
            ->where('a.inicio', '>=', $de->toDateTimeString())
            ->where('a.inicio', '<', $ate->copy()->addDay()->toDateTimeString())
            ->orderBy('a.inicio')->orderBy('a.id')
            ->get($this->colunas());

        return response()->json([
            'itens' => $linhas->map(fn ($l) => $this->item($l))->values()->all(),
            'pode_criar' => $this->oficina->podeCriarOs($user),
        ]);
    }

    /** POST /api/app/agendamentos {vehicle_id, contact_id|null, inicio, observacao|null} */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->oficina->podeVerOficina($user) || ! $this->oficina->podeCriarOs($user)) {
            return $this->semPermissao();
        }

        $bizId = (int) $user->business_id;
        $v = Validator::make($request->all(), [
            'vehicle_id' => ['required', 'integer'],
            'contact_id' => ['nullable', 'integer'],
            'inicio' => ['required', 'date_format:Y-m-d\TH:i'],
            'observacao' => ['nullable', 'string', 'max:500'],
        ], [
            'vehicle_id.required' => 'Escolha o veículo.',
            'inicio.required' => 'Informe o dia e a hora.',
            'inicio.date_format' => 'Use o formato AAAA-MM-DDTHH:MM.',
            'observacao.max' => 'A observação tem no máximo 500 caracteres.',
        ]);
        $campos = $v->fails() ? collect($v->errors()->toArray())->map(fn ($m) => $m[0])->all() : [];

        // Tier 0: veículo e cliente só do business do token. Outra empresa = "não encontrado".
        if (! isset($campos['vehicle_id']) && ! DB::table('vehicles')->where('business_id', $bizId)
            ->whereNull('deleted_at')->where('id', (int) $request->input('vehicle_id'))->exists()) {
            $campos['vehicle_id'] = 'Veículo não encontrado.';
        }
        if (! isset($campos['contact_id']) && $request->filled('contact_id') && ! DB::table('contacts')
            ->where('business_id', $bizId)->where('id', (int) $request->input('contact_id'))->exists()) {
            $campos['contact_id'] = 'Cliente não encontrado.';
        }
        $inicio = null;
        if (! isset($campos['inicio'])) {
            $inicio = Carbon::createFromFormat('Y-m-d\TH:i', (string) $request->input('inicio'))->startOfMinute();
            // Dia passado é recusado; o próprio dia vale (encaixe de última hora).
            if ($inicio->copy()->startOfDay()->lt(now()->startOfDay())) {
                $campos['inicio'] = 'O agendamento não pode ser num dia que já passou.';
            }
        }
        if ($campos !== []) {
            return $this->invalido($campos);
        }

        $obs = $request->input('observacao');
        $id = DB::table('oficina_agendamentos')->insertGetId([
            'business_id' => $bizId,
            'vehicle_id' => (int) $request->input('vehicle_id'),
            'contact_id' => $request->filled('contact_id') ? (int) $request->input('contact_id') : null,
            'inicio' => $inicio->toDateTimeString(),
            'observacao' => is_string($obs) && trim($obs) !== '' ? trim($obs) : null,
            'status' => 'agendado',
            'created_by' => (int) $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->resposta($bizId, $id, 201);
    }

    /** POST /api/app/agendamentos/{id}/cancelar {motivo|null} */
    public function cancelar(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $this->oficina->podeVerOficina($user) || ! $this->oficina->podeCriarOs($user)) {
            return $this->semPermissao();
        }

        $bizId = (int) $user->business_id;
        $atual = DB::table('oficina_agendamentos')->where('business_id', $bizId)->where('id', $id)->first(['status']);
        if ($atual === null) {
            return $this->naoEncontrado();
        }

        $v = Validator::make($request->all(), ['motivo' => ['nullable', 'string', 'max:500']], [
            'motivo.max' => 'O motivo tem no máximo 500 caracteres.',
        ]);
        if ($v->fails()) {
            return $this->invalido(collect($v->errors()->toArray())->map(fn ($m) => $m[0])->all());
        }
        if ($atual->status !== 'agendado') {
            return response()->json([
                'erro' => 'estado_invalido',
                'mensagem' => $atual->status === 'atendido'
                    ? 'Este agendamento já virou OS.' : 'Este agendamento já foi cancelado.',
            ], 422);
        }

        $motivo = $request->input('motivo');
        DB::table('oficina_agendamentos')->where('business_id', $bizId)->where('id', $id)
            ->where('status', 'agendado')
            ->update([
                'status' => 'cancelado',
                'motivo_cancelamento' => is_string($motivo) && trim($motivo) !== '' ? trim($motivo) : null,
                'updated_at' => now(),
            ]);

        return $this->resposta($bizId, $id, 200);
    }

    private function consulta(int $bizId): \Illuminate\Database\Query\Builder
    {
        return DB::table('oficina_agendamentos as a')
            ->join('vehicles as v', function ($j) {
                $j->on('v.id', '=', 'a.vehicle_id')->on('v.business_id', '=', 'a.business_id');
            })
            ->leftJoin('contacts as c', function ($j) {
                $j->on('c.id', '=', 'a.contact_id')->on('c.business_id', '=', 'a.business_id');
            })
            ->where('a.business_id', $bizId);
    }

    /** @return list<string> */
    private function colunas(): array
    {
        return [
            'a.id', 'a.inicio', 'a.observacao', 'a.status', 'a.os_id',
            'v.id as veiculo_id', 'v.plate', 'v.vehicle_type', 'c.id as cliente_id', 'c.name as cliente',
        ];
    }

    /** @return array<string, mixed> */
    private function item(object $l): array
    {
        return [
            'id' => (int) $l->id,
            'inicio' => Carbon::parse($l->inicio)->format('Y-m-d\TH:i'),
            'veiculo' => [
                'id' => (int) $l->veiculo_id,
                'placa' => (string) $l->plate,
                // Mesma `descricao` do GET /api/app/veiculos (rótulo do tipo).
                'descricao' => $l->vehicle_type === null ? null : (TiposVeiculo::ROTULOS[$l->vehicle_type] ?? null),
            ],
            'cliente' => $l->cliente_id !== null ? ['id' => (int) $l->cliente_id, 'nome' => (string) $l->cliente] : null,
            'observacao' => $l->observacao,
            'status' => (string) $l->status,
            'os_id' => $l->os_id !== null ? (int) $l->os_id : null,
        ];
    }

    private function resposta(int $bizId, int $id, int $status): JsonResponse
    {
        $l = $this->consulta($bizId)->where('a.id', $id)->first($this->colunas());

        return $l === null ? $this->naoEncontrado() : response()->json($this->item($l), $status);
    }

    /** @param  array<string, string>  $campos */
    private function invalido(array $campos): JsonResponse
    {
        return response()->json(['erro' => 'validacao', 'campos' => $campos], 422);
    }

    private function naoEncontrado(): JsonResponse
    {
        return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'Agendamento não encontrado.'], 404);
    }

    private function semPermissao(): JsonResponse
    {
        return response()->json([
            'erro' => 'sem_permissao',
            'mensagem' => 'Seu usuário não tem acesso à oficina.',
        ], 403);
    }
}
