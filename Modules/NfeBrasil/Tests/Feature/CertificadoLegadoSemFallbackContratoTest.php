<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\CertificadoService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Lote 1.5 da auditoria do NfeBrasil (N5-08) · ADR 0449 (Fase 4 da ADR 0090).
 *
 * O certificado A1 vem só de `nfe_certificados` (pfx cifrado em disco privado, senha cifrada).
 * O caminho que caía em `business.certificado` (pfx em claro) com `business.senha_certificado`
 * (base64, reversível) saiu. Tenant 98 (ADR 0358); o business é alterado dentro da transação.
 */
beforeEach(function () {
    if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('nfe_certificados') || ! Schema::hasColumn('business', 'certificado')) {
        $this->markTestSkipped('precisa do schema MySQL de business e nfe_certificados');
    }
    $this->biz = (int) $this->seededTenant()->id;
    DB::table('nfe_certificados')->where('business_id', $this->biz)->update(['ativo' => false]);
    DB::table('business')->where('id', $this->biz)->update([
        'certificado'       => 'PFX-LEGADO-DO-TESTE',
        'senha_certificado' => base64_encode('senha-do-teste'),
    ]);
});

it('CU-NFE · empresa só com o certificado legado não emite com ele: pede o A1 de novo', function () {
    expect(fn () => app(CertificadoService::class)->carregarParaSefaz($this->biz))
        ->toThrow(RuntimeException::class, 'não tem certificado A1 ativo');
});

it('CU-NFE · a cadeia da consulta de cadastro também não usa o legado', function () {
    config(['fiscal.fallback_business_id' => $this->biz]);

    expect(fn () => app(CertificadoService::class)->carregarParaSefazComFallback($this->biz))
        ->toThrow(RuntimeException::class);
});
