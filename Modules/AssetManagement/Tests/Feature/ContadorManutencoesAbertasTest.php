<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\AssetManagement\Entities\Asset;
use Modules\AssetManagement\Entities\AssetMaintenance;
use Modules\AssetManagement\Services\AssetMaintenanceService;

uses(Tests\TestCase::class);

/**
 * Contrato do contador da aba "Manutencoes" (`AssetMaintenanceService::contarAbertas`).
 *
 * O QUE ELE DEFENDE, e por que a allowlist importa. `AssetUtil::maintenanceStatuses()`
 * declara QUATRO status: `new`, `in_progress`, `completed`, `cancelled`. A formulacao
 * intuitiva -- `status <> 'completed'` -- contaria manutencao CANCELADA como trabalho
 * pendente. O banco de staging so tinha `completed` e `in_progress`, entao o DADO nao
 * denunciaria o defeito; so a leitura do codigo denunciou. O 2o cenario abaixo e o
 * controle: se alguem trocar a allowlist por negacao, ele cai.
 *
 * TIER 0 (ADR 0093). O contador aparece no cabecalho das telas do modulo, isto e, num
 * lugar que todo tenant ve. O 3o cenario prova que a contagem de um business ignora as
 * manutencoes do outro -- e o unico jeito de esse pill vazar seria contando alheio.
 *
 * ADR 0358: tenant canonico FICTICIO 98 (`seededTenant()`), adversario 99
 * (`seededSupportClientTenant()`). Nunca biz=1 nem biz=4.
 *
 * Limpeza em `try/finally`, nao em `afterEach`: a base do CT 100 nao se limpa entre runs
 * e o gancho por teste ja foi medido nao executando (ver `ManutencoesContratoTest:24`).
 * `finally` tambem limpa quando o assert falha.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: Models AssetManagement legacy requerem schema MySQL UltimatePOS');
    }
    foreach (['assets', 'asset_maintenances', 'business', 'users'] as $tabela) {
        if (! Schema::hasTable($tabela)) {
            $this->markTestSkipped("Tabela {$tabela} ausente -- rode migrate primeiro");
        }
    }
});

function contadorUsuario(int $businessId): User
{
    return User::factory()->create([
        'business_id' => $businessId,
        'username' => 'contador_manut_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
}

function contadorAsset(int $businessId, int $ownerId): Asset
{
    return Asset::create([
        'business_id' => $businessId,
        'name' => 'Fixture contador',
        'asset_code' => 'CTD-'.uniqid(),
        'quantity' => 1,
        'unit_price' => 100.00,
        'is_allocatable' => 1,
        'purchase_type' => 'owned',
        'created_by' => $ownerId,
    ]);
}

function contadorManutencao(int $businessId, int $assetId, int $createdBy, string $status): AssetMaintenance
{
    return AssetMaintenance::create([
        'business_id' => $businessId,
        'asset_id' => $assetId,
        'maitenance_id' => 'CTD-'.uniqid(),  // typo do schema, preservado de proposito
        'status' => $status,
        'priority' => 'low',
        'created_by' => $createdBy,
        'details' => 'Fixture do contador de abertas',
    ]);
}

it('conta as abertas: `new` e `in_progress` somam, e mais nada', function () {
    $biz = $this->seededTenant();
    $user = contadorUsuario($biz->id);
    $asset = contadorAsset($biz->id, $user->id);
    $criadas = [];

    try {
        $antes = app(AssetMaintenanceService::class)->contarAbertas($biz->id);

        // Delta, nao valor absoluto: a base do CT 100 persiste entre runs, entao um
        // numero fixo mediria o acumulado de outras suites em vez desta fixture.
        foreach (['new', 'in_progress'] as $status) {
            $criadas[] = contadorManutencao($biz->id, $asset->id, $user->id, $status);
        }

        expect(app(AssetMaintenanceService::class)->contarAbertas($biz->id))->toBe($antes + 2);
    } finally {
        foreach ($criadas as $m) {
            $m->forceDelete();
        }
        $asset->forceDelete();
        $user->forceDelete();
    }
});

it('NAO conta `completed` nem `cancelled` -- cancelada nao e trabalho pendente', function () {
    $biz = $this->seededTenant();
    $user = contadorUsuario($biz->id);
    $asset = contadorAsset($biz->id, $user->id);
    $criadas = [];

    try {
        $antes = app(AssetMaintenanceService::class)->contarAbertas($biz->id);

        foreach (['completed', 'cancelled'] as $status) {
            $criadas[] = contadorManutencao($biz->id, $asset->id, $user->id, $status);
        }

        // Este assert e o que separa a allowlist da negacao: com `status <> completed`,
        // a `cancelled` entraria e o contador subiria 1.
        expect(app(AssetMaintenanceService::class)->contarAbertas($biz->id))->toBe($antes);
    } finally {
        foreach ($criadas as $m) {
            $m->forceDelete();
        }
        $asset->forceDelete();
        $user->forceDelete();
    }
});

it('Tier 0: a contagem de um business ignora as manutencoes do outro', function () {
    $dono = $this->seededTenant();
    $vizinho = $this->seededSupportClientTenant();

    $userDono = contadorUsuario($dono->id);
    $userVizinho = contadorUsuario($vizinho->id);
    $assetVizinho = contadorAsset($vizinho->id, $userVizinho->id);
    $criadas = [];

    try {
        $antesDono = app(AssetMaintenanceService::class)->contarAbertas($dono->id);

        // Tres abertas no VIZINHO. Se o filtro de tenant sumir, o contador do dono sobe.
        foreach (['new', 'in_progress', 'new'] as $status) {
            $criadas[] = contadorManutencao($vizinho->id, $assetVizinho->id, $userVizinho->id, $status);
        }

        expect(app(AssetMaintenanceService::class)->contarAbertas($dono->id))->toBe($antesDono);
        expect(app(AssetMaintenanceService::class)->contarAbertas($vizinho->id))->toBeGreaterThanOrEqual(3);
    } finally {
        foreach ($criadas as $m) {
            $m->forceDelete();
        }
        $assetVizinho->forceDelete();
        $userVizinho->forceDelete();
        $userDono->forceDelete();
    }
});
