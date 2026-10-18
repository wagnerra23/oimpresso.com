<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Record;

/**
 * Monta o conteúdo do prontuário exportado em PDF (UC-FPS-19; Res. COFFITO 414/2012).
 *
 * Separado do controller para o teste ler o que vai para o documento sem decodificar o PDF.
 * Entra só o que está ASSINADO (`signed_at` preenchido): registro sem carimbo não é prontuário.
 * Ordem cronológica — do mais antigo para o mais novo, ao contrário da tela — porque o
 * documento se lê como história clínica. Escopo por empresa vem do model (HasBusinessScope);
 * o filtro por paciente, daqui.
 *
 * Datas formatadas como gravadas (relógio do servidor), sem conversão: o carimbo é o que o
 * servidor gravou, e reformatar fuso aqui criaria duas versões do mesmo carimbo.
 */
class ProntuarioExportacao
{
    /** Rótulos dos campos do corpo do registro — os mesmos da ficha (Show.tsx). */
    public const ROTULO_CAMPO = [
        'queixa_principal' => 'Queixa principal',
        'historia_doenca' => 'História da doença',
        'exame_fisico' => 'Exame físico',
        'diagnostico_cinesiologico' => 'Diagnóstico cinesiológico-funcional',
        'objetivos' => 'Objetivos',
        'plano_tratamento' => 'Plano de tratamento',
        'conduta' => 'Conduta',
        'resposta_paciente' => 'Resposta do paciente',
        'texto' => 'Correção',
    ];

    public const TIPO = [
        Record::KIND_AVALIACAO => 'Avaliação',
        Record::KIND_EVOLUCAO => 'Evolução',
        Record::KIND_TELEMONITORAMENTO => 'Telemonitoramento',
        Record::KIND_ADENDO => 'Adendo',
    ];

    /** Rótulos dos marcadores (lista fechada em Patient::CONDITION_FLAGS) — os mesmos de condicoes.ts. */
    public const ROTULO_CONDICAO = [
        'protese_quadril_posterior' => 'Prótese de quadril (via posterior)',
        'protese_quadril_anterior' => 'Prótese de quadril (via anterior)',
        'pos_op_manguito' => 'Pós-operatório de manguito rotador',
        'pos_lca' => 'Pós-operatório de LCA',
        'gestante' => 'Gestante',
        'osteoporose' => 'Osteoporose',
        'risco_queda' => 'Risco de queda',
        'hipertensao_nao_controlada' => 'Hipertensão não controlada',
    ];

    private const FORMATO = 'd/m/Y H:i';

    /** @return array{paciente: array<string, mixed>, registros: list<array<string, mixed>>, empresa: string, emitido_em: string} */
    public function montar(Patient $paciente, int $businessId): array
    {
        $paciente->loadMissing('contact:id,name,mobile');

        $registros = Record::query()
            ->where('patient_id', $paciente->id)
            ->whereNotNull('signed_at')
            ->with('signer:id,first_name,last_name')
            ->orderBy('signed_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Record $r) => [
                'id' => (int) $r->id,
                'tipo' => self::TIPO[$r->kind] ?? $r->kind,
                'campos' => $this->campos((array) $r->body),
                'modalidade' => $r->modality,
                'canal' => $r->channel,
                'assinado_por' => trim(($r->signer?->first_name ?? '').' '.($r->signer?->last_name ?? '')),
                'crefito' => $r->signed_crefito,
                'assinado_em' => $r->signed_at?->format(self::FORMATO),
                'corrige' => $r->amends_id !== null ? (int) $r->amends_id : null,
            ])
            ->values()
            ->all();

        return [
            'paciente' => [
                'id' => (int) $paciente->id,
                'nome' => $paciente->contact?->name,
                'telefone' => $paciente->contact?->mobile,
                'queixa_principal' => $paciente->main_complaint,
                'objetivos' => array_values((array) ($paciente->goals ?? [])),
                'sessoes_por_semana' => $paciente->sessions_per_week,
                'condicoes' => array_map(fn (string $c) => self::ROTULO_CONDICAO[$c] ?? $c, (array) ($paciente->condition_flags ?? [])),
                'bloqueado' => $paciente->estaBloqueado(),
            ],
            'registros' => $registros,
            'empresa' => (string) DB::table('business')->where('id', $businessId)->value('name'),
            'emitido_em' => now()->format(self::FORMATO),
        ];
    }

    /** @return list<array{rotulo: string, valor: string}> */
    private function campos(array $body): array
    {
        $saida = [];
        foreach ($body as $chave => $valor) {
            $saida[] = [
                'rotulo' => self::ROTULO_CAMPO[$chave] ?? (string) $chave,
                'valor' => is_array($valor) ? implode('; ', array_map('strval', $valor)) : (string) ($valor ?? ''),
            ];
        }

        return $saida;
    }
}
