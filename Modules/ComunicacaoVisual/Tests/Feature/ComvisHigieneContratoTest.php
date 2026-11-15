<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Entities\Acabamento;
use Modules\ComunicacaoVisual\Entities\Orcamento;
use Tests\Support\WithSeededTenant;

/**
 * Higiene do módulo (UC-CVHIG-01..03): criar sem empresa falha, a demo não roda em produção e o
 * health-check está agendado.
 *
 * Âncora: ADR 0093 (Tier 0: nunca linha com business_id 0) + auditoria 2026-11-05 I-11, I-12, I-14.
 *
 * Tenant 98 (ADR 0358). ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('comvis_orcamentos')) {
        $this->markTestSkipped('Requer MySQL com o schema do módulo.');
    }
    try {
        $this->biz = $this->seededTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Tenant de teste ausente: '.$e->getMessage());
    }
});

afterEach(function () {
    app()->detectEnvironment(fn () => 'testing');
});

it('UC-CVHIG-01 · sem sessão e sem business_id, criar falha em vez de gravar empresa 0', function () {
    session()->forget(['user', 'business']);
    $antes = DB::table('comvis_orcamentos')->where('business_id', 0)->count();

    // Família da trait (Orcamento) e família da closure em booted() (Acabamento).
    expect(fn () => Orcamento::create(['numero' => 'HIG-'.uniqid(), 'data_emissao' => now()->toDateString(), 'status' => 'rascunho']))
        ->toThrow(\LogicException::class, 'sem business_id');
    expect(fn () => Acabamento::create(['nome' => 'Ilhós'.uniqid()]))
        ->toThrow(\LogicException::class, 'sem business_id');

    expect(DB::table('comvis_orcamentos')->where('business_id', 0)->count())->toBe($antes);

    // Par positivo: com o business_id explícito, sem sessão, grava na empresa certa.
    $orc = Orcamento::create(['business_id' => $this->biz->id, 'numero' => 'HIG-'.uniqid(),
        'data_emissao' => now()->toDateString(), 'status' => 'rascunho']);
    expect((int) $orc->business_id)->toBe((int) $this->biz->id);
});

it('UC-CVHIG-02 · a demo não roda em produção e não grava nada', function () {
    $antes = DB::table('comvis_orcamentos')->where('business_id', $this->biz->id)->count();

    app()->detectEnvironment(fn () => 'live');
    $codigo = Artisan::call('comvis:demo-seed', ['--business' => $this->biz->id]);

    expect($codigo)->toBe(1)
        ->and(Artisan::output())->toContain('não roda em produção')
        ->and(DB::table('comvis_orcamentos')->where('business_id', $this->biz->id)->count())->toBe($antes);
});

it('UC-CVHIG-03 · comvis:health roda todo dia 06:40, só em produção, sem sobrepor', function () {
    $eventos = collect(app(Schedule::class)->events())
        ->filter(fn ($e) => str_contains((string) $e->command, 'comvis:health'));

    expect($eventos)->toHaveCount(1);
    $evento = $eventos->first();

    expect($evento->expression)->toBe('40 6 * * *')
        ->and($evento->command)->toContain('--notify')
        ->and($evento->withoutOverlapping)->toBeTrue()
        ->and($evento->runsInEnvironment('live'))->toBeTrue()
        ->and($evento->runsInEnvironment('testing'))->toBeFalse();
});
