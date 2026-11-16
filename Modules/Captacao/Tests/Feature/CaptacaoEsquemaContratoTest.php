<?php

declare(strict_types=1);
// @covers-us US-CAPT-001

use App\Utils\ModuleUtil;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Captacao\Entities\Aporte;
use Modules\Captacao\Entities\Comprovante;
use Modules\Captacao\Entities\Fonte;
use Modules\Captacao\Entities\Projeto;
use Modules\Captacao\Services\CaptacaoAcesso;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato da CAMADA DE DADOS do Captacao (US-CAPT-001 · ADR 0452 onda 1): toda tabela com
 * business_id e FK, escopo dos models entre os tenants 98 e 99 (ADR 0358), aporte e comprovante
 * que não se apagam, e a regra de acesso pelo pacote. Sem rota de negócio nem tela (ondas 2 a 4).
 *
 * ⚠️ SKIP em SQLite: schema UltimatePOS (LC-13). A lane é verticais-pest (MySQL).
 */
const CAPT_TABELAS = ['captacao_fontes', 'captacao_projetos', 'captacao_aportes', 'captacao_comprovantes'];

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS (ADR 0358).');
    }
    foreach (CAPT_TABELAS as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — migrations do Modules/Captacao não rodaram.");
        }
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([CaptacaoAcesso::PERMISSAO], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([CaptacaoAcesso::PERMISSAO], $this->seededSupportClientTenant());
});

function captComo($t, $user): void
{
    $t->actingAs($user);
    session(['user.business_id' => (int) $user->business_id]);
}

/** Fonte + projeto + aporte + comprovante no business dado. Dados fictícios. */
function captSemear(int $biz): array
{
    // SUPERADMIN: fixture grava em duas empresas; o escopo é provado nos asserts.
    $fonte = Fonte::withoutGlobalScopes()->create([
        'business_id' => $biz, 'tipo' => 'edital', 'nome' => 'Edital Fixture '.uniqid(),
    ]);
    // SUPERADMIN: idem.
    $projeto = Projeto::withoutGlobalScopes()->create([
        'business_id' => $biz, 'titulo' => 'Projeto Fixture '.uniqid(), 'proponente_nome' => 'Proponente Fixture',
    ]);
    // SUPERADMIN: idem.
    $aporte = Aporte::withoutGlobalScopes()->create([
        'business_id' => $biz, 'projeto_id' => $projeto->id, 'fonte_id' => $fonte->id,
        'natureza' => 'horas_voluntarias', 'horas' => 4, 'recebido_em' => now()->toDateString(),
    ]);
    // SUPERADMIN: idem.
    $comprovante = Comprovante::withoutGlobalScopes()->create([
        'business_id' => $biz, 'projeto_id' => $projeto->id, 'aporte_id' => $aporte->id,
        'descricao' => 'Despesa Fixture', 'valor' => 1, 'pago_em' => now()->toDateString(),
    ]);

    return ['fonte' => $fonte, 'projeto' => $projeto, 'aporte' => $aporte, 'comprovante' => $comprovante];
}

it('US-CAPT-001 · toda tabela do módulo tem business_id com FK para business', function () {
    $fks = collect(DB::select(
        "SELECT TABLE_NAME AS t FROM information_schema.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'business_id' AND REFERENCED_TABLE_NAME = 'business'"
    ))->pluck('t')->all();

    foreach (CAPT_TABELAS as $t) {
        expect(Schema::hasColumn($t, 'business_id'))->toBeTrue()->and($fks)->toContain($t);
    }
});

