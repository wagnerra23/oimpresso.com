<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use Illuminate\Validation\Rule;
use Modules\Fisio\Entities\Record;

/**
 * Conteúdo mínimo de cada registro do prontuário (Res. COFFITO 414/2012; ADR 0437 §4;
 * US-FISIO-005). Fonte única das regras de validação e do recorte do corpo: o que não
 * está aqui não entra no `body` (nada do corpo do request vira coluna nem carimbo).
 *
 * Avaliação (anamnese): queixa principal, história da doença atual, exame físico-funcional,
 * diagnóstico cinesiológico funcional, objetivos e plano de tratamento são OBRIGATÓRIOS.
 * Antecedentes, prognóstico, sessões previstas, sinais de alerta e marcadores de condição
 * entram quando o profissional informa (a suíte TDD fixa os seis obrigatórios — ver PR).
 *
 * Evolução: conduta do atendimento e resposta do paciente (dor 0–10 opcional).
 * Adendo: texto da correção + `amends_id` (validado no controller, contra o paciente).
 */
final class ProntuarioRegras
{
    /** Tipos que a TELA do profissional cria. `telemonitoramento` só o portal gera (US-FISIO-007). */
    public const KINDS_DA_TELA = [Record::KIND_AVALIACAO, Record::KIND_EVOLUCAO, Record::KIND_ADENDO];

    /** Campos do corpo por tipo — o recorte que vai ao banco. */
    public const CAMPOS = [
        Record::KIND_AVALIACAO => [
            'queixa_principal', 'historia_doenca', 'antecedentes', 'exame_fisico',
            'diagnostico_cinesiologico', 'prognostico', 'objetivos', 'plano_tratamento',
            'sessoes_previstas', 'red_flags', 'condition_flags',
        ],
        Record::KIND_EVOLUCAO => ['conduta', 'resposta_paciente', 'dor', 'observacoes'],
        Record::KIND_ADENDO => ['texto'],
    ];

    /** @return array<string, mixed> */
    public static function regras(?string $kind): array
    {
        $base = [
            'kind' => ['required', 'string', Rule::in(self::KINDS_DA_TELA)],
            'crefito' => ['required', 'string', 'max:40'],
            'body' => ['required', 'array'],
        ];

        $texto = ['required', 'string', 'max:5000'];
        $opcional = ['nullable', 'string', 'max:5000'];

        return $base + match ($kind) {
            Record::KIND_AVALIACAO => [
                'body.queixa_principal' => $texto,
                'body.historia_doenca' => $texto,
                'body.antecedentes' => $opcional,
                'body.exame_fisico' => $texto,
                'body.diagnostico_cinesiologico' => $texto,
                'body.prognostico' => $opcional,
                'body.objetivos' => ['required', 'array', 'min:1', 'max:10'],
                'body.objetivos.*' => ['required', 'string', 'max:300'],
                'body.plano_tratamento' => $texto,
                'body.sessoes_previstas' => ['nullable', 'integer', 'between:1,200'],
                'body.red_flags' => ['nullable', 'array'],
                'body.red_flags.*' => ['string', Rule::in((array) config('fisio.red_flags', []))],
                'body.condition_flags' => ['nullable', 'array'],
                'body.condition_flags.*' => ['string', Rule::in((array) config('fisio.condition_flags', []))],
            ],
            Record::KIND_EVOLUCAO => [
                'body.conduta' => $texto,
                'body.resposta_paciente' => $texto,
                'body.dor' => ['nullable', 'integer', 'between:0,10'],
                'body.observacoes' => $opcional,
            ],
            Record::KIND_ADENDO => [
                'body.texto' => $texto,
            ],
            default => [],
        };
    }

    /**
     * Só os campos conhecidos do tipo, sem vazios. Nada além deles chega ao banco.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public static function recortar(string $kind, array $body): array
    {
        $saida = [];
        foreach (self::CAMPOS[$kind] ?? [] as $campo) {
            if (! array_key_exists($campo, $body) || $body[$campo] === null || $body[$campo] === '' || $body[$campo] === []) {
                continue;
            }
            $saida[$campo] = is_array($body[$campo]) ? array_values($body[$campo]) : $body[$campo];
        }

        return $saida;
    }
}
