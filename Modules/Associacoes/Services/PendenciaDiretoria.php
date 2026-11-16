<?php

declare(strict_types=1);

namespace Modules\Associacoes\Services;

use Illuminate\Support\Carbon;
use Modules\Associacoes\Entities\Associacao;

/**
 * Pendência de documentação da diretoria (ADR 0451 D7). Fonte única do cálculo: a lista de
 * associações (onda 3) e o painel da rede (onda 5) leem daqui.
 *
 *  - `em_dia`   — nenhuma mudança de diretoria aberta;
 *  - `pendente` — mudança registrada em `diretoria_mudou_em`, ata nova ainda não chegou;
 *  - `atrasada` — passaram mais de `config('associacoes.prazo_ata_diretoria_dias')` dias.
 *
 * O sistema avisa; não bloqueia nada (D7). A ata nova enviada como documento `ata_diretoria`
 * limpa a pendência e grava `diretoria_atualizada_em`.
 */
final class PendenciaDiretoria
{
    public const EM_DIA = 'em_dia';

    public const PENDENTE = 'pendente';

    public const ATRASADA = 'atrasada';

    public static function prazoDias(): int
    {
        return max(1, (int) config('associacoes.prazo_ata_diretoria_dias', 30));
    }

    /**
     * @return array{estado: string, mudou_em: ?string, vence_em: ?string, dias_atraso: int}
     */
    public function situacao(Associacao $associacao, ?Carbon $hoje = null): array
    {
        $mudou = $associacao->diretoria_mudou_em;
        if ($mudou === null) {
            return ['estado' => self::EM_DIA, 'mudou_em' => null, 'vence_em' => null, 'dias_atraso' => 0];
        }
        $hoje = ($hoje ?? Carbon::today())->copy()->startOfDay();
        $vence = $mudou->copy()->startOfDay()->addDays(self::prazoDias());
        $atrasada = $hoje->greaterThan($vence);

        return [
            'estado' => $atrasada ? self::ATRASADA : self::PENDENTE,
            'mudou_em' => $mudou->toDateString(),
            'vence_em' => $vence->toDateString(),
            'dias_atraso' => $atrasada ? (int) $vence->diffInDays($hoje) : 0,
        ];
    }

    /** Registra a mudança de diretoria: abre (ou reabre) a pendência na data informada. */
    public function registrarMudanca(Associacao $associacao, Carbon $data): void
    {
        $associacao->forceFill(['diretoria_mudou_em' => $data->toDateString()])->save();
    }

    /** A ata nova chegou: limpa a pendência e grava a data da diretoria vigente. */
    public function ataRecebida(Associacao $associacao, Carbon $data): void
    {
        $associacao->forceFill([
            'diretoria_mudou_em' => null,
            'diretoria_atualizada_em' => $data->toDateString(),
        ])->save();
    }
}
