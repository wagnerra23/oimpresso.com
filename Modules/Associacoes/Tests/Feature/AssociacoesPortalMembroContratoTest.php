<?php

declare(strict_types=1);
// @covers-us US-ASSOC-005

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Associacoes\Entities\Associacao;
use Modules\Associacoes\Entities\Associada;
use Modules\Associacoes\Entities\ConsentimentoAssociada;
use Modules\Associacoes\Entities\PortalAssociada;
use Modules\Associacoes\Services\AssociacoesAcesso;
use Modules\Associacoes\Services\PortalAssociadaService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Área da associada no app — `/associacoes/m/{token}` (ADR 0451 D6 · US-ASSOC-005). Contrato em
 * `Modules/Associacoes/Resources/js/Pages/Associacoes/Public/Membro.casos.md` (UC-AMB-01..06).
 * Tenant 98 × 99 (ADR 0358): o pacote fica ligado só para a 98.
 *
 * ⚠️ SKIP em SQLite: schema UltimatePOS (LC-13). A lane é verticais-pest (MySQL).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('assoc_portal_tokens')) {
        $this->markTestSkipped('Exige MySQL com as migrations do Modules/Associacoes (ADR 0358).');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;

    $fake = Mockery::mock(ModuleUtil::class)->makePartial();
    $fake->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($biz, $perm) => $perm === AssociacoesAcesso::PACOTE && (int) $biz === $this->b98);
    app()->instance(ModuleUtil::class, $fake);

    $this->u98 = $this->usuarioComPermissoes([AssociacoesAcesso::PERMISSAO, AssociacoesAcesso::PERMISSAO_REDE, AssociacoesAcesso::PERMISSAO_EDITAR], $this->seededTenant());
    $this->a98 = assocMembroAssociada($this->b98, (int) $this->u98->id);
    $this->servico = app(PortalAssociadaService::class);
});

/** Associação + associada (contato fictício) + consentimentos `associada` e `whatsapp` no business dado. */
function assocMembroAssociada(int $biz, int $userId): Associada
{
    // SUPERADMIN: fixture grava na empresa do teste; o isolamento é provado nos asserts.
    $assoc = Associacao::withoutGlobalScopes()->create(['business_id' => $biz, 'nome' => 'Associação Fixture '.uniqid(), 'cidade' => 'Cidade Fixture']);
    $contato = (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Associada Fixture '.uniqid(),
        'mobile' => '48999990000', 'contact_status' => 'active', 'created_by' => $userId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    // SUPERADMIN: idem.
    $a = Associada::withoutGlobalScopes()->create([
        'business_id' => $biz, 'associacao_id' => $assoc->id, 'contact_id' => $contato, 'entrou_em' => now()->subYear()->toDateString(),
    ]);
    foreach (['associada', 'whatsapp'] as $finalidade) {
        // SUPERADMIN: idem.
        ConsentimentoAssociada::withoutGlobalScopes()->create([
            'business_id' => $biz, 'associada_id' => $a->id, 'finalidade' => $finalidade,
            'versao' => (string) config("associacoes.consentimento_versoes.{$finalidade}"), 'concedido_em' => now(), 'canal' => 'cadastro',
        ]);
    }

    // SUPERADMIN: recarrega com a situação default do banco.
    return Associada::withoutGlobalScopes()->findOrFail($a->id);
}

/** Props da página pela visita Inertia (o que o app recebe). */
function assocMembroProps($t, string $token): array
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $t->get('/associacoes/m/'.$token, ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao]);
    $r->assertOk();
    expect($r->json('component'))->toBe('Associacoes/Public/Membro');

    return (array) $r->json('props');
}

function assocMembroPinErrado(string $pin): string
{
    return $pin === '0000' ? '1111' : '0000';
}

