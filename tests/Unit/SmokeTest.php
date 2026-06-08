<?php

it('php version satisfaz requisito minimo do composer (>=8.1)', function () {
    expect(PHP_VERSION_ID)->toBeGreaterThanOrEqual(80100);
});

it('composer autoload carrega classe do framework', function () {
    expect(class_exists(\Illuminate\Foundation\Application::class))->toBeTrue();
});

it('composer autoload carrega classe do projeto (helper)', function () {
    expect(function_exists('config'))->toBeTrue();
});
