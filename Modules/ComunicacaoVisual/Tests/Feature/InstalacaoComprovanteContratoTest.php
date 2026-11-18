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
use Illuminate\Validation\ValidationException;
use Modules\ComunicacaoVisual\Entities\InstalacaoComprovante;
use Modules\ComunicacaoVisual\Http\Controllers\InstalacaoComprovanteController;
use Modules\ComunicacaoVisual\Services\AgendaInstalacao;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\WithSeededTenant;

/**
 * US-COMVIS-007 · UC-PCV-25 — comprovante da instalação de campo: foto antes, foto depois e assinatura do
 * cliente; só conclui com os três.
 *
 * Âncora: memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-007 ("Comprovante instalação: foto +
 * assinatura digital … LGPD: consent") e o UC-PCV-25 do protótipo da Produção. Contrato:
 * resources/js/Pages/ComunicacaoVisual/Index.casos.md UC-CVINST-11..14.
 *
 * Arranjo (tenant 98 contra 99, ADR 0358): uma instalação agendada em cada empresa. Os arquivos vão para o
 * disco fake do módulo Arquivos. ⚠️ SKIP em SQLite ou sem as tabelas: leia assertions, não "0 failed" (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

const CVCOMP_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer MySQL.');
    }
    foreach (['cv_instalacao_comprovantes', 'arquivos', 'comvis_os'] as $t) {
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

    $instalacao = function (int $biz) {
        $os = DB::table('comvis_os')->insertGetId(['business_id' => $biz, 'numero' => 'CP-'.substr(uniqid(), -10),
            'status_etapa' => 'instalacao', 'valor_total' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $quem = \App\User::factory()->create(['business_id' => $biz]);

        return app(AgendaInstalacao::class)->agendar($biz, $os, Carbon::parse('2026-11-19 09:00'), Carbon::parse('2026-11-19 11:00'), [$quem->id])->id;
    };
    $this->inst = $instalacao($this->biz->id);
    $this->instOutra = $instalacao($this->outra->id);
    $this->instalador = $this->usuarioComPermissoes(['comvis.os.update_status', 'comvis.os.view'], $this->biz);
});

afterEach(fn () => Carbon::setTestNow());

/** Chama o controller como a rota chamaria, com a sessão da empresa do usuário. */
function cvComp($t, string $acao, int $id, array $dados = [], array $arquivos = [], ?\App\User $user = null): \Illuminate\Http\JsonResponse
{
    $user ??= $t->instalador;
    $t->actingAs($user);
    session(['user.business_id' => $user->business_id, 'business.id' => $user->business_id, 'user.id' => $user->id]);
    app()->instance('request', Request::create('/comunicacao-visual/api/x', $acao === 'show' ? 'GET' : 'POST', $dados, [], $arquivos));
    try {
        return app()->call([app(InstalacaoComprovanteController::class), $acao], ['id' => $id]);
    } catch (HttpException $e) {
        return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
    } catch (ValidationException $e) {
        return response()->json(['message' => $e->getMessage()], 422);
    }
}

function cvFoto($t, int $id, string $tipo): \Illuminate\Http\JsonResponse
{
    $caminho = tempnam(sys_get_temp_dir(), 'cvf');
    file_put_contents($caminho, base64_decode(CVCOMP_PNG));

    return cvComp($t, 'foto', $id, ['tipo' => $tipo], ['arquivo' => new UploadedFile($caminho, $tipo.'.png', 'image/png', null, true)]);
}

function cvAssinar($t, int $id, bool $consentimento = true): \Illuminate\Http\JsonResponse
{
    return cvComp($t, 'assinatura', $id, ['png' => 'data:image/png;base64,'.CVCOMP_PNG, 'assinado_por' => 'Marta Ribeiro', 'consentimento' => $consentimento]);
}

