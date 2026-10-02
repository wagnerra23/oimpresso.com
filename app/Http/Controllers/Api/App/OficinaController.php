<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\OficinaAuto\Http\Controllers\VehicleController;

/**
 * Oficina do app das lojas (oimpresso-app) — SÓ LEITURA. Contrato:
 * memory/requisitos/AppMobile/API-CONTRATO-v1.md §11 (Onda D).
 *
 * Mesmo universo da tela web /oficina-auto/ordens-servico (ServiceOrderController::board):
 * OS no processo FSM `oficina_mecanica_os` em etapa NÃO-terminal, ou OS de mecânica ainda sem
 * pipeline (contam na etapa inicial). Terminais (entregue, cancelado, garantia) não entram.
 * Valor = soma dos itens da OS (peças + mão de obra), como o card web.
 *
 * Tier 0 (ADR 0093): business_id do usuário do token em toda consulta, explícito — o global
 * scope do ServiceOrder lê a SESSÃO, que não existe numa chamada com token.
 */
class OficinaController extends Controller
{
    public const PROCESSO = 'oficina_mecanica_os';

    /** Etapas em que a OS espera alguém de fora (cliente aprovar, peça chegar). */
    public const TRAVADAS = ['aguardando_aprovacao', 'aguardando_pecas'];

    private const POR_PAGINA = 20;

    public function __construct(private ModuleUtil $moduleUtil)
    {
    }

    /** GET /api/app/os?etapa=<chave|todas>&pagina=N — tela 07. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVerOficina($user)) {
            return $this->semPermissao();
        }

        $bizId = (int) $user->business_id;
        $etapas = $this->etapas($bizId);
        $pagina = max((int) $request->query('pagina', 1), 1);
        $etapa = (string) $request->query('etapa', 'todas');
        if ($etapa !== 'todas' && ! $etapas->contains('key', $etapa)) {
            $etapa = 'todas';
        }

        if ($etapas->isEmpty()) {
            return response()->json([
                'itens' => [], 'etapas' => [], 'total' => 0, 'travadas' => 0,
                'pagina' => $pagina, 'tem_mais' => false,
            ]);
        }

        $inicial = $etapas->first();
        $porId = $etapas->keyBy('id');

        $linhas = $this->filtrarEtapa($this->base($bizId, $etapas), $etapa, $etapas)
            ->orderByRaw('COALESCE(sps.sort_order, ?) DESC', [(int) $inicial->sort_order])
            ->orderByDesc('so.id')
            ->offset(($pagina - 1) * self::POR_PAGINA)
            ->limit(self::POR_PAGINA + 1)
            ->get([
                'so.id', 'so.current_stage_id', 'v.plate', 'v.vehicle_type', 'c.name as cliente',
                DB::raw('(SELECT SUM(i.valor_total) FROM oficina_service_order_items i'
                    . ' WHERE i.service_order_id = so.id AND i.business_id = so.business_id'
                    . ' AND i.deleted_at IS NULL) as valor'),
            ]);

        // Contagem por etapa (OS sem pipeline contam na inicial, como no quadro web).
        $contagem = $this->base($bizId, $etapas)
            ->groupBy('so.current_stage_id')
            ->selectRaw('so.current_stage_id, COUNT(*) as n')
            ->pluck('n', 'current_stage_id');
        $totais = [];
        foreach ($contagem as $stageId => $n) {
            $chave = $stageId === '' || $stageId === null ? $inicial->key : ($porId[(int) $stageId]->key ?? null);
            if ($chave !== null) {
                $totais[$chave] = ($totais[$chave] ?? 0) + (int) $n;
            }
        }

        $total = array_sum($totais);
        $travadas = array_sum(array_intersect_key($totais, array_flip(self::TRAVADAS)));

        return response()->json([
            'itens' => $linhas->take(self::POR_PAGINA)->map(fn ($l) => $this->resumo($l, $etapas))->values(),
            'etapas' => $etapas->map(fn ($s) => [
                'chave' => $s->key,
                'rotulo' => $s->name,
                'total' => $totais[$s->key] ?? 0,
            ])->values(),
            'total' => $total,
            'travadas' => $travadas,
            'pagina' => $pagina,
            'tem_mais' => $linhas->count() > self::POR_PAGINA,
        ]);
    }

    /**
     * Mesma regra do menu web da Oficina (DataController::modifyAdminMenu): módulo no pacote do
     * business (superadmin: módulo instalado) + permissão de ver OS. Usado também pela área
     * `oficina` do /api/app/inicio.
     */
    public function podeVerOficina(?User $user): bool
    {
        if ($user === null) {
            return false;
        }
        $superadmin = $user->can('superadmin');
        $habilitado = $superadmin
            ? $this->moduleUtil->isModuleInstalled('OficinaAuto')
            : (bool) $this->moduleUtil->hasThePermissionInSubscription(
                (int) $user->business_id,
                'oficina_auto_module',
                'superadmin_package'
            );

        return $habilitado && ($superadmin || $user->can('oficinaauto.service_order.view'));
    }

