# Modules/Superadmin — SPEC (resumo legado)

## Propósito

Painel administrativo da WR2 sobre o tenant (negócios, packages,
assinaturas, comunicador, páginas frontend). Inclui também o landing
público `/pricing` redesenhado.

## Controle de acesso

- **Middleware `superadmin`** (`App\Http\Middleware\Superadmin`):
  permite passagem só pra usuários cujo `username` ∈ `explode(',',
  config('constants.administrator_usernames'))`. Default WR23 via env
  `ADMINISTRATOR_USERNAMES`. Resto recebe `abort(403)`.
- Não usa `spatie/laravel-permission`. Allowlist por username é
  intencional (mantém isolamento da WR2 mesmo se uma role vazar).

## Superfícies relevantes

### Pública

- `GET /pricing` → `PricingController@index` (renderiza
  `superadmin::pricing.index` com `packages` + `permission_formatted`).
- `GET /page/{slug}` → `PageController@showPage` (CMS leve).

### Superadmin (`web + auth + language + AdminSidebarMenu + superadmin`, prefixo `/superadmin`)

- `GET /` → dashboard
- `GET /stats`
- `resource('business')` + `GET /business/{id}/destroy`
- `GET /{business_id}/toggle-active/{is_active}`
- `GET /users/{business_id}` + `POST /update-password`
- `resource('packages')`
- `GET/PUT /settings`
- `resource('superadmin-subscription')` + `editSubscription/updateSubscription`
- `GET/POST /communicator` + history
- `resource('frontend-pages')`
- `GET /portainer` (redirect para http://jana.wr2.com.br:9000/)
- `GET /painel` (redirect para painel.wr2.com.br:7800)

### Subscriptions (`web + auth`, sem prefixo)

`/subscription/{package_id}/pay`, `/confirm`, `/paypal-express-checkout`,
`/pesapal-callback`, `/all-subscriptions`, `resource('/subscription')`.

## Riscos regressivos conhecidos

1. Remover middleware `superadmin` em qualquer rota = vazamento total
   do painel WR2 (qualquer business owner acessa todos os tenants).
2. `/pricing` virar privada quebra captação (lead público).
3. `ADMINISTRATOR_USERNAMES` sem fallback no env = nenhum acesso.

## Cobertura de testes (batch 7)

- `tests/Feature/Modules/Superadmin/SuperadminAccessTest.php`
- `tests/Feature/Modules/Superadmin/PricingTest.php`

Filtro: `vendor/bin/pest --filter=Superadmin`

## Recomendação

**MANTER.** Módulo central, em uso, redesign de pricing recente
(handoff sessões 0023-0024). Vale endurecer permissionamento futuramente
com `permission` ou Gate dedicada, mas allowlist por username é simples
e funciona hoje.
