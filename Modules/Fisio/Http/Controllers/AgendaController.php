<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Fisio\Entities\Appointment;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\ScheduleBlock;
use Modules\Fisio\Entities\WaitlistEntry;
use Modules\Fisio\Services\Agenda\AgendaDaSemana;
use Modules\Fisio\Services\Agenda\AgendaService;
use Modules\Fisio\Services\ResponsavelPaciente;

/**
 * Agenda da clínica (US-FISIO-015 sobre as regras da US-FISIO-014).
 *
 * Ler exige `fisio.access` (FisioLiberado); escrever exige `fisio.editar`. `business_id` vem
 * sempre da sessão; atendimento, bloqueio, espera ou paciente de outra empresa respondem 404
 * pelo escopo do model. Toda leitura cara vai por `Inertia::defer`.
 */
class AgendaController extends Controller
{
    public function __construct(private readonly AgendaService $agenda, private readonly AgendaDaSemana $leitura) {}

    public function index(Request $request, ResponsavelPaciente $profissionais): Response
    {
        $biz = $this->biz($request);
        $ini = Carbon::parse((string) $request->query('semana', now()->toDateString()))->startOfWeek();
        $fim = $ini->copy()->addDays(6);
        $lista = $profissionais->profissionais($biz);
        $prof = (int) $request->query('profissional', 0);
        $prof = $lista->firstWhere('id', $prof) ? $prof : ($lista->firstWhere('id', (int) $request->user()->id) ? (int) $request->user()->id : (int) ($lista->first()['id'] ?? 0));

        return Inertia::render('Fisio/Agenda/Index', [
            'semana' => $ini->toDateString(),
            'profissional_id' => $prof,
            'profissionais' => $lista->all(),
            'pode_editar' => (bool) $request->user()?->can('fisio.editar'),
            'tipos' => Inertia::defer(fn () => $this->leitura->tipos($biz)),
            'atendimentos' => Inertia::defer(fn () => $this->leitura->atendimentos($biz, $prof, $ini, $fim)),
            'bloqueios' => Inertia::defer(fn () => $this->leitura->bloqueios($biz, $prof, $ini, $fim)),
            'resumo' => Inertia::defer(fn () => $this->leitura->resumoDoDia($biz, $prof, now())),
            'espera' => Inertia::defer(fn () => $this->leitura->espera($biz)),
            'vaga' => Inertia::defer(fn () => $this->leitura->vagaLiberada($biz, $prof, now())),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->editar($request);
        $d = $request->validate([
            'patient_id' => ['required', 'integer'], 'type_id' => ['required', 'integer'], 'professional_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date'], 'encaixe' => ['boolean'],
        ], ['starts_at.required' => 'Escolha o dia e a hora.']);
        Patient::query()->findOrFail((int) $d['patient_id']);
        $this->agenda->marcar($this->biz($request), [
            'patient_id' => (int) $d['patient_id'], 'type_id' => (int) $d['type_id'], 'professional_id' => (int) $d['professional_id'],
            'starts_at' => Carbon::parse($d['starts_at']), 'created_by' => (int) $request->user()->id,
        ], (bool) ($d['encaixe'] ?? false));

        return back()->with('status', 'Sessão marcada.');
    }

    public function status(Request $request, int $id): RedirectResponse
    {
        $this->editar($request);
        $d = $request->validate([
            'status' => ['required', Rule::in(['confirmado', 'chegou', 'em_atendimento', 'realizado', 'faltou'])],
            'record_id' => ['nullable', 'integer'],
        ]);
        $this->agenda->mudarStatus(Appointment::query()->findOrFail($id), $d['status'], isset($d['record_id']) ? (int) $d['record_id'] : null);

        return back()->with('status', 'Atendimento atualizado.');
    }

    public function cancelar(Request $request, int $id): RedirectResponse
    {
        $this->editar($request);
        $d = $request->validate(['motivo' => ['nullable', 'string', 'max:280'], 'por' => ['required', Rule::in(['clinica', 'paciente'])]]);
        $candidatos = $this->agenda->cancelar(Appointment::query()->findOrFail($id), $d['por'], $d['motivo'] ?? null);

        $n = $candidatos->count();

        return back()->with('status', match (true) {
            $n === 0 => 'Sessão desmarcada.',
            $n === 1 => 'Sessão desmarcada. 1 paciente da lista de espera serve neste horário.',
            default => "Sessão desmarcada. {$n} pacientes da lista de espera servem neste horário.",
        });
    }

    public function bloquear(Request $request): RedirectResponse
    {
        $this->editar($request);
        $d = $request->validate([
            'professional_id' => ['required', 'integer'], 'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'], 'motivo' => ['nullable', 'string', 'max:120'],
        ]);
        $biz = $this->biz($request);
        abort_unless(app(ResponsavelPaciente::class)->podeReceber((int) $d['professional_id'], $biz), 422, 'Profissional de outra empresa.');
        ScheduleBlock::query()->create([
            'business_id' => $biz, 'professional_id' => (int) $d['professional_id'], 'starts_at' => Carbon::parse($d['starts_at']),
            'ends_at' => Carbon::parse($d['ends_at']), 'reason' => $d['motivo'] ?? null, 'created_by' => (int) $request->user()->id,
        ]);

        return back()->with('status', 'Horário bloqueado.');
    }

    public function desbloquearHorario(Request $request, int $id): RedirectResponse
    {
        $this->editar($request);
        ScheduleBlock::query()->findOrFail($id)->delete();

        return back()->with('status', 'Bloqueio removido.');
    }

    public function entrarNaEspera(Request $request): RedirectResponse
    {
        $this->editar($request);
        $d = $request->validate([
            'patient_id' => ['required', 'integer'], 'dias' => ['array'], 'dias.*' => ['integer', 'between:1,7'],
            'turnos' => ['array'], 'turnos.*' => [Rule::in(['manha', 'tarde', 'noite'])], 'ate' => ['nullable', 'date'],
        ]);
        $pac = Patient::query()->findOrFail((int) $d['patient_id']);
        WaitlistEntry::query()->create(['business_id' => $this->biz($request), 'patient_id' => $pac->id,
            'preferences' => array_filter(['dias' => $d['dias'] ?? [], 'turnos' => $d['turnos'] ?? [], 'ate' => $d['ate'] ?? null])]);

        return back()->with('status', 'Paciente na lista de espera.');
    }

    public function sairDaEspera(Request $request, int $id): RedirectResponse
    {
        $this->editar($request);
        WaitlistEntry::query()->findOrFail($id)->forceFill(['status' => 'saiu'])->save();

        return back()->with('status', 'Paciente saiu da lista de espera.');
    }

    public function liberarApp(Request $request, int $id): RedirectResponse
    {
        $this->editar($request);
        $this->agenda->desbloquear(Patient::query()->findOrFail($id));

        return back()->with('status', 'Agendamento pelo app liberado para o paciente.');
    }

    private function biz(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }

    private function editar(Request $request): void
    {
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);
    }
}
