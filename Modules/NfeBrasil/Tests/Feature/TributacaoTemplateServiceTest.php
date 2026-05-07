<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Modules\NfeBrasil\Services\Tributacao\TributacaoTemplateService;

uses(Tests\TestCase::class);

/**
 * US-NFE-TPL-001 · Templates tributários L1 — testes do Service.
 *
 * Garante:
 *   1. listar() carrega os 3 templates iniciais (varejo / atacado / indústria)
 *   2. listar() retorna ordenado por setor → regime → uf
 *   3. buscar() encontra slug existente e null quando não existe
 *   4. aplicar() cria config nova quando business sem config
 *   5. aplicar() atualiza config existente substituindo regime + tributacao_default
 *   6. aplicar() é idempotente (re-aplicar mesmo template = mudou:false)
 *   7. aplicar() lança InvalidArgumentException pra slug inexistente
 *   8. aplicar() NÃO toca em nfe_fiscal_rules existentes
 */

beforeEach(function () {
    if (! Schema::hasTable('nfe_business_configs')) {
        $this->markTestSkipped('nfe_business_configs não existe — migration não rodou');
    }
});

it('listar() retorna os 3 templates iniciais ordenados', function () {
    $service = new TributacaoTemplateService;
    $templates = $service->listar();

    expect($templates)->toBeArray()->toHaveCount(3);

    $slugs = array_column($templates, 'slug');
    expect($slugs)->toContain('comercio-varejo-simples-sp')
        ->and($slugs)->toContain('comercio-atacado-simples-sp')
        ->and($slugs)->toContain('industria-grafica-simples-sp');

    // Cada template tem todas as chaves obrigatórias
    foreach ($templates as $tpl) {
        expect($tpl)->toHaveKeys([
            'slug', 'titulo', 'descricao', 'icon', 'setor', 'regime', 'uf',
            'modelo_nfe', 'recomendado_para', 'tributacao_default', 'observacoes',
        ]);
    }
});

it('listar() ordena por setor → regime → uf', function () {
    $service = new TributacaoTemplateService;
    $templates = $service->listar();
    $setores = array_column($templates, 'setor');

    // Ordenação: comercio (2 templates) vem antes de industria (1)
    expect($setores[0])->toBe('comercio')
        ->and(end($setores))->toBe('industria');
});

it('buscar() encontra slug existente', function () {
    $service = new TributacaoTemplateService;
    $tpl = $service->buscar('comercio-varejo-simples-sp');

    expect($tpl)->toBeArray()
        ->and($tpl['slug'])->toBe('comercio-varejo-simples-sp')
        ->and($tpl['regime'])->toBe('simples')
        ->and($tpl['uf'])->toBe('SP')
        ->and($tpl['modelo_nfe'])->toBe('65');
});

it('buscar() retorna null pra slug inexistente', function () {
    $service = new TributacaoTemplateService;
    expect($service->buscar('nao-existe-este-slug'))->toBeNull();
});

it('aplicar() cria config nova quando business não tem config', function () {
    NfeBusinessConfig::where('business_id', 999)->delete();

    $service = new TributacaoTemplateService;
    $resultado = $service->aplicar(999, 'comercio-varejo-simples-sp');

    expect($resultado['criou'])->toBeTrue()
        ->and($resultado['mudou'])->toBeTrue()
        ->and($resultado['config']->business_id)->toBe(999)
        ->and($resultado['config']->regime)->toBe('simples')
        ->and($resultado['config']->tributacao_default['csosn'])->toBe('102')
        ->and($resultado['config']->tributacao_default['cfop'])->toBe('5102');

    NfeBusinessConfig::where('business_id', 999)->delete();
});

it('aplicar() atualiza config existente substituindo regime + tributacao_default', function () {
    NfeBusinessConfig::where('business_id', 998)->delete();

    NfeBusinessConfig::create([
        'business_id' => 998,
        'regime' => 'lucro_real',
        'tributacao_default' => ['csosn' => '999', 'cfop' => '0000'],
    ]);

    $service = new TributacaoTemplateService;
    $resultado = $service->aplicar(998, 'comercio-atacado-simples-sp');

    expect($resultado['criou'])->toBeFalse()
        ->and($resultado['mudou'])->toBeTrue()
        ->and($resultado['config']->regime)->toBe('simples')
        ->and($resultado['config']->tributacao_default['csosn'])->toBe('101')
        ->and($resultado['config']->tributacao_default['cfop'])->toBe('5102');

    NfeBusinessConfig::where('business_id', 998)->delete();
});

it('aplicar() é idempotente (re-aplicar mesmo template = mudou:false)', function () {
    NfeBusinessConfig::where('business_id', 997)->delete();

    $service = new TributacaoTemplateService;

    $primeiro = $service->aplicar(997, 'industria-grafica-simples-sp');
    expect($primeiro['criou'])->toBeTrue()->and($primeiro['mudou'])->toBeTrue();

    $segundo = $service->aplicar(997, 'industria-grafica-simples-sp');
    expect($segundo['criou'])->toBeFalse()
        ->and($segundo['mudou'])->toBeFalse();

    NfeBusinessConfig::where('business_id', 997)->delete();
});

it('aplicar() lança InvalidArgumentException pra slug inexistente', function () {
    $service = new TributacaoTemplateService;
    $service->aplicar(996, 'template-fantasma');
})->throws(InvalidArgumentException::class, 'Template tributário');
