<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Pesquisa;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Fisio\Entities\Study;
use Modules\Fisio\Entities\StudyMoment;
use Modules\Fisio\Entities\StudyParticipant;
use Modules\Fisio\Services\Questionarios\CatalogoQuestionarios;

/**
 * Exportação do estudo para a universidade (etapa 07 P-07, §4.4; ADR 0437 §7).
 *
 * - Sem identificação: nada de nome, CPF, telefone, data de nascimento nem data do calendário.
 *   Cada linha usa o código do participante, e o tempo é o dia do estudo (0 = entrada).
 * - Formato longo: participante × momento × instrumento, e a série diária de dor e adesão.
 * - Dia sem check-in e questionário não respondido saem como `NA`, nunca como zero.
 * - Estudo travado: entra só o que estava gravado até `locked_at`.
 * - Um estudo por arquivo, da empresa do estudo: nada de outra clínica.
 *
 * Quem chama registra a exportação no activity_log.
 */
class ExportacaoEstudo
{
    public const ARQUIVOS = ['momentos', 'diario', 'participantes', 'dicionario'];

    public const AUSENTE = 'NA';

    /** @return list<list<string>> primeira linha = cabeçalho */
    public function linhas(Study $estudo, string $arquivo): array
    {
        return match ($arquivo) {
            'momentos' => $this->momentos($estudo),
            'diario' => $this->diario($estudo),
            'participantes' => $this->participantes($estudo),
            'dicionario' => $this->dicionario(),
            default => throw new \InvalidArgumentException('arquivo desconhecido'),
        };
    }

    public function csv(array $linhas): string
    {
        $h = fopen('php://temp', 'r+');
        foreach ($linhas as $l) {
            fputcsv($h, $l, ',', '"', '');
        }
        rewind($h);
        $out = (string) stream_get_contents($h);
        fclose($h);

        return $out;
    }

    /** @return list<list<string>> */
    private function momentos(Study $estudo): array
    {
        $momentos = StudyMoment::withoutGlobalScopes() // SUPERADMIN: empresa do estudo, explícita.
            ->where('business_id', $estudo->business_id)->where('study_id', $estudo->id)->orderBy('sort')->orderBy('day_offset')->get();
        $comVersao = Schema::hasColumn('fisio_questionnaires', 'instrument_version');
        $respostas = $this->limitarAoTravamento($estudo, DB::table('fisio_questionnaires')->where('business_id', $estudo->business_id))
            ->whereIn('patient_id', $this->participantesQuery($estudo)->pluck('patient_id')->all())
            ->get(array_merge(['patient_id', 'instrument', 'score', 'answered_at'], $comVersao ? ['instrument_version'] : []))
            ->groupBy('patient_id');

        $out = [['participante', 'momento', 'dia_alvo', 'instrumento', 'versao_instrumento', 'valor', 'dia_resposta']];
        foreach ($this->participantesQuery($estudo)->get() as $p) {
            $entrada = CarbonImmutable::parse($p->entered_on->toDateString());
            foreach ($momentos as $m) {
                $alvo = $entrada->addDays($m->day_offset);
                foreach ((array) $m->instruments as $instr) {
                    $r = $respostas->get($p->patient_id, collect())
                        ->filter(fn ($q) => $q->instrument === $instr
                            && abs(CarbonImmutable::parse($q->answered_at)->startOfDay()->diffInDays($alvo, false)) <= $m->window_days)
                        ->sortBy(fn ($q) => abs(CarbonImmutable::parse($q->answered_at)->startOfDay()->diffInDays($alvo, false)))
                        ->first();
                    $out[] = [
                        $p->code, $m->label, (string) $m->day_offset, $instr,
                        $r !== null ? (string) ($r->instrument_version ?? CatalogoQuestionarios::VERSAO) : self::AUSENTE,
                        $r !== null && $r->score !== null ? (string) (float) $r->score : self::AUSENTE,
                        $r !== null ? (string) (int) round($entrada->diffInDays(CarbonImmutable::parse($r->answered_at)->startOfDay(), false)) : self::AUSENTE,
                    ];
                }
            }
        }

        return $out;
    }

