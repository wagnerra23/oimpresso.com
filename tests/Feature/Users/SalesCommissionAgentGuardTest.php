<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse.

/**
 * Guarda de exclusão do comissionado — por VÍNCULO DE DADO, não por regra de negócio.
 *
 * `transactions.commission_agent` guarda o id do usuário e NÃO tem FK
 * (migration 2018_02_26_134500_add_commission_agent_to_transactions_table). Nada no banco impede
 * a venda de ficar apontando para um agente que saiu da listagem — e aí o nome dele some dos
 * relatórios sem que ninguém tenha decidido isso.
 *
 * Correção de rota sobre o pedido de origem, que chamava o destroy() de "hard delete": App\User
 * usa SoftDeletes, então o registro era recuperável. O dano real era menor do que o descrito,
 * mas existia — soft-delete tira o usuário das consultas normais do mesmo jeito.
 *
 * Usa firstOrFail() no seed em vez de markTestSkipped: se a lane não tiver location/contact, o
 * caso deve ERRAR (visível) e não PULAR (skip sai exit 0 — LC-13).
 */

use App\BusinessLocation;
use App\Contact;
/**
 * Tenant canonico de teste = 98 (ADR 0358 / CLAUDE.md R6). Nao e detalhe de estilo: a action
 * pest-mysql-setup so semeia business_locations e contacts para o 98 — em biz=1 o firstOrFail
 * da venda estoura com 'No query results for model [App\BusinessLocation]', que foi como
 * este teste falhou no primeiro run da lane.
 */
const TENANT_TESTE = 98;
const TENANT_OUTRO = 2;

use App\Transaction;
use App\User;
use Carbon\Carbon;

/**
 * DELETE com o header que a TELA REAL envia.
 *
 * O destroy() do UltimatePOS embrulha o corpo INTEIRO em `if (request()->ajax())`, e
 * `ajax()` testa o header `X-Requested-With: XMLHttpRequest`. O `deleteJson()` do Pest manda
 * `Accept: application/json` mas NAO manda esse header — entao o metodo caia no fim, devolvia
 * null e a resposta era 200 VAZIA, sem executar nada.
 *
 * Foi assim que os casos NEGATIVOS passaram pelo motivo errado: 'o agente continuou marcado' e
 * 'o alheio nao foi tocado' sao verdade quando o controller nem roda. Sem o caso POSITIVO ao
 * lado, este arquivo teria ficado verde provando nada.
 */
function ajaxDeleteHeaders(): array
{
    return ['X-Requested-With' => 'XMLHttpRequest'];
}

function agenteDoNegocio(int $businessId): User
{
    return User::factory()->create([
        'business_id' => $businessId,
        'is_cmmsn_agnt' => 1,
        'cmmsn_percent' => 5,
    ]);
}

/**
 * Operador com papel PROPRIO do negocio e SO as permissoes pedidas.
 *
 * O papel NAO pode ser `Admin#{business_id}`: `Gate::before` (AuthServiceProvider) libera
 * qualquer ability pra admin, e todo 403 esperado aqui viraria falso-verde.
 *
 * @param  array<int,string>  $permissoes
 */
