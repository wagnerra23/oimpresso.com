<?php

namespace Modules\OficinaAuto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\OficinaAuto\Entities\OaInspectionItem;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Http\Requests\StoreServiceOrderRequest;
use Modules\OficinaAuto\Http\Requests\UpdateServiceOrderRequest;

/**
 * ServiceOrderController — CRUD de Ordens de Serviço (Modules/OficinaAuto V0).
 *
 * Convenção UltimatePOS:
 * - Permissões Spatie: oficinaauto.service_order.{view,create,update,delete}
 * - Multi-tenant: global scope em ServiceOrder filtra business_id automaticamente (ADR 0093)
 *
 * Status V0: string livre (default 'aberta'). FSM canônica entra em US-OFICINA-003.
 *
 * Rotas (Routes/web.php):
 *   GET    /oficina-auto/ordens-servico              → index
 *   GET    /oficina-auto/ordens-servico/create       → create
 *   POST   /oficina-auto/ordens-servico              → store
 *   GET    /oficina-auto/ordens-servico/{order}      → show
 *   GET    /oficina-auto/ordens-servico/{order}/edit → edit
 *   PUT    /oficina-auto/ordens-servico/{order}      → update
 *   DELETE /oficina-auto/ordens-servico/{order}      → destroy
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-001
 */
class ServiceOrderController extends Controller
{
    /**
     * Index dashboard de Ordens de Serviço (V0.5 — pré-reunião Martinho 13/maio).
     *
     * Visão única de REPARO (ADR 0265 — locação erradicada): 3 KPIs + filtros +
     * tabela rica. Espelha layout de Vehicles Index (Wave 5-B) e mockup
     * memory/requisitos/OficinaAuto/demo-martinho-2026-05-13/mockup.html
     *
     * Schema::hasColumn fallback porque colunas Wave 5-A (order_type / number /
     * started_at) podem não estar migradas ainda nessa branch.
     *
     * Filtros aceitos (locação erradicada — ADR 0265):
     *  - ?status=manutencao_ativa | concluida_mes | atrasada | all
     *    (atrasada = OS não-terminal com expected_completion vencida — atraso de reparo)
     *  - ?type=manutencao | mecanica | all
     *  - ?q=<busca em number/cliente/vehicle>
     */
    public function index(Request $request): Response
    {
        // Tela unificada "Oficina Auto" (pedido [W] 2026-06-11): /ordens-servico e
        // /ordens-servico/board servem a MESMA tela (workspace com toggle Kanban·Lista·
        // Grade·Fila in-page, KPIs/abas/toolbar compartilhados). index() delega pro
        // board() — zero duplicação de payload ou de componentes. A antiga listagem
        // paginada separada (componente OficinaAuto/ServiceOrders/Index) foi aposentada;
        // as 4 views derivam do mesmo payload `columns`.
        return $this->board($request);
    }

