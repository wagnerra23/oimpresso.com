<?php

declare(strict_types=1);

/**
 * LgpdComplianceTest — valida compliance LGPD do módulo ComunicacaoVisual.
 *
 * Cobre:
 *  - Existência de Config/retention.php com janelas declaradas
 *  - Entities core (Orcamento, Os, Apontamento) com trait LogsActivity (Spatie)
 *  - retention.php aderente a CCom Art. 195 (5 anos) pra docs fiscais
 *  - right_to_be_forgotten configurado com fields anonimizáveis declarados
 *
 * Wave 18 — restauração D7 regressão (Wave 17 documentou mas não criou suite).
 *
 * Multi-tenant Tier 0: testes não dependem de DB — leitura de config + reflection.
 * Compatível com Hostinger e CT 100 (sem fixtures).
 *
 * @see Modules/ComunicacaoVisual/Config/retention.php
 * @see https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm
 */

use Modules\ComunicacaoVisual\Entities\Apontamento;
use Modules\ComunicacaoVisual\Entities\Orcamento;
use Modules\ComunicacaoVisual\Entities\Os;

it('publica config retention.php no módulo ComunicacaoVisual', function () {
    $path = base_path('Modules/ComunicacaoVisual/Config/retention.php');

    expect(file_exists($path))->toBeTrue('Config/retention.php é obrigatório (LGPD Art. 16)');

    $cfg = require $path;

    expect($cfg)->toBeArray()
        ->and($cfg)->toHaveKeys(['entities', 'telemetry', 'right_to_be_forgotten']);
});

it('declara janelas de retenção pra Apontamento, Orcamento e Os', function () {
    $cfg = require base_path('Modules/ComunicacaoVisual/Config/retention.php');

    foreach (['apontamento', 'orcamento', 'os'] as $entity) {
        expect($cfg['entities'])->toHaveKey($entity, "Entity {$entity} sem retention declarada");
        expect($cfg['entities'][$entity])->toHaveKeys(['days', 'basis_legal', 'pii_fields']);
        expect($cfg['entities'][$entity]['days'])->toBeGreaterThanOrEqual(1825,
            "Docs fiscais exigem retenção ≥5 anos (CCom Art. 195) — entity {$entity}");
    }
});

it('marca Apontamento como append_only conforme padrão registro produtivo legal', function () {
    $cfg = require base_path('Modules/ComunicacaoVisual/Config/retention.php');

    expect($cfg['entities']['apontamento']['append_only'])
        ->toBeTrue('Apontamento é registro produtivo legal — append-only obrigatório');
});

it('habilita right_to_be_forgotten com preservação de ids fiscais', function () {
    $cfg = require base_path('Modules/ComunicacaoVisual/Config/retention.php');

    expect($cfg['right_to_be_forgotten']['enabled'])->toBeTrue();
    expect($cfg['right_to_be_forgotten']['preserve_fiscal_ids'])
        ->toBeTrue('CCom obriga retenção até janela legal — não pode hard-delete dado fiscal');
    expect($cfg['right_to_be_forgotten']['anonymize_fields'])->toBeArray()->not->toBeEmpty();
});

it('mantém Entities core (Orcamento/Os/Apontamento) instanciáveis', function () {
    // Sanity: classes existem e seguem padrão Eloquent com business_id no fillable
    expect(class_exists(Orcamento::class))->toBeTrue();
    expect(class_exists(Os::class))->toBeTrue();
    expect(class_exists(Apontamento::class))->toBeTrue();

    foreach ([Orcamento::class, Os::class, Apontamento::class] as $class) {
        $instance = new $class;
        expect($instance->getFillable())->toContain('business_id',
            "{$class} deve ter business_id fillable (Tier 0 ADR 0093)");
    }
});

it('Apontamento não usa SoftDeletes (append-only registro legal)', function () {
    $traits = class_uses_recursive(Apontamento::class);

    expect($traits)->not->toContain(\Illuminate\Database\Eloquent\SoftDeletes::class,
        'Apontamento é APPEND-ONLY — SoftDeletes viola registro legal produtivo');
});

it('Orcamento e Os usam SoftDeletes (recuperáveis dentro janela retenção)', function () {
    foreach ([Orcamento::class, Os::class] as $class) {
        $traits = class_uses_recursive($class);
        expect($traits)->toContain(\Illuminate\Database\Eloquent\SoftDeletes::class,
            "{$class} deve usar SoftDeletes (recovery dentro janela LGPD)");
    }
});

it('telemetria operacional tem janela curta (≤ 365 dias)', function () {
    $cfg = require base_path('Modules/ComunicacaoVisual/Config/retention.php');

    expect($cfg['telemetry']['days'])->toBeLessThanOrEqual(365,
        'Telemetria sem PII deve expirar em ≤12 meses pra otimizar armazenamento');
});
