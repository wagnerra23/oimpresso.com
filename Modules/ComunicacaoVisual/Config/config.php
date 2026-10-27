<?php

return [
    'name' => 'ComunicacaoVisual',

    /*
     * CNAE principal deste vertical: 1813-0/01 — indústria gráfica rápida e comunicação visual.
     * Concorrentes: Mubisys, Zênite, Calcgraf.
     * Diferencial: cálculo m² + PCP gráfico + apontamento + NFe-de-boleto-pago + IA conversacional.
     */
    'cnae' => '1813-0/01',

    /*
     * Unidade padrão de medida para orçamento (m²).
     * Configurável por business futuramente.
     */
    'unidade_medida_default' => 'm2',

    /*
     * US-COMVIS-005: pós-cálculo da OS (custo orçado × real e margem). Nasce desligada.
     */
    'pos_calculo' => (bool) env('COMVIS_POS_CALCULO_ENABLED', false),
];
