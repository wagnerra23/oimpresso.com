<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use Modules\Fisio\Entities\Checkin;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Program;
use Modules\Fisio\Entities\Questionnaire;

/**
 * Sugestão da seção "limitação no desempenho de atividades" do laudo biopsicossocial
 * (US-FISIO-060; LBI art. 2º, §1º, II), montada só com o que o Fisio já mede.
 *
 * Cada número sai com o período e a fonte. Sem dado suficiente o número não é calculado: entra
 * um aviso no lugar e nada é estimado. O texto é descritivo: não interpreta a medida, não diz
 * se a limitação é grave nem conclui que o paciente é pessoa com deficiência (quem enquadra é
 * a perícia, ADR 0437 §5). O fisioterapeuta revisa e edita antes de assinar.
 *
 * Fontes: `fisio_checkins` (dor 0 a 10, dia de crise, exercício feito), `fisio_programs`
 * (meta semanal do programa ativo) e `fisio_questionnaires`. Escopo por empresa (HasBusinessScope)
 * e `business_id` explícito como segunda camada (ADR 0093).
 */
final class LaudoBiopsicossocialFisio
{
    /** Janelas em dias, contadas para trás a partir de hoje (inclusive). */
    public const JANELAS = [30, 90];

    /** Mínimo de dias com dor registrada na janela para calcular média e variação. */
    public const MINIMO_REGISTROS_DOR = 7;

    /** @return array{janelas: list<array<string, mixed>>, adesao: ?array<string, mixed>, questionarios: list<array<string, string>>, avisos: list<string>, texto: string} */
    public function montar(Patient $paciente, int $biz, string $hoje): array
    {
        $inicio90 = DiaLocal::somar($hoje, -(max(self::JANELAS) - 1));
        $checkins = Checkin::query()->where('business_id', $biz)->where('patient_id', $paciente->id)
            ->whereBetween('date', [$inicio90, $hoje])->get(['date', 'pain', 'crisis', 'done_item_ids']);

        $avisos = [];
        $janelas = [];
        foreach (self::JANELAS as $dias) {
            $de = DiaLocal::somar($hoje, -($dias - 1));
            $naJanela = $checkins->filter(fn (Checkin $c) => $c->date->format('Y-m-d') >= $de);
            $dores = $naJanela->pluck('pain')->filter(fn ($d) => $d !== null)->map(fn ($d) => (int) $d)->values();
            $janela = ['dias' => $dias, 'de' => $de, 'ate' => $hoje, 'crises' => $naJanela->where('crisis', true)->count(),
                'registros_dor' => $dores->count(), 'media' => null, 'minima' => null, 'maxima' => null];
            if ($dores->count() >= self::MINIMO_REGISTROS_DOR) {
                $janela['media'] = round($dores->avg(), 1);
                $janela['minima'] = $dores->min();
                $janela['maxima'] = $dores->max();
            } else {
                $avisos[] = "Dor dos últimos {$dias} dias: {$dores->count()} registro(s), menos que os ".self::MINIMO_REGISTROS_DOR.' necessários. Não calculada.';
            }
            $janelas[] = $janela;
        }

        $adesao = $this->adesao($paciente, $biz, $hoje, $checkins);
        if ($adesao === null) {
            $avisos[] = 'Adesão: sem programa ativo, não calculada.';
        }

        $questionarios = Questionnaire::query()->where('business_id', $biz)->where('patient_id', $paciente->id)
            ->orderBy('answered_at')->orderBy('id')->get()
            ->map(fn (Questionnaire $q) => ['instrumento' => strtoupper((string) $q->instrument), 'pontuacao' => (string) $q->score,
                'em' => $q->answered_at->format('d/m/Y')])->values()->all();
        if ($questionarios === []) {
            $avisos[] = 'Questionários: nenhum respondido.';
        }

        return ['janelas' => $janelas, 'adesao' => $adesao, 'questionarios' => $questionarios, 'avisos' => $avisos,
            'texto' => $this->texto($janelas, $adesao, $questionarios)];
    }

    /** Dias com exercício feito ÷ sessões esperadas pela meta semanal, nos últimos 30 dias. */
    private function adesao(Patient $paciente, int $biz, string $hoje, $checkins): ?array
    {
        $programa = Program::query()->where('business_id', $biz)->where('patient_id', $paciente->id)
            ->where('status', Program::STATUS_ATIVO)->latest('id')->first();
        if ($programa === null) {
            return null;
        }
        $de = DiaLocal::somar($hoje, -29);
        $feitos = $checkins->filter(fn (Checkin $c) => $c->date->format('Y-m-d') >= $de && count((array) $c->done_item_ids) > 0)->count();
        $meta = (int) $paciente->sessions_per_week;
        $esperado = $meta * 30 / 7;

        return ['de' => $de, 'ate' => $hoje, 'dias_com_exercicio' => $feitos, 'meta_semanal' => $meta,
            'percentual' => (int) min(100, round($feitos * 100 / $esperado, 0, PHP_ROUND_HALF_UP))];
    }

    /** @param list<array<string, mixed>> $janelas @param list<array<string, string>> $questionarios */
    private function texto(array $janelas, ?array $adesao, array $questionarios): string
    {
        $linhas = [];
        foreach ($janelas as $j) {
            $periodo = $this->data($j['de']).' a '.$this->data($j['ate']);
            if ($j['media'] !== null) {
                $linhas[] = "Dor referida no check-in diário (escala de 0 a 10), {$periodo}: média ".number_format($j['media'], 1, ',', '')
                    .", mínima {$j['minima']} e máxima {$j['maxima']}, em {$j['registros_dor']} dias com registro. Dias de crise registrados: {$j['crises']}.";
            } else {
                $linhas[] = "Dias de crise registrados no check-in, {$periodo}: {$j['crises']}.";
            }
        }
        if ($adesao !== null) {
            $linhas[] = 'Adesão ao programa domiciliar, '.$this->data($adesao['de']).' a '.$this->data($adesao['ate'])
                .": {$adesao['percentual']}% ({$adesao['dias_com_exercicio']} dias com exercício feito, para a meta de {$adesao['meta_semanal']} sessões por semana).";
        }
        foreach ($questionarios as $q) {
            $linhas[] = "{$q['instrumento']}: {$q['pontuacao']} em {$q['em']}.";
        }
        $linhas[] = 'Fonte: registros do paciente no Fisio (check-in diário, programa e questionários).';

        return implode("\n", $linhas);
    }

    private function data(string $dia): string
    {
        return DiaLocal::parse($dia)->format('d/m/Y');
    }
}
