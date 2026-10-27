<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Services;

use App\Contracts\Comissao\TecnicosDaOs;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * US-AUTO-006 — vários mecânicos por OS, cada um com as horas que digitou (decisão [W] 2026-10-26).
 *
 * `definir()` troca a lista inteira da OS (o formulário manda todos de uma vez). As horas são só
 * registro: não mudam total, preço nem estoque da OS. Quem as usa para dinheiro é a comissão por
 * regra, pelo contrato `TecnicosDaOs`.
 *
 * Tier 0 (ADR 0093): OS e mecânicos validados contra o `$businessId` recebido.
 */
class MecanicosDaOs implements TecnicosDaOs
{
    public const HORAS_MAX = 999.99;

    public function ligado(): bool
    {
        return (bool) config('oficina-auto.multi_mecanico.enabled', false);
    }

    /** @return list<array{user_id:int, nome:string, horas:float}> */
    public function listar(int $businessId, int $serviceOrderId): array
    {
        return DB::table('oficina_os_mecanicos as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.business_id', $businessId)
            ->where('m.service_order_id', $serviceOrderId)
            ->orderByDesc('m.horas')->orderBy('u.first_name')
            ->get(['m.user_id', 'u.first_name', 'u.last_name', 'm.horas'])
            ->map(fn ($r) => [
                'user_id' => (int) $r->user_id,
                'nome'    => trim($r->first_name . ' ' . $r->last_name),
                'horas'   => (float) $r->horas,
            ])->all();
    }

    /**
     * @param  list<array{user_id:int|string, horas:float|int|string}>  $linhas
     * @return list<array{user_id:int, nome:string, horas:float}>
     */
    public function definir(int $businessId, int $serviceOrderId, array $linhas): array
    {
        $daEmpresa = DB::table('service_orders')->where('id', $serviceOrderId)->where('business_id', $businessId)->exists();
        if (! $daEmpresa) {
            throw new InvalidArgumentException('OS não pertence à empresa.');
        }

        $porUsuario = [];
        foreach ($linhas as $l) {
            $uid = (int) ($l['user_id'] ?? 0);
            $horas = round((float) ($l['horas'] ?? 0), 2);
            if ($uid <= 0) {
                throw new InvalidArgumentException('Mecânico inválido.');
            }
            if ($horas <= 0 || $horas > self::HORAS_MAX) {
                throw new InvalidArgumentException('Horas devem ser maiores que zero e até ' . self::HORAS_MAX . '.');
            }
            if (isset($porUsuario[$uid])) {
                throw new InvalidArgumentException('O mesmo mecânico aparece duas vezes.');
            }
            $porUsuario[$uid] = $horas;
        }

        $validos = DB::table('users')->where('business_id', $businessId)->whereIn('id', array_keys($porUsuario))->count();
        if ($validos !== count($porUsuario)) {
            throw new InvalidArgumentException('Mecânico não encontrado nesta empresa.');
        }

        DB::transaction(function () use ($businessId, $serviceOrderId, $porUsuario) {
            DB::table('oficina_os_mecanicos')->where('business_id', $businessId)->where('service_order_id', $serviceOrderId)->delete();
            foreach ($porUsuario as $uid => $horas) {
                DB::table('oficina_os_mecanicos')->insert([
                    'business_id' => $businessId, 'service_order_id' => $serviceOrderId, 'user_id' => $uid,
                    'horas' => $horas, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        return $this->listar($businessId, $serviceOrderId);
    }

    public function daVenda(int $businessId, int $transactionId): array
    {
        $osId = DB::table('service_orders')->where('business_id', $businessId)->where('transaction_id', $transactionId)
            ->whereNull('deleted_at')->value('id');
        if (! $osId) {
            return [];
        }

        return array_map(
            fn ($m) => ['user_id' => $m['user_id'], 'horas' => $m['horas']],
            $this->listar($businessId, (int) $osId),
        );
    }
}
