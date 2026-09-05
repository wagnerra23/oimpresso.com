<?php

namespace Modules\Ponto\Tests\Feature;

use App\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Autorizacao Spatie nas rotas do Ponto — R-PONT-002 a R-PONT-006 (SPEC PontoWr2 §R).
 *
 * ── POR QUE ESTE ARQUIVO FOI REESCRITO (2026-09-04) ────────────────────────────
 * A versao anterior era FLAKY na lane required `PHP / Pest (Ponto · MySQL)`: os
 * mesmos 5 data sets reprovavam numa run e passavam na seguinte, com o codigo de
 * producao BYTE-IDENTICO. Medido nas runs 33926131550 (FAILURE) e 33927425547
 * (SUCCESS) — 904 assertions e 274 casos nas DUAS, entao ambas executaram (LC-13).
 *
 * A causa NAO era `->first()` sorteando entre usuarios. Era a CARDINALIDADE do
 * conjunto virar ZERO, e ela dependia da ORDEM DOS ARQUIVOS:
 *
 *   1. `phpunit.xml:7` declara `executionOrder="random"` (o rodape do Pest imprime
 *      `Random Order Seed: …`), entao a posicao deste arquivo muda a cada run.
 *      MEDIDO: na run vermelha ele foi o 3o de 37; na verde, o 25o de 37.
 *   2. O seed do CI (.github/actions/pest-mysql-setup) cria UM user por business.
 *      Quem povoa `biz=1` com usuarios SEM papel sao os `*ContratoTest`, via
 *      `User::factory()` — e so se eles rodarem ANTES deste arquivo.
 *   3. Rodando cedo, `whereDoesntHave('roles', 'Admin#%')->first()` devolvia NULL
 *      e o teste caia num fallback que usava o proprio admin, revogando a
 *      PERMISSION e nunca o PAPEL.
 *   4. `Gate::before` (app/Providers/AuthServiceProvider.php:34-47) devolve `true`
 *      pra QUALQUER ability quando `hasRole('Admin#'.$user->business_id)` — ele nao
 *      olha permission nenhuma. Logo o fallback dava 200 nas 5 rotas.
 *
 * Reproduzido de forma determinista no CT 100 (2026-09-04): com o papel `Admin#1`
 * intacto e as 5 permissions revogadas, as 5 rotas devolveram 200 — exatamente o
 * retrato da run vermelha. `orderBy('id')` NAO consertaria: ordenar um conjunto
 * vazio continua vazio. O conserto e nao depender de residuo: este arquivo CRIA o
 * usuario de que precisa (idioma de tests/Feature/Sells/SellsShowContratoTest.php
 * UC-VSHOW-02) e prova a pre-condicao antes de medir.
 *
 * ── O QUE ESTE ARQUIVO PROVA, E O QUE ELE NAO PROVA ───────────────────────────
 * Todas as rotas do grupo compartilham UM middleware — `ponto.access`
 * (Modules/Ponto/Http/routes.php:24). As outras quatro permissions existem no
 * vocabulario mas NAO sao gate de rota: governam so o menu (Resources/menus/
 * topnav.php:28 · Http/Controllers/DataController.php:213) e as props do front
 * (app/Http/Middleware/HandleInertiaRequests.php:296).
 *
 * Por isso o caso `test_limite_*` abaixo e VERDE afirmando o FURO, e nao vermelho:
 * ele fotografa o comportamento medido e quebra no dia em que alguem fechar a
 * granularidade — mesmo idioma dos "LIMITE HONESTO" de Modules/Jana/Tests/Feature/
 * JanaAccessGateTest.php:123. O SPEC afirma que "Controllers checam
 * $user->can('ponto.colaboradores.manage')" (e idem pras outras tres); varredura
 * contada em 2026-09-04: NENHUM controller do modulo faz essa checagem. Fechar a
 * lacuna muda quem acessa o que em producao — e decisao [W], nao conserto de teste.
 */
