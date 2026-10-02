<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;

/**
 * API do Fiscal do app das lojas (tela 14) — GET /api/app/fiscal, só leitura.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §10.2 (formato fechado com o app no
 * oimpresso-app#38). NÃO derivado do controller.
 *
 * Tier 0 (ADR 0093): notas do business 2 nunca aparecem no 98. Controle positivo em par: as do
 * próprio business aparecem, senão o "não aparece" seria verde por vácuo.
 *
 * As notas criadas aqui ficam em 2099 para ficarem no topo da lista "mais recente primeiro"
 * independente do que o seed tiver; os contadores são conferidos por delta.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['nfe_emissoes', 'nfse_emissoes'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }

    $this->tenant = $this->seededTenant();
    $this->outro = \App\Business::where('id', '!=', $this->tenant->id)->orderBy('id')->first();
    if (! $this->outro) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }
    foreach (['fiscal.nfe.view', 'fiscal.nfse.view'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
});

function appFisPlano(bool $comFiscal): void
{
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($biz, $perm) => $comFiscal && $perm === 'fiscal_module');
    app()->instance(ModuleUtil::class, $mu);
}

/** @param list<string> $perms */
function appFisUsuario(int $businessId, array $perms): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'APP Fis', 'username' => 'app_fis_' . uniqid(), 'password' => 'x',
        'business_id' => $businessId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $u = User::findOrFail($id);
    foreach ($perms as $p) {
        $u->givePermissionTo($p);
    }

    return $u;
}

/** @param array<string, mixed> $extra */
function appFisNfe(int $biz, string $modelo, string $status, string $quando, array $extra = []): int
{
    return (int) DB::table('nfe_emissoes')->insertGetId($extra + [
        'business_id' => $biz, 'modelo' => $modelo, 'serie' => '9', 'numero' => random_int(800000, 999999),
        'status' => $status, 'valor_total' => 100.00, 'emitido_em' => $quando,
        'metadata' => json_encode(['dest_name' => 'Destinatário APP']), 'created_at' => $quando, 'updated_at' => $quando,
    ]);
}

/** @param array<string, mixed> $extra */
function appFisNfse(int $biz, string $status, string $quando, array $extra = []): int
{
    return (int) DB::table('nfse_emissoes')->insertGetId($extra + [
        'business_id' => $biz, 'competencia' => '2099-01-01', 'tomador_nome' => 'Tomador APP', 'descricao' => 'Serviço APP',
        'valor_servicos' => 250.00, 'status' => $status, 'idempotency_key' => (string) Str::uuid(),
        'created_at' => $quando, 'updated_at' => $quando,
    ]);
}

it('sem token 401; sem permissão fiscal 403; sem o módulo no plano 403', function () {
    $this->getJson('/api/app/fiscal')->assertStatus(401);

    appFisPlano(true);
    Passport::actingAs(appFisUsuario((int) $this->tenant->id, []), [], 'api');
    $this->getJson('/api/app/fiscal')->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');

    appFisPlano(false);
    Passport::actingAs(appFisUsuario((int) $this->tenant->id, ['fiscal.nfe.view', 'fiscal.nfse.view']), [], 'api');
    $this->getJson('/api/app/fiscal')->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
});

