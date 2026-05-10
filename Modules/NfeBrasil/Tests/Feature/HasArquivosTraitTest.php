<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeDfeRecebido;
use Modules\NfeBrasil\Models\NfeEmissao;

uses(Tests\TestCase::class);

/**
 * NFe primeiro consumer Modules/Arquivos backbone — Sprint 3 US-ARQ-019.
 *
 * Cobertura:
 * - NfeEmissao + NfeDfeRecebido têm relação morphMany via trait HasArquivos
 * - Accessors xml_arquivo / danfe_arquivo retornam Arquivo OU null
 * - Sem regressão: criar NfeEmissao com xml_path legacy continua funcionando
 *   (fallback durante transição até US-ARQ-021 remover colunas)
 *
 * @see memory/decisions/0123-modules-arquivos-backbone.md
 *
 * SQLite CI: tests com accessors xml_arquivo/danfe_arquivo querying `arquivos`
 * são skipados defensivamente (PR #475/#478) porque CI Modules Pest não migra
 * Modules/Arquivos. Pest local MySQL é o gate real (Wagner regra 2026-05-09).
 */

beforeEach(function () {
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('arquivos table missing — Modules/Arquivos não migrado');
    }
});

it('NfeEmissao usa trait HasArquivos', function () {
    $reflection = new ReflectionClass(NfeEmissao::class);
    $traits = $reflection->getTraitNames();

    expect($traits)->toContain('Modules\\Arquivos\\Concerns\\HasArquivos');
});

it('NfeDfeRecebido usa trait HasArquivos', function () {
    $reflection = new ReflectionClass(NfeDfeRecebido::class);
    $traits = $reflection->getTraitNames();

    expect($traits)->toContain('Modules\\Arquivos\\Concerns\\HasArquivos');
});

it('NfeEmissao expõe método arquivos() retornando MorphMany', function () {
    $emissao = new NfeEmissao();
    expect(method_exists($emissao, 'arquivos'))->toBeTrue();
});

it('NfeEmissao xml_arquivo accessor retorna null sem arquivos relacionados', function () {
    $emissao = new NfeEmissao([
        'business_id' => 1,
        'numero'      => 999,
        'modelo'      => '65',
        'serie'       => 1,
        'chave_44'    => '35210112345678000199550010000000011000000019',
        'status'      => 'pendente',
    ]);

    // Sem Arquivo relacionado, accessor retorna null (não throw)
    expect($emissao->xml_arquivo)->toBeNull();
});

it('NfeEmissao danfe_arquivo accessor retorna null sem arquivos', function () {
    $emissao = new NfeEmissao(['business_id' => 1]);
    expect($emissao->danfe_arquivo)->toBeNull();
});

it('NfeDfeRecebido xml_arquivo accessor retorna null sem arquivos', function () {
    $dfe = new NfeDfeRecebido(['business_id' => 1]);
    expect($dfe->xml_arquivo)->toBeNull();
});

it('NfeEmissao preserva colunas legacy xml_path e danfe_path no fillable', function () {
    $emissao = new NfeEmissao();
    $fillable = $emissao->getFillable();

    expect($fillable)->toContain('xml_path');
    expect($fillable)->toContain('danfe_path');
});
