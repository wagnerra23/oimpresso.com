<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Ponto\Entities\Importacao;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Entities\Rep;
use Modules\Ponto\Services\AfdParserService;

/**
 * Contrato do importador AFD nos DOIS leiautes que chegam ao Ponto.
 *
 * Fonte do leiaute 671 (âncora, não o código): "Leiaute do Arquivo Fonte de Dados - AFD",
 * gov.br/trabalho-e-emprego (Portaria MTP 671/2021, versão de leiaute "004"):
 *   tipo 1 = 302 posições (INPI/fabricação 190-206, DH geração 227-250, versão 251-253)
 *   tipo 3 = NSR 001-009 · tipo 010 · DH 011-034 · CPF 035-046 · CRC-16 047-050  (50)
 *   tipo 7 = NSR · tipo · DH marcação · CPF · DH gravação · coletor · on/off · SHA-256 (137)
 *   tipo 9 = "999999999" · 6 contadores (tipos 2..7) · "9" na posição 064        (64)
 * O leiaute 1510/2009 (legado) continua importado — ADR 0413 W7.
 *
 * Tenant fictício 98 (ADR 0358). Tudo dentro de uma transação revertida no afterEach:
 * `ponto_marcacoes` é append-only por lei e nada aqui pode sobrar no banco persistente
 * do CT 100 (§5 2026-09-18, mutação que escreve).
 *
 * @covers-us US-PONTO-002
 */
uses(Tests\TestCase::class);

