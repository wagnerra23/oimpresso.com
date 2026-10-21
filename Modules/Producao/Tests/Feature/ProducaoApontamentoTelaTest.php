<?php
// @covers-us US-PCP-027

declare(strict_types=1);
// Cobre, na camada HTTP da PWA, UC-PCV-19, UC-PCV-23, UC-PCV-34 e UC-PCREF-07 (UC-FLX-16) (Producao/Apontamento/Index.casos.md).

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * A tela do operador (`/producao/apontamento`) — controller web da thread 04. A regra é do
 * `ApontamentoOperadorService` (provada em ProducaoApontamentoContratoTest); aqui se prova o que
 * só a camada HTTP faz: quem entra, a empresa vir da SESSÃO, e o código de erro que chega à fila
 * offline do aparelho.
 *
 * Tenant 98 contra o 99. O pacote do módulo é simulado só para `producao_module` (o resto do
 * ModuleUtil roda de verdade). ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('pcp_apontamentos')) {
        $this->markTestSkipped('Precisa do schema MySQL com as tabelas pcp_*.');
    }
    Carbon::setTestNow('2026-10-15 10:00:00');
    $this->biz = $this->seededTenant();
    $this->diego = $this->usuarioComPermissoes(['producao.access'], $this->biz);
    $this->felipe = $this->usuarioComPermissoes(['producao.access'], $this->biz);
    $GLOBALS['tela_pacote'] = true;
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module' && $GLOBALS['tela_pacote']));
    $posto = (int) DB::table('pcp_postos')->insertGetId(['business_id' => $this->biz->id, 'codigo' => 'TELA-'.uniqid(), 'nome' => 'Acabamento',
        'tipo_custo' => 'pessoa', 'custo_hora' => '30.00', 'created_at' => now(), 'updated_at' => now()]);
    $etapa = fn (int $resp, int $os) => (int) DB::table('pcp_programacoes')->insertGetId(['business_id' => $this->biz->id, 'os_tipo' => 'comvis_os',
        'os_id' => $os, 'posto_id' => $posto, 'inicio_previsto' => '2026-10-15 08:00:00', 'fim_previsto' => '2026-10-15 09:00:00',
        'user_id' => $resp, 'created_by' => $resp, 'created_at' => now(), 'updated_at' => now()]);
    $this->etapaDiego = $etapa($this->diego->id, 701);
    $this->etapaFelipe = $etapa($this->felipe->id, 702);
});

afterEach(fn () => Carbon::setTestNow());

it('UC-PCV-19 · a tela abre só para quem tem acesso e o pacote, e lista só as etapas do operador', function () {
    $this->actingAs($this->diego)->get('/producao/apontamento')->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Producao/Apontamento/Index', false));

    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $etapas = $this->actingAs($this->diego)->get('/producao/apontamento', ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) $versao, 'X-Inertia-Partial-Data' => 'etapas', 'X-Inertia-Partial-Component' => 'Producao/Apontamento/Index'])
        ->assertOk()->json('props.etapas');
    expect(array_column($etapas, 'id'))->toBe([$this->etapaDiego]);

    // Controles negativos: sem a permissão, e sem o pacote do módulo, 403.
    $semAcesso = $this->usuarioComPermissoes([], $this->biz);
    $this->actingAs($semAcesso)->get('/producao/apontamento')->assertForbidden();
    $GLOBALS['tela_pacote'] = false;
    $this->actingAs($this->diego)->get('/producao/apontamento')->assertForbidden();
});

it('UC-PCV-34 · QR de etapa alheia responde quem está com ela; tentar apontar dá 409 e não grava', function () {
    $this->actingAs($this->diego)->postJson('/producao/apontamento/qr', ['os_tipo' => 'comvis_os', 'os_id' => 702])
        ->assertOk()->assertJsonPath('pode_apontar', false)->assertJsonPath('etapa.id', $this->etapaFelipe);

    $this->actingAs($this->diego)->postJson('/producao/apontamento/iniciar', ['programacao_id' => $this->etapaFelipe,
        'chave' => 'tela-34', 'momento' => '2026-10-15T09:58:00'])->assertStatus(409)->assertJsonPath('erro', 'etapa_alheia');
    expect(DB::table('pcp_apontamentos')->where('chave_idempotencia', 'tela-34')->count())->toBe(0);

    // OS sem etapa na empresa da sessão: 404 sem contexto (o 98 × 99 do Service está no ContratoTest UC-PCV-20).
    $this->actingAs($this->diego)->postJson('/producao/apontamento/qr', ['os_tipo' => 'comvis_os', 'os_id' => 999999])
        ->assertNotFound()->assertJsonPath('contexto', []);
});

