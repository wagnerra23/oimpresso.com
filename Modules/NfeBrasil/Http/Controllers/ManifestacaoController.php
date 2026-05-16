<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Modules\NfeBrasil\Jobs\BuscarDfesRecebidosJob;
use Modules\NfeBrasil\Models\NfeDfeEvento;
use Modules\NfeBrasil\Models\NfeDfeNsuState;
use Modules\NfeBrasil\Models\NfeDfeRecebido;
use Modules\NfeBrasil\Services\Manifestacao\ManifestacaoService;

/**
 * US-NFE-052 (ADR 0116 caso Gold) · UI Manifestação do Destinatário.
 *
 * Permissão: `nfe.manifestacao.view` (index) + `nfe.manifestacao.manage` (mutations).
 * Pattern: Inertia (index = render; mutações = redirect+flash). ADR 0029.
 *
 * Multi-tenant: NfeDfeRecebido usa HasBusinessScope (ADR 0093 Tier 0). Cross-tenant
 * guard explícito nos POSTs (where business_id) — defesa em profundidade.
 */
class ManifestacaoController extends Controller
{
    public function __construct(
        private readonly ManifestacaoService $service,
    ) {}

    /**
     * GET /nfe-brasil/manifestacao
     *
     * Props caras (`itens` paginate, `kpis` 3 counts, `nsuState`) usam
     * `Inertia::defer()` pra pular execução em partial reloads que pedem
     * `only:['filters']`/`['permissions']`. Skill `inertia-defer-default`.
     */
    public function index(Request $request): Response
    {
        abort_unless($this->canView(), 403);

        $businessId = (int) session('user.business_id');
        $statusFilter = $request->string('status')->toString();
        $qFilter = trim($request->string('q')->toString());

        return Inertia::render('NfeBrasil/Manifestacao/Index', [
            'itens'    => Inertia::defer(fn () => $this->buildItensPayload($statusFilter, $qFilter)),
            'kpis'     => Inertia::defer(fn () => $this->buildKpisPayload($businessId)),
            'nsuState' => Inertia::defer(fn () => $this->buildNsuStatePayload($businessId)),
            'filters'  => [
                'status' => $statusFilter ?: null,
                'q'      => $qFilter ?: null,
            ],
            'permissions' => [
                'canManage' => $this->canManage(),
            ],
        ]);
    }

    private function buildItensPayload(string $statusFilter, string $qFilter)
    {
        $query = NfeDfeRecebido::query()
            ->orderByRaw('CASE WHEN status_manifestacao = "pendente" THEN 0 ELSE 1 END')
            ->orderBy('prazo_confirmacao_em', 'asc');

        if ($statusFilter !== '') {
            $query->where('status_manifestacao', $statusFilter);
        }
        if ($qFilter !== '') {
            $query->where(function ($w) use ($qFilter) {
                $w->where('cnpj_emitente', 'like', "%{$qFilter}%")
                  ->orWhere('nome_emitente', 'like', "%{$qFilter}%")
                  ->orWhere('chave_44', 'like', "%{$qFilter}%");
            });
        }

        return $query->paginate(50)->withQueryString()->through(fn (NfeDfeRecebido $dfe) => [
            'id'                   => $dfe->id,
            'chave_44'             => $dfe->chave_44,
            'cnpj_emitente'        => $dfe->cnpj_emitente,
            'nome_emitente'        => $dfe->nome_emitente,
            'valor_total'          => (float) $dfe->valor_total,
            'data_emissao'         => optional($dfe->data_emissao)?->toIso8601String(),
            'status_manifestacao'  => $dfe->status_manifestacao,
            'manifestado_em'       => optional($dfe->manifestado_em)?->toIso8601String(),
            'prazo_confirmacao_em' => optional($dfe->prazo_confirmacao_em)?->toDateString(),
            'dias_ate_prazo'       => $dfe->diasAtePrazoConfirmacao(),
        ]);
    }

