<?php

use App\Business;
use App\Contact;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * auditoria:backfill-business-id — preenche activity_log.business_id NULL pelo tenant do
 * REGISTRO auditado (Tier 0, ADR 0093). Tenants fictícios 98 × 99 (ADR 0358), nunca biz=4.
 *
 * Contrato: dry-run não escreve; --apply resolve cada linha para o tenant do PRÓPRIO
 * registro; nunca sobrescreve business_id existente; registro apagado e classe sem
 * caminho continuam NULL; rodar de novo não escreve nada (idempotente).
 */
uses(DatabaseTransactions::class);

/**
 * Stub de log da PLATAFORMA (o marcador das licenças do Officeimpresso). Usa a tabela
 * contacts de propósito: ela TEM business_id, então só o marcador impede o backfill.
 */
class BkfLogDaPlataformaFake extends \Illuminate\Database\Eloquent\Model
{
    public const AUDITORIA_LOG_DA_PLATAFORMA = true;

    protected $table = 'contacts';
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasColumn('activity_log', 'business_id')) {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS com activity_log.business_id.');
    }

    $this->biz98 = $this->seededTenant();
    $this->biz99 = $this->seededSupportClientTenant();

    $contato = fn (Business $biz) => DB::table('contacts')->insertGetId([
        'business_id' => $biz->id, 'type' => 'customer', 'name' => 'Backfill T0 '.$biz->id,
        'mobile' => '', 'contact_id' => 'BKF-'.uniqid(), 'created_by' => $biz->owner_id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->c98 = $contato($this->biz98);
    $this->c99 = $contato($this->biz99);
    $this->apagado = $contato($this->biz99);
    DB::table('contacts')->where('id', $this->apagado)->delete();

    $log = fn (string $tipo, int $subjectId, ?int $biz = null) => DB::table('activity_log')->insertGetId([
        'log_name' => 'backfill-t0', 'description' => 'backfill-t0', 'subject_type' => $tipo,
        'subject_id' => $subjectId, 'business_id' => $biz, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->l98 = $log(Contact::class, $this->c98);
    $this->l99 = $log(Contact::class, $this->c99);
    $this->lBiz = $log(Business::class, $this->biz99->id);
    $this->lApagado = $log(Contact::class, $this->apagado);
    $this->lSemCaminho = $log('App\\ClasseQueNaoExiste', $this->c99);
    // Já preenchido (mesmo que divergente do registro): o backfill NÃO toca.
    $this->lJaTinha = $log(Contact::class, $this->c99, $this->biz98->id);
    $this->lPlataformaNull = $log(BkfLogDaPlataformaFake::class, $this->c99);
    $this->lPlataformaVazou = $log(BkfLogDaPlataformaFake::class, $this->c99, $this->biz99->id);
});

function bkf_biz(int $id): ?int
{
    $v = DB::table('activity_log')->where('id', $id)->value('business_id');

    return $v === null ? null : (int) $v;
}

function bkf_rodar(array $args = []): string
{
    Artisan::call('auditoria:backfill-business-id', $args + ['--type' => [Contact::class, Business::class, 'App\\ClasseQueNaoExiste', BkfLogDaPlataformaFake::class]]);

    return Artisan::output();
}

it('dry-run (padrão) não escreve nada', function () {
    $saida = bkf_rodar();

    expect($saida)->toContain('DRY-RUN');
    foreach ([$this->l98, $this->l99, $this->lBiz, $this->lApagado, $this->lSemCaminho] as $id) {
        expect(bkf_biz($id))->toBeNull();
    }
});

it('--apply grava o tenant do PRÓPRIO registro em cada linha (98 e 99 não se misturam)', function () {
    bkf_rodar(['--apply' => true]);

    expect(bkf_biz($this->l98))->toBe((int) $this->biz98->id);
    expect(bkf_biz($this->l99))->toBe((int) $this->biz99->id);
    expect(bkf_biz($this->lBiz))->toBe((int) $this->biz99->id);
});

it('--apply não inventa: registro apagado e classe sem caminho continuam NULL', function () {
    bkf_rodar(['--apply' => true]);

    expect(bkf_biz($this->lApagado))->toBeNull();
    expect(bkf_biz($this->lSemCaminho))->toBeNull();
});

it('--apply nunca sobrescreve business_id já preenchido', function () {
    bkf_rodar(['--apply' => true]);

    expect(bkf_biz($this->lJaTinha))->toBe((int) $this->biz98->id);
});

it('idempotente: a segunda execução não escreve nada', function () {
    bkf_rodar(['--apply' => true]);
    $saida = bkf_rodar(['--apply' => true]);

    expect($saida)->toContain('escrito: 0');
});

it('log da plataforma: o backfill não dá tenant e o --apply LIMPA o que já vazou', function () {
    bkf_rodar();
    expect(bkf_biz($this->lPlataformaVazou))->toBe((int) $this->biz99->id); // dry-run não limpa

    bkf_rodar(['--apply' => true]);

    expect(bkf_biz($this->lPlataformaNull))->toBeNull();
    expect(bkf_biz($this->lPlataformaVazou))->toBeNull();
});
