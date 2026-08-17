<?php

/*
|--------------------------------------------------------------------------
| VISREG — harness do gate visual (visual-regression)
|--------------------------------------------------------------------------
|
| Flags do harness NÃO-INVASIVO do gate de regressão visual. Mesmo princípio
| das outras alavancas visreg (rota env-guarded /_visreg-state +
| VisregStateMiddleware): o estado determinístico vem do CONTEXTO do request,
| nunca de edição de controller ou de Page .tsx.
|
| Tudo aqui é no-op fora do job `visual-regression` — as envs só existem no
| .env que aquele workflow escreve.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | freeze_clock — instante do relógio do NAVEGADOR
    |----------------------------------------------------------------------
    |
    | Quando preenchido, `layouts/inertia.blade.php` injeta o shim
    | `resources/visreg/freeze-clock.js` no <head>, ANTES do bundle da app.
    | A partir daí `new Date()` e `Date.now()` no browser devolvem sempre este
    | instante — o que torna reproduzível a captura de telas que renderizam
    | "agora" (ex.: JanaAreaHeader.tsx:80, JanaCockpit.tsx:356 e :107).
    |
    | O valor é lido com `Carbon::parse()`, ou seja, no MESMO fuso da app e
    | com a MESMA semântica de `\Carbon\Carbon::setTestNow($valor)` que as
    | suítes de baseline já usam. Passando a mesma string nos dois lados, os
    | dois relógios ficam no mesmo INSTANTE.
    |
    | ⚠️ O contexto do Playwright roda com `timezoneId => 'UTC'`
    | (vendor pest-plugin-browser/src/Api/PendingAwaitablePage.php:175), enquanto
    | a app roda em config('app.timezone'). Então a string de parede exibida pelo
    | browser pode diferir da exibida pelo PHP — é o mesmo instante lido em dois
    | fusos, e o que o gate exige é que ela seja CONSTANTE, não igual à do PHP.
    |
    | Vazio/ausente (default) = nenhum shim injetado, zero efeito.
    |
    */

    'freeze_clock' => env('VISREG_FREEZE_CLOCK'),

];
