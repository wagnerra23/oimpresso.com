<?php

declare(strict_types=1);

use App\Services\Unidades\AuditoriaMultiplicadorUnidade;
use App\Unit;
use App\Utils\Util;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\EstoqueFixture;

/**
 * Dano JÁ GRAVADO pelo modal "Editar unidade" antes do PR #8394 (decisão [W] 2026-10-01 item 11):
 * `units:auditar-multiplicador` (só lê) e `units:corrigir-multiplicador` (dry-run obrigatório).
 *
 * O contrato vem do bug descrito no #8394, não do código desta auditoria:
 *   salvar sem mexer gravava 1000→1, 0,5→1, 2,5→3, 1500,75→1,501, e a ausência de
 *   `define_base_unit` zerava base e multiplicador.
 * O "save com bug" aqui é reproduzido pela MESMA composição do Blade antigo + controller:
 *   `num_uf(number_format($antigo))` gravado via Eloquent — o activity_log nasce do trait real
 *   `LogsActivity` (LC-22: o consumidor lê o formato que o produtor de verdade escreve).
 *
 * ⛔ Tenant 98 (ADR 0358) contra o vizinho 99. NUNCA biz=4.
 * ⚠️ SKIP sem schema MySQL: leia assertions, não "0 failed" (LC-13).
 *
 * @see app/Services/Unidades/AuditoriaMultiplicadorUnidade.php
 * @see app/Console/Commands/Unidades/AuditarMultiplicadorCommand.php
 * @see app/Console/Commands/Unidades/CorrigirMultiplicadorCommand.php
 */
uses(DatabaseTransactions::class);

const UMULT_TAG = '[umult]';

function umultUnidade(int $bizId, string $simbolo, ?int $baseId = null, ?float $mult = null): Unit
{
    return Unit::create([
        'business_id' => $bizId, 'actual_name' => $simbolo.' '.UMULT_TAG, 'short_name' => $simbolo,
        'allow_decimal' => 1, 'base_unit_id' => $baseId, 'base_unit_multiplier' => $mult,
        'created_by' => EstoqueFixture::userId($bizId),
    ]);
}

/** O save do modal antigo sem mexer em nada: exibe com number_format() e relê com num_uf(). */
function umultSalvarComBug(Unit $u): Unit
{
    $u = $u->fresh();
    $u->base_unit_multiplier = app(Util::class)->num_uf(number_format((float) $u->base_unit_multiplier));
    $u->save();

    return $u->fresh();
}

function umultAchado(int $bizId, int $unitId): ?array
{
    foreach (app(AuditoriaMultiplicadorUnidade::class)->auditar($bizId) as $l) {
        if ($l['unit_id'] === $unitId) {
            return $l;
        }
    }

    return null;
}

function umultCodigo(int $bizId, array $extra = []): string
{
    Artisan::call('units:corrigir-multiplicador', ['--business' => $bizId] + $extra);
    preg_match('/código ([0-9a-f]{12})/', Artisan::output(), $m);

    return $m[1] ?? '';
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }
    $this->biz = $this->seededTenant();
    $this->vizinho = $this->seededSupportClientTenant();
    $this->base = umultUnidade($this->biz->id, 'g');
});

it('a função do bug reproduz os 4 casos do #8394', function () {
    $a = app(AuditoriaMultiplicadorUnidade::class);

    expect($a->corromper(1000.0))->toContain(1.0)
        ->and($a->corromper(0.5))->toContain(1.0)
        ->and($a->corromper(2.5))->toContain(3.0)
        ->and($a->corromper(1500.75))->toContain(1.501)
        ->and($a->corromper(12.0))->toBe([]);
});

it('1000→1 pelo bug é RECUPERAVEL, com as duas provas e o valor de criação', function () {
    $kg = umultUnidade($this->biz->id, 'kg', $this->base->id, 1000);
    $kg = umultSalvarComBug($kg);
    expect((float) $kg->base_unit_multiplier)->toEqual(1.0);

    $l = umultAchado($this->biz->id, $kg->id);

    expect($l)->not->toBeNull()
        ->and($l['classe'])->toBe(AuditoriaMultiplicadorUnidade::RECUPERAVEL)
        ->and($l['mult_atual'])->toEqual(1.0)
        ->and($l['mult_proposto'])->toEqual(1000.0)
        ->and($l['prova_valor_log'])->toBeTrue()
        ->and($l['prova_mecanismo'])->toBeTrue()
        ->and($l['valor_na_criacao'])->toEqual(1000.0);
});

it('cadeia 1500,75→1,501→2 volta ao valor original, não ao intermediário', function () {
    $cx = umultUnidade($this->biz->id, 'cx', $this->base->id, 1500.75);
    umultSalvarComBug($cx);
    $cx = umultSalvarComBug($cx);
    expect((float) $cx->base_unit_multiplier)->toEqual(2.0);

    $l = umultAchado($this->biz->id, $cx->id);

    expect($l['classe'])->toBe(AuditoriaMultiplicadorUnidade::RECUPERAVEL)
        ->and($l['mult_proposto'])->toEqual(1500.75)
        ->and($l['passos_corrompidos'])->toBe(2)
        ->and($l['prova_mecanismo'])->toBeTrue();
});

it('edição manual (1000→12) não vira proposta de correção', function () {
    $pc = umultUnidade($this->biz->id, 'pc', $this->base->id, 1000);
    $pc->base_unit_multiplier = 12;
    $pc->save();

    $l = umultAchado($this->biz->id, $pc->id);

    expect($l === null || $l['classe'] !== AuditoriaMultiplicadorUnidade::RECUPERAVEL)->toBeTrue();
});

