<?php

/*
|--------------------------------------------------------------------------
| Config Menus — do nosso Menu:: proprio (app/Services/Menu/)
|--------------------------------------------------------------------------
| Substitui nwidart/laravel-menus (abandonado, nao aceita Laravel 10+).
|
| `styles`: alias curto -> classe FQCN do Presenter. Usado por
| `Menu::render('nome', 'adminltecustom')`. Apenas o presenter que o
| codebase realmente usa (AdminlteCustomPresenter) é registrado — os
| presenters Bootstrap/Foundation da lib original nao foram portados
| porque nunca foram usados pelo UltimatePOS.
|
| `ordering`: quando true, MenuBuilder::getOrderedItems() ordena por
| ->order(N) dos DataControllers. Mantido em true (comportamento legado).
*/

return [
    'styles' => [
        'adminltecustom' => \App\Http\AdminlteCustomPresenter::class,
    ],

    'ordering' => true,
];
