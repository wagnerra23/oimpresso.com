---
status: accepted
date: 2026-05-28
deciders: wagner
consulted: claude (incident response 2026-05-28 10:11→13:22)
related: [0093, 0212]
---

# ADR 0215 — Deploy webhook Hostinger deve rodar `composer dump-autoload -o` + `php artisan optimize` após `git pull`

## Status

Accepted — 2026-05-28 pós-incident produção.

## Contexto

PR #1852 (`feat(infra): LogContextMiddleware global — Onda 1.3 (US-INFRA-016, ADR 0212 Camada 1)`) foi mergeado em `main` e deployado em prod Hostinger às **10:11 BRT** via webhook GitHub. O middleware foi registrado em `app/Http/Kernel.php` linha 43 (`\App\Http\Middleware\LogContextMiddleware::class`) e o arquivo PHP `app/Http/Middleware/LogContextMiddleware.php` foi puxado no `git pull` corretamente.

**Mas** o arquivo `vendor/composer/autoload_classmap.php` ficou stale. O Laravel container tentou resolver `App\Http\Middleware\LogContextMiddleware` em **TODA** request web (middleware group `web`), recebia `BindingResolutionException: Target class does not exist`, e devolvia **HTTP 500** pra `/`, `/sells`, `/contacts`, `/business/register` e demais rotas.

### Timeline

