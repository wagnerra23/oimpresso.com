<?php

use Illuminate\Support\Facades\Route;
use Modules\NfeBrasil\Http\Controllers\NfeBrasilController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group([], function () {
    Route::resource('nfebrasil', NfeBrasilController::class)->names('nfebrasil');
});
