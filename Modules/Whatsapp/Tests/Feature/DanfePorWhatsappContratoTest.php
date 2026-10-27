<?php
// @covers-us US-WA-321

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\NfeBrasil\Events\NFeAutorizada;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\DanfeService;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Jobs\DespacharMensagemDoSistemaJob;
use Tests\Support\WithSeededTenant;

/**
 * NF-e autorizada → DANFE ao cliente por WhatsApp, por link assinado (US-WA-321).
 *
 * Âncora: SPEC US-WA-321 (critérios 1-4). Caminho real: evento `NFeAutorizada` → listener registrado
 * no provider do Whatsapp → `NotificadorClienteWhatsapp` → `DespacharMensagemDoSistemaJob` (só o job e o
 * gerador de PDF são falsos). Rota pública: middleware `signed` real.
 *
 * Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite'
        || ! Schema::hasTable('nfe_emissoes')
        || ! Schema::hasColumn('contacts', 'whatsapp_consent')
        || ! Schema::hasColumn('channels', 'handles_outbound_default')) {
        $this->markTestSkipped('Requer MySQL com nfe_emissoes, consentimento em contacts e channels.handles_*.');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    $local = DB::table('business_locations')->where('business_id', $this->biz->id)->value('id');
    if ($local === null) {
        $this->markTestSkipped('Tenant 98 sem business_location semeada.');
    }
    Bus::fake([DespacharMensagemDoSistemaJob::class]);
    $criador = \App\User::factory()->create(['business_id' => $this->biz->id]);

    Channel::query()->withoutGlobalScopes()->forceCreate([
        'business_id' => $this->biz->id, 'channel_uuid' => (string) Str::uuid(), 'label' => 'DANFE teste',
        'type' => Channel::TYPE_WHATSAPP_WHATSMEOW, 'status' => 'active', 'channel_health' => 'healthy',
        'handles_outbound_default' => true, 'display_identifier' => '+55 47 3'.random_int(100, 999).'-'.random_int(1000, 9999),
    ]);
    $this->enviadas = fn () => DB::table('messages')->where('business_id', $this->biz->id)
        ->where('direction', 'outbound')->where('sender_kind', 'system')->pluck('body')->all();
    $contato = (int) DB::table('contacts')->insertGetId([
        'business_id' => $this->biz->id, 'type' => 'customer', 'name' => 'Cliente DANFE', 'mobile' => '48988887777',
        'whatsapp_consent' => true, 'contact_status' => 'active', 'created_by' => $criador->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    // Uma venda por emissão: nfe_emissoes é única por (business_id, transaction_id).
    $novaVenda = fn (): int => (int) DB::table('transactions')->insertGetId([
        'business_id' => $this->biz->id, 'location_id' => $local, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'paid', 'contact_id' => $contato, 'transaction_date' => now(),
        'total_before_tax' => 10, 'final_total' => 10, 'created_by' => $criador->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->emissao = function (string $status = 'autorizada') use ($novaVenda): NfeEmissao {
        $id = (int) DB::table('nfe_emissoes')->insertGetId([
            'business_id' => $this->biz->id, 'transaction_id' => $novaVenda(), 'modelo' => '55', 'serie' => '9',
            'numero' => random_int(700000, 799999), 'status' => $status, 'chave_44' => str_pad((string) random_int(1, PHP_INT_MAX), 44, '4', STR_PAD_LEFT),
            'valor_total' => 10.00, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return NfeEmissao::query()->where('business_id', $this->biz->id)->findOrFail($id);
    };
});

it('US-WA-321 · flag desligada: NF-e autorizada não manda nada por WhatsApp', function () {
    config(['whatsapp.aviso_automatico.danfe_enabled' => false]);

    event(new NFeAutorizada(($this->emissao)()));

    expect(($this->enviadas)())->toBe([]);
});

it('US-WA-321 · flag ligada: texto + link assinado da DANFE da própria empresa', function () {
    config(['whatsapp.aviso_automatico.danfe_enabled' => true]);
    $nfe = ($this->emissao)();

    event(new NFeAutorizada($nfe));

    $msgs = ($this->enviadas)();
    expect($msgs)->toHaveCount(1)->and($msgs[0])->toContain("nº {$nfe->numero}")->toContain('DANFE NF-e');
    // O canal só envia texto: a DANFE vai como link assinado no fim da mensagem.
    expect(preg_match('#(https?://\S+/nfe-brasil/danfe/'.$this->biz->id.'/'.$nfe->id.'\S*)#', $msgs[0], $m))->toBe(1)
        ->and(URL::hasValidSignature(\Illuminate\Http\Request::create($m[1])))->toBeTrue();
    Bus::assertDispatchedTimes(DespacharMensagemDoSistemaJob::class, 1);
});

it('US-WA-321 · emissão sem venda ou não autorizada: não avisa', function () {
    config(['whatsapp.aviso_automatico.danfe_enabled' => true]);

    event(new NFeAutorizada(($this->emissao)('rejeitada')));
    $semVenda = ($this->emissao)();
    DB::table('nfe_emissoes')->where('id', $semVenda->id)->update(['transaction_id' => null]);
    event(new NFeAutorizada($semVenda->fresh()));

    expect(($this->enviadas)())->toBe([]);
});

it('US-WA-321 · link público: assinado serve o PDF; sem assinatura, adulterado ou de outra empresa não', function () {
    $danfe = Mockery::mock(DanfeService::class);
    $danfe->shouldReceive('lerOuGerar')->andReturn('%PDF-1.4 teste');
    app()->instance(DanfeService::class, $danfe);
    $nfe = ($this->emissao)();
    $assinar = fn (int $biz, int $id) => URL::temporarySignedRoute('nfe-brasil.danfe-publica', now()->addDay(), ['business' => $biz, 'emissao' => $id]);

    $ok = $this->get($assinar($this->biz->id, $nfe->id));
    $ok->assertOk();
    expect($ok->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($ok->getContent())->toBe('%PDF-1.4 teste');

    $this->get(route('nfe-brasil.danfe-publica', ['business' => $this->biz->id, 'emissao' => $nfe->id]))->assertForbidden();
    $this->get(str_replace("/danfe/{$this->biz->id}/", "/danfe/{$this->outra->id}/", $assinar($this->biz->id, $nfe->id)))->assertForbidden();
    // Assinatura válida, mas a emissão não é desta empresa: 404, nunca o PDF de outra empresa.
    $this->get($assinar($this->outra->id, $nfe->id))->assertNotFound();
    $this->get($assinar($this->biz->id, ($this->emissao)('rejeitada')->id))->assertNotFound();
});
