<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Pesquisa;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Fisio\Entities\Checkin;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Study;
use Modules\Fisio\Entities\StudyAdverseEvent;
use Modules\Fisio\Entities\StudyParticipant;
use Modules\Fisio\Services\JogoService;

/**
 * Eventos adversos do estudo a partir do check-in (etapa 07 P-06, ADR 0437 §5).
 *
 * - `crise_48h`: crise no check-in por 3 dias seguidos ou mais (mais de 48 h). O evento começa
 *   no 1º dia da sequência e o fim acompanha enquanto ela continua.
 * - `alerta_dor`: dor no limiar em vigor do programa (`JogoService::limiarVigente`), a mesma
 *   regra que tira o jogo da tela e avisa o profissional.
 *
 * Só para quem está no estudo naquele dia (entrou, não saiu). Idempotente: reenviar o check-in
 * não cria evento novo. O software registra e mostra; não classifica gravidade.
 */
class EventosAdversos
{
    public const DIAS_DE_CRISE = 3;

    public function __construct(private readonly JogoService $jogo) {}

    public function registrarDoCheckin(Checkin $checkin): void
    {
        $biz = (int) $checkin->business_id;
        $dia = $checkin->date->format('Y-m-d');
        $participacoes = StudyParticipant::withoutGlobalScopes() // SUPERADMIN: roda no portal, sem sessão; empresa do check-in.
            ->where('business_id', $biz)->where('patient_id', (int) $checkin->patient_id)
            ->where('entered_on', '<=', $dia)
            ->where(fn ($q) => $q->whereNull('exited_on')->orWhere('exited_on', '>=', $dia))
            ->get();
        if ($participacoes->isEmpty()) {
            return;
        }
        $paciente = Patient::withoutGlobalScopes()->where('business_id', $biz)->find((int) $checkin->patient_id); // SUPERADMIN: idem.
        if ($paciente === null) {
            return;
        }

        $limiar = $checkin->pain !== null ? $this->jogo->limiarVigente($paciente, $dia) : null;
        $crise = $checkin->crisis ? $this->inicioDaCrise($biz, (int) $paciente->id, $dia) : null;

        foreach ($participacoes as $p) {
            if ($limiar !== null && (int) $checkin->pain >= $limiar) {
                $this->gravar($p, StudyAdverseEvent::ORIGEM_ALERTA_DOR, $dia, $dia, ['dor' => (int) $checkin->pain, 'limiar' => $limiar]);
            }
            if ($crise !== null) {
                $dias = CarbonImmutable::parse($crise)->diffInDays(CarbonImmutable::parse($dia)) + 1;
                $this->gravar($p, StudyAdverseEvent::ORIGEM_CRISE, $crise, $dia, ['dias' => (int) $dias]);
            }
        }
    }

    /** A pesquisadora revisa e escreve a nota. Revisar de novo troca a nota e o autor. */
    public function revisar(StudyAdverseEvent $evento, string $nota, int $autor): StudyAdverseEvent
    {
        if (trim($nota) === '') {
            throw new InvalidArgumentException('nota da revisão é obrigatória');
        }
        $evento->forceFill(['reviewed_at' => now(), 'reviewed_by' => $autor, 'review_note' => mb_substr(trim($nota), 0, 1000)])->save();

        return $evento;
    }

    /** @return list<array<string, mixed>> */
    public function doEstudo(Study $estudo): array
    {
        $codigos = StudyParticipant::withoutGlobalScopes() // SUPERADMIN: empresa do estudo, explícita.
            ->where('business_id', $estudo->business_id)->where('study_id', $estudo->id)->pluck('code', 'id');

        return StudyAdverseEvent::withoutGlobalScopes() // SUPERADMIN: empresa do estudo, explícita.
            ->where('business_id', $estudo->business_id)->where('study_id', $estudo->id)
            ->orderByDesc('started_on')->orderBy('id')->get()
            ->map(fn (StudyAdverseEvent $e) => [
                'id' => $e->id, 'code' => (string) ($codigos[$e->participant_id] ?? ''), 'origin' => $e->origin,
                'started_on' => $e->started_on->toDateString(), 'ended_on' => $e->ended_on->toDateString(),
                'detail' => $e->detail, 'reviewed_at' => $e->reviewed_at?->toIso8601String(), 'review_note' => $e->review_note,
            ])->values()->all();
    }

    /** 1º dia da sequência de crise que termina em `$dia`, se ela já passou de 48 h. */
    private function inicioDaCrise(int $biz, int $pacienteId, string $dia): ?string
    {
        $dias = Checkin::withoutGlobalScopes() // SUPERADMIN: empresa do check-in, explícita.
            ->where('business_id', $biz)->where('patient_id', $pacienteId)
            ->where('date', '<=', $dia)->orderByDesc('date')->limit(400)->get(['date', 'crisis']);
        $esperado = CarbonImmutable::parse($dia);
        $inicio = null;
        foreach ($dias as $c) {
            if (! $c->crisis || $c->date->format('Y-m-d') !== $esperado->toDateString()) {
                break;
            }
            $inicio = $esperado->toDateString();
            $esperado = $esperado->subDay();
        }
        if ($inicio === null || CarbonImmutable::parse($inicio)->diffInDays(CarbonImmutable::parse($dia)) + 1 < self::DIAS_DE_CRISE) {
            return null;
        }

        return $inicio;
    }

    private function gravar(StudyParticipant $p, string $origem, string $inicio, string $fim, array $detalhe): void
    {
        $evento = StudyAdverseEvent::withoutGlobalScopes() // SUPERADMIN: mesma empresa do participante.
            ->firstOrNew(['participant_id' => $p->id, 'origin' => $origem, 'started_on' => $inicio]);
        $evento->forceFill([
            'business_id' => (int) $p->business_id,
            'study_id' => (int) $p->study_id,
            'ended_on' => $fim,
            'detail' => $detalhe,
        ])->save();
    }
}
