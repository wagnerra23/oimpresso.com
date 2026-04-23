# ADR ARQ-0005 (DocVault) · Observabilidade — Monolog v3 + Sentry

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner, Claude
- **Categoria**: arq
- **Desbloqueia**: ADR arq/0002 Fase 2 (monolog v2→v3 requirement)

## Contexto

Laravel 10 exige `monolog/monolog ^3.0`. Nosso sistema usa v2.9.1. Upgrade é transitivo (automático quando Laravel 10 instala).

Porém, Wagner observou: "monolog tem outro que é bem melhor". Não há substituto direto pro monolog (ele é a lib de logging padrão do Laravel, PSR-3 compliant). O que existe é **complementar**: ferramentas de observabilidade que sobem um nível — capturam erros, rastreiam performance, enviam alertas.

## Decisão

**Duas mudanças independentes:**

### Parte A — Monolog v3 (upgrade transitivo automático)

Ao bumpar `laravel/framework` pra ^10.0, monolog v3 entra automaticamente. Breaking changes reais são mínimos pro nosso código:

- `Monolog\Handler\*Handler::handleBatch()` agora aceita `array` tipado (no nosso código só chamamos via Logger facade, zero impacto).
- `Monolog\Formatter\*` — a mesma API.
- Channels em `config/logging.php` — já funciona com v2 e v3.

**Ação**: nada a fazer manualmente. Ficar atento durante `composer update`.

### Parte B — Adotar **Sentry** como camada de observabilidade

Sentry (`sentry/sentry-laravel`) é o padrão da indústria pra:
- **Error tracking**: captura exceções com stack trace + contexto (user, request, session, breadcrumbs)
- **Performance monitoring**: spans de rotas lentas, queries N+1, jobs demorados
- **Alertas**: email/Slack quando erro novo aparece ou taxa de erro sobe
- **Release tracking**: correlaciona bugs com deploy específico

**Não substitui monolog** — complementa. Monolog continua escrevendo em `storage/logs/laravel.log`, Sentry manda pro dashboard externo.

### Plano de instalação

```bash
# 1. Instalar
composer require sentry/sentry-laravel

# 2. Publicar config
php artisan sentry:publish --dsn=https://...@sentry.io/...

# 3. Testar
php artisan sentry:test
```

No `.env`:
```
SENTRY_LARAVEL_DSN=https://...@sentry.io/...
SENTRY_TRACES_SAMPLE_RATE=0.2   # 20% das requests pra performance (ajustar)
SENTRY_ENVIRONMENT=local         # ou production
```

Integra automaticamente com o handler de exceções do Laravel 9+. Zero linha de código adicional.

### Privacidade (LGPD)

Sentry captura request/session por default. Configurar scrubbing pra não vazar:
- `business.id` pode enviar (ID numérico, não PII)
- `user.name`, `user.email` **não** enviar em eventos
- PIS, CPF — **nunca** enviar (Portaria 671/2021 + LGPD Art. 5º II)

Fazer via `config/sentry.php`:
```php
'send_default_pii' => false,
'before_send' => fn ($event, $hint) => app(\App\Services\SentryScrubber::class)->scrub($event),
```

## Consequências

**Positivas:**
- Diagnóstico de bugs em produção acelera de "olhar grep no laravel.log" pra "clicar no dashboard Sentry".
- Captura erros que nunca chegam a bilhetes (ex: `view:clear` miss em produção).
- Breadcrumbs mostram sequência de ações do user antes da exception.
- Integra com DocVault: quando erro aparece, pode cruzar com `docs_pages` pra ver qual tela/módulo afetou.

**Negativas:**
- Custo: Sentry SaaS free tier cobre ~5k erros/mês. Acima disso, plano pago (~$26/mês).
- Alternativa self-hosted: `sentry.io` open-source, precisa docker + infra.

## Alternativas consideradas

| Ferramenta | Escolha | Razão |
|---|---|---|
| **Sentry** | ✅ adotar | Padrão mercado, Laravel SDK maduro, free tier generoso |
| Bugsnag | ❌ | Similar a Sentry, menos integração Laravel nativa |
| Rollbar | ❌ | Cara, UI datada |
| Laravel Pulse | ⏳ futuro | Só Laravel 11+ — entra na Fase 4 |
| Flare (by Spatie) | ❌ | Similar, mas só error tracking (sem APM) |
| Papertrail / BetterStack | ❌ | Só logs, sem error tracking com stack trace |
| **Só monolog** | ❌ | Grep em arquivo não escala, sem alertas |

## Ordem de implementação

Independente da Fase 2 do Laravel 10:
- Sentry pode entrar **hoje** (funciona em Laravel 9+)
- OU pode esperar Laravel 10 estável (menos pontos de variação simultaneamente)

Recomendação: **entrar primeiro em produção Laravel 9.51**. Quando upgrade acontecer, Sentry já está coletando baseline. Comparar antes/depois fica fácil.

## Sinais de conclusão

- [x] `composer require sentry/sentry-laravel` feito (sentry-laravel ^4.25 instalado em Laravel 9.52.4)
- [x] `vendor:publish` gerou `config/sentry.php`
- [x] `.env` com `SENTRY_LARAVEL_DSN=` (vazio — aguardando conta), `SENTRY_TRACES_SAMPLE_RATE=0.2`, `SENTRY_SEND_DEFAULT_PII=false`
- [ ] **Wagner cria conta em sentry.io e preenche DSN**
- [ ] `php artisan sentry:test` manda evento teste (após DSN preenchido)
- [ ] Dashboard Sentry mostra eventos reais da semana
- [ ] `before_send` configurado pra scrubbing LGPD adicional (opcional — `send_default_pii=false` já cobre a maior parte)

## Estado da instalação (2026-04-22)

- **Pacote**: `sentry/sentry-laravel ^4.25` no composer.json ✅
- **Config**: `config/sentry.php` com defaults OK pra LGPD (pii=false) ✅
- **Integração**: ExceptionHandler do Laravel 9 integra automaticamente via ServiceProvider ✅
- **DSN**: vazio no `.env` — sem efeito até preencher ✅ (não quebra nada)

Quando DSN for preenchido, Sentry passa a capturar exceptions automaticamente. Zero código adicional necessário pro fluxo básico.
