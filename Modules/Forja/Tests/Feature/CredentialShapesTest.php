<?php

declare(strict_types=1);

use App\Support\Privacy\CredentialShapes;
use Illuminate\Support\Facades\Artisan;

uses(Tests\TestCase::class);

/**
 * Parte PURA do bite-test da redação de credencial: vocabulário + registro.
 *
 * ── POR QUE SEPARADO DO `CcSecretSweepTest` ──────────────────────────────────
 * Aquele monta tabelas sintéticas e por isso se auto-pula fora do sqlite (idioma
 * do vizinho `CcIngestPersistsFieldsTest`, US-GOV-021) — senão o
 * `Schema::drop` rodaria contra o MySQL PERSISTENTE do CT 100/CI e o
 * `sqlite-test-corruptors` o classifica como corruptor, corretamente.
 *
 * Mas a lane `forja-pest` roda em **MySQL**. Se o arquivo inteiro carregasse o
 * guard, ele viraria skip justamente onde a lane roda — e skip conta como pass,
 * que é a LC-13 ("0 failed num teste que não rodou"). Estes asserts não tocam
 * banco nenhum, então ficam aqui e rodam nas DUAS lanes, dando assertions reais.
 *
 * Vetores compartilhados com o lado cliente (`scripts/cc-watcher/redact.test.mjs`):
 * `tests/fixtures/credential-shapes-vectors.json`.
 */

require_once __DIR__.'/../Support/credential-vectors.php';

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

it('preserva o ROTULO e remove so o VALOR', function () {
    $segredo = valorSinteticoDe('assign_generic');
    $hits = [];

    $saida = CredentialShapes::redigir('DB_USERNAME=staging DB_PASSWORD='.$segredo.' APP_ENV=staging', $hits);

    expect($hits)->toEqual(['assign_generic' => 1]);
    expect(str_contains($saida, $segredo))->toBeFalse();
    expect($saida)->toContain('DB_PASSWORD=[REDACTED:assign_generic]');
    // o vizinho benigno sobrevive — a redacao nao come contexto
    expect($saida)->toContain('DB_USERNAME=staging');
    expect($saida)->toContain('APP_ENV=staging');
});

it('o comando esta REGISTRADO no Artisan (disco nao e registro)', function () {
    // `class_exists`/`app()` medem o DISCO; registro e pergunta do registry. O
    // proprio ForjaServiceProvider carrega o aviso: comando existe no disco mas
    // nunca chega ao Artisan (medido 2026-07-28 no `project-mgmt:health`).
    expect(array_keys(Artisan::all()))->toContain('cc:secret-sweep');
});
