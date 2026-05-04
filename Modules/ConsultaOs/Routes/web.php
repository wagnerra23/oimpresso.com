<?php

use Modules\ConsultaOs\Http\Controllers\ConsultaOsController;

// Portal publico de consulta de OS — sem middleware auth, espelha
// Modules/Repair/Routes/web.php (rotas /repair-status e /post-repair-status).
Route::prefix('consulta-os')->name('consulta-os.')->group(function () {

    Route::get('/', [ConsultaOsController::class, 'index'])
        ->name('index');

    Route::get('/buscar', [ConsultaOsController::class, 'buscar'])
        ->name('buscar')
        ->middleware('throttle:30,1');
});
