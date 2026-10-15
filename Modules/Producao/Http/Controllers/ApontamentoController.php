<?php

namespace Modules\Producao\Http\Controllers;

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Producao\Entities\Apontamento;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\ApontamentoOperadorService;

/**
 * Celular do operador (PWA web) — thread 04 + E04 do playbook PCP. A regra mora no
 * `ApontamentoOperadorService`; aqui só se lê a sessão, valida o corpo e traduz a recusa.
 * A API do app com token é outro consumidor do mesmo Service (PR 06), com controller próprio.
 *
 * As escritas são JSON (não visitas Inertia) porque a fila offline do aparelho reenvia o mesmo
 * corpo com a mesma `chave` até ter resposta (UC-PCV-23).
 */
class ApontamentoController extends Controller
{
    private const HTTP = [
        ApontamentoRecusado::NAO_ENCONTRADO => 404,
        ApontamentoRecusado::ETAPA_ALHEIA => 409,
        ApontamentoRecusado::PESSOA_OCUPADA => 409,
    ];

    public function __construct(private readonly ApontamentoOperadorService $svc, private readonly ModuleUtil $moduleUtil) {}

    public function index(Request $request): Response
    {
        [$biz, $user] = $this->operador($request);

        return Inertia::render('Producao/Apontamento/Index', [
            'operador' => ['id' => $user, 'nome' => (string) User::query()->where('business_id', $biz)->whereKey($user)->value('first_name')],
            'etapas' => Inertia::defer(fn () => $this->etapas($biz, $user)),
            'rodando' => Inertia::defer(fn () => $this->rodando($biz, $user)),
            'motivos' => Inertia::defer(fn () => $this->motivos($biz)),
        ]);
    }

    public function qr(Request $request): JsonResponse
    {
        [$biz, $user] = $this->operador($request);
        $d = $request->validate(['os_tipo' => 'required|string|max:40', 'os_id' => 'required|integer|min:1', 'programacao_id' => 'nullable|integer|min:1']);

        return $this->responder(function () use ($biz, $user, $d) {
            $r = $this->svc->resolverQr($biz, $user, $d['os_tipo'], (int) $d['os_id'], $d['programacao_id'] ?? null);
            $nome = $r['programacao']->user_id ? User::query()->where('business_id', $biz)->whereKey($r['programacao']->user_id)->value('first_name') : null;

            return ['pode_apontar' => $r['pode_apontar'], 'etapa' => $this->etapa($biz, $r['programacao']), 'responsavel' => $nome];
        });
    }

    public function iniciar(Request $request): JsonResponse
    {
        [$biz, $user] = $this->operador($request);
        $d = $request->validate($this->regras() + ['programacao_id' => 'required|integer|min:1']);

        return $this->escrever(fn () => $this->svc->iniciar($biz, $user, (int) $d['programacao_id'], $d['chave'], Carbon::parse($d['momento'])));
    }

    public function pausar(Request $request, int $inicio): JsonResponse
    {
        [$biz, $user] = $this->operador($request);
        $d = $request->validate($this->regras() + ['motivo_id' => 'required|integer|min:1']);

        return $this->escrever(fn () => $this->svc->pausar($biz, $user, $inicio, (int) $d['motivo_id'], $d['chave'], Carbon::parse($d['momento'])));
    }

    public function concluir(Request $request, int $inicio): JsonResponse
    {
        [$biz, $user] = $this->operador($request);
        $d = $request->validate($this->regras());

        return $this->escrever(fn () => $this->svc->concluir($biz, $user, $inicio, $d['chave'], Carbon::parse($d['momento'])));
    }

    public function corrigir(Request $request, int $inicio): JsonResponse
    {
        [$biz, $user] = $this->operador($request);
        $d = $request->validate($this->regras() + ['justificativa' => 'required|string|min:5|max:500']);

        return $this->responder(fn () => ['evento_id' => $this->svc->lancarCorrecao($biz, $user, $inicio, Carbon::parse($d['momento']), $d['justificativa'], $d['chave'])->id]);
    }

