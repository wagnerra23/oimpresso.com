<?php

return [
    'name' => 'Financeiro',
    'module_version' => '0.1.0',

    /**
     * Juros de mora padrão BR (config por business pode override).
     * 0,033% ao dia (~1% ao mês) + 2% multa pós-vencimento.
     */
    'juros_mora_diario' => 0.0033,
    'multa_atraso' => 0.02,
];
