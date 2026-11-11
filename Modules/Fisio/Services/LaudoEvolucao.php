<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Program;
use Modules\Fisio\Entities\ProgramItem;
use Modules\Fisio\Entities\Questionnaire;
use Modules\Fisio\Entities\Record;

/**
 * Monta o laudo de evolução fisioterapêutica (Lote 2; protótipo `Laudo.dc.html`).
 *
 * É um documento DIFERENTE do prontuário exportado (ProntuarioExportacao): o prontuário é a
 * história inteira, para a guarda; o laudo é uma síntese de uma página para quem encaminhou
 * (médico, convênio). Nada aqui é escrito na hora: tudo sai de registro ASSINADO, questionário
 * respondido e programa da empresa da sessão. O sistema não interpreta medida nem escreve
 * prognóstico — o diagnóstico e o plano são os da última avaliação assinada pelo profissional.
 *
 * Escopo: Record, Questionnaire, Program e ProgramItem têm escopo por empresa (HasBusinessScope);
 * o `business_id` explícito é a segunda camada (ADR 0093).
 */
class LaudoEvolucao
{
    public function __construct(private readonly IdentidadeClinica $identidade) {}

    private const DATA = 'd/m/Y';

    /** @return array<string, mixed> */
    public function montar(Patient $paciente, int $businessId, ?int $userId): array
    {
        $paciente->loadMissing('contact:id,name');
        $registros = Record::query()->where('business_id', $businessId)->where('patient_id', $paciente->id)
            ->whereNotNull('signed_at')->orderBy('signed_at')->orderBy('id')->get();
        $avaliacao = $registros->where('kind', Record::KIND_AVALIACAO)->last();
        $evolucao = $registros->where('kind', Record::KIND_EVOLUCAO)->last();

        return [
            'empresa' => (string) DB::table('business')->where('id', $businessId)->value('name'),
            'clinica' => $this->identidade->montar($businessId),
            'emitido_em' => now()->format(self::DATA),
            'paciente' => $paciente->contact?->name,
            'periodo' => $registros->isEmpty() ? null : $registros->first()->signed_at->format(self::DATA).' a '.now()->format(self::DATA),
            'contagem' => [
                'avaliacoes' => $registros->where('kind', Record::KIND_AVALIACAO)->count(),
                'evolucoes' => $registros->where('kind', Record::KIND_EVOLUCAO)->count(),
                'telemonitoramentos' => $registros->where('kind', Record::KIND_TELEMONITORAMENTO)->count(),
            ],
            'diagnostico' => $this->campo($avaliacao, 'diagnostico_cinesiologico'),
            'objetivos' => $this->campo($avaliacao, 'objetivos'),
            'plano' => $this->campo($avaliacao, 'plano_tratamento'),
            'conduta' => $this->campo($evolucao, 'conduta'),
            'medidas' => $this->medidas($paciente, $businessId),
            'exercicios' => $this->exercicios($paciente, $businessId),
            'emitido_por' => $this->profissional($userId),
            'crefito' => $registros->where('signed_by', $userId)->whereNotNull('signed_crefito')->last()?->signed_crefito,
        ];
    }

    private function campo(?Record $r, string $chave): ?string
    {
        $v = $r ? ((array) $r->body)[$chave] ?? null : null;

        return is_array($v) ? implode('; ', array_map('strval', $v)) : ($v === null || $v === '' ? null : (string) $v);
    }

    /** @return list<array{instrumento: string, primeira: ?string, primeira_em: string, atual: ?string, atual_em: string}> */
    private function medidas(Patient $paciente, int $biz): array
    {
        return Questionnaire::query()->where('business_id', $biz)->where('patient_id', $paciente->id)
            ->orderBy('answered_at')->orderBy('id')->get()->groupBy('instrument')
            ->map(fn ($lista, $instrumento) => [
                'instrumento' => strtoupper((string) $instrumento),
                'primeira' => $lista->first()->score, 'primeira_em' => $lista->first()->answered_at->format(self::DATA),
                'atual' => $lista->last()->score, 'atual_em' => $lista->last()->answered_at->format(self::DATA),
            ])->values()->all();
    }

    /** @return list<string> exercícios do programa ativo, na ordem */
    private function exercicios(Patient $paciente, int $biz): array
    {
        $programa = Program::query()->where('business_id', $biz)->where('patient_id', $paciente->id)
            ->where('status', Program::STATUS_ATIVO)->latest('id')->first();
        if (! $programa) {
            return [];
        }

        return ProgramItem::query()->where('business_id', $biz)->where('program_id', $programa->id)->orderBy('position')
            ->with('exercise:id,name')->get()->map(fn (ProgramItem $i) => (string) $i->exercise?->name)->filter()->values()->all();
    }

    private function profissional(?int $userId): ?string
    {
        $u = $userId ? DB::table('users')->where('id', $userId)->first(['first_name', 'last_name']) : null;

        return $u ? trim($u->first_name.' '.$u->last_name) : null;
    }
}