    private function buildKpisPayload(int $businessId): array
    {
        return [
            'pendentes'        => NfeDfeRecebido::where('business_id', $businessId)
                ->where('status_manifestacao', 'pendente')->count(),
            'vencendo_7d'      => NfeDfeRecebido::where('business_id', $businessId)
                ->where('status_manifestacao', 'pendente')
                ->whereDate('prazo_confirmacao_em', '<=', now()->addDays(7))
                ->count(),
            'confirmadas_mes'  => NfeDfeRecebido::where('business_id', $businessId)
                ->where('status_manifestacao', 'confirmada')
                ->whereBetween('manifestado_em', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
        ];
    }

    private function buildNsuStatePayload(int $businessId): ?array
    {
        $nsuState = NfeDfeNsuState::where('business_id', $businessId)->first();
        if (! $nsuState) {
            return null;
        }
        return [
            'last_nsu'          => (int) $nsuState->last_nsu,
            'ultimo_check_em'   => optional($nsuState->ultimo_check_em)?->toIso8601String(),
            'ultimo_lote_count' => (int) $nsuState->ultimo_lote_count,
        ];
    }

    /**
     * POST /nfe-brasil/manifestacao/{id}/cienciar
     */
    public function cienciar(int $id): RedirectResponse
    {
        return $this->aplicarEvento($id, 'cienciar');
    }

    /**
     * POST /nfe-brasil/manifestacao/{id}/confirmar
     */
    public function confirmar(int $id): RedirectResponse
    {
        return $this->aplicarEvento($id, 'confirmar');
    }

    /**
     * POST /nfe-brasil/manifestacao/{id}/desconhecer
     */
    public function desconhecer(int $id, Request $request): RedirectResponse
    {
        $request->validate(['justificativa' => 'required|string|min:15|max:255']);
        return $this->aplicarEvento($id, 'desconhecer', $request->string('justificativa')->toString());
    }

    /**
     * POST /nfe-brasil/manifestacao/{id}/nao-realizada
     */
    public function naoRealizada(int $id, Request $request): RedirectResponse
    {
        $request->validate(['justificativa' => 'required|string|min:15|max:255']);
        return $this->aplicarEvento($id, 'naoRealizada', $request->string('justificativa')->toString());
    }

    /**
     * POST /nfe-brasil/manifestacao/bulk/confirmar
     *
     * Loop sequencial (não Promise.all) — cada chave precisa nSeqEvento isolado;
     * paralelo geraria duplicidade (cstat 573).
     */
    public function bulkConfirmar(Request $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $ids = $request->input('ids', []);
        if (! is_array($ids) || count($ids) === 0) {
            return back()->with('error', 'Selecione ao menos 1 NF-e pra confirmar.');
        }

        $businessId = (int) session('user.business_id');
        $sucessos = 0;
        $falhas = 0;

        foreach ($ids as $id) {
            $dfe = NfeDfeRecebido::where('business_id', $businessId)
                ->where('id', (int) $id)
                ->where('status_manifestacao', 'pendente')
                ->first();
            if (! $dfe) {
                continue;
            }
            try {
                $this->service->confirmar($dfe);
                $sucessos++;
            } catch (\Throwable $e) {
                $falhas++;
                Log::warning('ManifestacaoController::bulkConfirmar falha em DFe', [
                    'dfe_id' => $dfe->id,
                    'erro'   => $e->getMessage(),
                ]);
            }
        }

        $msg = "{$sucessos} confirmada(s)";
        if ($falhas > 0) {
            $msg .= "; {$falhas} falharam — verificar /nfe-brasil/manifestacao";
        }
        return back()->with($falhas === 0 ? 'success' : 'error', $msg);
    }

    /**
     * POST /nfe-brasil/manifestacao/sync-now
     *
     * Dispatch job em fila (não sync — SEFAZ pode travar 30s+).
     */
    public function syncNow(): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $businessId = (int) session('user.business_id');
        BuscarDfesRecebidosJob::dispatch($businessId);

        return back()->with('success', 'Busca SEFAZ disparada — atualizando em alguns segundos.');
    }

