<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * UC-PGSET-08 `[T0]` — configurar gateway exige `business_settings.access`.
 *
 * Contrato: auditoria PaymentGateway L0 (2026-11-19). As rotas do grupo `settings.` só
 * tinham `auth`: qualquer usuário logado da empresa trocava `api_key`/`webhook_secret`,
 * apagava credencial e subia retorno CNAB que marca cobrança paga. Agora o grupo tem
 * `can:business_settings.access` (Routes/web.php).
 *
 * Cada verbo mutador tem o PAR, com o MESMO payload e a MESMA sessão:
 *   - sem a permissão → 403 e o banco não muda;
 *   - com a permissão → o controller faz a mudança (controle positivo).
 * O positivo é o que torna o 403 discriminante: com o mesmo payload o controller
 * aceitaria, então o 403 só pode vir da trava da rota. E o corpo do 403 não tem a chave
 * `success`, que é o que o controller devolve nas recusas dele.
 *
 * O usuário sem a permissão tem `financeiro.dashboard.view`: é quem vê o link "Gateway"
 * no menu do Financeiro e era o caso real de acesso indevido.
 *
 * TENANT: 98 (ADR 0358), via seededTenant(). NUNCA 4, NUNCA 1.
 * DatabaseTransactions: nada fica gravado. NÃO RODADO LOCAL (Pest só CI/CT 100).
 */
const PGPERM_PERMISSAO = 'business_settings.access';
const PGPERM_CHAVES = ['inter', 'c6', 'asaas', 'bcb_pix', 'pagarme', 'sicoob_api'];

if (! function_exists('pgpermComboLivre')) {
    /**
     * Par (gateway_key, ambiente) livre no business — respeita a UNIQUE mesmo numa base
     * que persiste entre runs (CT 100).
     *
     * @param  array<int, array{0:string,1:string}>  $jaUsados
     * @return array{0:string,1:string}
     */
    function pgpermComboLivre(int $businessId, array $jaUsados = []): array
    {
        foreach (PGPERM_CHAVES as $key) {
            foreach (['sandbox', 'production'] as $amb) {
                if (in_array([$key, $amb], $jaUsados, true)) {
                    continue;
                }
                $existe = DB::table('payment_gateway_credentials')
                    ->where('business_id', $businessId)
                    ->where('gateway_key', $key)
                    ->where('ambiente', $amb)
                    ->exists();
                if (! $existe) {
                    return [$key, $amb];
                }
            }
        }

        throw new RuntimeException("Sem par (gateway_key, ambiente) livre para o business {$businessId}.");
    }
}

if (! function_exists('pgpermCriar')) {
    function pgpermCriar(int $businessId): PaymentGatewayCredential
    {
        [$key, $amb] = pgpermComboLivre($businessId);

        // SUPERADMIN: fixture criada antes de haver sessão; business_id explícito.
        return PaymentGatewayCredential::withoutGlobalScopes()->create([
            'business_id' => $businessId,
            'gateway_key' => $key,
            'ambiente' => $amb,
            'ativo' => true,
            'nome_display' => 'CT PGPERM ' . uniqid(),
            'config_json' => ['marcador' => 'ficticio'],
            'health_status' => 'ok',
        ]);
    }
}

if (! function_exists('pgpermLer')) {
    function pgpermLer(int $id): ?PaymentGatewayCredential
    {
        // SUPERADMIN: leitura de verificação do teste, por id, sem depender da sessão.
        return PaymentGatewayCredential::withoutGlobalScopes()->find($id);
    }
}

beforeEach(function () {
    $this->tenant = $this->seededTenant();
    $this->sessao = ['user.business_id' => $this->tenant->id, 'business.id' => $this->tenant->id];
    $this->semPermissao = $this->usuarioComPermissoes(['financeiro.dashboard.view'], $this->tenant);
    $this->comPermissao = $this->usuarioComPermissoes([PGPERM_PERMISSAO], $this->tenant);
});

it('UC-PGSET-08 [T0] sem business_settings.access a tela de gateways devolve 403; com ela abre', function () {
    $this->actingAs($this->semPermissao)->withSession($this->sessao)
        ->get('/settings/payment-gateways')
        ->assertForbidden();

    $this->actingAs($this->comPermissao)->withSession($this->sessao)
        ->get('/settings/payment-gateways')
        ->assertOk();
});

