<?php

/*
 * Parâmetros do Modules/Associacoes (ADR 0451).
 *
 * Nada aqui liga o módulo para empresa alguma: habilitação é SÓ pelo pacote
 * `associacoes_module` do superadmin + permissão `associacoes.access` do papel.
 */
return [
    'name' => 'Associacoes',

    // D7 da ADR 0451: a associação avisa mudança de diretoria em até 30 dias (estatuto da rede).
    'prazo_ata_diretoria_dias' => 30,

    // D5 da ADR 0451: consentimento por finalidade, versionado. A versão vigente vem daqui,
    // nunca do corpo do request. Mudou o texto de uma finalidade? Suba a versão dela: quem
    // aceitou a versão anterior deixa de ter consentimento vigente e precisa aceitar de novo.
    'consentimento_versoes' => [
        'associada' => '1',
        'whatsapp' => '1',
        'visivel_rede' => '1',
    ],

    // Texto que a associada lê antes de aceitar (onda 4, US-ASSOC-003). Linguagem simples, uma
    // finalidade por item. `associada` é obrigatória para o cadastro; as outras são opcionais.
    'consentimento_textos' => [
        'associada' => [
            'titulo' => 'Fazer parte da associação',
            'texto' => 'Guardamos seu nome, celular e cidade para registrar que você é associada, avisar sobre encontros e prestar contas à rede. Não pedimos CPF nem informação de saúde. Você pode pedir para sair quando quiser.',
            'obrigatorio' => true,
        ],
        'whatsapp' => [
            'titulo' => 'Receber mensagens no WhatsApp',
            'texto' => 'A associação pode mandar avisos e convites pelo WhatsApp no seu celular. Você pode pedir para parar a qualquer momento.',
            'obrigatorio' => false,
        ],
        'visivel_rede' => [
            'titulo' => 'Aparecer para a diretoria da rede',
            'texto' => 'Seu nome pode ser visto pela diretoria da rede de associações, além da sua associação. Sem este aceite, a diretoria só sabe que existe uma associada na sua cidade; o nome fica com a sua associação.',
            'obrigatorio' => false,
        ],
    ],
];
