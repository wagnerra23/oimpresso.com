<?php

declare(strict_types=1);
// @covers-us US-FISIO-005
// Cobre UC-FPI-05, UC-FPI-06 e UC-FPI-07 (Fisio/Pacientes/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Origem do paciente: o contact da empresa (ADR 0437 §4). Três regras:
 *  - contato apagado (soft-delete) não vira paciente;
 *  - a escolha do contato é por busca no servidor (nome ou celular, até 20, só da empresa,
 *    só sem ficha, nunca apagado);
 *  - o contato pode nascer no próprio cadastro, pelo ContactUtil do UltimatePOS, sem duplicar
 *    celular dentro da empresa.
 *
 * Tenant 98 contra o 99. NUNCA biz=4. ⚠️ SKIP em SQLite (LC-13) — leia assertions.
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip()) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, $this->b99]);
    F::como($this, $this->u98);
});

function fctPerfil(array $mudar = []): array
{
    return array_merge([
        'main_complaint' => 'Dor lombar ao sentar',
        'goals' => ['trabalhar sentado sem dor'],
        'sessions_per_week' => 3,
        'condition_flags' => [],
    ], $mudar);
}

/** Celular fictício e único por chamada (o teste nunca usa número real). */
function fctCelular(): string
{
    return '4890'.str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
}

/** @return list<array<string, mixed>> */
function fctBusca($t, string $q): array
{
    $r = $t->getJson('/fisio/pacientes/contatos?q='.urlencode($q));
    $r->assertOk();

    return (array) $r->json('contatos');
}

// ── UC-FPI-05 · contato apagado ───────────────────────────────────────────────

it('UC-FPI-05 · contato apagado (soft-delete) é recusado e nenhuma ficha nasce', function () {
    $apagado = F::contato($this->b98, $this->u98->id, 'Contato Apagado');
    DB::table('contacts')->where('id', $apagado)->update(['deleted_at' => now()]);

    $this->post('/fisio/pacientes', ['contact_id' => $apagado, ...fctPerfil()])->assertSessionHasErrors('contact_id');

    // SUPERADMIN: confere que nada nasceu, fora do escopo, para o recibo ser do banco.
    expect(Patient::withoutGlobalScopes()->where('contact_id', $apagado)->exists())->toBeFalse();

    // Controle positivo: o MESMO contato sem deleted_at é aceito — a recusa acima é pelo apagado.
    DB::table('contacts')->where('id', $apagado)->update(['deleted_at' => null]);
    $this->post('/fisio/pacientes', ['contact_id' => $apagado, ...fctPerfil()])->assertSessionHasNoErrors();
    expect(Patient::query()->where('contact_id', $apagado)->exists())->toBeTrue();
});

it('UC-FPI-05 · contato de outra empresa é recusado', function () {
    $alheio = F::contato($this->b99, $this->u99->id, 'Contato da 99');

    $this->post('/fisio/pacientes', ['contact_id' => $alheio, ...fctPerfil()])->assertSessionHasErrors('contact_id');

    // SUPERADMIN: verificação pós-tentativa nas duas empresas.
    expect(Patient::withoutGlobalScopes()->where('contact_id', $alheio)->exists())->toBeFalse();
});

// ── UC-FPI-06 · busca de contato no servidor ──────────────────────────────────

it('UC-FPI-06 · busca por nome e por celular, só da empresa, só sem ficha, nunca apagado', function () {
    $marca = 'Zq'.substr(uniqid(), -6);
    $cel = fctCelular();
    $livre = F::contato($this->b98, $this->u98->id, $marca.' Livre', $cel);
    $comFicha = F::contato($this->b98, $this->u98->id, $marca.' ComFicha');
    F::paciente($this->b98, $this->u98->id, ['contact_id' => $comFicha]);
    $apagado = F::contato($this->b98, $this->u98->id, $marca.' Apagado');
    DB::table('contacts')->where('id', $apagado)->update(['deleted_at' => now()]);
    $alheio = F::contato($this->b99, $this->u99->id, $marca.' DaOutra');
    $fornecedor = F::contato($this->b98, $this->u98->id, $marca.' Fornecedor');
    DB::table('contacts')->where('id', $fornecedor)->update(['type' => 'supplier']);

    $ids = collect(fctBusca($this, $marca))->pluck('id')->all();
    expect($ids)->toBe([$livre]);

    // Pelo celular (só dígitos, parte do número).
    $porCelular = collect(fctBusca($this, substr($cel, -7)))->pluck('id')->all();
    expect($porCelular)->toContain($livre)
        ->not->toContain($alheio);

    // Formato da resposta: só o que a tela usa.
    $item = fctBusca($this, $marca)[0];
    expect(array_keys($item))->toBe(['id', 'nome', 'telefone'])
        ->and($item['id'])->toBe($livre);

    // Discriminante 98 × 99: a mesma busca na 99 devolve o contato dela e não o da 98.
    F::como($this, $this->u99);
    $ids99 = collect(fctBusca($this, $marca))->pluck('id')->all();
    expect($ids99)->toBe([$alheio]);
});

