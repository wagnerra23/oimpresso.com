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
use Modules\ComunicacaoVisual\Services\ProtocoloInstalacao;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\WithSeededTenant;

/**
 * Protocolo da instalação em PDF (US-COMVIS-007 · UC-PCV-25 · 1c) — `GET /comunicacao-visual/instalacoes/{id}/protocolo`.
 *
 * Âncora: SPEC US-COMVIS-007 ("Comprovante instalação: foto + assinatura digital") e o UC-PCV-25 do protótipo
 * da Produção ("PDF com fotos antes/depois, checklist, horário e assinatura … fica na OS"). Contrato:
 * resources/js/Pages/ComunicacaoVisual/Instalacao.casos.md UC-CVCEL-07..09.
 *
 * Duas camadas, como no FisioFolhaProgramaTest: CONTEÚDO pelo HTML que a view renderiza com o que o serviço
 * monta (é a mesma fonte que o dompdf recebe), e HTTP pelo binário (`%PDF-`) e pelos códigos 403/404/422.
 *
 * Arranjo (tenant 98 contra 99, ADR 0358): instalação com cliente e endereço inventados; foto antes, foto
 * depois e assinatura gravadas pelo ComprovanteInstalacao no disco fake do módulo Arquivos.
 * ⚠️ SKIP em SQLite ou sem as tabelas: leia assertions, não "0 failed" (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

const CVPROT_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

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
    Carbon::setTestNow('2026-11-19 11:10:00');

    $this->instalador = \App\User::factory()->create(['business_id' => $this->biz->id, 'first_name' => 'Instalador', 'last_name' => 'Gama']);
    $contato = DB::table('contacts')->insertGetId([
        'business_id' => $this->biz->id, 'type' => 'customer', 'name' => 'Padaria Exemplo Teste', 'mobile' => '',
        'address_line_1' => 'Rua das Placas, 100', 'city' => 'Cidade Teste', 'state' => 'SC',
        'contact_status' => 'active', 'created_by' => $this->instalador->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $instalacao = function (int $biz, int $quem, ?int $contato) {
        $orc = DB::table('comvis_orcamentos')->insertGetId([
            'business_id' => $biz, 'numero' => 'ORC-'.substr(uniqid(), -10), 'contato_id' => $contato,
            'data_emissao' => '2026-11-10', 'status' => 'aprovado', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $os = DB::table('comvis_os')->insertGetId([
            'business_id' => $biz, 'orcamento_id' => $orc, 'numero' => 'PR-'.substr(uniqid(), -10),
            'status_etapa' => 'instalacao', 'valor_total' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('cv_instalacoes')->insertGetId([
            'business_id' => $biz, 'os_id' => $os, 'status' => 'agendada', 'veiculo' => 'Fiorino ABC1D23',
            'equipe_user_ids_json' => json_encode([$quem]), 'data_agendada' => '2026-11-19 09:00:00',
            'data_fim' => '2026-11-19 11:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
    };
    $this->inst = $instalacao($this->biz->id, $this->instalador->id, $contato);
    $alheio = \App\User::factory()->create(['business_id' => $this->outra->id]);
    $this->instOutra = $instalacao($this->outra->id, $alheio->id, null);
});

afterEach(fn () => Carbon::setTestNow());

/** Grava foto antes, foto depois e assinatura; com $concluir, conclui. Como o instalador faria pela API. */
function cvProtComprovante($t, bool $concluir = true): void
{
    $c = app(ComprovanteInstalacao::class);
    foreach (['foto_antes', 'foto_depois'] as $tipo) {
        $caminho = tempnam(sys_get_temp_dir(), 'cvp');
        file_put_contents($caminho, base64_decode(CVPROT_PNG));
        $c->foto($t->biz->id, $t->inst, $tipo, new UploadedFile($caminho, $tipo.'.png', 'image/png', null, true), $t->instalador->id);
    }
    $c->assinatura($t->biz->id, $t->inst, 'data:image/png;base64,'.CVPROT_PNG, 'Marta Ribeiro', true, $t->instalador->id);
    if ($concluir) {
        $c->concluir($t->biz->id, $t->inst);
    }
}

/** Chama a rota do protocolo como o navegador, com a sessão da empresa do usuário. */
function cvProtBaixar($t, \App\User $user, int $id): \Symfony\Component\HttpFoundation\Response
{
    $t->actingAs($user);
    session(['user.business_id' => $user->business_id, 'business.id' => $user->business_id, 'user.id' => $user->id]);
    app()->instance('request', Request::create("/comunicacao-visual/instalacoes/{$id}/protocolo", 'GET'));
    try {
        return app()->call([app(InstalacaoCampoController::class), 'protocolo'], ['id' => $id]);
    } catch (HttpException $e) {
        return response($e->getMessage(), $e->getStatusCode());
    }
}

it('UC-CVCEL-07 · o protocolo traz OS, cliente, endereço, horários, equipe, as duas fotos e a assinatura com o aceite', function () {
    cvProtComprovante($this);
    $dados = app(ProtocoloInstalacao::class)->montar($this->biz->id, $this->inst);
    $html = view(ProtocoloInstalacao::VIEW, $dados)->render();

    expect($dados['instalacao']['os_numero'])->toStartWith('PR-');
    foreach ([$dados['instalacao']['os_numero'], 'Padaria Exemplo Teste', 'Rua das Placas, 100 — Cidade Teste/SC',
        '19/11/2026 09:00 às 11:00', 'Instalador Gama', 'Fiorino ABC1D23', 'Assinado por Marta Ribeiro',
        'O cliente aceitou que a assinatura fique guardada como comprovante em 19/11/2026 11:10'] as $trecho) {
        expect($html)->toContain($trecho);
    }
    // Concluída agora (data_realizada = hora do teste) e as três imagens embutidas, sem nota de falta.
    expect($dados['concluida'])->toBe('19/11/2026 11:10')
        ->and(substr_count($html, 'src="data:image/png;base64,'))->toBe(3)
        ->and($html)->not->toContain('Não registrada.')
        ->and($html)->not->toContain('Arquivo indisponível.');
});

it('UC-CVCEL-08 · instalação ainda não concluída não tem protocolo: 422 com o motivo', function () {
    cvProtComprovante($this, concluir: false);
    $user = $this->usuarioComPermissoes(['comvis.os.view'], $this->biz);

    $r = cvProtBaixar($this, $user, $this->inst);
    expect($r->getStatusCode())->toBe(422)
        ->and($r->getContent())->toBe('O protocolo sai depois de concluir a instalação.');
});

it('UC-CVCEL-09 · concluída devolve o PDF; outra empresa é não encontrada; sem permissão 403', function () {
    cvProtComprovante($this);
    $user = $this->usuarioComPermissoes(['comvis.os.view'], $this->biz);

    $r = cvProtBaixar($this, $user, $this->inst);
    expect($r->getStatusCode())->toBe(200)
        ->and((string) $r->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(substr((string) $r->getContent(), 0, 5))->toBe('%PDF-');

    expect(cvProtBaixar($this, $user, $this->instOutra)->getStatusCode())->toBe(404);
    $semNada = \App\User::factory()->create(['business_id' => $this->biz->id]);
    expect(cvProtBaixar($this, $semNada, $this->inst)->getStatusCode())->toBe(403);
});
