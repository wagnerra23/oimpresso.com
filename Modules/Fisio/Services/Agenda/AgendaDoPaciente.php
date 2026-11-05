<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Agenda;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Fisio\Entities\Appointment;
use Modules\Fisio\Entities\AppointmentType;
use Modules\Fisio\Entities\BookingSettings;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\WaitlistEntry;
use Modules\Fisio\Services\ResponsavelPaciente;

/**
 * Agenda vista e mexida pelo próprio paciente no portal (US-FISIO-016, etapa 04 A6, A7, A9, A19,
 * A20, A21). O business vem do token, nunca de parâmetro, e toda consulta filtra por ele.
 *
 * Marcar pelo app obedece às regras da clínica: app ligado, quem pode, tipo liberado, horário aberto,
 * antecedência mínima, dias à frente e bloqueio por faltas. Remarcar e cancelar só até o prazo da
 * política; depois disso, só pela clínica. Nenhuma cobrança aqui (sinal e pacote são do Financeiro).
 */
class AgendaDoPaciente
{
    public const MINUTOS_CHEGUEI = 60;

    public function __construct(private readonly AgendaService $agenda, private readonly ResponsavelPaciente $profissionais) {}

    /** Por que o paciente não pode marcar sozinho, ou null se pode. */
    public function impedimento(Patient $p): ?string
    {
        $r = BookingSettings::daEmpresa((int) $p->business_id);

        return match (true) {
            ! $r->online_enabled => 'desligado',
            $p->booking_blocked_at !== null => 'bloqueado_faltas',
            // Sem pacote no Fisio ainda (lote do Financeiro): "só com pacote" não libera ninguém.
            $r->who_can_book !== 'todos' => 'so_pela_clinica',
            default => null,
        };
    }

    /** @return list<array{id:int, nome:string, duracao:int, teleconsulta:bool}> */
    public function tipos(int $biz): array
    {
        // SUPERADMIN: escopo explícito pelo business do token.
        return AppointmentType::withoutGlobalScopes()->where('business_id', $biz)->where('active', true)->where('online_bookable', true)
            ->orderBy('id')->get()
            ->map(fn ($t) => ['id' => (int) $t->id, 'nome' => $t->name, 'duracao' => (int) $t->duration_min, 'teleconsulta' => $t->modality === 'teleconsulta'])
            ->all();
    }

    /** Horários livres do dia para o tipo e o profissional, dentro das regras da clínica. @return list<string> */
    public function horariosLivres(Patient $p, int $tipoId, int $profissionalId, string $dia, ?CarbonInterface $agora = null): array
    {
        $biz = (int) $p->business_id;
        $agora ??= now();
        $r = BookingSettings::daEmpresa($biz);
        $tipo = collect($this->tipos($biz))->firstWhere('id', $tipoId);
        $data = Carbon::parse($dia)->startOfDay();
        if (! $tipo || ! $this->profissionais->podeReceber($profissionalId, $biz) || $data->gt($agora->copy()->addDays((int) $r->max_days_ahead))) {
            return [];
        }
        $minimo = $agora->copy()->addHours((int) $r->min_notice_hours);
        $abertos = ($r->open_slots ?? [])[(string) $data->dayOfWeekIso] ?? [];

        return array_values(array_filter($abertos, function (string $h) use ($data, $tipo, $minimo, $biz, $profissionalId) {
            $ini = $data->copy()->setTimeFromTimeString($h);

            return $ini->gte($minimo)
                && $this->agenda->conflitos($biz, $profissionalId, $ini, $ini->copy()->addMinutes($tipo['duracao']))->isEmpty();
        }));
    }

