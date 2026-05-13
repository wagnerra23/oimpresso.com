---
name: Eloquent `(array) $model` quebra Inertia — sempre `->toArray()`
description: PHP cast `(array)` em Eloquent\Model expõe propriedades protected com prefixo null-byte (`\x00*\x00attributes`, ~30 chaves estranhas). Inertia serializa, mas o JSON no JS não permite acesso direto aos campos — frontend recebe undefined. Aparece quando Collection→map(fn ($a) => (array) $a). Fix: $a->toArray().
type: feedback
---
## Bug

Quando Controller PHP faz `(array) $eloquent_model` num `Collection::map()` pra passar pro Inertia, **o cast PHP nativo expõe propriedades protected do `Model` com prefixo null-byte** (`\x00*\x00attributes`, `\x00*\x00original`, `\x00*\x00casts`, `\x00*\x00classCastCache`, ~30 chaves totais). Inertia serializa esse array, mas:

- O JSON resultante tem chaves estranhas tipo `"*attributes"` que React não acessa via `a.name`
- Os attributes reais ficam **enterrados** em `a[" * attributes"].name`
- Frontend renderiza `undefined` em todos os campos

**Comparação real (validado prod biz=1, 2026-05-11):**

```php
$a = Account::select(['id','name','account_number'])->first();

// BUG — 30 chaves null-byte
(array) $a
// [" * attributes" => [...], " * original" => [...],
//  " * casts" => [...], "timestamps" => true, "usesUniqueIds" => false,
//  " * hidden" => [], " * visible" => [], ... 30+ chaves]

// CORRETO — 3 chaves limpas
$a->toArray()
// ["id" => 23, "name" => "BANCO BRASIL - ELIANA PF", "account_number" => "19"]
```

## Sintoma na tela

- `Cc undefined` em vez do account_number
- `—` (em-dash) em vez do banco
- Lista renderiza N linhas (count certo) mas todos os campos vazios
- DevTools console SEM erro JS (porque `a.name` é tecnicamente válido, retorna undefined)

## Fix

Trocar `(array) $a` por `$a->toArray()` **sempre que $a é Eloquent\Model**. PR #596.

```php
// ANTES (bug)
$accounts = Account::select(...)->get()->map(function ($a) {
    $result = (array) $a;
    unset($result['secret_field']);
    return $result;
});

// DEPOIS (fix)
$accounts = Account::select(...)->get()->map(function ($a) {
    $result = $a->toArray();
    unset($result['secret_field']);
    return $result;
});
```

## Quando `(array) $obj` É seguro

- `DB::table('x')->get()->map(fn($r) => (array) $r)` — `$r` é `\stdClass` (não Model), cast funciona
- `(array) $request->input('foo', [])` — input PHP nativo
- `Account::find(1)`, `Account::get()->first()`, qualquer `Eloquent\Model` — usar `->toArray()` ou `->getAttributes()`

## Quando ativar suspeita

Sempre que vê:
- Frontend Inertia/React mostrando `undefined`, `null` ou em-dash em campos que existem no DB
- Tela funciona em dev (Eloquent serializa via `->toJson()` automático quando volta da rota), mas quebra quando Controller faz `map()` retornando array
- Grep `\(array\)\s+\$` em Controllers de Modules — qualquer match precisa verificar se $ é Eloquent ou stdClass

## Detecção preventiva

Validado: `grep -nE '\(array\)\s+\$\w+' Modules/**/Http/Controllers/*.php` lista todos os usos. Em 2026-05-11 só `Modules/Financeiro/Http/Controllers/ContaBancariaController.php` tinha o bug; os demais (Governance, Superadmin) eram `(array) $stdClass` de `DB::table()` — OK.

## Root cause técnico

PHP `(array) $obj` cast serializa propriedades respeitando visibilidade:
- `public` → key normal
- `protected` → key com prefixo `\0*\0`
- `private` → key com prefixo `\0ClassName\0`

`Eloquent\Model` tem `protected $attributes`, `protected $original`, etc — então `(array) $model` retorna essas chaves com null-bytes (visualmente invisíveis mas semanticamente diferentes de keys limpas).

`toArray()` é o método público de Eloquent que retorna `$this->attributes` com appends/hidden/visible aplicados — caminho canônico Laravel.

## Refs

- PR #596 ([fix](https://github.com/wagnerra23/oimpresso.com/pull/596))
- ContaBancariaController.php:85
- [Laravel Eloquent toArray docs](https://laravel.com/docs/eloquent-serialization)
- Apareceu após migration 19 contas WR Comercial (ver feedback-legacy-migration-importer.md) — bug latente antes (placeholders id=1,2 com `name=Bradesco`/`Caixa` simples não evidenciavam o problema visualmente)
