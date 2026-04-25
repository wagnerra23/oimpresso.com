---
name: Form:: shim — laravelcollective → spatie/laravel-html
description: Migração do Form:: em 2026-04-23; laravelcollective removido, shim em App\View\Helpers\Form delega pra spatie preservando paridade HTML
type: project
originSessionId: 35b2b09f-6215-4da4-babc-740643587a77
---
Em 2026-04-23 migramos o helper `Form::` usado em ~6.433 chamadas / 535 `Form::open` em 260 arquivos Blade. `laravelcollective/html` não suporta Laravel 11+.

## Por que um shim em vez de usar spatie direto

**Why**: As 6.433 chamadas estão em Blade legado do UltimatePOS. Rescrever todas era escopo enorme. Shim mantém API `Form::text`, `Form::select`, etc. idêntica, delegando internamente pra `html()->text()` do spatie.

**How to apply**: Se aparecer nova chamada `Form::foo()` não suportada, adicionar método em `app/View/Helpers/Form.php` seguindo o padrão (converter args pra API spatie, forçar paridade HTML quando divergir).

## Divergências forçadas do spatie pra paridade laravelcollective

Documentadas no próprio `Form.php`:
- `checked`/`selected` em XHTML-style (`checked="checked"`), não HTML5 boolean
- `textarea` content pré-escapado com `e()` (spatie não escapa — era XSS silencioso)
- `submit` renderiza `<input type="submit">`, não `<button>`
- `password` sempre com value vazio presente
- `label` conteúdo escapado com `e()`
- `Form::select` converte Collection/Arrayable/Traversable pra array (spatie já aceita, laravelcollective também — o problema era minha strict type hint)

## Divergência ACEITA (melhoria de a11y)

spatie auto-adiciona `id="name"` em inputs. Laravelcollective nunca fazia. Aceitamos — melhora acessibilidade, não quebra nada existente.

## Cobertura de testes

- `tests/Feature/Form/FormShimEquivalenceTest.php` — 16 tests dos helpers
- `tests/Feature/Form/FormOpenCloseTest.php` — 8 tests de open/close/token
- `tests/Feature/Form/BladeAliasRenderTest.php` — renderiza Blade fixture via alias
- `tests/fixtures/views/form_kitchen_sink.blade.php` + `form_open_close.blade.php`

Validação browser: 14/15 views-chave no Laravel 11 (login, register, business/settings com 272 inputs, contacts/create, etc.) — zero shim bugs.

## Como testar antes de mexer

Antes de qualquer mudança no shim, rodar: `vendor/bin/pest tests/Feature/Form/`. 25 tests esperados. Se quebrar, é regressão.
