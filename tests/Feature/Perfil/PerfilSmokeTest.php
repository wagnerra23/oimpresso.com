<?php

/**
 * Contrato + smoke da tela /perfil (Meu perfil) — redesign ComVis Inertia.
 *
 * Tier 0: a tela só renderiza/edita o usuário logado. Este teste verifica o
 * contrato Inertia (componente + props) e que o legado /user/profile segue vivo.
 *
 * Precisa DB local seedado + login real (DEV_LOGIN_*), igual RouteSmokeTest.
 * Pulado (skip) sem credenciais — roda em CT100/CI.
 */

use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $user = env('DEV_LOGIN_USERNAME');
    $pass = env('DEV_LOGIN_PASSWORD');
    if (! $user || ! $pass) {
        $this->markTestSkipped('DEV_LOGIN_USERNAME/PASSWORD nao setadas em .env');
    }

    session()->flush();
    auth()->logout();

    $this->post('/login', ['username' => $user, 'password' => $pass]);

    if (! auth()->check()) {
        $this->markTestSkipped('Login falhou — confirmar creds DEV_LOGIN_* + user no DB oimpresso.');
    }
});

it('UC-P01 · renderiza /perfil com o componente Inertia e as props do usuario logado', function () {
    $this->get('/perfil')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('User/Perfil')
            ->has('languages')
            ->has('usuario', fn (Assert $u) => $u
                ->where('email', auth()->user()->email)
                ->has('bank_details')
                ->etc()
            )
        );
});

it('UC-P02 · mantem o legado /user/profile (Blade) intacto — nao 500', function () {
    $this->get('/user/profile')->assertOk();
});
