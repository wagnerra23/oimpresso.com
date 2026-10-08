<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;

// O UserFactory sorteava `username` com faker->unique()->userName(). O unique() só vale dentro do processo, então num
// banco persistente (CT 100) o nome sorteado podia já existir e o teste caía em users_username_unique — o flaky de
// ~1 em 10 rodadas da pasta tests/Feature/Relatorios. Este teste força a colisão: sorteia o mesmo nome duas vezes
// com a mesma semente, com o primeiro já gravado no banco.

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }
});

test('UserFactory não colide com username que já está no banco', function () {
    $business = $this->seededTenant();
    $faker = app(\Faker\Generator::class);

    // O nome que o factory sortearia com esta semente (definition: first_name, last_name, depois username).
    $faker->seed(4242);
    $faker->firstName();
    $faker->lastName();
    $sorteado = $faker->userName();

    // Ele já existe no banco.
    User::factory()->create(['business_id' => $business->id, 'username' => $sorteado]);

    // Mesma semente, unique() zerado: o factory sorteia o mesmo nome de novo.
    $faker->seed(4242);
    $faker->unique(true);
    $novo = User::factory()->create(['business_id' => $business->id]);

    expect($novo->username)->not->toBe($sorteado);
    expect(str_starts_with((string) $novo->username, $sorteado.'_'))->toBeTrue();
});
