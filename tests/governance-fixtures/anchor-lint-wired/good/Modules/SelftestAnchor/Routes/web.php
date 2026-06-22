<?php

use Illuminate\Support\Facades\Route;
use Modules\SelftestAnchor\Http\Controllers\VivaController;

// VivaController REFERENCIADO (use + ::class) → tela viva no roteador.
Route::get('/viva', [VivaController::class, 'index'])->name('selftest.viva');
