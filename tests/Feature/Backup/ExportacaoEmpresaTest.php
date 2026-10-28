<?php
// @covers-us US-BKP-001

declare(strict_types=1);

use App\Backup\ExportacaoEmpresa\ExportadorDaEmpresa;
use App\Backup\ExportacaoEmpresa\TabelasDaEmpresa;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\EstoqueFixture;

/**
 * Exportação dos dados de UMA empresa — US-BKP-001, fatia 1 (o pacote). Tier 0 (ADR 0093).
 *
 * Monta, nas empresas 98 e 99, o mesmo conjunto: contato marcado, venda com linha,
 * estoque num local e um usuário. Exporta a 98, abre o zip e confere:
 *  - toda coluna `business_id` de todo CSV vale 98;
 *  - toda tabela filha (sem `business_id`) só aponta para id que está no CSV do pai;
 *  - o contato, a linha de venda e o estoque da 99 não aparecem em lugar nenhum;
 *  - credencial e senha não saem.
 *
 * Tenants: 98 (ADR 0358) × 99. Nunca biz=4. Não roda local (proibicoes.md). Lane: backup-pest.yml.
 */
uses(DatabaseTransactions::class);

/** @return array<string, list<array<string, string>>> tabela => linhas */
function bkxAbrirZip(string $caminho): array
{
    $zip = new ZipArchive();
    expect($zip->open($caminho))->toBeTrue();
    $tabelas = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $nome = $zip->getNameIndex($i);
        if (! str_ends_with($nome, '.csv')) {
            continue;
        }
        $texto = preg_replace('/^\xEF\xBB\xBF/', '', (string) $zip->getFromIndex($i));
        $fh = fopen('php://memory', 'r+');
        fwrite($fh, $texto);
        rewind($fh);
        $cab = fgetcsv($fh, null, ';', '"', '');
        $linhas = [];
        while (($l = fgetcsv($fh, null, ';', '"', '')) !== false) {
            $linhas[] = array_combine($cab, $l);
        }
        fclose($fh);
        $tabelas[substr($nome, 0, -4)] = ['cabecalho' => $cab, 'linhas' => $linhas];
    }
    $tabelas['_MANIFEST'] = json_decode((string) $zip->getFromName('_MANIFEST.json'), true);
    $zip->close();

    return $tabelas;
}

