<?php
// @covers-us US-BKP-001

declare(strict_types=1);

use App\Jobs\ExportarDadosDaEmpresaJob;
use App\Models\ExportacaoEmpresa;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Exportação por empresa — US-BKP-001, fatia 2 (pedido, job, download e registro).
 *
 * Só o DONO pede e baixa (o pacote tem PII). O 403 do não-dono é conferido pela MENSAGEM,
 * porque o CheckUserLogin também devolve 403 — sem isso o caso passaria pelo motivo errado
 * (mesma armadilha registrada em BackupSegurancaTest). O dono da 99 não alcança o pedido da 98
 * nem com link assinado válido. Tenants 98 (ADR 0358) × 99. Nunca biz=4. Lane: backup-pest.yml.
 */
uses(DatabaseTransactions::class);

function bkaDono(int $bizId): User
{
    $u = User::factory()->create(['business_id' => $bizId])->fresh();
    DB::table('business')->where('id', $bizId)->update(['owner_id' => $u->id]);

    return $u;
}

function bkaComo(User $u): void
{
    test()->actingAs($u);
    session(['user.business_id' => $u->business_id, 'business.id' => $u->business_id]);
}

beforeEach(function () {
    if (! Schema::hasTable('business') || ! Schema::hasTable('exportacoes_empresa')) {
        $this->markTestSkipped('Schema ausente — roda na lane MySQL / CT 100. NÃO sqlite.');
    }
    $this->biz = (int) $this->seededTenant()->id;
    $this->outro = (int) $this->seededSupportClientTenant()->id;
    $this->dono = bkaDono($this->biz);
    $this->donoOutro = bkaDono($this->outro);
    $this->funcionario = User::factory()->create(['business_id' => $this->biz])->fresh();

    Storage::fake('local');
    Carbon::setTestNow(Carbon::parse('2026-10-27 10:00:00'));
    config(['backup.exportacao_empresa.enabled' => true, 'backup.exportacao_empresa.disco' => 'local', 'backup.exportacao_empresa.horas_validade' => 24]);
});

afterEach(fn () => Carbon::setTestNow());

it('UC-BKX-07 · com a flag desligada o pedido não existe', function () {
    config(['backup.exportacao_empresa.enabled' => false]);
    bkaComo($this->dono);
    $this->postJson('/backup/empresa/exportacoes')->assertNotFound();
    expect(ExportacaoEmpresa::daEmpresa($this->biz)->count())->toBe(0);
});

it('UC-BKX-08 · funcionário que não é o dono não pede', function () {
    bkaComo($this->funcionario);
    $this->postJson('/backup/empresa/exportacoes')->assertForbidden()->assertJsonFragment(['message' => 'Só o dono da empresa exporta os dados.']);
    expect(ExportacaoEmpresa::daEmpresa($this->biz)->count())->toBe(0);
});

it('UC-BKX-09 · o dono pede: um pedido, job na fila backups com o business_id, registro de atividade', function () {
    Queue::fake();
    bkaComo($this->dono);
    $id = $this->postJson('/backup/empresa/exportacoes')->assertCreated()->json('id');
    // Segundo clique com pedido pendente devolve o mesmo, sem novo job.
    expect($this->postJson('/backup/empresa/exportacoes')->assertOk()->json('id'))->toBe($id);

    Queue::assertPushedOn('backups', ExportarDadosDaEmpresaJob::class, fn ($j) => $j->businessId === $this->biz && $j->exportacaoId === $id);
    Queue::assertPushed(ExportarDadosDaEmpresaJob::class, 1);
    expect(DB::table('activity_log')->where('description', 'exportacao_solicitada')->where('subject_id', $id)->value('business_id'))->toEqual($this->biz);
});

it('UC-BKX-10 · o job gera o zip e o dono baixa pelo link assinado; o download fica registrado', function () {
    Queue::fake();
    bkaComo($this->dono);
    $id = $this->postJson('/backup/empresa/exportacoes')->json('id');
    (new ExportarDadosDaEmpresaJob($this->biz, $id))->handle(app(\App\Backup\ExportacaoEmpresa\ExportadorDaEmpresa::class));

    $pedido = ExportacaoEmpresa::find($id);
    expect($pedido->status)->toBe('pronto');
    expect($pedido->expira_em->toDateTimeString())->toBe('2026-10-28 10:00:00');
    Storage::disk('local')->assertExists($pedido->arquivo);
    expect($pedido->arquivo)->toStartWith("exportacoes-empresa/{$this->biz}/");

    $link = $this->getJson("/backup/empresa/exportacoes/{$id}")->assertOk()->json('link');
    expect($link)->toContain('signature=');
    $this->get($link)->assertOk()->assertDownload();

    expect(ExportacaoEmpresa::find($id)->downloads)->toBe(1);
    expect(DB::table('activity_log')->where('description', 'exportacao_baixada')->where('subject_id', $id)->value('business_id'))->toEqual($this->biz);
});

it('UC-BKX-11 · o dono da outra empresa não alcança o pedido da 98, nem com link assinado', function () {
    $pedido = ExportacaoEmpresa::create([
        'business_id' => $this->biz, 'solicitado_por' => $this->dono->id, 'status' => 'pronto',
        'arquivo' => "exportacoes-empresa/{$this->biz}/x.zip", 'expira_em' => now()->addHour(),
    ]);
    Storage::disk('local')->put($pedido->arquivo, 'zip');
    $link = URL::temporarySignedRoute('backup.empresa.baixar', now()->addHour(), ['id' => $pedido->id]);

    bkaComo($this->donoOutro);
    $this->getJson("/backup/empresa/exportacoes/{$pedido->id}")->assertNotFound();
    $this->get($link)->assertNotFound();
    expect(ExportacaoEmpresa::find($pedido->id)->downloads)->toBe(0);
});

it('UC-BKX-12 · link sem assinatura ou arquivo vencido não baixa', function () {
    $pedido = ExportacaoEmpresa::create([
        'business_id' => $this->biz, 'solicitado_por' => $this->dono->id, 'status' => 'pronto',
        'arquivo' => "exportacoes-empresa/{$this->biz}/y.zip", 'expira_em' => now()->subMinute(),
    ]);
    Storage::disk('local')->put($pedido->arquivo, 'zip');
    bkaComo($this->dono);

    $this->get("/backup/empresa/exportacoes/{$pedido->id}/baixar")->assertForbidden();
    $this->get(URL::temporarySignedRoute('backup.empresa.baixar', now()->addHour(), ['id' => $pedido->id]))->assertStatus(410);
    expect($this->getJson("/backup/empresa/exportacoes/{$pedido->id}")->json('link'))->toBeNull();
});
