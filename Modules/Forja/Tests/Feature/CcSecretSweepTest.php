<?php

declare(strict_types=1);

use App\Support\Privacy\CredentialShapes;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Bite-test do `cc:secret-sweep` e do vocabulário `CredentialShapes`.
 *
 * MORDE + CONTROLE NEGATIVO nos MESMOS vetores que o lado cliente roda
 * (`scripts/cc-watcher/redact.test.mjs`) — `tests/fixtures/credential-shapes-vectors.json`.
 * Sem essa paridade, as duas implementações drifam em silêncio e só um dos dois
 * lados protege.
 *
 * Origem: a varredura de 2026-09-16 achou 24 linhas com credencial em
 * `mcp_cc_messages`/`mcp_cc_blobs`, de julho/agosto, incluindo senha de PayPal
 * de produção, numa tabela que o time lê pelo `cc-search`. A limpeza saiu por
 * script solto via SSH; este comando é a dívida "mexeu, registra" paga.
 *
 * Tabelas montadas aqui (sem FK), no idioma do vizinho `CcIngestPersistsFieldsTest`
 * — o `user_id` real tem FK pra `users` e não interessa ao que este teste prova.
 */
function ensureCcSweepTables(): void
{
    foreach (['mcp_cc_messages', 'mcp_cc_blobs'] as $tbl) {
        if (Schema::hasTable($tbl)) {
            Schema::drop($tbl);
        }
    }

    Schema::create('mcp_cc_messages', function ($t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('session_id');
        $t->string('msg_uuid', 36)->unique();
        $t->unsignedInteger('user_id')->nullable();
        $t->unsignedInteger('business_id')->nullable();
        $t->string('msg_type', 20);
        $t->mediumText('content_text')->nullable();
        $t->json('content_json')->nullable();
        $t->timestamp('ts')->nullable();
        $t->timestamps();
    });

    Schema::create('mcp_cc_blobs', function ($t) {
        $t->bigIncrements('id');
        $t->string('hash_sha256', 64)->index();
        $t->string('blob_type', 30)->nullable();
        $t->string('mime_type', 100)->nullable();
        $t->unsignedBigInteger('size_original_bytes')->default(0);
        $t->unsignedBigInteger('size_compressed_bytes')->default(0);
        $t->binary('compressed_data')->nullable();
        $t->unsignedInteger('refs_count')->default(1);
        $t->timestamps();
    });
}

