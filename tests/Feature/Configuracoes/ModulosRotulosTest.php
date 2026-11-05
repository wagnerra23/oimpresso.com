<?php

declare(strict_types=1);

use App\Utils\ModuleUtil;

/**
 * Aba Módulos da Configuração da empresa: os 14 rótulos que ModuleUtil::availableModules()
 * entrega à tela, em português. Os nomes vêm do protótipo (configuracoes-page.jsx, MODULOS).
 *
 * Os 7 rótulos trocados usam chaves lang_v1.modulo_* próprias. As traduções gerais que
 * estavam aqui seguem com outros consumidores: sale.pos_sale (11 arquivos),
 * restaurant.service_staff (27) e lang_v1.stock_transfers (7, um deles o Sidebar.tsx,
 * que agrupa o menu pelo texto "Transferências de ações").
 */
beforeEach(fn () => app()->setLocale('pt'));

it('entrega os 14 módulos com os rótulos do protótipo', function () {
    $rotulos = array_map(fn ($m) => $m['name'], app(ModuleUtil::class)->availableModules());

    expect($rotulos)->toBe([
        'purchases' => 'Compras',
        'add_sale' => 'Adicionar venda',
        'pos_sale' => 'PDV',
        'stock_transfers' => 'Transferência de estoque',
        'stock_adjustment' => 'Ajuste de estoque',
        'expenses' => 'Despesas',
        'account' => 'Conta bancária',
        'tables' => 'Mesas',
        'modifiers' => 'Modificadores',
        'service_staff' => 'Atendentes',
        'booking' => 'Agenda / reservas',
        'kitchen' => 'Cozinha',
        'subscription' => 'Cobrança recorrente',
        'types_of_service' => 'Tipos de serviço',
    ]);
});

it('não muda as traduções gerais que outras telas usam', function () {
    expect(__('sale.pos_sale'))->toBe('POS')
        ->and(__('restaurant.service_staff'))->toBe('Responsável pela venda')
        ->and(__('lang_v1.stock_transfers'))->toBe('Transferências de ações');
});
