<?php

/*
 * Site da Uniafibro (https://uniafibro.com.br). Ver memory/requisitos/Site/RUNBOOK-uniafibro.md.
 *
 * whatsapp: número com DDI e DDD (ex.: 5548999999999). Vazio = o botão flutuante não aparece.
 * Decisão [W] 2026-11-14: fica vazio até a associação ter um número próprio.
 */
return [
    'whatsapp' => env('UNIAFIBRO_WHATSAPP', ''),
];
