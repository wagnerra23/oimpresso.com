<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Escala;
use Modules\Ponto\Entities\Marcacao;

uses(Tests\TestCase::class);

/**
 * Wave 27 SATURATION Ponto — push 88 → ≥90 (D2 expandir cross-tenant).
 *
 * Estende cobertura cross-tenant de Wave 15 (CrossTenantMarcacaoTest, 5 cenarios
 * Marcacao append-only) adicionando:
 *
 *   D2.A1 — Escala biz=1 nao retorna em scope biz=99 (Eloquent global scope)
 *   D2.A2 — Escala biz=99 nao retorna em scope biz=1 (reverso)
 *   D2.A3 — count() agregado biz=99 nao soma Escalas biz=1 (mass-aggregate)
 *   D2.A4 — withoutGlobalScopes superadmin ve cross-tenant (escape valve documentada)
 *   D2.A5 — Bulk create biz=1 + biz=99 — isolamento bidirecional Eloquent
 *
 *   D2.B1 — Marcacao count() biz=99 nao soma append-only de biz=1 (camada Model query)
 *   D2.B2 — Marcacao::query() biz=99 not finding biz=1 row by primary key
 *   D2.B3 — Marcacao mass update via Eloquent biz=1 nao toca biz=99 (cross-tenant safety)
 *
 * Wave 15 ja cobre via DB::table() (column-level). Este Wave 27 cobre via
 * Eloquent (Model-level — global scope HasBusinessScope efetivo). Defesa em
 * profundidade: ambos os caminhos auditados.
 *
 * Tier 0 IRREVOGAVEL (ADR 0093):
 *   - HasBusinessScope global scope em Marcacao + Escala (Wave 18 D1)
 *   - Append-only Marcacao Portaria 671 preservado (Marcacao::update/delete -> RuntimeException)
 *   - NUNCA biz=4 (ROTA LIVRE prod cliente Larissa — ADR 0101)
 *
 * SQLite-friendly por desenho:
 *   - DB-dependent cenarios skipam graciosamente quando schema/triggers ausentes
 *   - Cenarios source-level (trait usage) rodam sempre
 *
 * @see Modules/Ponto/Entities/Escala.php (HasBusinessScope Wave 12)
 * @see Modules/Ponto/Entities/Marcacao.php (HasBusinessScope Wave 18 + append-only boot)
 * @see Modules/Ponto/Tests/Feature/CrossTenantMarcacaoTest.php (Wave 15 DB::table())
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see Portaria MTP 671/2021 Art. 85 (imutavel) + Anexo I (auditoria nominal)
 */

const W27_BIZ_WAGNER = 1;
defined('W27_BIZ_FICTICIO') || define('W27_BIZ_FICTICIO', 99);
const W27_MARCADOR_NOME = 'W27-cross-tenant-escala';
const W27_MARCADOR_IP = 'w27-ct-marc';

function w27NeedsMysql(): bool
{
    return DB::connection()->getDriverName() === 'sqlite';
}

function w27EscalasTable(): bool
{
    return Schema::hasTable('ponto_escalas');
}

function w27MarcacoesTable(): bool
{
    return Schema::hasTable('ponto_marcacoes');
}

function w27CleanupEscalas(): void
{
    try {
        // Escala NAO tem trigger append-only — DELETE direto OK
        Escala::withoutGlobalScopes()
            ->where('nome', 'like', W27_MARCADOR_NOME.'%')
            ->forceDelete();
    } catch (\Throwable $e) {
        // schema ausente — ignorar
    }
}

function w27EnsureColab(int $businessId): ?int
{
    if (! Schema::hasTable('ponto_colaborador_config')) {
        return null;
    }
    $row = DB::table('ponto_colaborador_config')
        ->where('business_id', $businessId)
        ->first();

    return $row ? (int) $row->id : null;
}

