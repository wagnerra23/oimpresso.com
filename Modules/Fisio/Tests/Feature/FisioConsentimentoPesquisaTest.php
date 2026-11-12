<?php

declare(strict_types=1);
// @covers-us US-FISIO-007
// Cobre UC-FPP-56..UC-FPP-58 e UC-FPP-62 (Fisio/Public/Portal.casos.md) · etapa 07 P-02.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\Study;
use Modules\Fisio\Services\Consentimentos;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Consentimento de pesquisa POR ESTUDO, separado do treino de IA (etapa 07 P-02, ADR 0437 §7).
 *
 * A versão gravada vem do estudo (TCLE aprovado pelo CEP), nunca do request. O antigo
 * `pesquisa_ia` não vale para nada. Tenant 98 contra 99. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_studies'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    Carbon::setTestNow('2026-11-11 09:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    F::pacote([$this->b98, $this->b99]);
    $this->p = F::paciente($this->b98, $this->u98->id);
    F::programa($this->p, [F::exercicio(null, 'Ponte')], $this->u98->id);
    $this->svc = app(Consentimentos::class);
});

afterEach(fn () => Carbon::setTestNow());

function fisioPesqEstudo(int $biz, array $attrs = []): Study
{
    return Study::withoutGlobalScopes()->create($attrs + [ // SUPERADMIN: fixture grava no tenant pedido.
        'business_id' => $biz,
        'title' => 'Fibromialgia — coorte',
        'cep_opinion_number' => '7.000.000',
        'protocol_version' => 'p1',
        'tcle_version' => 'tcle-2',
        'starts_on' => '2026-11-01',
        'status' => Study::STATUS_ATIVO,
    ]);
}

it('UC-FPP-56 · pesquisa grava o estudo e a versão do TCLE dele; pesquisa_ia antiga não vale e treino_ia é outra finalidade', function () {
    F::consentir($this->p, 'pesquisa_ia', 'v1');
    $estudo = fisioPesqEstudo($this->b98);

    // O aceite antigo não faz ninguém participante nem dá treino de IA.
    expect($this->svc->pesquisaVigente($this->p, $estudo))->toBeFalse()
        ->and($this->svc->finalidades())->not->toContain('pesquisa_ia')
        ->and($this->svc->finalidades())->not->toContain('pesquisa')
        ->and($this->svc->finalidades())->toContain('treino_ia')
        ->and($this->svc->situacao($this->p)['treino_ia'])->toBeFalse();

    $r = $this->svc->concederPesquisa($this->p, $estudo, '127.0.0.1', 'clinica');
    $c = DB::table('fisio_consents')->where('id', $r['consentimento']->id)->first();
    expect($r['criado'])->toBeTrue()
        ->and($c->purpose)->toBe('pesquisa')
        ->and((int) $c->study_id)->toBe($estudo->id)
        ->and($c->version)->toBe('tcle-2')
        ->and($this->svc->pesquisaVigente($this->p, $estudo))->toBeTrue()
        ->and($this->svc->situacao($this->p)['treino_ia'])->toBeFalse();

    // Aceitar de novo não duplica; outro estudo não herda o aceite.
    expect($this->svc->concederPesquisa($this->p, $estudo, null, 'clinica')['criado'])->toBeFalse()
        ->and($this->svc->pesquisaVigente($this->p, fisioPesqEstudo($this->b98, ['title' => 'Outro'])))->toBeFalse();

    // Nova versão do TCLE aprovada: o aceite antigo deixa de valer.
    $estudo->update(['tcle_version' => 'tcle-3']);
    expect($this->svc->pesquisaVigente($this->p, $estudo->fresh()))->toBeFalse();
});

