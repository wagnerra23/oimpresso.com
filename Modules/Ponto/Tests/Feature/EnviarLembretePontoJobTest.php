<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Jobs\EnviarLembretePontoJob;
use Modules\Ponto\Services\Push\FcmClient;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Envio do lembrete de bater ponto — ADR 0423 §2, §5, §7 (PR 2a).
 *
 * UC-REPP-16 de `resources/js/Pages/Ponto/Mobile/Index.casos.md`, derivado da ADR. O FCM é
 * falso (`Http::fake`); a assinatura do JWT é real, com chave RSA gerada no teste.
 *
 * @covers-us US-PONTO-001
 *
 * Tier 0: tenant fictício 98 (ADR 0358) contra o business 2 semeado pela lane. Transação
 * revertida por caso.
 */

const ELJ_BIZ = 98;
const ELJ_BIZ_OUTRO = 2;

function eljAparelho(int $biz, int $userId, string $token): void
{
    DB::table('ponto_push_dispositivos')->insert([
        'business_id' => $biz, 'user_id' => $userId, 'token' => $token, 'plataforma' => 'android',
        'ativo' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + FK exigem MySQL (ADR 0358).');
    }
    if (! Schema::hasTable('ponto_push_dispositivos')
        || ! DB::table('business')->where('id', ELJ_BIZ)->exists()
        || ! DB::table('business')->where('id', ELJ_BIZ_OUTRO)->exists()) {
        $this->markTestSkipped('Tabela de aparelhos ou tenants 98/2 ausentes nesta lane.');
    }
    DB::beginTransaction();

    $chave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($chave, $pem);
    $this->credenciais = tempnam(sys_get_temp_dir(), 'fcm');
    file_put_contents($this->credenciais, json_encode(['client_email' => 'ci@teste.iam.gserviceaccount.com', 'private_key' => $pem]));
    config()->set('ponto_push.fcm_project_id', 'oimpresso-ci');
    config()->set('ponto_push.fcm_credentials', $this->credenciais);
    Cache::forget('ponto:push:fcm_access_token');
});

afterEach(function () {
    if (isset($this->credenciais) && is_file($this->credenciais)) {
        unlink($this->credenciais);
    }
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('UC-REPP-16: o Job envia só aos aparelhos do business dele, e desativa o token que o FCM recusa', function () {
    $userId = DB::table('users')->insertGetId([
        'first_name' => 'ELJ teste', 'username' => 'elj_' . uniqid(), 'password' => 'x',
        'business_id' => ELJ_BIZ, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $bom = 'tok-bom-' . uniqid();
    $morto = 'tok-morto-' . uniqid();
    $alheio = 'tok-alheio-' . uniqid();
    eljAparelho(ELJ_BIZ, $userId, $bom);
    eljAparelho(ELJ_BIZ, $userId, $morto);
    eljAparelho(ELJ_BIZ_OUTRO, $userId, $alheio);

    Http::fake(function ($request) use ($morto) {
        if (str_contains($request->url(), 'oauth2.googleapis.com')) {
            return Http::response(['access_token' => 'ya29.ci', 'expires_in' => 3600]);
        }
        if (($request->data()['message']['token'] ?? null) === $morto) {
            return Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404);
        }

        return Http::response(['name' => 'projects/oimpresso-ci/messages/1']);
    });

    (new EnviarLembretePontoJob(ELJ_BIZ, $userId, Marcacao::TIPO_ENTRADA, '08:00'))->handle(app(FcmClient::class));

    $enviados = collect(Http::recorded())
        ->map(fn ($par) => $par[0])
        ->filter(fn ($req) => str_contains($req->url(), 'fcm.googleapis.com/v1/projects/oimpresso-ci/'));
    $tokens = $enviados->map(fn ($req) => $req->data()['message']['token'])->sort()->values()->all();
    expect($tokens)->toBe(collect([$bom, $morto])->sort()->values()->all());
    expect($enviados->first()->hasHeader('Authorization', 'Bearer ya29.ci'))->toBeTrue();
    expect($enviados->first()->data()['message']['notification']['body'])->toBe('Entrada às 08:00. Toque para bater o ponto.');
    expect($enviados->first()->data()['message']['data'])->toBe(['url' => '/ponto/mobile']);

    $jwt = collect(Http::recorded())->map(fn ($par) => $par[0])
        ->first(fn ($req) => str_contains($req->url(), 'oauth2.googleapis.com'))->data()['assertion'];
    expect(substr_count($jwt, '.'))->toBe(2);

    expect((bool) DB::table('ponto_push_dispositivos')->where('token', $morto)->value('ativo'))->toBeFalse();
    expect((bool) DB::table('ponto_push_dispositivos')->where('token', $bom)->value('ativo'))->toBeTrue();
    expect((bool) DB::table('ponto_push_dispositivos')->where('token', $alheio)->value('ativo'))->toBeTrue();
});
