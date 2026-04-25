---
name: format_now_local pra "agora" + readonly precisa datetimepicker
description: format_date('now') sofre o shift +3h intencional histórico — usar format_now_local() pra campos pré-preenchidos com "agora". Campos readonly só editam se datetimepicker inicializar neles.
type: feedback
originSessionId: 6cbda521-1ac7-4ff2-9419-9acdb42822ac
---
## Contexto histórico

Em 2026-04-24 final do dia, cliente ROTA LIVRE reclamou: em `/sells/create` o campo `transaction_date` chegava 3h adiantado e não deixava editar manualmente. Bug duplo:

1. **Shift +3h no valor inicial** — `SellController` passava `$default_datetime = $this->businessUtil->format_date('now', true)`. Como `format_date()` mantém shift +3h **intencional** pra preservar consistência visual com vendas antigas (ver `feedback_carbon_timezone_bug.md`), aplicar isso no "agora" empurra a hora 3h pro futuro.

2. **Campo readonly sem datetimepicker** — `transaction_date` era `readonly` no Blade, mas o JS só inicializava `$('.paid_on').datetimepicker()`. Sem trigger no input, o readonly travava de verdade.

Esse bug **sempre existiu** no código (desde o commit raiz `46419c12`), mas estava mascarado: antes do fix do middleware Timezone (commit `47c9e594` mais cedo no mesmo dia), `app.timezone` caía em UTC, e como o servidor Hostinger Linux roda em UTC, a hora "errada" coincidia com o relógio do servidor — o cliente lia como SP local sem perceber. Quando o middleware passou a setar SP corretamente, o shift +3h ficou visível.

## Solução adotada (commits f736da74 + 07c498c2)

### `Util::format_now_local($show_time, $business_details = null)`

Novo método em `app/Utils/Util.php` que retorna `Carbon::now()->format($format)` direto — respeita `app.timezone` setado pelo middleware, zero shift. **Não toca em `format_date()`** que continua com o bug intencional pra histórico.

```php
// app/Utils/Util.php
public function format_now_local($show_time = false, $business_details = null)
{
    $format = ! empty($business_details) ? $business_details->date_format : session('business.date_format', config('constants.default_date_format', 'd/m/Y'));
    if (! empty($show_time)) {
        $time_format = ! empty($business_details) ? $business_details->time_format : session('business.time_format');
        $format .= ($time_format == 12) ? ' h:i A' : ' H:i';
    }
    return \Carbon::now()->format($format);
}
```

### Substituição em 3 controllers

| Arquivo | Linha |
|---|---|
| `app/Http/Controllers/SellController.php` | 709 |
| `app/Http/Controllers/SellPosController.php` | 248 |
| `app/SellController.php` (legado) | 551 |

Padrão de comentário deixado no código:
```php
// format_now_local pra evitar shift +3h intencional do format_date.
// (ver feedback_carbon_timezone_bug.md)
$default_datetime = $this->businessUtil->format_now_local(true);
```

### Datetimepicker no input

Em `resources/views/sell/create.blade.php` e `resources/views/sell/edit.blade.php`:

```js
$('.paid_on, #transaction_date').datetimepicker({
    format: moment_date_format + ' ' + moment_time_format,
    ignoreReadonly: true,
});
```

`ignoreReadonly: true` permite que o picker mude o valor mesmo com `readonly` no HTML.

### Testes regressivos

`tests/Unit/FormatNowLocalTest.php` com 3 casos:

1. `format_now_local()` retorna o mesmo `Carbon::now()->format(...)` — bate até o minuto
2. `format_now_local(true, $business)` respeita override de business_details
3. **Sentinela**: `format_date('2026-04-24 09:00:00')` ainda retorna `'24/04/2026 12:00'` (+3h) — protege o bug intencional histórico de regressão silenciosa

## Lição operacional — Edit silencioso

Durante o fix, o primeiro commit (`f736da74`) ficou incompleto: o `Edit` em `app/Http/Controllers/SellController.php:706` falhou com erro `File has not been read yet` mas eu não percebi (o output do tool mostrava success em outros Edits do mesmo turno). Só descobri depois do deploy quando rodei `grep format_now_local` no servidor — vi 2/3 controllers OK e 1 ainda bugado. Precisei de commit corretivo `07c498c2`.

**Regra operacional:**
- Sempre que aplicar Edit em arquivo que ainda não foi Read na sessão, fazer Read primeiro
- Se Edit retornar erro "File has not been read yet", **NÃO assumir que o erro foi tratado** — re-aplicar
- Após deploy de mudança que toca múltiplos arquivos, rodar `grep` no servidor confirmando o conteúdo, não só `git status` local

## How to apply (em sessões futuras)

1. **Ao pré-preencher campo de form com "agora"** (transaction_date, paid_on, etc): use `format_now_local()`, NÃO `format_date('now')`
2. **Ao adicionar campo `readonly`** no Blade: garanta que datetimepicker (ou outro input opener) inicialize com `ignoreReadonly: true`
3. **Antes de fechar ticket**: confirmar via grep direto no servidor que o conteúdo deployado bate com o esperado

## Pontos relacionados na auto-memória

- `feedback_carbon_timezone_bug.md` — por que `format_date()` mantém shift +3h
- `cliente_rotalivre.md` — sensibilidade do cliente que fez essa descoberta
- `reference_hostinger_analise.md` — receita SSH pra grep pós-deploy
