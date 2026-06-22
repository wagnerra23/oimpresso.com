<?php

use Illuminate\Support\Facades\Route;

// ZumbiController DEPRECADO: só redirect 301, controller NÃO referenciado
// (use/::class) → tela renderizada só por controller fora das rotas = zumbi.
Route::redirect('/zumbi', '/viva', 301);
