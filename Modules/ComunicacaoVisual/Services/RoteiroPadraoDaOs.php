<?php

namespace Modules\ComunicacaoVisual\Services;

use App\Contracts\Producao\ProgramacaoDaOs;
use Illuminate\Support\Facades\DB;
use Modules\ComunicacaoVisual\Entities\Os;

/**
 * A OS da Comunicação Visual nasce com o roteiro padrão da empresa e entra no quadro do PCP
 * (US-COMVIS-003, decisão [W] 2026-10-26: "toda OS de comunicação visual nasce com o roteiro padrão
 * da empresa, e o operador pode trocar à mão depois").
 *
 * O padrão fica em `business.common_settings[CHAVE] = {roteiro_id}`, no mesmo formato da assinatura
 * de entrega da oficina. Sem padrão definido, ou sem o módulo Producao ligado, a OS nasce como hoje
 * (sem etapas, fora do quadro). As etapas são criadas pelo Producao, pelo contrato
 * `App\Contracts\Producao\ProgramacaoDaOs` (a CV não importa o Producao).
 *
 * Tier 0: o roteiro tem de ser da empresa (o Producao confere); tudo pelo `$businessId` recebido.
 */
final class RoteiroPadraoDaOs
{
    public const CHAVE = 'comvis_roteiro_padrao';

    /** Tipo de OS no PCP (o mesmo de Producao\Services\RomaneioDeImpressao::OS_TIPO). */
    public const OS_TIPO = 'comvis_os';

    public function atual(int $businessId): ?int
    {
        $id = (int) (($this->ler($businessId)[self::CHAVE] ?? [])['roteiro_id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    public function definir(int $businessId, ?int $roteiroId): void
    {
        $settings = $this->ler($businessId);
        $settings[self::CHAVE] = ['roteiro_id' => $roteiroId];

        DB::table('business')->where('id', $businessId)->update(['common_settings' => json_encode($settings)]);
    }

    /**
     * Programa a OS com o roteiro padrão. Devolve quantas etapas criou (0 = ficou fora do quadro).
     * Autor das etapas: quem aprovou; sem ele (o cliente aceitou pelo link), o dono da empresa.
     */
    public function programar(int $businessId, Os $os, ?int $userId): int
    {
        $roteiroId = $this->atual($businessId);
        if ($roteiroId === null || ! app()->bound(ProgramacaoDaOs::class)) {
            return 0;
        }
        $autor = $userId ?? (int) DB::table('business')->where('id', $businessId)->value('owner_id');

        return app(ProgramacaoDaOs::class)->programar($businessId, self::OS_TIPO, (int) $os->id, $roteiroId, $autor);
    }

    /** @return array<string, mixed> */
    private function ler(int $businessId): array
    {
        $settings = json_decode((string) DB::table('business')->where('id', $businessId)->value('common_settings'), true);

        return is_array($settings) ? $settings : [];
    }
}
