<?php
// @covers-us US-COMVIS-007

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\ComunicacaoVisual\Services\ComprovanteInstalacao;
use Modules\ComunicacaoVisual\Services\ProtocoloInstalacao;
use Tests\Support\WithSeededTenant;

/**
 * Link do protocolo para o CLIENTE, sem login (US-COMVIS-007 · UC-PCV-25 · 1d) —
 * `GET /instalacao-cv/{biz}/{id}/protocolo` assinado e com validade.
 *
 * Âncora: o UC-PCV-25 do protótipo da Produção ("PDF com fotos antes/depois … vai para o cliente por WhatsApp")
 * e a regra do link público do aceite do orçamento (UC-CVACEP-01: sem indexar, sem Referer). Contrato:
 * resources/js/Pages/ComunicacaoVisual/Index.casos.md UC-CVINST-15..16.
 *
 * Vai pela pilha HTTP de verdade (o middleware `signed` é o que protege), sem usuário logado.
 * Arranjo (tenant 98 contra 99, ADR 0358) igual ao InstalacaoProtocoloContratoTest.
 * ⚠️ SKIP em SQLite ou sem as tabelas: leia assertions, não "0 failed" (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

const CVPUB_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer MySQL.');
    }
    foreach (['cv_instalacao_comprovantes', 'arquivos', 'comvis_os', 'comvis_orcamentos'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    Storage::fake(config('arquivos.disk_default', 'local'));
    Storage::fake('arquivos');
    Carbon::setTestNow('2026-11-19 11:10:00');

    $this->instalador = \App\User::factory()->create(['business_id' => $this->biz->id, 'first_name' => 'Instalador', 'last_name' => 'Gama']);
    $contato = DB::table('contacts')->insertGetId([
        'business_id' => $this->biz->id, 'type' => 'customer', 'name' => 'Padaria Exemplo Teste', 'mobile' => '',
        'address_line_1' => 'Rua das Placas, 100', 'city' => 'Cidade Teste', 'state' => 'SC',
        'contact_status' => 'active', 'created_by' => $this->instalador->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $instalacao = function (int $biz, int $quem, ?int $contato) {
        $orc = DB::table('comvis_orcamentos')->insertGetId([
            'business_id' => $biz, 'numero' => 'ORC-'.substr(uniqid(), -10), 'contato_id' => $contato,
            'data_emissao' => '2026-11-10', 'status' => 'aprovado', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $os = DB::table('comvis_os')->insertGetId([
            'business_id' => $biz, 'orcamento_id' => $orc, 'numero' => 'PU-'.substr(uniqid(), -10),
            'status_etapa' => 'instalacao', 'valor_total' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('cv_instalacoes')->insertGetId([
            'business_id' => $biz, 'os_id' => $os, 'status' => 'agendada', 'veiculo' => 'Fiorino ABC1D23',
            'equipe_user_ids_json' => json_encode([$quem]), 'data_agendada' => '2026-11-19 09:00:00',
            'data_fim' => '2026-11-19 11:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
    };
    $this->inst = $instalacao($this->biz->id, $this->instalador->id, $contato);
    $alheio = \App\User::factory()->create(['business_id' => $this->outra->id]);
    $this->instOutra = $instalacao($this->outra->id, $alheio->id, null);
});

afterEach(fn () => Carbon::setTestNow());

/** Grava foto antes, foto depois e assinatura; com $concluir, conclui. Como o instalador faria pela API. */
function cvPubComprovante($t, bool $concluir = true): void
{
    $c = app(ComprovanteInstalacao::class);
    foreach (['foto_antes', 'foto_depois'] as $tipo) {
        $caminho = tempnam(sys_get_temp_dir(), 'cvu');
        file_put_contents($caminho, base64_decode(CVPUB_PNG));
        $c->foto($t->biz->id, $t->inst, $tipo, new UploadedFile($caminho, $tipo.'.png', 'image/png', null, true), $t->instalador->id);
    }
    $c->assinatura($t->biz->id, $t->inst, 'data:image/png;base64,'.CVPUB_PNG, 'Marta Ribeiro', true, $t->instalador->id);
    if ($concluir) {
        $c->concluir($t->biz->id, $t->inst);
    }
}


it('UC-CVINST-15 · o link assinado abre o PDF sem login, sem indexar, e expira no prazo', function () {
    cvPubComprovante($this);
    $link = app(ProtocoloInstalacao::class)->linkPublico($this->biz->id, $this->inst);
    expect($link)->toContain("/instalacao-cv/{$this->biz->id}/{$this->inst}/protocolo?expires=")->toContain('signature=');

    $r = $this->get($link);
    $r->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Referrer-Policy', 'no-referrer');
    expect((string) $r->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(substr((string) $r->getContent(), 0, 5))->toBe('%PDF-');

    // 7 dias por padrão: no 6º ainda abre, no 8º expirou.
    Carbon::setTestNow(now()->addDays(6));
    $this->get($link)->assertOk();
    Carbon::setTestNow(now()->addDays(2));
    $this->get($link)->assertForbidden();
});

it('UC-CVINST-16 · link adulterado 403; instalação aberta ou de outra empresa 404', function () {
    cvPubComprovante($this);
    $link = app(ProtocoloInstalacao::class)->linkPublico($this->biz->id, $this->inst);

    // Trocar a instalação ou a empresa na URL invalida a assinatura.
    $this->get(str_replace("/{$this->inst}/protocolo", '/'.($this->inst + 1).'/protocolo', $link))->assertForbidden();
    $this->get(str_replace("/instalacao-cv/{$this->biz->id}/", "/instalacao-cv/{$this->outra->id}/", $link))->assertForbidden();

    // Assinado mas a instalação não é dessa empresa (a da 99 com a empresa 98): 404, sem dizer por quê.
    $this->get(app(ProtocoloInstalacao::class)->linkPublico($this->biz->id, $this->instOutra))->assertNotFound();

    // Assinado mas ainda não concluída: 404.
    DB::table('cv_instalacoes')->where('id', $this->inst)->update(['status' => 'agendada']);
    $this->get($link)->assertNotFound();
});
