<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\DanfeService;
use NFePHP\DA\NFe\Danfe;

uses(Tests\TestCase::class);

/**
 * US-ARQ-022 — DanfeService prefere xml_arquivo (arquivos backbone) sobre xml_path legacy.
 *
 * Sprint 1 dia 4 ADR 0123 §2 (consumers prefer accessor).
 *
 * Cobertura:
 * - Quando arquivos table tem row pra emissao, DanfeService lê dela (não xml_path)
 * - Backward compat: sem arquivos backbone, fallback xml_path funciona
 * - File físico ausente em arquivos → cai pro fallback xml_path graciosamente
 *
 * @see Modules/NfeBrasil/Services/DanfeService.php obterXmlContents
 */

beforeEach(function () {
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('arquivos table missing — Modules/Arquivos não migrado');
    }

    // Pattern dual-mode (PR #486 reference):
    //   - SQLite: drop+create isolado em :memory:
    //   - MySQL (Pest local — gate Wagner): preserva schema real;
    //     limpa rows biz=1/99 com FK_CHECKS=0 (cascateia em nfe_eventos.emissao_id)
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('nfe_emissoes');
        Schema::create('nfe_emissoes', function ($t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->unsignedInteger('transaction_id')->nullable();
            $t->string('modelo', 2)->default('55');
            $t->string('serie', 3)->default('1');
            $t->unsignedInteger('numero')->nullable();
            $t->string('chave_44', 44)->nullable();
            $t->string('status', 20)->default('pendente');
            $t->string('cstat', 5)->nullable();
            $t->text('motivo')->nullable();
            $t->string('xml_path', 255)->nullable();
            $t->string('danfe_path', 255)->nullable();
            $t->decimal('valor_total', 15, 2)->default(0);
            $t->timestamp('emitido_em')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
    } elseif (Schema::hasTable('nfe_emissoes')) {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        if (Schema::hasTable('nfe_eventos')) {
            DB::table('nfe_eventos')->whereIn('business_id', [1, 99])->delete();
        }
        DB::table('nfe_emissoes')->whereIn('business_id', [1, 99])->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    Storage::fake('local');
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('nfe_emissoes');
        return;
    }

    if (Schema::hasTable('nfe_emissoes')) {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        if (Schema::hasTable('nfe_eventos')) {
            DB::table('nfe_eventos')->whereIn('business_id', [1, 99])->delete();
        }
        DB::table('nfe_emissoes')->whereIn('business_id', [1, 99])->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    if (Schema::hasTable('arquivos')) {
        DB::table('arquivos')->where('classified_by', 'test-pr13-prefer-arquivos')->delete();
    }
});

function fakeFactoryCapturaXml(array &$captured): Closure
{
    return function (string $xml) use (&$captured) {
        $captured[] = $xml;
        $mock = \Mockery::mock(Danfe::class);
        $mock->shouldReceive('render')->withAnyArgs()->andReturn('PDF-FAKE');
        return $mock;
    };
}

it('lê XML de arquivos backbone quando xml_arquivo presente (não xml_path)', function () {
    $emissao = NfeEmissao::create([
        'business_id'  => 1,
        'numero'       => 1,
        'chave_44'     => '35210112345678000199550010000000011000000019',
        'status'       => 'autorizada',
        'cstat'        => '100',
        'xml_path'     => 'nfe-brasil/1/notas/1-1.xml',
        'valor_total'  => 100.00,
        'emitido_em'   => now(),
    ]);

    $arquivosPath = "biz-1/2026/05/{$emissao->id}.xml";
    Storage::disk('local')->put($arquivosPath, '<XML>FROM-ARQUIVOS-BACKBONE</XML>');
    Storage::disk('local')->put($emissao->xml_path, '<XML>FROM-LEGACY-PATH</XML>');

    DB::table('arquivos')->insert([
        'business_id'     => 1,
        'arquivable_type' => NfeEmissao::class,
        'arquivable_id'   => $emissao->id,
        'disk'            => 'local',
        'storage_path'    => $arquivosPath,
        'filename'        => 'nfe-1.xml',
        'original_name'   => 'nfe-1.xml',
        'mime_type'       => 'application/xml',
        'size_bytes'      => 100,
        'md5'             => md5('arquivos-content'),
        'bucket'          => 'active',
        'sub_destination' => 'nfe-xml',
        'classified_by'   => 'test-pr13-prefer-arquivos',
        'classified_at'   => now(),
        'encrypted'       => false,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $captured = [];
    $svc = new DanfeService(fakeFactoryCapturaXml($captured));
    $svc->renderizar($emissao);

    expect($captured)->toHaveCount(1);
    expect($captured[0])->toBe('<XML>FROM-ARQUIVOS-BACKBONE</XML>');
    expect($captured[0])->not->toContain('FROM-LEGACY-PATH');
});

it('fallback pra xml_path legacy quando sem row em arquivos', function () {
    $emissao = NfeEmissao::create([
        'business_id'  => 1,
        'numero'       => 2,
        'chave_44'     => '35210112345678000199550010000000021000000026',
        'status'       => 'autorizada',
        'cstat'        => '100',
        'xml_path'     => 'nfe-brasil/1/notas/legacy-only.xml',
        'valor_total'  => 50.00,
        'emitido_em'   => now(),
    ]);

    Storage::disk('local')->put($emissao->xml_path, '<XML>LEGACY-ONLY</XML>');
    // NÃO insere row em arquivos

    $captured = [];
    $svc = new DanfeService(fakeFactoryCapturaXml($captured));
    $svc->renderizar($emissao);

    expect($captured)->toHaveCount(1);
    expect($captured[0])->toBe('<XML>LEGACY-ONLY</XML>');
});

it('cai pra fallback xml_path quando arquivos row existe mas file físico ausente', function () {
    $emissao = NfeEmissao::create([
        'business_id'  => 1,
        'numero'       => 3,
        'chave_44'     => '35210112345678000199550010000000031000000033',
        'status'       => 'autorizada',
        'cstat'        => '100',
        'xml_path'     => 'nfe-brasil/1/notas/3-1.xml',
        'valor_total'  => 75.00,
        'emitido_em'   => now(),
    ]);

    Storage::disk('local')->put($emissao->xml_path, '<XML>FALLBACK-WORKED</XML>');
    // arquivos row aponta pra path inexistente
    DB::table('arquivos')->insert([
        'business_id'     => 1,
        'arquivable_type' => NfeEmissao::class,
        'arquivable_id'   => $emissao->id,
        'disk'            => 'local',
        'storage_path'    => 'biz-1/inexistente/missing.xml',
        'filename'        => 'missing.xml',
        'original_name'   => 'missing.xml',
        'mime_type'       => 'application/xml',
        'size_bytes'      => 0,
        'md5'             => 'placeholder',
        'bucket'          => 'active',
        'sub_destination' => 'nfe-xml',
        'classified_by'   => 'test-pr13-prefer-arquivos',
        'classified_at'   => now(),
        'encrypted'       => false,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $captured = [];
    $svc = new DanfeService(fakeFactoryCapturaXml($captured));
    $svc->renderizar($emissao);

    expect($captured)->toHaveCount(1);
    expect($captured[0])->toBe('<XML>FALLBACK-WORKED</XML>');
});
