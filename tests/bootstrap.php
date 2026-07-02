<?php

declare(strict_types=1);

/*
 * Bootstrap de teste — força APP_ENV=testing ANTES do autoload/boot do Laravel.
 *
 * POR QUÊ: o container CT100 `oimpresso-staging` (onde a suite roda contra MySQL real,
 * ADR 0062) exporta APP_ENV=staging como env var REAL do SO. O `<env name="APP_ENV"
 * value="testing"/>` do phpunit.xml — mesmo com force="true" — NÃO sobrepõe essa var
 * pré-existente sob `php artisan test`/`vendor/bin/pest` (comprovado empiricamente no
 * CT100). Resultado: app()->environment()='staging' no runtime → runningUnitTests()=false
 * → VerifyCsrfToken volta a EXIGIR o token → todo POST de teste feature devolve 419
 * (falso-negativo sistêmico: ImpostosGuardTest, suite Support, etc).
 *
 * O único ponto confiável de sobreposição é ANTES do `require vendor/autoload.php`: aqui
 * setamos putenv + $_ENV + $_SERVER, então quando LoadEnvironmentVariables roda o Dotenv
 * (imutável) já encontra 'testing' e não regride pro .env/SO. CI e local NÃO têm APP_ENV
 * no SO (vem do .env, tarde demais) → isto é no-op lá; só corrige o ambiente que vazava.
 */
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

require __DIR__.'/../vendor/autoload.php';
