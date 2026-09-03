<?php

return [
    // O rótulo que o [W] procura em /superadmin/packages/{id}/edit. Era 'Copiloto' e ele
    // procurava "Jana" — a caixa existia e não era achável. Medido em 2026-08-26: 129 dos
    // 130 usuários têm `language = pt` (biz=1 e biz=4 são 100% pt) e `app.locale = pt`,
    // então trocar aqui é trocar o que praticamente todo mundo vê. Commit separado de
    // propósito: é copy user-visible, e copy é decisão do [W] — reverter isto não desfaz
    // o conserto da chave.
    'module_label' => 'Jana',

    // Rótulo do 2º checkbox do módulo em /superadmin/packages/{id}/edit. É o TIER
    // (qual plano da Jana), distinto de `module_label`, que é o módulo on/off (o
    // business TEM a Jana). Marcar este sem aquele não faz sentido e a UI não impede
    // — quem lê o tier é o selo do header, e ele só aparece pra quem já tem a Jana.
    'module_label_pro' => 'Jana Pro (plano)',

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
        'acoes'      => 'Ações',
        'plataforma' => 'Plataforma',
        'custos'     => 'Custos de IA',
    ],
];
