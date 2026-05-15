<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Whatsapp\Entities\EmployeePerformance;
use Modules\Whatsapp\Services\EmployeePerformance\EmployeePerformanceRebuilder;

uses(Tests\TestCase::class);

/**
 * US-WA-VOZ-003 — EmployeePerformanceRebuilder.
 *
 * Cobertura:
 *   1. rebuild() com user_id real (PRIMÁRIO) cria scorecard
 *   2. rebuild() com heuristic_name (FALLBACK) cria scorecard
 *   3. rebuild() é idempotente
 *   4. Stats agregados batem count real
 *   5. Scoring 0-100 com breakdown transparente
 *   6. faixa() classifica corretamente (excelente/bom/regular/abaixo)
 *   7. Tier 0: biz=99 NÃO vê biz=1
 *   8. Velocidade mediana calculada (inbound prev → outbound)
 *   9. SLA breach contado quando >4h
 */
beforeEach(function () {
    foreach (['employee_performance', 'customer_memory', 'messages', 'conversations', 'users', 'business'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('business', function ($table) {
        $table->bigIncrements('id');
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('users', function ($table) {
        $table->bigIncrements('id');
        $table->string('username', 60);
        $table->string('first_name', 60)->nullable();
        $table->string('last_name', 60)->nullable();
        $table->timestamps();
    });

    Schema::create('conversations', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('channel_id');
        $table->string('customer_external_id', 40)->nullable();
        $table->timestamps();
    });

    Schema::create('messages', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('conversation_id');
        $table->string('direction', 20);
        $table->string('type', 20)->default('text');
        $table->text('body')->nullable();
        $table->boolean('is_internal_note')->default(false);
        $table->unsignedInteger('sender_user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('customer_memory', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('customer_external_id', 40);
        $table->unsignedInteger('total_reclamacoes')->default(0);
        $table->timestamps();
        $table->unique(['business_id', 'customer_external_id']);
    });

    Schema::create('employee_performance', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedInteger('user_id')->nullable();
        $table->string('heuristic_name', 60)->nullable();
        $table->string('display_name', 120)->nullable();
        $table->unsignedInteger('n_msgs_total')->default(0);
        $table->unsignedInteger('n_conversations_atendidas')->default(0);
        $table->unsignedInteger('n_clientes_diferentes')->default(0);
        $table->unsignedInteger('tempo_resposta_mediana_s')->nullable();
        $table->unsignedInteger('tempo_resposta_p90_s')->nullable();
        $table->unsignedInteger('sla_breach_count')->default(0);
        $table->unsignedInteger('reclamacoes_recebidas')->default(0);
        $table->decimal('csat_avg', 3, 2)->nullable();
        $table->unsignedTinyInteger('horas_ativas_distintas')->default(0);
        $table->unsignedTinyInteger('hora_pico')->nullable();
        $table->unsignedTinyInteger('dias_ativos_30d')->default(0);
        $table->timestamp('primeira_atividade_at')->nullable();
        $table->timestamp('ultima_atividade_at')->nullable();
        $table->json('temas_dominantes')->nullable();
        $table->unsignedTinyInteger('nota_geral')->nullable();
        $table->json('nota_breakdown')->nullable();
        $table->timestamp('nota_calculada_em')->nullable();
        $table->json('flags')->nullable();
        $table->timestamp('last_rebuilt_at')->nullable();
        $table->string('rebuilt_via', 24)->nullable();
        $table->timestamps();
    });

    DB::table('business')->insert([
        ['id' => 1, 'name' => 'WR', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 99, 'name' => 'Outro', 'created_at' => now(), 'updated_at' => now()],
    ]);
});

function seedEmpUser(int $id, string $name): void
{
    DB::table('users')->insert([
        'id' => $id,
        'username' => strtolower($name),
        'first_name' => $name,
        'last_name' => '',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function seedEmpConv(int $bizId, string $ext): int
{
    return DB::table('conversations')->insertGetId([
        'business_id' => $bizId,
        'channel_id' => 10,
        'customer_external_id' => $ext,
        'created_at' => now()->subDays(1),
        'updated_at' => now(),
    ]);
}

function seedEmpMsg(int $bizId, int $convId, string $direction, ?int $userId = null, ?string $body = null, ?string $when = null): int
{
    return DB::table('messages')->insertGetId([
        'business_id' => $bizId,
        'conversation_id' => $convId,
        'direction' => $direction,
        'type' => 'text',
        'body' => $body ?? 'msg',
        'sender_user_id' => $userId,
        'is_internal_note' => false,
        'created_at' => $when ? \Carbon\Carbon::parse($when) : now(),
        'updated_at' => $when ? \Carbon\Carbon::parse($when) : now(),
    ]);
}

it('rebuild() com user_id real (PRIMÁRIO) cria scorecard', function () {
    seedEmpUser(10, 'Maiara');
    $conv = seedEmpConv(1, '5548999872822');
    seedEmpMsg(1, $conv, 'outbound', userId: 10);
    seedEmpMsg(1, $conv, 'outbound', userId: 10);
    seedEmpMsg(1, $conv, 'outbound', userId: 10);

    $rebuilder = new EmployeePerformanceRebuilder();
    $perf = $rebuilder->rebuild(1, userId: 10);

    expect($perf->business_id)->toBe(1)
        ->and($perf->user_id)->toBe(10)
        ->and($perf->heuristic_name)->toBeNull()
        ->and($perf->display_name)->toBe('Maiara')
        ->and($perf->n_msgs_total)->toBe(3)
        ->and($perf->n_conversations_atendidas)->toBe(1)
        ->and($perf->nota_geral)->toBeGreaterThan(0)
        ->and($perf->nota_breakdown)->toBeArray();
});

it('rebuild() com heuristic_name (FALLBACK) cria scorecard', function () {
    $conv = seedEmpConv(1, '5548999872822');
    seedEmpMsg(1, $conv, 'outbound', body: '*Luiz:* Bom dia');
    seedEmpMsg(1, $conv, 'outbound', body: '*Luiz:* posso ajudar?');

    $rebuilder = new EmployeePerformanceRebuilder();
    $perf = $rebuilder->rebuild(1, heuristicName: 'Luiz');

    expect($perf->user_id)->toBeNull()
        ->and($perf->heuristic_name)->toBe('Luiz')
        ->and($perf->display_name)->toBe('Luiz')
        ->and($perf->n_msgs_total)->toBe(2);
});

it('rebuild() é idempotente — re-run não duplica', function () {
    seedEmpUser(10, 'Maiara');
    $conv = seedEmpConv(1, '5548999872822');
    seedEmpMsg(1, $conv, 'outbound', userId: 10);

    $rebuilder = new EmployeePerformanceRebuilder();
    $first = $rebuilder->rebuild(1, userId: 10);
    $second = $rebuilder->rebuild(1, userId: 10);

    expect($first->id)->toBe($second->id);
    expect(DB::table('employee_performance')->count())->toBe(1);
});

it('Stats agregados batem count real (3 convs, 9 msgs, 3 clientes)', function () {
    seedEmpUser(10, 'Maiara');
    $c1 = seedEmpConv(1, '5548111111111');
    $c2 = seedEmpConv(1, '5548222222222');
    $c3 = seedEmpConv(1, '5548333333333');

    foreach ([$c1, $c2, $c3] as $c) {
        seedEmpMsg(1, $c, 'outbound', userId: 10);
        seedEmpMsg(1, $c, 'outbound', userId: 10);
        seedEmpMsg(1, $c, 'outbound', userId: 10);
    }

    $rebuilder = new EmployeePerformanceRebuilder();
    $perf = $rebuilder->rebuild(1, userId: 10);

    expect($perf->n_msgs_total)->toBe(9);
    expect($perf->n_conversations_atendidas)->toBe(3);
    expect($perf->n_clientes_diferentes)->toBe(3);
});

it('Scoring 0-100 com breakdown transparente (6 dimensões)', function () {
    seedEmpUser(10, 'Maiara');
    // Atendimento robusto: 100 msgs em 30 conversas
    for ($i = 0; $i < 30; $i++) {
        $conv = seedEmpConv(1, '55481' . str_pad((string) $i, 8, '0'));
        for ($j = 0; $j < 4; $j++) {
            seedEmpMsg(1, $conv, 'outbound', userId: 10);
        }
    }

    $rebuilder = new EmployeePerformanceRebuilder();
    $perf = $rebuilder->rebuild(1, userId: 10);

    expect($perf->nota_breakdown)->toBeArray()
        ->and(array_keys($perf->nota_breakdown))->toBe(['volume', 'diversidade', 'velocidade', 'profundidade', 'cobertura', 'engajamento']);

    // Soma das dimensões = nota_geral
    expect(array_sum($perf->nota_breakdown))->toBe((int) $perf->nota_geral);
});

it('faixa() classifica corretamente', function () {
    $perf = new EmployeePerformance();

    $perf->nota_geral = 95;
    expect($perf->faixa())->toBe('excelente');

    $perf->nota_geral = 75;
    expect($perf->faixa())->toBe('bom');

    $perf->nota_geral = 55;
    expect($perf->faixa())->toBe('regular');

    $perf->nota_geral = 30;
    expect($perf->faixa())->toBe('abaixo');
});

it('Tier 0: biz=99 NÃO vê biz=1', function () {
    seedEmpUser(10, 'Maiara');
    $cBiz1 = seedEmpConv(1, '5548999872822');
    seedEmpMsg(1, $cBiz1, 'outbound', userId: 10);
    seedEmpMsg(1, $cBiz1, 'outbound', userId: 10);

    $cBiz99 = seedEmpConv(99, '5548999872822');
    seedEmpMsg(99, $cBiz99, 'outbound', userId: 10);
    seedEmpMsg(99, $cBiz99, 'outbound', userId: 10);
    seedEmpMsg(99, $cBiz99, 'outbound', userId: 10);

    $rebuilder = new EmployeePerformanceRebuilder();
    $pBiz1 = $rebuilder->rebuild(1, userId: 10);
    $pBiz99 = $rebuilder->rebuild(99, userId: 10);

    expect($pBiz1->n_msgs_total)->toBe(2);
    expect($pBiz99->n_msgs_total)->toBe(3);
    expect($pBiz1->id)->not->toBe($pBiz99->id);
});

it('Velocidade mediana calculada entre inbound prev e outbound do atendente', function () {
    seedEmpUser(10, 'Maiara');
    $conv = seedEmpConv(1, '5548999872822');

    // Inbound 10:00, outbound 10:00:30 = 30s
    seedEmpMsg(1, $conv, 'inbound', when: '2026-05-15 10:00:00');
    seedEmpMsg(1, $conv, 'outbound', userId: 10, when: '2026-05-15 10:00:30');

    // Inbound 11:00, outbound 11:01 = 60s
    seedEmpMsg(1, $conv, 'inbound', when: '2026-05-15 11:00:00');
    seedEmpMsg(1, $conv, 'outbound', userId: 10, when: '2026-05-15 11:01:00');

    $rebuilder = new EmployeePerformanceRebuilder();
    $perf = $rebuilder->rebuild(1, userId: 10);

    expect($perf->tempo_resposta_mediana_s)->toBeGreaterThan(0)
        ->and($perf->tempo_resposta_mediana_s)->toBeLessThan(120);
});

it('SLA breach contado quando outbound >4h após inbound', function () {
    seedEmpUser(10, 'Maiara');
    $conv = seedEmpConv(1, '5548999872822');

    // Inbound 10:00, outbound 15:00 = 5h (breach >4h)
    seedEmpMsg(1, $conv, 'inbound', when: '2026-05-15 10:00:00');
    seedEmpMsg(1, $conv, 'outbound', userId: 10, when: '2026-05-15 15:00:00');

    // Inbound 16:00, outbound 16:30 = 30min (sem breach)
    seedEmpMsg(1, $conv, 'inbound', when: '2026-05-15 16:00:00');
    seedEmpMsg(1, $conv, 'outbound', userId: 10, when: '2026-05-15 16:30:00');

    $rebuilder = new EmployeePerformanceRebuilder();
    $perf = $rebuilder->rebuild(1, userId: 10);

    expect($perf->sla_breach_count)->toBe(1);
});

it('rebuild() exige user_id OU heuristic_name', function () {
    $rebuilder = new EmployeePerformanceRebuilder();

    expect(fn () => $rebuilder->rebuild(1))
        ->toThrow(\InvalidArgumentException::class, 'user_id OU heuristic_name');
});
