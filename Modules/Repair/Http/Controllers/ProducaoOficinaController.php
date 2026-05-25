<?php

namespace Modules\Repair\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Modules\Repair\Entities\JobSheet;
use Modules\Repair\Entities\RepairStatus;
use Modules\Repair\Services\KanbanProductionService;

/**
 * Produção · Oficina/OS — kanban shared infrastructure (read-mostly).
 *
 * REFACTOR shared (audit 2026-05-10):
 * - Vocabulário automotivo (placa/vehicle/km/box/elevador/mecanico) → genérico
 *   (code/item/usage_meter/slot/area/executor) consumível por
 *   Modules/OficinaAuto, Modules/ComunicacaoVisual, Modules/Vestuario, etc.
 * - SLOTS/AREAS hardcoded → consumidos de business.repair_settings JSON
 *   (coluna BD JÁ existe; ver Modules/Repair/Entities/JobSheet -> business)
 * - Frontend recebe `slotConfig` + `labelOverrides` opcionais — vertical
 *   passa via `business.repair_settings.{slots,areas,labels}`.
 *
 * US-REPAIR-PROD-2 (2026-05-09): query real \`JobSheet\` por business_id, com
 * heurística \`sort_order\` quartil pra mapear cada status pra uma das 5 colunas
 * fixas. Fallback pra mock data se biz não tem \`repair_statuses\` ou \`job_sheets\`
 * configurado.
 *
 * Charter: resources/js/Pages/Repair/ProducaoOficina/Index.charter.md
 */
class ProducaoOficinaController extends Controller
{
    private const COLUMN_TEMPLATES = [
        ['id' => 'recepcao', 'label' => 'Recepção', 'tone' => 'slate'],
        ['id' => 'diagnostico', 'label' => 'Diagnóstico', 'tone' => 'blue'],
        ['id' => 'aguardando-pecas', 'label' => 'Aguardando peças', 'tone' => 'amber'],
        ['id' => 'em-execucao', 'label' => 'Em execução', 'tone' => 'violet'],
        ['id' => 'pronto', 'label' => 'Pronto', 'tone' => 'emerald'],
    ];

    /**
     * Defaults conservadores caso business.repair_settings não tenha config.
     * Mantém comportamento atual (B1..B4 + E1..E2) pra não quebrar UX legacy.
     * Vertical OficinaAuto preserva via labelOverrides; ComunicacaoVisual /
     * Vestuario sobrescreve com seus próprios slot groups (ver business-repair-settings-example.json).
     */
    private const DEFAULT_SLOT_CONFIG = [
        ['key' => 'slot', 'label' => 'Box', 'options' => ['B1', 'B2', 'B3', 'B4']],
        ['key' => 'area', 'label' => 'Elevador', 'options' => ['E1', 'E2']],
    ];

    public function __construct(private ?KanbanProductionService $kanban = null)
    {
        $this->kanban ??= new KanbanProductionService();
    }

    private const DEFAULT_LABEL_OVERRIDES = [
        // genérico-shared (defaults). Vertical passa labels específicas via JSON.
        'code' => 'Código',
        'item' => 'Item',
        'brand' => 'Marca',
        'usage_meter' => null,    // omite se null
        'usage_unit' => null,
        'executor' => 'Executor',
    ];

    public function index()
    {
        $business_id = (int) request()->session()->get('user.business_id');

        $statuses = RepairStatus::where('business_id', $business_id)
            ->orderBy('sort_order', 'asc')
            ->get();

        $repairSettings = $this->loadRepairSettings($business_id);

        if ($statuses->isEmpty()) {
            return $this->renderMock($repairSettings);
        }

        $jobSheets = JobSheet::with(['status', 'technician', 'Brand', 'deviceModel'])
            ->where('business_id', $business_id)
            ->whereIn('status_id', $statuses->pluck('id'))
            ->orderBy('created_at', 'desc')
            ->limit(200)
            ->get();

        if ($jobSheets->isEmpty()) {
            return $this->renderMock($repairSettings);
        }

        $statusToColumn = $this->kanban->mapStatusesToColumns($statuses);
        $columns = $this->buildColumns($jobSheets, $statusToColumn);

        return Inertia::render('Repair/ProducaoOficina/Index', [
            'columns' => $columns,
            'totals' => [
                'os' => $jobSheets->count(),
                'pending_approval' => collect($columns)
                    ->flatMap(fn ($c) => $c['cards'])
                    ->filter(fn ($card) => $card['pending_approval'] ?? false)
                    ->count(),
            ],
            'data_source' => 'live',
            'slot_config' => $repairSettings['slots'],
            'label_overrides' => $repairSettings['labels'],
        ]);
    }

