<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use App\User;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Escala;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato da REMOÇÃO de escala — `Escala::podeSerRemovida()`, o predicado que o
 * `EscalaController@destroy` consulta. Ver `Escalas/Index.casos.md` (UC-ESCIDX-04).
 *
 * `D-ESC-DESTROY` ([W] 2026-09-14): remover é permitido SÓ sem vínculo.
 *
 * ── POR QUE ISTO NÃO PASSA PELO HTTP, e a escolha é deliberada ──────────────────────────
 * O caso precisa de um `ponto_colaborador_config` VINCULADO à escala. Fabricar essa fixture no
 * tenant que o teste trata como real é proibido — o dono dela é o SEED (proibicoes §5 2026-08-24,
 * e é por isso que o `pest-mysql-setup` semeia uma pro biz 1). O tenant FICTÍCIO, criado pelo
 * próprio caso com marcador e limpeza, é livre — então é lá que o vínculo nasce.
 *
 * Só que `Escala` e `Colaborador` usam `HasBusinessScope`, que filtra por
 * `session('user.business_id')` — e o `actAsAdmin()` loga no PRIMEIRO business do banco (a
 * chamada literal não entra nem em comentário: o `foundation-ratchet` conta o contador
 * `n_business_first` por regex, sem distinguir menção de uso). Então o caso
 * ALINHA a sessão ao tenant fictício antes de medir, que é o que produção faz (a rota é servida ao
 * usuário do próprio empregador). Escapar do scope mediria uma query que produção nunca faz.
 *
 * O predicado foi EXTRAÍDO pro entity por isso: é a decisão de verdade e o caso exercita
 * `podeSerRemovida()`, que é exatamente o que o controller chama — não uma cópia paralela
 * (§5 2026-08-14). A 1ª versão deste arquivo errou o alinhamento de sessão e o caso FALHOU
 * apontando o erro; fica registrado porque foi o teste que pegou o teste.
 *
 * RESÍDUO DECLARADO (2026-09-14): este arquivo não provava o caminho HTTP (o redirect com a
 * mensagem de recusa), só o predicado que o decide. FECHADO em 2026-09-28 pelos 2 casos HTTP no
 * fim do arquivo (thread 27, R1 da ata): eles logam como o usuário do tenant 98 que o seed do CI
 * cria (o `FechamentoContratoTest` faz o mesmo), dentro de transação revertida, sem mexer no
 * `PontoTestCase`.
 *
 * Tier 0: o vínculo vive no biz fictício 99 (`garantirBizAlheio`), NUNCA biz=4 (ADR 0358).
 * Sem `RefreshDatabase` — a lane ponto-pest proíbe.
 *
 * @see \Modules\Ponto\Entities\Escala::podeSerRemovida
 * @see \Modules\Ponto\Http\Controllers\EscalaController::destroy
 */

const ESCREM_MARCA = 'SDD-ESCREM-CONTRATO';

function escRemPrecisaDe(array $tabelas): void
{
    foreach ($tabelas as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
        }
    }
}

function escRemCriarEscala(int $businessId, string $sufixo): int
{
    return DB::table('ponto_escalas')->insertGetId([
        'business_id'           => $businessId,
        'nome'                  => ESCREM_MARCA . '-' . $sufixo,
        'codigo'                => substr(ESCREM_MARCA . $sufixo, 0, 30),
        'tipo'                  => 'FIXA',
        'carga_diaria_minutos'  => 480,
        'carga_semanal_minutos' => 2640,
        'permite_banco_horas'   => 0,
        'ativo'                 => 1,
        'created_at'            => now(),
        'updated_at'            => now(),
    ]);
}

