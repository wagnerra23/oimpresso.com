<?php

use Modules\Crm\Http\Controllers\CapturaLeadController;

// US-CRM-096 — captura pública de lead (sem sessão, sem CSRF). 10 envios/min por IP.
// Flag crm.captura_lead_enabled desligada = 404 (checado no controller).
Route::post('crm/captura/{chave}', [CapturaLeadController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('api.crm.captura.store');