    /**
     * POST /repair/producao-oficina/{id}/move
     * US-REPAIR-PROD-4 — drag-and-drop entre colunas.
     *
     * Recebe \`column\` no body (recepcao|diagnostico|aguardando-pecas|em-execucao|pronto)
     * e atualiza JobSheet.status_id pro primeiro status do bucket alvo (mapping reverso
     * heurístico — espelha mapStatusesToColumns).
     */
    public function move(Request $request, int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $targetColumn = (string) $request->input('column', '');

        if (! in_array($targetColumn, array_column(self::COLUMN_TEMPLATES, 'id'), true)) {
            return back()->with('error', "Coluna inválida: {$targetColumn}");
        }

        $jobSheet = JobSheet::where('business_id', $businessId)->find($id);
        if (! $jobSheet) {
            return back()->with('error', 'OS não encontrada ou pertence a outro tenant.');
        }

        $statuses = RepairStatus::where('business_id', $businessId)
            ->orderBy('sort_order', 'asc')
            ->get();

        if ($statuses->isEmpty()) {
            return back()->with('error', 'Nenhum status configurado para este business.');
        }

        $targetStatusId = $this->kanban->findStatusForColumn($statuses, $targetColumn);
        if ($targetStatusId === null) {
            return back()->with('error', "Não foi possível mapear coluna '{$targetColumn}' pra um status — confira se há status \`is_completed_status=true\` (Pronto) e ≥1 status ativo.");
        }

        if ($jobSheet->status_id === $targetStatusId) {
            // Card já está na coluna alvo (drag pra mesma coluna). No-op.
            return back();
        }

        $jobSheet->status_id = $targetStatusId;
        $jobSheet->save();

        return back()->with('success', 'OS movida.');
    }

    /**
     * Lê business.repair_settings (JSON column) e devolve config consolidada
     * com defaults preenchidos. Mantém retrocompat: biz que não configurou
     * ainda recebe slot config B1..B4 + E1..E2 (= UX atual).
     */
    private function loadRepairSettings(int $businessId): array
    {
        $business = \App\Business::find($businessId);
        $settings = $business?->repair_settings ?? [];

        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }

