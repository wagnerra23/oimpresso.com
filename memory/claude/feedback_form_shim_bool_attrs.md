---
name: Form shim normaliza atributos HTML booleanos
description: App\View\Helpers\Form remove attrs booleanos (disabled/readonly/etc) do array quando valor é false/null. Passar `disabled=false` diretamente ativa o atributo — sempre omitir ou usar true.
type: feedback
originSessionId: 6cbda521-1ac7-4ff2-9419-9acdb42822ac
---
Ao chamar `Form::text/select/textarea/etc` do shim `App\View\Helpers\Form`, **atributos HTML booleanos** (`disabled`, `readonly`, `required`, `checked`, `selected`, `autofocus`, `multiple`, `hidden`, `async`, `defer`, `reversed`, `open`, `autoplay`, `controls`, `loop`, `muted`, `formnovalidate`, `nomodule`, `ismap`, `default`, `novalidate`) são automaticamente removidos do array quando o valor é `false` ou `null`.

**Why:** em HTML, a mera **presença** do atributo (`disabled`, `disabled=""`, `disabled="false"`, `disabled="anything"`) ativa a flag — valor é irrelevante. Antes do fix (2026-04-24, commit `7fbfbdc7`), o shim passava o array direto para `spatie/laravel-html` que renderizava `disabled=""` mesmo recebendo `false`. Resultado: bug crônico em `/sells/create` onde o campo `#search_product` ficava travado porque o blade fazia `'disabled' => is_null($default_location) ? true : false`. Cliente ROTA LIVRE ficou sem conseguir vender.

**How to apply (em código novo):**
- Pode passar `'disabled' => true/false/null` livremente — o shim normaliza
- Também pode usar sintaxe curta `['required']` (laravelcollective-style) — spatie interpreta como attr sem value
- Testes: 19 casos regressivos em `tests/Feature/Form/FormShimEquivalenceTest.php` cobrindo (false/null/true) x 6 attrs booleanos + teste específico `disabled=false + autofocus=true` (cenário `/sells/create`)

**Quando NÃO confiar no shim:**
- Se chamar spatie/laravel-html direto (`Html::text(...)->attributes(...)`) sem passar pelo shim, o bug volta. Use sempre `Form::*`.
- Se alguém reverter o `normalizeOptions` no shim, o bug retorna em TODAS as 460+ views que usam `Form::*`.
