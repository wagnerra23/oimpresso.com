<?php

/*
 * US-PCP-034 — custo do produto fabricado numa conta só (App\Domain\Producao\CustoDoFabricado).
 * Desligada: nenhum consumidor troca de conta até o [W] ver o antes → depois.
 */
return [
    'unificado' => (bool) env('CUSTO_FABRICADO_UNIFICADO', false),
];
