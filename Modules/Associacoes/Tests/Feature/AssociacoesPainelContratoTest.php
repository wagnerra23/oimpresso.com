<?php

declare(strict_types=1);
// @covers-us US-ASSOC-004

use App\User;
use App\Utils\ModuleUtil;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Associacoes\Entities\Associacao;
use Modules\Associacoes\Entities\Associada;
use Modules\Associacoes\Entities\DocumentoAssociacao;
use Modules\Associacoes\Entities\Representante;
use Modules\Associacoes\Services\AssociacoesAcesso;
use Modules\Associacoes\Services\PendenciasDaRede;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Painel da rede (ADR 0451 onda 5 · US-ASSOC-004) — contrato em
 * resources/js/Pages/Associacoes/Painel.casos.md.
 *
 * Tenant 98 contra 99 (ADR 0358). A base do CT 100 persiste entre runs, então os asserts da
 * diretoria são por pertença (contém / não contém), e os do representante são exatos — o
 * alcance dele é só a associação criada aqui.
 *
 * ⚠️ SKIP em SQLite: schema UltimatePOS (LC-13). A lane é verticais-pest (MySQL).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('assoc_associacoes')) {
        $this->markTestSkipped('Exige MySQL com as migrations do Modules/Associacoes (ADR 0358).');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;

    // Pacote ligado para as duas empresas: o isolamento tem de vir da consulta, não do pacote.
    $fake = Mockery::mock(ModuleUtil::class)->makePartial();
    $fake->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($biz, $perm) => $perm === AssociacoesAcesso::PACOTE);
    app()->instance(ModuleUtil::class, $fake);
});

function assocPainelAssociacao(int $biz, string $nome, string $cidade, array $extra = []): Associacao
{
    // SUPERADMIN: fixture grava na empresa do teste; o alcance é provado nos asserts.
    return Associacao::withoutGlobalScopes()->create(['business_id' => $biz, 'nome' => $nome.' '.uniqid(), 'cidade' => $cidade] + $extra);
}

function assocPainelAssociadas(Associacao $a, int $userId, int $quantas, string $situacao = 'ativa'): void
{
    for ($i = 0; $i < $quantas; $i++) {
        // contacts.created_by é FK NOT NULL -> users.id. Nome fictício: nenhum dado real em teste.
        $contato = (int) DB::table('contacts')->insertGetId([
            'business_id' => $a->business_id, 'type' => 'customer', 'name' => 'Associada Painel '.uniqid(),
            'mobile' => '48999990000', 'contact_status' => 'active', 'created_by' => $userId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // SUPERADMIN: fixture.
        Associada::withoutGlobalScopes()->create([
            'business_id' => $a->business_id, 'associacao_id' => $a->id, 'contact_id' => $contato,
            'entrou_em' => now()->toDateString(), 'situacao' => $situacao,
        ]);
    }
}

function assocPainelDocumento(Associacao $a, string $tipo, array $extra = []): void
{
    // SUPERADMIN: fixture.
    DocumentoAssociacao::withoutGlobalScopes()->create([
        'business_id' => $a->business_id, 'associacao_id' => $a->id, 'tipo' => $tipo, 'enviado_em' => now(),
    ] + $extra);
}

/** Lê as props DEFERIDAS do painel pelo partial reload que o browser faz (o Inertia manda os dois headers). */
function assocPainelLer(User $user): array
{
    $biz = (int) $user->business_id;
    $sessao = ['user.business_id' => $biz, 'user' => ['business_id' => $biz, 'id' => $user->id], 'business.date_format' => 'd/m/Y'];

    test()->flushHeaders();
    $inicial = test()->actingAs($user)->withSession($sessao)->get('/associacoes');
    expect($inicial->status())->toBe(200);
    $page = $inicial->viewData('page');
    expect(data_get($page, 'component'))->toBe('Associacoes/Painel');

    $r = test()->actingAs($user)->withSession($sessao)->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) data_get($page, 'version'),
        'X-Inertia-Partial-Component' => 'Associacoes/Painel',
        'X-Inertia-Partial-Data' => 'kpis,associadasPorAssociacao,pendencias',
    ])->get('/associacoes');
    expect($r->status())->toBe(200);

    return (array) data_get($r->json(), 'props');
}

