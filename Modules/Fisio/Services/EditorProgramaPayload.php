<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use Modules\Fisio\Entities\Exercise;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Program;
use Modules\Fisio\Entities\ProgramItem;
use Modules\Fisio\Entities\Protocol;

/**
 * Props da aba Programa da ficha (US-FISIO-006). Tudo sob o escopo dos models: programa e
 * itens da empresa da sessão; exercícios e protocolos globais ou da própria clínica.
 *
 * O aviso de contraindicação é calculado aqui só para MOSTRAR ao profissional (marcação que
 * cruza com o paciente). Nada disso vai para o portal do paciente.
 */
final class EditorProgramaPayload
{
    private const LIMITE_EXERCICIOS = 300;

    /** @return array<string, mixed>|null programa ativo com os itens, ou null */
    public function programaAtivo(Patient $paciente): ?array
    {
        $programa = Program::query()
            ->where('patient_id', $paciente->id)
            ->where('status', Program::STATUS_ATIVO)
            ->latest('id')
            ->first();
        if ($programa === null) {
            return null;
        }

        $marcacoes = (array) ($paciente->condition_flags ?? []);
        $itens = ProgramItem::query()
            ->where('program_id', $programa->id)
            ->with('exercise:id,name,region,contraindication_flags')
            ->orderBy('position')
            ->get()
            ->map(fn (ProgramItem $i) => [
                'id' => $i->id,
                'exercise_id' => (int) $i->exercise_id,
                'nome' => $i->exercise?->name,
                'regiao' => $i->exercise?->region,
                'sets' => $i->sets,
                'reps' => $i->reps,
                'hold_s' => $i->hold_s,
                'times_per_day' => $i->times_per_day,
                'notes' => $i->notes,
                'position' => $i->position,
                'contraindicacao' => array_values(array_intersect((array) ($i->exercise?->contraindication_flags ?? []), $marcacoes)),
                'confirmado_em' => $i->contraindication_ack_at?->toIso8601String(),
                'justificativa' => $i->contraindication_justification,
            ])
            ->all();

        return [
            'id' => $programa->id,
            'phase' => $programa->phase,
            'origin' => $programa->origin,
            'stop_instruction' => $programa->stop_instruction,
            'pain_alert_threshold' => $programa->pain_alert_threshold,
            'pain_alert_mode' => $programa->pain_alert_mode,
            'limiar_efetivo' => $programa->limiarDeDor(),
            'crisis_items' => $programa->crisis_items ?? [],
            'starts_at' => $programa->starts_at?->toDateString(),
            'itens' => $itens,
        ];
    }

    /** @return list<array<string, mixed>> exercícios que a clínica pode prescrever */
    public function exercicios(Patient $paciente): array
    {
        $marcacoes = (array) ($paciente->condition_flags ?? []);

        return Exercise::query()
            ->orderBy('region')
            ->orderBy('name')
            ->limit(self::LIMITE_EXERCICIOS)
            ->get(['id', 'business_id', 'name', 'region', 'default_sets', 'default_reps', 'default_hold_s', 'times_per_day', 'contraindication_flags'])
            ->map(fn (Exercise $e) => [
                'id' => $e->id,
                'nome' => $e->name,
                'regiao' => $e->region,
                'proprio' => $e->business_id !== null,
                'dose' => [$e->default_sets, $e->default_reps, $e->default_hold_s],
                'contraindicacao' => array_values(array_intersect((array) ($e->contraindication_flags ?? []), $marcacoes)),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> protocolos-modelo do produto e da clínica */
    public function protocolos(): array
    {
        return Protocol::query()
            ->orderByRaw('business_id IS NULL')
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->map(fn (Protocol $p) => [
                'id' => $p->id,
                'nome' => $p->name,
                'regiao' => $p->region,
                'modelo_global' => $p->business_id === null,
                'copia_de' => $p->origin_protocol_id,
                'qtd_itens' => count((array) $p->items),
                'notes' => $p->notes,
            ])
            ->all();
    }
}
