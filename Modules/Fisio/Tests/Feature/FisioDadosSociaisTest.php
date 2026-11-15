<?php

declare(strict_types=1);
// @covers-us US-FISIO-061
// Cobre UC-FSO-01..05 (Fisio/Pacientes/Show.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Services\Pesquisa\EstudoService;
use Modules\Fisio\Services\Social\DadosSociais;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Dados sociais do paciente (US-FISIO-061): todos opcionais, dado sensível (LGPD art. 11).
 * Nascimento, município, UF e bairro moram no contato; o resto em `fisio_patient_social`.
 * Nada disso vai ao portal nem à exportação da pesquisa. Tenant 98 contra 99. ⚠️ SKIP em SQLite (LC-13).
 *
 * CNS fictício: 123456789010000 fecha o dígito (PIS 12345678901, soma 440, resto 0).
 */
const FSO_CNS = '123456789010000';

beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_patient_social', 'fisio_settings'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, (int) $this->seededSupportClientTenant()->id]);
    $this->p = F::paciente($this->b98, $this->u98->id);
    F::como($this, $this->u98);
});

function fsoPayload(array $mudar = []): array
{
    return array_merge([
        'nascimento' => '1975-03-14', 'municipio' => 'Tubarão', 'uf' => 'SC', 'bairro' => 'Centro',
        'sexo' => 'feminino', 'genero' => 'mulher', 'raca_cor' => 'parda', 'cns' => '1234 5678 9010 000',
        'renda_familiar' => '1_a_2_sm', 'cadunico' => 'sim', 'situacao_trabalho' => 'afastado_inss',
        'escolaridade' => 'medio_completo', 'diagnostico_cid' => 'm79.7', 'diagnostico_por' => 'reumatologista',
        'diagnostico_em' => '2020-05-02', 'sintomas_ha' => '5_a_10_anos', 'comorbidades' => ['depressao', 'disturbio_sono'],
        'comorbidades_outras' => 'Fibrose cística', 'como_chegou' => 'ubs_caps', 'cipf' => 'em_andamento',
    ], $mudar);
}

it('UC-FSO-01 · grava os dados sociais; nascimento, município e bairro vão para o contato; nada é obrigatório', function () {
    $this->put("/fisio/pacientes/{$this->p->id}/dados-sociais", [])->assertSessionHasNoErrors()->assertRedirect();
    // A tela manda todos os campos nulos quando ninguém respondeu: também não cria linha vazia.
    $this->put("/fisio/pacientes/{$this->p->id}/dados-sociais", array_fill_keys(array_keys(fsoPayload()), null))->assertSessionHasNoErrors();
    expect(DB::table('fisio_patient_social')->where('patient_id', $this->p->id)->exists())->toBeFalse();

    $this->put("/fisio/pacientes/{$this->p->id}/dados-sociais", fsoPayload())->assertSessionHasNoErrors()->assertRedirect()
        ->assertSessionHas('success', 'Dados sociais atualizados.');

    $c = DB::table('contacts')->where('id', $this->p->contact_id)->first();
    expect(substr((string) $c->dob, 0, 10))->toBe('1975-03-14')
        ->and($c->city)->toBe('Tubarão')->and($c->state)->toBe('SC')->and($c->neighborhood)->toBe('Centro');

    $s = DB::table('fisio_patient_social')->where('patient_id', $this->p->id)->first();
    expect((int) $s->business_id)->toBe($this->b98)
        ->and($s->cns)->toBe(FSO_CNS)
        ->and($s->diagnostico_cid)->toBe('M79.7')
        ->and($s->situacao_trabalho)->toBe('afastado_inss')
        ->and(json_decode((string) $s->comorbidades, true))->toBe(['depressao', 'disturbio_sono']);

    // "Prefiro não informar" é resposta; limpar o campo grava nulo sem apagar a linha.
    $this->put("/fisio/pacientes/{$this->p->id}/dados-sociais", ['raca_cor' => 'prefiro_nao_informar', 'cns' => ''])->assertSessionHasNoErrors();
    $s = DB::table('fisio_patient_social')->where('patient_id', $this->p->id)->first();
    expect($s->raca_cor)->toBe('prefiro_nao_informar')->and($s->cns)->toBeNull()->and($s->escolaridade)->toBe('medio_completo');
});

