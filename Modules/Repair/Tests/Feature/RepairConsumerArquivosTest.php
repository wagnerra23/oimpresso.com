<?php

declare(strict_types=1);

use App\Business;
use App\Media;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Arquivos\Entities\Arquivo;
use Modules\Repair\Entities\JobSheet;

uses(Tests\TestCase::class);

/**
 * US-ARQ-029 — Sprint 3 ADR 0123 §2.
 *
 * Cobertura do accessor `getAnexosAttribute` em JobSheet:
 * - backbone arquivos presente → retorna coleção Arquivo (não Media)
 * - backbone ausente → fallback graceful pra relação media() legacy
 * - multi-tenant Tier 0: biz=1 NÃO vê arquivos de biz=99 (ADR 0093)
 *
 * Padrão: roda contra DB dev real, auto-skip se tabelas ausentes.
 * biz=1 (Wagner WR2 SC) — nunca biz=4 (ROTA LIVRE — ADR 0101).
 */

function repairArquivosBootstrap(): array
{
    foreach (['arquivos', 'repair_job_sheets'] as $table) {
        if (! Schema::hasTable($table)) {
            test()->markTestSkipped("Tabela {$table} indisponível.");
        }
    }

    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: ' . $e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode seeder UltimatePOS antes.');
    }

    $user = User::where('business_id', $business->id)->first();
    if (! $user) {
        test()->markTestSkipped('Sem user no business.');
    }

    session([
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'business.id'      => $business->id,
    ]);

    return [$business, $user];
}

/**
 * Cria JobSheet mínimo no banco sem passar por validação de formulário.
 */
function repairCriarJobSheet(int $businessId, int $userId): JobSheet
{
    $js = new JobSheet([
        'business_id'  => $businessId,
        'created_by'   => $userId,
        'job_sheet_no' => 'TEST-ARQ029-' . uniqid(),
        'service_type' => 'carry_in',
        'status_id'    => 0,
        'location_id'  => DB::table('business_locations')
            ->where('business_id', $businessId)
            ->value('id') ?? 1,
    ]);
    $js->save();

    return $js;
}

afterEach(function () {
    // Limpa JobSheets criados pelo test suite
    try {
        $ids = JobSheet::where('job_sheet_no', 'like', 'TEST-ARQ029-%')
            ->withoutGlobalScopes() // SUPERADMIN: limpeza de teste cross-biz
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('arquivos')
                ->where('arquivable_type', 'Modules\\Repair\\Entities\\JobSheet')
                ->whereIn('arquivable_id', $ids)
                ->delete();

            DB::table('media')
                ->where('model_type', 'Modules\\Repair\\Entities\\JobSheet')
                ->whereIn('model_id', $ids)
                ->delete();

            JobSheet::withoutGlobalScopes() // SUPERADMIN: limpeza de teste cross-biz
                ->whereIn('id', $ids)
                ->delete();
        }
    } catch (\Throwable) {
        // ignora — env pode não ter tabelas
    }
});

// -----------------------------------------------------------------------------
// Cenário 1: backbone Arquivos presente → accessor retorna coleção Arquivo
// -----------------------------------------------------------------------------

it('accessor anexos retorna coleção Arquivo quando backbone está presente', function () {
    [$business, $user] = repairArquivosBootstrap();

    $js = repairCriarJobSheet((int) $business->id, (int) $user->id);

    // Insere arquivo backbone diretamente (sem upload real)
    DB::table('arquivos')->insert([
        'business_id'         => $business->id,
        'arquivable_type'     => 'Modules\\Repair\\Entities\\JobSheet',
        'arquivable_id'       => $js->id,
        'disk'                => 'local',
        'storage_path'        => 'biz-' . $business->id . '/2026/01/test.jpg',
        'original_name'       => 'test.jpg',
        'mime_type'           => 'image/jpeg',
        'size_bytes'          => 1024,
        'md5'                 => md5('test-arq029-cenario1-' . $js->id),
        'bucket'              => 'active',
        'sub_destination'     => 'repair-foto',
        'classified_by'       => 'test-arq029',
        'classified_at'       => now(),
        'encrypted'           => false,
        'visibility'          => 'private',
        'created_at'          => now(),
        'updated_at'          => now(),
    ]);

    // Recarrega com eager-load das duas relações (como o controller faz)
    $js->load(['media', 'arquivos']);

    $anexos = $js->anexos;

    expect($anexos)->not->toBeEmpty('Backbone presente mas accessor retornou vazio');
    expect($anexos->first())->toBeInstanceOf(Arquivo::class);
    expect($anexos->first()->sub_destination)->toBe('repair-foto');
});

// -----------------------------------------------------------------------------
// Cenário 2: backbone ausente → fallback para Media legacy
// -----------------------------------------------------------------------------

it('accessor anexos faz fallback para media legacy quando backbone está vazio', function () {
    [$business, $user] = repairArquivosBootstrap();

    if (! Schema::hasTable('media')) {
        $this->markTestSkipped('Tabela media indisponível.');
    }

    $js = repairCriarJobSheet((int) $business->id, (int) $user->id);

    // Insere só na tabela Media legacy (sem arquivo no backbone)
    DB::table('media')->insert([
        'business_id' => $business->id,
        'model_type'  => 'Modules\\Repair\\Entities\\JobSheet',
        'model_id'    => $js->id,
        'file_name'   => 'legacy_test_' . $js->id . '.jpg',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    // Recarrega sem arquivo no backbone
    $js->load(['media', 'arquivos']);

    $anexos = $js->anexos;

    expect($anexos)->not->toBeEmpty('Fallback Media não funcionou — accessor retornou vazio');
    expect($anexos->first())->toBeInstanceOf(Media::class);
});

// -----------------------------------------------------------------------------
// Cenário 3: multi-tenant Tier 0 — biz=1 não vê arquivos de biz=99
// -----------------------------------------------------------------------------

it('accessor anexos respeita isolamento multi-tenant biz=1 não vê biz=99', function () {
    [$business, $user] = repairArquivosBootstrap();

    $js = repairCriarJobSheet((int) $business->id, (int) $user->id);

    // Insere arquivo com business_id=99 (outro tenant fictício)
    DB::table('arquivos')->insert([
        'business_id'         => 99,
        'arquivable_type'     => 'Modules\\Repair\\Entities\\JobSheet',
        'arquivable_id'       => $js->id,
        'disk'                => 'local',
        'storage_path'        => 'biz-99/2026/01/invasor.jpg',
        'original_name'       => 'invasor.jpg',
        'mime_type'           => 'image/jpeg',
        'size_bytes'          => 512,
        'md5'                 => md5('invasor-biz99-' . $js->id),
        'bucket'              => 'active',
        'sub_destination'     => 'repair-foto',
        'classified_by'       => 'test-arq029-cross-biz',
        'classified_at'       => now(),
        'encrypted'           => false,
        'visibility'          => 'private',
        'created_at'          => now(),
        'updated_at'          => now(),
    ]);

    // Sessão aponta para biz do test (NÃO biz=99)
    session(['user.business_id' => $business->id]);

    $js->load(['media', 'arquivos']);

    $anexos = $js->anexos;

    // O global scope de Arquivo filtra por business_id da sessão.
    // Portanto arquivo de biz=99 NÃO deve aparecer pra sessão de biz=$business->id.
    $invasores = $anexos->filter(fn ($a) => $a instanceof Arquivo && (int) $a->business_id === 99);

    expect($invasores)->toBeEmpty(
        "Vazamento cross-tenant detectado: {$invasores->count()} arquivos de biz=99 visíveis para biz={$business->id}"
    );
});
