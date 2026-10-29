<?php

// Imprime, em JSON, os dados da página "Casos de uso por tela" lidos pelo leitor PHP
// (app/Services/Documentacao/CasosPorTela.php). Só existe para o teste de paridade
// (paridade-php.test.mjs) comparar com `gerar.mjs --json`. Sem Laravel, sem banco.
//
// Uso: php scripts/casos-backlog/dados.php [raiz]

declare(strict_types=1);

$raiz = $argv[1] ?? dirname(__DIR__, 2);
require dirname(__DIR__, 2) . '/app/Services/Documentacao/CasosPorTela.php';

echo json_encode((new App\Services\Documentacao\CasosPorTela($raiz))->dados(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
