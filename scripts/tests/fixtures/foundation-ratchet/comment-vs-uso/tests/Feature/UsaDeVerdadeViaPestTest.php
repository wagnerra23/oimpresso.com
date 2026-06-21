<?php

// FIXTURE (comment-vs-uso) do foundation-ratchet selftest — USO REAL via Pest `uses()`.
// Cobre o 2º padrão de detecção (o good/bad cobre `use RefreshDatabase;` clássico).
// DEVE contar: aplica o trait de fato. Nunca é executado — só lido como texto pelo ratchet.

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exemplo que aplica o trait pesado de verdade', function () {
    expect(true)->toBeTrue();
});
