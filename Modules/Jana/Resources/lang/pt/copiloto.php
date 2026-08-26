<?php

return [
    // O rótulo que o [W] procura em /superadmin/packages/{id}/edit. Era 'Copiloto' e ele
    // procurava "Jana" — a caixa existia e não era achável. Medido em 2026-08-26: 129 dos
    // 130 usuários têm `language = pt` (biz=1 e biz=4 são 100% pt) e `app.locale = pt`,
    // então trocar aqui é trocar o que praticamente todo mundo vê. Commit separado de
    // propósito: é copy user-visible, e copy é decisão do [W] — reverter isto não desfaz
    // o conserto da chave.
    'module_label' => 'Jana',

    'permissao_acesso'         => 'Copiloto: Acesso ao módulo',
    'permissao_chat'           => 'Copiloto: Conversar (chat IA)',
    'permissao_metas'          => 'Copiloto: Gerenciar metas',
    'permissao_superadmin'     => 'Copiloto: Configurar metas da plataforma (superadmin)',
    'permissao_admin_custos'   => 'Copiloto: Visualizar custos de IA (admin)',

    'menu' => [
        'conversar'  => 'Conversar',
        'dashboard'  => 'Dashboard',
        'metas'      => 'Metas',
        'alertas'    => 'Alertas',
        'plataforma' => 'Plataforma',
        'custos'     => 'Custos de IA',
    ],
];
