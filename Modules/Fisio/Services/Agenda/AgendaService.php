<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Agenda;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Fisio\Entities\Appointment;
use Modules\Fisio\Entities\AppointmentType;
use Modules\Fisio\Entities\BookingSettings;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Record;
use Modules\Fisio\Entities\ScheduleBlock;
use Modules\Fisio\Entities\WaitlistEntry;
use Modules\Fisio\Services\ResponsavelPaciente;

/**
 * Regras da agenda do Fisio (US-FISIO-014, etapa 04 A1, A4, A5, A7, A8, A10, A21).
 *
 * `business_id` é sempre argumento explícito: a mesma regra serve à clínica (sessão) e ao
 * portal (business do token, sem sessão). Toda leitura filtra por ele (ADR 0093).
 * Falta e cancelamento tardio não cobram nada aqui: o Financeiro é outro lote, e taxa ou
 * consumo de sessão é sempre confirmado pelo profissional (A8).
 */
class AgendaService
{
    /** Transições permitidas (A10). Realizado, faltou e cancelado são finais. */
    private const TRANSICOES = [
        'agendado' => ['confirmado', 'chegou', 'em_atendimento', 'realizado', 'faltou', 'cancelado'],
        'confirmado' => ['chegou', 'em_atendimento', 'realizado', 'faltou', 'cancelado'],
        'chegou' => ['em_atendimento', 'realizado', 'faltou', 'cancelado'],
        'em_atendimento' => ['realizado'],
    ];

    public function __construct(private readonly ResponsavelPaciente $profissionais) {}

