<?php

declare(strict_types=1);

// Tests\TestCase ja e aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')).
// NAO redeclarar aqui — Pest 4 lanca TestCaseAlreadyInUse.

/**
 * Cadastro de comissionado UNIFICA em vez de duplicar (decisao [W] 2026-08-19).
 *
 * Antes: o store() sempre chamava User::create(), entao cadastrar como comissionado alguem que
 * JA era usuario do negocio abria uma SEGUNDA linha em `users` pra mesma pessoa. Agora ele
 * procura o usuario do mesmo negocio pelo e-mail e MARCA `is_cmmsn_agnt` na linha existente.
 *
 * O escopo aqui e SO o caminho NOVO. O dedupe do legado foi deliberadamente adiado por [W]:
 * casar linhas de `users` por e-mail em base de producao funde gente errada quando o e-mail
 * esta vazio ou repetido — e biz=4 (ROTA LIVRE) esta viva. Os dois casos abaixo que provam
 * "e-mail vazio nao casa" e "e-mail repetido nao casa" sao exatamente essa fronteira: eles
 * garantem que o caminho novo nao reintroduz, por dentro, o dedupe que foi adiado.
 *
 * POR QUE OS CASOS NEGATIVOS NAO PASSAM PELO MOTIVO ERRADO: em todos eles o comportamento
 * esperado e "criou uma linha nova". Ou seja, a propria assercao (`+1` em users) e a prova de
 * que a requisicao chegou ao controller e executou — nao existe a leitura "passou porque nada
 * aconteceu". Foi assim que o SalesCommissionAgentGuardTest quase ficou verde provando nada.
 */

use App\User;

/**
 * Tenant canonico de teste = 98 (ADR 0358 / CLAUDE.md R6).
 *
 * Nomes proprios (prefixo CA_STORE_ / caStore*) de proposito: o Pest carrega TODOS os arquivos
 * de teste no MESMO processo, e o SalesCommissionAgentGuardTest ao lado ja declara
 * `const TENANT_TESTE` e as funcoes `agenteDoNegocio`/`operadorQuePodeExcluir` no escopo do
 * arquivo. Reusar os mesmos nomes aqui seria redeclaracao — erro fatal de PHP, nao falha de
 * teste, e derrubaria a lane inteira.
 */
const CA_STORE_TENANT = 98;
const CA_STORE_TENANT_OUTRO = 2;

/**
 * Cabecalho que a TELA REAL envia (o modal de cadastro submete por ajax).
 *
 * O store() NAO ramifica em `request()->ajax()` — diferente do destroy(), que embrulha o corpo
 * inteiro nisso. Mandar o cabecalho aqui e fidelidade a tela, nao pre-requisito: se um dia o
 * store() ganhar o mesmo embrulho, este teste continua exercitando o caminho de verdade.
 */
function caStoreHeaders(): array
{
    return ['X-Requested-With' => 'XMLHttpRequest'];
}

/**
 * Usuario comum do negocio: existe, NAO e comissionado e PODE logar.
 *
 * `allow_login` e `is_cmmsn_agnt` vao EXPLICITOS de proposito, e nao por default do banco.
 * `Model::create()` nao rele a linha depois do INSERT: a instancia devolvida so carrega os
 * atributos que foram gravados, entao coluna deixada por conta do DEFAULT chega ao teste como
 * `null` — e `(int) null` e 0, `(bool) null` e false. As duas sanidades daqui passariam (ou
 * falhariam) medindo ATRIBUTO AUSENTE em vez do estado real da linha. Foi assim que o primeiro
 * run acusou `allow_login = 0` numa coluna cujo DEFAULT no banco e 1.
 *
 * Alem de consertar a medicao, o valor explicito descreve o caso que importa: quem ja pode
 * logar e o unico que tem login a PERDER se o store() aplicar o `allow_login = 0` da criacao.
 */
function caStoreUsuarioComum(int $businessId, ?string $email): User
{
    return User::factory()->create([
        'business_id' => $businessId,
        'email' => $email,
        'allow_login' => 1,
        'is_cmmsn_agnt' => 0,
    ]);
}

