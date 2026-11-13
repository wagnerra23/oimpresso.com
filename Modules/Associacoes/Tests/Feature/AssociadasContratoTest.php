<?php

declare(strict_types=1);
// @covers-us US-ASSOC-003
// Cobre UC-ASSOCI-00..07 (Modules/Associacoes/Resources/js/Pages/Associacoes/Associadas.casos.md).

use App\Facades\Menu;
use App\Services\LegacyMenuAdapter;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Associacoes\Entities\Associacao;
use Modules\Associacoes\Entities\Associada;
use Modules\Associacoes\Entities\ConsentimentoAssociada;
use Modules\Associacoes\Entities\Representante;
use Modules\Associacoes\Http\Controllers\AssociadasController;
use Modules\Associacoes\Http\Controllers\DataController;
use Modules\Associacoes\Services\AssociacoesAcesso;
use Modules\Associacoes\Services\Consentimentos;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Associadas e consentimentos (US-ASSOC-003; ADR 0451 D4/D5). Tenant 98 contra o 99 (ADR 0358),
 * e associação A contra B dentro da 98. Celulares são fictícios e gerados por chamada.
 *
 * ⚠️ SKIP em SQLite: schema UltimatePOS + trigger do consentimento (LC-13 — leia assertions).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('assoc_consentimentos')) {
        $this->markTestSkipped('Exige MySQL com as migrations do Modules/Associacoes (ADR 0358).');
    }
    $this->t98 = $this->seededTenant();
    $this->t99 = $this->seededSupportClientTenant();
    $this->b98 = (int) $this->t98->id;
    $this->b99 = (int) $this->t99->id;

    $fake = Mockery::mock(ModuleUtil::class)->makePartial();
    $fake->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($biz, $perm) => $perm === AssociacoesAcesso::PACOTE && in_array((int) $biz, [$this->b98, $this->b99], true));
    app()->instance(ModuleUtil::class, $fake);

    $this->a = assocdAssociacao($this->b98, 'Associação A');
    $this->b = assocdAssociacao($this->b98, 'Associação B');
    $this->diretoria = $this->usuarioComPermissoes([AssociacoesAcesso::PERMISSAO, AssociacoesAcesso::PERMISSAO_REDE, AssociacoesAcesso::PERMISSAO_EDITAR], $this->t98);
    $this->repA = $this->usuarioComPermissoes([AssociacoesAcesso::PERMISSAO, AssociacoesAcesso::PERMISSAO_EDITAR], $this->t98);
    // SUPERADMIN: fixture do vínculo representante → associação A.
    Representante::withoutGlobalScopes()->create(['business_id' => $this->b98, 'associacao_id' => $this->a->id, 'user_id' => $this->repA->id]);
});

function assocdAssociacao(int $biz, string $nome): Associacao
{
    // SUPERADMIN: fixture grava na empresa do teste; o alcance é provado nos asserts.
    return Associacao::withoutGlobalScopes()->create(['business_id' => $biz, 'nome' => $nome.' '.uniqid(), 'cidade' => 'Cidade Fixture']);
}

function assocdComo($t, $user): void
{
    $t->actingAs($user);
    session(['user.business_id' => (int) $user->business_id]);
}

/** Celular fictício e único por chamada (nunca número real). */
function assocdCelular(): string
{
    return '4890'.str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
}

function assocdContato(int $biz, int $criadoPor, ?string $celular = null): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Associada Fixture '.uniqid(),
        'mobile' => $celular ?? assocdCelular(), 'contact_status' => 'active', 'created_by' => $criadoPor,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function assocdAssociada(int $biz, int $associacaoId, int $contactId): Associada
{
    // SUPERADMIN: fixture direto no banco, sem passar pela rota.
    return Associada::withoutGlobalScopes()->create([
        'business_id' => $biz, 'associacao_id' => $associacaoId, 'contact_id' => $contactId,
        'entrou_em' => now()->toDateString(), 'situacao' => 'ativa',
    ]);
}

/** Props deferidas da lista, como o cliente Inertia pede (partial reload). */
function assocdLista($t, array $query = []): array
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $t->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $versao,
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Partial-Component' => 'Associacoes/Associadas',
        'X-Inertia-Partial-Data' => 'associadas,associacoes',
    ])->get('/associacoes/associadas'.($query ? '?'.http_build_query($query) : ''));
    $r->assertOk();

    return (array) $r->json('props');
}

it('UC-ASSOCI-00 · o item "Associadas" aparece no menu para quem tem acesso, e some sem o pacote', function () {
    $menu = function ($user) {
        assocdComo($this, $user);
        Menu::create('admin-sidebar-menu', fn ($m) => $m);
        (new DataController())->modifyAdminMenu();

        return json_encode((new LegacyMenuAdapter())->build());
    };

    expect($menu($this->repA))->toContain('/associacoes/associadas');

    // Controle negativo: mesma pessoa, empresa sem o pacote.
    $fake = Mockery::mock(ModuleUtil::class)->makePartial();
    $fake->shouldReceive('hasThePermissionInSubscription')->andReturn(false);
    app()->instance(ModuleUtil::class, $fake);
    expect($menu($this->repA))->not->toContain('/associacoes/associadas');
});