it('US-CAPT-001 · sessão da 98 enxerga só a 98 nas tabelas do módulo', function () {
    $c98 = captSemear($this->b98);
    $c99 = captSemear($this->b99);

    captComo($this, $this->u98);
    foreach ([Fonte::class, Projeto::class, Aporte::class, Comprovante::class] as $model) {
        expect($model::query()->pluck('business_id')->map('intval')->unique()->values()->all())->toBe([$this->b98]);
    }
    expect(Projeto::query()->find($c99['projeto']->id))->toBeNull();

    // Discriminante: como 99, o filtro troca de lado em vez de esvaziar.
    captComo($this, $this->u99);
    expect(Projeto::query()->pluck('id')->all())->toContain($c99['projeto']->id)->not->toContain($c98['projeto']->id);

    // Controle negativo: sem escopo as duas linhas existem — o filtro acima é real.
    // SUPERADMIN: controle negativo do teste de isolamento.
    expect(Projeto::withoutGlobalScopes()->whereIn('id', [$c98['projeto']->id, $c99['projeto']->id])->count())->toBe(2);
});

it('US-CAPT-001 · aporte e comprovante não se apagam pelo Model', function () {
    $c = captSemear($this->b98);

    expect(fn () => $c['aporte']->delete())->toThrow(LogicException::class);
    expect(fn () => $c['comprovante']->delete())->toThrow(LogicException::class);
    expect(DB::table('captacao_aportes')->where('id', $c['aporte']->id)->exists())->toBeTrue()
        ->and(DB::table('captacao_comprovantes')->where('id', $c['comprovante']->id)->exists())->toBeTrue();

    // Tirar da lista é preencher removido_em + motivo — a linha continua lá.
    $c['comprovante']->forceFill(['removido_em' => now(), 'removido_motivo' => 'lançado em duplicidade'])->save();
    expect(DB::table('captacao_comprovantes')->where('id', $c['comprovante']->id)->whereNotNull('removido_em')->exists())->toBeTrue();
});

it('US-CAPT-001 · projeto de grupo sem CNPJ, com líder que é contato e não usuária', function () {
    // contacts.created_by é FK NOT NULL -> users.id. Nome fictício: nenhum dado real em teste.
    $liderId = (int) DB::table('contacts')->insertGetId([
        'business_id' => $this->b98, 'type' => 'customer', 'name' => 'Lider Fixture '.uniqid(),
        'mobile' => '48999990000', 'contact_status' => 'active', 'created_by' => (int) $this->u98->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    captComo($this, $this->u98);
    $projeto = Projeto::query()->create([
        'business_id' => $this->b98, 'titulo' => 'Projeto Grupo Fixture', 'proponente_nome' => 'Grupo em formação Fixture',
        'proponente_cnpj' => null, 'lider_contact_id' => $liderId, 'tipo' => 'outro', 'area' => 'saude',
    ]);

    $lido = Projeto::query()->findOrFail($projeto->id);
    expect($lido->proponente_cnpj)->toBeNull()
        ->and((int) $lido->lider->id)->toBe($liderId)
        ->and($lido->area)->toBe('saude');

    // O banco recusa líder que não existe (FK para contacts).
    expect(fn () => Projeto::query()->create([
        'business_id' => $this->b98, 'titulo' => 'Projeto FK Fixture', 'proponente_nome' => 'X', 'lider_contact_id' => 2147483000,
    ]))->toThrow(QueryException::class);
});

it('US-CAPT-001 · sem o pacote da empresa ninguém entra, nem com a permissão', function () {
    // Pacote ligado só para a 98 (idioma do AssociacoesAcessoContratoTest).
    $fake = Mockery::mock(ModuleUtil::class)->makePartial();
    $fake->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($biz, $perm) => $perm === CaptacaoAcesso::PACOTE && (int) $biz === $this->b98);
    app()->instance(ModuleUtil::class, $fake);

    $acesso = app(CaptacaoAcesso::class);
    $semPermissao = $this->usuarioComPermissoes([], $this->seededTenant());

    expect($acesso->liberado($this->u98, $this->b98))->toBeTrue()
        ->and($acesso->liberado($this->u99, $this->b99))->toBeFalse()
        ->and($acesso->liberado($semPermissao, $this->b98))->toBeFalse()
        ->and($acesso->liberado(null, $this->b98))->toBeFalse();
});
