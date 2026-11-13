<?php

declare(strict_types=1);
// @covers-us US-ASSOC-002
// Cobre UC-ASSOC-00..07 (Modules/Associacoes/Resources/js/Pages/Associacoes/Index.casos.md).

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Modules\Associacoes\Entities\Associacao;
use Modules\Associacoes\Entities\DocumentoAssociacao;
use Modules\Associacoes\Entities\Representante;
use Modules\Associacoes\Http\Controllers\DataController;
use Modules\Associacoes\Services\AssociacoesAcesso as A;
use Modules\Associacoes\Services\PendenciaDiretoria;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Telas de associações e documentos (US-ASSOC-002 · ADR 0451 D3/D4/D7). Isolamento nos dois
 * eixos: entre empresas (98 × 99, ADR 0358) e entre associações da mesma empresa (o
 * representante não vê nem edita a outra). ⚠️ SKIP em SQLite: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('assoc_documentos') || ! Schema::hasTable('arquivos') || ! Schema::hasTable('arquivos_audit_log')) {
        $this->markTestSkipped('Exige MySQL com as migrations do Modules/Associacoes e do Modules/Arquivos (ADR 0358).');
    }
    Storage::fake(config('arquivos.disk_default', 'local'));
    Storage::fake(config('arquivos.disk_vault', 'vault'));

    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $fake = Mockery::mock(ModuleUtil::class)->makePartial();
    $fake->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($biz, $perm) => $perm === A::PACOTE && in_array((int) $biz, [$this->b98, $this->b99], true));
    app()->instance(ModuleUtil::class, $fake);

    $this->diretoria = $this->usuarioComPermissoes([A::PERMISSAO, A::PERMISSAO_REDE, A::PERMISSAO_EDITAR], $this->seededTenant());
    $this->rep = $this->usuarioComPermissoes([A::PERMISSAO, A::PERMISSAO_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([A::PERMISSAO, A::PERMISSAO_REDE, A::PERMISSAO_EDITAR], $this->seededSupportClientTenant());

    $this->a = assocTelaAssociacao($this->b98, 'Associação A');
    $this->b = assocTelaAssociacao($this->b98, 'Associação B');
    $this->c99 = assocTelaAssociacao($this->b99, 'Associação da 99');
    // SUPERADMIN: fixture do vínculo do representante só com a A.
    Representante::withoutGlobalScopes()->create(['business_id' => $this->b98, 'associacao_id' => $this->a->id, 'user_id' => $this->rep->id]);
});

function assocTelaComo($t, $user): void
{
    $t->actingAs($user);
    session(['user.business_id' => (int) $user->business_id]);
}

function assocTelaAssociacao(int $biz, string $nome): Associacao
{
    // SUPERADMIN: fixture grava na empresa do teste; o alcance é provado nos asserts.
    return Associacao::withoutGlobalScopes()->create(['business_id' => $biz, 'nome' => $nome.' '.uniqid(), 'cidade' => 'Cidade Fixture', 'situacao' => 'ativa']);
}

function assocTelaPdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('doc.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF ".uniqid('', true));
}

/** @return list<int> ids da lista entregue pela prop deferida `associacoes`. */
function assocTelaIdsDaLista($t): array
{
    $ids = [];
    $t->get('/associacoes/lista')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
        ->component('Associacoes/Index')
        ->loadDeferredProps(function (AssertableInertia $r) use (&$ids) {
            $ids = array_map(fn ($l) => (int) $l['id'], $r->toArray()['props']['associacoes']);
        }));

    return $ids;
}

it('UC-ASSOC-00 · o menu Associações aparece com pacote e permissão e leva à lista; sem permissão some', function () {
    assocTelaComo($this, $this->rep);
    \Menu::create('admin-sidebar-menu', fn ($menu) => $menu);
    (new DataController())->modifyAdminMenu();
    $grupo = collect((new \App\Services\LegacyMenuAdapter())->build())->firstWhere('label', 'Associações');
    $this->assertNotNull($grupo, 'o grupo Associações deveria estar na sidebar');
    $rotas = collect($grupo['children'] ?? [])->map(fn ($c) => parse_url($c['href'], PHP_URL_PATH))->all();
    expect($rotas)->toContain('/associacoes/lista');
    $this->get('/associacoes/lista')->assertOk();

    // Controle negativo: mesma empresa, usuário sem associacoes.access → sem menu e 403.
    $sem = $this->usuarioComPermissoes([], $this->seededTenant());
    assocTelaComo($this, $sem);
    \Menu::create('admin-sidebar-menu', fn ($menu) => $menu);
    (new DataController())->modifyAdminMenu();
    expect(array_column((new \App\Services\LegacyMenuAdapter())->build(), 'label'))->not->toContain('Associações');
    $this->get('/associacoes/lista')->assertForbidden();
});