function caStoreOperador(int $businessId): User
{
    $user = User::factory()->create(['business_id' => $businessId]);

    $papel = \Spatie\Permission\Models\Role::create([
        'name' => 'OperadorCAStore'.uniqid().'#'.$businessId,
        'business_id' => $businessId,
        'guard_name' => 'web',
    ]);
    \Spatie\Permission\Models\Permission::findOrCreate('user.create', 'web');
    $papel->syncPermissions(['user.create']);
    $user->assignRole($papel);

    // Mesma correcao do RoleTenantIsolationTest e do GuardTest ao lado: sem limpar o cache de
    // permissoes do Spatie e sem reler o usuario, o can() responde com o retrato anterior e o
    // controller aborta 403 — e ai "nenhuma linha nova" tambem seria verdade, pelo motivo errado.
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

/**
 * Payload do formulario de cadastro (create.blade.php).
 *
 * `cmmsn_percent` vai em pt-BR ('7,50') de proposito: o store() passa o campo por
 * `Util::num_uf` antes de gravar, e esse parsing NAO foi tocado nesta mudanca. Mandar o valor
 * na forma que a tela manda mantem o caminho de calculo sob teste — o que muda aqui e QUAL
 * LINHA recebe o percentual, nunca como ele e lido.
 */
function caStorePayload(array $sobrescreve = []): array
{
    return array_merge([
        'surname' => 'Sr',
        'first_name' => 'Comissionado',
        'last_name' => 'Novo',
        'email' => 'ca-store-'.uniqid().'@exemplo.test',
        'address' => 'Rua de Teste, 10',
        'contact_no' => '48999990000',
        'cmmsn_percent' => '7,50',
    ], $sobrescreve);
}

function caStoreEmailUnico(string $prefixo): string
{
    return $prefixo.'-'.uniqid().'@exemplo.test';
}

/** Quantos usuarios VIVOS o negocio tem agora (o escopo padrao ja exclui soft-deleted). */
function caStoreTotalUsuarios(int $businessId): int
{
    return User::where('business_id', $businessId)->count();
}

it('marca o usuario que JA existe em vez de criar uma segunda linha', function () {
    $email = caStoreEmailUnico('ja-existe');
    $existente = caStoreUsuarioComum(CA_STORE_TENANT, $email);

    // SANIDADE do ponto de partida — se o usuario ja nascesse comissionado, o caso passaria
    // sem que o controller tivesse feito nada.
    expect((bool) $existente->is_cmmsn_agnt)->toBeFalse();
    expect((int) $existente->allow_login)->toBe(1);

    $operador = caStoreOperador(CA_STORE_TENANT);
    expect($operador->can('user.create'))->toBeTrue();

    $this->actingAs($operador);
    session(['user.business_id' => CA_STORE_TENANT]);

    $antes = caStoreTotalUsuarios(CA_STORE_TENANT);

    $resposta = $this->withHeaders(caStoreHeaders())
        ->postJson('/sales-commission-agents', caStorePayload(['email' => $email]));

    $resposta->assertOk();
    $resposta->assertJson(['success' => true]);

    // O CORACAO DA TASK: nenhuma linha nova em `users`.
    expect(caStoreTotalUsuarios(CA_STORE_TENANT))->toBe($antes);

    $existente->refresh();
    expect((bool) $existente->is_cmmsn_agnt)->toBeTrue();
    expect((float) $existente->cmmsn_percent)->toBe(7.5);

    // NAO stompar o login: a criacao grava allow_login=0 (agente novo nao loga), mas aplicar
    // isso a um usuario que ja existe tiraria o acesso dele.
    expect((int) $existente->allow_login)->toBe(1);

    // Mensagem propria — prova QUAL RAMO rodou (unificou, nao criou) e avisa o operador de que
    // a lista vai mostrar o nome que a pessoa ja tinha.
    expect($resposta->json('msg'))->toBe(__('lang_v1.commission_agent_linked_success'));
});

it('nao sobrescreve o cadastro do usuario existente com o que foi digitado', function () {
    $email = caStoreEmailUnico('nome-preservado');
    $existente = caStoreUsuarioComum(CA_STORE_TENANT, $email);
    $existente->update(['first_name' => 'Ana', 'last_name' => 'Original', 'contact_no' => '48911112222']);

    $operador = caStoreOperador(CA_STORE_TENANT);
    $this->actingAs($operador);
    session(['user.business_id' => CA_STORE_TENANT]);

    $this->withHeaders(caStoreHeaders())
        ->postJson('/sales-commission-agents', caStorePayload([
            'email' => $email,
            'first_name' => 'Nome',
            'last_name' => 'Digitado',
            'contact_no' => '48900000000',
        ]))
        ->assertOk();

    $existente->refresh();
    // O papel entrou (prova de que o ramo de unificacao rodou)...
    expect((bool) $existente->is_cmmsn_agnt)->toBeTrue();
    // ...e o cadastro da pessoa ficou intacto. Quem cadastra comissionado nao esta editando o
    // cadastro dela; sobrescrever seria perda silenciosa.
    expect($existente->first_name)->toBe('Ana');
    expect($existente->last_name)->toBe('Original');
    expect($existente->contact_no)->toBe('48911112222');
});

it('sem usuario com aquele e-mail, cria como antes', function () {
    $operador = caStoreOperador(CA_STORE_TENANT);
    $this->actingAs($operador);
    session(['user.business_id' => CA_STORE_TENANT]);

    $email = caStoreEmailUnico('inedito');
    $antes = caStoreTotalUsuarios(CA_STORE_TENANT);

    $resposta = $this->withHeaders(caStoreHeaders())
        ->postJson('/sales-commission-agents', caStorePayload(['email' => $email]));

    $resposta->assertOk();
    expect(caStoreTotalUsuarios(CA_STORE_TENANT))->toBe($antes + 1);

    $criado = User::where('business_id', CA_STORE_TENANT)->where('email', $email)->firstOrFail();
    expect((bool) $criado->is_cmmsn_agnt)->toBeTrue();
    expect((float) $criado->cmmsn_percent)->toBe(7.5);
    // Comportamento de hoje preservado: agente criado por esta tela nao loga.
    expect((int) $criado->allow_login)->toBe(0);
    expect($resposta->json('msg'))->toBe(__('lang_v1.commission_agent_added_success'));
});

it('e-mail VAZIO nao casa com ninguem — cria, em vez de fundir dois desconhecidos', function () {
    // `users.email` e nullable e o formulario nao exige e-mail, entao usuario sem e-mail e um
    // estado normal do banco. Casar por vazio juntaria duas pessoas diferentes — e essa e
    // exatamente a fusao errada que [W] adiou no legado.
    $semEmailA = caStoreUsuarioComum(CA_STORE_TENANT, null);
    $semEmailB = caStoreUsuarioComum(CA_STORE_TENANT, '');

    $operador = caStoreOperador(CA_STORE_TENANT);
    $this->actingAs($operador);
    session(['user.business_id' => CA_STORE_TENANT]);

    $antes = caStoreTotalUsuarios(CA_STORE_TENANT);

    $this->withHeaders(caStoreHeaders())
        ->postJson('/sales-commission-agents', caStorePayload(['email' => '']))
        ->assertOk();

    // Criou (e este `+1` e a prova de que o controller executou — sem ele, "ninguem foi
    // marcado" tambem seria verdade com a requisicao barrada por 403).
    expect(caStoreTotalUsuarios(CA_STORE_TENANT))->toBe($antes + 1);

    expect((bool) $semEmailA->refresh()->is_cmmsn_agnt)->toBeFalse();
    expect((bool) $semEmailB->refresh()->is_cmmsn_agnt)->toBeFalse();
});

it('e-mail REPETIDO no negocio e ambiguo — cria, em vez de adivinhar qual pessoa e', function () {
    // `users` tem UNIQUE so em `username`; duplicata de e-mail e um estado possivel do banco.
    // Escolher um dos dois seria o dedupe automatico adiado por [W].
    $email = caStoreEmailUnico('duplicado');
    $primeiro = caStoreUsuarioComum(CA_STORE_TENANT, $email);
    $segundo = caStoreUsuarioComum(CA_STORE_TENANT, $email);

    $operador = caStoreOperador(CA_STORE_TENANT);
    $this->actingAs($operador);
    session(['user.business_id' => CA_STORE_TENANT]);

    $antes = caStoreTotalUsuarios(CA_STORE_TENANT);

    $this->withHeaders(caStoreHeaders())
        ->postJson('/sales-commission-agents', caStorePayload(['email' => $email]))
        ->assertOk();

    expect(caStoreTotalUsuarios(CA_STORE_TENANT))->toBe($antes + 1);

    expect((bool) $primeiro->refresh()->is_cmmsn_agnt)->toBeFalse();
    expect((bool) $segundo->refresh()->is_cmmsn_agnt)->toBeFalse();
});

it('usuario de OUTRO negocio com o mesmo e-mail nao e alcancado (Tier 0)', function () {
    // ADR 0093: o casamento e escopado por business_id. Sem isso, cadastrar um comissionado
    // marcaria o papel — e gravaria um percentual de comissao — na linha de outro tenant.
    $email = caStoreEmailUnico('cross-tenant');
    $alheio = caStoreUsuarioComum(CA_STORE_TENANT_OUTRO, $email);

    $operador = caStoreOperador(CA_STORE_TENANT);
    $this->actingAs($operador);
    session(['user.business_id' => CA_STORE_TENANT]);

    $antes = caStoreTotalUsuarios(CA_STORE_TENANT);

    $this->withHeaders(caStoreHeaders())
        ->postJson('/sales-commission-agents', caStorePayload(['email' => $email]))
        ->assertOk();

    // Criou no negocio da sessao...
    expect(caStoreTotalUsuarios(CA_STORE_TENANT))->toBe($antes + 1);
    $criado = User::where('business_id', CA_STORE_TENANT)->where('email', $email)->firstOrFail();
    expect((bool) $criado->is_cmmsn_agnt)->toBeTrue();

    // ...e o alheio nao foi tocado, nem no papel nem no percentual.
    $alheio->refresh();
    expect((bool) $alheio->is_cmmsn_agnt)->toBeFalse();
    expect((float) $alheio->cmmsn_percent)->toBe(0.0);
});
