<?php

declare(strict_types=1);
// Cobre UC-PCV-37 (Producao-celular.casos.md · E05, modelo a4_os) e UC-PCV-28 no QR (Producao.casos.md).

use App\Services\AppLojas\EtiquetaProducao;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Milon\Barcode\DNS2D;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * PDF A4 da OS com o QR da etiqueta — PR 07 do playbook propostas-app, modelo `a4_os` da E05
 * (thread 05 do PCP). O QR leva `oimpresso:p:<business_hash>:<os_tipo>:<os_id>`, o formato que a
 * API do app (`GET /api/app/producao/etiqueta/{codigo}`) e a tela do operador leem.
 *
 * Tenant 98 contra o 99. NUNCA biz=4. ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_programacoes') || ! Schema::hasTable('comvis_os')) {
        $this->markTestSkipped('Tabelas pcp_* ou comvis_os ausentes nesta lane.');
    }
    $this->b98 = $this->seededTenant();
    $this->b99 = $this->seededSupportClientTenant();
    $this->g98 = $this->usuarioComPermissoes(['producao.access'], $this->b98);

    // O gerador do QR é trocado por um que grava o texto codificado: é o que prova o conteúdo do QR
    // no PDF (o PDF sai comprimido; ler o QR da imagem exigiria um decodificador).
    $this->qrs = new class extends DNS2D {
        public array $lidos = [];

        public function getBarcodePNG($code, $type, $w = 3, $h = 3, $color = [0, 0, 0], $bgcolor = null)
        {
            $this->lidos[] = [$code, $type];

            return parent::getBarcodePNG($code, $type, $w, $h, $color, $bgcolor);
        }
    };
    app()->instance(DNS2D::class, $this->qrs);
});

function etqOs(int $biz, string $numero): int
{
    return (int) DB::table('comvis_os')->insertGetId(['business_id' => $biz, 'numero' => $numero, 'status_etapa' => 'producao',
        'data_prazo' => '2026-10-20', 'valor_total' => 0, 'created_at' => now(), 'updated_at' => now()]);
}

it('UC-PCV-37 · a OS sai em PDF A4 com o QR do formato único (tipo e id da OS, hash no lugar do id da empresa)', function () {
    $os = etqOs($this->b98->id, 'OS-2099-'.random_int(10000, 99999));

    $r = $this->actingAs($this->g98)->get("/producao/os/{$os}/etiqueta");
    $r->assertOk();
    expect($r->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(substr((string) $r->getContent(), 0, 5))->toBe('%PDF-');

    expect($this->qrs->lidos)->toHaveCount(1);
    [$codigo, $tipo] = $this->qrs->lidos[0];
    expect($tipo)->toBe('QRCODE')
        ->and($codigo)->toBe(EtiquetaProducao::codigo((int) $this->b98->id, $os, 'comvis_os'))
        ->and($codigo)->toEndWith(':comvis_os:'.$os)
        // O QR não leva o business_id cru: o 3º segmento é o hash de 12 hex.
        ->and(explode(':', $codigo)[2])->toMatch('/^[0-9a-f]{12}$/')
        ->and(explode(':', $codigo)[2])->not->toBe((string) $this->b98->id);

    // Discriminante: OS diferente → QR diferente.
    $outra = etqOs($this->b98->id, 'OS-2099-'.random_int(10000, 99999));
    $this->get("/producao/os/{$outra}/etiqueta")->assertOk();
    expect($this->qrs->lidos[1][0])->toEndWith(':comvis_os:'.$outra)->not->toBe($codigo);
});

it('UC-PCV-28 · OS de outra empresa não gera etiqueta (404) e sem producao.access é 403', function () {
    $alheia = etqOs($this->b99->id, 'OS-2099-'.random_int(10000, 99999));
    $this->actingAs($this->g98)->get("/producao/os/{$alheia}/etiqueta")->assertNotFound();
    expect($this->qrs->lidos)->toBe([]);

    // Controle negativo: a mesma rota com OS própria responde 200 (o 404 acima é do escopo, não da rota).
    $propria = etqOs($this->b98->id, 'OS-2099-'.random_int(10000, 99999));
    $this->get("/producao/os/{$propria}/etiqueta")->assertOk();

    $semPermissao = $this->usuarioComPermissoes([], $this->b98);
    $this->actingAs($semPermissao)->get("/producao/os/{$propria}/etiqueta")->assertForbidden();
});

it('UC-PCV-28 · o QR lido por outra empresa não é dela; o formato antigo sem tipo segue lido como comvis_os', function () {
    $b98 = (int) $this->b98->id;
    $b99 = (int) $this->b99->id;
    $codigo = EtiquetaProducao::codigo($b98, 501);

    expect(EtiquetaProducao::ler($codigo, $b98))->toBe(['empresa' => true, 'os_tipo' => 'comvis_os', 'pedido_id' => 501, 'programacao_id' => null])
        ->and(EtiquetaProducao::ler($codigo, $b99)['empresa'])->toBeFalse();

    // Etiqueta impressa antes do tipo no QR (formato do PR 06): segue válida.
    $antigo = EtiquetaProducao::PREFIXO.EtiquetaProducao::hashDaEmpresa($b98).':501';
    expect(EtiquetaProducao::ler($antigo, $b98))->toBe(['empresa' => true, 'os_tipo' => 'comvis_os', 'pedido_id' => 501, 'programacao_id' => null]);

    // Tela do operador (thread 04) lê os dois últimos segmentos como tipo e id.
    expect(array_slice(explode(':', $codigo), -2))->toBe(['comvis_os', '501'])
        ->and(EtiquetaProducao::ler('oimpresso:p:xyz:comvis_os:501', $b98))->toBeNull()
        // Sufixo de etapa (thread 05a) só com id válido: `:e0` e `:ex` não são QR.
        ->and(EtiquetaProducao::ler($codigo.':e0', $b98))->toBeNull()
        ->and(EtiquetaProducao::ler($codigo.':ex', $b98))->toBeNull()
        ->and(fn () => EtiquetaProducao::codigo($b98, 501, 'Comvis OS'))->toThrow(InvalidArgumentException::class);
});
