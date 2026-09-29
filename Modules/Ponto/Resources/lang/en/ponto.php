<?php

/*
|--------------------------------------------------------------------------
| Idioma `en` do Ponto — mesmos textos do pt/ponto.php
|--------------------------------------------------------------------------
| O `fallback_locale` do app é `en` (config/app.php) e o módulo só tinha o
| arquivo `pt`. Quem usava outro idioma, como o Superadmin em inglês, via a
| chave crua `pontowr2::ponto.module_label` no chip do pacote, na sidebar e
| nos rótulos de permissão.
|
| Os textos ficam em português de propósito: nome de domínio do Ponto é
| português (Portaria 671/2021, CLT), não se traduz. Mesmo padrão do
| pt-BR/ponto.php: uma fonte só, sem cópia para divergir.
*/

return require __DIR__ . '/../pt/ponto.php';