    /**
     * GET /nfe-brasil/manifestacao/{id}/itens (JSON)
     *
     * Endpoint pra LinkedItens.tsx fetch lazy. Cross-tenant guard via where business_id.
     */
    public function listarItens(int $id): \Illuminate\Http\JsonResponse
    {
        abort_unless($this->canView(), 403);

        $businessId = (int) session('user.business_id');
        $dfe = NfeDfeRecebido::where('business_id', $businessId)
            ->where('id', $id)
            ->firstOrFail();

        $itens = $dfe->itens()->get(['id', 'ncm', 'cfop', 'descricao', 'quantidade', 'valor_unitario', 'valor_total'])
            ->map(fn ($i) => [
                'id'             => $i->id,
                'ncm'            => $i->ncm,
                'cfop'           => $i->cfop,
                'descricao'      => $i->descricao,
                'quantidade'     => (float) $i->quantidade,
                'valor_unitario' => (float) $i->valor_unitario,
                'valor_total'    => (float) $i->valor_total,
            ]);

        return response()->json(['itens' => $itens]);
    }

    /**
     * GET /nfe-brasil/manifestacao/{id}/eventos (JSON)
     *
     * Endpoint pra LinkedHistorico.tsx fetch lazy.
     */
    public function listarEventos(int $id): \Illuminate\Http\JsonResponse
    {
        abort_unless($this->canView(), 403);

        $businessId = (int) session('user.business_id');
        $dfe = NfeDfeRecebido::where('business_id', $businessId)
            ->where('id', $id)
            ->firstOrFail();

        $eventos = $dfe->eventos()->orderByDesc('created_at')
            ->get(['id', 'tipo', 'status', 'cstat_evento', 'created_at'])
            ->map(fn ($e) => [
                'id'           => $e->id,
                'tipo'         => $e->tipo,
                'status'       => $e->status,
                'cstat_evento' => $e->cstat_evento,
                'created_at'   => optional($e->created_at)?->toIso8601String(),
            ]);

        return response()->json(['eventos' => $eventos]);
    }

    /**
     * Common path pros 4 eventos individuais — valida cross-tenant + status pendente.
     */
    private function aplicarEvento(int $id, string $metodo, string $justificativa = ''): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $businessId = (int) session('user.business_id');
        $dfe = NfeDfeRecebido::where('business_id', $businessId)
            ->where('id', $id)
            ->firstOrFail();

        if ($dfe->status_manifestacao !== 'pendente' && $metodo !== 'cienciar') {
            return back()->with('error', 'NF-e já manifestada — ação não disponível.');
        }

        try {
            $args = $justificativa !== '' ? [$dfe, $justificativa] : [$dfe];
            $evento = $this->service->{$metodo}(...$args);

            $msg = match ($metodo) {
                'cienciar'      => 'Ciência registrada SEFAZ.',
                'confirmar'     => 'Confirmação registrada SEFAZ.',
                'desconhecer'   => 'Desconhecimento registrado SEFAZ.',
                'naoRealizada'  => 'Operação não realizada registrada SEFAZ.',
                default         => 'Manifestação registrada.',
            };

            return back()->with($evento->isAutorizado() ? 'success' : 'error', $msg);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['justificativa' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('ManifestacaoController::aplicarEvento falhou', [
                'dfe_id' => $id,
                'metodo' => $metodo,
                'erro'   => $e->getMessage(),
            ]);
            return back()->with('error', 'Falha SEFAZ — tente novamente em alguns segundos.');
        }
    }

    private function canView(): bool
    {
        return auth()->user()?->can('nfe.manifestacao.view')
            || auth()->user()?->can('nfe.manifestacao.manage')
            || auth()->user()?->can('superadmin');
    }

    private function canManage(): bool
    {
        return auth()->user()?->can('nfe.manifestacao.manage')
            || auth()->user()?->can('superadmin');
    }
}