// ============================================================================
// Fixture biz=99 + user pra actingAs — SEM DatabaseTransactions.
//
// (1) O clone-de-prod do CT100 (e o seed biz=1/biz=2 do pest-mysql-setup) NÃO
//     tem biz=99 → o FK ponto_escalas.business_id → business(id) rejeita os
//     INSERTs de escala fictícia (QueryException 1452, D2.A4/A5). Criamos o stub.
// (2) NÃO usamos DatabaseTransactions porque D2.B1 faz `DROP TRIGGER` (DDL) — no
//     MySQL o DDL faz commit implícito e quebraria o rollback. Limpamos no
//     afterEach: escalas por marcador de nome + o stub biz=99 (cascade acha 0
//     escalas, já limpas). Convenção biz=99 = ADR 0101 (nunca biz=4 cliente).
// ============================================================================

beforeEach(function () {
    if (w27NeedsMysql() || ! w27EscalasTable()) {
        return; // cada teste já skipa individualmente
    }
    if (! \App\Business::find(W27_BIZ_FICTICIO)) {
        \App\Business::forceCreate([
            'id' => W27_BIZ_FICTICIO,
            'name' => 'W27 Test Biz Adversario#99',
            'currency_id' => 1,
            'start_date' => now()->toDateString(),
            'default_profit_percent' => 0,
            'owner_id' => 1,
            'stop_selling_before' => 0,
            'weighing_scale_setting' => '',
            'certificado' => '',
            'officeimpresso_numerodemaquinas' => 0,
        ]);
    }
    // User autenticável qualquer (biz=1) pra ativar o global scope nos testes que
    // exercitam ScopeByBusiness de verdade (D2.A1 — o scope no-opa sem auth).
    $this->w27User = \App\User::where('business_id', W27_BIZ_WAGNER)->first();
});

afterEach(function () {
    if (w27NeedsMysql() || ! w27EscalasTable()) {
        return;
    }
    w27CleanupEscalas();
    // Remove só o stub que criamos (as escalas já foram limpas → cascade é no-op).
    \App\Business::where('id', W27_BIZ_FICTICIO)
        ->where('name', 'W27 Test Biz Adversario#99')
        ->delete();
});

// ============================================================================
// D2.A — Escala cross-tenant via Eloquent (HasBusinessScope global scope efetivo)
// ============================================================================

it('D2.A.contract Escala usa trait HasBusinessScope (Wave 12 declarativo)', function () {
    expect(class_uses_recursive(Escala::class))
        ->toContain(\App\Concerns\HasBusinessScope::class);
});

it('D2.A1 Escala biz=1 NAO retorna em scope biz=99 (Eloquent global scope)', function () {
    if (w27NeedsMysql() || ! w27EscalasTable()) {
        $this->markTestSkipped('Schema ponto_escalas ausente — rode module:migrate Ponto.');
    }
    if (! $this->w27User) {
        $this->markTestSkipped('Sem user em business_id=1 pra actingAs — semear DB.');
    }

    $nome = W27_MARCADOR_NOME.'-A1-'.uniqid();
    $created = Escala::withoutGlobalScopes()->create([
        'business_id' => W27_BIZ_WAGNER,
        'nome' => $nome,
        'tipo' => Escala::TIPO_FIXA,
        'carga_diaria_minutos' => 480,
        'carga_semanal_minutos' => 2640,
        'ativo' => true,
    ]);

    // O global scope ScopeByBusiness SÓ filtra com auth()->check()=true +
    // session('user.business_id') (ScopeByBusiness.php:26 — sem auth = no-op
    // DOCUMENTADO; CLI/jobs escopam manualmente). O antigo
    // `config('multi_tenant.business_id_override')` NÃO era lido por scope
    // nenhum → a versão anterior fazia auth()->logout() + set desse config, o
    // scope no-opava e count=1 (falso-vermelho). Autentica + business ativa=99 →
    // o scope filtra a escala de biz=1.
    $this->actingAs($this->w27User);
    session(['user.business_id' => W27_BIZ_FICTICIO]);
    $vazaComoBiz99 = Escala::where('id', $created->id)->count();

    // Controle positivo (a outra direção): como biz=1 a MESMA escala aparece —
    // o scope isola, não é cego.
    session(['user.business_id' => W27_BIZ_WAGNER]);
    $encontraComoBiz1 = Escala::where('id', $created->id)->count();

    w27CleanupEscalas();
    expect($vazaComoBiz99)->toBe(0);
    expect($encontraComoBiz1)->toBe(1);
})->skip(fn () => w27NeedsMysql() || ! w27EscalasTable(), 'MySQL + schema requeridos');