it('base zerada é BASE_REMOVIDA e só entra no plano com --incluir-base-removida', function () {
    $dz = umultUnidade($this->biz->id, 'dz', $this->base->id, 12);
    $dz->base_unit_id = null;
    $dz->base_unit_multiplier = null;
    $dz->save();

    $l = umultAchado($this->biz->id, $dz->id);
    expect($l['classe'])->toBe(AuditoriaMultiplicadorUnidade::BASE_REMOVIDA)
        ->and($l['mult_proposto'])->toEqual(12.0)
        ->and($l['base_proposta_id'])->toBe($this->base->id);

    Artisan::call('units:corrigir-multiplicador', ['--business' => $this->biz->id]);
    expect(Artisan::output())->not->toMatch('/\|\s*'.$dz->id.'\s*\|/');

    Artisan::call('units:corrigir-multiplicador', ['--business' => $this->biz->id, '--incluir-base-removida' => true]);
    expect(Artisan::output())->toMatch('/\|\s*'.$dz->id.'\s*\|/');
});

it('sem log (anterior a 2026-05-16) vira SUSPEITA_SEM_PROVA, sem valor proposto e fora do plano', function () {
    $id = (int) DB::table('units')->insertGetId([
        'business_id' => $this->biz->id, 'actual_name' => 'fd '.UMULT_TAG, 'short_name' => 'fd',
        'allow_decimal' => 0, 'base_unit_id' => $this->base->id, 'base_unit_multiplier' => 1,
        'created_by' => EstoqueFixture::userId($this->biz->id), 'created_at' => now()->subYear(), 'updated_at' => now()->subMonths(6),
    ]);

    $l = umultAchado($this->biz->id, $id);
    expect($l['classe'])->toBe(AuditoriaMultiplicadorUnidade::SUSPEITA_SEM_PROVA)
        ->and($l['mult_proposto'])->toBeNull()
        ->and($l['sinais'])->toContain('multiplicador_igual_a_1');

    Artisan::call('units:corrigir-multiplicador', ['--business' => $this->biz->id]);
    expect(Artisan::output())->not->toMatch('/\|\s*'.$id.'\s*\|/');
});

it('não enxerga unidade corrompida de outro business (Tier 0)', function () {
    $baseViz = umultUnidade($this->vizinho->id, 'g');
    $kgViz = umultSalvarComBug(umultUnidade($this->vizinho->id, 'kg', $baseViz->id, 1000));

    expect(umultAchado($this->biz->id, $kgViz->id))->toBeNull()
        ->and(umultAchado($this->vizinho->id, $kgViz->id)['classe'])->toBe(AuditoriaMultiplicadorUnidade::RECUPERAVEL);
});

it('auditar só lê: o comando não muda nenhuma unidade', function () {
    $kg = umultSalvarComBug(umultUnidade($this->biz->id, 'kg', $this->base->id, 1000));
    $antes = DB::table('units')->where('business_id', $this->biz->id)->orderBy('id')->get(['id', 'base_unit_id', 'base_unit_multiplier', 'updated_at'])->toJson();

    expect(Artisan::call('units:auditar-multiplicador', ['--business' => $this->biz->id, '--json' => true]))->toBe(0);
    $json = json_decode(Artisan::output(), true);

    expect(collect($json[$this->biz->id] ?? [])->firstWhere('unit_id', $kg->id)['mult_proposto'])->toEqual(1000.0)
        ->and(DB::table('units')->where('business_id', $this->biz->id)->orderBy('id')->get(['id', 'base_unit_id', 'base_unit_multiplier', 'updated_at'])->toJson())->toBe($antes);
});

it('corrigir: dry-run não grava, código errado recusa, código certo restaura e registra no log', function () {
    $kg = umultSalvarComBug(umultUnidade($this->biz->id, 'kg', $this->base->id, 1000));

    $codigo = umultCodigo($this->biz->id);
    expect($codigo)->toMatch('/^[0-9a-f]{12}$/')
        ->and((float) $kg->fresh()->base_unit_multiplier)->toEqual(1.0);

    expect(Artisan::call('units:corrigir-multiplicador', ['--business' => $this->biz->id, '--aplicar' => true, '--confirmar' => 'errado000000']))->toBe(1)
        ->and((float) $kg->fresh()->base_unit_multiplier)->toEqual(1.0);

    $logsAntes = DB::table('activity_log')->where('subject_type', Unit::class)->where('subject_id', $kg->id)->count();
    expect(Artisan::call('units:corrigir-multiplicador', ['--business' => $this->biz->id, '--aplicar' => true, '--confirmar' => $codigo]))->toBe(0)
        ->and((float) $kg->fresh()->base_unit_multiplier)->toEqual(1000.0)
        ->and(DB::table('activity_log')->where('subject_type', Unit::class)->where('subject_id', $kg->id)->count())->toBe($logsAntes + 1);

    expect(umultAchado($this->biz->id, $kg->id))->toBeNull();
});

it('corrigir: unidade mudou depois do dry-run → código não confere, nada gravado', function () {
    $kg = umultSalvarComBug(umultUnidade($this->biz->id, 'kg', $this->base->id, 1000));
    $codigo = umultCodigo($this->biz->id);

    $kg->base_unit_multiplier = 7;
    $kg->save();

    expect(Artisan::call('units:corrigir-multiplicador', ['--business' => $this->biz->id, '--aplicar' => true, '--confirmar' => $codigo]))->toBe(1)
        ->and((float) $kg->fresh()->base_unit_multiplier)->toEqual(7.0);
});

it('corrigir exige --business', function () {
    expect(Artisan::call('units:corrigir-multiplicador'))->toBe(1);
});