| Hora BRT | Evento |
|---|---|
| 10:11 | Webhook puxa commit `291f94409` (PR #1852). Files mudados, autoload classmap intocado. |
| 10:19–10:20 | Wagner tenta acessar `/`. Erros `BindingResolutionException` logados (8 entries em 4s). |
| 10:20:38 | Último erro logado. **LiteSpeed opcache passa a memorizar 500 sem log novo.** |
| 10:20:54 | Última request 2xx no log (`connector.delphi.check_update` — endpoint API que não passa pelo middleware web). |
| **10:20:54 → 13:22** | **2h31min de downtime invisível.** Todas requests web em 500. Zero logs novos porque o erro acontecia antes do logger inicializar (Kernel boot). |
| 13:17 | Claude tenta smoke prod a pedido Wagner. Detecta 500 generalizado via `curl -I`. |
| 13:22 | Claude SSH Hostinger: `php artisan optimize:clear` + `composer dump-autoload -o`. `vendor/composer/autoload_classmap.php` regenerado, classe resolvida, prod volta 200. |

### Por que invisível

1. **APP_DEBUG=false** em prod → página genérica "500 Server Error" sem stack.
2. **Erro no boot do middleware web** → roda ANTES do log writer parsear a request. Após primeiros 8 logs de exception (10:19–10:20), opcache memorizou a falha e parou de re-tentar resolver → parou de logar.
3. **Healthcheck endpoint `/up`** não existe (404), então monitoramento externo Pingdom/UptimeRobot ainda não detecta.
4. **Wagner não recebeu alerta** porque infra de alertas (Onda 1.4 ADR 0212 Camada 2) ainda não está pronta.

### Custo direto do incident

- 2h31min de prod indisponível ao único usuário ativo do horário (sessão biz=1 Wagner).
- Larissa biz=4 não rodou venda nesse intervalo (mas como ela usa principalmente após 14h, dano operacional ≈ zero).
- Risco reputacional alto se tivesse acontecido em horário comercial pesado.

## Decisão

Adicionar ao script de deploy Hostinger (atual: `~/domains/oimpresso.com/git-pull.sh` ou equivalente disparado pelo webhook GitHub) os seguintes passos **obrigatórios após `git pull`**:

```bash
# 1. Atualizar classmap composer — OBRIGATÓRIO quando arquivos novos em app/, Modules/, database/
composer dump-autoload --optimize --no-scripts --no-interaction

# 2. Limpar caches Laravel stale
php artisan optimize:clear

# 3. Recompilar caches Laravel (config + routes + events + views)
php artisan optimize

# 4. Health probe — falha o deploy se rota raiz não responder 2xx ou 3xx
curl -sf -o /dev/null -w '%{http_code}' https://oimpresso.com/ \
  | grep -qE '^(2|3)' \
  || (echo 'DEPLOY HEALTH PROBE FAILED' && exit 1)
```

Justificativa pra `--no-scripts`:

`composer dump-autoload` sem `--no-scripts` dispara post-autoload-dump scripts do Laravel framework. Em prod Hostinger, o script `Open\Telemetry\Contrib\Instrumentation\Laravel\_register.php` exige a extensão PHP `opentelemetry` carregada — que não está disponível no shared hosting. Sem `--no-scripts`, o `dump-autoload` aborta com fatal error e o autoload_classmap NÃO é regravado.

Foi exatamente isso que travou a tentativa inicial de recovery em 13:21 BRT (regressão confirmada no log da sessão de incident).

## Alternativas consideradas

### A) Manter status quo (deploy só `git pull`)

❌ Rejeitado — incident 2026-05-28 prova que classmap stale crasha prod inteiro toda vez que merge tocar `app/Http/Middleware/`, `app/Console/Commands/`, `Modules/<X>/`, `database/factories/`, ou qualquer outro path PSR-4 classmap-dependente. Esse incident teria se repetido em 100% dos próximos PRs do gênero.

### B) PSR-4 puro sem classmap

❌ Rejeitado — `composer install --optimize-autoloader` é canon Laravel produção. Sem classmap o autoload faz `file_exists()` por classe a cada cold-start FPM worker → degrada p95.

### C) Pipeline CI/CD remoto (GitHub Actions → SCP)

🟡 Adiado pra ADR futura — exige refator do fluxo deploy atual + secret management pesado pra Hostinger. Sai do escopo deste incident.

### D) Healthcheck endpoint Laravel `/up` exposto

✅ Aceito como parte da Decisão (step 4 acima usa `/` por enquanto). ADR futura pode trocar pra `/up` próprio com payload `{db: ok, cache: ok, autoload: <md5 classmap>}`.

## Consequências

### Positivas

- Próximos PRs que adicionarem classe nova em path classmap-dependente NÃO crasham prod.
- Healthcheck final detecta falha **antes** do webhook reportar "deploy ok".
- Tempo de deploy aumenta ~3s (dump-autoload + optimize) — aceitável.
- `--no-scripts` evita opentelemetry blocker (gap conhecido shared hosting).

### Negativas

- Deploy webhook fica menos atômico — se `dump-autoload` falhar, código novo está em disk mas autoload aponta pra classmap antigo. Mitigação: step 4 health probe + alerta pra Wagner.
- `optimize:clear` invalida session config cache → microsegundos de cold-start por worker FPM no primeiro request.

### Neutras

- Logs de prod precisam de Camada 2 ADR 0212 (alerting) pra capturar incidents que mascarem em opcache. Sem isso, mesmo com este ADR, se outro tipo de boot-fail acontecer, a invisibilidade pós-primeiros-8-logs continua.

## Skill afetada

`runtime-rules-hostinger-ct100` — adicionar bullet "deploy webhook DEVE rodar `composer dump-autoload --optimize --no-scripts`".

`incident-done-checklist` — adicionar "rodar health probe `curl -sf /` antes de marcar deploy `done`".

## Evidência

- Log Laravel prod: `[2026-05-28 10:19:34] live.ERROR: Target class [App\Http\Middleware\LogContextMiddleware] does not exist.` — 8 entries idênticas até 10:20:38.
- `vendor/composer/autoload_classmap.php` timestamp pré-fix: stale do deploy anterior.
- Pós-fix `composer dump-autoload -o --no-scripts` às 13:22: timestamp `May 28 13:22`, contém `LogContextMiddleware`, prod retorna 200.
- Última 2xx web pré-incident: `connector.delphi.check_update` 10:20:54.
- Primeira 2xx web pós-fix: `curl -I /` 13:22 BRT após dump-autoload.

## Refs

- PR causador: [#1852](https://github.com/wagnerra23/oimpresso.com/pull/1852)
- [ADR 0212 — defensive-logging-fallback-paths](0212-defensive-logging-fallback-paths.md) (origem do middleware)
- [ADR 0093 — multi-tenant-isolation-tier-0](0093-multi-tenant-isolation-tier-0.md)
- Skill `runtime-rules-hostinger-ct100`
- Skill `incident-done-checklist`
