<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Agenda;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Fisio\Entities\Appointment;
use Modules\Fisio\Entities\AppointmentType;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\ScheduleBlock;
use Modules\Fisio\Entities\WaitlistEntry;

/**
 * Leitura da tela Agenda da clínica (US-FISIO-015): a semana do profissional, o resumo do dia,
 * a lista de espera e a vaga liberada mais recente. `business_id` explícito em toda consulta.
 * Recado e nota do paciente não saem daqui (ficam na ficha do atendimento).
 */
class AgendaDaSemana
{
    /** Tipos padrão criados na primeira vez que a clínica abre a agenda (A2). */
    public const TIPOS_PADRAO = [
        ['name' => 'Avaliação', 'duration_min' => 60, 'modality' => 'presencial'],
        ['name' => 'Sessão', 'duration_min' => 50, 'modality' => 'presencial'],
        ['name' => 'Reavaliação', 'duration_min' => 60, 'modality' => 'presencial'],
        ['name' => 'Teleconsulta', 'duration_min' => 30, 'modality' => 'teleconsulta'],
    ];

    public function __construct(private readonly AgendaService $agenda) {}

    /** @return list<array{id:int, nome:string, duracao:int, modalidade:string}> */
    public function tipos(int $biz): array
    {
        // SUPERADMIN: escopo explícito por $biz.
        $q = fn () => AppointmentType::withoutGlobalScopes()->where('business_id', $biz)->where('active', true)->orderBy('id')->get();
        $tipos = $q();
        if ($tipos->isEmpty()) {
            foreach (self::TIPOS_PADRAO as $t) {
                AppointmentType::withoutGlobalScopes()->create($t + ['business_id' => $biz]); // SUPERADMIN: business explícito.
            }
            $tipos = $q();
        }

        return $tipos->map(fn ($t) => ['id' => (int) $t->id, 'nome' => $t->name, 'duracao' => (int) $t->duration_min, 'modalidade' => $t->modality])->all();
    }

    /** Pacientes ativos da empresa para a "Nova sessão" (nome completo, em ordem). @return list<array{id:int, nome:string}> */
    public function pacientes(int $biz): array
    {
        // SUPERADMIN: escopo explícito por $biz.
        return Patient::withoutGlobalScopes()->where('fisio_patients.business_id', $biz)->whereNull('fisio_patients.blocked_at')
            ->join('contacts', 'contacts.id', '=', 'fisio_patients.contact_id')->orderBy('contacts.name')->limit(500)
            ->get(['fisio_patients.id', 'contacts.name'])
            ->map(fn ($p) => ['id' => (int) $p->id, 'nome' => (string) $p->getAttribute('name')])->all();
    }

    /** @return list<array<string, mixed>> */
    public function atendimentos(int $biz, int $profissionalId, CarbonInterface $ini, CarbonInterface $fim): array
    {
        // SUPERADMIN: escopo explícito por $biz.
        return Appointment::withoutGlobalScopes()->where('business_id', $biz)->where('professional_id', $profissionalId)
            ->where('starts_at', '>=', $ini)->where('starts_at', '<', $fim)->where('status', '!=', 'cancelado')
            ->with(['patient.contact:id,name', 'type:id,name,modality'])->orderBy('starts_at')->get()
            ->map(fn (Appointment $a) => [
                'id' => (int) $a->id, 'patient_id' => (int) $a->patient_id, 'paciente' => self::curto($a->patient?->contact?->name),
                'tipo' => $a->type?->name, 'teleconsulta' => $a->type?->modality === 'teleconsulta',
                'inicio' => $a->starts_at->toIso8601String(), 'fim' => $a->ends_at->toIso8601String(),
                'status' => $a->status, 'encaixe' => (bool) $a->is_overbook,
            ])->all();
    }

    /** @return list<array{id:int, inicio:string, fim:string, motivo:?string}> */
    public function bloqueios(int $biz, int $profissionalId, CarbonInterface $ini, CarbonInterface $fim): array
    {
        // SUPERADMIN: escopo explícito por $biz.
        return ScheduleBlock::withoutGlobalScopes()->where('business_id', $biz)->where('professional_id', $profissionalId)
            ->where('starts_at', '<', $fim)->where('ends_at', '>', $ini)->orderBy('starts_at')->get()
            ->map(fn ($b) => ['id' => (int) $b->id, 'inicio' => $b->starts_at->toIso8601String(), 'fim' => $b->ends_at->toIso8601String(), 'motivo' => $b->reason])
            ->all();
    }