    /** Marca pelo app (A9). Só em horário livre; com "me avise" o paciente entra também na lista de espera (A7). */
    public function marcar(Patient $p, int $tipoId, int $profissionalId, CarbonInterface $inicio, ?string $recado, bool $avisarSeAbrir): Appointment
    {
        if ($motivo = $this->impedimento($p)) {
            throw ValidationException::withMessages(['agenda' => $motivo]);
        }
        if (! in_array($inicio->format('H:i'), $this->horariosLivres($p, $tipoId, $profissionalId, $inicio->toDateString()), true)) {
            throw ValidationException::withMessages(['inicio' => 'Este horário não está mais livre. Escolha outro.']);
        }

        return DB::transaction(function () use ($p, $tipoId, $profissionalId, $inicio, $recado, $avisarSeAbrir) {
            $a = $this->agenda->marcar((int) $p->business_id, ['patient_id' => (int) $p->id, 'professional_id' => $profissionalId,
                'type_id' => $tipoId, 'starts_at' => $inicio, 'source' => 'online', 'patient_note' => $recado]);
            if ($avisarSeAbrir && BookingSettings::daEmpresa((int) $p->business_id)->waitlist_enabled) {
                // SUPERADMIN: business do token, explícito.
                WaitlistEntry::withoutGlobalScopes()->create(['business_id' => $p->business_id, 'patient_id' => $p->id,
                    'professional_id' => $profissionalId, 'type_id' => $tipoId, 'preferences' => ['ate' => $inicio->copy()->subDay()->toDateString()]]);
            }

            return $a;
        });
    }

    /** O atendimento é deste paciente, nesta clínica? (404 se não). */
    public function doPaciente(Patient $p, int $id): Appointment
    {
        // SUPERADMIN: escopo pelo business do token e pelo paciente.
        return Appointment::withoutGlobalScopes()->where('business_id', $p->business_id)->where('patient_id', $p->id)->findOrFail($id);
    }

    /** Ainda dá para o paciente mexer (antes do prazo da política)? */
    public function dentroDoPrazo(Appointment $a, ?CarbonInterface $agora = null): bool
    {
        $horas = (int) BookingSettings::daEmpresa((int) $a->business_id)->cancel_min_hours;

        return in_array($a->status, ['agendado', 'confirmado'], true) && ($agora ?? now())->diffInMinutes($a->starts_at, false) >= $horas * 60;
    }

    public function cancelar(Appointment $a): void
    {
        if (! $this->dentroDoPrazo($a)) {
            throw ValidationException::withMessages(['agenda' => 'fora_do_prazo']);
        }
        $this->agenda->cancelar($a, 'paciente', 'pelo app');
    }

    public function remarcar(Patient $p, Appointment $a, CarbonInterface $inicio): Appointment
    {
        if (! $this->dentroDoPrazo($a)) {
            throw ValidationException::withMessages(['agenda' => 'fora_do_prazo']);
        }
        if (! in_array($inicio->format('H:i'), $this->horariosLivres($p, (int) $a->type_id, (int) $a->professional_id, $inicio->toDateString()), true)) {
            throw ValidationException::withMessages(['inicio' => 'Este horário não está mais livre. Escolha outro.']);
        }

        return DB::transaction(function () use ($a, $inicio) {
            $this->agenda->cancelar($a, 'paciente', 'remarcado pelo app');
            $novo = $this->agenda->marcar((int) $a->business_id, ['patient_id' => (int) $a->patient_id, 'professional_id' => (int) $a->professional_id,
                'type_id' => (int) $a->type_id, 'starts_at' => $inicio, 'source' => 'online', 'patient_note' => $a->patient_note]);
            $novo->forceFill(['rescheduled_from_id' => $a->id])->save();

            return $novo;
        });
    }

    public function confirmar(Appointment $a): void
    {
        if ($a->status !== 'agendado') {
            return; // confirmar de novo não muda nada
        }
        $this->agenda->mudarStatus($a, 'confirmado');
        $a->forceFill(['confirmation_channel' => 'portal'])->save();
    }

    /** "Cheguei" (A19): a partir de 1 h antes, se a clínica ligou o check-in. */
    public function cheguei(Appointment $a, ?CarbonInterface $agora = null): void
    {
        $agora ??= now();
        $janela = $agora->gte($a->starts_at->copy()->subMinutes(self::MINUTOS_CHEGUEI)) && $agora->lt($a->ends_at);
        if (! BookingSettings::daEmpresa((int) $a->business_id)->checkin_enabled || ! $janela || ! in_array($a->status, ['agendado', 'confirmado'], true)) {
            throw ValidationException::withMessages(['agenda' => 'fora_da_janela']);
        }
        $this->agenda->mudarStatus($a, 'chegou', null, $agora);
    }

    /** Nota de 1 a 5 depois da sessão (A20): privada, uma vez só. */
    public function avaliar(Appointment $a, int $nota): void
    {
        if ($a->status !== 'realizado' || $a->patient_rating !== null) {
            throw ValidationException::withMessages(['nota' => 'Esta sessão não aceita nota.']);
        }
        $a->forceFill(['patient_rating' => $nota])->save();
    }
}