it('UC-ASSOC-01 · a diretoria vê a rede da empresa dela; o representante vê só a dele; a 99 nunca aparece', function () {
    assocTelaComo($this, $this->diretoria);
    $daDiretoria = assocTelaIdsDaLista($this);
    expect($daDiretoria)->toContain((int) $this->a->id)->toContain((int) $this->b->id)
        ->not->toContain((int) $this->c99->id);

    assocTelaComo($this, $this->rep);
    expect(assocTelaIdsDaLista($this))->toBe([(int) $this->a->id]);
});

it('UC-ASSOC-02 · a diretoria cadastra associação com representante; o representante não cria nem troca representantes', function () {
    assocTelaComo($this, $this->diretoria);
    $this->from('/associacoes/lista')->post('/associacoes/lista', [
        'nome' => 'Nova Associação '.uniqid(), 'cidade' => 'Florianópolis', 'regiao' => 'Grande Florianópolis',
        'situacao' => 'em_adesao', 'representantes' => [$this->rep->id],
    ])->assertRedirect()->assertSessionHasNoErrors();
    $nova = Associacao::query()->where('cidade', 'Florianópolis')->latest('id')->firstOrFail();
    expect((int) $nova->business_id)->toBe($this->b98)
        ->and(Representante::query()->where('associacao_id', $nova->id)->pluck('user_id')->map('intval')->all())->toBe([(int) $this->rep->id]);

    // Usuário da 99 não vira representante de associação da 98.
    $this->from('/associacoes/lista')->put("/associacoes/{$nova->id}", [
        'nome' => $nova->nome, 'cidade' => 'Florianópolis', 'situacao' => 'ativa', 'representantes' => [$this->u99->id],
    ])->assertSessionHasErrors('representantes.0');

    assocTelaComo($this, $this->rep);
    $this->post('/associacoes/lista', ['nome' => 'Tentativa '.uniqid(), 'cidade' => 'X', 'situacao' => 'ativa'])->assertForbidden();
    // Editar a dele pode; a lista de representantes enviada é ignorada (sem associacoes.rede).
    $this->from('/associacoes/lista')->put("/associacoes/{$this->a->id}", [
        'nome' => $this->a->nome, 'cidade' => 'Joinville', 'situacao' => 'ativa', 'representantes' => [$this->diretoria->id],
    ])->assertSessionHasNoErrors();
    expect($this->a->fresh()->cidade)->toBe('Joinville')
        ->and(Representante::query()->where('associacao_id', $this->a->id)->pluck('user_id')->map('intval')->all())->toBe([(int) $this->rep->id]);
});

it('UC-ASSOC-03 · representante não edita nem abre a ficha de outra associação; a 99 não toca a 98 (404)', function () {
    assocTelaComo($this, $this->rep);
    $this->put("/associacoes/{$this->b->id}", ['nome' => 'Invasão', 'cidade' => 'X', 'situacao' => 'ativa'])->assertNotFound();
    $this->post("/associacoes/{$this->b->id}/diretoria", ['mudou_em' => now()->toDateString()])->assertNotFound();
    $this->post("/associacoes/{$this->b->id}/documentos", ['tipo' => 'estatuto', 'arquivo' => assocTelaPdf()])->assertNotFound();

    assocTelaComo($this, $this->u99);
    $this->put("/associacoes/{$this->a->id}", ['nome' => 'Invasão', 'cidade' => 'X', 'situacao' => 'ativa'])->assertNotFound();
    expect($this->b->fresh()->nome)->not->toBe('Invasão')->and($this->a->fresh()->nome)->not->toBe('Invasão');
});

it('UC-ASSOC-04 · anexar documento grava o arquivo no Arquivos ligado à linha; o arquivo só abre no alcance', function () {
    assocTelaComo($this, $this->rep);
    $this->from('/associacoes/lista')->post("/associacoes/{$this->a->id}/documentos", [
        'tipo' => 'estatuto', 'descricao' => 'Estatuto registrado', 'validade' => '2030-01-01', 'arquivo' => assocTelaPdf(),
    ])->assertRedirect()->assertSessionHasNoErrors();
    $doc = DocumentoAssociacao::query()->where('associacao_id', $this->a->id)->latest('id')->firstOrFail();
    expect((int) $doc->business_id)->toBe($this->b98)->and($doc->arquivo_id)->not->toBeNull();

    $this->get("/associacoes/documentos/{$doc->id}/arquivo")->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    // Discriminante: o representante de outra associação (sem vínculo com a A) e a 99 → 404.
    $outro = $this->usuarioComPermissoes([A::PERMISSAO, A::PERMISSAO_EDITAR], $this->seededTenant());
    Representante::withoutGlobalScopes()->create(['business_id' => $this->b98, 'associacao_id' => $this->b->id, 'user_id' => $outro->id]); // SUPERADMIN: fixture.
    assocTelaComo($this, $outro);
    $this->get("/associacoes/documentos/{$doc->id}/arquivo")->assertNotFound();
    assocTelaComo($this, $this->u99);
    $this->get("/associacoes/documentos/{$doc->id}/arquivo")->assertNotFound();
});