it('UC-ASSOCI-01 · cadastra com contato novo e grava os aceites marcados na versão da config, nunca a do request', function () {
    config(['associacoes.consentimento_versoes.associada' => '7']);
    assocdComo($this, $this->diretoria);
    $celular = assocdCelular();

    $this->post('/associacoes/associadas', [
        'associacao_id' => $this->a->id,
        'entrou_em' => now()->toDateString(),
        'novo_contato' => ['nome' => 'Associada Nova', 'telefone' => $celular, 'cidade' => 'Tubarão'],
        'consentimentos' => ['associada' => true, 'whatsapp' => true, 'visivel_rede' => false],
        'versao' => '999',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $contato = DB::table('contacts')->where('business_id', $this->b98)->where('mobile', $celular)->first();
    expect($contato)->not->toBeNull()->and($contato->type)->toBe('customer');
    $associada = Associada::query()->where('contact_id', $contato->id)->firstOrFail();
    expect((int) $associada->associacao_id)->toBe((int) $this->a->id)->and($associada->situacao)->toBe('ativa');

    $aceites = ConsentimentoAssociada::query()->where('associada_id', $associada->id)->pluck('versao', 'finalidade')->all();
    expect($aceites)->toBe(['associada' => '7', 'whatsapp' => '1']);

    // Celular repetido na empresa: recusado, nada nasce.
    $this->post('/associacoes/associadas', [
        'associacao_id' => $this->a->id, 'entrou_em' => now()->toDateString(),
        'novo_contato' => ['nome' => 'Outra', 'telefone' => $celular], 'consentimentos' => ['associada' => true],
    ])->assertSessionHasErrors('novo_contato.telefone');
    expect(DB::table('contacts')->where('business_id', $this->b98)->where('mobile', $celular)->count())->toBe(1);
});

it('UC-ASSOCI-02 · empresa 99 não vê nem cadastra associada da 98', function () {
    $contato98 = assocdContato($this->b98, $this->diretoria->id);
    $associada98 = assocdAssociada($this->b98, (int) $this->a->id, $contato98);
    $u99 = $this->usuarioComPermissoes([AssociacoesAcesso::PERMISSAO, AssociacoesAcesso::PERMISSAO_REDE, AssociacoesAcesso::PERMISSAO_EDITAR], $this->t99);

    assocdComo($this, $u99);
    $ids = array_column(assocdLista($this)['associadas'], 'id');
    expect($ids)->not->toContain((int) $associada98->id);

    // Associação e contato da 98 não servem para cadastrar na 99.
    $this->post('/associacoes/associadas', [
        'associacao_id' => $this->a->id, 'entrou_em' => now()->toDateString(),
        'contact_id' => assocdContato($this->b98, $this->diretoria->id), 'consentimentos' => ['associada' => true],
    ])->assertSessionHasErrors(['associacao_id', 'contact_id']);
    $this->post('/associacoes/associadas/'.$associada98->id.'/desligar', ['desligada_em' => now()->toDateString()])->assertNotFound();

    // Controle positivo: a diretoria da 98 vê a mesma associada.
    assocdComo($this, $this->diretoria);
    expect(array_column(assocdLista($this)['associadas'], 'id'))->toContain((int) $associada98->id);
});

it('UC-ASSOCI-03 · representante vê e cadastra só na associação dele', function () {
    $naA = assocdAssociada($this->b98, (int) $this->a->id, assocdContato($this->b98, $this->diretoria->id));
    $naB = assocdAssociada($this->b98, (int) $this->b->id, assocdContato($this->b98, $this->diretoria->id));

    assocdComo($this, $this->repA);
    $props = assocdLista($this);
    expect(array_column($props['associadas'], 'id'))->toContain((int) $naA->id)->not->toContain((int) $naB->id)
        ->and(array_column($props['associacoes'], 'id'))->toBe([(int) $this->a->id]);

    $contato = assocdContato($this->b98, $this->diretoria->id);
    $this->post('/associacoes/associadas', [
        'associacao_id' => $this->b->id, 'entrou_em' => now()->toDateString(),
        'contact_id' => $contato, 'consentimentos' => ['associada' => true],
    ])->assertForbidden();
    expect(Associada::query()->where('contact_id', $contato)->exists())->toBeFalse();
    $this->post('/associacoes/associadas/'.$naB->id.'/desligar', ['desligada_em' => now()->toDateString()])->assertNotFound();

    // Controle positivo: na associação dele, o mesmo contato entra.
    $this->post('/associacoes/associadas', [
        'associacao_id' => $this->a->id, 'entrou_em' => now()->toDateString(),
        'contact_id' => $contato, 'consentimentos' => ['associada' => true],
    ])->assertSessionHasNoErrors();
    expect(Associada::query()->where('contact_id', $contato)->exists())->toBeTrue();
});

it('UC-ASSOCI-04 · celular sai mascarado; diretoria só vê o nome com o aceite visivel_rede', function () {
    $celular = '48912345678';
    $contato = assocdContato($this->b98, $this->diretoria->id, $celular);
    $associada = assocdAssociada($this->b98, (int) $this->a->id, $contato);

    assocdComo($this, $this->diretoria);
    $linha = collect(assocdLista($this)['associadas'])->firstWhere('id', (int) $associada->id);
    expect($linha['celular'])->toBe('(48) 9****-**78')
        ->and(json_encode(assocdLista($this)))->not->toContain($celular)
        ->and($linha['nome'])->toBeNull();

    app(Consentimentos::class)->conceder($associada, Consentimentos::VISIVEL_REDE, 'cadastro');
    $linha = collect(assocdLista($this)['associadas'])->firstWhere('id', (int) $associada->id);
    expect($linha['nome'])->not->toBeNull();

    // O representante da associação vê o nome mesmo sem o aceite da rede.
    app(Consentimentos::class)->revogar($associada, Consentimentos::VISIVEL_REDE);
    assocdComo($this, $this->repA);
    $linha = collect(assocdLista($this)['associadas'])->firstWhere('id', (int) $associada->id);
    expect($linha['nome'])->not->toBeNull()->and($linha['celular'])->toBe('(48) 9****-**78');

    expect(AssociadasController::mascarar('+55 (48) 99876-5432'))->toBe('(48) 9****-**32')
        ->and(AssociadasController::mascarar(''))->toBe('');
});

it('UC-ASSOCI-05 · sem o aceite "fazer parte da associação" o cadastro é recusado', function () {
    assocdComo($this, $this->diretoria);
    $contato = assocdContato($this->b98, $this->diretoria->id);

    $this->post('/associacoes/associadas', [
        'associacao_id' => $this->a->id, 'entrou_em' => now()->toDateString(),
        'contact_id' => $contato, 'consentimentos' => ['associada' => false, 'whatsapp' => true],
    ])->assertSessionHasErrors('consentimentos.associada');
    expect(Associada::query()->where('contact_id', $contato)->exists())->toBeFalse();
});

it('UC-ASSOCI-06 · desligar guarda situação e data; consentimento não se apaga, só revoga', function () {
    $associada = assocdAssociada($this->b98, (int) $this->a->id, assocdContato($this->b98, $this->diretoria->id));
    $servico = app(Consentimentos::class);
    $servico->conceder($associada, Consentimentos::ASSOCIADA, 'cadastro');
    expect($servico->conceder($associada, Consentimentos::ASSOCIADA, 'cadastro')['criado'])->toBeFalse()
        ->and($servico->vigente($associada, Consentimentos::ASSOCIADA))->toBeTrue();

    assocdComo($this, $this->repA);
    $this->post('/associacoes/associadas/'.$associada->id.'/desligar', ['desligada_em' => '2026-11-01'])->assertSessionHasNoErrors();
    $associada->refresh();
    expect($associada->situacao)->toBe('desligada')->and($associada->desligada_em->toDateString())->toBe('2026-11-01');
    // Repetir não muda a data do primeiro desligamento.
    $this->post('/associacoes/associadas/'.$associada->id.'/desligar', ['desligada_em' => '2026-11-05']);
    expect($associada->fresh()->desligada_em->toDateString())->toBe('2026-11-01');

    expect($servico->revogar($associada, Consentimentos::ASSOCIADA))->toBe(1)
        ->and($servico->vigente($associada, Consentimentos::ASSOCIADA))->toBeFalse();
    $linha = ConsentimentoAssociada::query()->where('associada_id', $associada->id)->firstOrFail();
    expect($linha->revoked_at)->not->toBeNull()
        ->and(fn () => $linha->delete())->toThrow(LogicException::class);
    // E o banco também recusa (trigger), por fora do Model.
    expect(fn () => DB::table('assoc_consentimentos')->where('id', $linha->id)->delete())->toThrow(\Illuminate\Database\QueryException::class);
    expect(ConsentimentoAssociada::query()->whereKey($linha->id)->exists())->toBeTrue();
});

it('UC-ASSOCI-07 · sem associacoes.editar não cadastra nem desliga, mas vê a lista', function () {
    $leitor = $this->usuarioComPermissoes([AssociacoesAcesso::PERMISSAO, AssociacoesAcesso::PERMISSAO_REDE], $this->t98);
    $associada = assocdAssociada($this->b98, (int) $this->a->id, assocdContato($this->b98, $this->diretoria->id));

    assocdComo($this, $leitor);
    expect(array_column(assocdLista($this)['associadas'], 'id'))->toContain((int) $associada->id);
    $this->post('/associacoes/associadas', [
        'associacao_id' => $this->a->id, 'entrou_em' => now()->toDateString(),
        'contact_id' => assocdContato($this->b98, $this->diretoria->id), 'consentimentos' => ['associada' => true],
    ])->assertForbidden();
    $this->post('/associacoes/associadas/'.$associada->id.'/desligar', ['desligada_em' => now()->toDateString()])->assertForbidden();
    $this->getJson('/associacoes/associadas/contatos?q=a')->assertForbidden();
    expect($associada->fresh()->situacao)->toBe('ativa');
});
