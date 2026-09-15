<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Escala;
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
 * `session('user.business_id')` — e o `actAsAdmin()` loga no `Business::first()`. Então o caso
 * ALINHA a sessão ao tenant fictício antes de medir, que é o que produção faz (a rota é servida ao
 * usuário do próprio empregador). Escapar do scope mediria uma query que produção nunca faz.
 *
 * O predicado foi EXTRAÍDO pro entity por isso: é a decisão de verdade e o caso exercita
 * `podeSerRemovida()`, que é exatamente o que o controller chama — não uma cópia paralela
 * (§5 2026-08-14). A 1ª versão deste arquivo errou o alinhamento de sessão e o caso FALHOU
 * apontando o erro; fica registrado porque foi o teste que pegou o teste.
 *
 * RESÍDUO DECLARADO: este arquivo não prova o caminho HTTP (o redirect com a mensagem de recusa).
 * Prova o predicado que o decide. Fechar o HTTP exigiria logar como admin do tenant fictício, o
 * que o `PontoTestCase` hoje não oferece — e inventar isso aqui seria mexer no TestCase de 20+
 * arquivos dentro de um PR de tela.
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
