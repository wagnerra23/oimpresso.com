<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Services\Memoria\ProfileDistiller;

uses(Tests\TestCase::class);

/**
 * jana:profile-distill — COPI-26 (incidente 2026-06-20).
 *
 * Cobre a ORQUESTRAÇÃO que faltava (a causa-raiz: NADA iterava businesses chamando
 * o distiller). Usa um ProfileDistiller fake (anonymous subclass) → ZERO chamada LLM.
 *
 * Invariantes:
 *  001. default itera TODOS os businesses (cada um chamado 1×) + exit 0
 *  002. --business=N regenera só aquele business (Tier 0 — não toca os outros)
 *  003. --only-stale pula profiles frescos (<7d), regenera ausentes + stale
 *  004. falha de UM business NÃO aborta o batch (exit FAILURE, demais processados)
 *
 * SQLite-friendly: schema sintético mínimo, sem FULLTEXT/JSON. Pattern dual-mode
 * documentado em reference_tests_pest_canon.md (mesmo molde de RetentionPurgeCommandTest).
 *
 * @see Modules\Jana\Console\Commands\ProfileDistillCommand
 * @see Modules\Jana\Services\Memoria\ProfileDistiller
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor.');
    }

    Schema::dropIfExists('jana_business_profile');
    Schema::dropIfExists('business');

    Schema::create('business', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('name', 191)->nullable();
        $t->timestamps();
    });

    Schema::create('jana_business_profile', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id')->unique();
        $t->text('profile_text');
        $t->unsignedInteger('tokens_estimated')->default(0);
        $t->unsignedInteger('raw_context_tokens')->default(0);
        $t->timestamp('gerado_em')->nullable();
        $t->timestamps();
    });

    DB::table('business')->insert([
        ['id' => 1, 'name' => 'WR2 Sistemas'],
        ['id' => 4, 'name' => 'ROTA LIVRE'],
        ['id' => 99, 'name' => 'Outro tenant'],
    ]);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('jana_business_profile');
        Schema::dropIfExists('business');
    }
});

/**
 * Fake distiller: registra os business_ids chamados e escreve a row como o real
 * (updateOrInsert bumpando gerado_em). `$throwFor` força exceção pra um biz (teste 004).
 */
function fakeDistiller(array $throwFor = []): ProfileDistiller
{
    return new class($throwFor) extends ProfileDistiller {
        /** @var list<int> */
        public array $called = [];

        public function __construct(public array $throwFor = [])
        {
            // sem parent::__construct — não precisa de ContextSnapshotService no fake
        }

        public function destilar(int $businessId): array
        {
            $this->called[] = $businessId;

            if (in_array($businessId, $this->throwFor, true)) {
                throw new \RuntimeException("LLM timeout simulado biz {$businessId}");
            }

            $texto = "Perfil destilado do business {$businessId}.";
            DB::table('jana_business_profile')->updateOrInsert(
                ['business_id' => $businessId],
                [
                    'profile_text' => $texto,
                    'tokens_estimated' => 10,
                    'raw_context_tokens' => 20,
                    'gerado_em' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            return [
                'profile_text' => $texto,
                'tokens_estimated' => 10,
                'raw_context_tokens' => 20,
                'compression_ratio' => 2.0,
            ];
        }
    };
}

it('ProfileDistill 001 — default itera TODOS os businesses (cada um 1×) + exit 0', function () {
    $fake = fakeDistiller();
    app()->instance(ProfileDistiller::class, $fake);

    $exit = Artisan::call('jana:profile-distill');

    expect($exit)->toBe(0)
        ->and($fake->called)->toEqualCanonicalizing([1, 4, 99])
        ->and(DB::table('jana_business_profile')->count())->toBe(3);

    // Todas as rows ficaram frescas (gerado_em = agora).
    $stale = DB::table('jana_business_profile')->where('gerado_em', '<', now()->subDays(7))->count();
    expect($stale)->toBe(0);
});

it('ProfileDistill 002 — --business=4 regenera só ROTA LIVRE (Tier 0 — não toca os outros)', function () {
    $fake = fakeDistiller();
    app()->instance(ProfileDistiller::class, $fake);

    $exit = Artisan::call('jana:profile-distill', ['--business' => '4']);

    expect($exit)->toBe(0)
        ->and($fake->called)->toBe([4])
        ->and(DB::table('jana_business_profile')->where('business_id', 4)->exists())->toBeTrue()
        ->and(DB::table('jana_business_profile')->where('business_id', 1)->exists())->toBeFalse()
        ->and(DB::table('jana_business_profile')->where('business_id', 99)->exists())->toBeFalse();
});

it('ProfileDistill 003 — --only-stale pula frescos (<7d), regenera ausentes + stale', function () {
    // biz=1 fresco (não deve ser tocado) · biz=4 stale (10d) · biz=99 ausente
    DB::table('jana_business_profile')->insert([
        ['business_id' => 1, 'profile_text' => 'fresco', 'gerado_em' => now()->subDay(), 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 4, 'profile_text' => 'velho', 'gerado_em' => now()->subDays(10), 'created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)],
    ]);

    $fake = fakeDistiller();
    app()->instance(ProfileDistiller::class, $fake);

    $exit = Artisan::call('jana:profile-distill', ['--only-stale' => true]);

    expect($exit)->toBe(0)
        ->and($fake->called)->toEqualCanonicalizing([4, 99]) // biz=1 fresco NÃO foi chamado
        ->and($fake->called)->not->toContain(1);

    // biz=1 manteve o texto original (não regenerado).
    expect(DB::table('jana_business_profile')->where('business_id', 1)->value('profile_text'))->toBe('fresco');
});

it('ProfileDistill 004 — falha de UM business não aborta o batch (exit FAILURE, demais processados)', function () {
    $fake = fakeDistiller(throwFor: [4]); // biz=4 lança; 1 e 99 devem seguir
    app()->instance(ProfileDistiller::class, $fake);

    $exit = Artisan::call('jana:profile-distill');

    expect($exit)->toBe(1) // FAILURE porque houve falha
        ->and($fake->called)->toEqualCanonicalizing([1, 4, 99]); // todos tentados

    // biz=1 e biz=99 foram persistidos apesar da exceção no biz=4.
    expect(DB::table('jana_business_profile')->where('business_id', 1)->exists())->toBeTrue()
        ->and(DB::table('jana_business_profile')->where('business_id', 99)->exists())->toBeTrue()
        ->and(DB::table('jana_business_profile')->where('business_id', 4)->exists())->toBeFalse();
});
