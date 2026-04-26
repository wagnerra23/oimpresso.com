<?php

declare(strict_types=1);

use App\Services\Evolution\Tools\ExtractorTool;

beforeEach(function () {
    config([
        'prism.providers.deepseek.api_key' => '',
        'prism.providers.anthropic.api_key' => '',
    ]);
});

it('Extractor sem chave retorna modo offline (passthrough)', function () {
    $tool = new ExtractorTool;

    $result = $tool([
        'text' => 'O modulo Financeiro tem 3 ondas: contas a pagar, baixa, conciliação.',
        'instruction' => 'Resuma em 1 linha.',
    ]);

    expect($result)->toBeArray()
        ->and($result['mode'])->toBe('offline')
        ->and($result['provider'])->toBe('deepseek')
        ->and($result)->toHaveKey('text_preview');
});

it('Extractor falha sem text obrigatório', function () {
    $tool = new ExtractorTool;
    $result = $tool(['instruction' => 'X']);
    expect($result)->toHaveKey('error');
});

it('Extractor respeita override de model via args', function () {
    $tool = new ExtractorTool;

    $result = $tool([
        'text' => 'foo',
        'model' => 'haiku',
    ]);

    expect($result['provider'])->toBe('anthropic')
        ->and($result['model'])->toBe('claude-haiku-4-5');
});