it('UC-FPI-06 · devolve no máximo 20 contatos', function () {
    $marca = 'Lm'.substr(uniqid(), -6);
    foreach (range(1, 22) as $i) {
        F::contato($this->b98, $this->u98->id, $marca.' '.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
    }

    expect(fctBusca($this, $marca))->toHaveCount(20);
});

it('UC-FPI-06 · a tela não carrega mais a lista de contatos (a busca é no servidor)', function () {
    F::contato($this->b98, $this->u98->id, 'Qualquer Contato');

    $this->get('/fisio/pacientes')->assertOk()
        ->assertInertia(fn ($pg) => $pg->component('Fisio/Pacientes/Index', false)->missing('contatos'));
});

it('UC-FPI-06 · busca de contato exige fisio.editar', function () {
    $leitor = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    F::como($this, $leitor);

    $this->getJson('/fisio/pacientes/contatos?q=a')->assertForbidden();
});

// ── UC-FPI-07 · contato criado na hora ────────────────────────────────────────

it('UC-FPI-07 · cria o contato customer da empresa e a ficha ligada a ele', function () {
    $cel = fctCelular();
    $nome = 'Paciente Novo '.uniqid();

    $this->post('/fisio/pacientes', ['novo_contato' => ['nome' => $nome, 'telefone' => $cel], ...fctPerfil()])
        ->assertSessionHasNoErrors()->assertRedirect();

    $contato = DB::table('contacts')->where('business_id', $this->b98)->where('name', $nome)->first();
    expect($contato)->not->toBeNull()
        ->and($contato->type)->toBe('customer')
        ->and($contato->mobile)->toBe($cel)
        ->and((int) $contato->created_by)->toBe((int) $this->u98->id)
        // Caminho do ContactUtil: ele gera a referência (contacts.contact_id); um insert cru não.
        ->and((string) $contato->contact_id)->not->toBe('');

    $p = Patient::query()->where('contact_id', $contato->id)->firstOrFail();
    expect((int) $p->business_id)->toBe($this->b98)
        ->and((int) $p->sessions_per_week)->toBe(3);
});

it('UC-FPI-07 · celular já usado na empresa é recusado com sugestão e nada é criado', function () {
    $cel = fctCelular();
    $existente = F::contato($this->b98, $this->u98->id, 'Contato Existente', $cel);
    $antes = DB::table('contacts')->where('business_id', $this->b98)->count();

    // Mesmo número com máscara: a comparação é pelos dígitos.
    $mascarado = sprintf('(%s) %s %s-%s', substr($cel, 0, 2), substr($cel, 2, 1), substr($cel, 3, 4), substr($cel, 7));
    $r = $this->post('/fisio/pacientes', ['novo_contato' => ['nome' => 'Outro Nome', 'telefone' => $mascarado], ...fctPerfil()]);

    $r->assertSessionHasErrors('novo_contato.telefone');
    expect(session('errors')->first('novo_contato.telefone'))->toContain('Contato Existente');
    expect(DB::table('contacts')->where('business_id', $this->b98)->count())->toBe($antes)
        ->and(Patient::query()->where('contact_id', $existente)->exists())->toBeFalse();
});

it('UC-FPI-07 · celular igual em OUTRA empresa não bloqueia', function () {
    $cel = fctCelular();
    F::contato($this->b99, $this->u99->id, 'Contato da 99', $cel);
    $nome = 'Paciente 98 '.uniqid();

    $this->post('/fisio/pacientes', ['novo_contato' => ['nome' => $nome, 'telefone' => $cel], ...fctPerfil()])
        ->assertSessionHasNoErrors();

    expect(DB::table('contacts')->where('business_id', $this->b98)->where('name', $nome)->exists())->toBeTrue();
});

it('UC-FPI-07 · perfil clínico inválido não deixa contato órfão', function () {
    $nome = 'Sem Ficha '.uniqid();

    // sessions_per_week inválido: a validação recusa ANTES de criar qualquer coisa.
    $this->post('/fisio/pacientes', ['novo_contato' => ['nome' => $nome, 'telefone' => fctCelular()], ...fctPerfil(['sessions_per_week' => 0])])
        ->assertSessionHasErrors('sessions_per_week');

    expect(DB::table('contacts')->where('name', $nome)->exists())->toBeFalse();
});

it('UC-FPI-07 · nome e celular são obrigatórios no contato novo', function () {
    $this->post('/fisio/pacientes', ['novo_contato' => ['nome' => '', 'telefone' => ''], ...fctPerfil()])
        ->assertSessionHasErrors(['novo_contato.nome']);

    $this->post('/fisio/pacientes', ['novo_contato' => ['nome' => 'Só Nome '.uniqid(), 'telefone' => ''], ...fctPerfil()])
        ->assertSessionHasErrors(['novo_contato.telefone']);
});