    /** @return array{sessoes:int, confirmadas:int, faltas_mes:int} */
    public function resumoDoDia(int $biz, int $profissionalId, CarbonInterface $hoje): array
    {
        // SUPERADMIN: escopo explícito por $biz.
        $base = fn () => Appointment::withoutGlobalScopes()->where('business_id', $biz)->where('professional_id', $profissionalId);
        $dia = $base()->whereBetween('starts_at', [$hoje->copy()->startOfDay(), $hoje->copy()->endOfDay()])->where('status', '!=', 'cancelado');

        return [
            'sessoes' => (clone $dia)->count(),
            'confirmadas' => (clone $dia)->whereNotNull('confirmed_at')->count(),
            'faltas_mes' => $base()->where('status', 'faltou')->whereBetween('starts_at', [$hoje->copy()->startOfMonth(), $hoje->copy()->endOfMonth()])->count(),
        ];
    }

    /** @return list<array{id:int, patient_id:int, paciente:string, preferencia:string}> */
    public function espera(int $biz): array
    {
        // SUPERADMIN: escopo explícito por $biz.
        return WaitlistEntry::withoutGlobalScopes()->where('business_id', $biz)->where('status', 'ativo')
            ->with('patient.contact:id,name')->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (WaitlistEntry $w) => ['id' => (int) $w->id, 'patient_id' => (int) $w->patient_id,
                'paciente' => self::curto($w->patient?->contact?->name), 'preferencia' => self::preferencia($w->preferences)])
            ->all();
    }

    /** A vaga futura cancelada mais recente das últimas 24 h, com quantos da espera servem nela (A7). */
    public function vagaLiberada(int $biz, int $profissionalId, CarbonInterface $agora): ?array
    {
        // SUPERADMIN: escopo explícito por $biz.
        $a = Appointment::withoutGlobalScopes()->where('business_id', $biz)->where('professional_id', $profissionalId)
            ->where('status', 'cancelado')->where('starts_at', '>', $agora)->where('cancelled_at', '>=', $agora->copy()->subDay())
            ->with('patient.contact:id,name')->latest('cancelled_at')->first();
        if (! $a) {
            return null;
        }
        $candidatos = $this->agenda->candidatosDaEspera($a);

        return $candidatos->isEmpty() ? null : [
            'appointment_id' => (int) $a->id, 'inicio' => $a->starts_at->toIso8601String(),
            'quem_cancelou' => self::curto($a->patient?->contact?->name), 'candidatos' => $candidatos->count(),
        ];
    }

    /** "Marta Lima Souza" → "Marta L." — a agenda não precisa do nome completo. */
    public static function curto(?string $nome): string
    {
        $p = preg_split('/\s+/', trim((string) $nome)) ?: [];
        if (($p[0] ?? '') === '') {
            return 'Paciente';
        }

        return count($p) > 1 ? $p[0].' '.mb_substr((string) end($p), 0, 1).'.' : $p[0];
    }

    /** @param  array{dias?:list<int>, turnos?:list<string>, ate?:string}|null  $pref */
    public static function preferencia(?array $pref): string
    {
        $dias = ['', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb', 'dom'];
        $partes = Collection::make([
            ! empty($pref['dias']) ? implode(' ou ', array_map(fn ($d) => $dias[(int) $d] ?? '', $pref['dias'])) : null,
            ! empty($pref['turnos']) ? implode(' ou ', array_map(fn ($t) => ['manha' => 'manhã', 'tarde' => 'tarde', 'noite' => 'noite'][$t] ?? $t, $pref['turnos'])) : null,
            ! empty($pref['ate']) ? 'até '.date('d/m', strtotime($pref['ate'])) : null,
        ])->filter();

        return $partes->isEmpty() ? 'qualquer horário' : $partes->implode(' · ');
    }
}
