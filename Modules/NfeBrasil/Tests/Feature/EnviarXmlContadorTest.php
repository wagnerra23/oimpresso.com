<?php

declare(strict_types=1);

// @covers-us US-NFE-073 — envio mensal do XML ao contador (R-NFE-038).

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Mail\PacoteXmlContadorMail;
use Modules\NfeBrasil\Services\PacoteXmlContadorService;

uses(Tests\TestCase::class);

/**
 * R-NFE-038 · casos derivados da US-NFE-073 (SPEC), não do service.
 * Tenant 98 (dono), 99 (vizinho) — ADR 0358. Números 9_730_xxx em `nfe_emissoes` reservados
 * a este arquivo; o storage é falso (Storage::fake) e o e-mail também (Mail::fake).
 */

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql'
        || ! Schema::hasTable('nfe_emissoes')
        || ! Schema::hasTable('nfe_contadores')
        || ! Schema::hasTable('nfe_envios_contador')) {
        $this->markTestSkipped('precisa do schema MySQL de nfe_emissoes, nfe_contadores e nfe_envios_contador');
    }
    Storage::fake();
    Mail::fake();
    $this->a = $this->seededTenant()->id;
    $this->b = $this->seededSupportClientTenant()->id;
    xc38Limpar([$this->a, $this->b]);
});

afterEach(function () {
    if (isset($this->a) && Schema::hasTable('nfe_envios_contador')) {
        xc38Limpar([$this->a, $this->b]);
    }
});

function xc38Limpar(array $bizs): void
{
    DB::table('nfe_emissoes')->whereBetween('numero', [9_730_000, 9_730_999])->delete();
    DB::table('nfe_envios_contador')->whereIn('business_id', $bizs)->delete();
    DB::table('nfe_contadores')->whereIn('business_id', $bizs)->delete();
}

function xc38Nota(int $biz, int $numero, string $status, string $quando, bool $comXml = true): void
{
    $path = "nfe-brasil/{$biz}/notas/973-{$numero}.xml";
    if ($comXml) {
        Storage::put($path, "<nfeProc><numero>{$numero}</numero><biz>{$biz}</biz></nfeProc>");
    }
    DB::table('nfe_emissoes')->insert([
        'business_id' => $biz, 'modelo' => '55', 'serie' => '973', 'numero' => $numero,
        'status' => $status, 'valor_total' => 10.00, 'xml_path' => $path,
        'emitido_em' => $quando, 'created_at' => $quando, 'updated_at' => $quando,
    ]);
}

function xc38Contador(int $biz, string $email): void
{
    DB::table('nfe_contadores')->insert([
        'business_id' => $biz, 'nome' => 'Contador Fixture', 'email' => $email,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('UC-FNFE-37 · o pacote leva as autorizadas e canceladas do mês, só da empresa', function () {
    xc38Nota($this->a, 9_730_001, 'autorizada', '2026-09-03 10:00:00');
    xc38Nota($this->a, 9_730_002, 'cancelada', '2026-09-20 10:00:00');
    xc38Nota($this->a, 9_730_003, 'rejeitada', '2026-09-21 10:00:00');
    xc38Nota($this->a, 9_730_004, 'autorizada', '2026-10-01 00:00:00'); // outro mês
    xc38Nota($this->b, 9_730_005, 'autorizada', '2026-09-10 10:00:00'); // vizinho

    $p = app(PacoteXmlContadorService::class)->montar($this->a, '2026-09');

    try {
        expect($p['qtd'])->toBe(2);
        expect($p['sem_xml'])->toBe([]);
        $zip = new ZipArchive();
        $zip->open($p['zip']);
        $nomes = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nomes[] = $zip->getNameIndex($i);
        }
        sort($nomes);
        expect($nomes)->toBe(['nfe/serie973-973-9730001.xml', 'nfe/serie973-973-9730002-cancelada.xml']);
        expect(str_contains((string) $zip->getFromName('nfe/serie973-973-9730001.xml'), "<biz>{$this->a}</biz>"))->toBeTrue();
        $zip->close();
    } finally {
        @unlink((string) $p['zip']);
    }
});

it('UC-FNFE-37 · nota sem XML no storage é contada, não some', function () {
    xc38Nota($this->a, 9_730_011, 'autorizada', '2026-09-03 10:00:00');
    xc38Nota($this->a, 9_730_012, 'autorizada', '2026-09-04 10:00:00', comXml: false);

    $p = app(PacoteXmlContadorService::class)->montar($this->a, '2026-09');

    expect($p['qtd'])->toBe(1);
    expect(count($p['sem_xml']))->toBe(1);
    @unlink((string) $p['zip']);
});

it('UC-FNFE-38 · envia ao contador da empresa, registra e não reenvia no mesmo mês', function () {
    xc38Contador($this->a, 'contador-a@example.test');
    xc38Contador($this->b, 'contador-b@example.test');
    xc38Nota($this->a, 9_730_021, 'autorizada', '2026-09-03 10:00:00');

    $svc = app(PacoteXmlContadorService::class);
    expect($svc->enviar($this->a, '2026-09'))->toBe('enviado');
    expect($svc->enviar($this->a, '2026-09'))->toBe('ja_enviado');

    Mail::assertSent(PacoteXmlContadorMail::class, 1);
    Mail::assertSent(PacoteXmlContadorMail::class, fn ($m) => $m->hasTo('contador-a@example.test') && $m->qtd === 1);
    Mail::assertNotSent(PacoteXmlContadorMail::class, fn ($m) => $m->hasTo('contador-b@example.test'));

    $linha = DB::table('nfe_envios_contador')->where('business_id', $this->a)->where('competencia', '2026-09')->first();
    expect($linha->status)->toBe('enviado');
    expect((int) $linha->qtd_xml)->toBe(1);
    // O vizinho tem contador mas nenhuma nota: nada sai para ele.
    expect($svc->enviar($this->b, '2026-09'))->toBe('sem_notas');
    expect(DB::table('nfe_envios_contador')->where('business_id', $this->b)->count())->toBe(0);
});

it('UC-FNFE-39 · sem contador cadastrado, ou em dry-run, nenhum e-mail sai e nada é gravado', function () {
    xc38Nota($this->a, 9_730_031, 'autorizada', '2026-09-03 10:00:00');

    $svc = app(PacoteXmlContadorService::class);
    expect($svc->enviar($this->a, '2026-09'))->toBe('sem_contador');

    xc38Contador($this->a, 'contador-a@example.test');
    $this->artisan('nfe:enviar-xml-contador', ['--mes' => '2026-09', '--business-id' => $this->a, '--dry-run' => true])
        ->assertSuccessful();

    Mail::assertNothingSent();
    expect(DB::table('nfe_envios_contador')->where('business_id', $this->a)->count())->toBe(0);
});

it('UC-FNFE-39 · o agendamento mensal existe e fica atrás da flag desligada', function () {
    expect(config('fiscal.envio_xml_contador_enabled'))->toBeFalse();

    $evento = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(fn ($e) => str_contains((string) $e->command, 'nfe:enviar-xml-contador'));

    expect($evento)->not->toBeNull();
    expect($evento->expression)->toBe('0 7 5 * *');
    expect($evento->filtersPass(app()))->toBeFalse();

    config(['fiscal.envio_xml_contador_enabled' => true]);
    expect($evento->filtersPass(app()))->toBeTrue();
});
