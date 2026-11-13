<?php

declare(strict_types=1);
// @covers-us US-ASSOC-001

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Associacoes\Entities\Associacao;
use Modules\Associacoes\Entities\Associada;
use Modules\Associacoes\Entities\ConsentimentoAssociada;
use Modules\Associacoes\Entities\DocumentoAssociacao;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato da CAMADA DE DADOS do Associacoes (US-ASSOC-001 · ADR 0451 onda 2): toda tabela com
 * business_id e FK, escopo dos models entre os tenants 98 e 99 (ADR 0358), associada única por
 * empresa, consentimento e documento que não se apagam. Sem rota nem tela (ondas 3 a 6).
 *
 * ⚠️ SKIP em SQLite: schema UltimatePOS + trigger MySQL (LC-13). A lane é verticais-pest (MySQL).
 */
const ASSOC_TABELAS = ['assoc_associacoes', 'assoc_representantes', 'assoc_documentos', 'assoc_associadas', 'assoc_consentimentos'];

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS + trigger (ADR 0358).');
    }
    foreach (ASSOC_TABELAS as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — migrations do Modules/Associacoes não rodaram.");
        }
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes(['associacoes.access'], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes(['associacoes.access'], $this->seededSupportClientTenant());
});

function assocComo($t, $user): void
{
    $t->actingAs($user);
    session(['user.business_id' => (int) $user->business_id]);
}

function assocContato(int $biz, int $createdBy): int
{
    // contacts.created_by é FK NOT NULL -> users.id. Nome fictício: nenhum dado real em teste.
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Associada Fixture '.uniqid(),
        'mobile' => '48999990000', 'contact_status' => 'active', 'created_by' => $createdBy,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Associação + documento + associada + consentimento no business dado. */
function assocSemear(int $biz, int $userId): array
{
    // SUPERADMIN: fixture grava em duas empresas; o escopo é provado nos asserts.
    $assoc = Associacao::withoutGlobalScopes()->create([
        'business_id' => $biz, 'nome' => 'Associação Fixture '.uniqid(), 'cidade' => 'Cidade Fixture',
    ]);
    // SUPERADMIN: idem.
    $doc = DocumentoAssociacao::withoutGlobalScopes()->create([
        'business_id' => $biz, 'associacao_id' => $assoc->id, 'tipo' => 'estatuto', 'enviado_em' => now(),
    ]);
    // SUPERADMIN: idem.
    $assda = Associada::withoutGlobalScopes()->create([
        'business_id' => $biz, 'associacao_id' => $assoc->id, 'contact_id' => assocContato($biz, $userId),
        'entrou_em' => now()->toDateString(),
    ]);
    // SUPERADMIN: idem.
    $cons = ConsentimentoAssociada::withoutGlobalScopes()->create([
        'business_id' => $biz, 'associada_id' => $assda->id, 'finalidade' => 'associada',
        'versao' => '1', 'concedido_em' => now(), 'canal' => 'cadastro',
    ]);

    return ['associacao' => $assoc, 'documento' => $doc, 'associada' => $assda, 'consentimento' => $cons];
}

it('US-ASSOC-001 · toda tabela do módulo tem business_id com FK para business', function () {
    $fks = collect(DB::select(
        "SELECT TABLE_NAME AS t FROM information_schema.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'business_id' AND REFERENCED_TABLE_NAME = 'business'"
    ))->pluck('t')->all();

    foreach (ASSOC_TABELAS as $t) {
        expect(Schema::hasColumn($t, 'business_id'))->toBeTrue()->and($fks)->toContain($t);
    }
});

it('US-ASSOC-001 · sessão da 98 enxerga só a 98 nas tabelas do módulo', function () {
    $a98 = assocSemear($this->b98, (int) $this->u98->id);
    $a99 = assocSemear($this->b99, (int) $this->u99->id);

    assocComo($this, $this->u98);
    foreach ([Associacao::class, DocumentoAssociacao::class, Associada::class, ConsentimentoAssociada::class] as $model) {
        expect($model::query()->pluck('business_id')->map('intval')->unique()->values()->all())->toBe([$this->b98]);
    }
    expect(Associada::query()->find($a99['associada']->id))->toBeNull();

    // Discriminante: como 99, o filtro troca de lado em vez de esvaziar.
    assocComo($this, $this->u99);
    expect(Associada::query()->pluck('id')->all())->toContain($a99['associada']->id)->not->toContain($a98['associada']->id);

    // Controle negativo: sem escopo as duas linhas existem — o filtro acima é real.
    // SUPERADMIN: controle negativo do teste de isolamento.
    expect(Associada::withoutGlobalScopes()->whereIn('id', [$a98['associada']->id, $a99['associada']->id])->count())->toBe(2);
});

it('US-ASSOC-001 · a mesma pessoa não entra duas vezes na rede', function () {
    $a = assocSemear($this->b98, (int) $this->u98->id);

    expect(fn () => Associada::withoutGlobalScopes()->create([ // SUPERADMIN: fixture
        'business_id' => $this->b98, 'associacao_id' => $a['associacao']->id,
        'contact_id' => $a['associada']->contact_id, 'entrou_em' => now()->toDateString(),
    ]))->toThrow(QueryException::class);
});

it('US-ASSOC-001 · consentimento é prova: banco e Model recusam apagar', function () {
    $cons = assocSemear($this->b98, (int) $this->u98->id)['consentimento'];

    expect(fn () => DB::table('assoc_consentimentos')->where('id', $cons->id)->delete())->toThrow(QueryException::class);
    expect(fn () => $cons->delete())->toThrow(LogicException::class);

    // Revogar é preencher revoked_at — a linha continua lá.
    $cons->forceFill(['revoked_at' => now()])->save();
    expect(DB::table('assoc_consentimentos')->where('id', $cons->id)->whereNotNull('revoked_at')->exists())->toBeTrue();
});

it('US-ASSOC-001 · documento da associação não se apaga pelo Model', function () {
    $doc = assocSemear($this->b98, (int) $this->u98->id)['documento'];

    expect(fn () => $doc->delete())->toThrow(LogicException::class);
    expect(DB::table('assoc_documentos')->where('id', $doc->id)->exists())->toBeTrue();
});
