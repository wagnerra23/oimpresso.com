<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Modules\Arquivos\Services\AvisoTitularService;

uses(Tests\TestCase::class);

/**
 * Aviso ao titular — ADR 0421 ([W] 2026-10-01, D5), thread 05 / PR-9 do playbook Arquivos.
 *
 * Contrato (ficha 05 + ADR 0421):
 *   - janela = 30 dias antes do vencimento; vencido e longe do prazo ficam fora;
 *   - só bucket=sensitive com titular identificado (dono = App\Contact);
 *   - registrar grava titular_avisado_at + linha `notice` na trilha, idempotente;
 *   - avisar NÃO apaga nada e NÃO envia nada (canal pendente [W]);
 *   - cross-tenant: business 98 nunca enxerga nem marca arquivo do 99.
 *
 * Tenant fictício 98/99 (ADR 0358). MySQL-only: o enum `notice` só existe no MySQL.
 */

const AVISO_TITULAR_MARCA = 'test-thread05-aviso-titular';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('enum `notice` é MySQL-only — roda na lane arquivos-pest.');
    }
    if (! Schema::hasColumn('arquivos', 'titular_avisado_at')) {
        $this->markTestSkipped('migration do aviso ao titular não aplicada.');
    }
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));
});

afterEach(function () {
    Carbon::setTestNow(null);
    if (DB::connection()->getDriverName() === 'sqlite') {
        return;
    }
    $ids = DB::table('arquivos')->where('classified_by', AVISO_TITULAR_MARCA)->pluck('id');
    DB::table('arquivos_audit_log')->whereIn('arquivo_id', $ids)->delete();
    DB::table('arquivos')->whereIn('id', $ids)->delete();
});

/** Arquivo que vence em $diasParaVencer (prazo 365d via retention_days). */
function avisoTitularArquivo(int $biz, int $diasParaVencer, array $over = []): int
{
    static $seq = 0;
    $seq++;
    $criado = Carbon::now()->copy()->subDays(365 - $diasParaVencer);

    return (int) DB::table('arquivos')->insertGetId(array_merge([
        'business_id'     => $biz,
        'arquivable_type' => \App\Contact::class,
        'arquivable_id'   => 900000 + $seq,
        'disk'            => 'local',
        'storage_path'    => "biz-{$biz}/thread05-{$seq}.pdf",
        'original_name'   => "thread05-{$seq}.pdf",
        'mime_type'       => 'application/pdf',
        'size_bytes'      => 10,
        'md5'             => md5("thread05-{$seq}"),
        'bucket'          => 'sensitive',
        'retention_days'  => 365,
        'classified_by'   => AVISO_TITULAR_MARCA,
        'created_at'      => $criado,
        'updated_at'      => $criado,
    ], $over));
}

it('aviso-titular 1 · janela: entra quem vence em ≤30d; fica fora vencido, longe, não-sensível e sem titular', function () {
    $dentro = avisoTitularArquivo(98, 20);
    $limite = avisoTitularArquivo(98, 30);
    avisoTitularArquivo(98, 45);                                   // longe do prazo
    avisoTitularArquivo(98, -2);                                   // já vencido
    avisoTitularArquivo(98, 10, ['bucket' => 'active']);           // não sensível
    avisoTitularArquivo(98, 10, ['arquivable_type' => 'Modules\\Repair\\Entities\\JobSheet']); // sem titular direto
    avisoTitularArquivo(98, 10, ['deleted_at' => Carbon::now()]); // na lixeira

    $ids = app(AvisoTitularService::class)->elegiveis(98)->pluck('id')->all();

    expect($ids)->toBe([$dentro, $limite]);
});

it('aviso-titular 2 · registrar grava titular_avisado_at + notice na trilha, sem PII e idempotente', function () {
    $id = avisoTitularArquivo(98, 15);
    $svc = app(AvisoTitularService::class);

    expect($svc->registrarAviso(98, $id, 'teste-fake'))->toBeTrue();
    expect($svc->registrarAviso(98, $id, 'teste-fake'))->toBeFalse();

    expect(DB::table('arquivos')->where('id', $id)->value('titular_avisado_at'))->not->toBeNull();

    $linhas = DB::table('arquivos_audit_log')->where('arquivo_id', $id)->where('action', 'notice')->get();
    expect($linhas)->toHaveCount(1);
    expect((int) $linhas[0]->business_id)->toBe(98);

    $payload = json_decode((string) $linhas[0]->payload, true);
    expect(array_keys($payload))->toBe(['canal', 'vence_em', 'dias_restantes']);
    expect($payload['dias_restantes'])->toBe(15);

    // Avisado sai da janela.
    expect(app(AvisoTitularService::class)->elegiveis(98)->pluck('id')->all())->not->toContain($id);
});

it('aviso-titular 3 · avisar não apaga, não expira e não envia nada', function () {
    Notification::fake();
    Mail::fake();
    $id = avisoTitularArquivo(98, 5);
    $total = DB::table('arquivos')->where('classified_by', AVISO_TITULAR_MARCA)->count();

    app(AvisoTitularService::class)->registrarAviso(98, $id, 'teste-fake');

    expect(DB::table('arquivos')->where('id', $id)->value('deleted_at'))->toBeNull();
    expect(DB::table('arquivos')->where('classified_by', AVISO_TITULAR_MARCA)->count())->toBe($total);
    expect(DB::table('arquivos_audit_log')->where('arquivo_id', $id)->whereIn('action', ['soft_delete', 'hard_delete'])->count())->toBe(0);
    Notification::assertNothingSent();
    Mail::assertNothingSent();
});

it('aviso-titular 4 · cross-tenant: 98 não vê nem marca arquivo do 99', function () {
    $do99 = avisoTitularArquivo(99, 10);
    $do98 = avisoTitularArquivo(98, 10);

    $svc = app(AvisoTitularService::class);

    expect($svc->elegiveis(98)->pluck('id')->all())->toBe([$do98]);
    expect($svc->elegiveis(99)->pluck('id')->all())->toBe([$do99]);

    expect($svc->registrarAviso(98, $do99, 'teste-fake'))->toBeFalse();
    expect(DB::table('arquivos')->where('id', $do99)->value('titular_avisado_at'))->toBeNull();
    expect(DB::table('arquivos_audit_log')->where('arquivo_id', $do99)->count())->toBe(0);
});

it('aviso-titular 5 · enum de arquivos_audit_log.action aceita notice', function () {
    $col = DB::selectOne("SHOW COLUMNS FROM arquivos_audit_log LIKE 'action'");
    preg_match_all("/'([^']+)'/", (string) $col->Type, $m);

    expect($m[1])->toContain('notice');
});

it('aviso-titular 6 · canal vazio é recusado (sem canal não há aviso para registrar)', function () {
    $id = avisoTitularArquivo(98, 10);

    expect(fn () => app(AvisoTitularService::class)->registrarAviso(98, $id, '  '))
        ->toThrow(InvalidArgumentException::class);
    expect(DB::table('arquivos')->where('id', $id)->value('titular_avisado_at'))->toBeNull();
});
