<?php

return [
    'name' => 'Essentials',
    'module_version' => '4.0',
    'chat_refresh_interval' => 20, //Interval to check for new chats in seconds, default 20sec

    // Folha CLT com encargos (ADR-mãe da folha, 2026-09-05). Desligada: por enquanto só existe o
    // modelo de verba (passo 3 do §9). Motor e tela, quando vierem, só rodam com ela ligada.
    'folha_clt' => [
        // Config de módulo é lida no config:cache, então o env() resolve; o Larastan só reconhece config/.
        'enabled' => env( // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
            'ESSENTIALS_FOLHA_CLT_ENABLED', false),
    ],
];
