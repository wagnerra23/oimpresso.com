<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Tests\Contract\AutosaveContractRunner;

/**
 * Anexos da OS no app das lojas (P2, PR 09 do playbook propostas-app) — GET e POST
 * /api/app/os/{id}/arquivos + o conteúdo pela URL assinada.
 *
 * Contrato: memory/requisitos/AppMobile/api/tela-03-anexos.md (UC-APPANX-01..08). NÃO derivado do
 * controller: lista do mais novo, URL assinada de 10 min, etapa gravada pelo servidor, jpg/png/pdf
 * até 10 MB (413 acima), ajuste `app_anexos_camera`, Idempotency-Key.
 *
 * Tier 0 (ADR 0093): OS de OUTRO business → 404 sem gravar nada; anexo de outro business nunca
 * aparece. Controle positivo em par: o anexo do próprio business APARECE.
 */
uses(DatabaseTransactions::class);

const APPANX_OS = 'Modules\OficinaAuto\Entities\ServiceOrder';

/** @return array<string,int> chave → id das etapas não-terminais + entregue */
function appAnxProcesso(int $bizId): array
{
    $proc = DB::table('sale_processes')->where('business_id', $bizId)->where('key', 'oficina_mecanica_os')->value('id')
        ?? DB::table('sale_processes')->insertGetId([
            'business_id' => $bizId, 'key' => 'oficina_mecanica_os', 'name' => 'Oficina — OS de Mecânica',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    $ids = [];
    foreach (['recepcao' => [0, false], 'em_execucao' => [4, false], 'entregue' => [6, true]] as $key => [$ordem, $terminal]) {
        $ids[$key] = DB::table('sale_process_stages')->where('process_id', $proc)->where('key', $key)->value('id')
            ?? DB::table('sale_process_stages')->insertGetId([
                'process_id' => $proc, 'key' => $key, 'name' => ucfirst($key), 'sort_order' => $ordem,
                'is_initial' => $ordem === 0, 'is_terminal' => $terminal,
                'created_at' => now(), 'updated_at' => now(),
            ]);
    }

    return $ids;
}

function appAnxOs(int $bizId, ?int $stageId): int
{
    $veiculo = DB::table('vehicles')->insertGetId([
        'business_id' => $bizId, 'plate' => 'ANX' . random_int(1000, 9999), 'vehicle_type' => 'caminhao',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return DB::table('service_orders')->insertGetId([
        'business_id' => $bizId, 'vehicle_id' => $veiculo, 'order_type' => 'mecanica', 'status' => 'aberta',
        'current_stage_id' => $stageId, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function appAnxLinha(int $bizId, int $osId, string $nome, string $quando, ?int $userId = null): int
{
    return DB::table('arquivos')->insertGetId([
        'business_id' => $bizId, 'arquivable_type' => APPANX_OS, 'arquivable_id' => $osId,
        'disk' => 'arquivos', 'storage_path' => "biz-{$bizId}/teste/{$nome}", 'original_name' => $nome,
        'mime_type' => 'image/jpeg', 'size_bytes' => 1234, 'md5' => md5($nome . $quando),
        'uploaded_by_user_id' => $userId, 'created_at' => $quando, 'updated_at' => $quando,
    ]);
}

/** JPEG real (o validador lê o conteúdo, não a extensão). */
function appAnxJpeg(string $nome = 'foto.jpg'): UploadedFile
{
    $bytes = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');

    return UploadedFile::fake()->createWithContent($nome, $bytes);
}

beforeEach(function () {
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['service_orders', 'vehicles', 'arquivos', 'app_idempotencia', 'sale_processes', 'sale_process_stages'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }
    if (! Schema::hasColumn('arquivos', 'legenda')) {
        $this->markTestSkipped('Migration de legenda/etapa em arquivos não rodou.');
    }

    Storage::fake('arquivos');
    Storage::fake('vault');

    $ctx = AutosaveContractRunner::setupSellsContext($this);
    $this->biz = $ctx['business'];
    $this->user = $ctx['user'];
    $this->outroBiz = \App\Business::where('id', '!=', $this->biz->id)->first();
    if (! $this->outroBiz) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }

    if (app(\App\Utils\ModuleUtil::class)->isSuperadminInstalled() && Schema::hasTable('subscriptions')) {
        DB::table('subscriptions')->insert([
            'business_id' => $this->biz->id, 'package_id' => 0, 'package_price' => 0,
            'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
            'package_details' => json_encode(['oficina_auto_module' => 1]), 'created_id' => $this->user->id,
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    foreach (['oficinaauto.service_order.view', 'oficinaauto.service_order.update'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        $this->user->givePermissionTo($p);
    }

    $this->etapas = appAnxProcesso((int) $this->biz->id);
    $this->os = appAnxOs((int) $this->biz->id, $this->etapas['em_execucao']);
    Passport::actingAs($this->user, [], 'api');
});

function appAnxEnviar(object $t, int $osId, array $corpo, string $chave = 'k-1')
{
    return $t->withHeaders(['Accept' => 'application/json', 'Idempotency-Key' => $chave])
        ->post("/api/app/os/{$osId}/arquivos", $corpo);
}

it('UC-APPANX-01: lista do mais novo para o mais antigo, com URL assinada de 10 min e pode_enviar', function () {
    $velho = appAnxLinha((int) $this->biz->id, $this->os, 'velho.jpg', now()->subDay()->toDateTimeString(), (int) $this->user->id);
    $novo = appAnxLinha((int) $this->biz->id, $this->os, 'novo.jpg', now()->toDateTimeString());

    $r = $this->getJson("/api/app/os/{$this->os}/arquivos")->assertOk();

    expect(collect($r->json('itens'))->pluck('id')->all())->toBe([$novo, $velho]);
    expect($r->json('pode_enviar'))->toBeTrue();
    $item = $r->json('itens.1');
    expect($item['nome'])->toBe('velho.jpg');
    expect($item['tipo'])->toBe('image/jpeg');
    expect($item['tamanho'])->toBe(1234);
    expect($item['enviado_por'])->not->toBeNull();
    expect($item['enviado_em'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/');
    // Assinada, nunca o caminho do disco; expira em ~10 min.
    expect($item['url'])->toContain('signature=');
    expect($item['url'])->not->toContain('biz-' . $this->biz->id . '/teste');
    parse_str((string) parse_url($item['url'], PHP_URL_QUERY), $q);
    expect((int) $q['expires'] - now()->timestamp)->toBeGreaterThan(540);
    expect((int) $q['expires'] - now()->timestamp)->toBeLessThanOrEqual(600);
    expect($item['miniatura'])->toBe($item['url']);
});

it('UC-APPANX-02: envia foto → 201 com o item, etapa atual gravada pelo servidor e linha em arquivos', function () {
    $r = appAnxEnviar($this, $this->os, ['arquivo' => appAnxJpeg(), 'legenda' => 'Fachada · antes', 'origem' => 'galeria'])
        ->assertStatus(201);

    expect($r->json('legenda'))->toBe('Fachada · antes');
    expect($r->json('etapa'))->toBe('em_execucao');
    expect($r->json('tipo'))->toBe('image/jpeg');
    $linha = DB::table('arquivos')->where('id', $r->json('id'))->first();
    expect((int) $linha->business_id)->toBe((int) $this->biz->id);
    expect($linha->arquivable_type)->toBe(APPANX_OS);
    expect((int) $linha->arquivable_id)->toBe($this->os);
    expect((int) $linha->uploaded_by_user_id)->toBe((int) $this->user->id);

    // E aparece na lista.
    expect(collect($this->getJson("/api/app/os/{$this->os}/arquivos")->json('itens'))->pluck('id')->all())
        ->toContain($r->json('id'));
});

it('UC-APPANX-03: OS de outro business → 404 no GET e no POST, sem gravar nada; anexo alheio não aparece', function () {
    $osAlheia = appAnxOs((int) $this->outroBiz->id, null);
    $anexoAlheio = appAnxLinha((int) $this->outroBiz->id, $osAlheia, 'alheio.jpg', now()->toDateTimeString());
    $meu = appAnxLinha((int) $this->biz->id, $this->os, 'meu.jpg', now()->toDateTimeString());
    $antes = DB::table('arquivos')->count();

    $this->getJson("/api/app/os/{$osAlheia}/arquivos")->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
    appAnxEnviar($this, $osAlheia, ['arquivo' => appAnxJpeg(), 'origem' => 'galeria'])
        ->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
    expect(DB::table('arquivos')->count())->toBe($antes);

    // Anexo do outro business com o MESMO arquivable_id da minha OS não vaza.
    DB::table('arquivos')->where('id', $anexoAlheio)->update(['arquivable_id' => $this->os]);
    $ids = collect($this->getJson("/api/app/os/{$this->os}/arquivos")->json('itens'))->pluck('id')->all();
    expect($ids)->toContain($meu);
    expect($ids)->not->toContain($anexoAlheio);
});

it('UC-APPANX-04: sem oficinaauto.service_order.update o POST dá 403 e o GET diz pode_enviar=false', function () {
    // Usuário próprio, sem o papel Admin# (que libera tudo pelo Gate::before): só ver a OS.
    $id = DB::table('users')->insertGetId([
        'first_name' => 'APP Anx', 'username' => 'app_anx_' . uniqid(), 'password' => 'x',
        'business_id' => $this->biz->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $soVer = \App\User::findOrFail($id);
    $soVer->givePermissionTo('oficinaauto.service_order.view');
    Passport::actingAs($soVer, [], 'api');
    $antes = DB::table('arquivos')->count();

    appAnxEnviar($this, $this->os, ['arquivo' => appAnxJpeg(), 'origem' => 'galeria'])
        ->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
    expect(DB::table('arquivos')->count())->toBe($antes);
    expect($this->getJson("/api/app/os/{$this->os}/arquivos")->assertOk()->json('pode_enviar'))->toBeFalse();
});

it('UC-APPANX-05: tipo não aceito → 422 campos.arquivo; acima de 10 MB → 413', function () {
    appAnxEnviar($this, $this->os, ['arquivo' => UploadedFile::fake()->createWithContent('nota.txt', 'texto puro')])
        ->assertStatus(422)->assertJsonPath('erro', 'validacao')->assertJsonStructure(['campos' => ['arquivo']]);

    appAnxEnviar($this, $this->os, ['arquivo' => UploadedFile::fake()->create('grande.jpg', 10 * 1024 + 1, 'image/jpeg')])
        ->assertStatus(413)->assertJsonPath('erro', 'arquivo_grande');
});

it('UC-APPANX-06: app_anexos_camera=false recusa origem camera com 422 campos.origem; galeria passa', function () {
    $pos = json_decode((string) DB::table('business')->where('id', $this->biz->id)->value('pos_settings'), true) ?: [];
    $pos['app_anexos_camera'] = false;
    DB::table('business')->where('id', $this->biz->id)->update(['pos_settings' => json_encode($pos)]);

    appAnxEnviar($this, $this->os, ['arquivo' => appAnxJpeg(), 'origem' => 'camera'], 'k-cam')
        ->assertStatus(422)->assertJsonStructure(['campos' => ['origem']]);
    appAnxEnviar($this, $this->os, ['arquivo' => appAnxJpeg('g.jpg'), 'origem' => 'galeria'], 'k-gal')
        ->assertStatus(201);
});

it('UC-APPANX-07: mesma Idempotency-Key + mesmo corpo → mesmo anexo; corpo diferente → 422; sem chave → 422', function () {
    $a = appAnxEnviar($this, $this->os, ['arquivo' => appAnxJpeg(), 'legenda' => 'x', 'origem' => 'galeria'], 'k-rep')->assertStatus(201);
    $antes = DB::table('arquivos')->count();
    $b = appAnxEnviar($this, $this->os, ['arquivo' => appAnxJpeg(), 'legenda' => 'x', 'origem' => 'galeria'], 'k-rep')->assertStatus(201);

    expect($b->json('id'))->toBe($a->json('id'));
    expect(DB::table('arquivos')->count())->toBe($antes);

    appAnxEnviar($this, $this->os, ['arquivo' => appAnxJpeg(), 'legenda' => 'outra', 'origem' => 'galeria'], 'k-rep')
        ->assertStatus(422)->assertJsonPath('erro', 'idempotencia_conflito');

    // withHeaders acumula entre requisições do mesmo teste: limpa a chave usada acima.
    $this->flushHeaders();
    $this->withHeaders(['Accept' => 'application/json'])
        ->post("/api/app/os/{$this->os}/arquivos", ['arquivo' => appAnxJpeg()])
        ->assertStatus(422)->assertJsonStructure(['campos' => ['idempotency_key']]);
});

it('UC-APPANX-08: a URL assinada abre o conteúdo sem token; adulterada → 403', function () {
    $item = appAnxEnviar($this, $this->os, ['arquivo' => appAnxJpeg(), 'origem' => 'galeria'], 'k-url')->assertStatus(201)->json();

    $this->app['auth']->forgetGuards();
    $r = $this->get($item['url'])->assertOk();
    expect($r->headers->get('Content-Type'))->toContain('image/jpeg');
    expect(strlen((string) $r->getContent()))->toBeGreaterThan(0);

    // Trocar o business dentro da URL quebra a assinatura.
    $adulterada = preg_replace('/([?&]biz=)\d+/', '${1}' . $this->outroBiz->id, $item['url']);
    $this->get($adulterada)->assertStatus(403);
});
