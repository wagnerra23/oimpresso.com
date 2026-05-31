<?php

declare(strict_types=1);

use App\Contact;

/**
 * Pest test — Contact::full_name_with_business dedup quando supplier_business_name
 * é igual ao full_name. Bug Wagner @ Larissa 2026-05-27: recibo mostrava
 * "CLAUDIO MENDES, CLAUDIO MENDES" porque cliente foi cadastrado com first_name
 * e supplier_business_name idênticos.
 */

it('full_name_with_business dedupe quando supplier_business_name === full_name', function () {
    $c = new Contact();
    $c->first_name = 'CLAUDIO MENDES';
    $c->supplier_business_name = 'CLAUDIO MENDES';
    expect($c->full_name_with_business)->toBe('CLAUDIO MENDES');
});

it('full_name_with_business dedupe case-insensitive + trim', function () {
    $c = new Contact();
    $c->first_name = 'Claudio Mendes';
    $c->supplier_business_name = '  CLAUDIO MENDES  ';
    expect($c->full_name_with_business)->toBe('Claudio Mendes');
});

it('full_name_with_business preserva comportamento quando NOMES DIFERENTES', function () {
    $c = new Contact();
    $c->first_name = 'Joao';
    $c->supplier_business_name = 'Empresa XYZ';
    expect($c->full_name_with_business)->toBe('Empresa XYZ, Joao');
});

it('full_name_with_business retorna so full_name quando supplier vazio', function () {
    $c = new Contact();
    $c->first_name = 'Joao';
    $c->supplier_business_name = null;
    expect($c->full_name_with_business)->toBe('Joao');
});

it('full_name_with_business compoe prefix + first + middle + last', function () {
    $c = new Contact();
    $c->prefix = 'Sr.';
    $c->first_name = 'Joao';
    $c->middle_name = 'da';
    $c->last_name = 'Silva';
    expect($c->full_name_with_business)->toBe('Sr. Joao da Silva');
});
