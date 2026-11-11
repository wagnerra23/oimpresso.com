<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\LaudoBiopsicossocial;
use Modules\Fisio\Entities\LaudoBiopsicossocialSecao as Secao;
use Modules\Fisio\Entities\Patient;

/**
 * Monta o PDF do laudo biopsicossocial (US-FISIO-060; Lei 15.176/2025; LBI art. 2º, §1º).
 *
 * Entra no documento só seção REGISTRADA: a mais recente de cada item (correção substitui a
 * anterior, que segue guardada). Item sem seção registrada sai com espaço em branco para o
 * profissional escrever e assinar à mão. Rascunho nunca vai para o papel.
 *
 * O documento descreve; não conclui. A nota de rodapé diz que o enquadramento é da perícia
 * (ADR 0437 §5).
 */
final class LaudoBiopsicossocialDocumento
{
    /** Título e inciso de cada item, na ordem do art. 2º, §1º, da LBI. */
    public const TITULOS = [
        'impedimentos' => ['I', 'Impedimentos nas funções e nas estruturas do corpo'],
        'fatores' => ['II', 'Fatores socioambientais, psicológicos e pessoais'],
        'atividades' => ['III', 'Limitação no desempenho de atividades'],
        'participacao' => ['IV', 'Restrição de participação'],
    ];

    /**
     * Laudos do paciente para a ficha (mais recente primeiro): por item, a última seção
     * registrada e o rascunho aberto, se houver.
     *
     * @return list<array<string, mixed>>
     */
    public function doPaciente(Patient $paciente, int $biz): array
    {
        $laudos = LaudoBiopsicossocial::query()->where('business_id', $biz)->where('patient_id', $paciente->id)
            ->orderByDesc('id')->get();
        $secoes = Secao::query()->where('business_id', $biz)->whereIn('laudo_id', $laudos->pluck('id'))
            ->orderBy('id')->get()->groupBy('laudo_id');

        return $laudos->map(function (LaudoBiopsicossocial $l) use ($secoes) {
            $doLaudo = ($secoes->get($l->id) ?? collect())->groupBy('item');

            return [
                'id' => $l->id,
                'criado_em' => $l->created_at->format('d/m/Y'),
                'secoes' => array_map(function (string $item) use ($doLaudo) {
                    $lista = $doLaudo->get($item) ?? collect();
                    $registrada = $lista->whereNotNull('registrado_em')->last();
                    $rascunho = $lista->whereNull('registrado_em')->last();

                    return [
                        'item' => $item, 'inciso' => self::TITULOS[$item][0], 'titulo' => self::TITULOS[$item][1],
                        'conselho_sugerido' => Secao::CONSELHO_SUGERIDO[$item],
                        'registrada' => $registrada === null ? null : [
                            'autor' => $registrada->autor_nome, 'conselho' => $registrada->conselho, 'registro' => $registrada->registro_conselho,
                            'externa' => $registrada->assinatura === Secao::ASSINATURA_EXTERNA,
                            'registrado_em' => $registrada->registrado_em->format('d/m/Y H:i'), 'texto' => $registrada->texto,
                        ],
                        'rascunho' => $rascunho === null ? null : [
                            'texto' => $rascunho->texto, 'assinatura' => $rascunho->assinatura, 'autor_nome' => $rascunho->autor_nome,
                            'conselho' => $rascunho->conselho, 'registro_conselho' => $rascunho->registro_conselho,
                        ],
                    ];
                }, Secao::ITENS),
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    public function montar(LaudoBiopsicossocial $laudo, int $biz): array
    {
        $laudo->loadMissing('patient.contact:id,name');
        $registradas = Secao::query()->where('business_id', $biz)->where('laudo_id', $laudo->id)
            ->whereNotNull('registrado_em')->orderBy('id')->get()->groupBy('item');

        $secoes = [];
        foreach (Secao::ITENS as $item) {
            $ultima = $registradas->get($item)?->last();
            $secoes[] = [
                'item' => $item,
                'inciso' => self::TITULOS[$item][0],
                'titulo' => self::TITULOS[$item][1],
                'conselho_sugerido' => Secao::CONSELHO_SUGERIDO[$item],
                'registrada' => $ultima === null ? null : [
                    'texto' => (string) $ultima->texto,
                    'autor' => (string) $ultima->autor_nome,
                    'conselho' => (string) $ultima->conselho,
                    'registro' => (string) $ultima->registro_conselho,
                    'externa' => $ultima->assinatura === Secao::ASSINATURA_EXTERNA,
                    'registrado_em' => $ultima->registrado_em->format('d/m/Y H:i'),
                    'corrige' => $ultima->amends_id !== null,
                ],
            ];
        }

        return [
            'empresa' => (string) DB::table('business')->where('id', $biz)->value('name'),
            'paciente' => $laudo->patient?->contact?->name,
            'laudo_id' => $laudo->id,
            'criado_em' => $laudo->created_at->format('d/m/Y'),
            'emitido_em' => now()->format('d/m/Y H:i'),
            'secoes' => $secoes,
        ];
    }
}