    /** Etapas NÃO-terminais do processo da oficina no business, na ordem do ERP. */
    private function etapas(int $bizId): Collection
    {
        return DB::table('sale_process_stages as s')
            ->join('sale_processes as p', 'p.id', '=', 's.process_id')
            ->where('p.business_id', $bizId)
            ->where('p.key', self::PROCESSO)
            ->where('s.is_terminal', false)
            ->orderBy('s.sort_order')
            ->orderBy('s.id')
            ->get(['s.id', 's.key', 's.name', 's.sort_order']);
    }

    /** OS ativas do business (universo do quadro web). */
    private function base(int $bizId, Collection $etapas): \Illuminate\Database\Query\Builder
    {
        $ids = $etapas->pluck('id')->all();

        return DB::table('service_orders as so')
            ->leftJoin('sale_process_stages as sps', 'sps.id', '=', 'so.current_stage_id')
            ->leftJoin('vehicles as v', function ($j) {
                $j->on('v.id', '=', 'so.vehicle_id')->on('v.business_id', '=', 'so.business_id');
            })
            ->leftJoin('contacts as c', function ($j) {
                $j->on('c.id', '=', 'so.contact_id')->on('c.business_id', '=', 'so.business_id');
            })
            ->where('so.business_id', $bizId)
            ->whereNull('so.deleted_at')
            ->where(function ($w) use ($ids) {
                $w->whereIn('so.current_stage_id', $ids)
                    ->orWhere(fn ($w2) => $w2->where('so.order_type', 'mecanica')->whereNull('so.current_stage_id'));
            });
    }

    private function filtrarEtapa($q, string $etapa, Collection $etapas)
    {
        if ($etapa === 'todas') {
            return $q;
        }
        $s = $etapas->firstWhere('key', $etapa);
        $inicial = $etapas->first();

        return $q->where(function ($w) use ($s, $inicial) {
            $w->where('so.current_stage_id', $s->id);
            if ($s->id === $inicial->id) {
                $w->orWhereNull('so.current_stage_id');
            }
        });
    }

    /** @return array<string, mixed> */
    private function resumo(object $l, Collection $etapas): array
    {
        $idx = $l->current_stage_id === null
            ? 0
            : (int) $etapas->search(fn ($s) => (int) $s->id === (int) $l->current_stage_id);
        $s = $etapas[$idx];

        return [
            'id' => (int) $l->id,
            'numero' => 'OS-' . str_pad((string) $l->id, 5, '0', STR_PAD_LEFT),
            'placa' => $l->plate ?: null,
            'veiculo' => $this->tipoVeiculo($l->vehicle_type),
            'cliente' => $l->cliente,
            'valor' => $l->valor === null ? null : round((float) $l->valor, 2),
            'etapa' => [
                'chave' => $s->key,
                'rotulo' => $s->name,
                'indice' => $idx + 1,
                'total_etapas' => $etapas->count(),
            ],
            'travada' => in_array($s->key, self::TRAVADAS, true),
        ];
    }

    /** Rótulo do tipo de veículo como a web mostra; tipo fora da lista da web → null. */
    private function tipoVeiculo(?string $tipo): ?string
    {
        return $tipo === null ? null : (VehicleController::vehicleTypes()[$tipo] ?? null);
    }

    private function semPermissao(): JsonResponse
    {
        return response()->json([
            'erro' => 'sem_permissao',
            'mensagem' => 'Seu usuário não tem acesso à oficina.',
        ], 403);
    }
}
