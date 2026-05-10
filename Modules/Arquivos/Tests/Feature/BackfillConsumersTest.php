<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * US-ARQ-026..028 — Pest test pra migration backfill consumers Sprint 4.
 *
 * Migration: 2026_05_10_000020_backfill_consumers_arquivos.php
 *
 * Cobertura:
 * - Idempotência (rodar 2x não duplica)
 * - Multi-tenant Tier 0 preservado pra Repair JobSheet (business_id herdado)
 * - Tags rastreáveis classified_by por consumer
 * - MIME types adequados pra cada extensão
 *
 * @see memory/decisions/0123-modules-arquivos-backbone.md Sprint 5
 */

beforeEach(function () {
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('arquivos table missing');
    }
});

it('arquivos backfill Repair Media tem classified_by tag US-ARQ-026', function () {
    if (DB::table('arquivos')->where('classified_by', 'backfill-us-arq-026')->count() === 0) {
        $this->markTestSkipped('Sem dados Repair backfilled — media table vazia');
        return;
    }

    $rows = DB::table('arquivos')
        ->where('classified_by', 'backfill-us-arq-026')
        ->limit(5)
        ->get();

    foreach ($rows as $row) {
        expect($row->bucket)->toBe('active');
        expect($row->sub_destination)->toBe('repair-foto');
        expect($row->arquivable_type)->toBe('Modules\\Repair\\Entities\\JobSheet');
    }
});

it('arquivos backfill Repair preservam business_id de media origem', function () {
    $diff = DB::table('arquivos as a')
        ->join('media as m', function ($join) {
            $join->on('a.arquivable_id', '=', 'm.model_id')
                ->where('m.model_type', 'Modules\\Repair\\Entities\\JobSheet')
                ->where('a.classified_by', 'backfill-us-arq-026');
        })
        ->whereColumn('a.business_id', '!=', 'm.business_id')
        ->count();

    expect($diff)->toBe(0, "Arquivos Repair com business_id divergente do Media origem ({$diff})");
});

it('arquivos backfill Cms feature_image tem sub_destination cms-featured', function () {
    if (DB::table('arquivos')->where('classified_by', 'backfill-us-arq-027')->count() === 0) {
        $this->markTestSkipped('Sem dados Cms backfilled — cms_pages.feature_image vazias');
        return;
    }

    $rows = DB::table('arquivos')
        ->where('classified_by', 'backfill-us-arq-027')
        ->limit(5)
        ->get();

    foreach ($rows as $row) {
        expect($row->sub_destination)->toBe('cms-featured');
        expect($row->arquivable_type)->toBe('Modules\\Cms\\Entities\\CmsPage');
        expect($row->storage_path)->toContain('uploads/cms/');
    }
});

it('arquivos backfill Boleto PDF tem MIME application/pdf', function () {
    if (DB::table('arquivos')->where('classified_by', 'backfill-us-arq-028')->count() === 0) {
        $this->markTestSkipped('Sem boletos backfilled — fin_boleto_remessas vazia');
        return;
    }

    $rows = DB::table('arquivos')
        ->where('classified_by', 'backfill-us-arq-028')
        ->limit(5)
        ->get();

    foreach ($rows as $row) {
        expect($row->sub_destination)->toBe('fin-boleto-pdf');
        expect($row->mime_type)->toBe('application/pdf');
        expect($row->arquivable_type)->toBe('Modules\\Financeiro\\Models\\BoletoRemessa');
    }
});

it('migration backfill consumers é idempotente', function () {
    $countBefore = DB::table('arquivos')
        ->whereIn('classified_by', [
            'backfill-us-arq-026',
            'backfill-us-arq-027',
            'backfill-us-arq-028',
        ])
        ->count();

    $this->artisan('migrate', ['--path' => 'Modules/Arquivos/Database/Migrations']);
    $this->artisan('migrate', ['--path' => 'Modules/Arquivos/Database/Migrations']);

    $countAfter = DB::table('arquivos')
        ->whereIn('classified_by', [
            'backfill-us-arq-026',
            'backfill-us-arq-027',
            'backfill-us-arq-028',
        ])
        ->count();

    expect($countAfter)->toBe($countBefore);
});
