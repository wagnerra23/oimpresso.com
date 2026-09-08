<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\AssetManagement\Entities\Asset;
use Modules\AssetManagement\Entities\AssetTransaction;

uses(Tests\TestCase::class);

/**
 * Contrato da tela Patrimonio/Alocacoes — a listagem de alocacoes migrada de Blade pra Inertia.
 *
 * Defende os UCs declarados em `resources/js/Pages/Patrimonio/Alocacoes.casos.md` (G-2 do
 * casos-gate, ADR 0264): cada `it()` cita o id do UC no titulo, que e como o gate amarra
 * caso <-> teste.
 *
 * ADR 0358: tenant canonico de teste e o FICTICIO 98 (`seededTenant()`); 99 e o adversario
 * cross-tenant (`seededSupportClientTenant()`). biz=1 e a WR2 Sistemas, empresa REAL — no
 * CT 100 a base e clone de prod e nao se limpa entre execucoes. biz=4 (ROTA LIVRE) e
 * proibido sem excecao.
 *
 * @see resources/js/Pages/Patrimonio/Alocacoes.casos.md
 * @see memory/requisitos/AssetManagement/RUNBOOK-alocacoes.md
 * @see memory/decisions/0394-endereco-de-ui-do-patrimonio-pages-patrimonio.md
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: Models AssetManagement legacy requerem schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('asset_transactions') || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Tabelas asset_transactions/business ausentes — rode migrate primeiro');
    }
});

/**
 * Usuario do business dado.
 *
 * Este controller NAO tem guarda `asset.*` em metodo nenhum — so o gate de assinatura do
 * modulo (a assimetria com o `AssetController::index()`, que ganhou `asset.view` na thread 03,
 * esta declarada no §9 do RUNBOOK). Por isso aqui nao ha papel nem permissao a conceder: se
 * um dia a guarda entrar, ESTES testes quebram, e e assim que se descobre.
 */
