<?php

 return [
     'home' => 'Dashboard',
     // Chave PRÓPRIA da entry de sidebar do painel interno. NÃO reusar
     // 'home.home': ele também rotula o dashboard do CLIENTE final
     // (Modules/Crm ContactSidebarMenu + views/dashboard/index.blade),
     // que é outra audiência. Medido 2026-08-28: 'home.home' tem 5 sites.
     'visao_geral' => 'Visão geral',
     'welcome_message' => 'Bem-vindo :name',
     'total_sell' => 'Total de vendas',
     'total_purchase' => 'Total de compras',
     'invoice_due' => 'Fatura a pagar',
     'purchase_due' => 'Compre em dívida',
     'today' => 'Today',
     'this_week' => 'Esta semana',
     'this_month' => 'Este mês',
     'this_fy' => 'Este ano fiscal',
     'sells_last_30_days' => 'Vendas nos últimos 30 dias',
     'sells_current_fy' => 'Ano fiscal atual de vendas',
     'total_sells' => 'Total de vendas (:currency)',
     'product_stock_alert' => 'Alerta de estoque do produto',
     'payment_dues' => 'Taxa de pagamento',
     'due_amount' => 'Valor devido',
     'stock_expiry_alert' => 'Alerta de expiração',
     'todays_profit' => 'Benefício de hoje',
 ];
