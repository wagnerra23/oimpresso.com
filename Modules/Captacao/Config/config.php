<?php

/*
 * Parâmetros do Modules/Captacao (ADR 0452).
 *
 * Nada aqui liga o módulo para empresa alguma: habilitação é SÓ pelo pacote
 * `captacao_module` do superadmin + permissão `captacao.access` do papel.
 */
return [
    'name' => 'Captacao',

    // D6 da ADR 0452: a quantos dias do prazo a prestação de contas entra em alerta.
    'alerta_prestacao_contas_dias' => 30,
];
