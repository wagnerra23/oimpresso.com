<?php

namespace Modules\Producao\Http\Controllers;

use App\Domain\Fsm\Exceptions\InvalidActionForCurrentStageException;
use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Producao\Entities\Apontamento;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\ApontamentoOperadorService;
use Modules\Producao\Services\ConsumoMaterialService;
use Modules\Producao\Services\RefugoService;

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
        ApontamentoRecusado::OS_NAO_LIBERADA => 409,
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
        // US-COMVIS-004: m² impresso e tinta C/M/Y/K são opcionais; o formato quem decide é o serviço.
        $campos = ['m2_impresso', 'tinta_ml_c', 'tinta_ml_m', 'tinta_ml_y', 'tinta_ml_k'];
        $d = $request->validate($this->regras() + array_fill_keys($campos, 'nullable|string|max:16'));

        return $this->escrever(fn () => $this->svc->concluir($biz, $user, $inicio, $d['chave'], Carbon::parse($d['momento']),
            array_intersect_key($d, array_flip($campos))));
    }

    /**
     * US-PCP-021 · UC-FLX-37: material consumido na etapa. Baixa o estoque do local da venda pelo lote
     * que saiu. O operador não vê o custo (é do gestor); a resposta diz só o que saiu e o que ficou sem lote.
     */
    public function material(Request $request, ConsumoMaterialService $consumo): JsonResponse
    {
        [$biz, $user] = $this->operador($request);
        $d = $request->validate($this->regras() + [
            'programacao_id' => 'required|integer|min:1', 'variation_id' => 'required|integer|min:1', 'quantidade' => 'required|string|max:24',
            'lote_id' => 'nullable|integer|min:1', // US-COM-025: rolo escolhido; sem ele, o método da empresa.
        ]);

        try {
            $c = $consumo->registrar($biz, $user, (int) $d['programacao_id'], (int) $d['variation_id'], (string) $d['quantidade'], $d['chave'], Carbon::parse($d['momento']),
                isset($d['lote_id']) ? (int) $d['lote_id'] : null);
        } catch (ApontamentoRecusado $e) {
            return $this->recusa($e);
        }

        return response()->json(['id' => $c->id, 'programacao_id' => $c->programacao_id, 'variation_id' => $c->variation_id,
            'quantidade' => (string) $c->quantidade, 'quantidade_sem_lote' => (string) $c->quantidade_sem_lote], $c->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * US-PCP-027 · UC-FLX-16: refugo com motivo na etapa (quantas peças, motivo de refugo do posto),
     * com material reposto (baixa pelo ConsumoMaterialService) e, se pedido, a OS para Retrabalho
     * (papel producao.qualidade no motor). A resposta não traz custo (o operador não vê custo).
     */
    public function refugo(Request $request, RefugoService $refugos): JsonResponse
    {
        [$biz, $user] = $this->operador($request);
        $d = $request->validate($this->regras() + [
            'programacao_id' => 'required|integer|min:1', 'motivo_id' => 'required|integer|min:1', 'quantidade' => 'required|string|max:24',
            'retrabalho' => 'sometimes|boolean', 'materiais' => 'sometimes|array|max:20',
            'materiais.*.variation_id' => 'required|integer|min:1', 'materiais.*.quantidade' => 'required|string|max:24',
        ]);

        try {
            $r = $refugos->registrar($biz, $user, (int) $d['programacao_id'], (int) $d['motivo_id'], (string) $d['quantidade'], $d['chave'],
                Carbon::parse($d['momento']), array_values($d['materiais'] ?? []), (bool) ($d['retrabalho'] ?? false));
        } catch (ApontamentoRecusado $e) {
            return $this->recusa($e);
        } catch (UnauthorizedActionException|InvalidActionForCurrentStageException $e) {
            return response()->json(['erro' => 'retrabalho_negado', 'mensagem' => 'Mandar para retrabalho é do controle de qualidade, com a OS em execução.', 'contexto' => []], 403);
        }

        return response()->json(['id' => $r->id, 'programacao_id' => $r->programacao_id, 'motivo' => $r->motivo, 'quantidade' => (string) $r->quantidade,
            'retrabalho_programacao_id' => $r->retrabalho_programacao_id], $r->wasRecentlyCreated ? 201 : 200);
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

    /** Motivos de pausa por posto (cadastro da thread 03, E04 A4): a mesma lista que o app lê. */
    private function motivos(int $biz): array
    {
        return $this->svc->motivosDePausa($biz)->all();
    }

    private function etapa(int $biz, Programacao $p): array
    {
        $posto = Posto::query()->where('business_id', $biz)->whereKey($p->posto_id)->first(['nome', 'tipo_custo']);

        return ['id' => $p->id, 'os_tipo' => $p->os_tipo, 'os_id' => $p->os_id, 'posto' => $posto?->nome, 'tipo_custo' => $posto?->tipo_custo,
            'inicio_previsto' => $p->inicio_previsto->format('H:i'), 'status' => $p->status];
    }
}
