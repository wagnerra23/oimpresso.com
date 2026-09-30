<?php

/*
|--------------------------------------------------------------------------
| AFD do REP-P — identidade do PROGRAMA no cabeçalho (Portaria MTP 671/2021)
|--------------------------------------------------------------------------
|
| O cabeçalho (tipo 1) do AFD do REP-P leva dois dados que não são do empregador
| nem das marcações — são do produto:
|   - rep_p_inpi          nº de registro do REP-P no INPI (posições 190-206, 17)
|   - desenvolvedor_cnpj  CNPJ do desenvolvedor do REP-P   (posições 255-268, 14)
|
| Os dois ficam SEM default de propósito ([W] 2026-09-30, thread 12): vazio, o
| gerador recusa e o catálogo marca o AFD como indisponível. Número de registro
| legal não se inventa — um cabeçalho com valor de mentira é pior que não ter AFD.
|
| Mora na raiz de config/ (e não em Modules/Ponto/Config/config.php) porque o
| Larastan conta os env() daquele arquivo numa catraca.
*/

return [
    'rep_p_inpi'         => env('PONTO_REP_P_INPI'),
    'desenvolvedor_cnpj' => env('PONTO_REP_P_DESENVOLVEDOR_CNPJ'),

    // AEJ, registro 08 — identidade do PTRP (Programa de Tratamento de Registro de Ponto). O CNPJ do
    // desenvolvedor é o mesmo acima. Mesma regra: sem default, vazio = AEJ recusado (ADR 0420).
    'ptrp_nome'   => env('PONTO_PTRP_NOME'),
    'ptrp_versao' => env('PONTO_PTRP_VERSAO'),
    'ptrp_razao'  => env('PONTO_PTRP_RAZAO'),
    'ptrp_email'  => env('PONTO_PTRP_EMAIL'),
];