it('UC-FPP-57 · o portal genérico não concede pesquisa nem pesquisa_ia; revogar a pesquisa de um estudo mantém a linha', function () {
    $estudo = fisioPesqEstudo($this->b98);
    $this->svc->concederPesquisa($this->p, $estudo, null, 'clinica');
    $token = F::portalPronto($this, $this->p);

    $this->postJson('/fisio/p/'.$token.'/consentimentos', ['purpose' => 'pesquisa', 'aceito' => true])->assertStatus(422);
    $this->postJson('/fisio/p/'.$token.'/consentimentos', ['purpose' => 'pesquisa_ia', 'aceito' => true])->assertStatus(422);
    $this->postJson('/fisio/p/'.$token.'/consentimentos', ['purpose' => 'treino_ia', 'aceito' => true])->assertCreated();

    $this->deleteJson('/fisio/p/'.$token.'/consentimentos/pesquisa/'.$estudo->id)->assertOk()->assertJsonPath('revogados', 1);
    $linhas = DB::table('fisio_consents')->where('patient_id', $this->p->id)->where('purpose', 'pesquisa')->get();
    expect($linhas)->toHaveCount(1)
        ->and($linhas[0]->revoked_at)->not->toBeNull()
        ->and($this->svc->pesquisaVigente($this->p, $estudo))->toBeFalse()
        // Revogar a pesquisa não fecha o tratamento.
        ->and(F::portalProps($this, $token)['etapa'])->toBe('hoje');
});

it('UC-FPP-58 · estudo de outra clínica: não concede e o portal responde 404 [T0]', function () {
    $estudo99 = fisioPesqEstudo($this->b99);

    expect(fn () => $this->svc->concederPesquisa($this->p, $estudo99, null, 'clinica'))->toThrow(InvalidArgumentException::class)
        ->and($this->svc->pesquisaVigente($this->p, $estudo99))->toBeFalse();

    $token = F::portalPronto($this, $this->p);
    $this->deleteJson('/fisio/p/'.$token.'/consentimentos/pesquisa/'.$estudo99->id)->assertNotFound();

    // Estudo encerrado também não recebe aceite novo.
    $encerrado = fisioPesqEstudo($this->b98, ['status' => Study::STATUS_ENCERRADO]);
    expect(fn () => $this->svc->concederPesquisa($this->p, $encerrado, null, 'clinica'))->toThrow(InvalidArgumentException::class)
        ->and(DB::table('fisio_consents')->where('purpose', 'pesquisa')->where('patient_id', $this->p->id)->count())->toBe(0);
});

it('UC-FPP-62 · Configurações do portal lista o estudo aceito; depois de sair, ele some; estudo de outra clínica nunca aparece [T0]', function () {
    $estudo = fisioPesqEstudo($this->b98, ['title' => 'Estudo de fibromialgia']);
    $this->svc->concederPesquisa($this->p, $estudo, null, 'clinica');
    // Linha forjada apontando estudo da 99 (o serviço recusaria; aqui entra direto no banco).
    $estudo99 = fisioPesqEstudo($this->b99, ['title' => 'Estudo da outra clinica']);
    DB::table('fisio_consents')->insert([
        'business_id' => $this->b98, 'patient_id' => $this->p->id, 'purpose' => 'pesquisa',
        'study_id' => $estudo99->id, 'version' => 'tcle-2', 'granted_at' => now(), 'channel' => 'clinica',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $token = F::portalPronto($this, $this->p);

    $lista = F::portalProps($this, $token)['estudos_pesquisa'];
    expect($lista)->toHaveCount(1)
        ->and($lista[0]['study_id'])->toBe($estudo->id)
        ->and($lista[0]['titulo'])->toBe('Estudo de fibromialgia')
        ->and($lista[0]['versao'])->toBe('tcle-2')
        ->and($lista[0]['aceito_em'])->toBe('2026-11-11')
        ->and($lista[0]['canal'])->toBe('clinica');

    $this->deleteJson('/fisio/p/'.$token.'/consentimentos/pesquisa/'.$estudo->id)->assertOk();
    expect(F::portalProps($this, $token)['estudos_pesquisa'])->toBe([]);
});
