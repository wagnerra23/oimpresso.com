# Sessão — 2026-04-24 — Fix do shim Form:: que travava /sells/create

## Contexto

Mesmo depois de corrigir a permissão `location.4` pro role Vendas#4 do ROTA LIVRE (sessão `2026-04-24-rotalivre-venda-liberada.md`), o cliente mandou vídeo ao Wagner confirmando que o campo `#search_product` em `/sells/create` **continuava disabled**.

Investigação revelou **segundo bug independente** — desta vez no código, não nos dados.

## Causa raiz

`App\View\Helpers\Form` (shim `laravelcollective/html` → `spatie/laravel-html`) passava o array `$options` direto pro builder do spatie sem filtrar valores booleanos falsos.

Teste empírico via tinker:

```php
echo Form::text('teste', null, ['disabled' => false, 'autofocus' => true])->toHtml();
// ANTES do fix: <input type="text" name="teste" id="teste" disabled autofocus="1">
// DEPOIS do fix: <input type="text" name="teste" id="teste" autofocus="1">
```

Em HTML, a mera presença do atributo `disabled` ativa a flag — o valor é irrelevante. O blade `resources/views/sell/create.blade.php:359` faz:

```php
'disabled' => is_null($default_location) ? true : false,
'autofocus' => is_null($default_location) ? false : true,
```

Com `default_location` preenchido, o blade passava `disabled=false` ao shim. O shim repassava pro spatie que renderizava o atributo. Browser via `disabled=""` → campo travado.

Cenário completo do sintoma ROTA LIVRE:
- Antes do fix de permissão: `default_location=null` → `disabled=true` → campo disabled (correto mas por motivo errado)
- Depois do fix de permissão: `default_location=BL0001` → `disabled=false` → campo AINDA disabled (shim bugado)

## Fix aplicado

Adicionei `Form::normalizeOptions($options)` que remove do array todos os atributos HTML booleanos cujo valor seja `false` ou `null` antes de chamar `->attributes(...)`. Lista de 22 atributos booleanos cobrindo inputs, `details`, áudio/vídeo, etc.

Aplicado em **13 métodos públicos** do shim: `text`, `email`, `password`, `hidden`, `number`, `date`, `file`, `textarea`, `select`, `checkbox`, `radio`, `label`, `url`.

```php
private const BOOL_ATTRS = [
    'disabled', 'readonly', 'required', 'checked', 'selected', 'autofocus',
    'multiple', 'hidden', 'async', 'defer', 'reversed', 'open', 'autoplay',
    'controls', 'loop', 'muted', 'formnovalidate', 'nomodule', 'ismap',
    'default', 'novalidate',
];

private static function normalizeOptions(array $options): array
{
    foreach (self::BOOL_ATTRS as $attr) {
        if (array_key_exists($attr, $options) && ($options[$attr] === false || $options[$attr] === null)) {
            unset($options[$attr]);
        }
    }
    return $options;
}
```

## Testes regressivos

`tests/Feature/Form/FormShimEquivalenceTest.php` ganhou **19 casos novos**:
- 6 × `attr=false` deve ser omitido (disabled/readonly/required/autofocus/multiple/hidden)
- 6 × `attr=null` deve ser omitido
- 6 × `attr=true` deve ser mantido
- 1 × teste específico reproduzindo o cenário de `/sells/create`: `disabled=false + autofocus=true`

Suite inteira `tests/Feature/Form/*` verde: **45 tests, 160 assertions** em 5.73s.

## Deploy

- Commit: `7fbfbdc7` na branch `6.7-bootstrap`
- Push: `2c7c6ec..7fbfbdc  6.7-bootstrap -> 6.7-bootstrap`
- Deploy Hostinger: `git reset --hard origin/6.7-bootstrap` + `php artisan view:clear` + `config:clear`
- Verificação pós-deploy: `grep -c normalizeOptions app/View/Helpers/Form.php` = **14** (declaração + 13 métodos)

## Validação local

```js
// no browser em /sells/create após o fix:
document.querySelector('#search_product').disabled         // false
document.querySelector('#search_product').getAttribute('disabled')  // null (AUSENTE)
document.querySelector('#search_product').getAttribute('autofocus') // "1"
// canFocus: true
```

## Impacto sistêmico

Este bug afetava **toda tela com `'disabled' => condição ? true : false`** — há dezenas de casos em purchase/create, purchase_order/create, purchase_return, sale_pos e outros. O fix no shim resolve todos em uma única mudança, sem tocar nos blades individuais. Como o shim tem 25 testes Pest e mais 20 novos (45 total), qualquer regressão futura no comportamento do shim é capturada.

## Conexão com sessões anteriores

Incidente foi uma cadeia de bugs na mesma tela:
1. `2026-04-24-sells-labels-and-timezone.md` — labels PT + colunas + timezone (parte revertida)
2. `2026-04-24-revert-format-date-timezone.md` — revert do format_date por regressão visual no ROTA LIVRE
3. `2026-04-24-rotalivre-venda-liberada.md` — fix de dados (permissão `location.4` no role Vendas#4)
4. **Este fix** — fix de código (shim Form:: normalizando bool attrs)

Sem o fix 3 (permissão) + fix 4 (shim), o ROTA LIVRE continuaria sem conseguir vender. Ambos eram necessários.

## Lição aprendida

Bugs que se manifestam juntos nem sempre têm causa única. Neste caso, a tela `/sells/create` tinha **dois defeitos independentes** (permissão de dados + conversão de atributos no shim) e corrigir só um deixava o problema aparente. Regra: validar o fix end-to-end com teste reproduzindo o sintoma original — não só confirmar que a causa diagnosticada sumiu. Se tivesse rodado o browser logo após o fix de permissão, teria descoberto o segundo bug imediatamente ao invés de confiar que a explicação estava completa.