it('UC-FSO-02 · cartão SUS com dígito errado é recusado; o válido aceita pontuação', function () {
    expect(DadosSociais::cnsValido(FSO_CNS))->toBeTrue()
        ->and(DadosSociais::cnsValido('123456789010001'))->toBeFalse()
        ->and(DadosSociais::cnsValido('12345678901000'))->toBeFalse()
        ->and(DadosSociais::cnsValido('300000000000000'))->toBeFalse();

    $this->put("/fisio/pacientes/{$this->p->id}/dados-sociais", ['cns' => '123456789010001'])->assertSessionHasErrors('cns');
    $this->put("/fisio/pacientes/{$this->p->id}/dados-sociais", ['raca_cor' => 'azul'])->assertSessionHasErrors('raca_cor');
    $this->put("/fisio/pacientes/{$this->p->id}/dados-sociais", ['nascimento' => now()->addDay()->toDateString()])->assertSessionHasErrors('nascimento');
    expect(DB::table('fisio_patient_social')->where('patient_id', $this->p->id)->exists())->toBeFalse();
});

it('UC-FSO-03 · sem fisio.editar 403, outra empresa 404, bloqueado 422; nada é gravado [T0]', function () {
    $leitor = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    F::como($this, $leitor);
    $this->put("/fisio/pacientes/{$this->p->id}/dados-sociais", fsoPayload())->assertForbidden();

    F::como($this, $this->u99);
    $this->put("/fisio/pacientes/{$this->p->id}/dados-sociais", fsoPayload())->assertNotFound();
    $this->get("/fisio/pacientes/{$this->p->id}")->assertNotFound();

    F::como($this, $this->u98);
    DB::table('fisio_patients')->where('id', $this->p->id)->update(['blocked_at' => now()]);
    $this->put("/fisio/pacientes/{$this->p->id}/dados-sociais", fsoPayload())->assertSessionHasErrors('dados_sociais');

    expect(DB::table('fisio_patient_social')->where('patient_id', $this->p->id)->exists())->toBeFalse()
        ->and(DB::table('contacts')->where('id', $this->p->contact_id)->value('city'))->toBeNull();
});

it('UC-FSO-04 · dados sociais não vão ao portal do paciente nem à exportação da pesquisa', function () {
    $this->put("/fisio/pacientes/{$this->p->id}/dados-sociais", fsoPayload())->assertSessionHasNoErrors();

    $token = F::portalPronto($this, $this->p);
    $portal = json_encode(F::portalProps($this, $token));
    expect($portal)->not->toContain(FSO_CNS)->not->toContain('afastado_inss')->not->toContain('M79.7')->not->toContain('dados_sociais');

    $pesq = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR, 'fisio.pesquisa'], $this->seededTenant());
    $svc = app(EstudoService::class);
    $estudo = $svc->criar($this->b98, ['title' => 'Fibro', 'cep_opinion_number' => '1', 'protocol_version' => 'p1',
        'tcle_version' => 't1', 'starts_on' => now()->toDateString()], [['label' => 'Entrada', 'day_offset' => 0, 'window_days' => 3, 'instruments' => ['eva']]], null);
    $svc->incluir($estudo, $this->p, null, null);
    F::como($this, $pesq);
    foreach (['momentos', 'diario', 'participantes', 'dicionario'] as $arquivo) {
        $csv = (string) $this->get("/fisio/pesquisa/estudos/{$estudo->id}/exportar/{$arquivo}")->assertOk()->getContent();
        expect($csv)->not->toContain(FSO_CNS)->not->toContain('afastado_inss')->not->toContain('Tubarão')->not->toContain('1975-03-14');
    }
});

it('UC-FSO-05 · a ficha recebe as listas junto e os dados por prop deferida; o log guarda só os nomes dos campos', function () {
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $this->get("/fisio/pacientes/{$this->p->id}", ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao])->assertOk();
    expect($r->json('props'))->not->toHaveKey('dados_sociais')
        ->and($r->json('deferredProps.dados_sociais'))->toContain('dados_sociais')
        ->and($r->json('props.opcoes_sociais.opcoes.raca_cor'))->toHaveKey('indigena')
        ->and($r->json('props.opcoes_sociais.opcoes.situacao_trabalho'))->toHaveKey('afastado_inss');

    $this->put("/fisio/pacientes/{$this->p->id}/dados-sociais", fsoPayload())->assertSessionHasNoErrors();
    $dados = F::props($this, $this->u98, "/fisio/pacientes/{$this->p->id}", 'Fisio/Pacientes/Show', 'dados_sociais');
    expect($dados['cns'])->toBe(FSO_CNS)->and($dados['municipio'])->toBe('Tubarão')->and($dados['nascimento'])->toBe('1975-03-14')
        ->and($dados['comorbidades'])->toBe(['depressao', 'disturbio_sono']);

    $log = DB::table('activity_log')->where('description', 'dados_sociais_atualizados')->where('subject_id', $this->p->id)->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and(json_decode((string) $log->properties, true)['campos'])->toContain('cns', 'raca_cor', 'municipio');
    expect((string) $log->properties)->not->toContain(FSO_CNS)->not->toContain('parda')->not->toContain('Tubarão');
});