it('UC-PAINEL-01 · a diretoria vê a rede da empresa dela e nada da outra empresa', function () {
    $diretoria = $this->usuarioComPermissoes([AssociacoesAcesso::PERMISSAO, AssociacoesAcesso::PERMISSAO_REDE], $this->seededTenant());
    $a = assocPainelAssociacao($this->b98, 'Associação Painel A', 'Cidade Painel A');
    assocPainelAssociadas($a, $diretoria->id, 2);
    $u99 = $this->usuarioComPermissoes([AssociacoesAcesso::PERMISSAO, AssociacoesAcesso::PERMISSAO_REDE], $this->seededSupportClientTenant());
    $x = assocPainelAssociacao($this->b99, 'Associação Painel Outra Empresa', 'Cidade Outra Empresa');
    assocPainelAssociadas($x, $u99->id, 3);

    $props = assocPainelLer($diretoria);

    $linhas = collect($props['associadasPorAssociacao'] ?? [])->keyBy('id');
    // Controle: a associação da própria empresa aparece, com o total certo — sem isso o
    // `not->toHaveKey` abaixo passaria com a lista vazia.
    expect($linhas->has($a->id))->toBeTrue();
    expect((int) $linhas[$a->id]['ativas'])->toBe(2);
    expect($linhas->has($x->id))->toBeFalse();
    expect(collect($props['pendencias'] ?? [])->pluck('associacao_id')->all())->not->toContain($x->id);
    expect((int) data_get($props, 'kpis.associadasAtivas'))->toBeGreaterThanOrEqual(2);
    // Nenhum dado pessoal: as linhas só carregam id, nome, cidade e total.
    expect(array_keys($linhas[$a->id]))->toBe(['id', 'nome', 'cidade', 'ativas']);
});

it('UC-PAINEL-02 · o representante vê só os números da associação dele', function () {
    $rep = $this->usuarioComPermissoes([AssociacoesAcesso::PERMISSAO], $this->seededTenant());
    $dele = assocPainelAssociacao($this->b98, 'Associação Painel Dele', 'Cidade Painel Dele');
    $outra = assocPainelAssociacao($this->b98, 'Associação Painel Vizinha', 'Cidade Painel Vizinha');
    assocPainelAssociadas($dele, $rep->id, 2);
    assocPainelAssociadas($dele, $rep->id, 1, 'desligada');
    assocPainelAssociadas($outra, $rep->id, 4);
    // SUPERADMIN: fixture do vínculo.
    Representante::withoutGlobalScopes()->create(['business_id' => $this->b98, 'associacao_id' => $dele->id, 'user_id' => $rep->id]);

    $props = assocPainelLer($rep);

    expect((int) data_get($props, 'kpis.associacoes'))->toBe(1)
        ->and((int) data_get($props, 'kpis.cidades'))->toBe(1)
        ->and((int) data_get($props, 'kpis.associadasAtivas'))->toBe(2);
    expect(collect($props['associadasPorAssociacao'])->pluck('id')->all())->toBe([(int) $dele->id]);
    // A associação dele não tem estatuto nem ata: as pendências existem e são todas dela.
    $pend = collect($props['pendencias']);
    expect($pend)->not->toBeEmpty();
    expect($pend->pluck('associacao_id')->unique()->values()->all())->toBe([(int) $dele->id]);
});

