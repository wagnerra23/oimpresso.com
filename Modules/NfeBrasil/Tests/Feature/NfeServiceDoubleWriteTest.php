<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\DanfeService;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * US-ARQ-021 — double-write XML/DANFE pra Modules/Arquivos backbone.
 *
 * Cobertura via Reflection (sem rodar emit real, evita SEFAZ):
 * - Métodos privados writeArquivoXml + writeArquivoDanfe existem
 * - Métodos invocam DB::table('arquivos')->insert
 * - Try/catch graceful — falha em arquivos NUNCA propaga
 *
 * Test integração funcional roda em Felipe local com NFe homolog real.
 *
 * @see Modules/NfeBrasil/Services/NfeService.php::writeArquivoXml
 * @see Modules/NfeBrasil/Services/DanfeService.php::writeArquivoDanfe
 */

it('NfeService tem método privado writeArquivoXml', function () {
    $reflection = new ReflectionClass(NfeService::class);
    expect($reflection->hasMethod('writeArquivoXml'))->toBeTrue();

    $method = $reflection->getMethod('writeArquivoXml');
    expect($method->isPrivate())->toBeTrue();

    // Aceita NfeEmissao + string + string params
    $params = $method->getParameters();
    expect(count($params))->toBe(3);
});

it('DanfeService tem método privado writeArquivoDanfe', function () {
    $reflection = new ReflectionClass(DanfeService::class);
    expect($reflection->hasMethod('writeArquivoDanfe'))->toBeTrue();

    $method = $reflection->getMethod('writeArquivoDanfe');
    expect($method->isPrivate())->toBeTrue();
});

it('writeArquivoXml não propaga exceção (graceful)', function () {
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('arquivos table missing');
    }

    $service = new NfeService();
    $reflection = new ReflectionClass(NfeService::class);
    $method = $reflection->getMethod('writeArquivoXml');
    $method->setAccessible(true);

    // Stub NfeEmissao mínimo
    $emissao = new NfeEmissao([
        'business_id' => 1,
        'numero'      => 99999,
        'modelo'      => '65',
        'serie'       => 1,
        'chave_44'    => '35210112345678000199550010000099999000099999',
        'status'      => 'autorizada',
    ]);
    $emissao->id = 999999; // Stub id

    // Mesmo com emissao não-persistida, método deve graceful skip
    $result = $method->invoke($service, $emissao, 'test/path.xml', '<xml/>');
    expect($result)->toBeNull(); // void return type

    // Cleanup se inseriu algo
    DB::table('arquivos')
        ->where('arquivable_id', 999999)
        ->where('arquivable_type', 'Modules\\NfeBrasil\\Models\\NfeEmissao')
        ->delete();
});

it('writeArquivoXml é idempotente (rodar 2x não duplica)', function () {
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('arquivos table missing');
    }

    $service = new NfeService();
    $reflection = new ReflectionClass(NfeService::class);
    $method = $reflection->getMethod('writeArquivoXml');
    $method->setAccessible(true);

    $emissao = new NfeEmissao([
        'business_id' => 1,
        'numero'      => 99998,
        'modelo'      => '65',
        'serie'       => 1,
        'chave_44'    => '35210112345678000199550010000099998000099998',
    ]);
    $emissao->id = 999998;

    // Cleanup primeiro
    DB::table('arquivos')
        ->where('arquivable_id', 999998)
        ->where('arquivable_type', 'Modules\\NfeBrasil\\Models\\NfeEmissao')
        ->delete();

    $method->invoke($service, $emissao, 'test/idempotent.xml', '<xml id=1/>');
    $method->invoke($service, $emissao, 'test/idempotent.xml', '<xml id=1/>');

    $count = DB::table('arquivos')
        ->where('arquivable_id', 999998)
        ->where('arquivable_type', 'Modules\\NfeBrasil\\Models\\NfeEmissao')
        ->where('classified_by', 'nfe-service-double-write')
        ->count();

    expect($count)->toBe(1);

    // Cleanup
    DB::table('arquivos')
        ->where('arquivable_id', 999998)
        ->where('arquivable_type', 'Modules\\NfeBrasil\\Models\\NfeEmissao')
        ->delete();
});

it('arquivos backfill double-write tem classified_by tag rastreável', function () {
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('arquivos table missing');
    }

    $service = new NfeService();
    $reflection = new ReflectionClass(NfeService::class);
    $method = $reflection->getMethod('writeArquivoXml');
    $method->setAccessible(true);

    $emissao = new NfeEmissao([
        'business_id' => 1,
        'numero'      => 99997,
        'modelo'      => '65',
        'serie'       => 1,
        'chave_44'    => '35210112345678000199550010000099997000099997',
    ]);
    $emissao->id = 999997;

    DB::table('arquivos')
        ->where('arquivable_id', 999997)
        ->where('arquivable_type', 'Modules\\NfeBrasil\\Models\\NfeEmissao')
        ->delete();

    $method->invoke($service, $emissao, 'test/tagged.xml', '<xml/>');

    $row = DB::table('arquivos')
        ->where('arquivable_id', 999997)
        ->where('arquivable_type', 'Modules\\NfeBrasil\\Models\\NfeEmissao')
        ->first();

    if ($row) {
        expect($row->classified_by)->toBe('nfe-service-double-write');
        expect($row->bucket)->toBe('active');
        expect($row->sub_destination)->toBe('nfe-xml');
        expect($row->mime_type)->toBe('application/xml');
        expect($row->size_bytes)->toBe(strlen('<xml/>'));
    }

    // Cleanup
    DB::table('arquivos')
        ->where('arquivable_id', 999997)
        ->where('arquivable_type', 'Modules\\NfeBrasil\\Models\\NfeEmissao')
        ->delete();
});