it('UC-CVINST-11 · só conclui com foto antes, foto depois e assinatura; antes disso diz o que falta', function () {
    $r = cvComp($this, 'concluir', $this->inst);
    expect($r->getStatusCode())->toBe(422)
        ->and($r->getData(true)['message'])->toBe('Para concluir falta: foto antes, foto depois, assinatura do cliente.')
        ->and(DB::table('cv_instalacoes')->where('id', $this->inst)->value('status'))->toBe('agendada');

    expect(cvFoto($this, $this->inst, 'foto_antes')->getStatusCode())->toBe(201);
    expect(cvFoto($this, $this->inst, 'foto_depois')->getStatusCode())->toBe(201);
    $antes = cvComp($this, 'show', $this->inst)->getData(true);
    expect($antes['foto_antes'])->toBeTrue()->and($antes['foto_depois'])->toBeTrue()->and($antes['assinatura'])->toBeNull()
        ->and($antes['pode_concluir'])->toBeFalse();
    expect(cvComp($this, 'concluir', $this->inst)->getData(true)['message'])->toBe('Para concluir falta: assinatura do cliente.');

    expect(cvAssinar($this, $this->inst)->getStatusCode())->toBe(201);
    $r = cvComp($this, 'concluir', $this->inst);
    expect($r->getStatusCode())->toBe(200)->and($r->getData(true)['status'])->toBe('concluida');
    expect(DB::table('cv_instalacoes')->where('id', $this->inst)->value('data_realizada'))->toBe('2026-11-18 08:00:00');
});

it('UC-CVINST-12 · assinatura só com o aceite do cliente (LGPD), com o nome de quem assinou, e uma vez só', function () {
    $sem = cvAssinar($this, $this->inst, false);
    expect($sem->getStatusCode())->toBe(422)
        ->and(DB::table('cv_instalacao_comprovantes')->where('instalacao_id', $this->inst)->count())->toBe(0);

    expect(cvAssinar($this, $this->inst)->getStatusCode())->toBe(201);
    $linha = DB::table('cv_instalacao_comprovantes')->where('instalacao_id', $this->inst)->where('tipo', 'assinatura')->first();
    expect($linha->assinado_por)->toBe('Marta Ribeiro')->and($linha->consentimento_em)->toBe('2026-11-18 08:00:00')
        ->and((int) $linha->business_id)->toBe((int) $this->biz->id);
    // O PNG fica no módulo Arquivos, anexado à linha do comprovante (não à instalação).
    expect(DB::table('arquivos')->where('arquivable_type', InstalacaoComprovante::class)->where('arquivable_id', $linha->id)
        ->where('business_id', $this->biz->id)->whereNull('deleted_at')->count())->toBe(1);

    $de_novo = cvAssinar($this, $this->inst);
    expect($de_novo->getStatusCode())->toBe(422)
        ->and($de_novo->getData(true)['message'])->toBe('A assinatura desta instalação já foi colhida e não pode ser trocada.')
        ->and(DB::table('cv_instalacao_comprovantes')->where('instalacao_id', $this->inst)->where('tipo', 'assinatura')->count())->toBe(1);
});

it('UC-CVINST-13 · instalação de outra empresa é não encontrada em tudo, e nada é gravado nela', function () {
    expect(cvComp($this, 'show', $this->instOutra)->getStatusCode())->toBe(404)
        ->and(cvFoto($this, $this->instOutra, 'foto_antes')->getStatusCode())->toBe(404)
        ->and(cvAssinar($this, $this->instOutra)->getStatusCode())->toBe(404)
        ->and(cvComp($this, 'concluir', $this->instOutra)->getStatusCode())->toBe(404);
    expect(DB::table('cv_instalacao_comprovantes')->where('instalacao_id', $this->instOutra)->count())->toBe(0)
        ->and(DB::table('cv_instalacoes')->where('id', $this->instOutra)->value('status'))->toBe('agendada');
    // Controle: a da própria empresa responde.
    expect(cvComp($this, 'show', $this->inst)->getStatusCode())->toBe(200);
});

it('UC-CVINST-14 · foto refeita vale a mais nova; concluída não recebe mais nada; sem permissão 403', function () {
    cvFoto($this, $this->inst, 'foto_antes');
    cvFoto($this, $this->inst, 'foto_antes');
    expect(DB::table('cv_instalacao_comprovantes')->where('instalacao_id', $this->inst)->where('tipo', 'foto_antes')->count())->toBe(2)
        ->and(cvComp($this, 'show', $this->inst)->getData(true)['foto_antes'])->toBeTrue();

    cvFoto($this, $this->inst, 'foto_depois');
    cvAssinar($this, $this->inst);
    expect(cvComp($this, 'concluir', $this->inst)->getStatusCode())->toBe(200);
    $depois = cvFoto($this, $this->inst, 'foto_depois');
    expect($depois->getStatusCode())->toBe(422)
        ->and($depois->getData(true)['message'])->toBe('A instalação está concluída e não recebe mais comprovante.');

    $semPermissao = $this->usuarioComPermissoes([], $this->biz);
    expect(cvComp($this, 'show', $this->inst, user: $semPermissao)->getStatusCode())->toBe(403);
});