it('UC-PAINEL-03 · diretoria que mudou sem ata nova fica no prazo e, depois de 30 dias, atrasada', function () {
    $hoje = CarbonImmutable::parse('2026-11-13');
    $prazo = (int) config('associacoes.prazo_ata_diretoria_dias');
    expect($prazo)->toBe(30);

    $noPrazo = assocPainelAssociacao($this->b98, 'Associação Painel No Prazo', 'Cidade P1', ['diretoria_mudou_em' => $hoje->subDays(10)->toDateString()]);
    $atrasada = assocPainelAssociacao($this->b98, 'Associação Painel Atrasada', 'Cidade P2', ['diretoria_mudou_em' => $hoje->subDays(31)->toDateString()]);
    $resolvida = assocPainelAssociacao($this->b98, 'Associação Painel Resolvida', 'Cidade P3', ['diretoria_mudou_em' => $hoje->subDays(40)->toDateString()]);
    assocPainelDocumento($resolvida, 'ata_diretoria', ['enviado_em' => $hoje->subDays(5)]);
    $limite = assocPainelAssociacao($this->b98, 'Associação Painel Limite', 'Cidade P4', ['diretoria_mudou_em' => $hoje->subDays(30)->toDateString()]);

    $ids = [$noPrazo->id, $atrasada->id, $resolvida->id, $limite->id];
    $mudou = collect(app(PendenciasDaRede::class)->calcular($this->b98, $ids, $hoje))
        ->where('tipo', PendenciasDaRede::DIRETORIA_MUDOU)
        ->keyBy('associacao_id');

    expect($mudou[$noPrazo->id]['situacao'])->toBe('no_prazo')
        ->and($mudou[$noPrazo->id]['dias'])->toBe(10)
        ->and($mudou[$limite->id]['situacao'])->toBe('no_prazo')
        ->and($mudou[$atrasada->id]['situacao'])->toBe('atrasada')
        ->and($mudou[$atrasada->id]['dias'])->toBe(31)
        ->and($mudou->has($resolvida->id))->toBeFalse();
});

it('UC-PAINEL-04 · pendências de documento: estatuto, ata e certidão vencida; removido não conta', function () {
    $hoje = CarbonImmutable::parse('2026-11-13');
    $completa = assocPainelAssociacao($this->b98, 'Associação Painel Completa', 'Cidade D1');
    assocPainelDocumento($completa, 'estatuto');
    assocPainelDocumento($completa, 'ata_diretoria');
    assocPainelDocumento($completa, 'certidao', ['validade' => $hoje->addDays(10)->toDateString()]);

    $furada = assocPainelAssociacao($this->b98, 'Associação Painel Furada', 'Cidade D2');
    assocPainelDocumento($furada, 'estatuto', ['removido_em' => now(), 'removido_motivo' => 'enviado errado']);
    assocPainelDocumento($furada, 'certidao', ['validade' => $hoje->subDay()->toDateString()]);

    $desligada = assocPainelAssociacao($this->b98, 'Associação Painel Desligada', 'Cidade D3', ['situacao' => 'desligada']);

    $pend = collect(app(PendenciasDaRede::class)->calcular($this->b98, [$completa->id, $furada->id, $desligada->id], $hoje));

    expect($pend->where('associacao_id', $completa->id))->toBeEmpty();
    expect($pend->where('associacao_id', $furada->id)->pluck('tipo')->sort()->values()->all())
        ->toBe([PendenciasDaRede::CERTIDAO_VENCIDA, PendenciasDaRede::SEM_ATA_DIRETORIA, PendenciasDaRede::SEM_ESTATUTO]);
    expect($pend->where('associacao_id', $desligada->id))->toBeEmpty();
    // Alcance vazio = nenhuma pendência (representante sem vínculo).
    expect(app(PendenciasDaRede::class)->calcular($this->b98, [], $hoje))->toBe([]);
    // Empresa errada: as associações da 98 não aparecem pedindo pela 99.
    expect(app(PendenciasDaRede::class)->calcular($this->b99, [$furada->id], $hoje))->toBe([]);
});

it('UC-PAINEL-00 · a tela tem entrada no menu "Associações", com a mesma regra do middleware', function () {
    // ⚠️ LIMITE: lê o CÓDIGO do DataController, não o menu renderizado (idioma do
    // ArquivosAdminControllerTest UC-INDEX-01). Prova que o item continua no caminho e sob a
    // regra única de acesso; a prova de que ele aparece é o smoke com olho humano.
    $codigo = (string) file_get_contents(base_path('Modules/Associacoes/Http/Controllers/DataController.php'));
    $metodo = substr($codigo, (int) strpos($codigo, 'function modifyAdminMenu'));

    expect($metodo)->toContain('Menu::modify')
        ->and($metodo)->toContain("dropdown('Associações'")
        ->and($metodo)->toContain("url('/associacoes')")
        ->and($metodo)->toContain('AssociacoesAcesso::class)->liberado(');
    expect(route('associacoes.painel', [], false))->toBe('/associacoes');
});
