<?php

declare(strict_types=1);

use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * Colisão entre PII e NÚMERO COMPRIDO QUALQUER — achado em produção 2026-08-02.
 *
 * Ao indexar o trio de tela no RAG (B3, PR #5167), o sync reportou 32 redactions
 * de PII em 8 `casos.md` do Produto. Nenhuma era PII: eram **run id do GitHub
 * Actions**. Não era vazamento — era o oposto, e apagava do índice justamente o
 * recibo de CI que a regra de evidência do projeto exige como prova.
 *
 * Duas colisões, e a segunda só apareceu ao testar a primeira:
 *   1. `\d{11}` do CPF casa QUALQUER número de 11 dígitos.
 *   2. Liberado o CPF, o regex de TELEFONE casava os 10 primeiros (`3036616443`).
 *
 * O desempate é por regra de formação, e só para dígito CRU: CPF tem dígito
 * verificador, telefone tem DDD que existe. Run id não tem nenhum dos dois.
 *
 * ⚠️ Metade destes casos são CONTROLE DE NÃO-AFROUXAMENTO. Num fix de PII, provar
 * que o filtro ficou mais esperto vale pouco; provar que ele NÃO ficou mais frouxo
 * é o que importa. Se algum `redige` abaixo virar `não redige`, é incidente LGPD.
 *
 * Arquivo separado do `PiiRedactorTest` de propósito: aquele carrega fixtures de
 * CPF/CNPJ literais anteriores a este trabalho, e tocá-lo acordaria o `PII scan`
 * sobre dívida que não é deste PR (proibicoes §5 2026-07-12 + emenda 07-27).
 */
uses(Tests\TestCase::class);

/** Run id real, dos que foram redigidos em produção. 11 dígitos, DV inválido. */
const RUN_ID_GITHUB = '30366164436';

/** Segundo run id real do mesmo incidente. */
const RUN_ID_GITHUB_2 = '30122611472';

/** CPF sintético com DV VÁLIDO — o controle que prova que nada afrouxou. */
const CPF_CRU_VALIDO = '11144477735'; // pii-allowlist (sintético, DV válido, fixture do teste)

/** Celular com DDD real — controle de que telefone cru segue coberto. */
const CELULAR_DDD_REAL = '11987654321'; // pii-allowlist (sintético, fixture do teste)

it('não redige run id do GitHub — 11 dígitos com DV inválido não é CPF', function () {
    $r = new PiiRedactor();
    $texto = 'lane Estoque · MySQL, run ' . RUN_ID_GITHUB . ' (PR #4953), lido 2026-07-29';

    expect($r->redact($texto))->toBe($texto)
        ->and($r->detect($texto))->not->toHaveKey('CPF');
});

it('não redige dois run ids na mesma linha', function () {
    $r = new PiiRedactor();
    $texto = 'runs ' . RUN_ID_GITHUB_2 . ' e ' . RUN_ID_GITHUB;

    expect($r->redact($texto))->toBe($texto);
});

it('CONTROLE — CPF cru com DV válido continua redigido', function () {
    $r = new PiiRedactor();

    expect($r->redact('titular ' . CPF_CRU_VALIDO . ' no cadastro'))
        ->not->toContain(CPF_CRU_VALIDO);
});

it('CONTROLE — celular cru com DDD real continua redigido', function () {
    $r = new PiiRedactor();

    // O DDD é o que separa: "30" nunca foi alocado no Brasil; "11" é São Paulo.
    expect($r->redact('tel ' . CELULAR_DDD_REAL))->not->toContain(CELULAR_DDD_REAL);
});

it('CONTROLE — formato explícito dispensa validação e segue sempre redigido', function () {
    $r = new PiiRedactor();

    // Quem escreve com pontuação está DECLARANDO o que é. CPF digitado errado
    // continua sendo tentativa de PII, e por isso não passa pelo desempate.
    $cpfPontuadoDvInvalido = '123.456.789-01'; // pii-allowlist (fixture: DV inválido de propósito)
    $telefoneFormatado = '(11) 98765-4321';    // pii-allowlist (fixture)

    expect($r->redact("CPF {$cpfPontuadoDvInvalido}"))->not->toContain($cpfPontuadoDvInvalido)
        ->and($r->redact("fone {$telefoneFormatado}"))->not->toContain('98765-4321');
});

it('detect e redact concordam — has_pii não marca doc que ficou intacto', function () {
    $r = new PiiRedactor();
    $texto = 'run ' . RUN_ID_GITHUB_2 . ' e run ' . RUN_ID_GITHUB;

    // Se divergissem, o doc entraria no índice marcado com PII que o redact não
    // tocou — e o alerta apontaria para conteúdo limpo.
    expect($r->detect($texto))->not->toHaveKey('CPF')
        ->and($r->redact($texto))->toBe($texto);
});
