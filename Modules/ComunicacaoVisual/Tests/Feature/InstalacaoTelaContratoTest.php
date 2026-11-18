<?php
// @covers-us US-COMVIS-007

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\ComunicacaoVisual\Http\Controllers\InstalacaoCampoController;
use Modules\ComunicacaoVisual\Services\ComprovanteInstalacao;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\WithSeededTenant;

/**
 * Tela do instalador no celular (US-COMVIS-007 · UC-PCV-25) — `GET /comunicacao-visual/instalacoes/{id}`.
 *
 * Âncora: SPEC US-COMVIS-007 ("Comprovante instalação: foto + assinatura digital") e o UC-PCV-25 do
 * protótipo da Produção. Contrato: resources/js/Pages/ComunicacaoVisual/Instalacao.casos.md UC-CVCEL-01..03.
 * O envio da foto pela tela (UC-CVCEL-04) mora em tests/js/cv-instalacao.test.tsx.
 *
 * Arranjo (tenant 98 contra 99, ADR 0358): uma instalação em cada empresa; a desta empresa é de uma OS cujo
 * orçamento é de um cliente com endereço. Nome e endereço são inventados.
 * ⚠️ SKIP em SQLite ou sem as tabelas: leia assertions, não "0 failed" (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer MySQL.');
    }
    foreach (['cv_instalacao_comprovantes', 'arquivos', 'comvis_os', 'comvis_orcamentos'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    Storage::fake(config('arquivos.disk_default', 'local'));
    Storage::fake('arquivos');
    Carbon::setTestNow('2026-11-18 08:00:00');

    $this->instalador = \App\User::factory()->create(['business_id' => $this->biz->id, 'first_name' => 'Instalador', 'last_name' => 'Gama']);
    $alheio = \App\User::factory()->create(['business_id' => $this->outra->id]);

    $instalacao = function (int $biz, int $quem, ?int $contato) {
        $orc = DB::table('comvis_orcamentos')->insertGetId([
            'business_id' => $biz, 'numero' => 'ORC-'.substr(uniqid(), -10), 'contato_id' => $contato,
            'data_emissao' => '2026-11-10', 'status' => 'aprovado', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $os = DB::table('comvis_os')->insertGetId([
            'business_id' => $biz, 'orcamento_id' => $orc, 'numero' => 'CA-'.substr(uniqid(), -10),
            'status_etapa' => 'instalacao', 'valor_total' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('cv_instalacoes')->insertGetId([
            'business_id' => $biz, 'os_id' => $os, 'status' => 'agendada', 'veiculo' => 'Fiorino ABC1D23',
            'equipe_user_ids_json' => json_encode([$quem]), 'data_agendada' => '2026-11-19 09:00:00',
            'data_fim' => '2026-11-19 11:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
    };
    $this->contato = DB::table('contacts')->insertGetId([
        'business_id' => $this->biz->id, 'type' => 'customer', 'name' => 'Padaria Exemplo Teste', 'mobile' => '',
        'address_line_1' => 'Rua das Placas, 100', 'city' => 'Cidade Teste', 'state' => 'SC',
        'contact_status' => 'active', 'created_by' => $this->instalador->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->inst = $instalacao($this->biz->id, $this->instalador->id, $this->contato);
    $this->instOutra = $instalacao($this->outra->id, $alheio->id, null);
});

afterEach(fn () => Carbon::setTestNow());

/** Abre a tela como o navegador abriria (visita Inertia) e devolve o "page" JSON. */
function cvCampoPagina($t, \App\User $user, int $id): array
{
    $t->actingAs($user);
    session(['user.business_id' => $user->business_id, 'business.id' => $user->business_id, 'user.id' => $user->id]);
    $request = Request::create("/comunicacao-visual/instalacoes/{$id}", 'GET');
    $request->headers->set('X-Inertia', 'true');
    $request->headers->set('X-Requested-With', 'XMLHttpRequest');
    app()->instance('request', $request);

    try {
        $resposta = app()->call([app(InstalacaoCampoController::class), 'show'], ['id' => $id]);
    } catch (HttpException $e) {
        return ['status' => $e->getStatusCode()];
    }

    return ['status' => 200] + json_decode($resposta->toResponse($request)->getContent(), true);
}

it('UC-CVCEL-01 · a tela mostra OS, cliente, endereço, horário, equipe e o que o comprovante já tem', function () {
    $caminho = tempnam(sys_get_temp_dir(), 'cvt');
    file_put_contents($caminho, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    app(ComprovanteInstalacao::class)->foto($this->biz->id, $this->inst, 'foto_antes',
        new UploadedFile($caminho, 'antes.png', 'image/png', null, true), $this->instalador->id);

    $pagina = cvCampoPagina($this, $this->usuarioComPermissoes(['comvis.os.update_status'], $this->biz), $this->inst);
    expect($pagina['status'])->toBe(200)->and($pagina['component'])->toBe('ComunicacaoVisual/Instalacao');

    $i = $pagina['props']['instalacao'];
    expect($i['id'])->toBe($this->inst)
        ->and($i['os_numero'])->toStartWith('CA-')
        ->and($i['cliente'])->toBe('Padaria Exemplo Teste')
        ->and($i['endereco'])->toBe('Rua das Placas, 100 — Cidade Teste/SC')
        ->and(substr($i['inicio'], 0, 16))->toBe('2026-11-19T09:00')
        ->and(substr($i['fim'], 0, 16))->toBe('2026-11-19T11:00')
        ->and($i['equipe'])->toBe(['Instalador Gama'])
        ->and($i['veiculo'])->toBe('Fiorino ABC1D23')
        ->and($i['status'])->toBe('agendada');

    $c = $pagina['props']['comprovante'];
    expect($c['foto_antes'])->toBeTrue()->and($c['foto_depois'])->toBeFalse()
        ->and($c['assinatura'])->toBeNull()->and($c['pode_concluir'])->toBeFalse();
});

it('UC-CVCEL-02 · instalação de outra empresa é não encontrada', function () {
    $user = $this->usuarioComPermissoes(['comvis.os.update_status'], $this->biz);
    expect(cvCampoPagina($this, $user, $this->instOutra)['status'])->toBe(404);
    // Controle: a da própria empresa abre.
    expect(cvCampoPagina($this, $user, $this->inst)['status'])->toBe(200);
});

it('UC-CVCEL-03 · sem permissão não abre; quem só vê abre sem poder registrar; quem atualiza a OS registra', function () {
    $semNada = \App\User::factory()->create(['business_id' => $this->biz->id]);
    expect(cvCampoPagina($this, $semNada, $this->inst)['status'])->toBe(403);

    $soVe = cvCampoPagina($this, $this->usuarioComPermissoes(['comvis.os.view'], $this->biz), $this->inst);
    expect($soVe['status'])->toBe(200)->and($soVe['props']['podeRegistrar'])->toBeFalse();

    $registra = cvCampoPagina($this, $this->usuarioComPermissoes(['comvis.os.update_status'], $this->biz), $this->inst);
    expect($registra['status'])->toBe(200)->and($registra['props']['podeRegistrar'])->toBeTrue();
});
