<?php

declare(strict_types=1);

// @covers-us UC-BKP-01 UC-BKP-02 UC-BKP-09

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * ONDA 3 (F3) — o render Inertia da tela Backup/Index, atras da flag `mwart.backup_index`.
 *
 * Roda NO CT 100 contra o MySQL real, NUNCA local nem sqlite (proibicoes.md secao Ambiente).
 *
 * O dual-render e o contrato aqui: com a flag DESLIGADA a rota segue no Blade legado; com ela
 * LIGADA nasce o componente Inertia com as props que a tela consome. Testar os dois lados e o
 * que impede a onda de virar troca-por-baixo-do-pano.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('business') || ! Schema::hasTable('users')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode no CT 100 (MySQL real). NAO sqlite.');
    }

    Permission::firstOrCreate(['name' => 'backup', 'guard_name' => 'web']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->biz = $this->seededTenant();                 // biz=98 ficticio (ADR 0358)
    $this->admin = User::factory()->create(['business_id' => $this->biz->id]);
    $this->admin->givePermissionTo('backup');

    Storage::fake('local');
    config(['backup.backup.destination.disks' => ['local']]);
    config(['mwart.backup_index.enabled' => true]);

    $this->pasta = str_replace(chr(92), '/', (string) config('backup.backup.name'));
    // o de 03:00 e "agendado"; o das 15:00 e "manual" — e o mais NOVO e o de hoje
    Storage::disk('local')->put($this->pasta.'/2026-08-19-03-00-04.zip', 'zip-agendado');
    Storage::disk('local')->put($this->pasta.'/2026-08-18-15-00-04.zip', 'zip-manual');

    $this->actingAs($this->admin);
    session(['user.business_id' => $this->biz->id, 'business.id' => $this->biz->id]);
});

/** GET /backup como Inertia, devolvendo o page object decodificado. */
function backupPage($teste): array
{
    $r = $teste->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])->get('/backup');
    $r->assertOk();

    return json_decode($r->getContent(), true);
}

// UC-BKP-01 — saber em um olhar se existe backup de hoje
test('a rota renderiza Backup/Index com as props que a tela consome', function () {
    $page = backupPage($this);

    expect($page['component'])->toBe('Backup/Index');
    expect($page['props'])->toHaveKeys(['destino', 'retencao', 'cron', 'pode', 'agendado_ok']);
    expect($page['props']['retencao']['manter'])->toBe(5);
});

// UC-BKP-01 — a lista vem do mais novo pro mais velho, e so .zip
test('a lista vem do mais novo pro mais velho e ignora quem nao e zip', function () {
    Storage::disk('local')->put($this->pasta.'/lixo.txt', 'nao-e-backup');

    $backups = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])
        ->get('/backup?only=backups')
        ->json('props.backups');

    expect($backups)->toHaveCount(2);
    expect($backups[0]['file_name'])->toBe('2026-08-19-03-00-04.zip');
    expect(collect($backups)->pluck('file_name')->every(fn ($n) => str_ends_with($n, '.zip')))->toBeTrue();
});

// UC-BKP-01 — a origem sai do ARQUIVO, nao do config (anti-hook do charter)
test('origem e derivada da hora do arquivo, nao do cron parseado', function () {
    $backups = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])
        ->get('/backup?only=backups')
        ->json('props.backups');

    $porNome = collect($backups)->keyBy('file_name');
    expect($porNome['2026-08-19-03-00-04.zip']['origem'])->toBe('agendado');
    expect($porNome['2026-08-18-15-00-04.zip']['origem'])->toBe('manual');
});

// UC-BKP-02 — ver que o backup esta no mesmo servidor
test('destino local marca remoto=false e aponta a pasta dentro de public/uploads', function () {
    $page = backupPage($this);

    expect($page['props']['destino']['remoto'])->toBeFalse();
    expect($page['props']['destino']['pasta'])->toStartWith('public/uploads/');
});

// UC-BKP-09 — em demo as acoes desligam sem 500, e o cron nao vaza
test('em demo as acoes vem desabilitadas com motivo e o cron fica vazio', function () {
    config(['app.env' => 'demo']);

    $page = backupPage($this);

    expect($page['props']['pode']['gerar'])->toBeFalse();
    expect($page['props']['pode']['motivo'])->not->toBeNull();
    expect($page['props']['cron'])->toBe('');
});

// o dual-render precisa continuar servindo o Blade com a flag desligada
test('com a flag desligada a rota segue no Blade legado', function () {
    config(['mwart.backup_index.enabled' => false]);

    $r = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])->get('/backup');

    $r->assertOk();
    expect($r->headers->get('x-inertia'))->toBeNull();   // Blade nao responde como Inertia
});