    /** @return list<list<string>> */
    private function diario(Study $estudo): array
    {
        $out = [['participante', 'dia_estudo', 'dor', 'crise', 'itens_feitos', 'itens_prescritos']];
        $fimGeral = CarbonImmutable::parse(($estudo->locked_at ?? now())->toDateString());
        if ($estudo->ends_on !== null && $estudo->ends_on->lt($fimGeral)) {
            $fimGeral = CarbonImmutable::parse($estudo->ends_on->toDateString());
        }
        foreach ($this->participantesQuery($estudo)->get() as $p) {
            $entrada = CarbonImmutable::parse($p->entered_on->toDateString());
            $fim = $p->exited_on !== null && $p->exited_on->lt($fimGeral) ? CarbonImmutable::parse($p->exited_on->toDateString()) : $fimGeral;
            $checkins = $this->limitarAoTravamento($estudo, DB::table('fisio_checkins')->where('business_id', $estudo->business_id))
                ->where('patient_id', $p->patient_id)->whereBetween('date', [$entrada->toDateString(), $fim->toDateString()])
                ->get(['date', 'pain', 'crisis', 'done_item_ids', 'program_id'])
                ->keyBy(fn ($c) => substr((string) $c->date, 0, 10));
            $prescritos = DB::table('fisio_program_items')->whereIn('program_id', $checkins->pluck('program_id')->filter()->unique()->all())
                ->groupBy('program_id')->selectRaw('program_id, COUNT(*) as n')->pluck('n', 'program_id');
            for ($d = $entrada, $i = 0; $d->lte($fim); $d = $d->addDay(), $i++) {
                $c = $checkins->get($d->toDateString());
                $out[] = $c === null
                    ? [$p->code, (string) $i, self::AUSENTE, self::AUSENTE, self::AUSENTE, self::AUSENTE]
                    : [$p->code, (string) $i, $c->pain === null ? self::AUSENTE : (string) $c->pain, $c->crisis ? '1' : '0',
                        (string) count(array_unique((array) json_decode((string) ($c->done_item_ids ?? '[]'), true))),
                        $c->program_id !== null ? (string) ($prescritos[$c->program_id] ?? 0) : self::AUSENTE];
            }
        }

        return $out;
    }

    /** @return list<list<string>> motivo da saída fica fora: é texto livre e pode identificar. */
    private function participantes(Study $estudo): array
    {
        $out = [['participante', 'dia_saida', 'tipo_saida']];
        foreach ($this->participantesQuery($estudo)->get() as $p) {
            $out[] = [$p->code,
                $p->exited_on !== null ? (string) (int) round($p->entered_on->diffInDays($p->exited_on)) : self::AUSENTE,
                $p->exit_type ?? self::AUSENTE];
        }

        return $out;
    }

    /** @return list<list<string>> */
    private function dicionario(): array
    {
        return [
            ['arquivo', 'coluna', 'significado', 'unidade'],
            ['todos', 'participante', 'código do participante; não vem do nome nem do CPF; a chave de volta fica na clínica', 'texto'],
            ['todos', 'NA', 'valor ausente: dia sem check-in ou questionário não respondido na janela; nunca é zero', '—'],
            ['momentos', 'momento', 'momento de avaliação do estudo', 'texto'],
            ['momentos', 'dia_alvo', 'dia do estudo em que o momento vence (0 = entrada)', 'dias'],
            ['momentos', 'instrumento', 'código do questionário', 'texto'],
            ['momentos', 'versao_instrumento', 'versão do instrumento gravada com a resposta', 'texto'],
            ['momentos', 'valor', 'escore calculado pelo servidor; a resposta mais próxima do dia alvo dentro da janela', 'escore do instrumento'],
            ['momentos', 'dia_resposta', 'dia do estudo em que a resposta foi dada', 'dias'],
            ['diario', 'dia_estudo', 'dia desde a entrada no estudo (0 = entrada)', 'dias'],
            ['diario', 'dor', 'dor do dia informada pelo paciente', '0 a 10'],
            ['diario', 'crise', '1 se o paciente marcou crise no dia', '0 ou 1'],
            ['diario', 'itens_feitos', 'exercícios do programa marcados como feitos no dia', 'contagem'],
            ['diario', 'itens_prescritos', 'exercícios no programa do dia', 'contagem'],
            ['participantes', 'dia_saida', 'dia do estudo em que o participante saiu', 'dias'],
            ['participantes', 'tipo_saida', 'perda_seguimento, revogacao, conclusao ou outro', 'texto'],
        ];
    }

    private function participantesQuery(Study $estudo)
    {
        return StudyParticipant::withoutGlobalScopes() // SUPERADMIN: empresa do estudo, explícita.
            ->where('business_id', $estudo->business_id)->where('study_id', $estudo->id)->orderBy('code');
    }

    private function limitarAoTravamento(Study $estudo, $query)
    {
        return $estudo->locked_at !== null ? $query->where('created_at', '<=', $estudo->locked_at) : $query;
    }
}
