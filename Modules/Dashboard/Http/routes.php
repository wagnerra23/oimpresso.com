<?php

Route::group(['middleware' => ['web', 'authh', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu'], 'prefix' => 'dashboard', 'namespace' => 'Modules\Dashboard\Http\Controllers'], function () {
    Route::put('project/{id}/post-status', 'ProjectController@postProjectStatus');
    Route::get('install', 'InstallController@index');
    Route::post('install', 'InstallController@install');
    Route::get('install/uninstall', 'InstallController@uninstall');
    Route::get('install/update', 'InstallController@update');
});
