<?php

/*
 * Site da Uniafibro (https://uniafibro.com.br). Ver memory/requisitos/Site/RUNBOOK-uniafibro.md §6.
 *
 * contatos: dois slots, com WhatsApp (DDI + DDD + número, ex.: 5548999999999) e e-mail.
 * Decisão [W] 2026-11-15: "preenche depois" — ficam vazios no .env até ele preencher.
 * Slot vazio não aparece no site; com os dois vazios, nem o botão flutuante nem o bloco
 * de contato do rodapé são renderizados.
 *
 * A chave do slot é só interna: o site nunca mostra nome de pessoa (regra da home: só
 * entidades, sem nome de dirigente — tests/Feature/Site/UniafibroSiteTest.php).
 */
return [
    'contatos' => [
        'wagner' => [
            'whatsapp' => env('UNIAFIBRO_WAGNER_WHATSAPP', ''),
            'email' => env('UNIAFIBRO_WAGNER_EMAIL', ''),
        ],
        'giselli' => [
            'whatsapp' => env('UNIAFIBRO_GISELLI_WHATSAPP', ''),
            'email' => env('UNIAFIBRO_GISELLI_EMAIL', ''),
        ],
    ],
];
