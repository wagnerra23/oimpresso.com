<?php

namespace Modules\Repair\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Modules\Repair\Entities\JobSheet;
use Modules\Repair\Entities\RepairStatus;

/**
 * Produção · Oficina — kanban da oficina (read-mostly).
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

    public function index()
    {
        $business_id = (int) request()->session()->get('user.business_id');

        $statuses = RepairStatus::where('business_id', $business_id)
            ->orderBy('sort_order', 'asc')
            ->get();

        if ($statuses->isEmpty()) {
            return $this->renderMock();
        }

        $jobSheets = JobSheet::with(['status', 'technician', 'Brand', 'deviceModel'])
            ->where('business_id', $business_id)
            ->whereIn('status_id', $statuses->pluck('id'))
            ->orderBy('created_at', 'desc')
            ->limit(200)
            ->get();

        if ($jobSheets->isEmpty()) {
            return $this->renderMock();
        }

        $statusToColumn = $this->mapStatusesToColumns($statuses);
        $columns = $this->buildColumns($jobSheets, $statusToColumn);

        return Inertia::render('Repair/ProducaoOficina/Index', [
            'columns' => $columns,
            'totals' => [
                'os' => $jobSheets->count(),
                'aguardando_aprovacao' => collect($columns)
                    ->flatMap(fn ($c) => $c['cards'])
                    ->filter(fn ($card) => $card['aprovacao_pendente'] ?? false)
                    ->count(),
            ],
            'data_source' => 'live',
        ]);
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

        foreach ($jobSheets as $js) {
            $columnId = $statusToColumn[$js->status_id] ?? null;
            if ($columnId === null || ! isset($columns[$columnId])) {
                continue;
            }
            $columns[$columnId]['cards'][] = $this->jobSheetToCard($js);
        }

        return array_values($columns);
    }

    private function jobSheetToCard(JobSheet $js): array
    {
        $tech = $js->technician;
        $brand = $js->Brand;
        $deviceModel = $js->deviceModel ?? null;

        $mecanicoName = $tech ? trim(($tech->first_name ?? '').' '.($tech->last_name ?? '')) : null;
        if ($mecanicoName === '') {
            $mecanicoName = $tech?->username;
        }

        $estimated = $js->estimated_cost !== null ? (int) round((float) $js->estimated_cost) : null;
        $isCompleted = $js->status?->is_completed_status ?? false;

        return [
            'plate' => $js->serial_no ?: $js->job_sheet_no,
            'vehicle' => $deviceModel?->name ?? '—',
            'brand' => $brand?->name ?? '—',
            'km' => 0,
            'mecanico' => $mecanicoName,
            'mecanico_initials' => $this->initials($mecanicoName),
            'wait' => $js->created_at ? Carbon::parse($js->created_at)->diffForHumans(['short' => true]) : null,
            'box' => null,
            'aprovacao_pendente' => false,
            'aprovado' => $isCompleted,
            'status_label' => $isCompleted ? 'Aguardando retirada' : null,
            'orcamento_total' => $estimated,
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

    private function renderMock()
    {
        return Inertia::render('Repair/ProducaoOficina/Index', [
            'columns' => $this->mockColumns(),
            'totals' => [
                'os' => 17,
                'aguardando_aprovacao' => 3,
            ],
            'data_source' => 'mock',
        ]);
    }

    private function mockColumns(): array
    {
        return [
            [
                'id' => 'recepcao',
                'label' => 'Recepção',
                'tone' => 'slate',
                'cards' => [
                    ['plate' => 'RUI-2A45', 'vehicle' => 'Civic 2019', 'brand' => 'Honda', 'km' => 78420, 'mecanico' => 'Carlos R.', 'mecanico_initials' => 'CR', 'wait' => 'há 12min', 'box' => null, 'aprovacao_pendente' => false],
                    ['plate' => 'QPF-7B12', 'vehicle' => 'Onix 2022', 'brand' => 'Chevrolet', 'km' => 42100, 'mecanico' => 'Diego M.', 'mecanico_initials' => 'DM', 'wait' => 'há 38min', 'box' => null, 'aprovacao_pendente' => false],
                    ['plate' => 'SVE-9C03', 'vehicle' => 'HB20 2020', 'brand' => 'Hyundai', 'km' => 105880, 'mecanico' => 'Carlos R.', 'mecanico_initials' => 'CR', 'wait' => 'há 1h', 'box' => null, 'aprovacao_pendente' => false],
                ],
            ],
            [
                'id' => 'diagnostico',
                'label' => 'Diagnóstico',
                'tone' => 'blue',
                'cards' => [
                    ['plate' => 'TPL-3D88', 'vehicle' => 'Corolla 2018', 'brand' => 'Toyota', 'km' => 156300, 'mecanico' => 'João P.', 'mecanico_initials' => 'JP', 'box' => 'B1', 'aprovacao_pendente' => false],
                    ['plate' => 'UNK-5E27', 'vehicle' => 'Polo 2021', 'brand' => 'Volkswagen', 'km' => 31770, 'mecanico' => 'Diego M.', 'mecanico_initials' => 'DM', 'box' => 'B2', 'aprovacao_pendente' => false],
                    ['plate' => 'VWP-1F94', 'vehicle' => 'Sandero 2017', 'brand' => 'Renault', 'km' => 198450, 'mecanico' => 'João P.', 'mecanico_initials' => 'JP', 'box' => 'E1', 'aprovacao_pendente' => false],
                    ['plate' => 'WXR-8G56', 'vehicle' => 'Yaris 2020', 'brand' => 'Toyota', 'km' => 67220, 'mecanico' => 'Carlos R.', 'mecanico_initials' => 'CR', 'box' => 'B3', 'aprovacao_pendente' => false],
                ],
            ],
            [
                'id' => 'aguardando-pecas',
                'label' => 'Aguardando peças',
                'tone' => 'amber',
                'cards' => [
                    ['plate' => 'YTQ-4H73', 'vehicle' => 'Strada 2019', 'brand' => 'Fiat', 'km' => 88350, 'mecanico' => null, 'mecanico_initials' => null, 'box' => null, 'aprovacao_pendente' => true, 'orcamento_total' => 2480, 'orcamento_pecas' => 4, 'orcamento_status' => 'Cliente não respondeu'],
                    ['plate' => 'ZAB-6I20', 'vehicle' => 'Compass 2021', 'brand' => 'Jeep', 'km' => 54110, 'mecanico' => 'Diego M.', 'mecanico_initials' => 'DM', 'wait' => '3 dias', 'eta' => 'sex.', 'aprovacao_pendente' => false],
                    ['plate' => 'ACE-2J15', 'vehicle' => 'Tracker 2018', 'brand' => 'Chevrolet', 'km' => 119880, 'mecanico' => 'João P.', 'mecanico_initials' => 'JP', 'wait' => '5 dias', 'eta' => 'seg.', 'aprovacao_pendente' => false],
                ],
            ],
            [
                'id' => 'em-execucao',
                'label' => 'Em execução',
                'tone' => 'violet',
                'cards' => [
                    ['plate' => 'BDF-9K61', 'vehicle' => 'Kicks 2022', 'brand' => 'Nissan', 'km' => 28500, 'mecanico' => 'João P.', 'mecanico_initials' => 'JP', 'box' => 'B1', 'aprovacao_pendente' => false],
                    ['plate' => 'CGE-3L08', 'vehicle' => 'Hilux 2019', 'brand' => 'Toyota', 'km' => 142700, 'mecanico' => 'Carlos R.', 'mecanico_initials' => 'CR', 'box' => 'E1', 'aprovacao_pendente' => false],
                    ['plate' => 'DHJ-7M52', 'vehicle' => 'Argo 2021', 'brand' => 'Fiat', 'km' => 49330, 'mecanico' => 'Diego M.', 'mecanico_initials' => 'DM', 'box' => 'B2', 'aprovacao_pendente' => false],
                    ['plate' => 'EIK-1N99', 'vehicle' => 'Renegade 2020', 'brand' => 'Jeep', 'km' => 71060, 'mecanico' => 'João P.', 'mecanico_initials' => 'JP', 'box' => 'E2', 'aprovacao_pendente' => false],
                ],
            ],
            [
                'id' => 'pronto',
                'label' => 'Pronto',
                'tone' => 'emerald',
                'cards' => [
                    ['plate' => 'FJL-5O44', 'vehicle' => 'Cronos 2020', 'brand' => 'Fiat', 'km' => 88100, 'mecanico' => null, 'mecanico_initials' => null, 'status_label' => 'Aguardando retirada', 'aprovado' => true],
                    ['plate' => 'GKM-8P77', 'vehicle' => 'Mobi 2018', 'brand' => 'Fiat', 'km' => 64220, 'mecanico' => null, 'mecanico_initials' => null, 'status_label' => 'Retirado às 14:30', 'aprovado' => true],
                    ['plate' => 'HLN-2Q31', 'vehicle' => 'Saveiro 2019', 'brand' => 'Volkswagen', 'km' => 121450, 'mecanico' => null, 'mecanico_initials' => null, 'status_label' => 'Aguardando retirada', 'aprovado' => true],
                ],
            ],
        ];
    }
}
