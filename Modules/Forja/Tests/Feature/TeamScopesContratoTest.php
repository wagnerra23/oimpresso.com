<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Forja\Services\UserScopeService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato da tela /ads/admin/team-scopes — escopo de escrita por dev × módulo.
 *
 * UC-TSCOPE-01 — conceder escrita num módulo libera aquele módulo no servidor, e só ele.
 * UC-TSCOPE-02 — sem concessão de escrita, o servidor nega (DENY por padrão).
 * UC-TSCOPE-03 — revogar retira o acesso no servidor.
 *
 * Os UC derivam do charter (`TeamScopes.charter.md`), da US-COPI-079 (Jana/SPEC, demo
 * Maiara), do `memory/requisitos/Forja/SCOPE.md` e da ADR 0053 — nunca do `.tsx`.
 * Trio: Modules/Forja/Resources/js/Pages/ads/Admin/{TeamScopes.charter.md,TeamScopes.casos.md}
 *
 * O assert mede o EFEITO no servidor — `UserScopeService::canWriteToPath()`, o mesmo
 * método que o WriteFileTool consulta antes de escrever —, não a linha da tabela. A linha
 * só aparece como pré-condição anti-vácuo (prova que o POST de fato executou).
 *
 * Tier 0 (ADR 0093 + ADR 0358): usuários nascem no tenant fictício 98. NUNCA biz=4.
 * `DatabaseTransactions`: os casos criam usuários e concessões — sem rollback fica
 * resíduo no banco persistente da lane/CT 100 (proibicoes §Ambiente, emenda 2026-09-18).
 *
 * @see Modules/Forja/Http/Controllers/Admin/TeamScopesController.php
 * @see Modules/Forja/Services/UserScopeService.php
 */

const TSCOPE_PATH_COMPRAS = 'Modules/Compras/Http/Controllers/PedidoController.php';
const TSCOPE_PATH_NFE = 'Modules/NfeBrasil/Services/EmissaoService.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a stack UltimatePOS das rotas /ads exige schema MySQL.');
    }
    foreach (['users', 'business', 'mcp_user_module_access'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — rode com DB_CONNECTION=mysql e o schema baseline.");
        }
    }
});

/** Usuário novo no tenant canônico de teste (98), apto a logar. */
function tscopeUsuario(int $businessId, string $rotulo): User
{
    return User::factory()->create([
        'business_id' => $businessId,
        'username' => 'tscope_'.$rotulo.'_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
}

/** POST numa rota de team-scopes autenticado como o operador, no tenant da sessão. */
function tscopePost(User $operador, int $businessId, string $acao, array $dados)
{
    return test()
        ->actingAs($operador)
        ->withSession([
            'user.business_id' => $businessId,
            'user' => ['business_id' => $businessId, 'id' => $operador->id],
        ])
        ->post('/ads/admin/team-scopes/'.$acao, $dados);
}

it('UC-TSCOPE-01 — conceder escrita em Compras libera Compras no servidor e NfeBrasil segue negado', function () {
    $biz = (int) $this->seededTenant()->id;
    $operador = tscopeUsuario($biz, 'op');
    $dev = tscopeUsuario($biz, 'dev');
    $scope = app(UserScopeService::class);

    // Pré-condição: antes da concessão o servidor nega (senão o "libera" abaixo não mede nada).
    expect($scope->canWriteToPath($dev->id, TSCOPE_PATH_COMPRAS))->toBeFalse();

    tscopePost($operador, $biz, 'grant', [
        'user_id' => $dev->id,
        'module' => 'Compras',
        'can_read' => true,
        'can_write' => true,
    ])->assertSessionHasNoErrors();

    expect($scope->canWriteToPath($dev->id, TSCOPE_PATH_COMPRAS))->toBeTrue();
    // Controle: o módulo NÃO concedido continua negado — um serviço que liberasse tudo cai aqui.
    expect($scope->canWriteToPath($dev->id, TSCOPE_PATH_NFE))->toBeFalse();
});

it('UC-TSCOPE-02 — sem concessão de escrita o servidor nega, inclusive com concessão só-leitura', function () {
    $biz = (int) $this->seededTenant()->id;
    $operador = tscopeUsuario($biz, 'op');
    $semNada = tscopeUsuario($biz, 'semnada');
    $soLeitura = tscopeUsuario($biz, 'leitura');
    $scope = app(UserScopeService::class);

    // (a) dev sem linha nenhuma: DENY por padrão.
    expect(DB::table('mcp_user_module_access')->where('user_id', $semNada->id)->count())->toBe(0);
    expect($scope->canWriteToPath($semNada->id, TSCOPE_PATH_COMPRAS))->toBeFalse();

    // (b) concessão só-leitura. Pré-condição anti-vácuo: a linha TEM de existir — sem ela,
    // o "segue negado" abaixo passaria igual com um POST que não executou.
    tscopePost($operador, $biz, 'grant', [
        'user_id' => $soLeitura->id,
        'module' => 'Compras',
        'can_read' => true,
    ])->assertSessionHasNoErrors();

    $linha = DB::table('mcp_user_module_access')
        ->where('user_id', $soLeitura->id)
        ->where('module', 'Compras')
        ->first();
    expect($linha)->not->toBeNull();
    expect((bool) $linha->can_read)->toBeTrue();

    expect($scope->canWriteToPath($soLeitura->id, TSCOPE_PATH_COMPRAS))->toBeFalse();
});

it('UC-TSCOPE-03 — revogar retira a escrita no servidor', function () {
    $biz = (int) $this->seededTenant()->id;
    $operador = tscopeUsuario($biz, 'op');
    $dev = tscopeUsuario($biz, 'dev');
    $scope = app(UserScopeService::class);

    tscopePost($operador, $biz, 'grant', [
        'user_id' => $dev->id,
        'module' => 'Compras',
        'can_read' => true,
        'can_write' => true,
    ])->assertSessionHasNoErrors();

    // Pré-condição: liberou de fato — o "negado depois" não pode ser o estado inicial disfarçado.
    expect($scope->canWriteToPath($dev->id, TSCOPE_PATH_COMPRAS))->toBeTrue();

    tscopePost($operador, $biz, 'revoke', [
        'user_id' => $dev->id,
        'module' => 'Compras',
    ])->assertSessionHasNoErrors();

    expect($scope->canWriteToPath($dev->id, TSCOPE_PATH_COMPRAS))->toBeFalse();
    expect(DB::table('mcp_user_module_access')
        ->where('user_id', $dev->id)
        ->where('module', 'Compras')
        ->exists())->toBeFalse();
});
