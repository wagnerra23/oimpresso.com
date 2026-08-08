<?php

declare(strict_types=1);

use Modules\Jana\Services\Mcp\IndexarMemoryGitParaDb;

/**
 * O fix do redactor (#5169) NÃO alcançava o indexador do RAG.
 *
 * PR #5169 corrigiu `Services/Privacy/PiiRedactor.php`. Mas o `mcp:sync-memory`
 * não usa essa classe: `IndexarMemoryGitParaDb` carrega cópia PRÓPRIA de
 * `PII_PATTERNS` + `redactarPii()` e tem ZERO referência ao `PiiRedactor`.
 * Duas implementações, uma corrigida — o consumidor ficou com a antiga.
 *
 * Medido em produção 2026-08-02, DEPOIS do #5169 estar deployado (prod em
 * `75914b058`, com `c33d291` como ancestral): rodar
 * `php artisan mcp:sync-memory --only=casos` devolveu
 *
 *     75 indexados (0 novos, 0 atualizados), 33 redactions PII
 *
 * `0 atualizados` porque o redactor não-corrigido reproduz conteúdo IDÊNTICO ao
 * armazenado — a comparação do upsert é `content_md !== $contentRedacted`. O
 * índice seguia com `run XXX.XXX.XXX-NN` onde o disco tem `run 30366164436`,
 * ou seja: apagado do RAG justamente o recibo de CI que a regra de evidência
 * exige como prova.
 *
 * ⚠️ Metade destes casos são CONTROLE DE NÃO-AFROUXAMENTO. Num fix de PII, provar
 * que o filtro ficou mais esperto vale pouco; provar que ele NÃO ficou mais frouxo
 * é o que importa. Se algum `redige` abaixo virar `não redige`, é incidente LGPD.
 *
 * Constantes com nome próprio de propósito: a lane roda este arquivo e o
 * `PiiRedactorNumeroCruTest` no MESMO invoke do Pest, e `const` global repetido
 * seria fatal error de redeclaração.
 */
uses(Tests\TestCase::class);

/** Run id real, dos que foram redigidos em produção. 11 dígitos, DV inválido. */
const IDX_RUN_ID_GITHUB = '30366164436';

/** CPF sintético com DV VÁLIDO — o controle que prova que nada afrouxou. */
const IDX_CPF_CRU_VALIDO = '11144477735'; // pii-allowlist (sintético, DV válido, fixture do teste)

/** Expõe o `redactarPii()` protegido sem reflection. */
function idxRedactor(): object
{
    return new class ('/tmp/repo-fake') extends IndexarMemoryGitParaDb
    {
        public function redigir(string $texto): array
        {
            return $this->redactarPii($texto);
        }
    };
}

it('não redige run id do GitHub — 11 dígitos com DV inválido não é CPF', function () {
    $texto = 'lane Estoque · MySQL, run ' . IDX_RUN_ID_GITHUB . ' (PR #4953)';

    $r = idxRedactor()->redigir($texto);

    expect($r['redacted'])->toContain(IDX_RUN_ID_GITHUB)
        ->and($r['redacted'])->not->toContain('XXX.XXX.XXX-NN')
        ->and($r['count'])->toBe(0);
});

it('preserva a linha real do casos.md que produção apagou', function () {
    // Frase literal de `resources/js/Pages/Produto/Edit.casos.md`, a que virou
    // `run XXX.XXX.XXX-NN` no índice.
    $texto = 'last_run_ci: "lane Estoque · MySQL, run ' . IDX_RUN_ID_GITHUB
        . ' (PR #4953), lido 2026-07-29: UC-PEDIT-05/06/07 vermelhos"';

    $r = idxRedactor()->redigir($texto);

    expect($r['redacted'])->toBe($texto)
        ->and($r['count'])->toBe(0);
});

it('CONTROLE: CPF cru com DV válido CONTINUA redigido', function () {
    $r = idxRedactor()->redigir('cliente ' . IDX_CPF_CRU_VALIDO . ' cadastrado');

    expect($r['redacted'])->toContain('XXX.XXX.XXX-NN')
        ->and($r['redacted'])->not->toContain(IDX_CPF_CRU_VALIDO)
        ->and($r['count'])->toBe(1);
});

it('CONTROLE: CPF PONTUADO segue redigido mesmo com DV inválido — formato é declaração', function () {
    // DV inválido de propósito: quem escreve pontuado está declarando que é CPF.
    $r = idxRedactor()->redigir('CPF 303.661.644-36 na nota'); // pii-allowlist (sintético, fixture)

    expect($r['redacted'])->toContain('XXX.XXX.XXX-NN')
        ->and($r['count'])->toBe(1);
});

it('CONTROLE: CNPJ e cartão seguem redigidos — default inalterado, igual ao PiiRedactor', function () {
    $cnpj = idxRedactor()->redigir('CNPJ 12.345.678/0001-99'); // pii-allowlist (sintético, fixture)
    $card = idxRedactor()->redigir('cartao 4111 1111 1111 1111'); // pii-allowlist (sintético, fixture)

    expect($cnpj['redacted'])->toContain('XX.XXX.XXX/XXXX-NN')
        ->and($cnpj['count'])->toBe(1)
        ->and($card['redacted'])->toContain('****-****-****-****')
        ->and($card['count'])->toBe(1);
});

it('conta apenas o que redigiu — run id no meio de CPF válido não infla o contador', function () {
    $texto = 'run ' . IDX_RUN_ID_GITHUB . ' e cliente ' . IDX_CPF_CRU_VALIDO;

    $r = idxRedactor()->redigir($texto);

    expect($r['count'])->toBe(1)
        ->and($r['redacted'])->toContain(IDX_RUN_ID_GITHUB)
        ->and($r['redacted'])->toContain('XXX.XXX.XXX-NN');
});

/**
 * Paridade com o `PiiRedactor`: a emenda do CNPJ vale nos DOIS redactors. Se um
 * lado mudar sem o outro, volta o defeito do LC-18 (duas cópias, uma corrigida).
 */

/** LID real do WhatsApp Multi-Device, dos que apareciam redigidos no índice. DV inválido. */
const IDX_LID_WHATSAPP = '14628809617558';

/** CNPJ sintético com DV VÁLIDO — controle de não-afrouxamento. */
const IDX_CNPJ_CRU_VALIDO = '11222333000181'; // pii-allowlist (sintético, DV válido, fixture do teste)

it('não redige LID do WhatsApp — 14 dígitos com DV inválido não é CNPJ', function () {
    $texto = 'remoteJid "' . IDX_LID_WHATSAPP . '@lid" na conversa';

    $r = idxRedactor()->redigir($texto);

    expect($r['redacted'])->toBe($texto)
        ->and($r['count'])->toBe(0);
});

it('CONTROLE: CNPJ cru com DV válido CONTINUA redigido', function () {
    $r = idxRedactor()->redigir('emitente ' . IDX_CNPJ_CRU_VALIDO);

    expect($r['redacted'])->toContain('XX.XXX.XXX/XXXX-NN')
        ->and($r['redacted'])->not->toContain(IDX_CNPJ_CRU_VALIDO)
        ->and($r['count'])->toBe(1);
});