    /** @return array{0: int, 1: int} */
    private function operador(Request $request): array
    {
        $biz = (int) $request->session()->get('user.business_id');
        $pode = $request->user()?->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($biz, 'producao_module');
        abort_unless($biz > 0 && $pode && $request->user()->can('producao.access'), 403);

        return [$biz, (int) $request->user()->id];
    }

    private function regras(): array
    {
        return ['chave' => 'required|string|max:64', 'momento' => 'required|date'];
    }

    private function escrever(callable $f): JsonResponse
    {
        try {
            /** @var Apontamento $ap */
            $ap = $f();
        } catch (ApontamentoRecusado $e) {
            return $this->recusa($e);
        }

        return response()->json(['id' => $ap->id, 'tipo' => $ap->tipo, 'momento' => $ap->momento->toIso8601String()],
            $ap->wasRecentlyCreated ? 201 : 200);
    }

    private function responder(callable $f): JsonResponse
    {
        try {
            return response()->json($f());
        } catch (ApontamentoRecusado $e) {
            return $this->recusa($e);
        }
    }

    private function recusa(ApontamentoRecusado $e): JsonResponse
    {
        $status = self::HTTP[$e->codigo] ?? 422;
        $contexto = $e->codigo === ApontamentoRecusado::NAO_ENCONTRADO ? [] : $e->contexto;

        return response()->json(['erro' => $e->codigo, 'mensagem' => $e->getMessage(), 'contexto' => $contexto], $status);
    }

    private function etapas(int $biz, int $user): array
    {
        return $this->svc->minhasEtapasDeHoje($biz, $user, Carbon::now())->map(fn (Programacao $p) => $this->etapa($biz, $p))->all();
    }

    private function rodando(int $biz, int $user): array
    {
        $r = $this->svc->rodandoAgora($biz, $user, Carbon::now());
        $linha = fn (Apontamento $a) => ['id' => $a->id, 'programacao_id' => $a->programacao_id, 'os_tipo' => $a->os_tipo, 'os_id' => $a->os_id,
            'posto_id' => $a->posto_id, 'posto' => Posto::query()->where('business_id', $biz)->whereKey($a->posto_id)->value('nome'), 'tipo_custo' => $a->tipo_custo,
            'desde' => $a->momento->toIso8601String()];

        return [
            'pessoa' => $r['pessoa'] ? $linha($r['pessoa']) : null,
            'maquinas' => $r['maquinas']->map($linha)->all(),
            'ponto' => $r['ponto'] ? ['entrada' => $r['ponto']['entrada']?->format('H:i'), 'saida' => $r['ponto']['saida']?->format('H:i')] : null,
        ];
    }

    /** Motivos de pausa por posto (cadastro da thread 03, E04 A4). Antes dela, lista vazia. */
    private function motivos(int $biz): array
    {
        if (! Schema::hasTable('pcp_motivos')) {
            return [];
        }

        return DB::table('pcp_motivos')->where('business_id', $biz)->where('tipo', 'pausa')->where('ativo', true)
            ->orderBy('ordem')->orderBy('nome')->get(['id', 'posto_id', 'nome'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'posto_id' => (int) $m->posto_id, 'nome' => $m->nome])->all();
    }

    private function etapa(int $biz, Programacao $p): array
    {
        $posto = Posto::query()->where('business_id', $biz)->whereKey($p->posto_id)->first(['nome', 'tipo_custo']);

        return ['id' => $p->id, 'os_tipo' => $p->os_tipo, 'os_id' => $p->os_id, 'posto' => $posto?->nome, 'tipo_custo' => $posto?->tipo_custo,
            'inicio_previsto' => $p->inicio_previsto->format('H:i'), 'status' => $p->status];
    }
}
