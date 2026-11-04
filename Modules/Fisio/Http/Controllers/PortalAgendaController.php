<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Fisio\Entities\Appointment;
use Modules\Fisio\Entities\BookingSettings;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\WaitlistEntry;
use Modules\Fisio\Services\Agenda\AgendaDaSemana;
use Modules\Fisio\Services\Agenda\AgendaDoPaciente;
use Modules\Fisio\Services\Consentimentos;
use Modules\Fisio\Services\PortalTokenService;
use Modules\Fisio\Services\ResponsavelPaciente;
use Modules\Fisio\Services\SessaoDoPortal;

/**
 * Agenda no portal do paciente (US-FISIO-016). JSON, rotas públicas `/fisio/p/{token}/agenda/*`.
 *
 * Mesmo portão do portal: link ruim = 404 `link_invalido`, sem PIN = 403 `pin_pendente`, sem o
 * consentimento de teleatendimento = 403. O business vem do token; atendimento de outro paciente ou
 * de outra clínica = 404. A resposta não traz recado nem nota de ninguém além do próprio paciente.
 */
class PortalAgendaController extends Controller
{
    public function __construct(
        private readonly PortalTokenService $tokens,
        private readonly SessaoDoPortal $sessao,
        private readonly Consentimentos $consentimentos,
        private readonly AgendaDoPaciente $agenda,
    ) {}

    public function index(Request $request, string $token, ResponsavelPaciente $profissionais): JsonResponse
    {
        return $this->comPaciente($request, $token, function (Patient $p) use ($profissionais) {
            $biz = (int) $p->business_id;
            $r = BookingSettings::daEmpresa($biz);
            // SUPERADMIN: escopo pelo business do token e pelo paciente.
            $todos = Appointment::withoutGlobalScopes()->where('business_id', $biz)->where('patient_id', $p->id)
                ->with('type:id,name,modality')->orderBy('starts_at')->get();
            $nomes = $profissionais->profissionais($biz)->pluck('nome', 'id');
            $linha = fn (Appointment $a) => [
                'id' => (int) $a->id, 'inicio' => $a->starts_at->toIso8601String(), 'tipo' => $a->type?->name,
                'teleconsulta' => $a->type?->modality === 'teleconsulta', 'profissional' => $nomes[$a->professional_id] ?? null,
                'status' => $a->status, 'pode_mexer' => $this->agenda->dentroDoPrazo($a), 'nota' => $a->patient_rating,
            ];
            // SUPERADMIN: idem.
            $espera = WaitlistEntry::withoutGlobalScopes()->where('business_id', $biz)->where('patient_id', $p->id)->where('status', 'ativo')->latest('id')->first();

            return response()->json([
                'impedimento' => $this->agenda->impedimento($p),
                'prazo_horas' => (int) $r->cancel_min_hours,
                'checkin' => (bool) $r->checkin_enabled,
                'proximas' => $todos->filter(fn ($a) => $a->ends_at->isFuture() && ! in_array($a->status, ['cancelado', 'faltou', 'realizado'], true))->map($linha)->values(),
                'historico' => $todos->filter(fn ($a) => ! $a->ends_at->isFuture() || in_array($a->status, ['faltou', 'realizado'], true))
                    ->sortByDesc('starts_at')->take(20)->map($linha)->values(),
                'espera' => $espera ? ['id' => (int) $espera->id, 'preferencia' => AgendaDaSemana::preferencia($espera->preferences)] : null,
                'tipos' => $this->agenda->tipos($biz),
                'profissionais' => $profissionais->profissionais($biz)->map(fn ($x) => $x + ['acompanha' => (int) $x['id'] === (int) $p->responsible_user_id])->sortByDesc('acompanha')->values(),
            ]);
        });
    }

    public function horarios(Request $request, string $token): JsonResponse
    {
        return $this->comPaciente($request, $token, function (Patient $p) use ($request) {
            $d = $request->validate(['tipo' => ['required', 'integer'], 'profissional' => ['required', 'integer'], 'dia' => ['required', 'date_format:Y-m-d']]);

            return response()->json(['horarios' => $this->agenda->horariosLivres($p, (int) $d['tipo'], (int) $d['profissional'], $d['dia'])]);
        });
    }

    public function store(Request $request, string $token): JsonResponse
    {
        return $this->comPaciente($request, $token, function (Patient $p) use ($request) {
            $d = $request->validate(['tipo' => ['required', 'integer'], 'profissional' => ['required', 'integer'], 'inicio' => ['required', 'date_format:Y-m-d H:i'],
                'recado' => ['nullable', 'string', 'max:280'], 'avisar_se_abrir' => ['boolean']]);
            $a = $this->agenda->marcar($p, (int) $d['tipo'], (int) $d['profissional'], Carbon::parse($d['inicio']), $d['recado'] ?? null, (bool) ($d['avisar_se_abrir'] ?? false));

            return response()->json(['id' => (int) $a->id, 'inicio' => $a->starts_at->toIso8601String()], 201);
        });
    }

    public function acao(Request $request, string $token, int $id, string $acao): JsonResponse
    {
        return $this->comPaciente($request, $token, function (Patient $p) use ($request, $id, $acao) {
            $a = $this->agenda->doPaciente($p, $id);
            match ($acao) {
                'cancelar' => $this->agenda->cancelar($a),
                'confirmar' => $this->agenda->confirmar($a),
                'cheguei' => $this->agenda->cheguei($a),
                'nota' => $this->agenda->avaliar($a, (int) $request->validate(['nota' => ['required', 'integer', 'between:1,5']])['nota']),
                'remarcar' => $a = $this->agenda->remarcar($p, $a, Carbon::parse($request->validate(['inicio' => ['required', 'date_format:Y-m-d H:i']])['inicio'])),
            };

            return response()->json(['id' => (int) $a->id, 'status' => $a->fresh()->status]);
        });
    }

    public function sairDaEspera(Request $request, string $token): JsonResponse
    {
        return $this->comPaciente($request, $token, function (Patient $p) {
            // SUPERADMIN: escopo pelo business do token e pelo paciente.
            WaitlistEntry::withoutGlobalScopes()->where('business_id', $p->business_id)->where('patient_id', $p->id)->where('status', 'ativo')->update(['status' => 'saiu']);

            return response()->json(['espera' => null]);
        });
    }

    /** Portão do portal (mesmas respostas do PortalController). */
    private function comPaciente(Request $request, string $token, \Closure $fazer): JsonResponse
    {
        $linha = $this->tokens->resolver($token);
        if ($linha === null) {
            return response()->json(['erro' => 'link_invalido'], 404);
        }
        if (! $this->sessao->pinAceito($request, $linha)) {
            return response()->json(['erro' => 'pin_pendente'], 403);
        }
        $p = $linha->getRelation('patient');
        abort_unless($p instanceof Patient, 404);
        if (! $this->consentimentos->vigente($p, Consentimentos::TELEATENDIMENTO)) {
            return response()->json(['erro' => 'consentimento_pendente'], 403);
        }

        return $fazer($p);
    }
}
