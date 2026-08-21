<?php

/*
|--------------------------------------------------------------------------
| FEATURE FLAGS — override por ambiente (harness de teste / CI)
|--------------------------------------------------------------------------
|
| Mesmo desenho do `config/visreg.php`: este arquivo é um LEITOR BURRO da env.
| A guarda de produção NÃO mora aqui — mora no consumidor
| (`App\Services\FeatureFlagService::forcedOn`, via `isProduction()`), igual ao
| par `visreg.freeze_clock` + `AppServiceProvider:66` / `inertia.blade.php:34`.
|
| POR QUE EXISTE (medido em 2026-08-20, PR #5977):
| o `PixelBaselineTest` faz `assertInertia(component: '<Componente>')`. No runner
| do gate visual não há `GROWTHBOOK_SDK_KEY`, então `getFeatures()` devolve vazio
| e `isOn()` cai em `fallback()` — que só conhece o array `fallbackDefaults`. Com
| a flag OFF a rota devolve o Blade, o `assertInertia` reprova e a baseline nunca
| nasce. A tela ficaria fora do gate por CONFIGURAÇÃO, não por decisão.
|
| A doutrina é a do `MWART_CLIENTE_INDEX` no `visual-regression.yml`:
| "flag de ambiente pra tela poder ENTRAR no gate, não pra mudar o que ela mostra".
|
| ⚠️ ISTO NÃO É CUTOVER. Ligar uma flag para os business de PRODUÇÃO é decisão do
| [W] — feita no GrowthBook (`flag-set` / `flag-env-toggle`) ou, em último caso,
| no `fallbackDefaults`. Este caminho é INERTE em produção, por construção.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | forced_on — lista de flags forçadas em ON, separadas por vírgula
    |----------------------------------------------------------------------
    |
    | Consultada ANTES do GrowthBook: o harness precisa ser determinístico
    | mesmo quando o SDK está acessível e responde outra coisa.
    |
    | Formato: `FEATURE_FLAGS_FORCED_ON=useV2OfficeimpressoLogs,outraFlag`
    | (espaços em volta da vírgula são tolerados).
    |
    | Vazio/ausente (default) = nenhum override, zero efeito no comportamento
    | atual. Em produção o valor é IGNORADO mesmo se preenchido.
    |
    */

    'forced_on' => env('FEATURE_FLAGS_FORCED_ON', ''),

];
