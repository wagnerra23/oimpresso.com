<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// D8.a Security Wave 10 — throttle:60,1 em endpoint API AssetManagement
// (atualmente apenas user info echo, mas hardening preventivo).
Route::middleware(['throttle:60,1', 'auth:api'])->get('/asset', function (Request $request) {
    return $request->user();
});