it('UC-ASSOC-05 · tirar da lista pede motivo e não apaga; quem só acessa não grava', function () {
    assocTelaComo($this, $this->rep);
    $this->from('/associacoes/lista')->post("/associacoes/{$this->a->id}/documentos", ['tipo' => 'certidao', 'arquivo' => assocTelaPdf()])->assertSessionHasNoErrors();
    $doc = DocumentoAssociacao::query()->where('associacao_id', $this->a->id)->latest('id')->firstOrFail();

    $this->from('/associacoes/lista')->post("/associacoes/documentos/{$doc->id}/tirar", ['motivo' => ''])->assertSessionHasErrors('motivo');
    $this->from('/associacoes/lista')->post("/associacoes/documentos/{$doc->id}/tirar", ['motivo' => 'Certidão substituída'])->assertSessionHasNoErrors();
    $doc->refresh();
    expect($doc->removido_em)->not->toBeNull()
        ->and($doc->removido_motivo)->toBe('Certidão substituída')
        ->and((int) $doc->removido_por)->toBe((int) $this->rep->id)
        ->and(DocumentoAssociacao::query()->whereKey($doc->id)->exists())->toBeTrue();

    $leitor = $this->usuarioComPermissoes([A::PERMISSAO, A::PERMISSAO_REDE], $this->seededTenant());
    assocTelaComo($this, $leitor);
    $this->post("/associacoes/{$this->a->id}/documentos", ['tipo' => 'estatuto', 'arquivo' => assocTelaPdf()])->assertForbidden();
});

it('UC-ASSOC-06 · mudança de diretoria abre pendência; ata nova fecha e grava a data (D7)', function () {
    assocTelaComo($this, $this->rep);
    $this->from('/associacoes/lista')->post("/associacoes/{$this->a->id}/diretoria", ['mudou_em' => now()->subDays(3)->toDateString()])->assertSessionHasNoErrors();
    $situacao = app(PendenciaDiretoria::class)->situacao($this->a->fresh());
    expect($situacao['estado'])->toBe(PendenciaDiretoria::PENDENTE);

    $this->from('/associacoes/lista')->post("/associacoes/{$this->a->id}/documentos", ['tipo' => 'ata_diretoria', 'arquivo' => assocTelaPdf()])->assertSessionHasNoErrors();
    $a = $this->a->fresh();
    expect($a->diretoria_mudou_em)->toBeNull()
        ->and($a->diretoria_atualizada_em?->toDateString())->toBe(Carbon::today()->toDateString())
        ->and(app(PendenciaDiretoria::class)->situacao($a)['estado'])->toBe(PendenciaDiretoria::EM_DIA);
});

it('UC-ASSOC-07 · passado o prazo da config sem ata nova, a pendência fica atrasada; dentro do prazo, pendente', function () {
    config(['associacoes.prazo_ata_diretoria_dias' => 30]);
    $p = app(PendenciaDiretoria::class);
    $hoje = Carbon::parse('2026-11-13');
    $a = new Associacao(['diretoria_mudou_em' => '2026-10-14']);
    expect($p->situacao($a, $hoje)['estado'])->toBe(PendenciaDiretoria::PENDENTE)
        ->and($p->situacao($a, $hoje)['vence_em'])->toBe('2026-11-13');

    $a = new Associacao(['diretoria_mudou_em' => '2026-10-13']);
    expect($p->situacao($a, $hoje))->toMatchArray(['estado' => PendenciaDiretoria::ATRASADA, 'dias_atraso' => 1]);

    // O prazo vem da config: com 60 dias a mesma data volta a ser só pendente.
    config(['associacoes.prazo_ata_diretoria_dias' => 60]);
    expect($p->situacao($a, $hoje)['estado'])->toBe(PendenciaDiretoria::PENDENTE)
        ->and($p->situacao(new Associacao(), $hoje)['estado'])->toBe(PendenciaDiretoria::EM_DIA);
});
