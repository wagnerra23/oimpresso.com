<?php

namespace Modules\Marketplaces\Http\Controllers;

use Illuminate\Routing\Controller;

/**
 * DataController — Modules/Marketplaces (ADR 0439).
 *
 * Habilitação é SEMPRE por UI (pacote do superadmin + permissão do papel), nunca por if de
 * business no código ({@see memory/proibicoes.md}). Sem item de menu até existir a tela de
 * pedidos (US-MKT-008).
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
        // US-MKT-008 cria a tela de pedidos e o item de menu.
    }
}
