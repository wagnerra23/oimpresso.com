<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ComunicacaoVisual\Services\AgendaInstalacao;

/**
 * AgendaController — TELA da agenda de instalação (US-COMVIS-007 · UC-FLX-21).
 *
 *   GET /comunicacao-visual/agenda?semana=AAAA-MM-DD → ComunicacaoVisual/Agenda (semana × equipe)
 *
 * A tela só LÊ por aqui. Agendar continua no POST da API (InstalacaoController@agendar), que é quem
 * recusa sem janela — a tela mostra a recusa, não a reimplementa.
 *
 * Props:
 *   - semana        segunda-feira da semana exibida (AAAA-MM-DD) · eager, é parâmetro de UI
 *   - equipe        users da empresa (user_type=user) · eager: lista curta, o formulário precisa dela
 *                   no 1º paint, e deferida a grade nasceria sem linhas
 *   - os            OS da empresa ainda abertas (sem concluída/cancelada) · eager pelo mesmo motivo
 *   - instalacoes   AgendaInstalacao::listar da semana · Inertia::defer (é a query da janela)
 *   - podeAgendar   comvis.os.update_status (ou superadmin) — o POST confere de novo
 *
 * Tier 0 (ADR 0093): business_id vem da sessão; toda query filtra explícito.
 */
class AgendaController extends Controller
{
    public function __construct(private readonly AgendaInstalacao $agenda) {}

    public function index(Request $request): Response
    {
        $user = auth()->user();
        $superadmin = $user !== null && $user->can('superadmin');
        if (! $superadmin && ! ($user?->can('comvis.os.view') || $user?->can('comvis.os.update_status'))) {
            abort(403, 'Sem permissão na agenda de instalação (comvis.os.view ou comvis.os.update_status).');
        }

        $businessId = (int) (session('user.business_id') ?? session('business.id'));
        $semana = self::segundaDaSemana($request->query('semana'));
        $fimSemana = $semana->copy()->addDays(7);

        $equipe = DB::table('users')
            ->where('business_id', $businessId)
            ->where('user_type', 'user')
            ->whereNull('deleted_at')
            ->orderBy('first_name')->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn ($u) => ['id' => (int) $u->id, 'nome' => trim($u->first_name.' '.$u->last_name)])
            ->values();

        $os = DB::table('comvis_os')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->whereNotIn('status_etapa', ['concluida', 'cancelada'])
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'numero', 'status_etapa'])
            ->map(fn ($o) => ['id' => (int) $o->id, 'numero' => $o->numero, 'etapa' => $o->status_etapa])
            ->values();

        return Inertia::render('ComunicacaoVisual/Agenda', [
            'bizName' => session('business.name', 'oimpresso'),
            'semana' => $semana->toDateString(),
            'equipe' => $equipe,
            'os' => $os,
            'instalacoes' => Inertia::defer(fn () => $this->agenda->listar($businessId, $semana, $fimSemana)),
            'podeAgendar' => $superadmin || (bool) $user?->can('comvis.os.update_status'),
        ]);
    }

    /** Segunda-feira da semana da data pedida; data inválida ou ausente cai na semana de hoje. */
    private static function segundaDaSemana(mixed $semana): Carbon
    {
        try {
            $base = is_string($semana) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $semana) ? Carbon::parse($semana) : Carbon::now();
        } catch (\Throwable) {
            $base = Carbon::now();
        }

        return $base->startOfWeek(Carbon::MONDAY)->startOfDay();
    }
}
