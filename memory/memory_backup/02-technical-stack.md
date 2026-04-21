# 02 — Stack Técnica

## Linguagem e framework

| Item | Versão real na instância | Razão |
|---|---|---|
| PHP | **8.3** | Atualizado em 2026-04-21 junto com UltimatePOS 6.7 |
| Laravel | **9.51** | UltimatePOS v6.7 roda Laravel 9 |
| MySQL | 8.0+ | Triggers de imutabilidade exigem recursos modernos |
| Redis | 7.x | Filas (Horizon), cache de sessão |

> ✅ **Stack atualizada em 2026-04-21 (sessão 09):** UltimatePOS foi atualizado de v6 (Laravel 5.8 + PHP 7.1) para **v6.7 (Laravel 9.51 + PHP 8.3)**. Toda a restrição de sintaxe PHP 7.1 foi removida. O módulo PontoWR2 foi desativado no servidor por incompatibilidade e precisa ser adaptado para a nova stack.

> ~~⚠️ **Atenção — descoberta de 2026-04-19 (sessão 05):** este arquivo dizia "PHP 8.1+ / Laravel 10" mas a verdade é Laravel 5.8 + PHP 7.1.3 (mínimo).~~ — **Superado pela atualização de 2026-04-21.**

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