/** Usuário do tenant fictício — `ponto_colaborador_config.user_id` tem FK pra `users`. */
function escRemCriarUser(int $businessId): int
{
    return DB::table('users')->insertGetId([
        'business_id' => $businessId,
        'user_type'   => 'user',
        'surname'     => '',
        'first_name'  => ESCREM_MARCA,
        'email'       => strtolower(ESCREM_MARCA) . '@exemplo.invalid',
        'username'    => strtolower(ESCREM_MARCA),
        'password'    => bcrypt('x'),
        'language'    => 'pt_BR',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
}

/**
 * Alinha a SESSÃO ao tenant do fixture e lê a escala pelo caminho normal.
 *
 * Por que a sessão, e não `withoutGlobalScopes()`: `Colaborador` TAMBÉM usa `HasBusinessScope`, e
 * o scope filtra por `session('user.business_id')`. A 1ª versão deste arquivo criava o vínculo no
 * tenant fictício e media com a sessão do `actAsAdmin()` (outro tenant) — `vinculosAtivos()`
 * devolvia **0** com a linha gravada, e o caso falhou apontando pra isso. O defeito era do TESTE,
 * não do predicado: em produção a sessão é sempre a do tenant da escala, porque a rota é servida
 * ao usuário daquele empregador. Alinhar a sessão REPRODUZ isso; escapar do scope mediria uma
 * query que produção nunca faz.
 */
function escRemCarregar(int $id, int $businessId): Escala
{
    session(['user.business_id' => $businessId, 'business.id' => $businessId]);

    /** @var Escala $e */
    $e = Escala::findOrFail($id);
    return $e;
}

afterEach(function () {
    try {
        // Ordem importa: o vínculo aponta pra escala, e o user é pai do vínculo (FK cascade).
        DB::table('ponto_colaborador_config')->where('matricula', ESCREM_MARCA)->delete();
        DB::table('users')->where('first_name', ESCREM_MARCA)->delete();
        $ids = DB::table('ponto_escalas')->where('nome', 'like', ESCREM_MARCA . '%')->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('ponto_escala_turnos')->whereIn('escala_id', $ids)->delete();
            DB::table('ponto_escalas')->whereIn('id', $ids)->delete();
        }
    } catch (\Throwable $e) {
        // schema ausente — cleanup best-effort, igual aos irmãos do módulo.
    }
});

it('UC-ESCIDX-04 · escala COM colaborador vinculado não pode ser removida', function () {
    $this->actAsAdmin();
    escRemPrecisaDe(['ponto_escalas', 'ponto_colaborador_config', 'users']);

    $biz = $this->garantirBizAlheio();
    $idEscala = escRemCriarEscala($biz, 'em-uso');
    $idUser = escRemCriarUser($biz);

    DB::table('ponto_colaborador_config')->insert([
        'business_id'     => $biz,
        'user_id'         => $idUser,
        'matricula'       => ESCREM_MARCA,
        'escala_atual_id' => $idEscala,
        'admissao'        => now()->toDateString(),
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    // Pré-condição anti-vácuo: sem o vínculo de fato gravado, "não pode remover" seria verdade
    // por outro motivo e o caso não exerceria nada.
    expect(DB::table('ponto_colaborador_config')->where('escala_atual_id', $idEscala)->count())
        ->toBe(1, 'O vínculo tem de existir — senão o caso não exercita a trava.');

    $escala = escRemCarregar($idEscala, $biz);

    expect($escala->vinculosAtivos())->toBe(1, 'O predicado tem de VER o vínculo que acabou de ser gravado.');
    expect($escala->podeSerRemovida())->toBeFalse(
        'Escala em uso não pode ser removida: apagar deixaria `escala_atual_id` apontando pra linha '
        . 'morta, e a coluna não tem FK — o banco não segura. Escala define a jornada ESPERADA na '
        . 'apuração, então sem ela o cálculo de HE/intrajornada perde a referência (CLT Art. 58/59).'
    );

    $this->removerBizAlheio();
});

it('UC-ESCIDX-04 · escala SEM vínculo pode ser removida — a ponta positiva', function () {
    $this->actAsAdmin();
    escRemPrecisaDe(['ponto_escalas', 'ponto_colaborador_config']);

    $biz = $this->garantirBizAlheio();
    $idEscala = escRemCriarEscala($biz, 'livre');

    // Sem esta metade, um predicado que devolvesse `false` pra tudo passaria no caso de cima — e a
    // remoção ficaria impossível pra sempre, que é um defeito tão real quanto o inverso.
    expect(DB::table('ponto_colaborador_config')->where('escala_atual_id', $idEscala)->count())
        ->toBe(0, 'A escala tem de estar de fato livre pra esta metade significar algo.');

    $escala = escRemCarregar($idEscala, $biz);

    expect($escala->vinculosAtivos())->toBe(0);
    expect($escala->podeSerRemovida())->toBeTrue(
        'Escala sem vínculo é removível — é o caso normal de limpar regime antigo.'
    );

    $this->removerBizAlheio();
});

/*
 * ── GUARD HTTP de D-ESC-DESTROY (thread 27, R1 da ata 2026-09-14) ─────────────────────────────
 * Os 2 casos de cima provam o PREDICADO. Estes provam a ROTA: um `DELETE` direto, sem passar pelo
 * botão desabilitado da tela, contra escala em uso é recusado e a escala continua no banco.
 *
 * A thread 27 pedia `assertForbidden()` (403). O controller devolve redirect para a lista com flash
 * `error`, e é esse o comportamento que o UC-ESCIDX-04 defende. Pela regra de precedência
 * (teste > casos > charter), o guard afirma o que existe; trocar por 403 seria decisão nova de [W].
 * O redirect não é "302 silencioso": ele carrega o motivo, e o caso lê o motivo.
 *
 * O par positivo (sem vínculo → remove) é o que prova que a requisição CHEGOU ao controller. Sem
 * ele, uma recusa vinda de outra camada (middleware, permissão) deixaria o caso negativo verde por
 * vácuo (§5 2026-09-27).
 *
 * Tenant fictício 98 (ADR 0358), usuário semeado pelo pest-mysql-setup. Transação revertida por
 * caso. Nunca biz=4.
 */
const ESCREM_BIZ = 98;

function escRemHttpPreparar(): User
{
    escRemPrecisaDe(['ponto_escalas', 'ponto_colaborador_config', 'users', 'permissions']);
    if (! DB::table('business')->where('id', ESCREM_BIZ)->exists()) {
        test()->markTestSkipped('Tenant fictício 98 ausente — seed do pest-mysql-setup não rodou.');
    }
    $u = User::query()->where('business_id', ESCREM_BIZ)->first()
        ?? test()->markTestSkipped('Nenhum user no biz 98.');

    Permission::firstOrCreate(['name' => 'ponto.access', 'guard_name' => 'web']);
    $u->givePermissionTo('ponto.access');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    session(['user.business_id' => ESCREM_BIZ, 'business.id' => ESCREM_BIZ]);

    return $u->fresh();
}

it('UC-ESCIDX-04 · DELETE direto na rota de escala em uso é recusado e a escala fica', function () {
    DB::beginTransaction();
    try {
        $u = escRemHttpPreparar();
        $idEscala = escRemCriarEscala(ESCREM_BIZ, 'http-em-uso');
        DB::table('ponto_colaborador_config')->insert([
            'business_id'     => ESCREM_BIZ,
            'user_id'         => escRemCriarUser(ESCREM_BIZ),
            'matricula'       => ESCREM_MARCA,
            'escala_atual_id' => $idEscala,
            'admissao'        => now()->toDateString(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->actingAs($u)
            ->delete("/ponto/escalas/{$idEscala}")
            ->assertRedirect(route('ponto.escalas.index'))
            ->assertSessionHas('error', fn ($msg) => str_contains((string) $msg, 'vinculado'));

        expect(DB::table('ponto_escalas')->where('id', $idEscala)->exists())->toBeTrue(
            'Escala em uso não pode sumir pela rota: o botão desabilitado é conveniência, a trava é o '
            . 'servidor (D-ESC-DESTROY, [W] 2026-09-14 — perder a escala é perder a jornada esperada).'
        );
    } finally {
        DB::rollBack();
    }
});

it('UC-ESCIDX-04 · DELETE na rota de escala SEM vínculo remove — prova que o caso chega ao controller', function () {
    DB::beginTransaction();
    try {
        $u = escRemHttpPreparar();
        $idEscala = escRemCriarEscala(ESCREM_BIZ, 'http-livre');

        $this->actingAs($u)
            ->delete("/ponto/escalas/{$idEscala}")
            ->assertRedirect(route('ponto.escalas.index'))
            ->assertSessionHas('success');

        expect(DB::table('ponto_escalas')->where('id', $idEscala)->exists())->toBeFalse(
            'Sem vínculo a rota tem de remover — senão a recusa do caso de cima pode ter vindo de '
            . 'outra camada, e o guard estaria verde sem exercer a trava.'
        );
    } finally {
        DB::rollBack();
    }
});