it('UC-PCV-23 · a fila offline reenvia a mesma chave: 201, depois 200, uma linha só; corpo diferente é 422', function () {
    $corpo = ['programacao_id' => $this->etapaDiego, 'chave' => 'tela-23', 'momento' => '2026-10-15T08:05:00'];

    $this->actingAs($this->diego)->postJson('/producao/apontamento/iniciar', $corpo)->assertCreated();
    $this->actingAs($this->diego)->postJson('/producao/apontamento/iniciar', $corpo)->assertOk();
    expect(DB::table('pcp_apontamentos')->where('chave_idempotencia', 'tela-23')->count())->toBe(1);

    $this->actingAs($this->diego)->postJson('/producao/apontamento/iniciar', ['momento' => '2026-10-15T08:06:00'] + $corpo)
        ->assertStatus(422)->assertJsonPath('erro', 'idempotencia_conflito');
    $this->actingAs($this->diego)->postJson('/producao/apontamento/iniciar', ['chave' => 'tela-23b', 'momento' => '2026-10-15T11:00:00'] + $corpo)
        ->assertStatus(422)->assertJsonPath('erro', 'momento_invalido');
});

it('UC-PCREF-07 · UC-FLX-16 · a tela recebe os motivos de refugo da empresa 98, separados dos de pausa', function () {
    $posto = (int) DB::table('pcp_programacoes')->where('id', $this->etapaDiego)->value('posto_id');
    $mot = fn (int $biz, int $postoId, string $tipo, string $nome, bool $ativo = true) => (int) DB::table('pcp_motivos')->insertGetId([
        'business_id' => $biz, 'posto_id' => $postoId, 'tipo' => $tipo, 'nome' => $nome, 'ordem' => 0, 'ativo' => $ativo,
        'created_at' => now(), 'updated_at' => now()]);
    $corte = $mot($this->biz->id, $posto, 'refugo', 'Corte torto');
    $pausa = $mot($this->biz->id, $posto, 'pausa', 'Falta material');
    $inativo = $mot($this->biz->id, $posto, 'refugo', 'Desativado', false);
    // Adversário: motivo de refugo da empresa 99, num posto dela (pcp_motivos.posto_id é obrigatório).
    $b99 = (int) $this->seededSupportClientTenant()->id;
    $postoAlheio = (int) DB::table('pcp_postos')->insertGetId(['business_id' => $b99, 'codigo' => 'TELA-'.uniqid(), 'nome' => 'Acabamento',
        'tipo_custo' => 'pessoa', 'custo_hora' => '30.00', 'created_at' => now(), 'updated_at' => now()]);
    $alheio = $mot($b99, $postoAlheio, 'refugo', 'Corte (outra empresa)');

    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $props = $this->actingAs($this->diego)->get('/producao/apontamento', ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) $versao, 'X-Inertia-Partial-Data' => 'motivos,motivos_refugo', 'X-Inertia-Partial-Component' => 'Producao/Apontamento/Index'])
        ->assertOk()->json('props');

    // O formulário filtra pelo posto da etapa: a linha precisa trazer o posto_id. O posto nasce neste teste,
    // então o recorte por ele isola de motivos que a empresa 98 já tenha em outros postos.
    $doPosto = fn (array $lista) => array_values(array_filter($lista, fn ($m) => $m['posto_id'] === $posto));
    expect($doPosto($props['motivos_refugo']))->toBe([['id' => $corte, 'posto_id' => $posto, 'nome' => 'Corte torto']]);
    $refugo = array_column($props['motivos_refugo'], 'id');
    expect($refugo)->not->toContain($pausa)->and($refugo)->not->toContain($inativo)->and($refugo)->not->toContain($alheio);
    // A pausa segue lendo só os seus: o motivo de refugo não aparece na lista de pausa.
    expect(array_column($doPosto($props['motivos']), 'id'))->toBe([$pausa]);
});
