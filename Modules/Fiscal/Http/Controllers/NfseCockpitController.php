<?php

namespace Modules\Fiscal\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\NfeBrasil\Models\NfseEmissao;

/**
 * Cockpit NFS-e (sub-página 3 do design KB-9.75).
 *
 * Lê NfseEmissao (modelo 56 nacional NT 2024-001 — substitui emissores
 * municipais legacy). HasBusinessScope global scope (ADR 0093).
 *
 * Sem mutações no PR. Botões cancelar/retransmitir desabilitados.
 */
class NfseCockpitController extends Controller
{
    public function index(Request $request): Response
    {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.nfse.view')) {
            abort(403, 'Sem permissão fiscal.nfse.view');
        }

        $filters = [
            'search' => (string) $request->input('search', ''),
            'status' => (string) $request->input('status', 'todas'),
            'mes'    => (string) $request->input('mes', now()->format('Y-m')),
        ];

        return Inertia::render('Fiscal/Nfse', [
            'filters' => $filters,
            'counts'  => $this->computeCounts($filters),
            'rows'    => Inertia::defer(fn () => $this->buildRowsPayload($filters)),
        ]);
    }

    protected function computeCounts(array $filters): array
    {
        $base = NfseEmissao::query();

        if (! empty($filters['mes'])) {
            try {
                $start = \Carbon\Carbon::parse($filters['mes'] . '-01')->startOfMonth();
                $end   = $start->copy()->endOfMonth();
                $base->whereBetween('emitted_at', [$start, $end]);
            } catch (\Throwable $e) {
                // ignora mes inválido
            }
        }

        return [
            'total'        => (clone $base)->count(),
            'autorizadas'  => (clone $base)->where('status', NfseEmissao::STATUS_AUTHORIZED)->count(),
            'rejeitadas'   => (clone $base)->where('status', NfseEmissao::STATUS_REJECTED)->count(),
            'processando'  => (clone $base)->whereIn('status', [NfseEmissao::STATUS_PENDING, NfseEmissao::STATUS_SENT])->count(),
            'canceladas'   => (clone $base)->where('status', NfseEmissao::STATUS_CANCELLED)->count(),
            'faturamento'  => (float) (clone $base)->where('status', NfseEmissao::STATUS_AUTHORIZED)->sum('value_servico'),
        ];
    }

    protected function buildRowsPayload(array $filters): array
    {
        $query = NfseEmissao::query()->orderByDesc('emitted_at');

        if (! empty($filters['mes'])) {
            try {
                $start = \Carbon\Carbon::parse($filters['mes'] . '-01')->startOfMonth();
                $end   = $start->copy()->endOfMonth();
                $query->whereBetween('emitted_at', [$start, $end]);
            } catch (\Throwable $e) {
            }
        }

        if ($filters['status'] === 'autorizadas') $query->where('status', NfseEmissao::STATUS_AUTHORIZED);
        if ($filters['status'] === 'rejeitadas')  $query->where('status', NfseEmissao::STATUS_REJECTED);
        if ($filters['status'] === 'processando') $query->whereIn('status', [NfseEmissao::STATUS_PENDING, NfseEmissao::STATUS_SENT]);
        if ($filters['status'] === 'canceladas')  $query->where('status', NfseEmissao::STATUS_CANCELLED);

        if ($filters['search'] !== '') {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('numero_nfse', 'like', "%{$s}%")
                  ->orWhere('codigo_verificacao', 'like', "%{$s}%")
                  ->orWhere('cpf_cnpj_tomador', 'like', '%' . preg_replace('/\D/', '', $s) . '%');
            });
        }

        $paginator = $query->paginate(50);

        return [
            'data' => $paginator->getCollection()->map(fn (NfseEmissao $e) => $this->mapRow($e))->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
            ],
        ];
    }

    protected function mapRow(NfseEmissao $e): array
    {
        return [
            'id'                 => $e->id,
            'num'                => $e->numero_nfse ?? '—',
            'codigoVerificacao'  => $e->codigo_verificacao ?? null,
            'tomador'            => $e->nome_tomador ?? '—',
            'documentoTomador'   => $e->cpf_cnpj_tomador ?? null,
            'municipio'          => $e->municipio_prestacao ?? null,
            'codServico'         => $e->codigo_servico ?? null,
            'aliquotaIss'        => (float) ($e->aliquota_iss ?? 0),
            'valueServico'       => (float) ($e->value_servico ?? 0),
            'valueIss'           => (float) ($e->value_iss ?? 0),
            'status'             => $e->status,
            'errorMsg'           => $e->error_msg ?? null,
            'emittedAtIso'       => $e->emitted_at?->toIso8601String(),
            'when'               => $e->emitted_at?->format('d/m H:i'),
        ];
    }
}
