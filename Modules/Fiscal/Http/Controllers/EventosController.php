<?php

namespace Modules\Fiscal\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\NfeBrasil\Models\NfeEvento;

/**
 * Eventos fiscais (sub-página 5 do design KB-9.75).
 *
 * Timeline append-only de eventos SEFAZ aplicados a NfeEmissao:
 *  - 110110 CC-e (Carta de Correção Eletrônica)
 *  - 110111 Cancelamento (cStat 135 = homologado)
 *  - 110140 EPEC (Contingência)
 *  - 210200/210210/210220/210240 Manifestação destinatário
 *
 * Sem mutação no PR — eventos são append-only por natureza (LGPD Art. 37
 * + CONFAZ SINIEF 07/2005 Art. 14). Mutações em sub-página NF-e (botão
 * "Cancelar" / "CC-e" em PR de ações).
 */
class EventosController extends Controller
{
    /** Mapa tipo (tpEvento NFe) → label PT-BR + classe CSS. */
    public const TIPOS = [
        '110110' => ['kind' => 'cce',      'label' => 'Carta de correção'],
        '110111' => ['kind' => 'cancel',   'label' => 'Cancelamento'],
        '110140' => ['kind' => 'epec',     'label' => 'EPEC (contingência)'],
        '210200' => ['kind' => 'manifest', 'label' => 'Manifesto · Confirmação'],
        '210210' => ['kind' => 'manifest', 'label' => 'Manifesto · Ciência'],
        '210220' => ['kind' => 'manifest', 'label' => 'Manifesto · Desconhecimento'],
        '210240' => ['kind' => 'manifest', 'label' => 'Manifesto · Não realizada'],
    ];

    public function index(Request $request): Response
    {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.access')) {
            abort(403, 'Sem permissão fiscal.access');
        }

        $filters = [
            'kind' => (string) $request->input('kind', 'todos'),
            'dias' => (int) $request->input('dias', 30),
        ];

        return Inertia::render('Fiscal/Eventos', [
            'filters' => $filters,
            'tipos'   => self::TIPOS,
            'counts'  => $this->computeCounts($filters),
            'rows'    => Inertia::defer(fn () => $this->buildRowsPayload($filters)),
        ]);
    }

    protected function computeCounts(array $filters): array
    {
        $cutoff = now()->subDays(max(1, $filters['dias']));

        $base = NfeEvento::query()->where('created_at', '>=', $cutoff);

        $cceTipos      = ['110110'];
        $cancelTipos   = ['110111'];
        $inutTipos     = []; // inutilização não vive em NfeEvento — vive em NfeInutilizacao (sub-página separada)
        $epecTipos     = ['110140'];
        $manifestTipos = ['210200', '210210', '210220', '210240'];

        return [
            'total'     => (clone $base)->count(),
            'cce'       => (clone $base)->whereIn('tipo', $cceTipos)->count(),
            'cancel'    => (clone $base)->whereIn('tipo', $cancelTipos)->count(),
            'epec'      => (clone $base)->whereIn('tipo', $epecTipos)->count(),
            'manifest'  => (clone $base)->whereIn('tipo', $manifestTipos)->count(),
            'autorizados' => (clone $base)->where('status', 'autorizado')->count(),
        ];
    }

    protected function buildRowsPayload(array $filters): array
    {
        $cutoff = now()->subDays(max(1, $filters['dias']));

        $query = NfeEvento::query()
            ->with(['emissao:id,numero,modelo,chave_44'])
            ->where('created_at', '>=', $cutoff)
            ->orderByDesc('created_at');

        if ($filters['kind'] !== 'todos') {
            $tiposPraKind = collect(self::TIPOS)
                ->filter(fn ($meta) => $meta['kind'] === $filters['kind'])
                ->keys()
                ->all();
            if (! empty($tiposPraKind)) {
                $query->whereIn('tipo', $tiposPraKind);
            }
        }

        $paginator = $query->paginate(50);

        return [
            'data' => $paginator->getCollection()->map(fn (NfeEvento $e) => $this->mapRow($e))->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
            ],
        ];
    }

    protected function mapRow(NfeEvento $e): array
    {
        $tipoMeta = self::TIPOS[$e->tipo] ?? ['kind' => 'manifest', 'label' => "Tipo {$e->tipo}"];

        return [
            'id'             => $e->id,
            'tipo'           => $e->tipo,
            'kind'           => $tipoMeta['kind'],
            'label'          => $tipoMeta['label'],
            'status'         => $e->status,
            'cstatEvento'    => (int) ($e->cstat_evento ?? 0),
            'justificativa'  => mb_substr((string) ($e->justificativa ?? ''), 0, 200),
            'createdAtIso'   => $e->created_at?->toIso8601String(),
            'when'           => $e->created_at?->format('d/m H:i'),
            'emissao'        => $e->emissao ? [
                'id'     => $e->emissao->id,
                'numero' => $e->emissao->numero,
                'modelo' => (int) $e->emissao->modelo,
                'chave'  => $e->emissao->chave_44,
            ] : null,
        ];
    }
}