it('UC-AMB-01 · link válido pede o PIN e não entrega dado nenhum antes dele', function () {
    $g = $this->servico->emitir($this->a98);
    $nome = (string) DB::table('contacts')->where('id', $this->a98->contact_id)->value('name');

    $props = assocMembroProps($this, $g['token']);
    expect($props['etapa'])->toBe('pin')
        ->and($props)->not->toHaveKey('dados')
        ->and($props)->not->toHaveKey('consentimentos');

    // Nem a página HTML (primeira visita, fora do Inertia) carrega o nome.
    $html = $this->get('/associacoes/m/'.$g['token'])->assertOk()->getContent();
    $this->assertStringNotContainsString($nome, $html, 'o nome da associada vazou antes do PIN');

    // O banco guarda só hashes.
    // SUPERADMIN: leitura da linha da fixture.
    $linha = PortalAssociada::withoutGlobalScopes()->where('associada_id', $this->a98->id)->firstOrFail();
    expect($linha->token_hash)->toBe(hash('sha256', $g['token']))
        ->and($linha->pin_hash)->not->toBe($g['pin']);
});

it('UC-AMB-02 · link de outra empresa, adulterado ou revogado é o mesmo estado vazio', function () {
    // Empresa 99 sem o pacote: o link emitido para ela não abre.
    $u99 = $this->usuarioComPermissoes([AssociacoesAcesso::PERMISSAO], $this->seededSupportClientTenant());
    $a99 = assocMembroAssociada($this->b99, (int) $u99->id);
    $g99 = $this->servico->emitir($a99);
    expect(assocMembroProps($this, $g99['token'])['erro'])->toBe('link_invalido');
    $this->postJson('/associacoes/m/'.$g99['token'].'/pin', ['pin' => $g99['pin']])->assertNotFound()->assertJsonPath('erro', 'link_invalido');

    // Payload trocado para apontar a associada da 99 com a assinatura da 98: assinatura não confere.
    $g98 = $this->servico->emitir($this->a98);
    [, $assinatura] = explode('.', $g98['token']);
    $forjado = rtrim(strtr(base64_encode(json_encode(['b' => $this->b99, 'a' => (int) $a99->id, 'n' => 'x'])), '+/', '-_'), '=').'.'.$assinatura;
    expect(assocMembroProps($this, $forjado)['erro'])->toBe('link_invalido');

    // Revogado: gerar de novo derruba o anterior.
    $novo = $this->servico->emitir($this->a98);
    expect(assocMembroProps($this, $g98['token'])['erro'])->toBe('link_invalido')
        ->and(assocMembroProps($this, $novo['token'])['etapa'])->toBe('pin');
});

it('UC-AMB-03 · 5 PINs errados travam, e o PIN certo também fica travado', function () {
    $g = $this->servico->emitir($this->a98);
    $errado = assocMembroPinErrado($g['pin']);

    for ($i = 4; $i >= 1; $i--) {
        $this->postJson('/associacoes/m/'.$g['token'].'/pin', ['pin' => $errado])->assertStatus(422)->assertJsonPath('tentativas_restantes', $i);
    }
    $this->postJson('/associacoes/m/'.$g['token'].'/pin', ['pin' => $errado])->assertStatus(423)->assertJsonPath('erro', 'bloqueado');
    $this->postJson('/associacoes/m/'.$g['token'].'/pin', ['pin' => $g['pin']])->assertStatus(423);

    $props = assocMembroProps($this, $g['token']);
    expect($props['etapa'])->toBe('pin')->and($props['tentativas_restantes'])->toBe(0)->and($props)->not->toHaveKey('dados');
});