function inserirMensagem(string $texto, int $n = 1): int
{
    return (int) DB::table('mcp_cc_messages')->insertGetId([
        'session_id' => 1,
        'msg_uuid' => 'sweep-uuid-'.$n.'-'.bin2hex(random_bytes(4)),
        // ADR 0358: tenant fictício 98 — nunca biz=4 (cliente) em teste.
        'business_id' => 98,
        'msg_type' => 'tool_result',
        'content_text' => $texto,
        'ts' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function () {
    ensureCcSweepTables();
});

/** @return array<int,array<string,mixed>> */
function vetoresDeCredencial(): array
{
    $caminho = base_path('tests/fixtures/credential-shapes-vectors.json');
    $json = json_decode((string) file_get_contents($caminho), true, 512, JSON_THROW_ON_ERROR);

    return $json['vetores'];
}

/**
 * Valor sintético do vetor que exercita `$shape`.
 *
 * Os valores vivem SÓ no fixture, nunca inline aqui: `tests/fixtures/` está no
 * allowlist do `.gitleaks.toml`, este arquivo não (ele é `*Test.php`, e o
 * allowlist cobre `*.test.php`). Um `AKIA...` literal aqui reprovava o
 * `Secret scan` — medido no #7418. Bônus: o fixture vira fonte única dos
 * valores, então PHP e JS exercitam exatamente os mesmos.
 */
function valorSinteticoDe(string $shape): string
{
    foreach (vetoresDeCredencial() as $v) {
        if (isset($v['shapes'][$shape], $v['sumir'])) {
            return $v['sumir'];
        }
    }

    throw new RuntimeException("fixture sem vetor com 'sumir' para o shape {$shape}");
}

it('MORDE e NAO morde exatamente como o fixture manda (paridade com redact.mjs)', function () {
    $vetores = vetoresDeCredencial();
    expect($vetores)->not->toBeEmpty();

    foreach ($vetores as $v) {
        $hits = [];
        $saida = CredentialShapes::redigir($v['entrada'], $hits);

        expect($hits)->toEqual($v['shapes'], 'vetor divergiu: '.$v['nome']);

        if ($v['shapes'] === []) {
            // controle negativo: sai byte a byte idêntico
            expect($saida)->toBe($v['entrada'], 'nao deveria ter mudado: '.$v['nome']);

            continue;
        }

        if (isset($v['sumir'])) {
            expect(str_contains($saida, $v['sumir']))->toBeFalse('o valor sobreviveu: '.$v['nome']);
        }

        if (isset($v['manter'])) {
            expect($saida)->toContain($v['manter']);
        }
    }
});

it('o comando esta REGISTRADO no Artisan (disco nao e registro)', function () {
    // `class_exists`/`app()` medem o DISCO; registro e pergunta do registry. O
    // proprio ForjaServiceProvider carrega o aviso: comando existe no disco mas
    // nunca chega ao Artisan (medido 2026-07-28 no `project-mgmt:health`).
    expect(array_keys(Artisan::all()))->toContain('cc:secret-sweep');
});

it('dry-run NAO escreve, --apply escreve preservando o rotulo, e a 2a passada e idempotente', function () {
    $segredo = valorSinteticoDe('assign_generic');
    $original = 'DB_USERNAME=staging DB_PASSWORD='.$segredo.' APP_ENV=staging';
    $id = inserirMensagem($original);

    Artisan::call('cc:secret-sweep');
    expect(DB::table('mcp_cc_messages')->where('id', $id)->value('content_text'))
        ->toBe($original, 'dry-run escreveu — deveria ser somente leitura');

    Artisan::call('cc:secret-sweep', ['--apply' => true]);
    $depois = (string) DB::table('mcp_cc_messages')->where('id', $id)->value('content_text');
    expect(str_contains($depois, $segredo))->toBeFalse('o segredo sobreviveu ao --apply');
    expect($depois)->toContain('DB_PASSWORD=[REDACTED:assign_generic]');
    expect($depois)->toContain('DB_USERNAME=staging');

    Artisan::call('cc:secret-sweep', ['--apply' => true]);
    expect(DB::table('mcp_cc_messages')->where('id', $id)->value('content_text'))
        ->toBe($depois, 'a 2a passada mudou o conteudo — nao e idempotente');
});

it('CONTROLE: mensagem benigna passa intacta pelo --apply', function () {
    $benigno = 'chamei mcp__ccd_session_mgmt__list_sessions e o SELECT devolveu 12 linhas';
    $id = inserirMensagem($benigno);

    Artisan::call('cc:secret-sweep', ['--apply' => true]);

    expect(DB::table('mcp_cc_messages')->where('id', $id)->value('content_text'))
        ->toBe($benigno, 'redigiu conteudo benigno — falso-positivo');
});

it('redige BLOB, que vive comprimido, e mantem os quatro campos coerentes', function () {
    $segredo = valorSinteticoDe('aws_akia');
    $conteudo = 'log do deploy: usando '.$segredo.' no cluster';
    $comprimido = zlib_encode($conteudo, ZLIB_ENCODING_DEFLATE, 6);

    $id = (int) DB::table('mcp_cc_blobs')->insertGetId([
        'hash_sha256' => hash('sha256', $conteudo),
        'blob_type' => 'stdout',
        'size_original_bytes' => strlen($conteudo),
        'size_compressed_bytes' => strlen((string) $comprimido),
        'compressed_data' => $comprimido,
        'refs_count' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('cc:secret-sweep', ['--apply' => true]);

    $b = DB::table('mcp_cc_blobs')->where('id', $id)->first();
    $texto = zlib_decode($b->compressed_data);

    expect(str_contains((string) $texto, $segredo))->toBeFalse('o segredo sobreviveu no blob');
    expect($texto)->toContain('[REDACTED:aws_akia]');
    // os quatro campos andam juntos ou o registro fica incoerente
    expect($b->hash_sha256)->toBe(hash('sha256', (string) $texto));
    expect((int) $b->size_original_bytes)->toBe(strlen((string) $texto));
    expect((int) $b->size_compressed_bytes)->toBe(strlen((string) $b->compressed_data));
});

it('--fail-on-find devolve exit 1 com credencial e 0 depois de limpo', function () {
    inserirMensagem(valorSinteticoDe('aws_akia'));

    expect(Artisan::call('cc:secret-sweep', ['--fail-on-find' => true]))->toBe(1);

    Artisan::call('cc:secret-sweep', ['--apply' => true]);

    expect(Artisan::call('cc:secret-sweep', ['--fail-on-find' => true]))->toBe(0);
});
