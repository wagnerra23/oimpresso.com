# 02 — Stack Técnica

## Linguagem e framework

| Item | Versão real na instância | Razão |
|---|---|---|
| PHP | **8.4.20** (Herd) | Atualizado em 2026-04-22 (Herd local) |
| Laravel | **13.6** | Upgrade em cascata 9→10→11→12→13 na sessão 13 (2026-04-23) |
| MySQL | 8.0+ | Triggers de imutabilidade exigem recursos modernos |
| Redis | 7.x | Filas (Horizon), cache de sessão |

> ✅ **Stack atualizada em 2026-04-23 (sessão 13):** upgrade em cascata Laravel 9.51 → 10.50 → 11.51 → 12.57 → **13.6** em 5 milestones isolados no mesmo dia. Cada salto validado com Pest + crawler browser (99 tests, zero shim regressions).
>
> L13 foi desbloqueado ao inlinear `knox/pesapal` em `app/Vendor/Pesapal` (576 LOC) — upstream não tem versão L13 stable. `arcanedev/log-viewer` e `barryvdh/laravel-debugbar` removidos (sem compat L13; reinstalar quando upstream publicar).

## Helpers / UI

| Item | Versão | Notas |
|---|---|---|
| `spatie/laravel-html` | **^3.13** | Substituiu `laravelcollective/html` (removido em sessão 13). Acesso via shim `App\View\Helpers\Form` pra preservar API `Form::text`, `Form::select`, etc. nas ~6.433 chamadas em ~460 Blade views. |
| `inertiajs/inertia-laravel` | **^2.0** | Bumpado em L13 (antes era ^1.3 / teto L12) |
| `laravel/passport` | **^13.0** | Bumpado em L13 |
| `laravel/tinker` | **^3.0** | Bumpado em L13 |
| `yajra/laravel-datatables-oracle` | **^13.0** | |
| `barryvdh/laravel-dompdf` | ^3.0 | |
| `spatie/laravel-backup` | ^10.0 | v10 mudou `disks` string → array em `config/backup.php` |
| `nwidart/laravel-modules` | ^10.0 | |
| `App\Vendor\Pesapal` | inline | 576 LOC copiados de knox/pesapal (removido). Namespace renomeado. Gateway Pesapal continua funcional. |

## Testing

| Item | Versão | Notas |
|---|---|---|
| `pestphp/pest` + `pest-plugin-laravel` | **^4.0** | Bumpado em L13 |
| `phpunit/phpunit` | **^12.0** | Bumpado em L13 |
| CI/CD | `.github/workflows/ci.yml` | Pest Unit + Vite build |
| Suite atual | **99 Pest tests** | 26 Form shim + 73 crawler de rotas |

## IA

| Item | Status |
|---|---|
| `openai-php/laravel` | ❌ REMOVIDO em sessão 12 |
| Vizra ADK + Prisma | 🟡 Planejado — substituirá OpenAI como motor de IA |

## Sintaxe PHP agora DISPONÍVEL (PHP 8.3)

Com PHP 8.3 + Laravel 9, **todo o seguinte está liberado:**

- ✅ Constructor Property Promotion (`public function __construct(protected X $y)`)
- ✅ Argumentos nomeados (`foo(nome: $x)`)
- ✅ Null-safe operator (`$a?->b?->c`)
- ✅ Typed properties (`private array $parsers = [...]`)
- ✅ Arrow functions (`fn ($x) => ...`)
- ✅ `match (x) { ... }`
- ✅ `readonly` em propriedades
- ✅ Spread em arrays `[...$a, ...$b]`
- ✅ Null coalescing assignment `??=`
- ✅ Atributos `#[...]`
- ✅ Enums
- ✅ Fibers (PHP 8.1+)
- ✅ `never` return type

## Features Laravel 9 agora DISPONÍVEIS

- ✅ `$table->id()`, `$table->foreignId()->constrained()`
- ✅ `return new class extends Migration` (anonymous migrations)
- ✅ `HasFactory` trait nos models
- ✅ `Attribute::make(...)` para accessors/mutators modernos
- ✅ `auth:sanctum` (disponível — mas verificar se Passport ainda é padrão do UltimatePOS)
- ✅ `[Controller::class, 'method']` em rotas
- ✅ `Route::controller(X::class)->group(...)` 
- ✅ Implicit Enum binding nas rotas
- ✅ `scoped()` para rotas com resource aninhado

## nWidart/laravel-modules

- **Versão antiga (pre-upgrade):** 5.1.0 — padrão Jana (`start.php`, `Http/routes.php`, sem RouteServiceProvider)
- **Versão nova (Laravel 9):** provavelmente v9.x ou v10.x — verificar no `composer.json` do servidor
- ⚠️ **A API de carregamento de rotas mudou** — provavelmente usa `Routes/` separado com `RouteServiceProvider` agora
- Verificar padrão atual inspecionando módulos existentes (`Jana`, `Repair`, `Project`) no servidor atualizado

## Arquitetura modular

- **nWidart/laravel-modules** — padrão adotado pelo UltimatePOS Essentials. Cada módulo vive em `Modules/NomeModulo/`.
- Nosso módulo: `Modules/PontoWr2/`

## Bibliotecas PHP (composer)

| Pacote | Uso |
|---|---|
| `spatie/laravel-permission` | RBAC — permissões granulares |
| `spatie/laravel-activitylog` | Auditoria de ações |
| `maatwebsite/excel` | Importação/exportação CSV/XLSX |
| `barryvdh/laravel-dompdf` | Espelho de ponto em PDF (verificar compatibilidade versão) |
| `nfephp-org/sped-esocial` | (a adicionar) eventos eSocial |

## Frontend

- **Blade + AdminLTE + jQuery** — padrão UltimatePOS (verificar versão AdminLTE no 6.7)
- **React + Inertia** — possível no 6.7, verificar se UltimatePOS adotou

## Mobile

- **React Native + Expo** — app de marcação do colaborador
- **SQLite local** — offline-first, sync quando online

## Infraestrutura

- **Hostinger Cloud** — produção atual
- **Laravel Horizon** — filas (import AFD, reapuração, notificações)

## Autenticação e autorização

- **Laravel Passport ou Sanctum** — verificar qual o UltimatePOS 6.7 usa
- **Web guard padrão** — sessão web via middleware UltimatePOS
- **spatie/laravel-permission** — RBAC (já instalado pelo core)
- Escopo multi-empresa via `business_id` em todas as tabelas

## Certificado digital

- **ICP-Brasil A1** (.pfx) — cada empresa tem o seu
- **PKCS#7** para assinatura de marcações

---

## Quando adicionar uma nova dependência

1. Abra ADR em `memory/decisions/` com nome `NNNN-adopt-<package>.md`
2. Liste alternativas consideradas
3. Atualize este arquivo
4. Atualize `composer.json` do módulo

---

**Última atualização:** 2026-04-21 (sessão 09 — UltimatePOS atualizado para v6.7, Laravel 9.51, PHP 8.3; módulo PontoWR2 desativado por incompatibilidade — requer adaptação)
