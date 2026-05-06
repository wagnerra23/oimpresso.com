<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\NfeBrasil\Models\NfeCertificado;
use Modules\NfeBrasil\Services\CertificadoService;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class)->in(__DIR__);

/**
 * US-NFE-041 fase 2 — ConfiguracaoController (página Inertia).
 *
 * Não usa RefreshDatabase — roda contra DB dev (UltimatePOS + triggers).
 * Limpeza manual em afterEach para nfe_certificados inseridos no teste.
 */

function nfeBootstrapUser(): array
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: ' . $e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode seeder UltimatePOS antes.');
    }

    try {
        $user = User::where('business_id', $business->id)->first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela users indisponível: ' . $e->getMessage());
    }

    if (! $user) {
        test()->markTestSkipped('Sem user no business.');
    }

    foreach (['nfebrasil.settings.manage', 'nfebrasil.access'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    session([
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'business.id'      => $business->id,
        'business.name'    => $business->name ?? '',
        'is_admin'         => true,
    ]);

    return [$business, $user];
}

beforeEach(function () {
    if (! Schema::hasTable('nfe_certificados')) {
        test()->markTestSkipped('Tabela nfe_certificados não existe — rode migrations primeiro.');
    }
});

afterEach(function () {
    try {
        NfeCertificado::where('cnpj_titular', '00000000000000')->forceDelete();
    } catch (\Throwable) {
        // SQLite in-memory não tem a tabela — ignora
    }
});

it('GET /nfe-brasil/configuracao retorna componente Inertia correto sem cert ativo', function () {
    [$business, $user] = nfeBootstrapUser();

    $user->givePermissionTo('nfebrasil.settings.manage');

    $this->actingAs($user)
        ->withSession([
            'user.business_id' => $business->id,
            'business.id'      => $business->id,
        ])
        ->get('/nfe-brasil/configuracao')
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('NfeBrasil/Configuracao/Certificado')
                ->has('cert')
                ->has('upload_url')
        );
});

it('GET /nfe-brasil/configuracao renderiza dados do cert quando existe cert ativo', function () {
    [$business, $user] = nfeBootstrapUser();

    $user->givePermissionTo('nfebrasil.settings.manage');

    // Insere cert fake pra testar a prop
    $cert = NfeCertificado::create([
        'business_id'       => $business->id,
        'uuid'              => \Illuminate\Support\Str::uuid(),
        'cnpj_titular'      => '00000000000000',
        'valido_ate'        => now()->addDays(60)->toDateString(),
        'encrypted_password' => encrypt('teste'),
        'ativo'             => true,
    ]);

    $this->actingAs($user)
        ->withSession([
            'user.business_id' => $business->id,
            'business.id'      => $business->id,
        ])
        ->get('/nfe-brasil/configuracao')
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('NfeBrasil/Configuracao/Certificado')
                ->where('cert.cnpj_titular', '00000000000000')
                ->where('cert.alerta', 'ok')
        );

    $cert->forceDelete();
});

it('GET /nfe-brasil/configuracao com cert próximo ao vencimento retorna alerta proximo_vencimento', function () {
    [$business, $user] = nfeBootstrapUser();

    $user->givePermissionTo('nfebrasil.settings.manage');

    $cert = NfeCertificado::create([
        'business_id'        => $business->id,
        'uuid'               => \Illuminate\Support\Str::uuid(),
        'cnpj_titular'       => '00000000000000',
        'valido_ate'         => now()->addDays(15)->toDateString(),
        'encrypted_password' => encrypt('teste'),
        'ativo'              => true,
    ]);

    $this->actingAs($user)
        ->withSession([
            'user.business_id' => $business->id,
            'business.id'      => $business->id,
        ])
        ->get('/nfe-brasil/configuracao')
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('cert.alerta', 'proximo_vencimento')
        );

    $cert->forceDelete();
});

it('POST /nfe-brasil/configuracao sem autenticação retorna 302 (redirect login)', function () {
    $this->post('/nfe-brasil/configuracao')->assertRedirect();
});

it('POST /nfe-brasil/configuracao sem arquivo retorna erro de validação', function () {
    [$business, $user] = nfeBootstrapUser();

    $user->givePermissionTo('nfebrasil.settings.manage');

    $response = $this->actingAs($user)
        ->withSession([
            'user.business_id' => $business->id,
            'business.id'      => $business->id,
        ])
        ->post('/nfe-brasil/configuracao', ['senha' => 'alguma_senha']);

    $response->assertSessionHasErrors(['certificado']);
});

it('POST /nfe-brasil/configuracao com arquivo com extensão inválida retorna erro de validação', function () {
    [$business, $user] = nfeBootstrapUser();

    $user->givePermissionTo('nfebrasil.settings.manage');

    $arquivo = UploadedFile::fake()->create('cert.txt', 10);

    $response = $this->actingAs($user)
        ->withSession([
            'user.business_id' => $business->id,
            'business.id'      => $business->id,
        ])
        ->post('/nfe-brasil/configuracao', [
            'certificado' => $arquivo,
            'senha'       => 'alguma_senha',
        ]);

    $response->assertSessionHasErrors(['certificado']);
});

it('POST /nfe-brasil/configuracao com cert inválido retorna erro via CertificadoService', function () {
    [$business, $user] = nfeBootstrapUser();

    $user->givePermissionTo('nfebrasil.settings.manage');

    // PFX falso — CertificadoService vai lançar InvalidArgumentException (base64 inválido ou openssl falha)
    $arquivo = UploadedFile::fake()->create('cert.pfx', 5, 'application/x-pkcs12');

    $response = $this->actingAs($user)
        ->withSession([
            'user.business_id' => $business->id,
            'business.id'      => $business->id,
        ])
        ->post('/nfe-brasil/configuracao', [
            'certificado' => $arquivo,
            'senha'       => 'senha_errada',
        ]);

    // Back com erro de validação (InvalidArgumentException → withErrors)
    $response->assertSessionHasErrors(['certificado']);
});