function operadorComPermissoes(int $businessId, array $permissoes): User
{
    $user = User::factory()->create(['business_id' => $businessId]);

    $papel = \Spatie\Permission\Models\Role::create([
        'name' => 'OperadorCA'.uniqid().'#'.$businessId,
        'business_id' => $businessId,
        'guard_name' => 'web',
    ]);
    foreach ($permissoes as $permissao) {
        \Spatie\Permission\Models\Permission::findOrCreate($permissao, 'web');
    }
    $papel->syncPermissions($permissoes);
    $user->assignRole($papel);

    // Mesma correcao do RoleTenantIsolationTest (ja em main): sem limpar o cache de
    // permissoes do Spatie e sem reler o usuario, o can() responde com o retrato anterior
    // e o controller aborta 403 — e ai os casos NEGATIVOS passam pelo MOTIVO ERRADO,
    // porque 'nada foi criado' tambem e verdade quando a requisicao nem chega.
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

/** Quem administra comissionado. Desde 2026-08-20 isto NAO e mais `user.delete`. */
function operadorQuePodeExcluir(int $businessId): User
{
    return operadorComPermissoes($businessId, ['commission_agent.manage']);
}

function vendaDoAgente(int $businessId, User $agente): Transaction
{
    $location = BusinessLocation::where('business_id', $businessId)->firstOrFail();
    $contact = Contact::where('business_id', $businessId)->where('type', '!=', 'lead')->firstOrFail();

    return Transaction::create([
        'business_id' => $businessId,
        'location_id' => $location->id,
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'due',
        'contact_id' => $contact->id,
        'transaction_date' => Carbon::now()->toDateTimeString(),
        'final_total' => 100.0000,
        'total_before_tax' => 100.0000,
        'created_by' => $agente->id,
        'commission_agent' => $agente->id,
        'invoice_no' => 'CA-'.uniqid(),
    ]);
}

it('BLOQUEIA a exclusão de comissionado com venda vinculada, e diz quantas', function () {
    $agente = agenteDoNegocio(TENANT_TESTE);
    vendaDoAgente(TENANT_TESTE, $agente);

    $operador = operadorQuePodeExcluir(TENANT_TESTE);
    // SANIDADE — o run anterior devolveu 200 SEM efeito nos dois casos, e o unico caminho do
    // controller que faz isso e `empty($agente)`: a query nao acha o comissionado. Estes dois
    // expects separam as causas numa unica rodada — se o primeiro falhar, o DADO esta errado
    // (is_cmmsn_agnt/business_id nao gravou como esperado); se ele passar e o request ainda nao
    // tiver efeito, o controller esta vendo OUTRO business_id que o da sessao que eu setei.
    expect(User::where('id', $agente->id)
        ->where('business_id', TENANT_TESTE)
        ->where('is_cmmsn_agnt', 1)
        ->exists())->toBeTrue();
    expect($operador->can('commission_agent.manage'))->toBeTrue();

    $this->actingAs($operador);
    session(['user.business_id' => TENANT_TESTE]);

    // 422 = a guarda respondeu. Sem assertar STATUS, "o agente continuou marcado" tambem seria
    // verdade se a requisicao fosse barrada por 403 — foi assim que o RoleTenantIsolationTest
    // quase passou pelo motivo errado nesta mesma sessao.
    $this->withHeaders(ajaxDeleteHeaders())->deleteJson('/sales-commission-agents/'.$agente->id)->assertStatus(422);

    $agente->refresh();
    expect((bool) $agente->is_cmmsn_agnt)->toBeTrue();
    expect($agente->deleted_at)->toBeNull();
});

it('sem venda vinculada, DESMARCA o papel em vez de excluir o usuário', function () {
    $agente = agenteDoNegocio(TENANT_TESTE);

    $operador = operadorQuePodeExcluir(TENANT_TESTE);
    // SANIDADE — o run anterior devolveu 200 SEM efeito nos dois casos, e o unico caminho do
    // controller que faz isso e `empty($agente)`: a query nao acha o comissionado. Estes dois
    // expects separam as causas numa unica rodada — se o primeiro falhar, o DADO esta errado
    // (is_cmmsn_agnt/business_id nao gravou como esperado); se ele passar e o request ainda nao
    // tiver efeito, o controller esta vendo OUTRO business_id que o da sessao que eu setei.
    expect(User::where('id', $agente->id)
        ->where('business_id', TENANT_TESTE)
        ->where('is_cmmsn_agnt', 1)
        ->exists())->toBeTrue();
    expect($operador->can('commission_agent.manage'))->toBeTrue();

    $this->actingAs($operador);
    session(['user.business_id' => TENANT_TESTE]);

    // 200 = chegou ao controller e o caminho feliz respondeu (a rota devolve o array $output).
    $this->withHeaders(ajaxDeleteHeaders())->deleteJson('/sales-commission-agents/'.$agente->id)->assertOk();

    // withTrashed porque o comportamento ANTIGO era delete() (soft): se voltar a ser,
    // o refresh() puro não acharia a linha e o teste falharia por motivo errado.
    $depois = User::withTrashed()->findOrFail($agente->id);
    expect((bool) $depois->is_cmmsn_agnt)->toBeFalse();
    expect($depois->deleted_at)->toBeNull();
});

it('comissionado de outro negócio não é alcançado', function () {
    $alheio = agenteDoNegocio(TENANT_OUTRO);

    $operador = operadorQuePodeExcluir(TENANT_TESTE);
    $this->actingAs($operador);
    session(['user.business_id' => TENANT_TESTE]);

    $this->withHeaders(ajaxDeleteHeaders())->deleteJson('/sales-commission-agents/'.$alheio->id);

    $depois = User::withTrashed()->findOrFail($alheio->id);
    expect((bool) $depois->is_cmmsn_agnt)->toBeTrue();
    expect($depois->deleted_at)->toBeNull();
});

/**
 * ---------------------------------------------------------------------------------------
 * DESACOPLAMENTO de `user.*` (decisao [W] 2026-08-19, aplicado em 2026-08-20).
 *
 * Ate aqui, apurar comissao exigia permissao sobre o cadastro de USUARIOS do negocio. Os dois
 * casos abaixo sao as duas metades da mesma prova, e nenhum sozinho basta:
 *   - o POSITIVO (acima, via operadorQuePodeExcluir) mostra que `commission_agent.manage` BASTA;
 *   - o NEGATIVO (aqui) mostra que `user.*` NAO basta mais — sem ele, "funciona" tambem seria
 *     verdade se o controller tivesse ficado aceitando as duas familias.
 * ---------------------------------------------------------------------------------------
 */
it('quem só tem user.* NÃO alcança mais a tela de comissionados', function () {
    $agente = agenteDoNegocio(TENANT_TESTE);

    // O conjunto INTEIRO que abria a tela antes da troca — nao so `user.delete`.
    $operador = operadorComPermissoes(TENANT_TESTE, ['user.view', 'user.create', 'user.update', 'user.delete']);

    // SANIDADE dos dois lados: se a segunda expect falhasse, o 403 abaixo poderia vir de o
    // papel ter ganho `commission_agent.manage` sem querer — e o caso passaria sem provar nada.
    expect($operador->can('user.delete'))->toBeTrue();
    expect($operador->can('commission_agent.manage'))->toBeFalse();
    expect($operador->can('commission_agent.view'))->toBeFalse();

    $this->actingAs($operador);
    session(['user.business_id' => TENANT_TESTE]);

    $this->withHeaders(ajaxDeleteHeaders())->getJson('/sales-commission-agents')->assertForbidden();
    $this->withHeaders(ajaxDeleteHeaders())->deleteJson('/sales-commission-agents/'.$agente->id)->assertForbidden();

    $agente->refresh();
    expect((bool) $agente->is_cmmsn_agnt)->toBeTrue();
});

it('commission_agent.view LÊ a lista mas não desmarca ninguém', function () {
    $agente = agenteDoNegocio(TENANT_TESTE);

    $leitor = operadorComPermissoes(TENANT_TESTE, ['commission_agent.view']);
    expect($leitor->can('commission_agent.manage'))->toBeFalse();

    $this->actingAs($leitor);
    session(['user.business_id' => TENANT_TESTE]);

    // Header ajax de proposito: o index() so devolve JSON nesse ramo. Sem ele a rota
    // renderizaria a blade e um erro de view viraria falha por motivo errado.
    $this->withHeaders(ajaxDeleteHeaders())->getJson('/sales-commission-agents')->assertOk();

    $this->withHeaders(ajaxDeleteHeaders())->deleteJson('/sales-commission-agents/'.$agente->id)->assertForbidden();

    $agente->refresh();
    expect((bool) $agente->is_cmmsn_agnt)->toBeTrue();
});