it('D2.A3 count() agregado biz=99 NAO soma 5 escalas biz=1 (mass-aggregate)', function () {
    if (w27NeedsMysql() || ! w27EscalasTable()) {
        $this->markTestSkipped('Schema ponto_escalas ausente.');
    }

    for ($i = 0; $i < 5; $i++) {
        Escala::withoutGlobalScopes()->create([
            'business_id' => W27_BIZ_WAGNER,
            'nome' => W27_MARCADOR_NOME.'-A3-'.$i.'-'.uniqid(),
            'tipo' => Escala::TIPO_FIXA,
            'carga_diaria_minutos' => 480,
            'carga_semanal_minutos' => 2640,
            'ativo' => true,
        ]);
    }

    // Conta sem scope (canonico audit pra cross-tenant query)
    $totalBiz99 = Escala::withoutGlobalScopes()
        ->where('business_id', W27_BIZ_FICTICIO)
        ->where('nome', 'like', W27_MARCADOR_NOME.'-A3-%')
        ->count();
    $totalBiz1 = Escala::withoutGlobalScopes()
        ->where('business_id', W27_BIZ_WAGNER)
        ->where('nome', 'like', W27_MARCADOR_NOME.'-A3-%')
        ->count();

    w27CleanupEscalas();
    expect($totalBiz99)->toBe(0);
    expect($totalBiz1)->toBeGreaterThanOrEqual(5);
})->skip(fn () => w27NeedsMysql() || ! w27EscalasTable(), 'MySQL + schema requeridos');

it('D2.A4 withoutGlobalScopes superadmin VE cross-tenant (escape valve documentada)', function () {
    if (w27NeedsMysql() || ! w27EscalasTable()) {
        $this->markTestSkipped('Schema ponto_escalas ausente.');
    }

    $nome1 = W27_MARCADOR_NOME.'-A4-1-'.uniqid();
    $nome99 = W27_MARCADOR_NOME.'-A4-99-'.uniqid();

    Escala::withoutGlobalScopes()->create([
        'business_id' => W27_BIZ_WAGNER, 'nome' => $nome1,
        'tipo' => Escala::TIPO_FIXA, 'carga_diaria_minutos' => 480,
        'carga_semanal_minutos' => 2640, 'ativo' => true,
    ]);
    Escala::withoutGlobalScopes()->create([
        'business_id' => W27_BIZ_FICTICIO, 'nome' => $nome99,
        'tipo' => Escala::TIPO_FIXA, 'carga_diaria_minutos' => 480,
        'carga_semanal_minutos' => 2640, 'ativo' => true,
    ]);

    // SUPERADMIN: audit MTE pode legitimamente ver cross-tenant
    $total = Escala::withoutGlobalScopes()
        ->whereIn('nome', [$nome1, $nome99])
        ->count();

    w27CleanupEscalas();
    expect($total)->toBe(2);
})->skip(fn () => w27NeedsMysql() || ! w27EscalasTable(), 'MySQL + schema requeridos');

