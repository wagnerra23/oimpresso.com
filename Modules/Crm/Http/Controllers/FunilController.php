<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Crm\Entities\Deal;
use Modules\Crm\Services\DealPipelineService;

/**
 * Funil de vendas em kanban sobre `crm_deals` (US-CRM-092 · ADR 0441).
 *
 * O motor é o `DealPipelineService` (mover etapa com histórico, resumo por etapa, previsão
 * ponderada), que já existia sem tela. Permissões próprias: `crm.funil.ver` para abrir e
 * `crm.funil.editar` para criar negócio e mover de etapa — ninguém tem por padrão, então a tela
 * nasce desligada em toda empresa até o admin liberar no papel (Camada 3).
 *
 * Tier 0 (ADR 0093): business_id da sessão em toda leitura e escrita; contato de outra empresa
 * é recusado; negócio de outra empresa dá 404.
 */
class FunilController extends Controller
{
    /** Rótulos das etapas em PT, na ordem de `Deal::STAGES`. */
    public const ROTULOS = [
        Deal::STAGE_LEAD => 'Lead',
        Deal::STAGE_QUALIFICACAO => 'Qualificado',
        Deal::STAGE_PROPOSTA => 'Proposta',
        Deal::STAGE_NEGOCIACAO => 'Negociação',
        Deal::STAGE_GANHO => 'Ganho',
        Deal::STAGE_PERDIDO => 'Perdido',
    ];

    public function __construct(private readonly DealPipelineService $pipeline) {}

    public function index(Request $request): Response
    {
        abort_unless(auth()->user()?->can('crm.funil.ver'), 403);
        $businessId = (int) $request->session()->get('user.business_id');

        return Inertia::render('Crm/Funil/Index', [
            'etapas' => collect(Deal::STAGES)->map(fn (string $s) => [
                'id' => $s,
                'rotulo' => self::ROTULOS[$s],
                'probabilidade' => Deal::PROBABILIDADES_DEFAULT[$s],
                'terminal' => in_array($s, [Deal::STAGE_GANHO, Deal::STAGE_PERDIDO], true),
            ])->values(),
            'pode_editar' => (bool) auth()->user()?->can('crm.funil.editar'),
            'negocios' => Inertia::defer(fn () => $this->negocios($businessId)),
            'resumo' => Inertia::defer(fn () => [
                'por_etapa' => $this->pipeline->pipelineSummary($businessId),
                'previsao_mes' => $this->pipeline->forecastFechamento($businessId, Carbon::now()->endOfMonth()),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('crm.funil.editar'), 403);
        $businessId = (int) $request->session()->get('user.business_id');

        $dados = $request->validate([
            'titulo' => ['required', 'string', 'max:191'],
            'contact_id' => ['required', 'integer', Rule::exists('contacts', 'id')->where('business_id', $businessId)],
            'valor_estimado' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'data_fechamento_prevista' => ['nullable', 'date'],
        ]);

        Deal::query()->create([
            'business_id' => $businessId,
            'contact_id' => (int) $dados['contact_id'],
            'titulo' => $dados['titulo'],
            'stage' => Deal::STAGE_LEAD,
            // Valor de negócio vem como número (não texto pt-BR) e é arredondado a 2 casas.
            'valor_estimado' => round((float) $dados['valor_estimado'], 2),
            'data_fechamento_prevista' => $dados['data_fechamento_prevista'] ?? null,
            'owner_user_id' => (int) auth()->id(),
        ]);

        return back()->with('success', 'Negócio criado no funil.');
    }

    public function mover(Request $request, int $id): RedirectResponse
    {
        abort_unless(auth()->user()?->can('crm.funil.editar'), 403);
        $businessId = (int) $request->session()->get('user.business_id');

        $dados = $request->validate([
            'etapa' => ['required', Rule::in(Deal::STAGES)],
            'razao' => ['nullable', 'string', 'max:500'],
        ]);
        abort_unless(Deal::query()->where('business_id', $businessId)->where('id', $id)->exists(), 404);

        try {
            $this->pipeline->moverStage($businessId, $id, $dados['etapa'], $dados['razao'] ?? null);
        } catch (\RuntimeException) {
            return back()->withErrors(['etapa' => 'Negócio ganho ou perdido não muda de etapa.']);
        }

        return back()->with('success', 'Negócio movido para '.self::ROTULOS[$dados['etapa']].'.');
    }

    /** @return list<array<string, mixed>> */
    private function negocios(int $businessId): array
    {
        return DB::table('crm_deals as d')
            ->leftJoin('contacts as c', fn ($j) => $j->on('c.id', '=', 'd.contact_id')->where('c.business_id', $businessId))
            ->leftJoin('users as u', 'u.id', '=', 'd.owner_user_id')
            ->where('d.business_id', $businessId)
            ->whereNull('d.deleted_at')
            ->orderByDesc('d.updated_at')
            ->limit(500)
            ->get([
                'd.id', 'd.titulo', 'd.stage', 'd.valor_estimado', 'd.data_fechamento_prevista', 'd.updated_at',
                'c.id as contato_id', 'c.name as contato', 'c.supplier_business_name as empresa',
                DB::raw("TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) as responsavel"),
            ])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'titulo' => (string) $r->titulo,
                'etapa' => (string) $r->stage,
                'valor' => (float) $r->valor_estimado,
                'fechamento_previsto' => $r->data_fechamento_prevista ? Carbon::parse($r->data_fechamento_prevista)->format('d/m/Y') : null,
                'atualizado_em' => Carbon::parse($r->updated_at)->toIso8601String(),
                'contato_id' => $r->contato_id !== null ? (int) $r->contato_id : null,
                'contato' => trim((string) ($r->empresa ?: $r->contato)) ?: null,
                'responsavel' => trim((string) $r->responsavel) ?: null,
            ])
            ->values()
            ->all();
    }
}
