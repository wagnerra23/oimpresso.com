<?php

namespace Modules\Marketplaces\Http\Controllers;

use Illuminate\Routing\Controller;
use Menu;
use Modules\Marketplaces\Services\MarketplacesAcesso;

/**
 * DataController — Modules/Marketplaces (ADR 0439).
 *
 * Habilitação é SEMPRE por UI (pacote do superadmin + permissão do papel), nunca por if de
 * business no código ({@see memory/proibicoes.md}). O item do menu segue a MESMA regra do
 * middleware das rotas (MarketplacesAcesso), para menu e rota nunca discordarem.
 */
class DataController extends Controller
{
    public const PACOTE = 'marketplaces_module';

    public function superadmin_package(): array
    {
        return [
            ['name' => self::PACOTE, 'label' => 'Modulo Marketplaces (Mercado Livre)', 'default' => false],
        ];
    }

    public function user_permissions(): array
    {
        return [
            ['value' => 'marketplaces.access', 'label' => 'Marketplaces: acessar o modulo', 'default' => false],
            ['value' => 'marketplaces.account.connect', 'label' => 'Marketplaces: conectar e desconectar contas', 'default' => false],
        ];
    }

    public function modifyAdminMenu(): void
    {
        if (! app(MarketplacesAcesso::class)->liberado(auth()->user(), session()->get('user.business_id'))) {
            return;
        }

        $ativo = request()->segment(1) === 'marketplaces';
        Menu::modify('admin-sidebar-menu', function ($menu) use ($ativo) {
            $menu->url(url('/marketplaces/pedidos'), 'Marketplaces', [
                'icon' => 'fa fa-store',
                'active' => $ativo,
                // Canal de venda: grupo COMERCIAL do sidebar (CRM · Vendas), como o Fisio.
                'group' => 'comercial',
            ]);
        });
    }
}