    /**
     * Board (Kanban) das OS de mecânica — port do protótipo Cowork do carro ([W] 2026-06-02).
     *
     * Renderiza um quadro data-driven a partir das etapas REAIS do processo FSM
     * `oficina_mecanica_os` (Recepção → Diagnóstico → Aguardando aprovação →
     * Aguardando peças → Em execução → Pronto p/ retirar). NÃO é o Kanban de caçamba
     * (ProducaoOficina) — aquele é vertical legado/equívoco (ADR 0194).
     *
     * Colunas = stages não-terminais do processo, ordenadas por sort_order (o quadro
     * se adapta sozinho se o seeder mudar — sem hardcode de coluna). O drag entre
     * colunas dispara FSM via ServiceOrderFsmActionController@execute (canon — nunca
     * UPDATE direto em current_stage_id).
     *
     * Multi-tenant Tier 0 (ADR 0093): ServiceOrder global scope + SaleProcess filtrado
     * por business_id. Frontend só lê.
     *
     * @see resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx
     * @see Modules/OficinaAuto/Database/Seeders/OficinaAutoFsmSeeder.php (oficina_mecanica_os)
     */
    public function board(Request $request): Response
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.service_order.view'),
            403
        );

        $businessId = (int) (session('user.business_id') ?? session('business.id') ?? 0);
        $q = $request->string('q')->toString();
        // Filtros do quadro (mesmo eixo Box/Mecânico que o RichSheet mostra).
        // box_label/assigned_user_id chegaram na Wave 2.1 (US-OFICINA-027) — guard de schema.
        $hasResourceSchema = Schema::hasColumn('service_orders', 'box_label');
        $mecanico = $request->integer('mecanico') ?: null;
        $box      = $request->string('box')->toString();

        // Stages do processo real do carro, ordenados (multi-tenant via business_id).
        $stages = \App\Domain\Fsm\Models\SaleProcessStage::query()
            ->whereHas('process', function ($p) use ($businessId) {
                $p->withoutGlobalScope(\Modules\Jana\Scopes\ScopeByBusiness::class)
                    ->where('business_id', $businessId)
                    ->where('key', 'oficina_mecanica_os');
            })
            ->orderBy('sort_order')
            ->get(['id', 'key', 'name', 'color', 'sort_order', 'is_initial', 'is_terminal']);

        $boardStages = $stages->where('is_terminal', false)->values();
        $initialStageKey = optional($stages->firstWhere('is_initial', true))->key
            ?? optional($boardStages->first())->key;
        $stageKeyById = $stages->pluck('key', 'id');
        $boardStageIds = $boardStages->pluck('id');

        // Membership do quadro: OS em pipeline (stage de board) OU recém-criadas sem
        // pipeline (order_type=mecanica, current_stage_id null) — estas caem na coluna
        // inicial com in_pipeline=false (drag desabilitado até abrir a OS e iniciar).
        // Closure reusada pra montar as opções de filtro sobre o MESMO universo.
        $boardMembership = function ($w) use ($boardStageIds) {
            $w->whereIn('current_stage_id', $boardStageIds)
                ->orWhere(function ($w2) {
                    $w2->where('order_type', 'mecanica')->whereNull('current_stage_id');
                });
        };

        // Opções dos selects (Box/Mecânico) — distinct sobre o universo do quadro, ANTES
        // de aplicar box/mecanico (senão o select encolheria pra própria seleção).
        [$boxOptions, $mecanicoOptions] = $this->buildBoardFilterOptions(
            $boardMembership,
            $businessId,
            $hasResourceSchema,
        );

        $orders = ServiceOrder::query()
            ->with([
                'vehicle:id,plate,vehicle_type,mileage_at_entry',
                'contact:id,name',
                'assignedUser:id,first_name,last_name,surname',
                'dviInspectionItems',
                'dviInspectionItems.arquivos',
            ])
            ->where($boardMembership)
            // Filtro por mecânico (assigned_user_id) e/ou box (texto livre) — query (canon
            // charter), não client-side. Guard de schema pra ambiente pré-Wave 2.1.
            ->when($hasResourceSchema && $mecanico !== null, fn ($qb) => $qb->where('assigned_user_id', $mecanico))
            ->when($hasResourceSchema && $box !== '', fn ($qb) => $qb->where('box_label', $box))
            ->when($q !== '', function ($qb) use ($q) {
                $like = '%' . $q . '%';
                $qb->where(function ($w) use ($like, $q) {
                    if (ctype_digit($q)) {
                        $w->orWhere('id', (int) $q);
                    }
                    $w->orWhereHas('vehicle', fn ($v) => $v->where('plate', 'like', $like))
                        ->orWhereHas('contact', fn ($c) => $c->where('name', 'like', $like));
                });
            })
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        // "Último" de cada OS — 1 query bulk no audit FSM (sale_stage_history),
        // filtrada pelo processo oficina_mecanica_os pra não colidir com o
        // transaction_id de vendas (a coluna é compartilhada por convenção legada).
        // Paridade card Cowork (linha "últ.") com dado REAL de auditoria.
        $lastActivityByOsId = $this->buildLastActivityMap($orders->pluck('id')->all(), $businessId);

        // Agrupa cards por stage_key (null → coluna inicial).
        $cardsByStage = [];
        foreach ($boardStages as $s) {
            $cardsByStage[$s->key] = [];
        }
        foreach ($orders as $order) {
            $currentStageId = $order->getAttribute('current_stage_id');
            $stageKey = $currentStageId !== null
                ? ($stageKeyById[$currentStageId] ?? null)
                : $initialStageKey;
            // Stage fora do board (terminal ou de outro processo) — ignora no quadro.
            if ($stageKey === null || ! array_key_exists($stageKey, $cardsByStage)) {
                continue;
            }
            $cardsByStage[$stageKey][] = $this->shapeBoardCard(
                $order,
                $currentStageId !== null,
                $lastActivityByOsId[$order->id] ?? null,
            );
        }

        $columns = $boardStages->map(fn ($s) => [
            'key'   => $s->key,
            'name'  => $s->name,
            'color' => $s->color,
            'cards' => $cardsByStage[$s->key] ?? [],
            'count' => count($cardsByStage[$s->key] ?? []),
        ])->all();

        return Inertia::render('OficinaAuto/ServiceOrders/Board', [
            'columns'      => $columns,
            'kpis'         => $this->buildBoardKpis($columns, count($boxOptions)),
            'process_seeded' => $boardStages->isNotEmpty(),
            'filters'      => ['q' => $q, 'mecanico' => $mecanico, 'box' => $box !== '' ? $box : null],
            'filterOptions' => ['boxes' => $boxOptions, 'mecanicos' => $mecanicoOptions],
        ]);
    }

    /**
     * Mapa osId → "última atividade" (linha "últ." do card, paridade Cowork).
     *
     * 1 query bulk no audit append-only sale_stage_history (ADR 0129), juntando
     * stage destino + label da ação. Filtrado por process key oficina_mecanica_os
     * (a coluna transaction_id é compartilhada com vendas por convenção legada —
     * sem o filtro de processo, uma venda com mesmo id colidiria). Multi-tenant
     * Tier 0 (ADR 0093) via business_id explícito.
     *
     * @param  list<int>  $osIds
     * @return array<int, array{label: string, at: string}>
     */
    private function buildLastActivityMap(array $osIds, int $businessId): array
    {
        if (empty($osIds)) {
            return [];
        }

        // Stage IDs do processo de mecânica deste negócio — guarda contra colisão
        // de transaction_id com pipelines de venda.
        $mecanicaStageIds = \App\Domain\Fsm\Models\SaleProcessStage::query()
            ->whereHas('process', function ($p) use ($businessId) {
                $p->withoutGlobalScope(\Modules\Jana\Scopes\ScopeByBusiness::class)
                    ->where('business_id', $businessId)
                    ->where('key', 'oficina_mecanica_os');
            })
            ->pluck('id');

        if ($mecanicaStageIds->isEmpty()) {
            return [];
        }

        // Histórico das OS do quadro, mais recente primeiro. Pega o 1º (latest) por OS.
        $rows = \App\Domain\Fsm\Models\SaleStageHistory::query()
            ->withoutGlobalScope(\Modules\Jana\Scopes\ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->whereIn('transaction_id', $osIds)
            ->whereIn('to_stage_id', $mecanicaStageIds)
            ->with(['toStage:id,name', 'action:id,label'])
            ->orderByDesc('executed_at')
            ->get(['id', 'transaction_id', 'action_id', 'to_stage_id', 'executed_at']);

        $map = [];
        foreach ($rows as $row) {
            // getAttribute (não acesso a propriedade dinâmica) — evita property.notFound
            // do PHPStan sobre transaction_id (guarded, sem @property) e sobre o Model
            // genérico das relações belongsTo. Checagem explícita (sem ?? sobre mixed)
            // pra não tropeçar no "left side not nullable".
            $osId = (int) $row->getAttribute('transaction_id');
            if (isset($map[$osId])) {
                continue; // já temos o mais recente (ordenação desc)
            }
            $actionLabel = $row->action?->getAttribute('label');
            $stageName = $row->toStage?->getAttribute('name');
            $label = is_string($actionLabel) && $actionLabel !== ''
                ? $actionLabel
                : 'Etapa: ' . (is_string($stageName) && $stageName !== '' ? $stageName : '—');
            $executedAt = $row->getAttribute('executed_at');
            $map[$osId] = [
                'label' => $label,
                'at'    => $executedAt instanceof \Carbon\CarbonInterface ? $executedAt->toIso8601String() : '',
            ];
        }

        return $map;
    }

    /**
     * Opções dos selects de filtro do quadro — boxes (texto livre) + mecânicos distintos
     * entre as OS do universo do quadro (closure $membership). Mesmo eixo Box/Mecânico que
     * o ServiceOrderRichSheet mostra. Global scope ServiceOrder já filtra business_id;
     * mecânicos resolvidos com scope explícito por business (User não tem global scope).
     *
     * @param  \Closure  $membership  filtro de pertencimento ao quadro (reusa o do board)
     * @return array{0: list<string>, 1: list<array{id:int, nome:string}>}
     */
    private function buildBoardFilterOptions(\Closure $membership, int $businessId, bool $hasResourceSchema): array
    {
        if (! $hasResourceSchema) {
            return [[], []];
        }

        $boxes = ServiceOrder::query()
            ->where($membership)
            ->whereNotNull('box_label')
            ->where('box_label', '!=', '')
            ->distinct()
            ->orderBy('box_label')
            ->pluck('box_label')
            ->values()
            ->all();

        $mechanicIds = ServiceOrder::query()
            ->where($membership)
            ->whereNotNull('assigned_user_id')
            ->distinct()
            ->pluck('assigned_user_id')
            ->all();

        $mecanicos = [];
        if (! empty($mechanicIds)) {
            $users = \App\User::query()
                ->whereIn('id', $mechanicIds)
                ->when($businessId > 0, fn ($qb) => $qb->where('business_id', $businessId))
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'surname', 'username']);

            foreach ($users as $u) {
                $nome = trim((string) ($u->first_name ?? '') . ' ' . (string) ($u->last_name ?? ''));
                if ($nome === '') {
                    $nome = (string) ($u->surname ?? $u->username ?? '');
                }
                $mecanicos[] = ['id' => (int) $u->id, 'nome' => $nome !== '' ? $nome : '—'];
            }
        }

        return [$boxes, $mecanicos];
    }

    /**
     * Shape de um card do board (1 OS). Aplica as modificações [W]-aceitas:
     *  - foto REAL (1ª foto de item DVI) ou null (frontend esconde o thumb);
     *  - contador DVI x/y (itens decididos pelo cliente / total) + nº de críticos;
     *  - dados de placa/cliente/mecânico/prazo pro card denso.
     *
     * @return array<string, mixed>
     */
    private function shapeBoardCard(ServiceOrder $order, bool $inPipeline, ?array $lastActivity = null): array
    {
        $dvi = $order->dviInspectionItems;
        $dviTotal = $dvi->count();
        // x/y = itens já decididos pelo cliente (aprovado/rejeitado) sobre o total.
        $dviDone = $dvi->whereIn('client_decision', ['approved', 'rejected'])->count();
        $dviCritico = $dvi->where('severity', 'critico')->count();

        // Foto REAL: 1º arquivo anexado a qualquer item DVI; fallback photo_url legado.
        // Nunca placeholder de texto ([W] mod #1) — null deixa o frontend esconder o thumb.
        $thumbUrl = null;
        foreach ($dvi as $item) {
            $arquivo = $item->arquivos->first();
            $arquivoUrl = $arquivo?->getAttribute('display_url');
            if ($arquivoUrl) {
                $thumbUrl = (string) $arquivoUrl;
                break;
            }
            if ($item->photo_url) {
                $thumbUrl = (string) $item->photo_url;
                break;
            }
        }

        $mechanic = $order->assignedUser;
        $mechanicName = $mechanic
            ? trim(($mechanic->first_name ?? '') . ' ' . ($mechanic->last_name ?? ($mechanic->surname ?? '')))
            : null;
        $mechanicInitials = $mechanicName !== null && $mechanicName !== ''
            ? mb_strtoupper(mb_substr($mechanicName, 0, 1) . (mb_strpos($mechanicName, ' ') !== false ? mb_substr(mb_strrchr($mechanicName, ' ') ?: '', 1, 1) : ''))
            : null;

        $expected = $order->expected_completion;
        $isOverdue = $expected !== null
            && ! in_array($order->status, ['concluida', 'cancelada', 'entregue'], true)
            && $expected->isPast();

        // box_label/assigned_user_id (Wave 2.1) podem não existir pré-migração — SELECT *
        // não traz a coluna ausente e getAttribute devolve null (guard implícito).
        $boxLabel = $order->getAttribute('box_label');

        // Progresso (barra do card · paridade Cowork): % de itens DVI decididos pelo
        // cliente sobre o total. Derivado de dado REAL (sem campo "progress" fake);
        // null quando não há DVI (frontend esconde a barra).
        $progress = $dviTotal > 0 ? (int) round($dviDone / $dviTotal * 100) : null;

        // KM de entrada (mileage_at_entry do veículo) — campo real, null se ausente.
        $km = $order->vehicle?->getAttribute('mileage_at_entry');

        return [
            'id'                => (int) $order->id,
            'number'            => 'OS-' . str_pad((string) $order->id, 5, '0', STR_PAD_LEFT),
            'in_pipeline'       => $inPipeline,
            'plate'             => $order->vehicle?->getAttribute('plate'),
            'vehicle_type'      => $order->vehicle?->getAttribute('vehicle_type'),
            'cliente_nome'      => $order->contact?->getAttribute('name'),
            'thumb_url'         => $thumbUrl,
            'dvi_done'          => $dviDone,
            'dvi_total'         => $dviTotal,
            'dvi_critico'       => $dviCritico,
            'valor'             => (float) $order->total_items,
            // box + mechanic_id alimentam o re-pivot client-side do quadro (menu Visão:
            // Foco Box/Mecânico) e a capacidade "x/y boxes" — Onda 1 paridade Cowork.
            'box'               => is_string($boxLabel) && $boxLabel !== '' ? $boxLabel : null,
            'mechanic_id'       => $order->getAttribute('assigned_user_id') !== null ? (int) $order->getAttribute('assigned_user_id') : null,
            'mechanic_name'     => $mechanicName ?: null,
            'mechanic_initials' => $mechanicInitials ?: null,
            // Onda 1.5 paridade Cowork — campos do card rico (todos com lastro real):
            'km'                => $km !== null ? (int) $km : null,
            'progress'          => $progress,
            'entered_at'        => $order->entered_at?->toIso8601String(),
            'completed_at'      => $order->completed_at?->toIso8601String(),
            'expected_completion' => $expected?->toIso8601String(),
            'is_overdue'        => $isOverdue,
            // Linha "últ." — última transição FSM auditada (null se nunca transitou).
            'last_activity'     => $lastActivity,
            'notes'             => $order->notes,
            'urls'              => [
                'show' => '/oficina-auto/ordens-servico/' . $order->id,
                'edit' => '/oficina-auto/ordens-servico/' . $order->id . '/edit',
            ],
        ];
    }

    /**
     * KPIs compactos do board (densidade @1280). Derivados das colunas já montadas
     * (zero query extra). Onda 1.5: set do protótipo Cowork — Recepção, Em
     * diagnóstico, Aguardando peças, Em execução, Urgentes (atrasadas) e Valor em
     * curso (faturamento previsto = soma do valor das OS não-terminais). Mantém as
     * chaves antigas (backward-compat) e adiciona as novas.
     *
     * @param  array<int, array{key:string, count:int, cards:array<int,array<string,mixed>>}>  $columns
     * @param  int  $boxesTotal  nº de boxes/elevadores distintos (sublabel "N boxes")
     * @return array<string, int|float>
     */
    private function buildBoardKpis(array $columns, int $boxesTotal = 0): array
    {
        $byKey = [];
        $total = 0;
        $atrasadas = 0;
        $valorEmCurso = 0.0;
        foreach ($columns as $col) {
            $byKey[$col['key']] = $col['count'];
            $total += $col['count'];
            foreach ($col['cards'] as $card) {
                if (! empty($card['is_overdue'])) {
                    $atrasadas++;
                }
                $valorEmCurso += (float) ($card['valor'] ?? 0);
            }
        }

        return [
            'total'                => $total,
            'recepcao'             => $byKey['recepcao'] ?? 0,
            'em_diagnostico'       => $byKey['em_diagnostico'] ?? 0,
            'aguardando_aprovacao' => $byKey['aguardando_aprovacao'] ?? 0,
            'aguardando_pecas'     => $byKey['aguardando_pecas'] ?? 0,
            'em_execucao'          => $byKey['em_execucao'] ?? 0,
            'pronto_retirada'      => $byKey['pronto_retirada'] ?? 0,
            'atrasadas'            => $atrasadas,
            'valor_em_curso'       => round($valorEmCurso, 2),
            'boxes_total'          => $boxesTotal,
        ];
    }


    public function create(): Response
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.service_order.create'),
            403
        );

        $vehicles = Vehicle::query()
            ->orderBy('plate')
            ->limit(500)
            ->get(['id', 'plate', 'secondary_plate', 'vehicle_type']);

        // Clientes (donos dos caminhões de terceiros) pro combobox do Create — charter
        // exige (sem cliente o gate de aprovação WhatsApp não tem destinatário). Sweep
        // ADR 0265: Create.tsx renderiza o campo só quando a prop `contacts` chega.
        // Multi-tenant Tier 0 (ADR 0093): business_id EXPLÍCITO + scopes Contact canon
        // (onlyCustomers/active). Contact não tem global scope (pattern UltimatePOS).
        $businessId = (int) (session('user.business_id') ?? session('business.id') ?? 0);
        $contacts = \App\Contact::where('contacts.business_id', $businessId)
            ->onlyCustomers()
            ->active()
            ->orderBy('contacts.name')
            ->limit(500)
            ->get(['contacts.id', 'contacts.name']);

        return Inertia::render('OficinaAuto/ServiceOrders/Create', [
            'vehicles' => $vehicles,
            'statuses' => self::statuses(),
            'contacts' => $contacts,
        ]);
    }

    public function store(StoreServiceOrderRequest $request): RedirectResponse
    {
        // D8 Security Wave 15: FormRequest authorize() + abort_unless defense-in-depth.
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.service_order.create'),
            403
        );

        $validated = $request->validated();

        if (empty($validated['entered_at'])) {
            $validated['entered_at'] = now();
        }

        // ADR 0265 — order_type é nullable no request (API/imports), mas o fio usável
        // exige a OS nascendo no pipeline certo: default DELIBERADO 'mecanica' (fluxo
        // real de reparo) quando não informado. Create.tsx já manda 'mecanica'.
        $validated['order_type'] ??= 'mecanica';

        $order = ServiceOrder::create($validated);

        // UC-07/UC-11 (casos.md Produção/Oficina): a OS recém-criada é o "documento vivo"
        // do veículo no kanban. Sem este vínculo o card fica "sem OS" (bucket disponivel não
        // tem fallback V3 em ProducaoOficinaController::loadRentalFallbacks) e o drawer rico
        // não abre — bug pego pelo E2E UC-11 (run 27273605033). Só vincula se o veículo está
        // LIVRE (não clobbera OS ativa de outro fluxo). Tier 0 (ADR 0093): além do global
        // scope do Model (Vehicle::booted), filtro explícito por tenant (defense-in-depth).
        $vehicle = Vehicle::query()
            ->where('business_id', (int) (session('user.business_id') ?? session('business.id') ?? 0))
            ->find($order->vehicle_id);
        if ($vehicle !== null && $vehicle->current_rental_id === null) {
            $vehicle->update(['current_rental_id' => $order->id]);
        }

        // ADR 0265 (fio usável) — auto-start do pipeline FSM: a OS nasce JÁ no quadro
        // (stage inicial do processo resolvido pelo order_type — 'mecanica' →
        // oficina_mecanica_os/recepcao). Antes nascia com current_stage_id=null e
        // dependia de clique manual em start-pipeline, que podia cair no processo
        // errado (OS-00004 órfã em locação). Falha aqui NÃO aborta o create (OS fica
        // fora de pipeline como antes, botão manual continua existindo) — só loga.
        try {
            app(\Modules\OficinaAuto\Services\ServiceOrderPipelineStarter::class)
                ->start($order, null, auth()->id());
        } catch (\Throwable $e) {
            \Log::warning('ServiceOrderController@store: auto-start pipeline falhou', [
                'business_id'      => $order->business_id,
                'service_order_id' => $order->id,
                'order_type'       => $order->order_type,
                'error'            => $e->getMessage(),
            ]);
        }

        return redirect('/oficina-auto/ordens-servico/' . $order->id)
            ->with('status', ['success' => 1, 'msg' => 'Ordem de Serviço criada.']);
    }

    public function show(Request $request, ServiceOrder $order)
    {
        // D8 Security Wave 15: Policy multi-tenant sameTenant guard.
        // Causa raiz #25 (2026-05-20): ServiceOrderController extendia Illuminate\Routing\Controller
        // (sem trait AuthorizesRequests) — $this->authorize() não existia. Fixed: extends
        // App\Http\Controllers\Controller (projeto canon que já usa AuthorizesRequests).
        $this->authorize('view', $order);

        // ADR 0192 — Integração Vendas × Oficina A1 KB-9.75. Carrega Transaction
        // derivada (criada pelo ServiceOrderObserver na transição status='concluida'
        // · PR #1530) pra renderizar VendaDerivadaCard no drawer. Eager 1 query FK
        // barata — multi-tenant Tier 0 herdado via belongsTo + global scope Transaction.
        $order->load([
            'vehicle',
            'contact:id,name,mobile',
            'transaction:id,business_id,invoice_no,final_total,transaction_date',
            // Wave 2.3 US-OFICINA-027 — drawer ServiceOrderRichSheet seção PEÇAS & MÃO DE OBRA
            'items',
            // Wave 2.1 US-OFICINA-027 — drawer KV grid modo manutenção (Mecânico)
            'assignedUser:id,first_name,last_name,surname',
            // US-OFICINA-040 — itens DVI pra seção "Vistoria → orçamento" no Show
            'dviInspectionItems',
            // F3 OS-V2-1 — fotos do laudo OS-level (Fotos & Laudo) pro drawer + print
            'arquivos',
        ]);

        // Accept-aware: drawer ServiceOrderSheet faz fetch JSON via header.
        // Hotfix Wave 7+ — drawer chamava /oficina-auto/service-orders/{id} esperando
        // JSON mas show() só retornava Inertia HTML (HTTP 404 percebido por drawer).
        if ($request->wantsJson()) {
            // Etapa FSM atual pro eyebrow do drawer ("OS #103 · Em execução"). Mostra a
            // ETAPA real do processo, não o status cru (paridade protótipo Cowork · polish
            // [W] 2026-06-11). OS sem pipeline iniciado (current_stage_id null) cai na etapa
            // INICIAL — espelha o $initialStageKey do board(). Mesmo guard multi-tenant do
            // board: withoutGlobalScope no process + business_id explícito (ADR 0093).
            $currentStage = null;
            if (Schema::hasColumn('service_orders', 'current_stage_id')) {
                $stageBaseQuery = \App\Domain\Fsm\Models\SaleProcessStage::query()
                    ->whereHas('process', function ($p) use ($order) {
                        $p->withoutGlobalScope(\Modules\Jana\Scopes\ScopeByBusiness::class)
                            ->where('business_id', $order->business_id)
                            ->where('key', 'oficina_mecanica_os');
                    });
                $stageId = $order->getAttribute('current_stage_id');
                $stage = $stageId !== null
                    ? (clone $stageBaseQuery)->whereKey($stageId)->first(['id', 'key', 'name'])
                    : null;
                if ($stage === null) {
                    $stage = (clone $stageBaseQuery)->where('is_initial', true)->first(['id', 'key', 'name']);
                }
                if ($stage !== null) {
                    $currentStage = ['key' => $stage->key, 'name' => $stage->name];
                }
            }

            // Campos de locação (delivery_address/expected_return_date/daily_rate/
            // dias_locacao) ERRADICADOS do payload (ADR 0265) — colunas ficam no DB
            // como dado histórico; UI/contrato não.
            return response()->json([
                'id'                    => $order->id,
                'number'                => 'OS-' . str_pad((string) $order->id, 5, '0', STR_PAD_LEFT),
                'status'                => $order->status,
                // Etapa FSM atual (key + name PT) pro eyebrow do drawer — null se o processo
                // oficina_mecanica_os não estiver seedado pro negócio.
                'current_stage'         => $currentStage,
                'order_type'            => $order->order_type,
                'expected_completion'   => $order->expected_completion,
                'valor_receber'         => $order->valor_receber ?? 0,
                'is_overdue'            => $order->is_overdue ?? false,
                'entered_at'            => $order->entered_at,
                'started_at'            => $order->entered_at,
                'completed_at'          => $order->completed_at,
                'notes'                 => $order->notes,
                // Wave 2.1 US-OFICINA-027 — drawer modo manutenção (sub-vertical 4 ADR 0194)
                'mileage_at_service'    => $order->mileage_at_service,
                'box_label'             => $order->box_label,
                'assigned_user'         => $order->assignedUser ? [
                    'id'   => $order->assignedUser->id,
                    // UltimatePOS canon: surname + first_name + last_name (mesmo pattern User::getUserFullNameAttribute)
                    'name' => trim(
                        ($order->assignedUser->surname ?? '') . ' ' .
                        ($order->assignedUser->first_name ?? '') . ' ' .
                        ($order->assignedUser->last_name ?? '')
                    ),
                ] : null,
                'vehicle' => $order->vehicle ? [
                    'id'                => $order->vehicle->id,
                    'plate'             => $order->vehicle->plate,
                    'vehicle_number'    => $order->vehicle->vehicle_number ?? null,
                    'vehicle_type'      => $order->vehicle->vehicle_type ?? null,
                    'capacity_m3'       => $order->vehicle->capacity_m3 ?? null,
                    'model_year'        => $order->vehicle->model_year ?? null,
                    'manufacture_year'  => $order->vehicle->manufacture_year ?? null,
                    'color'             => $order->vehicle->color ?? null,
                ] : null,
                'contact' => $order->contact ? [
                    'id'     => $order->contact->id,
                    'name'   => $order->contact->name,
                    'mobile' => $order->contact->mobile,
                ] : null,
                // Wave 2.3 US-OFICINA-027 — items lançados na OS (peça + mão-obra + terceiro)
                // alimentam seção "PEÇAS & MÃO DE OBRA" do drawer ServiceOrderRichSheet.
                // Observer ::computeFinalTotal soma valor_total destes pra Transaction.final_total.
                'items' => $order->items->map(fn ($item) => [
                    'id'             => $item->id,
                    'tipo'           => $item->tipo,
                    'descricao'      => $item->descricao,
                    'quantidade'     => (float) $item->quantidade,
                    'valor_unitario' => (float) $item->valor_unitario,
                    'valor_total'    => (float) $item->valor_total,
                    'product_id'     => $item->product_id,
                    'notes'          => $item->notes,
                ])->values()->all(),
                'items_total' => (float) $order->total_items,
                // F3 OS-V2-3 — estado do gate de aprovação (none/pending/approved/declined)
                // + timestamps. O drawer (DviInlineEditor · DviGateFoot) deriva a barra de
                // 4 estados DESTE payload — nunca de simulação client-side. `total` espelha o
                // total recomendado da DVI (atenção+crítico), o número que o cliente aprovou.
                'approval' => [
                    'state'        => $order->approval_state,
                    'total'        => (float) $order->dvi_breakdown['total_recomendado'],
                    'requested_at' => $order->approval_requested_at?->toIso8601String(),
                    'decided_at'   => $order->approval_decided_at?->toIso8601String(),
                    'decision'     => $order->approval_decision,
                ],
                // F3 OS-V2-2 — itens DVI (Vistoria Digital) pra o semáforo inline editável
                // do drawer ServiceOrderRichSheet (DviInlineEditor). Mesmo shape do branch
                // Inertia (Show), + sort_order pra ordenação estável. `budget_item_id` (em
                // metadata) sinaliza item já convertido em linha de orçamento.
                'dvi_items' => $order->dviInspectionItems
                    ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
                    ->map(fn (OaInspectionItem $dvi) => [
                        'id'                => (int) $dvi->id,
                        'categoria'         => (string) $dvi->categoria,
                        'descricao'         => (string) $dvi->descricao,
                        'severity'          => (string) $dvi->severity,
                        'recomendacao'      => $dvi->recomendacao,
                        'valor_recomendado' => $dvi->valor_recomendado !== null ? (float) $dvi->valor_recomendado : null,
                        'sort_order'        => (int) $dvi->sort_order,
                        'budget_item_id'    => is_array($dvi->metadata) ? ($dvi->metadata['budget_item_id'] ?? null) : null,
                    ])->values()->all(),
                // F3 OS-V2-1 — fotos do laudo OS-level (Fotos & Laudo) pra o drawer.
                // `label` = original_name (legenda editável no lightbox). display_url
                // é signed quando bucket=sensitive (defesa em profundidade).
                'laudo_photos' => $order->arquivos
                    ->sortBy([['created_at', 'asc'], ['id', 'asc']])
                    ->map(function ($a) {
                        // closure sem tipo no param + @var: relação HasArquivos é MorphMany
                        // sem generic (Larastan tipa Model, não Arquivo — dívida US-ARQ-TYPE).
                        /** @var \Modules\Arquivos\Entities\Arquivo $a */
                        return [
                            'id'          => (int) $a->id,
                            'label'       => (string) ($a->original_name ?? ''),
                            'mime_type'   => (string) $a->mime_type,
                            'size_bytes'  => (int) $a->size_bytes,
                            'display_url' => (string) $a->display_url,
                            'created_at'  => $a->created_at?->toIso8601String(),
                        ];
                    })->values()->all(),
                // ADR 0192 · V0 core shape (Onda 5). FASE B (items_list / items_summary /
                // fiscal NF-e) fica pra wave futura — exige join sell_lines + NfeBrasil
                // que já existe no equivalente Modules/Repair/ProducaoOficinaController
                // (`buildVendaDerivadaPayload`). Quando trouxer pra OficinaAuto, extrair
                // helper compartilhado pra App\Services\VendaDerivadaPayloadService.
                'venda_derivada' => $order->transaction
                    ? $this->shapeVendaDerivada($order->transaction)
                    : null,
                'urls' => [
                    'show' => '/oficina-auto/ordens-servico/' . $order->id,
                    'edit' => '/oficina-auto/ordens-servico/' . $order->id . '/edit',
                ],
            ]);
        }

        // Wave 5 US-OFICINA-005-bis — Show.tsx seção "Itens da OS" consome
        // `order.items[]` (peças + mão-obra + terceiros). Items já foram eager-loaded
        // acima (linha `$order->load([..., 'items', ...])`); aqui só serializamos
        // o shape consumido pelo frontend (mesmo formato do JSON branch).
        $itemsPayload = $order->items->map(fn ($item) => [
            'id'             => $item->id,
            'tipo'           => $item->tipo,
            'descricao'      => $item->descricao,
            'quantidade'     => (float) $item->quantidade,
            'valor_unitario' => (float) $item->valor_unitario,
            'valor_total'    => (float) $item->valor_total,
            'product_id'     => $item->product_id,
            'notes'          => $item->notes,
        ])->values()->all();

        // US-OFICINA-040 — itens da vistoria DVI pra seção "Vistoria → orçamento".
        // `budget_item_id` (em metadata) sinaliza item já convertido em linha de orçamento.
        $dviPayload = $order->dviInspectionItems->map(fn (OaInspectionItem $dvi) => [
            'id'                => (int) $dvi->id,
            'categoria'         => (string) $dvi->categoria,
            'descricao'         => (string) $dvi->descricao,
            'severity'          => (string) $dvi->severity,
            'recomendacao'      => $dvi->recomendacao,
            'valor_recomendado' => $dvi->valor_recomendado !== null ? (float) $dvi->valor_recomendado : null,
            'budget_item_id'    => is_array($dvi->metadata) ? ($dvi->metadata['budget_item_id'] ?? null) : null,
        ])->values()->all();

        return Inertia::render('OficinaAuto/ServiceOrders/Show', [
            'order' => array_merge($order->toArray(), [
                'items'     => $itemsPayload,
                'dvi_items' => $dviPayload,
            ]),
        ]);
    }

    public function edit(ServiceOrder $order): Response
    {
        // D8 Security Wave 15: Policy sameTenant guard.
        $this->authorize('update', $order);

        $vehicles = Vehicle::query()
            ->orderBy('plate')
            ->limit(500)
            ->get(['id', 'plate', 'secondary_plate', 'vehicle_type']);

        // Wave 5 US-OFICINA-005-bis — Edit.tsx tem seção inline "Itens da OS" idêntica
        // ao Show.tsx. Eager-load items + serializa shape consumido pelo componente
        // compartilhado ServiceOrderItemRow.
        $order->load('items');
        $itemsPayload = $order->items->map(fn ($item) => [
            'id'             => $item->id,
            'tipo'           => $item->tipo,
            'descricao'      => $item->descricao,
            'quantidade'     => (float) $item->quantidade,
            'valor_unitario' => (float) $item->valor_unitario,
            'valor_total'    => (float) $item->valor_total,
            'product_id'     => $item->product_id,
            'notes'          => $item->notes,
        ])->values()->all();

        return Inertia::render('OficinaAuto/ServiceOrders/Edit', [
            'order'    => array_merge($order->toArray(), [
                'items' => $itemsPayload,
            ]),
            'vehicles' => $vehicles,
            'statuses' => self::statuses(),
        ]);
    }

    public function update(UpdateServiceOrderRequest $request, ServiceOrder $order): RedirectResponse
    {
        // D8 Security Wave 15: Policy multi-tenant + FormRequest validation.
        $this->authorize('update', $order);

        $order->update($request->validated());

        return redirect('/oficina-auto/ordens-servico/' . $order->id)
            ->with('status', ['success' => 1, 'msg' => 'OS atualizada.']);
    }

    /**
     * US-OFICINA-041 — Enviar orçamento pro cliente aprovar (gate de aprovação).
     *
     * Delta do protótipo Cowork "Nova OS" (card "Aprovação do cliente"): o mecânico
     * dispara o pedido de aprovação com 1 clique. Reusa o pipeline AUTOMÁTICO já
     * existente — transicionar status → `orcamento` faz o `ServiceOrderObserver`
     * despachar o `EnviarLinkAprovacaoWhatsappJob` (link público + PIN, US-OFICINA-014).
     *
     * A execução não inicia até o cliente aprovar (status vira `aprovada` via
     * AprovacaoOsController público). Gate visual no Show reflete o estado.
     *
     * Defesa em profundidade: Policy update(ServiceOrder) (sameTenant ADR 0093).
     */
    public function enviarAprovacao(ServiceOrder $order): RedirectResponse
    {
        $this->authorize('update', $order);

        // Estados terminais/já-aprovados não reenviam aprovação.
        if (in_array($order->status, ['aprovada', 'concluida', 'entregue', 'cancelada'], true)) {
            return back()->with('status', [
                'success' => 0,
                'msg'     => 'OS já está aprovada ou finalizada.',
            ]);
        }

        // F3 OS-V2-3 — distingue 1º envio de RE-envio ("Cobrar" / "Revisar e reenviar").
        // O ServiceOrderObserver só dispara o WhatsApp quando o status MUDA pra orcamento;
        // num re-envio (já em orcamento) precisamos disparar manualmente + limpar a chave
        // de idempotência de 7d do Job pra forçar um novo envio.
        $jaEmOrcamento = $order->status === 'orcamento';

        // Carimba o gate: pending (requested_at) + zera decisão anterior (caso reenvio
        // pós-declined → volta pra pending). status → orcamento (Observer envia WhatsApp).
        $order->forceFill([
            'status'                => 'orcamento',
            'approval_requested_at' => now(),
            'approval_decided_at'   => null,
            'approval_decision'     => null,
        ])->save();

        if ($jaEmOrcamento) {
            \Illuminate\Support\Facades\Cache::forget("oficina:approval_dispatched:{$order->id}");
            \Modules\OficinaAuto\Jobs\EnviarLinkAprovacaoWhatsappJob::dispatch(
                (int) $order->business_id,
                (int) $order->id,
            );
        }

        return back()->with('status', [
            'success' => 1,
            'msg'     => 'Orçamento enviado ao cliente para aprovação (WhatsApp).',
        ]);
    }

    public function destroy(ServiceOrder $order): RedirectResponse
    {
        // D8 Security Wave 15: Policy multi-tenant sameTenant guard.
        $this->authorize('delete', $order);

        try {
            $order->delete();
        } catch (\Throwable $e) {
            // D7 LGPD: redaciona PII (endereço entrega/contact pode aparecer em FK errors) antes de logar.
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(PiiRedactor::class)->redact($e->getMessage()));

            return redirect('/oficina-auto/ordens-servico')
                ->with('status', ['success' => 0, 'msg' => 'Falha ao remover OS.']);
        }

        return redirect('/oficina-auto/ordens-servico')
            ->with('status', ['success' => 1, 'msg' => 'OS removida (soft delete).']);
    }

    /**
     * Shape mínimo do payload `venda_derivada` consumido pelo VendaDerivadaCard
     * shared (resources/js/Components/shared/VendaDerivadaCard.tsx).
     *
     * V0 core (Onda 5 ADR 0192): id + invoice_no + final_total + transaction_date.
     * Items breakdown + badge fiscal NF-e ficam pra wave futura (paridade com
     * Modules/Repair `buildVendaDerivadaPayload`).
     *
     * Multi-tenant Tier 0 (ADR 0093): Transaction já vem scopada por business_id
     * via belongsTo + global scope herdado. Frontend só lê.
     */
    private function shapeVendaDerivada(\App\Transaction $t): array
    {
        return [
            'id'               => (int) $t->id,
            'invoice_no'       => (string) ($t->invoice_no ?? $t->id),
            'final_total'      => (float) $t->final_total,
            'transaction_date' => $t->transaction_date
                ? (is_string($t->transaction_date)
                    ? substr($t->transaction_date, 0, 10)
                    : $t->transaction_date->toDateString())
                : null,
        ];
    }

    /**
     * Gap 3 — Imprimir OS PDF profissional A4 nota-fiscal-like (US-OFICINA-037).
     *
     * Espelha pattern `SellPosController::printInvoice` (Sells legacy) — AJAX-only
     * (404 sem X-Requested-With) + retorna `{success:1, receipt:{html_content,
     * print_title}}`. Frontend `printServiceOrder.ts` injeta `html_content` em
     * IFRAME oculto + dispara `window.print()` (cross-origin IFRAME compat).
     *
     * Multi-tenant Tier 0 [ADR 0093]: Route Model Binding já respeita global scope
     * de ServiceOrder, mas defensive guard explícito (ADR 0093 §"Defense in depth")
     * — abort 404 cross-tenant antes mesmo do render. Cliente final do Martinho
     * sub-vertical 4 (CNAE 4520 mecânica pesada) leva papel pra ressarcir
     * transportadora 3ª/seguradora — não pode vazar dado de outra biz NUNCA.
     *
     * @see resources/views/oficina_auto/print/service_order.blade.php (template A4)
     * @see resources/js/Lib/printServiceOrder.ts (helper IFRAME mirror printSaleReceipt)
     * @see memory/sessions/2026-05-26-plano-gap-3-imprimir-os-pdf-profissional.md
     */
    public function printInvoice(Request $request, ServiceOrder $order)
    {
        // AJAX-only (espelha SellPosController::printInvoice §1928) — render direto
        // via browser sem X-Requested-With retorna 404 (não AppShellV2 vazado).
        if (! $request->ajax()) {
            abort(404);
        }

        // D8 Security: defensive guard cross-tenant + permission (ADR 0093).
        // Route Model Binding já filtrou via global scope, mas dupla-checa antes
        // do render pra fechar qualquer brecha futura (ex: superadmin com session
        // de outra biz).
        $businessId = (int) ($request->session()->get('user.business_id')
            ?? $request->session()->get('business.id') ?? 0);
        abort_unless((int) $order->business_id === $businessId, 404);

        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.service_order.view'),
            403
        );

        try {
            $order->load([
                'vehicle',
                'contact:id,name,mobile,tax_number,address_line_1,city,state',
                'items',
                'dviInspectionItems',
                'assignedUser:id,first_name,last_name,surname',
                // F3 OS-V2-1 — fotos do laudo OS-level entram na folha A4 ("Fotos da vistoria")
                'arquivos',
            ]);

            $business = \App\Business::find($businessId);
            $location = $business?->locations()->first();

            $orderNumber = 'OS-' . str_pad((string) $order->id, 5, '0', STR_PAD_LEFT);

            $htmlContent = view('oficina_auto.print.service_order', [
                'order'    => $order,
                'business' => $business,
                'location' => $location,
                'orderNumber' => $orderNumber,
                'generatedAt' => now(),
            ])->render();

            return response()->json([
                'success' => 1,
                'receipt' => [
                    'html_content' => $htmlContent,
                    'print_title'  => $orderNumber,
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::emergency('OficinaAuto printInvoice: File:' . $e->getFile()
                . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage()
                . ' Trace:' . substr($e->getTraceAsString(), 0, 800));

            $payload = [
                'success' => 0,
                'msg'     => 'Não foi possível gerar a impressão da OS.',
            ];
            if (config('app.debug')) {
                $payload['_debug'] = [
                    'class'   => get_class($e),
                    'message' => $e->getMessage(),
                    'file'    => basename($e->getFile()),
                    'line'    => $e->getLine(),
                ];
            }

            return response()->json($payload, 500);
        }
    }

    /**
     * Status livre V0 (FSM canônica chega em US-OFICINA-003 / ADR 0129).
     */
    public static function statuses(): array
    {
        return [
            'aberta'       => 'Aberta',
            'orcamento'    => 'Em Orçamento',
            'aprovada'     => 'Aprovada',
            'em_servico'   => 'Em Serviço',
            'em_producao'  => 'Em Produção',
            'concluida'    => 'Concluída',
            'entregue'     => 'Entregue',
            'cancelada'    => 'Cancelada',
        ];
    }
}