    /** Atendimentos e bloqueios que ocupam o horário do profissional (A1, A5). */
    public function conflitos(int $biz, int $profissionalId, CarbonInterface $ini, CarbonInterface $fim, ?int $ignorar = null): Collection
    {
        // SUPERADMIN: escopo explícito por $biz (o portal não tem sessão).
        $atend = Appointment::withoutGlobalScopes()->where('business_id', $biz)->where('professional_id', $profissionalId)
            ->whereIn('status', Appointment::OCUPAM)->where('starts_at', '<', $fim)->where('ends_at', '>', $ini)
            ->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar))->get();
        // SUPERADMIN: idem.
        $bloq = ScheduleBlock::withoutGlobalScopes()->where('business_id', $biz)->where('professional_id', $profissionalId)
            ->where('starts_at', '<', $fim)->where('ends_at', '>', $ini)->get();

        return $atend->concat($bloq);
    }

    /**
     * Marca um atendimento. Conflito com atendimento vira 422, a não ser que a clínica confirme o
     * encaixe (A4); bloqueio de horário nunca aceita encaixe.
     *
     * @param  array{patient_id:int, professional_id:int, type_id:int, starts_at:CarbonInterface, source?:string, patient_note?:?string, created_by?:?int}  $d
     */
    public function marcar(int $biz, array $d, bool $encaixe = false): Appointment
    {
        // SUPERADMIN: escopo explícito por $biz nas duas leituras abaixo.
        $pac = Patient::withoutGlobalScopes()->where('business_id', $biz)->whereNull('blocked_at')->find($d['patient_id']);
        $tipo = AppointmentType::withoutGlobalScopes()->where('business_id', $biz)->where('active', true)->find($d['type_id']);
        if (! $pac || ! $tipo) {
            throw ValidationException::withMessages(['patient_id' => 'Paciente ou tipo de atendimento não encontrado.']);
        }
        if (! $this->profissionais->podeReceber((int) $d['professional_id'], $biz)) {
            throw ValidationException::withMessages(['professional_id' => 'Este profissional não atende nesta clínica.']);
        }
        $ini = $d['starts_at']->copy()->seconds(0);
        $fim = $ini->copy()->addMinutes((int) $tipo->duration_min);

        return DB::transaction(function () use ($biz, $d, $pac, $tipo, $ini, $fim, $encaixe) {
            $conf = $this->conflitos($biz, (int) $d['professional_id'], $ini, $fim);
            if ($conf->contains(fn ($c) => $c instanceof ScheduleBlock)) {
                throw ValidationException::withMessages(['starts_at' => 'Horário bloqueado na agenda.']);
            }
            if ($conf->isNotEmpty() && ! $encaixe) {
                throw ValidationException::withMessages(['starts_at' => 'Já existe atendimento neste horário. Confirme o encaixe para marcar mesmo assim.']);
            }

            // SUPERADMIN: business explícito.
            return Appointment::withoutGlobalScopes()->create([
                'business_id' => $biz, 'patient_id' => $pac->id, 'professional_id' => (int) $d['professional_id'],
                'type_id' => $tipo->id, 'starts_at' => $ini, 'ends_at' => $fim, 'is_overbook' => $conf->isNotEmpty(),
                'source' => $d['source'] ?? 'clinica', 'patient_note' => $d['patient_note'] ?? null,
                'policy_version' => BookingSettings::daEmpresa($biz)->policy_version, 'created_by' => $d['created_by'] ?? null,
            ]);
        });
    }

    /** Muda o status (A10). "Realizado" exige evolução assinada do mesmo paciente e trava o status. */
    public function mudarStatus(Appointment $a, string $novo, ?int $recordId = null, ?CarbonInterface $agora = null): Appointment
    {
        if (! in_array($novo, self::TRANSICOES[$a->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Não dá para passar de {$a->status} para {$novo}."]);
        }
        if ($novo === 'cancelado') {
            throw ValidationException::withMessages(['status' => 'Use o cancelamento, que registra quem cancelou e o prazo.']);
        }
        $agora ??= now();
        $campos = ['status' => $novo];
        if ($novo === 'realizado') {
            // SUPERADMIN: o registro tem que ser da mesma empresa e do mesmo paciente.
            $assinado = $recordId !== null && Record::withoutGlobalScopes()->where('business_id', $a->business_id)
                ->where('patient_id', $a->patient_id)->whereKey($recordId)
                ->whereIn('kind', ['avaliacao', 'evolucao'])->whereNotNull('signed_at')->exists();
            if (! $assinado) {
                throw ValidationException::withMessages(['record_id' => 'Para marcar como realizado, assine a evolução do atendimento.']);
            }
            $campos['record_id'] = $recordId;
        }
        if ($novo === 'confirmado') {
            $campos['confirmed_at'] = $agora;
        }
        if ($novo === 'chegou') {
            $campos['arrived_at'] = $agora;
        }
        $a->forceFill($campos)->save();
        if ($novo === 'faltou') {
            $this->aplicarBloqueioPorFaltas($a);
        }

        return $a;
    }

    /**
     * Cancela (clínica ou paciente). Dentro do prazo da política vira cancelamento tardio: só
     * marcado; o que acontece com ele o profissional decide (A8). Devolve quem da lista de espera
     * serve na vaga liberada (A7).
     */
    public function cancelar(Appointment $a, string $por, ?string $motivo = null, ?CarbonInterface $agora = null): Collection
    {
        if (! in_array('cancelado', self::TRANSICOES[$a->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => 'Este atendimento não pode mais ser cancelado.']);
        }
        $agora ??= now();
        $prazoMin = (int) BookingSettings::daEmpresa((int) $a->business_id)->cancel_min_hours * 60;
        $a->forceFill([
            'status' => 'cancelado', 'cancelled_at' => $agora, 'cancelled_by' => $por, 'cancel_reason' => $motivo,
            'late_cancel' => $agora->diffInMinutes($a->starts_at, false) < $prazoMin,
        ])->save();

        return $this->candidatosDaEspera($a);
    }

    /** Pacientes da lista de espera que servem no horário deste atendimento (A7), por ordem de entrada. */
    public function candidatosDaEspera(Appointment $vaga): Collection
    {
        // SUPERADMIN: escopo explícito pelo business da vaga.
        return WaitlistEntry::withoutGlobalScopes()->where('business_id', $vaga->business_id)->where('status', 'ativo')
            ->where('patient_id', '!=', $vaga->patient_id)
            ->where(fn ($q) => $q->whereNull('professional_id')->orWhere('professional_id', $vaga->professional_id))
            ->where(fn ($q) => $q->whereNull('type_id')->orWhere('type_id', $vaga->type_id))
            ->orderBy('created_at')->orderBy('id')->get()
            ->filter(fn (WaitlistEntry $w) => CompatibilidadeEspera::serve($w->preferences, $vaga->starts_at))->values();
    }

    /**
     * Faltas sem aviso bloqueiam o autoagendamento pelo app (A21). Só contam as faltas depois do
     * último desbloqueio feito pelo profissional.
     */
    public function aplicarBloqueioPorFaltas(Appointment $a): void
    {
        $limite = (int) BookingSettings::daEmpresa((int) $a->business_id)->no_show_block_after;
        // SUPERADMIN: escopo explícito pelo business do atendimento.
        $pac = Patient::withoutGlobalScopes()->where('business_id', $a->business_id)->find($a->patient_id);
        if ($limite < 1 || ! $pac || $pac->booking_blocked_at !== null) {
            return;
        }
        $desde = $pac->booking_unblocked_at;
        // SUPERADMIN: idem.
        $faltas = Appointment::withoutGlobalScopes()->where('business_id', $a->business_id)->where('patient_id', $pac->id)
            ->where('status', 'faltou')->when($desde, fn ($q) => $q->where('updated_at', '>', $desde))->count();
        if ($faltas >= $limite) {
            $pac->forceFill(['booking_blocked_at' => now()])->save();
        }
    }

    /** O profissional devolve o autoagendamento ao paciente (A21). */
    public function desbloquear(Patient $pac): void
    {
        $pac->forceFill(['booking_blocked_at' => null, 'booking_unblocked_at' => now()])->save();
    }
}
