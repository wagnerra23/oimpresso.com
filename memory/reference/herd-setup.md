# Herd Windows — setup PHP 8.4 dev oimpresso

> Lifecycle: active · Owner: [W] · Tags: [herd, php, windows, dev-env, opentelemetry, observability]
> Última atualização: 2026-05-25 (US-GOV-011 — extension OTel)

## Visão geral

Laravel Herd ([herd.laravel.com](https://herd.laravel.com)) é o ambiente Wagner dev local (Windows 11 Home). Substitui XAMPP/WAMP/Laragon. Gerencia múltiplas versões PHP isoladamente.

## Layout filesystem (referência)

| Path | Função |
|---|---|
| `C:\Users\wagne\.config\herd\bin\php.bat` | Launcher (delega pra versão default — hoje 8.4) |
| `C:\Users\wagne\.config\herd\bin\php84\php.exe` | Binário PHP 8.4 (NTS, x64, VS17, API20240924) |
| `C:\Users\wagne\.config\herd\bin\php84\php.ini` | Config principal (~71 KB stock) |
| `C:\Users\wagne\.config\herd\bin\php84\ext\` | DLLs de extensões (custom + bundled) |
| `C:\Users\wagne\AppData\Roaming\Herd\` | Logs + cache + storage Herd |

**Importante:** `php --ini` retorna `Configuration File (php.ini) Path: <vazio>` mas `Loaded Configuration File:` mostra o php.ini real ativo. Sempre conferir `php --ini` antes de editar.

## Build PHP atual

Confirme com `php -i | findstr /R "Thread Architecture Build Compiler"`:

```
Compiler => Visual C++ 2022
Architecture => x64
PHP Extension Build => API20240924,NTS,VS17
Thread Safety => disabled    ; (= NTS Non Thread Safe)
```

Necessário pra **escolher DLL compatível** ao baixar do PECL — qualquer mismatch (TS vs NTS, x86 vs x64, VS16 vs VS17) gera falha silenciosa no load com warning ou crash do CLI.

## Como instalar uma extension custom (padrão)

1. Baixar DLL do [PECL Windows](https://pecl.php.net/package/<ext>) ou [PHP Extension Repository](https://phpext.phptools.online/) — escolher zip casando `PHP X.Y / NTS / VSnn / x64`
2. Extrair `php_<ext>.dll` em `C:\Users\wagne\.config\herd\bin\php84\ext\`
3. Adicionar linha `extension=<ext>` no `php.ini` (sem prefixo `php_` e sem sufixo `.dll`)
4. Reiniciar serviços Herd (FrankenPHP + Nginx) — CLI já pega na próxima invocação
5. Validar: `php -m | findstr <ext>` e `php --ri <ext>`

> Ref oficial: [Herd Windows · PHP Extensions](https://herd.laravel.com/docs/windows/technology/php-extensions)

## Extensions instaladas pós-setup base (snapshot 2026-05-25)

Bundled pelo Herd (padrão):
`bcmath, bz2, calendar, ctype, curl, date, dom, exif, FFI, fileinfo, filter, gd, gmp, hash, herd, iconv, intl, json, libxml, mbstring, mongodb, mysqli, mysqlnd, openssl, pcre, PDO, pdo_mysql, pdo_pgsql, pdo_sqlite, pgsql, Phar, random, readline, redis, Reflection, session, shmop, SimpleXML, soap, sockets, sodium, SPL, sqlite3, standard, tokenizer, xml, xmlreader, xmlwriter, Zend OPcache, zip, zlib`

Custom adicionadas:
- **opentelemetry** 1.2.1 (2026-05-25 · US-GOV-011) — auto-instrumentation Laravel via [`open-telemetry/opentelemetry-auto-laravel`](https://github.com/open-telemetry/opentelemetry-auto-laravel)

## US-GOV-011 — install OpenTelemetry extension (2026-05-25)

### Sintoma original

`php artisan module:grade --all` emitia warning na 1ª linha em **todos os 36 módulos**:

```
PHP Warning: The opentelemetry extension must be loaded in order to autoload the
OpenTelemetry Laravel auto-instrumentation in
D:\oimpresso.com\vendor\open-telemetry\opentelemetry-auto-laravel\_register.php on line 13
```

D9.b Observability degradada → impactava nota de **TODOS** os módulos. ROI maior do projeto (0.5h IA-pair → +2-3pp média 36 módulos).

### Passo 1 — Baixar DLL compatível

Source PECL release 1.2.1 (stable 2025-10-02), zip oficial PHP 8.4 NTS x64 VS17:

```
https://downloads.php.net/~windows/pecl/releases/opentelemetry/1.2.1/php_opentelemetry-1.2.1-8.4-nts-vs17-x64.zip
```

Script idempotente em [`scripts/install-otel-ext.ps1`](../../scripts/install-otel-ext.ps1) (não versionado no repo principal, mantido neste worktree). Reusável caso suba PHP minor (ex: 8.5) no futuro.

### Passo 2 — Editar php.ini

Backup automático: `php.ini.bak-2026-05-25-otel`.

Bloco adicionado **logo após** `zend_extension=opcache` (~linha 951):

```ini
extension=opentelemetry

; ATENCAO: valores precisam estar entre aspas — bug arquitetural conhecido
; em get_cfg_var() converte "true" -> "1" e BooleanParser nao aceita.
; Ref: https://github.com/open-telemetry/opentelemetry-php/issues/1431
[opentelemetry]
OTEL_PHP_AUTOLOAD_ENABLED="true"
OTEL_SERVICE_NAME="oimpresso-dev"
OTEL_TRACES_EXPORTER="none"
OTEL_METRICS_EXPORTER="none"
OTEL_LOGS_EXPORTER="none"
```

**Por que `exporter=none` em dev:** auto-instrumentation funciona normalmente (gera spans em memória); sem collector OTLP local, exporter=none evita warnings de conexão recusada e overhead I/O. Produção (CT 100 Proxmox) sobrescreve via env vars do OS no `.env` do servidor.

### Passo 3 — Validação

| Check | Antes | Depois |
|---|---|---|
| `php -m \| findstr opentelemetry` | (vazio) | `opentelemetry` |
| `php --ri opentelemetry` | `Extension 'opentelemetry' not present` | `extension version => 1.2.1` |
| `php artisan module:grade Compras` 1ª linha stderr | `Warning: The opentelemetry extension must be loaded...` | (sem warning) |
| `php artisan module:grade --all` em 36 módulos | warning x36 | (sem warning) |

### Pitfall conhecido (bug upstream)

`OTEL_PHP_AUTOLOAD_ENABLED=true` (sem aspas) gera warning:

```
OpenTelemetry: [warning] Invalid boolean value "1" interpreted as "false" for OTEL_PHP_AUTOLOAD_ENABLED
```

Causa: `get_cfg_var()` do PHP retorna `"1"` quando ini value é `true`/`on`/`yes`. SDK OpenTelemetry só aceita literal `"true"`. **Fix:** sempre quotar valores OTel no `[opentelemetry]` section. Tracking: [opentelemetry-php#1431](https://github.com/open-telemetry/opentelemetry-php/issues/1431) + [#1442](https://github.com/open-telemetry/opentelemetry-php/issues/1442).

### Impacto produção (CT 100)

⚠️ **Esta mudança é DEV-ONLY** (Herd Windows local Wagner). Não afeta runtime CT 100 FrankenPHP ([ADR 0058](../decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md)) — lá a extension deve ser ativada separadamente quando módulo `Modules/Observability` (se existir) consumir spans. Hoje não consome — auto-instrumentation gera spans mas exporter=none em prod até collector OTLP estar no CT 100.

### Rollback

```powershell
Copy-Item 'C:\Users\wagne\.config\herd\bin\php84\php.ini.bak-2026-05-25-otel' `
          'C:\Users\wagne\.config\herd\bin\php84\php.ini' -Force
Remove-Item 'C:\Users\wagne\.config\herd\bin\php84\ext\php_opentelemetry.dll' -Force
```

## Troubleshooting Herd geral

| Sintoma | Causa provável | Fix |
|---|---|---|
| `php` no PowerShell aponta versão errada | `PATH` lista outra instância PHP antes do Herd | Reordenar PATH ou invocar caminho absoluto `C:\Users\wagne\.config\herd\bin\php84\php.exe` |
| Extension carregada no CLI mas não no Nginx Herd | FrankenPHP/PHP-FPM precisa restart | Herd UI → Restart Services |
| DLL não carrega + sem erro visível | Mismatch TS/NTS ou arch | Confirmar `php -i \| findstr Build` retorna `NTS,VS17,x64` antes de baixar DLL |

## Refs externas (2026)

- [Laravel Herd · PHP Extensions Windows](https://herd.laravel.com/docs/windows/technology/php-extensions)
- [PECL OpenTelemetry releases](https://pecl.php.net/package/opentelemetry)
- [open-telemetry/opentelemetry-php-instrumentation (extension source)](https://github.com/open-telemetry/opentelemetry-php-instrumentation)
- [opentelemetry-php#1431 — ini boolean bug](https://github.com/open-telemetry/opentelemetry-php/issues/1431)
- [opentelemetry-php#1442 — arquitetura PhpIniAccessor](https://github.com/open-telemetry/opentelemetry-php/issues/1442)
- [OpenTelemetry PHP zero-code instrumentation](https://opentelemetry.io/docs/zero-code/php/)
- [Uptrace 2026 — OpenTelemetry Integration for Laravel](https://uptrace.dev/guides/opentelemetry-laravel)