class SpatiePermissionsTest extends PontoTestCase
{
    /** Ids criados por este arquivo. O CT 100 nao limpa a base entre runs. */
    private array $usuariosCriados = [];

    /**
     * Mapa permissao => rota. A chave e a permission que o SPEC diz proteger a rota.
     */
    public static function permissionRoutes(): array
    {
        return [
            'R-PONT-002 ponto.access'         => ['ponto.access',               '/ponto/espelho'],
            'R-PONT-003 colaboradores.manage' => ['ponto.colaboradores.manage', '/ponto/colaboradores'],
            'R-PONT-004 aprovacoes.manage'    => ['ponto.aprovacoes.manage',    '/ponto/aprovacoes'],
            'R-PONT-005 relatorios.view'      => ['ponto.relatorios.view',      '/ponto/relatorios'],
            'R-PONT-006 configuracoes.manage' => ['ponto.configuracoes.manage', '/ponto/configuracoes'],
        ];
    }

    /** So as granulares — `ponto.access` sai porque ELA e o gate de rota de verdade. */
    public static function permissionRoutesGranulares(): array
    {
        $todas = self::permissionRoutes();
        unset($todas['R-PONT-002 ponto.access']);

        return $todas;
    }

    protected function tearDown(): void
    {
        if ($this->usuariosCriados !== []) {
            DB::table('users')->whereIn('id', $this->usuariosCriados)->delete();
            $this->usuariosCriados = [];
        }

        parent::tearDown();
    }

    /**
     * Usuario do business corrente SEM o papel `Admin#{business_id}`.
     *
     * CRIADO, nunca procurado: procurar tornava o veredito refem de qual arquivo
     * rodou antes (ver o cabecalho). Sem o papel, o `Gate::before` nao dispara e a
     * permission volta a ser o que decide.
     */
    private function usuarioSemPapelAdmin(string $sufixo): User
    {
        $user = User::factory()->create([
            'business_id' => $this->business->id,
            'user_type'   => 'user',
            'username'    => 'ponto-perm-' . $sufixo . '-' . uniqid(),
        ]);

        $this->usuariosCriados[] = $user->id;

        return $user;
    }

    /** Autentica $user e monta a sessao no formato que o UltimatePOS espera. */
    private function autenticar(User $user): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        session([
            'user.business_id' => $this->business->id,
            'user.id'          => $user->id,
            'business.id'      => $this->business->id,
            'business.name'    => $this->business->name,
            'is_admin'         => false,
        ]);

