<?php

Route::get('pesapal-callback',['as'=>'pesapal-callback', 'uses'=>'App\Vendor\Pesapal\PesapalAPIController@handleCallback']);
Route::get('pesapal-ipn', ['as'=>'pesapal-ipn', 'uses'=>'App\Vendor\Pesapal\PesapalAPIController@handleIPN']);