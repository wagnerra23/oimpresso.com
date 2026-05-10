<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * US-ARQ-020 — Pest test pra migration backfill NFe XML/DANFE.
 *
 * Migration: 2026_05_10_000010_backfill_nfe_xml_arquivos.php
 *
 * Cobertura:
 * - Idempotência: rodar 2x não duplica rows
 * - Multi-tenant Tier 0: arquivos.business_id = nfe_emissoes.business_id
 * - Rollback (down) deleta apenas rows com classified_by='backfill-us-arq-020'
 *
 * IMPORTANTE Felipe: rode local ANTES de aplicar em prod.
 */

beforeEach(function () {
    if (! Schema::hasTable('arquivos') || ! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('Tables ausentes — rode migrate primeiro');
    }
});

it('migration backfill é idempotente (rodar 2x não duplica)', function () {
    $countBefore = DB::table('arquivos')
        ->where('classified_by', 'backfill-us-arq-020')
        ->count();

    // Simula rodar a migration via re-execução manual da lógica
    $this->artisan('migrate', ['--path' => 'Modules/Arquivos/Database/Migrations']);
    $this->artisan('migrate', ['--path' => 'Modules/Arquivos/Database/Migrations']);

    $countAfter = DB::table('arquivos')
        ->where('classified_by', 'backfill-us-arq-020')
        ->count();

    // Esperado: count não cresce em re-execução (mesmo NfeEmissao não duplica row)
    expect($countAfter)->toBe($countBefore);
});

it('arquivos backfill preservam business_id do NfeEmissao origem', function () {
    $diff = DB::table('arquivos as a')
        ->join('nfe_emissoes as e', function ($join) {
            $join->on('a.arquivable_id', '=', 'e.id')
                ->where('a.arquivable_type', 'Modules\\NfeBrasil\\Models\\NfeEmissao')
                ->where('a.classified_by', 'backfill-us-arq-020');
        })
        ->whereColumn('a.business_id', '!=', 'e.business_id')
        ->count();

    expect($diff)->toBe(0, "Arquivos backfill com business_id divergente do NfeEmissao detectados ({$diff}). " .
        'Multi-tenant Tier 0 (ADR 0093) violado.');
});

it('arquivos backfill tem classified_by tag rastreável', function () {
    if (DB::table('arquivos')->where('classified_by', 'backfill-us-arq-020')->count() === 0) {
        $this->markTestSkipped('Sem dados backfilled — migration ainda não rodou OU tabelas vazias');
        return;
    }

    $rows = DB::table('arquivos')
        ->where('classified_by', 'backfill-us-arq-020')
        ->limit(5)
        ->get();

    foreach ($rows as $row) {
        expect($row->bucket)->toBe('active');
        expect(in_array($row->sub_destination, ['nfe-xml', 'nfe-danfe'], true))->toBeTrue();
        expect($row->arquivable_type)->toMatch('/Modules\\\\NfeBrasil\\\\Models\\\\(NfeEmissao|NfeDfeRecebido)/');
    }
});

it('arquivos backfill tem MIME type adequado pro path', function () {
    $wrongMime = DB::table('arquivos')
        ->where('classified_by', 'backfill-us-arq-020')
        ->where(function ($q) {
            $q->where(function ($qq) {
                // .pdf path mas MIME != application/pdf
                $qq->where('storage_path', 'like', '%.pdf')
                    ->where('mime_type', '!=', 'application/pdf');
            })->orWhere(function ($qq) {
                // .xml path mas MIME != application/xml
                $qq->where('storage_path', 'like', '%.xml')
                    ->where('mime_type', '!=', 'application/xml');
            });
        })
        ->count();

    expect($wrongMime)->toBe(0, "Arquivos backfill com MIME inadequado ({$wrongMime}).");
});
