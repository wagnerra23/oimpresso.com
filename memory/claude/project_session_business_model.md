---
name: session('business') é Eloquent Model, não array
description: SetSessionData salva o objeto Business na session. Dot-notation (session('business.x')) não funciona. Para timezone, usar chave dedicada session('business_timezone').
type: project
originSessionId: 6cbda521-1ac7-4ff2-9419-9acdb42822ac
---
`app/Http/Middleware/SetSessionData.php` faz `$request->session()->put('business', $business)` passando o **Eloquent Model inteiro**, não array. Consequência:

- `session('business')['campo']` — funciona (ArrayAccess trait)
- `session('business')->campo` — funciona (acesso normal a model)
- `session()->has('business.campo')` — **retorna false** (dot-notation só olha em arrays plain)
- `session()->get('business.campo')` / `Session::get('business.campo')` em Blade — **retorna null**

**Why:** Bug raiz de 2026-04-24 do timezone não aplicado no frontend. O blade `layouts/partials/javascripts.blade.php` tinha `moment.tz.setDefault('{{ Session::get('business.time_zone') }}')` — sempre vazio. Fix: criar chave dedicada `business_timezone` (string plain) em `SetSessionData` + consumer update em `Timezone.php` e no blade.

**How to apply:**
- Se precisar de um sub-campo de `business` de forma confiável em qualquer camada, **adicionar nova chave na session** ao lado do objeto (como `business_timezone`) em `SetSessionData::handle()`.
- Ao ler `session('business')`, tratar como objeto OU array (pode ser qualquer dos dois em diferentes pontos do código — ver `TransactionUtil.php:4667` etc.). Usar o padrão:
  ```php
  $val = is_object($business) ? ($business->x ?? null) : ($business['x'] ?? null);
  ```
- Mesmo cuidado vale para `session('currency')` — também salvo como objeto (verificar antes).
