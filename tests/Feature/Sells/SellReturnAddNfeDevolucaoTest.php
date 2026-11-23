<?php

// @covers-us US-SELL-066

declare(strict_types=1);

use App\Http\Controllers\SellReturnController;
use Illuminate\Support\Facades\Auth;
use Modules\NfeBrasil\Services\DevolucaoService;

/**
 * Onda 1 do protótipo Devolução V3 (RUNBOOK-sell-return-add §6.2): o registro mostra a seção
 * "NF-e de devolução" com o que a rota POST /fiscal/acoes/venda-devolucao/{id}/nfe vai fazer.
 *
 * UCs: resources/js/Pages/SellReturn/Add.casos.md (UC-SRADD-10..12). O que se prova aqui é a
 * PROP `nfe_devolucao` (quando aparece e o que diz); a tradução de quantidades e o valor da nota
 * estão provados em Modules/Fiscal/Tests/Feature/AcoesDevolucaoDaVendaTest.php (UC-FNFE-73..80).
 * O serviço é substituído por um falso. Só o UC-SRADD-12 lê o banco (procura em `nfe_emissoes` a
 * nota de origem do plano, id 0 no tenant 98, que não existe): roda na lane sells-pest (MySQL).
 */
function sraddNfeUsuario(bool $fiscal): void
{
    Auth::setUser(new class($fiscal) extends \App\User
    {
        public function __construct(private bool $fiscal = true) {}

        public function can($abilities, $arguments = []): bool
        {
            return $this->fiscal && $abilities === 'fiscal.nfe.acoes';
        }
    });
}

/** Chama o método privado que monta a prop, com uma devolução salva de id 501. */
function sraddNfeProp(?object $devolucao = null): ?array
{
    $m = new ReflectionMethod(SellReturnController::class, 'inertiaNfeDevolucao');
    $m->setAccessible(true);

    return $m->invoke(app(SellReturnController::class), 98, $devolucao ?? (object) ['id' => 501]);
}

function sraddNfeServico(?array $plano, ?string $recusa = null): void
{
    app()->instance(DevolucaoService::class, new class($plano, $recusa) extends DevolucaoService
    {
        public function __construct(private ?array $plano, private ?string $recusa) {}

        public function planoDaDevolucaoDeVenda(int $businessId, int $sellReturnId): array
        {
            if ($this->recusa !== null) {
                throw new RuntimeException($this->recusa);
            }

            return $this->plano;
        }
    });
}

afterEach(function () {
    app()->forgetInstance(DevolucaoService::class);
});

it('UC-SRADD-10 · sem a flag, sem permissão fiscal ou sem devolução salva, a seção não aparece', function () {
    sraddNfeServico(['origem_id' => 0, 'itens' => [1 => 1.0]]);

    config(['nfebrasil.devolucao_pela_venda' => false]);
    sraddNfeUsuario(true);
    expect(sraddNfeProp())->toBeNull();

    config(['nfebrasil.devolucao_pela_venda' => true, 'nfebrasil.devolucao_pela_venda_business_ids' => []]);
    sraddNfeUsuario(false);
    expect(sraddNfeProp())->toBeNull();

    sraddNfeUsuario(true);
    $m = new ReflectionMethod(SellReturnController::class, 'inertiaNfeDevolucao');
    $m->setAccessible(true);
    expect($m->invoke(app(SellReturnController::class), 98, null))->toBeNull();

    // Lista de empresas sem a 98 (NFEBRASIL_DEVOLUCAO_PELA_VENDA_BIZ): a seção não aparece.
    config(['nfebrasil.devolucao_pela_venda_business_ids' => [97]]);
    expect(sraddNfeProp())->toBeNull();
    config(['nfebrasil.devolucao_pela_venda_business_ids' => []]);

    // Controle positivo: flag ligada, permissão e devolução salva → a seção aparece.
    expect(sraddNfeProp())->not->toBeNull();
});

it('UC-SRADD-11 · quando a rota recusaria, a seção mostra o motivo e não oferece o botão', function () {
    config(['nfebrasil.devolucao_pela_venda' => true, 'nfebrasil.devolucao_pela_venda_business_ids' => []]);
    sraddNfeUsuario(true);
    sraddNfeServico(null, 'Nada a declarar: tudo o que foi devolvido já está em NF-e de devolução.');

    $prop = sraddNfeProp();

    expect($prop['disponivel'])->toBeFalse();
    expect($prop['motivo'])->toBe('Nada a declarar: tudo o que foi devolvido já está em NF-e de devolução.');
    expect($prop['devolucao_id'])->toBe(501);
});

it('UC-SRADD-12 · quando dá para emitir, a seção diz quantos itens a nota vai declarar', function () {
    config(['nfebrasil.devolucao_pela_venda' => true, 'nfebrasil.devolucao_pela_venda_business_ids' => []]);
    sraddNfeUsuario(true);
    // origem_id 0 não existe: a nota de origem sai sem número, o resto vem do plano.
    sraddNfeServico(['origem_id' => 0, 'itens' => [1 => 4.0, 3 => 1.0]]);

    $prop = sraddNfeProp();

    expect($prop['disponivel'])->toBeTrue();
    expect($prop['itens'])->toBe(2);
    expect($prop['motivo'])->toBeNull();
    expect($prop['devolucao_id'])->toBe(501);
});
