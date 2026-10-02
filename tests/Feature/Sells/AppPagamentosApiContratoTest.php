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
 * API de Pagamentos do app das lojas (tela 15) — GET /api/app/pagamentos, SÓ LEITURA.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §10.5 (formato do oimpresso-app#47).
 * NÃO derivado do controller. A escrita (gerar cobrança) não existe aqui: o POST tem que dar 405.
 *
 * Tier 0 (ADR 0093): cobrança do business 2 nunca aparece no 98 (controle positivo em par).
 * As cobranças do teste ficam em 2099 para ficarem no topo da lista "mais recente primeiro";
 * os contadores são conferidos por delta.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    if (! Schema::hasTable('cobrancas')) {
        $this->markTestSkipped('Schema ausente (cobrancas).');
    }
    $this->tenant = $this->seededTenant();
    $this->outro = \App\Business::where('id', '!=', $this->tenant->id)->orderBy('id')->first();
    if (! $this->outro) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }
    Permission::firstOrCreate(['name' => 'financeiro.access', 'guard_name' => 'web']);
});

function appPagPlano(bool $comFinanceiro): void
{
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($biz, $perm) => $comFinanceiro && $perm === 'financeiro_module');
    app()->instance(ModuleUtil::class, $mu);
}

function appPagUsuario(int $businessId, bool $acesso): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'APP Pag', 'username' => 'app_pag_' . uniqid(), 'password' => 'x',
        'business_id' => $businessId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $u = User::findOrFail($id);
    if ($acesso) {
        $u->givePermissionTo('financeiro.access');
    }

    return $u;
}

/** @param array<string, mixed> $extra */
function appPagCobranca(int $biz, string $status, string $tipo, string $vencimento, string $criada, array $extra = []): int
{
    return (int) DB::table('cobrancas')->insertGetId($extra + [
        'business_id' => $biz, 'tipo' => $tipo, 'status' => $status, 'valor_centavos' => 231500,
        'vencimento' => $vencimento, 'descricao' => 'Cobrança APP', 'payer_name' => 'Pagador APP',
        'idempotency_key' => (string) Str::uuid(), 'created_at' => $criada, 'updated_at' => $criada,
    ]);
}

it('sem token 401; sem acesso ao Financeiro 403; e não existe escrita (POST 405)', function () {
    $this->getJson('/api/app/pagamentos')->assertStatus(401);
    appPagPlano(true);
    Passport::actingAs(appPagUsuario((int) $this->tenant->id, false), [], 'api');
    $this->getJson('/api/app/pagamentos')->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
    $this->postJson('/api/app/pagamentos', [])->assertStatus(405);
});

it('lista as cobranças do business com status, método e valor do app; erro e outro business ficam fora', function () {
    appPagPlano(true);
    $biz = (int) $this->tenant->id;
    Passport::actingAs(appPagUsuario($biz, true), [], 'api');
    $antes = $this->getJson('/api/app/pagamentos')->assertOk()->json('contadores');
    $ontem = now()->subDay()->toDateString();
    $amanha = now()->addDay()->toDateString();

    $paga = appPagCobranca($biz, 'paga', 'pix_cob', $ontem, '2099-01-06 10:00:00', ['paga_em' => '2099-01-06 11:00:00']);
    $aberta = appPagCobranca($biz, 'emitida', 'boleto', $amanha, '2099-01-05 10:00:00', ['boleto_pdf_url' => 'https://exemplo.test/b.pdf']);
    $bolepix = appPagCobranca($biz, 'emitida', 'boleto', $amanha, '2099-01-04 10:00:00', ['pix_emv' => '000201...']);
    $vencida = appPagCobranca($biz, 'emitida', 'card', $ontem, '2099-01-03 10:00:00');
    $cancelada = appPagCobranca($biz, 'cancelada', 'boleto', $amanha, '2099-01-02 10:00:00');
    $erro = appPagCobranca($biz, 'erro', 'boleto', $amanha, '2099-01-07 10:00:00');
    $alheia = appPagCobranca((int) $this->outro->id, 'emitida', 'boleto', $amanha, '2099-01-08 10:00:00');

    $r = $this->getJson('/api/app/pagamentos')->assertOk();
    $ids = array_column($r->json('itens'), 'id');

    expect(array_slice($ids, 0, 5))->toBe([$paga, $aberta, $bolepix, $vencida, $cancelada]);
    expect($ids)->not->toContain($erro);
    expect($ids)->not->toContain($alheia);
    expect($r->json('itens.0'))->toMatchArray(['valor' => 2315, 'metodo' => 'pix', 'status' => 'pago', 'link' => null]);
    expect($r->json('itens.0.pago_em'))->toStartWith('2099-01-06');
    expect($r->json('itens.1'))->toMatchArray([
        'descricao' => 'Cobrança APP · Pagador APP', 'vencimento' => $amanha, 'metodo' => 'boleto',
        'status' => 'pendente', 'pago_em' => null, 'link' => 'https://exemplo.test/b.pdf',
    ]);
    expect($r->json('itens.2.metodo'))->toBe('qualquer');
    expect($r->json('itens.3'))->toMatchArray(['metodo' => 'cartao', 'status' => 'vencido']);
    expect($r->json('itens.4.status'))->toBe('cancelado');

    $c = $r->json('contadores');
    foreach (['todos' => 5, 'pago' => 1, 'pendente' => 2, 'vencido' => 1, 'cancelado' => 1] as $k => $n) {
        expect($c[$k] - $antes[$k])->toBe($n);
    }

    $f = $this->getJson('/api/app/pagamentos?status=pendente')->assertOk();
    expect(array_unique(array_column($f->json('itens'), 'status')))->toBe(['pendente']);
    expect(array_slice(array_column($f->json('itens'), 'id'), 0, 2))->toBe([$aberta, $bolepix]);
});

it('Início: a área pagamentos segue a regra do Financeiro', function () {
    appPagPlano(true);
    Passport::actingAs(appPagUsuario((int) $this->tenant->id, true), [], 'api');
    expect($this->getJson('/api/app/inicio')->assertOk()->json('areas'))->toContain('pagamentos');
    Passport::actingAs(appPagUsuario((int) $this->tenant->id, false), [], 'api');
    expect($this->getJson('/api/app/inicio')->assertOk()->json('areas'))->not->toContain('pagamentos');
});
