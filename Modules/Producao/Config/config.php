<?php

return [
    'name' => 'Producao',

    // US-PCP-034 — custo do fabricado numa conta só (App\Domain\Producao\CustoDoFabricado).
    // Desligada: nenhum consumidor troca de conta até o [W] ver o antes → depois.
    'custo_fabricado_unificado' => (bool) env('PRODUCAO_CUSTO_FABRICADO_UNIFICADO', false),
];
