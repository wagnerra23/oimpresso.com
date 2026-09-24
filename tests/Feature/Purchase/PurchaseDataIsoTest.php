<?php

declare(strict_types=1);

use App\Utils\Util;

// Sem uses(TestCase): o tests/Pest.php já o aplica a tests/Feature — declarar de novo
// derruba a suíte inteira ("Test case [Tests\TestCase] can not be used").

/**
 * UC-PURCRE-08 — salvar aceita a data que a própria tela manda.
 *
 * Contrato: resources/js/Pages/Purchase/Create.casos.md
 *   @covers-uc UC-PURCRE-08  store()/update() aceitam o ISO do <input type="datetime-local">
 *
 * Origem (medida, não suposta): smoke em prod biz=1 em 2026-09-24 (thread 05 do playbook
 * Compras). A grade montou 4 linhas certas, e o store() caiu em Carbon "Trailing data" dentro
 * do Util::uf_date(): a tela React manda '2026-09-24 14:37' e o uf_date() monta o formato
 * da empresa ('d/m/Y H:i'). Os testes de store() existentes mandavam a data já no formato
 * da empresa (PurchaseGradeMatrixTest usa m/d/Y H:i) — por isso passavam.
 *
 * Não precisa de banco: o conversor lê só a sessão.
 */
beforeEach(function () {
    session(['business.date_format' => 'd/m/Y', 'business.time_format' => 24]);
});

it('UC-PURCRE-08 · aceita o ISO que o datetime-local envia (com espaço, como o Create.tsx troca o T)', function () {
    expect((new Util())->uf_datetime_input('2026-09-24 14:37'))->toBe('2026-09-24 14:37:00');
});

it('UC-PURCRE-08 · aceita o ISO cru do input (com T) e com segundos (default_datetime do controller)', function () {
    $util = new Util();

    expect($util->uf_datetime_input('2026-09-24T14:37'))->toBe('2026-09-24 14:37:00');
    expect($util->uf_datetime_input('2026-09-24 14:37:05'))->toBe('2026-09-24 14:37:05');
});

it('UC-PURCRE-08 · o formato da empresa (o que a Blade manda) continua valendo', function () {
    expect((new Util())->uf_datetime_input('24/09/2026 14:37'))->toBe('2026-09-24 14:37:00');
});

it('UC-PURCRE-08 · controle — o uf_date() cru estoura com o ISO; é o defeito que o conversor fecha', function () {
    // Se isto parar de estourar, o formato da empresa mudou e o teste acima deixou de provar
    // alguma coisa: ele passaria sem o conversor.
    expect(fn () => (new Util())->uf_date('2026-09-24 14:37', true))->toThrow(\Throwable::class);
});