function alocacoesContratoUsuario(int $businessId): User
{
    return User::factory()->create([
        'business_id' => $businessId,
        'username' => 'aloc_contrato_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
}

/**
 * `created_by` e NOT NULL nas tres tabelas do modulo e FK->`users.id` em duas — fixture sem
 * ele estoura na FK antes de chegar no assert. Em `asset_transactions`, `receiver` tambem e
 * FK->`users.id`.
 */
function alocacoesContratoAsset(int $businessId, int $ownerId, string $codigo, string $nome): Asset
{
    return Asset::create([
        'business_id' => $businessId,
        'name' => $nome,
        'asset_code' => $codigo,
        'quantity' => 10,
        'unit_price' => 100.00,
        'is_allocatable' => 1,
        'purchase_type' => 'owned',
        'created_by' => $ownerId,
    ]);
}

/**
 * Cria a alocacao (`transaction_type=allocate`). `quantity` e DECIMAL(22,4).
 *
 * ⚠️ A quantidade e passada como NUMERO, nunca como string formatada: este caminho grava
 * direto pelo Model, sem passar por `Util::num_uf` — que leria "2.500" como 2500 (um ponto
 * seguido de exatamente 3 digitos e separador de milhar pt-BR). O vetor esta declarado no
 * §11 do RUNBOOK; o teste nao o exercita porque esta onda nao posta quantidade.
 */
function alocacoesContratoAlocacao(
    int $businessId,
    int $assetId,
    int $userId,
    string $refNo,
    float $quantidade = 4.0,
): AssetTransaction {
    return AssetTransaction::create([
        'business_id' => $businessId,
        'asset_id' => $assetId,
        'transaction_type' => 'allocate',
        'ref_no' => $refNo,
        'receiver' => $userId,
        'quantity' => $quantidade,
        'transaction_datetime' => now()->subDay(),
        'created_by' => $userId,
    ]);
}

/** Devolucao parcial ou total — linha filha, ligada pelo `parent_id`. */
function alocacoesContratoDevolucao(
    int $businessId,
    AssetTransaction $alocacao,
    int $userId,
    string $refNo,
    float $quantidade,
): AssetTransaction {
    return AssetTransaction::create([
        'business_id' => $businessId,
        'asset_id' => $alocacao->asset_id,
        'transaction_type' => 'revoke',
        'ref_no' => $refNo,
        'receiver' => $userId,
        'quantity' => $quantidade,
        'transaction_datetime' => now(),
        'parent_id' => $alocacao->id,
        'created_by' => $userId,
    ]);
}

/**
 * A assinatura do modulo e o unico gate do `index()`. Sem neutraliza-la, todo cenario tomaria
 * 403 nessa linha e mediria a coisa errada.
 */
function alocacoesContratoAssinaturaLiberada(): void
{
    $moduleUtil = Mockery::mock(ModuleUtil::class)->makePartial();
    $moduleUtil->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $moduleUtil);
}

function alocacoesContratoGet(User $user, int $businessId, array $query = [])
{
    $url = '/asset/allocation'.($query ? '?'.http_build_query($query) : '');

    return test()
        ->actingAs($user)
        ->withSession([
            'user.business_id' => $businessId,
            'user' => ['business_id' => $businessId, 'id' => $user->id],
        ])
        ->get($url);
}

/**
 * Limpeza a mao em `try/finally`, NAO por `afterEach` encadeado: medido no CT 100 em
 * 2026-09-08 (thread 03), o gancho por teste nao executou e deixou fixtures orfaos numa base
 * que e clone de prod e nao se limpa entre runs. `finally` tambem limpa quando o assert falha.
 *
 * Ordem importa: `asset_transactions.parent_id` e FK self-referente com `onDelete cascade`, e
 * `asset_id` e FK->`assets`. As transacoes saem antes dos assets e dos users.
 */
function alocacoesContratoLimpar(): void
{
    app()->forgetInstance(ModuleUtil::class);

    AssetTransaction::where('ref_no', 'like', 'ALOC-CTR-%')->delete();
    Asset::where('asset_code', 'like', 'ALOC-CTR-%')->forceDelete();

    // `withTrashed`: App\User usa SoftDeletes — sem isto um fixture ja soft-deletado escaparia
    // da varredura e ficaria na base.
    $users = User::withTrashed()->where('username', 'like', 'aloc_contrato_%')->get();
    foreach ($users as $u) {
        DB::table('model_has_roles')->where('model_id', $u->id)->delete();
        $u->forceDelete();
    }
}

/**
 * Resolve a prop DEFERIDA `alocacoes` — o que a tela recebe no partial reload.
 *
 * Duas coisas foram MEDIDAS pela tela irma (Bens) neste mesmo ambiente, nao supostas:
 *
 * 1. A prop e `Inertia::defer`, entao ela NAO vem no primeiro render. Assertar sobre o
 *    `data-page` inicial mediria a ausencia dela, que e o comportamento correto do defer.
 * 2. O XHR do Inertia devolve 409 quando a versao do header nao bate com a que o middleware
 *    calculou (mesmo passando `Inertia::getVersion()`, resolvido FORA do ciclo da request).
 *    A versao boa e a que o proprio render acabou de emitir — ela vem no `page` da root view.
 *    Por isso o primeiro GET nao e desperdicio: ele e a fonte da versao.
 * 3. `flushHeaders()` NAO e enfeite. O test client do Laravel ACUMULA os headers passados em
 *    `withHeaders()` para todas as requisicoes seguintes da MESMA instancia de teste. Sem o
 *    flush, um cenario que chama esta helper DUAS vezes (UC-ALOC-03 e UC-ALOC-04 chamam) leva
 *    o `X-Inertia` da chamada anterior para o GET inicial da seguinte — que entao devolve o
 *    JSON do Inertia em vez da root view, e `viewData('page')` estoura com "The response is
 *    not a view". MEDIDO no CT 100 em 2026-09-08: exatamente os 2 cenarios de chamada dupla
 *    falhavam, e os 3 de chamada unica passavam.
 */
function alocacoesContratoPropDeferida(User $user, int $businessId, array $query = []): array
{
    test()->flushHeaders();

    $inicial = alocacoesContratoGet($user, $businessId, $query);

    expect($inicial->status())->toBe(200);

    $versao = data_get($inicial->viewData('page'), 'version');

    $url = '/asset/allocation'.($query ? '?'.http_build_query($query) : '');

    $parcial = test()
        ->actingAs($user)
        ->withSession([
            'user.business_id' => $businessId,
            'user' => ['business_id' => $businessId, 'id' => $user->id],
        ])
        ->withHeaders([
            // `X-Requested-With` NAO e decoracao: o cliente Inertia o manda
            // INCONDICIONALMENTE junto com `X-Inertia` (@inertiajs/core, `getHeaders()`).
            // Sem ele, este teste montava uma requisicao que o BROWSER NUNCA ENVIA — e por
            // isso ficava verde enquanto a tela, em producao, recebia o JSON do DataTables
            // e nunca renderizava a tabela. Mesma cegueira que o `BensContratoTest` tinha
            // ate o PR #7047.
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $versao,
            'X-Inertia-Partial-Component' => 'Patrimonio/Alocacoes',
            'X-Inertia-Partial-Data' => 'alocacoes',
        ])
        ->get($url);

    expect($parcial->status())->toBe(200);

    return collect(data_get($parcial->json(), 'props.alocacoes.data', []))
        ->pluck('ref_no')
        ->all();
}

it('UC-ALOC-02: a rota /asset/allocation devolve Inertia com o componente Patrimonio/Alocacoes', function () {
    $biz = $this->seededTenant();
    $user = alocacoesContratoUsuario((int) $biz->id);

    try {
        alocacoesContratoAssinaturaLiberada();

        alocacoesContratoGet($user, (int) $biz->id)
            ->assertStatus(200)
            ->assertInertia(
                fn ($page) => $page
                    ->component('Patrimonio/Alocacoes')
                    ->has('filtros')
                    ->has('permissoes')
            );
    } finally {
        alocacoesContratoLimpar();
    }
});

it('UC-ALOC-01: a listagem traz so as alocacoes do business do usuario', function () {
    $dono = $this->seededTenant();
    $adversario = $this->seededSupportClientTenant();

    $userDono = alocacoesContratoUsuario((int) $dono->id);
    $userAdv = alocacoesContratoUsuario((int) $adversario->id);

    try {
        alocacoesContratoAssinaturaLiberada();

        $bemDono = alocacoesContratoAsset((int) $dono->id, $userDono->id, 'ALOC-CTR-DONO', 'Furadeira do dono');
        $bemAdv = alocacoesContratoAsset((int) $adversario->id, $userAdv->id, 'ALOC-CTR-ADV', 'Furadeira do adversario');

        alocacoesContratoAlocacao((int) $dono->id, $bemDono->id, $userDono->id, 'ALOC-CTR-D1');
        alocacoesContratoAlocacao((int) $adversario->id, $bemAdv->id, $userAdv->id, 'ALOC-CTR-A1');

        // A busca casa o codigo das DUAS de proposito: elas disputam a mesma pagina, entao o
        // isolamento e a unica explicacao possivel para o resultado. Sem esse recorte, a
        // paginacao de 25 numa base persistente poderia excluir o fixture e o teste passaria
        // pelo motivo errado.
        $vistas = alocacoesContratoPropDeferida($userDono, (int) $dono->id, ['q' => 'ALOC-CTR-']);

        expect($vistas)->toContain('ALOC-CTR-D1');
        expect($vistas)->not->toContain('ALOC-CTR-A1');
    } finally {
        alocacoesContratoLimpar();
    }
});

it('UC-ALOC-01: o espelho — o adversario ve a alocacao dele e nao a do dono', function () {
    $dono = $this->seededTenant();
    $adversario = $this->seededSupportClientTenant();

    $userDono = alocacoesContratoUsuario((int) $dono->id);
    $userAdv = alocacoesContratoUsuario((int) $adversario->id);

    try {
        alocacoesContratoAssinaturaLiberada();

        $bemDono = alocacoesContratoAsset((int) $dono->id, $userDono->id, 'ALOC-CTR-DONO', 'Furadeira do dono');
        $bemAdv = alocacoesContratoAsset((int) $adversario->id, $userAdv->id, 'ALOC-CTR-ADV', 'Furadeira do adversario');

        alocacoesContratoAlocacao((int) $dono->id, $bemDono->id, $userDono->id, 'ALOC-CTR-D1');
        alocacoesContratoAlocacao((int) $adversario->id, $bemAdv->id, $userAdv->id, 'ALOC-CTR-A1');

        // Sozinho, o `not->toContain` do cenario acima tambem passaria se a alocacao do
        // adversario simplesmente nao existisse. O espelho mede o MESMO predicado pelo outro
        // lado — e substitui o bite-test por mutacao, que aqui significaria remover o filtro
        // de `business_id`, ou seja, desligar protecao Tier 0 pra ver o alarme tocar.
        $vistas = alocacoesContratoPropDeferida($userAdv, (int) $adversario->id, ['q' => 'ALOC-CTR-']);

        expect($vistas)->toContain('ALOC-CTR-A1');
        expect($vistas)->not->toContain('ALOC-CTR-D1');
    } finally {
        alocacoesContratoLimpar();
    }
});

it('UC-ALOC-03: o recorte por situacao acontece no servidor', function () {
    $biz = $this->seededTenant();
    $user = alocacoesContratoUsuario((int) $biz->id);

    try {
        alocacoesContratoAssinaturaLiberada();

        $bem = alocacoesContratoAsset((int) $biz->id, $user->id, 'ALOC-CTR-SIT', 'Furadeira de situacao');

        // Ativa: nada devolvido.
        alocacoesContratoAlocacao((int) $biz->id, $bem->id, $user->id, 'ALOC-CTR-ATIVA', 4.0);

        // Devolvida por inteiro: a filha cobre a quantidade toda.
        $total = alocacoesContratoAlocacao((int) $biz->id, $bem->id, $user->id, 'ALOC-CTR-VOLTOU', 3.0);
        alocacoesContratoDevolucao((int) $biz->id, $total, $user->id, 'ALOC-CTR-REV', 3.0);

        $ativas = alocacoesContratoPropDeferida($user, (int) $biz->id, [
            'q' => 'ALOC-CTR-',
            'situacao' => 'ativas',
        ]);

        expect($ativas)->toContain('ALOC-CTR-ATIVA');
        expect($ativas)->not->toContain('ALOC-CTR-VOLTOU');

        // O par invertido: uma das duas listas vazia satisfaria a negativa acima sozinha e o
        // teste passaria pelo motivo errado.
        $devolvidas = alocacoesContratoPropDeferida($user, (int) $biz->id, [
            'q' => 'ALOC-CTR-',
            'situacao' => 'devolvidas',
        ]);

        expect($devolvidas)->toContain('ALOC-CTR-VOLTOU');
        expect($devolvidas)->not->toContain('ALOC-CTR-ATIVA');
    } finally {
        alocacoesContratoLimpar();
    }
});

it('UC-ALOC-04: a busca recorta no servidor, pelo codigo e pelo nome do bem', function () {
    $biz = $this->seededTenant();
    $user = alocacoesContratoUsuario((int) $biz->id);

    try {
        alocacoesContratoAssinaturaLiberada();

        $furadeira = alocacoesContratoAsset((int) $biz->id, $user->id, 'ALOC-CTR-FUR', 'Furadeira de bancada');
        $notebook = alocacoesContratoAsset((int) $biz->id, $user->id, 'ALOC-CTR-NOTE', 'Notebook de campo');

        alocacoesContratoAlocacao((int) $biz->id, $furadeira->id, $user->id, 'ALOC-CTR-F1');
        alocacoesContratoAlocacao((int) $biz->id, $notebook->id, $user->id, 'ALOC-CTR-N1');

        // Pelo codigo da alocacao.
        $porCodigo = alocacoesContratoPropDeferida($user, (int) $biz->id, ['q' => 'ALOC-CTR-F1']);

        expect($porCodigo)->toContain('ALOC-CTR-F1');
        expect($porCodigo)->not->toContain('ALOC-CTR-N1');

        // Pelo nome do BEM — campo de outra tabela, que so entra porque o `join` esta na
        // query base. E o assert que quebra se alguem tirar `assets.name` do `where` da busca.
        $porBem = alocacoesContratoPropDeferida($user, (int) $biz->id, ['q' => 'Notebook de campo']);

        expect($porBem)->toContain('ALOC-CTR-N1');
        expect($porBem)->not->toContain('ALOC-CTR-F1');
    } finally {
        alocacoesContratoLimpar();
    }
});
