<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;

/**
 * Regressão do flaky do FetchTemplatesTest (2026-10-01).
 *
 * O Eloquent guarda em `Model::$guardableColumns` (estático, por processo) a lista de colunas
 * usada pelo `fill()` quando o model tem `$guarded` não-vazio. Um teste com schema sintético
 * reduzido envenenava essa lista para os testes seguintes. Tests\TestCase::setUp() passou a
 * zerá-la — este teste reproduz o envenenamento num único processo e prova as duas pontas:
 * sem o reset o atributo some (o defeito), com o reset ele volta (o conserto).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('schema sintético — lane sqlite (ci-sqlite-pest.list).');
    }
});

// Sem DDL no afterEach de propósito: o teardown roda mesmo com o teste pulado (MySQL) e um
// drop ali corromperia o schema persistente (scripts/audit/sqlite-test-corruptors.mjs).
// A tabela sintética vive na sqlite :memory: e some com o app.
afterEach(function () {
    $this->resetEloquentGuardableColumnsCache();
});

it('o cache de colunas guardáveis envenenado descarta atributo, e o reset do TestCase o devolve', function () {
    // Mesmo schema reduzido do AtendimentoMacrosJanaTemplatesContratoTest: sem colunas meta_*.
    Schema::dropIfExists('whatsapp_business_configs');
    Schema::create('whatsapp_business_configs', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('driver', 20)->default('zapi');
    });

    // Popula o cache estático, como o firstOrNew do SettingsController faz.
    WhatsappBusinessConfig::firstOrNew(['business_id' => 98]);

    // Próximo teste do processo: a tabela não existe mais (app novo, sqlite :memory:).
    Schema::drop('whatsapp_business_configs');

    // Defeito reproduzido: sem reset, o fill descarta o phone id em silêncio.
    $envenenado = new WhatsappBusinessConfig(['meta_phone_number_id' => 'PHONE_ID_123']);
    expect($envenenado->meta_phone_number_id)->toBeNull();

    $this->resetEloquentGuardableColumnsCache();

    $limpo = new WhatsappBusinessConfig(['meta_phone_number_id' => 'PHONE_ID_123']);
    expect($limpo->meta_phone_number_id)->toBe('PHONE_ID_123');
});