        return [
            'slots' => $settings['slots'] ?? self::DEFAULT_SLOT_CONFIG,
            'labels' => array_merge(self::DEFAULT_LABEL_OVERRIDES, $settings['labels'] ?? []),
        ];
    }

    /**
     * Mapping reverso: dado um column id, retorna o repair_status.id default
     * pra usar quando user dropa um card lá. Espelha a heurística de
     * mapStatusesToColumns():
     *   - 'pronto' → primeiro status com is_completed_status=true
     *   - resto → primeiro status do bucket equivalente (sort_order quartil)
     */
    private function findStatusForColumn(Collection $statuses, string $columnId): ?int
    {
        if ($columnId === 'pronto') {
            return $statuses->where('is_completed_status', true)->first()?->id;
        }

        $active = $statuses->where('is_completed_status', false)->values();
        if ($active->isEmpty()) {
            return null;
        }

        $columnOrder = ['recepcao', 'diagnostico', 'aguardando-pecas', 'em-execucao'];
        $bucketIdx = array_search($columnId, $columnOrder, true);
        if ($bucketIdx === false) {
            return null;
        }

        $bucketSize = max(1, (int) ceil($active->count() / 4));
        $startIdx = $bucketIdx * $bucketSize;

        return $active->get($startIdx)?->id ?? $active->first()?->id;
    }

    /**
     * Mapeia cada \`repair_status.id\` pro id da coluna kanban.
     * Heurística:
     *   - is_completed_status = true  → 'pronto'
     *   - resto, dividido em 4 buckets por posição em sort_order
     */
    private function mapStatusesToColumns(Collection $statuses): array
    {
        $completed = $statuses->where('is_completed_status', true);
        $active = $statuses->where('is_completed_status', false)->values();

        $map = [];
        foreach ($completed as $s) {
            $map[$s->id] = 'pronto';
        }

        $count = $active->count();
        if ($count === 0) {
            return $map;
        }

        $bucketSize = max(1, (int) ceil($count / 4));
        $columnOrder = ['recepcao', 'diagnostico', 'aguardando-pecas', 'em-execucao'];

        foreach ($active as $i => $status) {
            $bucketIdx = min(3, intdiv($i, $bucketSize));
            $map[$status->id] = $columnOrder[$bucketIdx];
        }

        return $map;
    }

    private function buildColumns(Collection $jobSheets, array $statusToColumn): array
    {
        $columns = [];
        foreach (self::COLUMN_TEMPLATES as $tpl) {
            $columns[$tpl['id']] = $tpl + ['cards' => []];
        }

        // ADR 0192 — Integração Vendas × Oficina (A1 KB-9.75).
        // Batch lookup das Transactions derivadas (source='oficina') por job_sheet_id pra
        // exibir card "Esta OS gerou venda #V-NNNN" no drawer (frontend Onda 5).
        // 1 query única (anti-N+1) scopada por business_id (Tier 0 ADR 0093).
        //
        // Onda 3 (Wave Z-2 backend): eager-load sell_lines.product pra expandir
        // payload com items_list (produto/serviço) + items_summary (counts/totals).
        // Sem este eager-load, cada card faria N queries (anti-N+1).
        $businessId = (int) request()->session()->get('user.business_id');
        $vendaDerivadaByOs = collect();
        $osIds = $jobSheets->pluck('id')->all();
        $nfeByTx = collect();
        if (! empty($osIds)) {
            $vendaDerivadaByOs = \App\Transaction::where('business_id', $businessId)
                ->where('source', 'oficina')
                ->whereIn('repair_job_sheet_id', $osIds)
                ->with(['sell_lines' => function ($q) {
                    $q->select([
                        'id', 'transaction_id', 'product_id',
                        'quantity', 'unit_price_inc_tax', 'unit_price',
                        'item_tax', 'line_discount_amount', 'line_discount_type',
                    ]);
                }, 'sell_lines.product:id,name,enable_stock,type'])
                ->get(['id', 'repair_job_sheet_id', 'invoice_no', 'final_total', 'transaction_date'])
                ->keyBy('repair_job_sheet_id');

            // Onda 3 (Wave Z-2 backend): batch lookup nfe_emissoes por transaction_id.
            // Padrão idêntico a SellController::inertiaList (linha 1226+).
            // Tier 0 ADR 0093: scoped por business_id (cross-tenant não vaza).
            // Precedence: pega emissão mais recente (orderByDesc id) — autorizada
            // sobrepõe pendente sobrepõe rejeitada quando há retentativa.
            $txIds = $vendaDerivadaByOs->pluck('id')->all();
            if (! empty($txIds) && class_exists(\Modules\NfeBrasil\Models\NfeEmissao::class)) {
                $nfeByTx = \Modules\NfeBrasil\Models\NfeEmissao::where('business_id', $businessId)
                    ->whereIn('transaction_id', $txIds)
                    ->orderByDesc('id')
                    ->get(['id', 'transaction_id', 'modelo', 'status', 'chave_44'])
                    ->groupBy('transaction_id')
                    ->map(fn ($group) => $group->first());
            }
        }

        foreach ($jobSheets as $js) {
            $columnId = $statusToColumn[$js->status_id] ?? null;
            if ($columnId === null || ! isset($columns[$columnId])) {
                continue;
            }
            $tx = $vendaDerivadaByOs->get($js->id);
            $nfe = $tx ? $nfeByTx->get($tx->id) : null;
            $columns[$columnId]['cards'][] = $this->jobSheetToCard($js, $tx, $nfe);
        }

        return array_values($columns);
    }

    /**
     * Onda 3 (Wave Z-2 backend) — expand `venda_derivada` com breakdown
     * items_list / items_summary / fiscal pro drawer Cowork-styled.
     *
     * Backward compat: se sell_lines vazio retorna items_list=[] + summary zerado
     * (não quebra Worker B Onda 5 que checa só presença de venda_derivada).
     */
    private function buildVendaDerivadaPayload(\App\Transaction $tx, $nfe = null): array
    {
        $itemsList = [];
        $productsCount = 0;
        $productsTotal = 0.0;
        $servicesCount = 0;
        $servicesTotal = 0.0;
        $taxTotal = 0.0;
        $discountTotal = 0.0;

        foreach ($tx->sell_lines ?? [] as $line) {
            // Convenção UltimatePOS — enable_stock=1 → produto físico, 0 → serviço.
            // Ver app/Utils/ProductUtil.php:361/406 (uso histórico canonical).
            $product = $line->product;
            $isService = $product ? ((int) $product->enable_stock === 0) : false;
            $type = $isService ? 'service' : 'product';

            $qty = (float) ($line->quantity ?? 0);
            $unitPrice = (float) ($line->unit_price_inc_tax ?? $line->unit_price ?? 0);
            $subtotal = round($qty * $unitPrice, 2);

            // Discount per-line (line_discount_amount * qty se fixed; % se percentage).
            $lineDiscount = 0.0;
            if (! empty($line->line_discount_amount) && ! empty($line->line_discount_type)) {
                if ($line->line_discount_type === 'fixed') {
                    $lineDiscount = (float) $line->line_discount_amount * $qty;
                } elseif ($line->line_discount_type === 'percentage') {
                    $lineDiscount = ($subtotal * (float) $line->line_discount_amount) / 100;
                }
            }

            // Tax per-line (item_tax é por 1 unidade — multiplica por qty).
            $lineTax = (float) ($line->item_tax ?? 0) * $qty;

            $itemsList[] = [
                'type' => $type,
                'name' => $product?->name ?? '—',
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ];

            if ($isService) {
                $servicesCount++;
                $servicesTotal += $subtotal;
            } else {
                $productsCount++;
                $productsTotal += $subtotal;
            }
            $taxTotal += $lineTax;
            $discountTotal += $lineDiscount;
        }

        return [
            'id' => $tx->id,
            'invoice_no' => $tx->invoice_no,
            'final_total' => (float) $tx->final_total,
            'transaction_date' => $tx->transaction_date?->toDateString(),
            'items_list' => $itemsList,
            'items_summary' => [
                'products_count' => $productsCount,
                'products_total' => round($productsTotal, 2),
                'services_count' => $servicesCount,
                'services_total' => round($servicesTotal, 2),
                'tax_total' => round($taxTotal, 2),
                'discount_total' => round($discountTotal, 2),
            ],
            'fiscal' => $nfe ? [
                'status' => $nfe->status,
                'modelo' => $nfe->modelo !== null ? (string) $nfe->modelo : null,
                'chave' => $nfe->chave_44,
                'danfe_url' => '/danfe/'.$nfe->id,
            ] : null,
        ];
    }

    private function jobSheetToCard(JobSheet $js, ?\App\Transaction $vendaDerivada = null, $nfe = null): array
    {
        $tech = $js->technician;
        $brand = $js->Brand;
        $deviceModel = $js->deviceModel ?? null;

        $executorName = $tech ? trim(($tech->first_name ?? '').' '.($tech->last_name ?? '')) : null;
        if ($executorName === '') {
            $executorName = $tech?->username;
        }

        $estimated = $js->estimated_cost !== null ? (int) round((float) $js->estimated_cost) : null;
        $isCompleted = $js->status?->is_completed_status ?? false;

        return [
            'id' => $js->id,
            // Genérico shared:
            //  - code: identificador legível (placa pra auto, nº OS pra com.visual, código serviço pra costureira)
            //  - item: descrição do objeto sendo processado (carro/arte/peça)
            //  - brand: marca/categoria (genérico no BD)
            //  - usage_meter / usage_unit: medida de uso (km/m²/horas) — null se não aplicável
            //  - slot / area: posição física na produção (box/elevador/mesa/máquina) — vertical configura
            //  - executor: pessoa atribuída (mecânico/designer/instalador/costureira)
            'code' => $js->serial_no ?: $js->job_sheet_no,
            'item' => $deviceModel?->name ?? '—',
            'brand' => $brand?->name ?? '—',
            'usage_meter' => null,    // hoje hardcoded 0; vertical OficinaAuto pode preencher km via custom_field
            'usage_unit' => null,     // 'km' | 'm²' | 'h' — vertical define
            'executor' => $executorName,
            'executor_initials' => $this->initials($executorName),
            'wait' => $js->created_at ? Carbon::parse($js->created_at)->diffForHumans(['short' => true]) : null,
            'slot' => null,
            'area' => null,
            'pending_approval' => false,
            'approved' => $isCompleted,
            'status_label' => $isCompleted ? 'Aguardando retirada' : null,
            'quote_total' => $estimated,
            // ADR 0192 — Integração Vendas × Oficina (A1 KB-9.75).
            // Card "Esta OS gerou venda #V-NNNN" renderizado no drawer Onda 5
            // quando coluna='pronto' AND venda_derivada !== null.
            //
            // Onda 3 (Wave Z-2 backend) expand: items_list / items_summary / fiscal
            // pra breakdown Cowork. Backward compat: keys core (id/invoice_no/
            // final_total/transaction_date) preservadas — Worker B Onda 5 segue OK.
            'venda_derivada' => $vendaDerivada ? $this->buildVendaDerivadaPayload($vendaDerivada, $nfe) : null,
        ];
    }

    private function initials(?string $name): ?string
    {
        if (! $name) {
            return null;
        }
        $parts = preg_split('/\s+/', trim($name));
        if (! $parts) {
            return null;
        }
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1] ?? '', 0, 1) : '';
        return mb_strtoupper($first.$last);
    }

    private function renderMock(array $repairSettings)
    {
        return Inertia::render('Repair/ProducaoOficina/Index', [
            'columns' => $this->mockColumns(),
            'totals' => [
                'os' => 17,
                'pending_approval' => 3,
            ],
            'data_source' => 'mock',
            'slot_config' => $repairSettings['slots'],
            'label_overrides' => $repairSettings['labels'],
        ]);
    }

    /**
     * Mock data — preserva fixture automotivo histórico (data shape estabelecido
     * na PR #363, 2026-05-09) mas usando keys genéricas. Vertical OficinaAuto
     * vê "Civic 2019 / Honda" via labelOverrides; verticais novas substituem o
     * mock no Modules/<Vertical>/Tests/ próprio.
     */
    private function mockColumns(): array
    {
        return [
            [
                'id' => 'recepcao',
                'label' => 'Recepção',
                'tone' => 'slate',
                'cards' => [
                    ['code' => 'RUI-2A45', 'item' => 'Civic 2019', 'brand' => 'Honda', 'usage_meter' => 78420, 'usage_unit' => 'km', 'executor' => 'Carlos R.', 'executor_initials' => 'CR', 'wait' => 'há 12min', 'slot' => null, 'pending_approval' => false],
                    ['code' => 'QPF-7B12', 'item' => 'Onix 2022', 'brand' => 'Chevrolet', 'usage_meter' => 42100, 'usage_unit' => 'km', 'executor' => 'Diego M.', 'executor_initials' => 'DM', 'wait' => 'há 38min', 'slot' => null, 'pending_approval' => false],
                    ['code' => 'SVE-9C03', 'item' => 'HB20 2020', 'brand' => 'Hyundai', 'usage_meter' => 105880, 'usage_unit' => 'km', 'executor' => 'Carlos R.', 'executor_initials' => 'CR', 'wait' => 'há 1h', 'slot' => null, 'pending_approval' => false],
                ],
            ],
            [
                'id' => 'diagnostico',
                'label' => 'Diagnóstico',
                'tone' => 'blue',
                'cards' => [
                    ['code' => 'TPL-3D88', 'item' => 'Corolla 2018', 'brand' => 'Toyota', 'usage_meter' => 156300, 'usage_unit' => 'km', 'executor' => 'João P.', 'executor_initials' => 'JP', 'slot' => 'B1', 'pending_approval' => false],
                    ['code' => 'UNK-5E27', 'item' => 'Polo 2021', 'brand' => 'Volkswagen', 'usage_meter' => 31770, 'usage_unit' => 'km', 'executor' => 'Diego M.', 'executor_initials' => 'DM', 'slot' => 'B2', 'pending_approval' => false],
                    ['code' => 'VWP-1F94', 'item' => 'Sandero 2017', 'brand' => 'Renault', 'usage_meter' => 198450, 'usage_unit' => 'km', 'executor' => 'João P.', 'executor_initials' => 'JP', 'slot' => 'E1', 'pending_approval' => false],
                    ['code' => 'WXR-8G56', 'item' => 'Yaris 2020', 'brand' => 'Toyota', 'usage_meter' => 67220, 'usage_unit' => 'km', 'executor' => 'Carlos R.', 'executor_initials' => 'CR', 'slot' => 'B3', 'pending_approval' => false],
                ],
            ],
            [
                'id' => 'aguardando-pecas',
                'label' => 'Aguardando peças',
                'tone' => 'amber',
                'cards' => [
                    ['code' => 'YTQ-4H73', 'item' => 'Strada 2019', 'brand' => 'Fiat', 'usage_meter' => 88350, 'usage_unit' => 'km', 'executor' => null, 'executor_initials' => null, 'slot' => null, 'pending_approval' => true, 'quote_total' => 2480, 'quote_items' => 4, 'quote_status' => 'Cliente não respondeu'],
                    ['code' => 'ZAB-6I20', 'item' => 'Compass 2021', 'brand' => 'Jeep', 'usage_meter' => 54110, 'usage_unit' => 'km', 'executor' => 'Diego M.', 'executor_initials' => 'DM', 'wait' => '3 dias', 'eta' => 'sex.', 'pending_approval' => false],
                    ['code' => 'ACE-2J15', 'item' => 'Tracker 2018', 'brand' => 'Chevrolet', 'usage_meter' => 119880, 'usage_unit' => 'km', 'executor' => 'João P.', 'executor_initials' => 'JP', 'wait' => '5 dias', 'eta' => 'seg.', 'pending_approval' => false],
                ],
            ],
            [
                'id' => 'em-execucao',
                'label' => 'Em execução',
                'tone' => 'violet',
                'cards' => [
                    ['code' => 'BDF-9K61', 'item' => 'Kicks 2022', 'brand' => 'Nissan', 'usage_meter' => 28500, 'usage_unit' => 'km', 'executor' => 'João P.', 'executor_initials' => 'JP', 'slot' => 'B1', 'pending_approval' => false],
                    ['code' => 'CGE-3L08', 'item' => 'Hilux 2019', 'brand' => 'Toyota', 'usage_meter' => 142700, 'usage_unit' => 'km', 'executor' => 'Carlos R.', 'executor_initials' => 'CR', 'slot' => 'E1', 'pending_approval' => false],
                    ['code' => 'DHJ-7M52', 'item' => 'Argo 2021', 'brand' => 'Fiat', 'usage_meter' => 49330, 'usage_unit' => 'km', 'executor' => 'Diego M.', 'executor_initials' => 'DM', 'slot' => 'B2', 'pending_approval' => false],
                    ['code' => 'EIK-1N99', 'item' => 'Renegade 2020', 'brand' => 'Jeep', 'usage_meter' => 71060, 'usage_unit' => 'km', 'executor' => 'João P.', 'executor_initials' => 'JP', 'slot' => 'E2', 'pending_approval' => false],
                ],
            ],
            [
                'id' => 'pronto',
                'label' => 'Pronto',
                'tone' => 'emerald',
                'cards' => [
                    ['code' => 'FJL-5O44', 'item' => 'Cronos 2020', 'brand' => 'Fiat', 'usage_meter' => 88100, 'usage_unit' => 'km', 'executor' => null, 'executor_initials' => null, 'status_label' => 'Aguardando retirada', 'approved' => true],
                    ['code' => 'GKM-8P77', 'item' => 'Mobi 2018', 'brand' => 'Fiat', 'usage_meter' => 64220, 'usage_unit' => 'km', 'executor' => null, 'executor_initials' => null, 'status_label' => 'Retirado às 14:30', 'approved' => true],
                    ['code' => 'HLN-2Q31', 'item' => 'Saveiro 2019', 'brand' => 'Volkswagen', 'usage_meter' => 121450, 'usage_unit' => 'km', 'executor' => null, 'executor_initials' => null, 'status_label' => 'Aguardando retirada', 'approved' => true],
                ],
            ],
        ];
    }
}
