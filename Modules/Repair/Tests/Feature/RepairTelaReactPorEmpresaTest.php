<?php

declare(strict_types=1);

use App\Services\FeatureFlagService;
use Modules\Repair\Concerns\TelaReactPorEmpresa;

/**
 * Decisão "React ou Blade" das telas do Repair — trait TelaReactPorEmpresa.
 *
 * Contrato: a porta antiga (config/mwart.php, env MWART_REPAIR_* + allowlist _BIZ)
 * continua valendo SEM mudança — é por ela que 5 telas estão ligadas para biz=1 em
 * produção (medido no .env em 2026-11-03). A porta nova (flag GrowthBook por
 * business_id) liga uma tela só para a empresa que a regra nomeia.
 *
 * Sem banco: o FeatureFlagService é trocado por um dublê que responde por
 * business_id, então o arquivo roda igual em sqlite (modules-pest) e MySQL.
 * Tenants fictícios 98 (dono) e 99 (adversário) — ADR 0358.
 */
uses(Tests\TestCase::class);

function telaRepair(): object
{
    return new class {
        use TelaReactPorEmpresa;

        public function decide(string $key, int $biz): bool
        {
            return $this->mwartEnabled($key, $biz);
        }
    };
}

/** Dublê: liga $flag só para os business_ids da lista; grava o que foi perguntado. */
function flagsSoPara(string $flag, array $bizIds): object
{
    $dubleh = new class($flag, $bizIds) extends FeatureFlagService {
        public array $perguntas = [];

        public function __construct(private string $flagAlvo, private array $bizIds) {}

        public function isOn(string $flag, array $attrs = []): bool
        {
            $this->perguntas[] = [$flag, $attrs['business_id'] ?? null];

            return $flag === $this->flagAlvo && in_array($attrs['business_id'] ?? null, $this->bizIds, true);
        }
    };
    app()->instance(FeatureFlagService::class, $dubleh);

    return $dubleh;
}

beforeEach(function () {
    config(['mwart.repair_job_sheet_create' => ['enabled' => false, 'business_ids' => []]]);
    config(['mwart.repair_index' => ['enabled' => false, 'business_ids' => []]]);
});

it('porta antiga: env ligada com allowlist liga só a empresa listada, sem perguntar ao GrowthBook', function () {
    config(['mwart.repair_index' => ['enabled' => true, 'business_ids' => [98]]]);
    $flags = flagsSoPara('nenhuma', []);

    expect(telaRepair()->decide('repair_index', 98))->toBeTrue();
    expect($flags->perguntas)->toBe([]);
    expect(telaRepair()->decide('repair_index', 99))->toBeFalse();
});

it('porta antiga: env ligada sem allowlist liga todas as empresas', function () {
    config(['mwart.repair_index' => ['enabled' => true, 'business_ids' => []]]);
    flagsSoPara('nenhuma', []);

    expect(telaRepair()->decide('repair_index', 99))->toBeTrue();
});

it('porta nova: regra GrowthBook por empresa liga só a empresa da regra', function () {
    $flags = flagsSoPara('useV2RepairJobSheetCreate', [98]);

    expect(telaRepair()->decide('repair_job_sheet_create', 98))->toBeTrue();
    expect(telaRepair()->decide('repair_job_sheet_create', 99))->toBeFalse();
    expect($flags->perguntas)->toBe([
        ['useV2RepairJobSheetCreate', 98],
        ['useV2RepairJobSheetCreate', 99],
    ]);
});

it('flag de outra tela não liga esta', function () {
    flagsSoPara('useV2RepairJobSheetCreate', [98]);

    expect(telaRepair()->decide('repair_job_sheet_edit', 98))->toBeFalse();
});

it('chave fora do mapa e business_id inválido nunca ligam nem consultam o GrowthBook', function () {
    $flags = flagsSoPara('useV2RepairIndex', [0, 98]);

    expect(telaRepair()->decide('repair_inexistente', 98))->toBeFalse();
    expect(telaRepair()->decide('repair_index', 0))->toBeFalse();
    expect($flags->perguntas)->toBe([]);
});

it('toda chave repair_* de config/mwart.php tem flag GrowthBook mapeada', function () {
    $semFlag = [];
    foreach (array_keys((array) config('mwart')) as $chave) {
        if (str_starts_with($chave, 'repair_') && $chave !== 'repair_show_fsm_panel'
            && TelaReactPorEmpresaHost::flag($chave) === null) {
            $semFlag[] = $chave;
        }
    }

    expect($semFlag)->toBe([]);
});

final class TelaReactPorEmpresaHost
{
    use TelaReactPorEmpresa;

    public static function flag(string $key): ?string
    {
        return self::flagDaTelaRepair($key);
    }
}
