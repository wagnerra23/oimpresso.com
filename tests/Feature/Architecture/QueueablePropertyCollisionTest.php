<?php

declare(strict_types=1);

/**
 * GUARD — colisao de property entre classe e o trait `Illuminate\Bus\Queueable`.
 *
 * O trait declara `public $queue;` — SEM tipo e SEM default. Re-declarar a property
 * na classe que compoe o trait, com qualquer tipo/default diferente, viola as regras
 * de composicao de trait do PHP e e FATAL na CARGA da classe:
 *
 *   "X and Queueable define the same property ($queue) in the composition of X.
 *    However, the definition differs and is considered incompatible."
 *
 * Por que um guard estatico (e nao reflexao): carregar a classe defeituosa e
 * justamente o que mata o processo — um teste que tentasse `new X` derrubaria a
 * suite inteira em vez de reportar. Entao a leitura e do TEXTO, e a pergunta que ele
 * responde e exatamente a regra do PHP, nao uma heuristica de intencao.
 *
 * Two-strikes (ADR 0344): strike 1 = `Modules/NfeBrasil/Jobs/EmitirNfceJob.php`
 * (consertado, com o comentario explicando a pegadinha); strike 2 = 2026-08-19,
 * `Modules/PaymentGateway/Jobs/ProcessarWebhookPixInterJob.php` — dormente porque as
 * flags do PaymentGateway estao OFF em prod. Na 2a ocorrencia, codifica.
 *
 * FP MEDIDO ANTES DE INSTALAR (§5 proibicoes — 2026-08-19, corpus app/ + Modules/):
 *   4338 arquivos .php varridos
 *     87 compoem o trait Queueable
 *      6 redeclaram $queue  (os 6 sao Listeners que NAO compoem o trait — inofensivos)
 *      0 fazem as duas coisas  => 0 falso-positivo
 *   Controle negativo: com o arquivo pre-fix de origin/main no corpus, morde 1/1.
 *
 * `php -l` NAO substitui este guard: medido em 2026-08-19, rc=0 (sem erro de sintaxe)
 * no mesmo arquivo que fatalava na carga.
 */

/**
 * Retorna a linha ofensiva se a classe compoe Queueable E redeclara $queue; senao null.
 *
 * Imports ficam ANTES da linha `class ...`; trait-use fica DEPOIS. Essa separacao evita
 * confundir `use Illuminate\Bus\Queueable;` (import, inofensivo) com `use Queueable;`
 * (composicao, que e o que importa).
 */
function detectarColisaoQueue(string $codigo): ?string
{
    $linhas = preg_split('/\R/', $codigo);
    if ($linhas === false) {
        return null;
    }

    $idxClasse = null;
    foreach ($linhas as $i => $linha) {
        if (preg_match('/^\s*(final\s+|abstract\s+|readonly\s+)*class\s+/', $linha) === 1) {
            $idxClasse = $i;
            break;
        }
    }
    if ($idxClasse === null) {
        return null;
    }

    $compoeTrait = false;
    $redeclarada = null;
    $total = count($linhas);
    for ($i = $idxClasse + 1; $i < $total; $i++) {
        $t = trim($linhas[$i]);
        if (str_starts_with($t, 'use ') && str_contains($t, 'Queueable')) {
            $compoeTrait = true;
        }
        if (preg_match('/^(public|protected|private)\s.*\$queue\s*=/', $t) === 1) {
            $redeclarada = $t;
        }
    }

    return ($compoeTrait && $redeclarada !== null) ? $redeclarada : null;
}

/** Varre .php sob um diretorio raiz. */
function arquivosPhpDe(string $dir): array
{
    if (! is_dir($dir)) {
        return [];
    }
    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        if ($f->isFile() && $f->getExtension() === 'php') {
            $out[] = $f->getPathname();
        }
    }

    return $out;
}

// ---------------------------------------------------------------------------
// BITE-TEST: prova que o guard MORDE antes de confiar no verde dele.
// Sem isto, "0 violacoes" e indistinguivel de "o detector nao funciona".
// ---------------------------------------------------------------------------

it('BITE: acusa classe que compoe Queueable e redeclara $queue', function () {
    $ruim = <<<'PHP'
    <?php
    namespace Foo;
    use Illuminate\Bus\Queueable;
    use Illuminate\Contracts\Queue\ShouldQueue;
    class JobRuim implements ShouldQueue
    {
        use Queueable;
        public string $queue = 'paymentgateway';
    }
    PHP;

    expect(detectarColisaoQueue($ruim))->toContain('$queue');
});

it('BITE: nao acusa classe que compoe Queueable e usa onQueue no constructor', function () {
    $boa = <<<'PHP'
    <?php
    namespace Foo;
    use Illuminate\Bus\Queueable;
    use Illuminate\Contracts\Queue\ShouldQueue;
    class JobBom implements ShouldQueue
    {
        use Queueable;
        public int $tries = 3;
        public function __construct()
        {
            $this->onQueue('paymentgateway');
        }
    }
    PHP;

    expect(detectarColisaoQueue($boa))->toBeNull();
});

it('BITE: nao acusa Listener que redeclara $queue SEM compor o trait', function () {
    // Este e o controle que impede o guard de reprovar os 6 Listeners legitimos:
    // sem `use Queueable;` no corpo da classe nao ha composicao, logo nao ha colisao.
    $listener = <<<'PHP'
    <?php
    namespace Foo;
    use Illuminate\Contracts\Queue\ShouldQueue;
    class MeuListener implements ShouldQueue
    {
        public string $queue = 'nfe';
    }
    PHP;

    expect(detectarColisaoQueue($listener))->toBeNull();
});

// ---------------------------------------------------------------------------
// O GUARD propriamente dito.
// ---------------------------------------------------------------------------

it('nenhuma classe do repo redeclara $queue compondo o trait Queueable', function () {
    $arquivos = array_merge(
        arquivosPhpDe(base_path('app')),
        arquivosPhpDe(base_path('Modules')),
    );

    // Sanidade: se a varredura vier vazia, o verde seria falso (gate mudo).
    expect($arquivos)->not->toBeEmpty('Varredura vazia — o guard nao mediu nada.');

    $violacoes = [];
    foreach ($arquivos as $caminho) {
        $codigo = file_get_contents($caminho);
        if ($codigo === false) {
            continue;
        }
        $linha = detectarColisaoQueue($codigo);
        if ($linha !== null) {
            $rel = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $caminho);
            $violacoes[] = strtr($rel, DIRECTORY_SEPARATOR, '/') . '  ==>  ' . $linha;
        }
    }

    expect($violacoes)->toBe([], implode("\n", array_merge(
        ['Property $queue redeclarada em classe que compoe Illuminate\Bus\Queueable.'],
        ['Isso e FATAL na carga da classe (php -l NAO pega).'],
        ['Fix canonico: remova a property e chame $this->onQueue(...) no constructor.'],
        ['Ver Modules/NfeBrasil/Jobs/EmitirNfceJob.php.'],
        $violacoes,
    )));
});
