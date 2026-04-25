<?php

return [
    'module_label' => 'Financeiro',

    // Permissões
    'permissao_acesso' => 'Financeiro: acesso ao módulo',
    'permissao_dashboard_view' => 'Financeiro: ver dashboard unificado',
    'permissao_contas_receber_view' => 'Financeiro: ver contas a receber',
    'permissao_contas_receber_create' => 'Financeiro: criar contas a receber',
    'permissao_contas_receber_baixar' => 'Financeiro: baixar contas a receber',
    'permissao_contas_pagar_view' => 'Financeiro: ver contas a pagar',
    'permissao_contas_pagar_create' => 'Financeiro: criar contas a pagar',
    'permissao_contas_pagar_pagar' => 'Financeiro: pagar contas a pagar',
    'permissao_caixa_view' => 'Financeiro: ver caixa e fluxo',
    'permissao_contas_bancarias_manage' => 'Financeiro: gerenciar contas bancárias',
    'permissao_conciliacao_manage' => 'Financeiro: conciliação bancária',
    'permissao_relatorios_view' => 'Financeiro: ver relatórios',
    'permissao_relatorios_share' => 'Financeiro: compartilhar relatórios (link público)',

    // Menu
    'menu' => [
        'dashboard' => 'Visão geral',
        'contas_receber' => 'Contas a Receber',
        'contas_pagar' => 'Contas a Pagar',
        'caixa' => 'Caixa',
        'contas_bancarias' => 'Contas bancárias',
        'categorias' => 'Categorias',
        'conciliacao' => 'Conciliação',
        'relatorios' => 'Relatórios',
    ],

    // Categorias
    'categorias' => [
        'titulo' => 'Categorias',
        'subtitulo' => 'Tags livres pra organizar lançamentos e relatórios. Complementam o plano de contas.',
        'nova' => 'Nova categoria',
        'editar' => 'Editar categoria',
        'nome' => 'Nome',
        'cor' => 'Cor',
        'plano_conta' => 'Plano de contas (opcional)',
        'tipo' => 'Tipo',
        'tipos' => [
            'receita' => 'Receita',
            'despesa' => 'Despesa',
            'ambos' => 'Ambos',
        ],
        'ativo' => 'Ativa',
        'inativa' => 'Inativa',
        'ativar' => 'Ativar',
        'inativar' => 'Inativar',
        'excluir' => 'Excluir',
        'salvar' => 'Salvar',
        'cancelar' => 'Cancelar',
        'sem_categoria' => 'Nenhuma categoria cadastrada ainda.',
        'confirma_exclusao' => 'Tem certeza? A categoria será removida (soft delete).',
        'sem_plano' => 'Sem vínculo',
    ],
];
