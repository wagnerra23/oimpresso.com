<?php

declare(strict_types=1);

use App\Media;

/**
 * Upload não pode gravar arquivo que o servidor executaria em public/uploads.
 * Origem: invasão de 2026-11-12 (webshell `bolela.php` em public/uploads/media desde 2024).
 * Sem banco: só o nome do arquivo (sqlite-safe).
 */
it('recusa nomes com extensão executável, inclusive disfarçada', function (string $nome) {
    expect(Media::nomeDeArquivoSeguro($nome))->toBeNull();
})->with([
    'php' => ['bolela.php'],
    'php maiúsculo' => ['SHELL.PHP'],
    'php5' => ['1.php5'],
    'php74' => ['bolela.php74'],
    'phtml' => ['1.phtml'],
    'ph5' => ['1 - Copy.ph5'],
    'phar' => ['x.phar'],
    'dupla extensão' => ['db.php.jpg'],
    'htaccess' => ['.htaccess'],
    'user.ini' => ['.user.ini'],
    'caminho embutido' => ['../../public/a.php'],
    'vazio' => [''],
]);

it('aceita nomes comuns de imagem e documento, sem alterar o nome', function (string $nome) {
    expect(Media::nomeDeArquivoSeguro($nome))->toBe($nome);
})->with([
    ['foto do produto.jpg'],
    ['Nota Fiscal 123.pdf'],
    ['planilha.vendas.xlsx'],
    ['logo-empresa.PNG'],
    ['relatório março.docx'],
]);

it('tira diretórios do nome enviado', function () {
    expect(Media::nomeDeArquivoSeguro('C:\\Users\\x\\foto.jpg'))->toBe('foto.jpg');
    expect(Media::nomeDeArquivoSeguro('pasta/foto.jpg'))->toBe('foto.jpg');
});

it('public/uploads tem .htaccess versionado que bloqueia execução de PHP', function () {
    $ht = (string) file_get_contents(public_path('uploads/.htaccess'));

    expect($ht)->toContain('<FilesMatch');
    expect($ht)->toContain('Deny from all');
});