it('lista NF-e, NFC-e e NFS-e do business, mais recente primeiro, com status mapeado; outro business fica fora', function () {
    appFisPlano(true);
    $biz = (int) $this->tenant->id;
    Passport::actingAs(appFisUsuario($biz, ['fiscal.nfe.view', 'fiscal.nfse.view']), [], 'api');
    $antes = $this->getJson('/api/app/fiscal')->assertOk()->json('contadores');

    $chave = str_repeat('4', 44);
    $nfe = appFisNfe($biz, '55', 'autorizada', '2099-01-05 10:00:00', ['chave_44' => $chave, 'cstat' => '100', 'motivo' => 'Autorizado o uso da NF-e']);
    $nfce = appFisNfe($biz, '65', 'rejeitada', '2099-01-04 10:00:00', ['cstat' => '539', 'motivo' => 'Duplicidade de NF-e']);
    $nfse = appFisNfse($biz, 'emitida', '2099-01-03 10:00:00', ['numero' => '77', 'provider_codigo_verificacao' => 'ABC123']);
    $inut = appFisNfe($biz, '55', 'inutilizada', '2099-01-02 10:00:00');
    $pend = appFisNfe($biz, '55', 'pendente', '2099-01-01 10:00:00');
    $rasc = appFisNfse($biz, 'rascunho', '2098-12-31 10:00:00');
    $alheia = appFisNfe((int) $this->outro->id, '55', 'autorizada', '2099-01-06 10:00:00');
    $alheiaS = appFisNfse((int) $this->outro->id, 'emitida', '2099-01-06 11:00:00');

    $r = $this->getJson('/api/app/fiscal')->assertOk();
    $topo = array_map(fn ($i) => $i['tipo'] . ':' . $i['id'], array_slice($r->json('itens'), 0, 6));

    expect($topo)->toBe(["NFe:{$nfe}", "NFCe:{$nfce}", "NFSe:{$nfse}", "NFe:{$inut}", "NFe:{$pend}", "NFSe:{$rasc}"]);
    $todos = array_map(fn ($i) => $i['tipo'] . ':' . $i['id'], $r->json('itens'));
    expect($todos)->not->toContain("NFe:{$alheia}");
    expect($todos)->not->toContain("NFSe:{$alheiaS}");

    expect($r->json('itens.0'))->toMatchArray([
        'tipo' => 'NFe', 'valor' => 100, 'status' => 'autorizado', 'chave' => $chave, 'erro' => null,
        'referencia' => 'Destinatário APP',
    ]);
    expect($r->json('itens.0.emitido_em'))->toStartWith('2099-01-05T10:00:00');
    expect($r->json('itens.1'))->toMatchArray(['status' => 'rejeitado', 'chave' => null, 'erro' => 'Rejeição 539: Duplicidade de NF-e']);
    expect($r->json('itens.2'))->toMatchArray(['numero' => '77', 'valor' => 250, 'status' => 'autorizado', 'referencia' => 'Tomador APP']);
    expect($r->json('itens.3.status'))->toBe('cancelado');
    expect($r->json('itens.4.status'))->toBe('processando');
    expect($r->json('itens.5.status'))->toBe('rascunho');

    $c = $r->json('contadores');
    foreach (['todos' => 6, 'autorizado' => 2, 'rejeitado' => 1, 'cancelado' => 1, 'processando' => 1, 'rascunho' => 1] as $k => $n) {
        expect($c[$k] - $antes[$k])->toBe($n);
    }
    expect($r->json('pagina'))->toBe(1);
});

it('filtro de status: só as do status; referencia traz o pedido da venda do mesmo business', function () {
    appFisPlano(true);
    $biz = (int) $this->tenant->id;
    Passport::actingAs(appFisUsuario($biz, ['fiscal.nfe.view']), [], 'api');
    $venda = DB::table('transactions')->where('business_id', $biz)->whereNotNull('invoice_no')->value('id');
    $rej = appFisNfe($biz, '55', 'rejeitada', '2099-02-01 10:00:00', ['cstat' => '204', 'motivo' => 'Duplicidade', 'transaction_id' => $venda]);
    appFisNfe($biz, '55', 'autorizada', '2099-02-02 10:00:00');

    $r = $this->getJson('/api/app/fiscal?status=rejeitado')->assertOk();

    expect(array_unique(array_column($r->json('itens'), 'status')))->toBe(['rejeitado']);
    expect($r->json('itens.0.id'))->toBe($rej);
    if ($venda) {
        $inv = DB::table('transactions')->where('id', $venda)->value('invoice_no');
        expect($r->json('itens.0.referencia'))->toStartWith('Pedido #' . $inv);
    }
});

it('quem só vê NF-e não recebe NFS-e (lista nem contador)', function () {
    appFisPlano(true);
    $biz = (int) $this->tenant->id;
    Passport::actingAs(appFisUsuario($biz, ['fiscal.nfe.view']), [], 'api');
    $nfse = appFisNfse($biz, 'emitida', '2099-03-01 10:00:00');
    $nfe = appFisNfe($biz, '55', 'autorizada', '2099-03-01 09:00:00');

    $tipos = array_map(fn ($i) => $i['tipo'] . ':' . $i['id'], $this->getJson('/api/app/fiscal')->assertOk()->json('itens'));

    expect($tipos)->toContain("NFe:{$nfe}");
    expect($tipos)->not->toContain("NFSe:{$nfse}");
    expect(array_unique(array_map(fn ($t) => explode(':', $t)[0], $tipos)))->not->toContain('NFSe');
});

it('Início: a área fiscal aparece com a mesma regra do endpoint', function () {
    appFisPlano(true);
    Passport::actingAs(appFisUsuario((int) $this->tenant->id, ['fiscal.nfse.view']), [], 'api');
    $r = $this->getJson('/api/app/inicio')->assertOk();
    expect($r->json('areas'))->toContain('fiscal');
    expect($r->json('perfil'))->toBe('erp');

    Passport::actingAs(appFisUsuario((int) $this->tenant->id, []), [], 'api');
    expect($this->getJson('/api/app/inicio')->assertOk()->json('areas'))->not->toContain('fiscal');
});