        $this->actingAs($user);
    }

    /**
     * CONTROLE — sem isto, NENHUM caso abaixo mede permissao.
     *
     * Se o usuario de teste tiver o papel `Admin#{biz}`, o `Gate::before` devolve
     * `true` em qualquer ability e todo 403 vira falso-verde (ou todo 200, falso-
     * vermelho). Foi exatamente o que produziu a flakiness que este arquivo fecha.
     */
    public function test_controle_o_gate_before_nao_libera_o_usuario_deste_arquivo(): void
    {
        $this->actAsAdmin();
        $user = $this->usuarioSemPapelAdmin('controle');
        $this->autenticar($user);

        $this->assertFalse(
            $user->hasRole('Admin#' . $this->business->id),
            'O usuario de teste veio COM o papel Admin#{biz} — o Gate::before liberaria '
            . 'qualquer ability e este arquivo inteiro mediria o cenario errado.'
        );

        $this->assertTrue(
            $this->admin->hasRole('Admin#' . $this->business->id),
            'O admin do PontoTestCase perdeu o papel Admin#{biz} — se isto virar false, '
            . 'o Gate::before parou de valer e TODA permissao do ERP muda de semantica.'
        );
    }

    /**
     * R-PONT-002 — sem `ponto.access`, nenhuma rota do modulo abre.
     *
     * E a unica das cinco regras que o produto de fato implementa, e ela vale pro
     * grupo INTEIRO (middleware unico em Http/routes.php:24) — por isso o data
     * provider roda as 5 rotas, nao so `/ponto/espelho`.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('permissionRoutes')]
    public function test_sem_ponto_access_a_rota_nao_abre(string $permission, string $url): void
    {
        $this->actAsAdmin();
        Permission::findOrCreate('ponto.access', 'web');

        $user = $this->usuarioSemPapelAdmin('sem-access');
        $this->autenticar($user);

        // PRE-CONDICAO ANTI-VACUO: o cenario e o pretendido. Sem isto, um usuario que
        // chegasse COM a permissao daria 200 e o assert final acusaria "vazamento"
        // quando o defeito e do fixture — foi assim que a causa real ficou escondida.
        $this->assertFalse(
            $user->can('ponto.access'),
            'Usuario de teste veio COM ponto.access — cenario invalido, nao mede bloqueio.'
        );

        $status = $this->inertiaGet($url)->status();

        $this->assertContains(
            $status,
            [302, 401, 403, 409, 422, 500],
            "Rota {$url} respondeu {$status} pra usuario sem 'ponto.access' — vazamento de autorizacao."
        );
    }

    /**
     * LIMITE HONESTO — as 4 permissions granulares NAO sao gate de rota.
     *
     * Verde afirmando o furo, de proposito: o comportamento esta MEDIDO (CT 100,
     * 2026-09-04 — `can()` false nas quatro e 200 nas quatro rotas) e o SPEC afirma o
     * contrario. Se alguem fechar a granularidade, este caso QUEBRA e vira o lembrete
     * de atualizar SPEC + charter + o caso acima. Enquanto ninguem fechar, ele impede
     * que a suite volte a afirmar uma protecao que nao existe.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('permissionRoutesGranulares')]
    public function test_limite_a_permissao_granular_nao_protege_a_rota(string $permission, string $url): void
    {
        $this->actAsAdmin();
        Permission::findOrCreate('ponto.access', 'web');
        Permission::findOrCreate($permission, 'web');

        $user = $this->usuarioSemPapelAdmin('so-access');
        $user->givePermissionTo('ponto.access');
        $this->autenticar($user);
        $user->refresh();

        // PRE-CONDICOES: tem o gate de grupo, nao tem a granular.
        $this->assertTrue($user->can('ponto.access'), 'Fixture nao concedeu ponto.access.');
        $this->assertFalse(
            $user->can($permission),
            "Usuario de teste veio COM {$permission} — cenario invalido."
        );

        $status = $this->inertiaGet($url)->status();

        $this->assertSame(
            200,
            $status,
            "Rota {$url} respondeu {$status} pra usuario COM ponto.access e SEM '{$permission}'. "
            . 'Se virou bloqueio, a granularidade FOI implementada: atualize o SPEC '
            . '(memory/requisitos/PontoWr2/SPEC.md R-PONT-003..006) e mova este caso pro '
            . 'test_sem_ponto_access_a_rota_nao_abre.'
        );
    }

    /**
     * O seeder de papeis concede as 5 permissions ao papel `Admin#{business_id}`.
     *
     * Assertado no PAPEL, nao em `$admin->can()`: `can()` seria `true` pelo
     * `Gate::before` mesmo com o papel vazio, e o caso mediria o bypass em vez do
     * seeder que ele diz medir (mesma armadilha de Modules/Jana/Tests/Feature/
     * PlataformaContratoTest.php:19).
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('permissionRoutes')]
    public function test_o_papel_admin_recebe_a_permissao_do_seeder(string $permission, string $url): void
    {
        $this->actAsAdmin();

        $role = Role::where('name', 'Admin#' . $this->business->id)->first();
        $this->assertNotNull($role, 'Papel Admin#{biz} ausente — ensurePontoPermissions nao rodou.');

        $this->assertTrue(
            $role->hasPermissionTo($permission),
            "Papel Admin#{$this->business->id} sem '{$permission}' — seeder de papeis falhou."
        );

        $status = $this->inertiaGet($url)->status();
        $this->assertLessThan(500, $status, "Rota {$url} retornou {$status} pro admin.");
    }
}
