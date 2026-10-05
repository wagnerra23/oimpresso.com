<?php

declare(strict_types=1);

namespace App\Domain\Oficina;

use Illuminate\Support\Facades\DB;

/**
 * Regra única de placa de veículo da Oficina: normalização e "placa já em outro veículo ativo".
 *
 * Decisão [W] 2026-10-05: "ativas não pode duplicar" — vale no app (POST /api/app/veiculos) e na
 * web (cadastro e edição). Mora no núcleo para o app e o módulo OficinaAuto usarem a MESMA regra
 * (DependencyDirectionTest: a seta vai módulo → núcleo). Ativo = não excluído (`deleted_at` nulo);
 * a tabela não tem outro estado de inativo.
 */
final class PlacaVeiculo
{
    /** "rba-2h78 " → "RBA2H78" (mesma regra do VehicleLookupService::normalizePlate). */
    public static function normalizar(?string $bruta): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $bruta));
    }

    /**
     * Id do veículo ATIVO do business que já usa a placa, como principal ou de reboque; null se
     * nenhum. Compara normalizado dos dois lados (o legado gravou placa com hífen, espaço e
     * minúscula). `$exceto` = o próprio veículo, na edição.
     */
    public static function veiculoAtivoCom(int $businessId, string $placa, ?int $exceto = null): ?int
    {
        $placa = self::normalizar($placa);
        if ($placa === '') {
            return null;
        }

        $norm = "UPPER(REPLACE(REPLACE(REPLACE(COALESCE(%s, ''), '-', ''), ' ', ''), '.', ''))";
        $id = DB::table('vehicles')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->when($exceto !== null, fn ($q) => $q->where('id', '!=', $exceto))
            ->where(fn ($w) => $w->whereRaw(sprintf($norm, 'plate') . ' = ?', [$placa])
                ->orWhereRaw(sprintf($norm, 'secondary_plate') . ' = ?', [$placa]))
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public const MENSAGEM_DUPLICADA = 'Esta placa já está em outro veículo ativo.';
}
