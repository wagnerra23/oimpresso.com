<?php

namespace Modules\Fiscal\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\NFSe\Models\NfseEmissao;

/**
 * Cockpit NFS-e (sub-página 3 do design KB-9.75).
 *
 * Lê NfseEmissao do Modules/NFSe — schema velho em prod (migration
 * `2026_05_01_000003_create_nfse_emissoes_table` batch 69).
 * NfseBusinessScope global scope (ADR 0093).
 *
 * **Schema race fix 2026-05-26 (Caminho A — task #12 sessão fiscal):**
 *
 * Originalmente importava `Modules\NfeBrasil\Models\NfseEmissao` (schema
 * NOVO — migration `2026_05_11_150001` batch 106 idempotente `Schema::hasTable`
 * → NUNCA rodou em prod pq tabela já existia). Controller usava colunas
 * inexistentes na tabela real → 500 em prod.
 *
 * Caminho A revertido pro Model NFSe alinhado com schema velho prod:
 *
 *   tomador_cnpj / tomador_cpf (separados, não `cpf_cnpj_tomador`)
 *   valor_servicos (não `value_servico`)
 *   valor_iss (não `value_iss`)
 *   numero (não `numero_nfse`)
 *   provider_codigo_verificacao (não `codigo_verificacao`)
 *   erro_mensagem (não `error_msg`)
 *   created_at (não `emitted_at` — schema velho sem essa coluna)
 *   lc116_codigo (não `codigo_servico`/`item_lc116`)
 *   tomador_nome (não `nome_tomador`)
 *   municipio_prestacao NÃO EXISTE no schema velho — payload retorna null
 *   status enum [rascunho|processando|emitida|cancelada|erro]
 *     (mapeado pra EN no payload Page Inertia pra preservar contrato front)
 *
 * Sem mutações no PR. Botões cancelar/retransmitir desabilitados.
 *
 * @see memory/sessions/2026-05-25-sessao-fiscal-sidebar-tests-ci.md task #12
 * @see memory/sessions/2026-05-26-levantamento-martinho-ready.md §B1
 */
class NfseCockpitController extends Controller
{
    // Status canônicos schema velho (Modules/NFSe migration 2026_05_01_000003)
    private const STATUS_AUTHORIZED = 'emitida';
    private const STATUS_REJECTED   = 'erro';
    private const STATUS_CANCELLED  = 'cancelada';
    private const STATUS_PENDING    = 'rascunho';
    private const STATUS_SENT       = 'processando';

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
                $base->whereBetween('created_at', [$start, $end]);
            } catch (\Throwable $e) {
                // ignora mes inválido
            }
        }

        return [
            'total'        => (clone $base)->count(),
            'autorizadas'  => (clone $base)->where('status', self::STATUS_AUTHORIZED)->count(),
            'rejeitadas'   => (clone $base)->where('status', self::STATUS_REJECTED)->count(),
            'processando'  => (clone $base)->whereIn('status', [self::STATUS_PENDING, self::STATUS_SENT])->count(),
            'canceladas'   => (clone $base)->where('status', self::STATUS_CANCELLED)->count(),
            'faturamento'  => (float) (clone $base)->where('status', self::STATUS_AUTHORIZED)->sum('valor_servicos'),
        ];
    }

    protected function buildRowsPayload(array $filters): array
    {
        $query = NfseEmissao::query()->orderByDesc('created_at');

        if (! empty($filters['mes'])) {
            try {
                $start = \Carbon\Carbon::parse($filters['mes'] . '-01')->startOfMonth();
                $end   = $start->copy()->endOfMonth();
                $query->whereBetween('created_at', [$start, $end]);
            } catch (\Throwable $e) {
            }
        }

        if ($filters['status'] === 'autorizadas') $query->where('status', self::STATUS_AUTHORIZED);
        if ($filters['status'] === 'rejeitadas')  $query->where('status', self::STATUS_REJECTED);
        if ($filters['status'] === 'processando') $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_SENT]);
        if ($filters['status'] === 'canceladas')  $query->where('status', self::STATUS_CANCELLED);

        if ($filters['search'] !== '') {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('numero', 'like', "%{$s}%")
                  ->orWhere('provider_codigo_verificacao', 'like', "%{$s}%")
                  ->orWhere('tomador_nome', 'like', "%{$s}%")
                  ->orWhere('tomador_cnpj', 'like', "%{$s}%")
                  ->orWhere('tomador_cpf', 'like', "%{$s}%");
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
            'num'                => $e->numero ?? '—',
            'codigoVerificacao'  => $e->provider_codigo_verificacao ?? null,
            'tomador'            => $e->tomador_nome ?? '—',
            'documentoTomador'   => $e->tomador_cnpj ?? $e->tomador_cpf ?? null,
            'municipio'          => null, // schema velho não tem municipio_prestacao
            'codServico'         => $e->lc116_codigo ?? null,
            'aliquotaIss'        => (float) ($e->aliquota_iss ?? 0),
            'valueServico'       => (float) ($e->valor_servicos ?? 0),
            'valueIss'           => (float) ($e->valor_iss ?? 0),
            'status'             => $this->mapStatusToFront($e->status),
            'errorMsg'           => $e->erro_mensagem ?? null,
            'emittedAtIso'       => $e->created_at?->toIso8601String(),
            'when'               => $e->created_at?->format('d/m H:i'),
        ];
    }

    /**
     * Mapeia status PT-BR (schema velho) → EN (contrato Page Inertia Fiscal/Nfse.tsx).
     *
     * Page React foi construída esperando os tokens do schema NOVO ('authorized',
     * 'rejected', 'pending', 'sent', 'cancelled'). Como Caminho A reverte só o
     * backend mantendo o frontend intacto, esta tradução preserva o contrato.
     */
    private function mapStatusToFront(string $statusPtBr): string
    {
        return match ($statusPtBr) {
            self::STATUS_AUTHORIZED => 'authorized',
            self::STATUS_REJECTED   => 'rejected',
            self::STATUS_PENDING    => 'pending',
            self::STATUS_SENT       => 'sent',
            self::STATUS_CANCELLED  => 'cancelled',
            default                 => $statusPtBr,
        };
    }
}
