<?php

declare(strict_types=1);

// @covers-us UC-BKP-05 UC-BKP-06 UC-BKP-07 UC-BKP-10

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * ONDA 1 da migracao do Backup — endurecimento do legado (sem tocar a UI).
 *
 * Roda NO CT 100 contra o MySQL real, NUNCA local nem sqlite (proibicoes.md secao Ambiente).
 *
 * O que estes casos defendem (medido em app/Http/Controllers/BackUpController.php@HEAD~):
 *
 *  1. TRAVESSIA DE CAMINHO em download/delete. O legado fazia
 *     `config('backup.backup.name').'/'.$file_name` sem validar. O disco de backup e o
 *     `local`, cuja raiz e `public_path('uploads')` (config/filesystems.php) — ou seja, a
 *     MESMA pasta onde vivem logos, imagens de produto e documentos de TODOS os tenants.
 *
 *     ATENCAO, precisao medida (Flysystem 3.33): um `..` que ESCAPA a raiz do disco lanca
 *     PathTraversalDetected (WhitespacePathNormalizer::normalizeRelativePath), entao o
 *     payload `..%2F..%2F..%2F.env` NAO le o .env do projeto — vira 500. O dano real e
 *     alcancavel e DENTRO de `public/uploads`: ler e, sobretudo, APAGAR arquivo de outro
 *     tenant via delete. E isso que estes casos travam. Ver ADR 0093 (Tier 0).
 *
 *  2. `catch (Exception $e)` SEM barra dentro de `namespace App\Http\Controllers`: a classe
 *     nao existe, o catch nunca casava e falha de backup virava 500 em vez de banner.
 *
 *  3. Excluir o UNICO backup do disco era permitido, sem lixeira.
 *
 *  4. `Route::resource(...)->only(..., 'store')` registrava POST /backup sem `store()` no
 *     controller — a rota existia e estourava.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('business') || ! Schema::hasTable('users')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode no CT 100 (MySQL real). NAO sqlite.');
    }

    Permission::firstOrCreate(['name' => 'backup', 'guard_name' => 'web']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->biz = $this->seededTenant();                 // biz=98 ficticio (ADR 0358)

    $this->comPermissao = User::factory()->create(['business_id' => $this->biz->id]);
    $this->comPermissao->givePermissionTo('backup');

    $this->semPermissao = User::factory()->create(['business_id' => $this->biz->id]);

    // Disco de backup falso. `vizinho.png` fica na RAIZ do disco — e o arquivo de outro
    // tenant que a travessia alcancaria (public/uploads/...), e o nosso canario.
    Storage::fake('local');
    config(['backup.backup.destination.disks' => ['local']]);

    $this->pasta = str_replace(chr(92), '/', (string) config('backup.backup.name'));
    Storage::disk('local')->put($this->pasta.'/2026-08-19-03-00-04.zip', 'zip-novo');
    Storage::disk('local')->put($this->pasta.'/2026-08-18-03-00-04.zip', 'zip-velho');
    Storage::disk('local')->put('vizinho.png', 'arquivo-de-outro-tenant');

    $this->actingAs($this->comPermissao);
    session(['user.business_id' => $this->biz->id, 'business.id' => $this->biz->id]);
});

// UC-BKP-07 — sem permissao, a tela nao existe
test('as quatro rotas devolvem 403 sem a permissao backup', function () {
    $this->actingAs($this->semPermissao);

    $this->get('/backup')->assertForbidden();
    $this->get('/backup/create')->assertForbidden();
    $this->get('/backup/download/2026-08-19-03-00-04.zip')->assertForbidden();
    $this->get('/backup/2026-08-19-03-00-04.zip/delete')->assertForbidden();
});

// UC-BKP-06 — nome de arquivo nao sai da pasta de backup (Tier 0)
dataset('nomes recusados', [
    'sobe um nivel (alcanca public/uploads)' => '../vizinho.png',
    'sobe varios niveis' => '../../../.env',
    'sem extensao zip' => 'vizinho.png',
    'php disfarcado' => '2026-08-19-03-00-04.zip.php',
    'zip que nao existe' => 'nao-existe.zip',
    'barra crua no nome' => 'sub/2026-08-19-03-00-04.zip',
]);

test('download recusa nome fora do padrao e nao entrega arquivo vizinho', function (string $nome) {
    $this->get('/backup/download/'.$nome)->assertNotFound();

    expect(Storage::disk('local')->get('vizinho.png'))->toBe('arquivo-de-outro-tenant');
})->with('nomes recusados');

test('delete recusa nome fora do padrao e nao apaga arquivo vizinho', function (string $nome) {
    $this->get('/backup/'.$nome.'/delete')->assertNotFound();

    expect(Storage::disk('local')->exists('vizinho.png'))->toBeTrue();
    expect(Storage::disk('local')->files($this->pasta))->toHaveCount(2);
})->with('nomes recusados');

// UC-BKP-05 — nao conseguir apagar o unico backup
test('nao exclui quando e o unico backup do disco', function () {
    Storage::disk('local')->delete($this->pasta.'/2026-08-18-03-00-04.zip');

    $this->from('/backup')->get('/backup/2026-08-19-03-00-04.zip/delete')
        ->assertRedirect('/backup')
        ->assertSessionHas('status.success', 0);

    expect(Storage::disk('local')->files($this->pasta))->toHaveCount(1);
});

test('exclui quando ha mais de um backup', function () {
    $this->from('/backup')->get('/backup/2026-08-18-03-00-04.zip/delete')
        ->assertSessionHas('status.success', 1);

    expect(Storage::disk('local')->exists($this->pasta.'/2026-08-18-03-00-04.zip'))->toBeFalse();
    expect(Storage::disk('local')->exists($this->pasta.'/2026-08-19-03-00-04.zip'))->toBeTrue();
});

// UC-BKP-10 (demo) + download legitimo
test('download entrega o zip como attachment', function () {
    $r = $this->get('/backup/download/2026-08-19-03-00-04.zip');

    $r->assertOk();
    expect($r->headers->get('content-disposition'))->toContain('2026-08-19-03-00-04.zip');
});

test('em demo o download e bloqueado sem 500', function () {
    config(['app.env' => 'demo']);

    $this->from('/backup')->get('/backup/download/2026-08-19-03-00-04.zip')
        ->assertRedirect('/backup')
        ->assertSessionHas('status.success', 0);
});

// POST /backup existia sem store() no controller
test('POST /backup responde sem estourar (store existe)', function () {
    config(['app.env' => 'demo']);   // demo evita rodar backup:run de verdade no CT 100

    $r = $this->from('/backup')->post('/backup');

    expect($r->status())->toBeLessThan(500);
});
