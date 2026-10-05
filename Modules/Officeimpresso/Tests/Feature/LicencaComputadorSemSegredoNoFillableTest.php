<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Officeimpresso\Entities\Licenca_Computador;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Officeimpresso thread 03 · `senha`/`contra_senha` saem do `fillable`.
 *
 * A thread 02 (#8365) parou de gravar os dois segredos que o desktop Delphi manda.
 * A decisão [W] D4 (2026-10-01, 2ª rodada, `_DECISOES-W-2026-10-01b.md`) é NÃO dropar
 * as colunas — então o que sobra para esta thread é tirá-las do `fillable`:
 *  - nenhum `fill()`/`create()` consegue mais gravar senha por mass-assignment;
 *  - o `LogsActivity` (logFillable) deixa de poder levar a senha ao `activity_log`.
 *
 * Tenant fictício 98 (seed do CI, ADR 0358). Nunca biz=4. Sem RefreshDatabase.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS exige MySQL (ADR 0358).');
    }
});

it('senha e contra_senha não são mass-assignable', function () {
    $m = new Licenca_Computador();

    expect($m->isFillable('senha'))->toBeFalse();
    expect($m->isFillable('contra_senha'))->toBeFalse();
    // âncora positiva: o fillable segue valendo para os campos comuns
    expect($m->isFillable('hostname'))->toBeTrue();
});

it('fill() ignora os segredos e mantém os demais campos', function () {
    $m = new Licenca_Computador([
        'business_id' => 98,
        'hd' => 'T03-FILL',
        'hostname' => 'MAQ-T03',
        'senha' => 'SEGREDO-A',
        'contra_senha' => 'SEGREDO-B',
    ]);

    expect($m->getAttribute('senha'))->toBeNull();
    expect($m->getAttribute('contra_senha'))->toBeNull();
    expect($m->getAttribute('hostname'))->toBe('MAQ-T03');
});

it('activity_log não registra senha nem contra_senha, e registra o campo comum', function () {
    $hd = 'T03-LOG-' . uniqid();

    $m = new Licenca_Computador();
    $m->business_id = 98;
    $m->hd = $hd;
    $m->hostname = 'ANTES';
    $m->save();

    // força os segredos como dirty (setAttribute ignora o fillable) junto com um campo comum
    $m->setAttribute('senha', 'SEGREDO-A');
    $m->setAttribute('contra_senha', 'SEGREDO-B');
    $m->hostname = 'DEPOIS';
    $m->save();

    $ultima = Activity::query()
        ->where('subject_type', Licenca_Computador::class)
        ->where('subject_id', $m->id)
        ->latest('id')
        ->first();

    // sem vácuo: o log da alteração existe e carrega o campo comum
    expect($ultima)->not->toBeNull();
    $attrs = $ultima->properties['attributes'] ?? [];
    expect($attrs)->toHaveKey('hostname');
    expect($attrs['hostname'])->toBe('DEPOIS');

    expect(array_key_exists('senha', $attrs))->toBeFalse();
    expect(array_key_exists('contra_senha', $attrs))->toBeFalse();
    expect(json_encode($ultima->properties))->not->toContain('SEGREDO-A');
    expect(json_encode($ultima->properties))->not->toContain('SEGREDO-B');
});
