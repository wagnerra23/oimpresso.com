<?php

namespace App\Support;

/**
 * Chave de cutover MWART (Blade → React) por empresa — fonte única da regra.
 *
 * Cada tela tem uma entrada em `config/mwart.php` com `enabled` + `business_ids`
 * (lista vazia = todas as empresas). A regra é a mesma que os controllers do Repair
 * repetem em `mwartEnabled()`; aqui ela mora num lugar só para as telas novas.
 *
 * Multi-tenant (ADR 0093): a lista de empresas vem SÓ da env (`MWART_<TELA>_BIZ`).
 * Nenhum business_id é escrito em código.
 */
final class Mwart
{
    /** A flag da tela está ligada para esta empresa? */
    public static function ativo(string $key, int $businessId): bool
    {
        if (! config("mwart.{$key}.enabled")) {
            return false;
        }

        $empresas = (array) config("mwart.{$key}.business_ids", []);

        return empty($empresas) || in_array($businessId, $empresas, true);
    }

    /**
     * Esta requisição deve receber a tela React?
     *
     * - `X-Inertia` presente → sim (comportamento de hoje, sem flag).
     * - chamada AJAX sem `X-Inertia` → não: é o DataTable do Blade ou um `fetch` da
     *   própria tela pedindo JSON no MESMO endereço; trocar por página quebraria os dois.
     * - GET comum (URL direta, menu) → só se a flag da tela estiver ligada para a empresa.
     */
    public static function telaReact(string $key, ?int $businessId = null): bool
    {
        $request = request();

        if ($request->header('X-Inertia')) {
            return true;
        }

        if ($request->ajax()) {
            return false;
        }

        $businessId ??= (int) $request->session()->get('user.business_id');

        return self::ativo($key, $businessId);
    }
}
