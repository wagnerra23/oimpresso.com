<?php

declare(strict_types=1);

use Modules\Governance\Services\ScopedScorecardEvaluator;
use Tests\TestCase;

/**
 * Pest test pra ScopedScorecardEvaluator::resolveModulePath() — Wave 27 (2026-05-17).
 *
 * Gap detectado pós Wave 25 ativar v4 default true: `module:grade-v4 Vestuario --detail`
 * retornava 20/100 (vs v3 80/100). Causa: scorecards YAML usavam path literal
 * `Modules/Vestuario/...` em vez de placeholder canônico `<modulo>`.
 *
 * Fix W27:
 *   1) Scorecards (vestuario.yaml, governance.yaml, jana.yaml, functional_horizontal.yaml)
 *      passaram pra placeholder `<modulo>`.
 *   2) `resolveModulePath()` suporta DOIS patterns: `<modulo>` canonical W27+ E legacy
 *      literal `Modules/Vestuario` (back-compat W19-W26).
 *
 * Testes cobrem:
 *   - placeholder `<modulo>` substituído pelo módulo avaliado
 *   - placeholder uppercase `<MODULO>` também aceito
 *   - legacy literal substituído (Modules/Vestuario → Modules/Jana quando module=Jana)
 *   - paths sem placeholder/literal passam-through inalterados
 *   - memory/requisitos/<Nome>/ também substituído
 *
 * @see Modules\Governance\Services\ScopedScorecardEvaluator::resolveModulePath
 */
uses(TestCase::class);

beforeEach(function () {
    $this->eval = new ScopedScorecardEvaluator();
});

it('substitui placeholder <modulo> pelo nome do módulo avaliado', function () {
    $resolved = $this->eval->resolveModulePath(
        'Vestuario',
        'Modules/<modulo>/Entities/**/*.php'
    );
    expect($resolved)->toBe('Modules/Vestuario/Entities/**/*.php');
});

it('substitui placeholder <modulo> pra módulo diferente (ComunicacaoVisual)', function () {
    $resolved = $this->eval->resolveModulePath(
        'ComunicacaoVisual',
        'Modules/<modulo>/Tests/Feature/**/*Test.php'
    );
    expect($resolved)->toBe('Modules/ComunicacaoVisual/Tests/Feature/**/*Test.php');
});

it('substitui placeholder <MODULO> uppercase também (back-compat functional_horizontal)', function () {
    $resolved = $this->eval->resolveModulePath(
        'Crm',
        'Modules/<MODULO>/Services/**/*.php'
    );
    expect($resolved)->toBe('Modules/Crm/Services/**/*.php');
});

it('substitui placeholder em memory/requisitos/<modulo>/', function () {
    $resolved = $this->eval->resolveModulePath(
        'Jana',
        'memory/requisitos/<modulo>/SPEC.md'
    );
    expect($resolved)->toBe('memory/requisitos/Jana/SPEC.md');
});

it('substitui legacy literal Modules/Vestuario pelo módulo avaliado (back-compat W19-W26)', function () {
    // YAML antigo ainda com path literal — resolveModulePath deve normalizar
    $resolved = $this->eval->resolveModulePath(
        'Jana',
        'Modules/Vestuario/Entities/**/*.php'
    );
    expect($resolved)->toBe('Modules/Jana/Entities/**/*.php');
});

it('substitui legacy literal memory/requisitos/Vestuario/ pelo módulo avaliado', function () {
    $resolved = $this->eval->resolveModulePath(
        'Governance',
        'memory/requisitos/Vestuario/SPEC.md'
    );
    expect($resolved)->toBe('memory/requisitos/Governance/SPEC.md');
});

it('substitui múltiplas ocorrências num path glob complexo (ratio numerator/denominator)', function () {
    $resolved = $this->eval->resolveModulePath(
        'NfeBrasil',
        'Modules/Vestuario/Http/Requests/*.php'
    );
    expect($resolved)->toBe('Modules/NfeBrasil/Http/Requests/*.php');
});

it('NÃO altera path quando não tem placeholder nem literal de módulo conhecido (passthrough)', function () {
    $path = 'app/Http/Middleware/VerifyCsrfToken.php';
    expect($this->eval->resolveModulePath('Jana', $path))->toBe($path);
});

it('NÃO altera path com módulo desconhecido fora da whitelist (defensivo)', function () {
    // Modules/ModuloFantasiaXyz não está na whitelist conhecida — não substitui
    $path = 'Modules/ModuloFantasiaXyz/Foo/**/*.php';
    expect($this->eval->resolveModulePath('Jana', $path))->toBe($path);
});

it('placeholder <module> singular (alternativo) também é aceito', function () {
    // Pra retrocompat com `yaml_lookup` que usava `<module>` em key
    $resolved = $this->eval->resolveModulePath(
        'Vestuario',
        'Modules/<module>/Config/retention.php'
    );
    expect($resolved)->toBe('Modules/Vestuario/Config/retention.php');
});

it('detectFileExists usa resolveModulePath corretamente com placeholder <modulo>', function () {
    // CHANGELOG.md de Vestuario existe (sanity check existência do file path)
    $changelogPath = base_path('Modules/Vestuario/CHANGELOG.md');
    if (! file_exists($changelogPath)) {
        $this->markTestSkipped('Modules/Vestuario/CHANGELOG.md não existe no fixture local');
    }

    $detected = $this->eval->detectFileExists('Vestuario', [
        'path' => 'Modules/<modulo>/CHANGELOG.md',
    ]);
    expect($detected)->toBeTrue();
});

it('detectFileExists com placeholder retorna false pra módulo sem o arquivo', function () {
    // Módulo bobo sem CHANGELOG — file_exists deve falhar
    $detected = $this->eval->detectFileExists('ModuloFantasiaQueNaoExisteXyz', [
        'path' => 'Modules/<modulo>/CHANGELOG.md',
    ]);
    expect($detected)->toBeFalse();
});
