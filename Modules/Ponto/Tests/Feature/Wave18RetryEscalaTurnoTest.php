<?php

declare(strict_types=1);

use App\Concerns\BelongsToBusinessViaParent;
use Modules\Ponto\Entities\EscalaTurno;

uses(Tests\TestCase::class);

/**
 * Wave 18 RETRY D1 — EscalaTurno multi-tenant via parent (ADR 0093).
 *
 * Tabela `ponto_escala_turnos` NÃO tem business_id direto — child de Escala
 * (que tem business_id). Trait `BelongsToBusinessViaParent` injeta whereHas
 * automático no scope global. Padrão idêntico Modules/Accounting/Entities/Transfer.
 *
 * SQLite-friendly — só smoke estrutural (trait aplicado + propriedade configurada).
 * Cross-tenant real cobre em MultiTenantIsolationTest (Wave 11+12).
 *
 * @see Modules/Ponto/Entities/EscalaTurno.php
 * @see app/Concerns/BelongsToBusinessViaParent.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

it('EscalaTurno usa trait BelongsToBusinessViaParent (Tier 0 child via Escala)', function () {
    $traits = class_uses_recursive(EscalaTurno::class);

    expect($traits)->toContain(
        BelongsToBusinessViaParent::class,
        'EscalaTurno DEVE usar BelongsToBusinessViaParent (ADR 0093 — child de Escala que tem business_id).'
    );
});

it('EscalaTurno declara businessParentRelation=escala (parent contrato)', function () {
    $reflection = new ReflectionClass(EscalaTurno::class);
    $instance = $reflection->newInstanceWithoutConstructor();
    $prop = $reflection->getProperty('businessParentRelation');
    $prop->setAccessible(true);
    $value = $prop->getValue($instance);

    expect($value)->toBe(
        'escala',
        'EscalaTurno->businessParentRelation deve apontar pra relação escala (Escala tem business_id).'
    );
});

it('EscalaTurno preserva relação BelongsTo escala (não quebra contrato)', function () {
    $turno = new EscalaTurno;
    $relation = $turno->escala();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    expect($relation->getForeignKeyName())->toBe('escala_id');
});

it('EscalaTurno mantém table ponto_escala_turnos (sanity)', function () {
    expect((new EscalaTurno)->getTable())->toBe('ponto_escala_turnos');
});

it('EscalaTurno fillable inclui escala_id + 5 horários (contrato Wave 18 RETRY)', function () {
    $expected = ['escala_id', 'dia_semana', 'hora_entrada', 'hora_almoco_inicio', 'hora_almoco_fim', 'hora_saida'];
    expect((new EscalaTurno)->getFillable())->toEqualCanonicalizing($expected);
});
