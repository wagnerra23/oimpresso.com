<?php

return [
    /*
     * O que os global scopes de empresa fazem quando a consulta não tem empresa no contexto
     * (ADR 0453): `aberto` (legado: não filtra), `log` (não filtra e registra
     * tenancy.escopo_sem_contexto) ou `fechado` (lança App\Tenancy\ContextoEmpresaAusente).
     * Rollout: aberto → log em produção até zerar o que não é plataforma → fechado.
     */
    'escopo_sem_contexto' => env('TENANCY_ESCOPO_SEM_CONTEXTO', 'aberto'),
];
