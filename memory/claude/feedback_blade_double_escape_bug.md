---
name: Bug Blade — {{ }} dentro de {!! !!} quebra parser PHP
description: Sintaxe inválida em Blade que mata view com 'unexpected token <'. Padrão concreto encontrado 2026-04-26 em resources/views/auth/register.blade.php:16 que travava /register em HTTP 500. Como diagnosticar via SSH e como evitar.
type: feedback
originSessionId: 78bc6849-f503-4b7f-93a1-4c2a439cc019
---
## Bug exato (2026-04-26)

`resources/views/auth/register.blade.php:16` tinha:

```blade
{!! Form::open(['url' => {{ route('business.postRegister') }}]) !!}
```

Sintoma: `/register` retornava **HTTP 500** com erro
```
syntax error, unexpected token "<" 
ViewException at register.blade.php:16
```

## Causa raiz

Blade tem **dois delimitadores**:
- `{{ $var }}` → echo escapado (htmlspecialchars)
- `{!! $var !!}` → echo cru (HTML literal)

**Não dá pra aninhar.** Quando o parser do Blade encontra `{!! ... {{ ... }} ... !!}`, ele compila pra PHP como:

```php
<?= e(...{{ route('...') }}...) ?>
```

E o PHP tenta parsear `{{` como literal, falha em `<` do próximo bloco. Daí "unexpected token <".

## Fix correto

Dentro de `{!! ... !!}`, **chame a função PHP direto** sem `{{ }}`:

```blade
{!! Form::open(['url' => route('business.postRegister')]) !!}
```

Mesmo padrão pra qualquer helper PHP dentro de array em `{!! Form !!}`:

```blade
{!! Form::label('email', __('messages.email_label')) !!}        ✅
{!! Form::label('email', {{ __('messages.email_label') }}) !!}  ❌ quebra
```

## Como diagnosticar 500 em produção

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  'cd domains/oimpresso.com/public_html && \
   > storage/logs/laravel.log && \
   curl -sL -o /dev/null https://oimpresso.com/<URL_QUEBRADA> && \
   sleep 1 && \
   head -30 storage/logs/laravel.log'
```

Limpar log antes (`> storage/logs/laravel.log`) garante que só o erro novo aparece. `head -30` pega a mensagem do erro (top do stack trace).

## Outros padrões equivalentes pra evitar

| ❌ Errado | ✅ Certo |
|---|---|
| `{!! Form::open([{{ $route }}]) !!}` | `{!! Form::open([$route]) !!}` |
| `{!! Form::text('x', {{ $val }}) !!}` | `{!! Form::text('x', $val) !!}` |
| `<a href="{{ {{ url('/') }} }}">` | `<a href="{{ url('/') }}">` |
| `{{ {!! $html !!} }}` | use uma das duas formas, não as duas |

## Por que isso vazou pra produção

A view `register.blade.php` é template UltimatePOS legado herdado, raramente acessada (sign-up flow). Em testes manuais ninguém clicou. Não tinha teste Pest cobrindo `/register` (memória `feedback_testes_com_nova_feature.md` reforça: rota nova sai com Pest test).

**Sentinela:** os 4 triggers Opus de testes (batch 1-4) disparados 2026-04-26 vão criar Pest test pra cada controller público — esse tipo de bug não vaza mais quando passar por CI.

## Fix permanente (PR #12)

PR #12 (`claude/cms-pr3-auth-social`) substitui `register.blade.php` por `Pages/Site/Register.tsx` Inertia com login social Google/Microsoft. Quando mergear, esse Blade legado morre — bug fica no histórico.

**Status:** hotfix commitado em `6.7-bootstrap` em 2026-04-26 (`c10f62be fix(auth): /register 500`). `/register` retorna 200 novamente.
