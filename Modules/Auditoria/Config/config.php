<?php

return [
    'name' => 'Auditoria',

    /*
     * Janelas de revert por nivel de permissao (em horas).
     * Per ADR 0127 §princípio 5.
     */
    'revert_window_own_hours'        => 24,
    'revert_window_admin_days'       => 30,
    'revert_window_unlimited'        => null, // superadmin

    /*
     * Pagination da listagem /auditoria
     */
    'page_size' => 50,
];