it('UC-AMB-04 · PIN certo abre Meus dados e os consentimentos', function () {
    $g = $this->servico->emitir($this->a98);
    $this->postJson('/associacoes/m/'.$g['token'].'/pin', ['pin' => $g['pin']])->assertOk();

    $props = assocMembroProps($this, $g['token']);
    expect($props['etapa'])->toBe('dados')
        ->and($props['dados']['situacao'])->toBe('ativa')
        ->and($props['dados']['entrou_em'])->toBe($this->a98->entrou_em->toDateString())
        ->and(collect($props['consentimentos'])->firstWhere('finalidade', 'whatsapp')['vigente'])->toBeTrue()
        ->and(collect($props['consentimentos'])->firstWhere('finalidade', 'visivel_rede')['vigente'])->toBeFalse()
        ->and(collect($props['consentimentos'])->firstWhere('finalidade', 'associada')['revogavel'])->toBeFalse();
});

it('UC-AMB-05 · revogar grava revoked_at e não apaga; o consentimento associada não se revoga aqui', function () {
    $g = $this->servico->emitir($this->a98);

    // Sem PIN, nada.
    $this->deleteJson('/associacoes/m/'.$g['token'].'/consentimentos/whatsapp')->assertForbidden();

    $this->postJson('/associacoes/m/'.$g['token'].'/pin', ['pin' => $g['pin']])->assertOk();
    $this->deleteJson('/associacoes/m/'.$g['token'].'/consentimentos/whatsapp')->assertOk()
        ->assertJsonPath('consentimentos.1.finalidade', 'whatsapp')
        ->assertJsonPath('consentimentos.1.vigente', false);

    // SUPERADMIN: leitura das linhas da fixture.
    $linhas = ConsentimentoAssociada::withoutGlobalScopes()->where('associada_id', $this->a98->id)->get();
    expect($linhas)->toHaveCount(2)
        ->and($linhas->firstWhere('finalidade', 'whatsapp')->revoked_at)->not->toBeNull();

    $this->deleteJson('/associacoes/m/'.$g['token'].'/consentimentos/associada')->assertStatus(422)->assertJsonPath('erro', 'so_pela_associacao');
    $this->deleteJson('/associacoes/m/'.$g['token'].'/consentimentos/inventada')->assertNotFound();
    expect($linhas->fresh()->firstWhere('finalidade', 'associada')->revoked_at)->toBeNull();
});

it('UC-AMB-06 · gerar e revogar o link: só quem edita e alcança a associada, nunca de outra empresa', function () {
    $this->actingAs($this->u98);
    session(['user.business_id' => $this->b98]);

    $r = $this->postJson('/associacoes/associadas/'.$this->a98->id.'/portal')->assertCreated();
    expect($r->json('link'))->toContain('/associacoes/m/')->and($r->json('pin'))->toMatch('/^\d{4}$/');

    // Associada da 99 não existe para a 98.
    $u99 = $this->usuarioComPermissoes([AssociacoesAcesso::PERMISSAO], $this->seededSupportClientTenant());
    $a99 = assocMembroAssociada($this->b99, (int) $u99->id);
    $this->postJson('/associacoes/associadas/'.$a99->id.'/portal')->assertNotFound();

    // Sem associacoes.editar: 403.
    $leitor = $this->usuarioComPermissoes([AssociacoesAcesso::PERMISSAO, AssociacoesAcesso::PERMISSAO_REDE], $this->seededTenant());
    $this->actingAs($leitor);
    $this->postJson('/associacoes/associadas/'.$this->a98->id.'/portal')->assertForbidden();
    // Âncora positiva: a recusa não emitiu nada (segue 1 link vivo, o primeiro).
    // SUPERADMIN: leitura das linhas da fixture.
    expect(PortalAssociada::withoutGlobalScopes()->where('associada_id', $this->a98->id)->whereNull('revogado_em')->count())->toBe(1);

    // Revogar marca revogado_em e não apaga.
    $this->actingAs($this->u98);
    $this->deleteJson('/associacoes/associadas/'.$this->a98->id.'/portal')->assertOk()->assertJsonPath('revogados', 1);
    // SUPERADMIN: leitura da linha da fixture.
    $linha = PortalAssociada::withoutGlobalScopes()->where('associada_id', $this->a98->id)->firstOrFail();
    expect($linha->revogado_em)->not->toBeNull();
});