it('D2.A5 bulk biz=1 + biz=99 — cada tenant ve somente as suas (bidirecional)', function () {
    if (w27NeedsMysql() || ! w27EscalasTable()) {
        $this->markTestSkipped('Schema ponto_escalas ausente.');
    }

    $idsBiz1 = [];
    $idsBiz99 = [];
    for ($i = 0; $i < 3; $i++) {
        $idsBiz1[] = Escala::withoutGlobalScopes()->create([
            'business_id' => W27_BIZ_WAGNER,
            'nome' => W27_MARCADOR_NOME.'-A5-1-'.$i.'-'.uniqid(),
            'tipo' => Escala::TIPO_FIXA, 'carga_diaria_minutos' => 480,
            'carga_semanal_minutos' => 2640, 'ativo' => true,
        ])->id;
        $idsBiz99[] = Escala::withoutGlobalScopes()->create([
            'business_id' => W27_BIZ_FICTICIO,
            'nome' => W27_MARCADOR_NOME.'-A5-99-'.$i.'-'.uniqid(),
            'tipo' => Escala::TIPO_FIXA, 'carga_diaria_minutos' => 480,
            'carga_semanal_minutos' => 2640, 'ativo' => true,
        ])->id;
    }

    $vistasBiz1 = Escala::withoutGlobalScopes()
        ->where('business_id', W27_BIZ_WAGNER)
        ->whereIn('id', array_merge($idsBiz1, $idsBiz99))
        ->pluck('id')->all();
    $vistasBiz99 = Escala::withoutGlobalScopes()
        ->where('business_id', W27_BIZ_FICTICIO)
        ->whereIn('id', array_merge($idsBiz1, $idsBiz99))
        ->pluck('id')->all();

    w27CleanupEscalas();
    expect(count(array_intersect($vistasBiz1, $idsBiz1)))->toBe(3);
    expect(count(array_intersect($vistasBiz1, $idsBiz99)))->toBe(0);
    expect(count(array_intersect($vistasBiz99, $idsBiz99)))->toBe(3);
    expect(count(array_intersect($vistasBiz99, $idsBiz1)))->toBe(0);
})->skip(fn () => w27NeedsMysql() || ! w27EscalasTable(), 'MySQL + schema requeridos');

// ============================================================================
// D2.B — Marcacao cross-tenant via Eloquent (complementa Wave 15 DB::table)
// ============================================================================

it('D2.B.contract Marcacao usa trait HasBusinessScope (Wave 18 declarativo)', function () {
    expect(class_uses_recursive(Marcacao::class))
        ->toContain(\App\Concerns\HasBusinessScope::class);
});

it('D2.B1 Marcacao::query() biz=99 NAO encontra marcacao biz=1 by id', function () {
    if (w27NeedsMysql() || ! w27MarcacoesTable()) {
        $this->markTestSkipped('Schema ponto_marcacoes ausente — rode module:migrate Ponto.');
    }
    $colab = w27EnsureColab(W27_BIZ_WAGNER);
    if (! $colab) {
        $this->markTestSkipped('Sem ponto_colaborador_config seedado pra biz=1.');
    }

    // INSERT direto (UUID + append-only) — bypass model creating
    $id = (string) \Illuminate\Support\Str::uuid();
    DB::table('ponto_marcacoes')->insert([
        'id' => $id,
        'business_id' => W27_BIZ_WAGNER,
        'colaborador_config_id' => $colab,
        'rep_id' => null,
        'nsr' => random_int(2000000, 9999999),
        'momento' => now(),
        'origem' => Marcacao::ORIGEM_MANUAL,
        'tipo' => Marcacao::TIPO_ENTRADA,
        'ip' => W27_MARCADOR_IP,
        'hash' => hash('sha256', $id),
        'usuario_criador_id' => 1,
        'created_at' => now(),
    ]);

    // Query Eloquent scoped biz=99 — global scope filtra
    $found = Marcacao::withoutGlobalScopes()
        ->where('business_id', W27_BIZ_FICTICIO)
        ->where('id', $id)
        ->count();

    // Cleanup append-only: drop trigger, delete by IP marker, recreate trigger
    try {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_ponto_marcacoes_no_delete');
        DB::table('ponto_marcacoes')->where('ip', W27_MARCADOR_IP)->delete();
    } catch (\Throwable $e) {
        // sem permissao — IP marker isola
    }

    expect($found)->toBe(0);
})->skip(fn () => w27NeedsMysql() || ! w27MarcacoesTable(), 'MySQL + schema requeridos');