/** @return array{contato: int, linha: int, usuario: int, produto: int} */
function bkxMontar(int $bizId, string $marca): array
{
    $produto = EstoqueFixture::singleProduct($bizId);
    $local = EstoqueFixture::locationId($bizId);
    EstoqueFixture::setStock($produto, 0, $local, 7);
    $venda = EstoqueFixture::saleWithLine($produto, 0, $local, 2, 15.0);
    $contato = (int) DB::table('contacts')->insertGetId([
        'business_id' => $bizId, 'type' => 'customer', 'name' => "BKX {$marca}", 'contact_status' => 'active',
        'created_by' => EstoqueFixture::userId($bizId), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $usuario = (int) DB::table('users')->insertGetId([
        'first_name' => "BKX {$marca}", 'username' => 'bkx_' . uniqid(), 'password' => bcrypt('segredo-bkx'),
        'business_id' => $bizId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['contato' => $contato, 'linha' => $venda['sell_line_id'], 'usuario' => $usuario, 'produto' => $produto->productId];
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS ausente — roda na lane MySQL / CT 100.');
    }
    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id;
    expect($this->outroBizId)->not->toBe($this->bizId);

    $this->meu = bkxMontar($this->bizId, 'MEU');
    $this->alheio = bkxMontar($this->outroBizId, 'ALHEIO');
    $this->zip = tempnam(sys_get_temp_dir(), 'bkx') . '.zip';
    app(ExportadorDaEmpresa::class)->exportar($this->bizId, $this->zip);
    $this->pacote = bkxAbrirZip($this->zip);
});

afterEach(function () {
    if (isset($this->zip)) {
        @unlink($this->zip);
    }
});

it('UC-BKX-01 · toda coluna business_id do pacote vale a empresa exportada', function () {
    $conferidas = 0;
    foreach ($this->pacote as $tabela => $csv) {
        if ($tabela === '_MANIFEST' || ! in_array('business_id', $csv['cabecalho'], true)) {
            continue;
        }
        foreach ($csv['linhas'] as $l) {
            expect((int) $l['business_id'])->toBe($this->bizId, "{$tabela} trouxe linha de outra empresa");
            $conferidas++;
        }
    }
    // Pré-condição anti-vácuo: o contato, a venda e o usuário da 98 estão no pacote.
    expect($conferidas)->toBeGreaterThanOrEqual(3);
    expect($this->pacote['business']['linhas'])->toHaveCount(1);
    expect((int) $this->pacote['business']['linhas'][0]['id'])->toBe($this->bizId);
    expect($this->pacote['_MANIFEST']['business_id'])->toBe($this->bizId);
});

it('UC-BKX-02 · tabela filha sem business_id só aponta para pai que está no pacote', function () {
    foreach (TabelasDaEmpresa::FILHAS as $filha => [$coluna, $pai]) {
        if (! isset($this->pacote[$filha])) {
            continue;
        }
        $idsDoPai = array_map('intval', array_column($this->pacote[$pai]['linhas'], 'id'));
        foreach ($this->pacote[$filha]['linhas'] as $l) {
            expect(in_array((int) $l[$coluna], $idsDoPai, true))->toBeTrue("{$filha}.{$coluna}={$l[$coluna]} fora de {$pai}");
        }
    }
    $linhas = array_map('intval', array_column($this->pacote['transaction_sell_lines']['linhas'], 'id'));
    expect(in_array($this->meu['linha'], $linhas, true))->toBeTrue();
    expect(in_array($this->alheio['linha'], $linhas, true))->toBeFalse();

    $estoque = array_map('intval', array_column($this->pacote['variation_location_details']['linhas'], 'product_id'));
    expect(in_array($this->meu['produto'], $estoque, true))->toBeTrue();
    expect(in_array($this->alheio['produto'], $estoque, true))->toBeFalse();
});

it('UC-BKX-03 · nada da outra empresa aparece no pacote', function () {
    $contatos = array_column($this->pacote['contacts']['linhas'], 'name');
    expect($contatos)->toContain('BKX MEU');
    expect($contatos)->not->toContain('BKX ALHEIO');

    $usuarios = array_map('intval', array_column($this->pacote['users']['linhas'], 'id'));
    expect(in_array($this->meu['usuario'], $usuarios, true))->toBeTrue();
    expect(in_array($this->alheio['usuario'], $usuarios, true))->toBeFalse();
});

it('UC-BKX-04 · credencial e senha não saem', function () {
    foreach (TabelasDaEmpresa::TABELAS_FORA as $t) {
        expect(array_key_exists($t, $this->pacote))->toBeFalse("{$t} entrou no pacote");
    }
    foreach (array_keys($this->pacote) as $t) {
        expect(str_starts_with($t, 'mcp_'))->toBeFalse("{$t} entrou no pacote");
    }
    expect($this->pacote['users']['cabecalho'])->not->toContain('password');
    expect($this->pacote['users']['cabecalho'])->not->toContain('remember_token');
    expect($this->pacote['business']['cabecalho'])->not->toContain('email_settings');
    foreach ($this->pacote as $t => $csv) {
        if ($t === '_MANIFEST') {
            continue;
        }
        foreach ($csv['cabecalho'] as $c) {
            expect(preg_match(TabelasDaEmpresa::PADRAO_COLUNA_FORA, $c))->toBe(0, "{$t}.{$c} tem cara de credencial");
        }
    }
});

it('UC-BKX-05 · o manifesto conta as linhas de cada CSV', function () {
    foreach ($this->pacote['_MANIFEST']['tabelas'] as $tabela => $n) {
        expect(count($this->pacote[$tabela]['linhas']))->toBe($n);
    }
});

it('UC-BKX-06 · sem business_id não exporta', function () {
    app(ExportadorDaEmpresa::class)->exportar(0, $this->zip);
})->throws(InvalidArgumentException::class);
