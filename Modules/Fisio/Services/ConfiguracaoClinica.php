<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use Illuminate\Support\Facades\DB;

/**
 * Configuração da clínica no Fisio (`fisio_settings`): chave-valor por empresa, com preferência
 * opcional por profissional (`$userId`). Valor guardado em JSON, devolvido decodificado.
 *
 * Toda leitura e escrita leva o `business_id` explícito: roda também em job, sem sessão
 * (ADR 0093). Chave de outra empresa nunca é lida.
 */
class ConfiguracaoClinica
{
    public function ler(int $businessId, string $chave, mixed $padrao = null, ?int $userId = null): mixed
    {
        $bruto = DB::table('fisio_settings')
            ->where('business_id', $businessId)
            ->where('user_key', (int) ($userId ?? 0))
            ->where('key', $chave)
            ->value('value');

        return $bruto === null ? $padrao : json_decode((string) $bruto, true);
    }

    /** @param  list<string>  $chaves  @return array<string, mixed> */
    public function varias(int $businessId, array $chaves, ?int $userId = null): array
    {
        return DB::table('fisio_settings')
            ->where('business_id', $businessId)
            ->where('user_key', (int) ($userId ?? 0))
            ->whereIn('key', $chaves)
            ->pluck('value', 'key')
            ->map(fn ($v) => json_decode((string) $v, true))
            ->all();
    }

    public function gravar(int $businessId, string $chave, mixed $valor, ?int $autor = null, ?int $userId = null): void
    {
        $onde = ['business_id' => $businessId, 'user_key' => (int) ($userId ?? 0), 'key' => $chave];
        $dados = ['user_id' => $userId, 'value' => json_encode($valor, JSON_UNESCAPED_UNICODE), 'updated_by' => $autor, 'updated_at' => now()];

        if (DB::table('fisio_settings')->where($onde)->exists()) {
            DB::table('fisio_settings')->where($onde)->update($dados);

            return;
        }
        DB::table('fisio_settings')->insert($onde + $dados + ['created_at' => now()]);
    }
}
