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
     * GET /api/app/os/{id} — tela 03 (detalhe). Qualquer OS do business (inclusive terminal e
     * fora do pipeline, abertas pelo histórico do veículo). Outra empresa ou inexistente → 404.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVerOficina($user)) {
            return $this->semPermissao();
        }

        $bizId = (int) $user->business_id;
        $os = DB::table('service_orders as so')
            ->leftJoin('vehicles as v', function ($j) {
                $j->on('v.id', '=', 'so.vehicle_id')->on('v.business_id', '=', 'so.business_id');
            })
            ->leftJoin('contacts as c', function ($j) {
                $j->on('c.id', '=', 'so.contact_id')->on('c.business_id', '=', 'so.business_id');
            })
            ->where('so.business_id', $bizId)
            ->whereNull('so.deleted_at')
            ->where('so.id', $id)
            ->first([
                'so.id', 'so.order_type', 'so.current_stage_id', 'so.box_label', 'so.notes',
                'so.mileage_at_service', 'v.plate', 'v.vehicle_type', 'c.id as cliente_id', 'c.name as cliente',
            ]);

        if (! $os) {
            return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'OS não encontrada.'], 404);
        }

        $etapa = $this->etapaDetalhe($bizId, $os);

        $itens = DB::table('oficina_service_order_items')
            ->where('business_id', $bizId)
            ->where('service_order_id', $id)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['tipo', 'descricao', 'quantidade', 'valor_unitario', 'valor_total']);

        $soma = fn (string $tipo) => round((float) $itens->where('tipo', $tipo)->sum('valor_total'), 2);

        $vistoria = DB::table('oa_inspection_items')
            ->where('business_id', $bizId)
            ->where('service_order_id', $id)
            ->whereNull('deleted_at')
            ->groupBy('severity')
            ->selectRaw('severity, COUNT(*) as n')
            ->pluck('n', 'severity');

        $fotos = DB::table('arquivos')
            ->where('business_id', $bizId)
            ->where('arquivable_type', \Modules\OficinaAuto\Entities\ServiceOrder::class)
            ->where('arquivable_id', $id)
            ->whereNull('deleted_at')
            ->count();

        return response()->json([
            'id' => (int) $os->id,
            'numero' => 'OS-' . str_pad((string) $os->id, 5, '0', STR_PAD_LEFT),
            'local' => is_string($os->box_label) && trim($os->box_label) !== '' ? $os->box_label : null,
            'etapa' => $etapa,
            'travada' => $etapa !== null && in_array($etapa['chave'], self::TRAVADAS, true),
            'veiculo' => $os->plate === null ? null : [
                'placa' => $os->plate,
                'descricao' => $this->tipoVeiculo($os->vehicle_type),
                'km' => $os->mileage_at_service !== null ? (int) $os->mileage_at_service : null,
            ],
            'cliente' => $os->cliente_id === null ? null : ['id' => (int) $os->cliente_id, 'nome' => (string) $os->cliente],
            'observacoes' => is_string($os->notes) && trim($os->notes) !== '' ? $os->notes : null,
            'vistoria' => [
                'ok' => (int) ($vistoria['ok'] ?? 0),
                'atencao' => (int) ($vistoria['atencao'] ?? 0),
                'critico' => (int) ($vistoria['critico'] ?? 0),
            ],
            'itens' => $itens->map(fn ($i) => [
                'tipo' => $i->tipo,
                'descricao' => (string) $i->descricao,
                'quantidade' => (float) $i->quantidade,
                'valor_unitario' => round((float) $i->valor_unitario, 2),
                'valor' => round((float) $i->valor_total, 2),
            ])->values(),
            'totais' => [
                'pecas' => $soma('peca'),
                'mao_de_obra' => $soma('mao_obra'),
                'terceiros' => $soma('servico_terceiro'),
                'total' => round((float) $itens->sum('valor_total'), 2),
            ],
            'fotos_laudo' => $fotos,
        ]);
    }

    /**
     * Etapa da OS no processo da oficina. Não-terminal → índice na lista da 07; terminal →
     * `indice: null, terminal: true`; OS de mecânica sem pipeline → etapa inicial; fora do
     * processo da oficina (ex.: as importadas sem pipeline) → null.
     *
     * @return array<string, mixed>|null
     */
    private function etapaDetalhe(int $bizId, object $os): ?array
    {
        $etapas = $this->etapas($bizId);
        if ($etapas->isEmpty()) {
            return null;
        }
        if ($os->current_stage_id === null) {
            if ($os->order_type !== 'mecanica') {
                return null;
            }
            $s = $etapas->first();

            return ['chave' => $s->key, 'rotulo' => $s->name, 'indice' => 1, 'total_etapas' => $etapas->count(), 'terminal' => false];
        }

        $idx = $etapas->search(fn ($s) => (int) $s->id === (int) $os->current_stage_id);
        if ($idx !== false) {
            $s = $etapas[$idx];

            return ['chave' => $s->key, 'rotulo' => $s->name, 'indice' => $idx + 1, 'total_etapas' => $etapas->count(), 'terminal' => false];
        }

        $terminal = DB::table('sale_process_stages as s')
            ->join('sale_processes as p', 'p.id', '=', 's.process_id')
            ->where('p.business_id', $bizId)
            ->where('p.key', self::PROCESSO)
            ->where('s.id', (int) $os->current_stage_id)
            ->first(['s.key', 's.name']);

        return $terminal === null ? null : [
            'chave' => $terminal->key, 'rotulo' => $terminal->name, 'indice' => null,
            'total_etapas' => $etapas->count(), 'terminal' => true,
        ];
    }

    /**
     * GET /api/app/veiculos?q=&pagina=N — tela 08. Veículos de cliente do business (tabela
     * `vehicles`); permissão da tela web de veículos. Busca por placa (principal e reboque),
     * rótulo do tipo e nome do dono. `km` = maior km conhecido (cadastro ou OS do veículo).
     */
    public function veiculos(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVerVeiculos($user)) {
            return $this->semPermissao();
        }

        $bizId = (int) $user->business_id;
        $pagina = max((int) $request->query('pagina', 1), 1);
        $busca = trim((string) $request->query('q', ''));

        $q = DB::table('vehicles as v')
            ->leftJoin('contacts as c', function ($j) {
                $j->on('c.id', '=', 'v.contact_id')->on('c.business_id', '=', 'v.business_id');
            })
            ->where('v.business_id', $bizId)
            ->whereNull('v.deleted_at');

        if ($busca !== '') {
            $like = '%' . $busca . '%';
            $tipos = array_keys(array_filter(
                VehicleController::vehicleTypes(),
                fn ($rotulo) => mb_stripos($rotulo, $busca) !== false
            ));
            $q->where(function ($w) use ($like, $tipos) {
                $w->where('v.plate', 'like', $like)
                    ->orWhere('v.secondary_plate', 'like', $like)
                    ->orWhere('c.name', 'like', $like);
                if ($tipos !== []) {
                    $w->orWhereIn('v.vehicle_type', $tipos);
                }
            });
        }

        $total = (clone $q)->count();
        $linhas = $q->orderBy('v.plate')
            ->orderBy('v.id')
            ->offset(($pagina - 1) * self::POR_PAGINA)
            ->limit(self::POR_PAGINA + 1)
            ->get([
                'v.id', 'v.plate', 'v.secondary_plate', 'v.vehicle_type', 'v.color',
                'v.manufacture_year', 'v.model_year', 'c.name as cliente',
                DB::raw('GREATEST(COALESCE(v.mileage_at_entry, 0), COALESCE((SELECT MAX(so.mileage_at_service)'
                    . ' FROM service_orders so WHERE so.vehicle_id = v.id AND so.business_id = v.business_id'
                    . ' AND so.deleted_at IS NULL), 0)) as km'),
            ]);

        return response()->json([
            'itens' => $linhas->take(self::POR_PAGINA)->map(fn ($v) => [
                'id' => (int) $v->id,
                'placa' => (string) $v->plate,
                'placa_secundaria' => $this->texto($v->secondary_plate),
                'descricao' => $this->tipoVeiculo($v->vehicle_type),
                'ano' => $this->ano($v->manufacture_year, $v->model_year),
                'cliente' => $v->cliente,
                'km' => (int) $v->km > 0 ? (int) $v->km : null,
                'cor' => $this->texto($v->color),
            ])->values(),
            'total' => $total,
            'pagina' => $pagina,
            'tem_mais' => $linhas->count() > self::POR_PAGINA,
        ]);
    }

    /**
     * GET /api/app/veiculos/{id}/os — histórico da 08. TODAS as OS do veículo (terminais e fora do
     * pipeline inclusive), mais nova primeiro. Pede ver veículo E ver OS. Veículo de outra empresa
     * ou inexistente → 404.
     */
    public function historicoVeiculo(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVerVeiculos($user) || ! $this->podeVerOficina($user)) {
            return $this->semPermissao();
        }

        $bizId = (int) $user->business_id;
        $existe = DB::table('vehicles')->where('business_id', $bizId)->where('id', $id)->whereNull('deleted_at')->exists();
        if (! $existe) {
            return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'Veículo não encontrado.'], 404);
        }

        $linhas = DB::table('service_orders as so')
            ->leftJoin('sale_process_stages as s', 's.id', '=', 'so.current_stage_id')
            ->leftJoin('sale_processes as p', function ($j) {
                $j->on('p.id', '=', 's.process_id')->on('p.business_id', '=', 'so.business_id');
            })
            ->leftJoin('contacts as c', function ($j) {
                $j->on('c.id', '=', 'so.contact_id')->on('c.business_id', '=', 'so.business_id');
            })
            ->where('so.business_id', $bizId)
            ->where('so.vehicle_id', $id)
            ->whereNull('so.deleted_at')
            ->orderByRaw('COALESCE(so.entered_at, so.created_at) DESC')
            ->orderByDesc('so.id')
            ->get([
                'so.id', 'so.entered_at', 'so.created_at', 'p.id as processo', 's.name as etapa', 'c.name as cliente',
                DB::raw('(SELECT SUM(i.valor_total) FROM oficina_service_order_items i'
                    . ' WHERE i.service_order_id = so.id AND i.business_id = so.business_id'
                    . ' AND i.deleted_at IS NULL) as valor'),
            ]);

        return response()->json([
            'itens' => $linhas->map(fn ($l) => [
                'os_id' => (int) $l->id,
                'numero' => 'OS-' . str_pad((string) $l->id, 5, '0', STR_PAD_LEFT),
                'data' => substr((string) ($l->entered_at ?? $l->created_at), 0, 10) ?: null,
                // Etapa só quando o estágio é de um processo do mesmo business.
                'etapa_rotulo' => $l->processo !== null ? $l->etapa : null,
                'cliente' => $l->cliente,
                'valor' => $l->valor === null ? null : round((float) $l->valor, 2),
            ])->values(),
        ]);
    }

    /** Mesma regra da tela web de veículos: pacote da Oficina + `oficinaauto.vehicle.view`. */
    public function podeVerVeiculos(?User $user): bool
    {
        return $user !== null
            && $this->moduloHabilitado($user)
            && ($user->can('superadmin') || $user->can('oficinaauto.vehicle.view'));
    }

    private function texto(?string $v): ?string
    {
        return is_string($v) && trim($v) !== '' ? trim($v) : null;
    }

    /** "2019/2020" (fabricação/modelo); um só ano quando só um existe ou os dois são iguais. */
    private function ano($fab, $mod): ?string
    {
        $f = $fab ? (int) $fab : null;
        $m = $mod ? (int) $mod : null;
        if ($f && $m) {
            return $f === $m ? (string) $f : $f . '/' . $m;
        }

        return $f ? (string) $f : ($m ? (string) $m : null);
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

        return $this->moduloHabilitado($user)
            && ($user->can('superadmin') || $user->can('oficinaauto.service_order.view'));
    }

    /** Módulo da Oficina no pacote do business (Camada 1); superadmin: módulo instalado. */
    private function moduloHabilitado(User $user): bool
    {
        return $user->can('superadmin')
            ? (bool) $this->moduleUtil->isModuleInstalled('OficinaAuto')
            : (bool) $this->moduleUtil->hasThePermissionInSubscription(
                (int) $user->business_id,
                'oficina_auto_module',
                'superadmin_package'
            );
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