it('UC-PGSET-08 [T0] store sem a permissão devolve 403 e não cria credencial; com ela cria', function () {
    [$key, $amb] = pgpermComboLivre($this->tenant->id);
    $nome = 'CT PGPERM store ' . uniqid();
    $payload = [
        'gateway_key' => $key,
        'ambiente' => $amb,
        'nome_display' => $nome,
        'config_json' => ['marcador' => 'ficticio'],
        'ativo' => true,
    ];
    $contar = fn () => DB::table('payment_gateway_credentials')->where('nome_display', $nome)->count();

    $this->actingAs($this->semPermissao)->withSession($this->sessao)
        ->postJson('/settings/payment-gateways', $payload)
        ->assertForbidden()
        ->assertJsonMissingPath('success');
    expect($contar())->toBe(0);

    $this->actingAs($this->comPermissao)->withSession($this->sessao)
        ->postJson('/settings/payment-gateways', $payload)
        ->assertCreated();
    expect($contar())->toBe(1);
});

it('UC-PGSET-08 [T0] update sem a permissão devolve 403 e não troca config_json; com ela troca', function () {
    $cred = pgpermCriar($this->tenant->id);
    $payload = ['nome_display' => 'CT PGPERM trocado', 'config_json' => ['api_key' => 'valor-ficticio']];

    $this->actingAs($this->semPermissao)->withSession($this->sessao)
        ->putJson("/settings/payment-gateways/{$cred->id}", $payload)
        ->assertForbidden()
        ->assertJsonMissingPath('success');
    $depois = pgpermLer($cred->id);
    expect($depois->nome_display)->toBe($cred->nome_display);
    expect($depois->config_json)->not->toHaveKey('api_key');

    $this->actingAs($this->comPermissao)->withSession($this->sessao)
        ->putJson("/settings/payment-gateways/{$cred->id}", $payload)
        ->assertOk();
    $depois = pgpermLer($cred->id);
    expect($depois->nome_display)->toBe('CT PGPERM trocado');
    expect($depois->config_json['api_key'] ?? null)->toBe('valor-ficticio');
});

it('UC-PGSET-08 [T0] destroy sem a permissão devolve 403 e a credencial fica; com ela é apagada', function () {
    Storage::fake('local');
    $cred = pgpermCriar($this->tenant->id);

    $this->actingAs($this->semPermissao)->withSession($this->sessao)
        ->deleteJson("/settings/payment-gateways/{$cred->id}")
        ->assertForbidden()
        ->assertJsonMissingPath('success');
    expect(pgpermLer($cred->id))->not->toBeNull();

    $this->actingAs($this->comPermissao)->withSession($this->sessao)
        ->deleteJson("/settings/payment-gateways/{$cred->id}")
        ->assertOk();
    expect(pgpermLer($cred->id))->toBeNull();
});

it('UC-PGSET-08 [T0] toggle sem a permissão devolve 403 e não muda ativo; com ela inverte', function () {
    $cred = pgpermCriar($this->tenant->id);

    $this->actingAs($this->semPermissao)->withSession($this->sessao)
        ->postJson("/settings/payment-gateways/{$cred->id}/toggle")
        ->assertForbidden();
    expect((bool) pgpermLer($cred->id)->ativo)->toBeTrue();

    $this->actingAs($this->comPermissao)->withSession($this->sessao)
        ->postJson("/settings/payment-gateways/{$cred->id}/toggle")
        ->assertOk();
    expect((bool) pgpermLer($cred->id)->ativo)->toBeFalse();
});

it('UC-PGSET-08 [T0] retorno CNAB sem a permissão devolve 403, não grava upload nem enfileira; com ela grava', function () {
    Storage::fake('local');
    Queue::fake();
    $cred = pgpermCriar($this->tenant->id);
    $contar = fn () => DB::table('cnab_retorno_uploads')->where('payment_gateway_credential_id', $cred->id)->count();
    $arquivo = fn () => UploadedFile::fake()->createWithContent('retorno-ficticio.ret', "linha ficticia\n");

    $this->actingAs($this->semPermissao)->withSession($this->sessao)
        ->post("/settings/payment-gateways/{$cred->id}/cnab-retorno", ['arquivo' => $arquivo()])
        ->assertForbidden();
    expect($contar())->toBe(0);
    Queue::assertNothingPushed();

    $this->actingAs($this->comPermissao)->withSession($this->sessao)
        ->post("/settings/payment-gateways/{$cred->id}/cnab-retorno", ['arquivo' => $arquivo()])
        ->assertRedirect();
    expect($contar())->toBe(1);
});
