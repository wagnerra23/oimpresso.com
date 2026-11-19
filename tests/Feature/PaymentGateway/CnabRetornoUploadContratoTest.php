<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\PaymentGateway\Jobs\CnabRetornoProcessor;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

/**
 * Contrato do envio de arquivo na tela `/settings/payment-gateways/{id}/cnab-retorno`.
 *
 * UC-PGCNAB-10  extensão fora da lista é recusada sem gravar upload nem enfileirar o Job
 *               (controle: `.ret` é aceito, grava o upload e enfileira o Job).
 *
 * Fonte: charter `CnabRetorno.charter.md` G2 (validação de formato/limite espelhando o
 * `validate()` do Controller) + ADR 0170 (bancos nativos) Onda 4f.0 (upload manual → Job).
 *
 * POR QUE AQUI E NÃO NO E2E: o servidor HTTP do Pest Browser não repassa arquivos ao Laravel
 * (`vendor/pestphp/pest-plugin-browser/src/Drivers/LaravelHttpServer.php`, `Request::create`
 * com `[], // @TODO files...`). Medido no run 44542116020: o formulário mostrou o arquivo
 * anexado e o servidor respondeu "O campo arquivo é obrigatório". Upload pela tela só se prova
 * por requisição HTTP com `UploadedFile`.
 *
 * Tenant fictício 98 (ADR 0358). Nada vai ao disco real (`Storage::fake`) nem à fila
 * (`Queue::fake`). Nenhum arquivo CNAB real, nenhum valor.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->tenant = \App\Business::find(98);
    if (! $this->tenant) {
        $this->markTestSkipped('Lane sem tenant 98 semeado (ADR 0358) — contrato não exercitável.');
    }

    // `business_settings.access`: exigida no grupo de rotas desde a auditoria L0 (#11200).
    $this->user = $this->usuarioComPermissoes(['business_settings.access'], $this->tenant);
    $this->sessao = ['user.business_id' => 98, 'business.id' => 98];

    $par = null;
    foreach (['inter', 'c6', 'asaas', 'bcb_pix', 'pesapal'] as $key) {
        foreach (['sandbox', 'production'] as $amb) {
            if ($par === null && ! DB::table('payment_gateway_credentials')
                ->where('business_id', 98)->where('gateway_key', $key)->where('ambiente', $amb)->exists()) {
                $par = [$key, $amb];
            }
        }
    }
    if ($par === null) {
        throw new RuntimeException('Sem par (gateway_key, ambiente) livre no business 98.');
    }

    // Sem auth ainda: o business vai explícito.
    $this->cred = PaymentGatewayCredential::withoutGlobalScopes()->create([
        'business_id' => 98,
        'gateway_key' => $par[0],
        'ambiente' => $par[1],
        'ativo' => true,
        'nome_display' => 'CT PGCNAB ' . uniqid(),
        'config_json' => [],
        'health_status' => 'ok',
    ]);

    Storage::fake('local');
    Queue::fake();

    $this->enviar = fn (string $nome) => $this->actingAs($this->user)
        ->withSession($this->sessao)
        ->from("/settings/payment-gateways/{$this->cred->id}/cnab-retorno")
        ->post("/settings/payment-gateways/{$this->cred->id}/cnab-retorno", [
            'arquivo' => UploadedFile::fake()->createWithContent($nome, "conteudo inerte de teste\n"),
        ]);

    $this->uploads = fn () => DB::table('cnab_retorno_uploads')
        ->where('payment_gateway_credential_id', $this->cred->id)->count();
});

it('UC-PGCNAB-10 arquivo com extensao fora da lista e recusado sem gravar upload nem enfileirar o Job; .ret e aceito', function () {
    // Controle positivo: a mesma rota, com extensão da lista, grava e enfileira.
    ($this->enviar)('retorno-controle.ret')->assertRedirect()->assertSessionHasNoErrors();
    expect(($this->uploads)())->toBe(1);
    Queue::assertPushed(CnabRetornoProcessor::class, 1);

    // O caso: .pdf.
    ($this->enviar)('retorno.pdf')->assertRedirect()->assertSessionHasErrors('arquivo');
    $erro = (string) (session('errors')?->first('arquivo') ?? '');
    $this->assertStringContainsString('Extensão não aceita', $erro, 'A recusa nao veio pela regra de extensao.');
    expect(($this->uploads)())->toBe(1);
    Queue::assertPushed(CnabRetornoProcessor::class, 1);
});