const AFD671_BIZ = 98;
const AFD671_PIS = '012345678919'; // 12 posições, como o 1510 grava — o parser compara literal

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Triggers append-only de ponto_marcacoes exigem MySQL.');
    }
    foreach (['ponto_marcacoes', 'ponto_colaborador_config', 'ponto_importacoes', 'ponto_reps'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente.");
        }
    }
    if (! DB::table('business')->where('id', AFD671_BIZ)->exists()) {
        $this->markTestSkipped('Tenant fictício 98 não semeado.');
    }

    DB::beginTransaction();

    // CPF gerado a cada caso (dígito verificador válido) — nunca literal no fonte (PII scan).
    $this->cpf = afd671Cpf(afd671GerarCpf());

    $this->userId = DB::table('users')->insertGetId([
        'first_name' => 'AFD671 Teste', 'username' => 'afd671_' . uniqid(),
        'password'   => 'x', 'business_id' => AFD671_BIZ,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    // CPF gravado FORMATADO de propósito: o AFD traz 12 dígitos, o cadastro pode ter máscara.
    $this->colabId = DB::table('ponto_colaborador_config')->insertGetId([
        'business_id' => AFD671_BIZ, 'user_id' => $this->userId,
        'cpf' => vsprintf('%s.%s.%s-%s', str_split($this->cpf, 3)), 'pis' => AFD671_PIS,
        'controla_ponto' => 1, 'usa_banco_horas' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->arquivos = [];
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    foreach ($this->arquivos ?? [] as $p) {
        Storage::delete($p);
    }
});

/** CPF do caso corrente (o beforeEach grava; as fábricas de linha leem). */
function afd671Cpf(?string $novo = null): string
{
    static $atual = '';
    if ($novo !== null) {
        $atual = $novo;
    }

    return $atual;
}

/** 11 dígitos com dígitos verificadores válidos, base aleatória. */
function afd671GerarCpf(): string
{
    $d = [];
    for ($i = 0; $i < 9; $i++) {
        $d[] = random_int(0, 9);
    }
    foreach ([10, 11] as $peso) {
        $soma = 0;
        foreach ($d as $i => $v) {
            $soma += $v * ($peso - $i);
        }
        $d[] = ($soma * 10) % 11 % 10;
    }

    return implode('', $d);
}

function afd671Linha(array $campos, int $tamanho): string
{
    $linha = implode('', $campos);
    expect(strlen($linha))->toBe($tamanho);

    return $linha;
}

function afd671Tipo3(string $nsr, string $dh): string
{
    return afd671Linha([$nsr, '3', $dh, '0' . afd671Cpf(), 'ABCD'], 50);
}

function afd671Tipo7(string $nsr, string $dh): string
{
    return afd671Linha([$nsr, '7', $dh, '0' . afd671Cpf(), $dh, '01', '0', str_repeat('a', 64)], 137);
}

/**
 * CRC-16/KERMIT, como manda o leiaute 671 para REP-A/REP-P (item 8). Implementação própria
 * do teste — não chama o serviço —, ancorada no valor de verificação da norma (AFD-INT-01).
 */
function afd671Crc(string $dados): string
{
    $crc = 0;
    foreach (str_split($dados) as $c) {
        $crc ^= ord($c);
        for ($b = 0; $b < 8; $b++) {
            $crc = ($crc & 1) ? (($crc >> 1) ^ 0x8408) : ($crc >> 1);
        }
    }

    return sprintf('%04X', $crc);
}

function afd671Tipo3ComCrc(string $nsr, string $dh): string
{
    $corpo = $nsr . '3' . $dh . '0' . afd671Cpf();

    return afd671Linha([$corpo, afd671Crc($corpo)], 50);
}

/**
 * Tipo 7 com hash encadeado. ⚠️ Premissa declarada: a norma lista os campos 1-7 + o hash
 * anterior, sem dizer o separador; aqui é a concatenação crua (posições 001-073 + hash).
 */
function afd671Tipo7Encadeado(string $nsr, string $dh, string $anterior): string
{
    $corpo = $nsr . '7' . $dh . '0' . afd671Cpf() . $dh . '01' . '0';

    return afd671Linha([$corpo, hash('sha256', $corpo . $anterior)], 137);
}

function afd671Importar($test, array $linhas): Importacao
{
    $path = 'ponto/afd-contrato-671/' . uniqid() . '.txt';
    Storage::put($path, implode("\r\n", $linhas) . "\r\n");
    $test->arquivos[] = $path;

    $imp = new Importacao();
    $imp->forceFill([
        'business_id' => AFD671_BIZ, 'usuario_id' => $test->userId, 'tipo' => 'AFD',
        'nome_arquivo' => basename($path), 'arquivo_path' => $path,
        'hash_arquivo' => hash('sha256', uniqid('', true)), 'tamanho_bytes' => 1,
        'estado' => Importacao::ESTADO_PENDENTE,
        'linhas_total' => 0, 'linhas_processadas' => 0, 'linhas_sucesso' => 0, 'linhas_erro' => 0,
    ])->save();

    app(AfdParserService::class)->processar($imp);

    return $imp->fresh();
}

function afd671Marcacoes($test): array
{
    return Marcacao::withoutGlobalScopes() // SUPERADMIN: teste lê o próprio tenant fictício explicitamente
        ->where('business_id', AFD671_BIZ)
        ->where('colaborador_config_id', $test->colabId)
        ->orderBy('momento')
        ->pluck('momento')
        ->map(fn ($m) => (string) $m)
        ->all();
}

it('AFD-671-01 · marcação tipo 3 (REP-C/REP-A) no leiaute 671 é importada pelo CPF', function () {
    $imp = afd671Importar($this, [afd671Tipo3('000000001', '2026-09-24T08:00:00-0300')]);

    expect($imp->linhas_erro)->toBe(0)
        ->and($imp->linhas_sucesso)->toBe(1)
        ->and(afd671Marcacoes($this))->toBe(['2026-09-24 08:00:00']);
});

it('AFD-671-02 · marcação tipo 7 (REP-P) no leiaute 671 é importada pelo CPF', function () {
    $imp = afd671Importar($this, [afd671Tipo7('000000001', '2026-09-24T12:30:00-0300')]);

    expect($imp->linhas_erro)->toBe(0)
        ->and(afd671Marcacoes($this))->toBe(['2026-09-24 12:30:00']);
});

it('AFD-671-03 · arquivo 671 completo (cabeçalho, marcações, trailer, assinatura) conclui sem erro e o REP vem do INPI', function () {
    $inpi = 'BR512021000123456';
    $header = afd671Linha([
        '000000000', '1', '1', '11222333000181', str_repeat(' ', 14),
        str_pad('EMPRESA FICTICIA AFD671', 150), $inpi,
        '2026-09-24', '2026-09-24', '2026-09-25T09:00:00-0300', '004',
        '1', '44555666000199', str_pad('', 30), 'ABCD',
    ], 302);
    $trailer = afd671Linha(['999999999', str_repeat('0', 9), '000000001', str_repeat('0', 27), '000000001', '9'], 64);
    $assinatura = str_pad('ASSINATURA_DIGITAL_EM_ARQUIVO_P7S', 100);

    $imp = afd671Importar($this, [
        $header,
        afd671Tipo3('000000001', '2026-09-24T08:00:00-0300'),
        afd671Tipo7('000000002', '2026-09-24T17:00:00-0300'),
        $trailer,
        $assinatura,
    ]);

    expect($imp->linhas_erro)->toBe(0)
        ->and($imp->estado)->toBe(Importacao::ESTADO_CONCLUIDA)
        ->and(afd671Marcacoes($this))->toBe(['2026-09-24 08:00:00', '2026-09-24 17:00:00'])
        ->and(Rep::withoutGlobalScopes()->where('business_id', AFD671_BIZ)->where('identificador', $inpi)->exists())->toBeTrue();
});

it('AFD-1510-01 · legado 1510 (DDMMAAAA HHMM + PIS) segue importado pelo PIS — ADR 0413 W7', function () {
    $linha = afd671Linha(['000000001', '3', '24092026', '0800', AFD671_PIS], 34);

    $imp = afd671Importar($this, [$linha]);

    expect($imp->linhas_erro)->toBe(0)
        ->and(afd671Marcacoes($this))->toBe(['2026-09-24 08:00:00']);
});

/**
 * Cabeçalho 1510 pelo Anexo I da Portaria 1510/2009 (com as alterações da 2233/2009):
 * tipo ident 011 · CNPJ 012-025 · CEI 026-037 · razão 038-187 · nº fabricação 188-204 ·
 * data inicial 205-212 · data final 213-220 · data geração 221-228 · hora geração 229-232.
 */
function afd1510Cabecalho(string $fabricacao, string $geracao): string
{
    return afd671Linha([
        '000000000', '1', '1', '11222333000181', str_repeat('0', 12),
        str_pad('EMPRESA FICTICIA AFD1510', 150), $fabricacao,
        '24092026', '24092026', $geracao, '0900',
    ], 232);
}

/** Trailer 1510: "999999999" + contadores dos tipos 2..5 + tipo "9" na posição 46. */
function afd1510Trailer(): string
{
    return afd671Linha(['999999999', str_repeat('0', 9), '000000001', str_repeat('0', 18), '9'], 46);
}

function afd1510Marcacao(string $nsr): string
{
    return afd671Linha([$nsr, '3', '24092026', '0800', AFD671_PIS], 34);
}

it('AFD-1510-02 · cabeçalho 1510 identifica o REP pelo nº de fabricação (188-204) e o CNPJ (012-025)', function () {
    $fabricacao = '00004000010000123';

    afd671Importar($this, [afd1510Cabecalho($fabricacao, '25092026'), afd1510Marcacao('000000001')]);

    $rep = Rep::withoutGlobalScopes()->where('business_id', AFD671_BIZ)->where('identificador', $fabricacao)->first();
    expect($rep)->not->toBeNull()
        ->and($rep->cnpj)->toBe('11222333000181');
});

it('AFD-1510-03 · arquivo 1510 completo (cabeçalho, marcação, trailer) conclui sem erro', function () {
    $imp = afd671Importar($this, [
        afd1510Cabecalho('00004000010000123', '25092026'),
        afd1510Marcacao('000000001'),
        afd1510Trailer(),
    ]);

    expect($imp->linhas_erro)->toBe(0)
        ->and($imp->estado)->toBe(Importacao::ESTADO_CONCLUIDA);
});

it('AFD-1510-04 · o mesmo relógio exportado duas vezes não duplica a marcação (idempotência de reimport)', function () {
    $fabricacao = '00004000010000123';

    afd671Importar($this, [afd1510Cabecalho($fabricacao, '25092026'), afd1510Marcacao('000000001')]);
    afd671Importar($this, [afd1510Cabecalho($fabricacao, '26092026'), afd1510Marcacao('000000001')]);

    expect(afd671Marcacoes($this))->toBe(['2026-09-24 08:00:00']);
});

// ── Integridade (decisão [W] 2026-09-28): CRC-16 e hash divergentes REGISTRAM AVISO,
//    nunca rejeitam o arquivo nem a marcação, e não mudam o estado da importação.

it('AFD-INT-01 · o CRC-16 do teste bate com o valor de verificação da norma ("123456789" = 2189)', function () {
    expect(afd671Crc('123456789'))->toBe('2189');
});

it('AFD-INT-02 · tipo 3 com CRC-16 correto importa sem aviso', function () {
    $imp = afd671Importar($this, [afd671Tipo3ComCrc('000000001', '2026-09-24T08:00:00-0300')]);

    expect($imp->linhas_erro)->toBe(0)
        ->and($imp->estado)->toBe(Importacao::ESTADO_CONCLUIDA)
        ->and((string) $imp->log)->not->toContain('integridade');
});

it('AFD-INT-03 · tipo 3 com CRC-16 divergente é importado assim mesmo, e o aviso fica no log', function () {
    $linha = afd671Tipo3ComCrc('000000007', '2026-09-24T08:00:00-0300');
    $linha = substr($linha, 0, 46) . (substr($linha, 46) === '0000' ? 'FFFF' : '0000');

    $imp = afd671Importar($this, [$linha]);

    expect($imp->linhas_erro)->toBe(0)
        ->and($imp->estado)->toBe(Importacao::ESTADO_CONCLUIDA)
        ->and(afd671Marcacoes($this))->toBe(['2026-09-24 08:00:00'])
        ->and((string) $imp->log)->toContain('integridade')
        ->and((string) $imp->log)->toContain('NSR 7')
        ->and((string) $imp->log)->toContain('CRC-16');
});

it('AFD-INT-04 · hash SHA-256 que não encadeia gera aviso só no registro quebrado; as 3 marcações entram', function () {
    $r1 = afd671Tipo7Encadeado('000000001', '2026-09-24T08:00:00-0300', '');
    $r2 = afd671Tipo7Encadeado('000000002', '2026-09-24T12:00:00-0300', substr($r1, 73));
    $r3 = afd671Tipo7Encadeado('000000003', '2026-09-24T13:00:00-0300', str_repeat('0', 64));

    $imp = afd671Importar($this, [$r1, $r2, $r3]);

    expect($imp->linhas_erro)->toBe(0)
        ->and($imp->estado)->toBe(Importacao::ESTADO_CONCLUIDA)
        ->and(afd671Marcacoes($this))->toHaveCount(3)
        ->and((string) $imp->log)->toContain('NSR 3')
        ->and((string) $imp->log)->not->toContain('NSR 2')
        ->and((string) $imp->log)->toContain('SHA-256');
});

it('AFD-INT-05 · arquivo 1510 não tem CRC nem hash, logo não gera aviso', function () {
    $imp = afd671Importar($this, [
        afd1510Cabecalho('00004000010000123', '25092026'),
        afd1510Marcacao('000000001'),
        afd1510Trailer(),
    ]);

    expect((string) $imp->log)->not->toContain('integridade');
});
