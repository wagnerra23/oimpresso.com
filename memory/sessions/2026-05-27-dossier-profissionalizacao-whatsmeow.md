# Dossier · Profissionalização Whatsmeow (pós-pareamento manual)

> **Status:** PROPOSED · aguardando aceite Wagner em ≤10 min
> **Companheiro ADR:** [`memory/decisions/proposals/0205-state-machine-whatsmeow-reconciliacao.md`](../decisions/proposals/0205-state-machine-whatsmeow-reconciliacao.md)
> **Autoria:** audit-senior-expert (opus-4.7) · 2026-05-27 19:00–20:30 BRT · PT-BR
> **Sinal qualificado Wagner ([ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)):**
> > "isso tem que ser automatizado, nada de fazer manualmente. crie sistema de controle de erros. profissionalize"

## TL;DR executável (180s)

Sessão 2026-05-27 (ADR 0204) entregou daemon Go whatsmeow substituto Baileys. Wagner conseguiu parear `Jana` + `Suporte` mas **5 bugs sequenciais exigiram workaround manual**. Mais 4 débitos catalogados de infraestrutura. Total: **9 débitos**.

| # | Débito | Severidade | Impacto | Fix |
|---|---|---|---|---|
| 1 | ENUM `channels.type` sem `whatsapp_whatsmeow` | **P0** | UI não mostra botão Conectar | Migration Fase A |
| 2 | `business.uuid` coluna ausente | **P0** | webhook multi-tenant quebrado | Migration + Trait Fase A |
| 3 | `Http::withToken` injeta `Bearer ` (WuzAPI rejeita) | **P0** | 100% requests 401 | PR #1787 mergeado, falta Pest guard Fase E |
| 4 | `/session/connect` 500 "already connected" sem reconcile | **P0** | usuário trava | Reconciler Fase B |
| 5 | QR PNG em `public/qr-suporte-temp.png` (gambiarra) | **P0** | URL pública sem auth, qualquer um vê | UI inline base64 Fase D |
| 6 | `asternic/wuzapi:latest` sem pin SHA digest | P1 | rebuild surpresa quebra prod | docker-compose Fase A |
| 7 | Sem cron backup `/srv/docker/whatsapp-whatsmeow/sessions/` | P1 | perder volume = re-pair todos channels | Restic cron Fase C |
| 8 | Sem retry/circuit breaker + sem OTel spans | P1 | falha transitória vira erro permanente, observability cega | Macro Http + spans Fase C |
| 9 | Sem alarme daemon banned/disconnected | P2 | silêncio até cliente reclamar | Health probe expandido + alerta Fase C |

**Decisão arquitetural escolhida** (alternativas em §3):
- **State Machine via Reconciler service** (sem lib externa — custom thin layer)
- **Retry + circuit breaker via macro `Http::whatsmeow()` custom** + cache state (sem package)
- **OTel spans inline** via helper já existente `OtelHelper::span()`
- **Polling Inertia 2s** via `usePoll` (real-time WebSocket fica review_trigger volume > 50 channels)

**Esforço total (recalibrado [ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) 10x + margem 2x):**

| Fase | Wagner-h | IA-pair-h | Relógio |
|---|---|---|---|
| A · Migrations canônicas | 0.5 | 2-4 | 1 dia |
| B · State Machine + Reconciler | 1 | 4-6 | 2 dias |
| C · Retry / circuit breaker / OTel | 1 | 3-5 | 2 dias |
| D · UI fluxo end-to-end | 1 | 3-5 | 2 dias |
| E · Pest + Runbook | 0.5 | 2-4 | 1 dia |
| **TOTAL** | **~4h** | **14-24h** | **~8 dias** |

**Pré-flight crítico (NÃO disparar implementadores antes):**
1. Wagner aceita ADR proposta 0205 (≤10 min de leitura)
2. Backup pré-migration: `mysqldump --tables business channels` em Hostinger
3. CT 100 daemon whatsmeow está UP — confirmar `curl https://whatsapp-whatsmeow.oimpresso.com/health`

**Sequência recomendada:** A (migrations) → B+C+D (paralelo seguro, áreas isoladas) → E (Pest sela tudo). Detalhe §7.

**Surpresa estratégica descoberta:** WuzAPI **NÃO documenta erro "already connected"** ([issue #131](https://github.com/asternic/wuzapi/issues/131) confirma bug de sync). Solução canônica é **chamar `GET /admin/users` ANTES de provision/connect**, não `POST /session/connect` defensivo. Reconciler vira o padrão mestre, não o connect bruto.

---

## §1 · Catálogo completo dos 9 débitos

### Débito 1 — ENUM `channels.type` sem `whatsapp_whatsmeow` (P0)

**Sintoma observado:** Wagner não viu botão Conectar na UI `/atendimento/canais`. Frontend (`Atendimento/Channels/Index.tsx`) checa `channel.type === 'whatsapp_whatsmeow'` — false negativo.

**Root cause:** Agente que implementou ADR 0204 adicionou `Channel::TYPE_WHATSAPP_WHATSMEOW = 'whatsapp_whatsmeow'` na entity ([`Modules/Whatsapp/Entities/Channel.php:82`](../../Modules/Whatsapp/Entities/Channel.php)) mas **esqueceu migration** alterando `channels.type` ENUM. Migration original em `2026_05_11_000001_create_omnichannel_tables.php` linha 42-51 só tem 8 valores. MySQL recebe valor não-listado e silenciosamente converte pra `""` em strict mode permissivo, ou rejeita em strict — depende de `sql_mode`.

**Impacto:**
- Wagner abriu channel novo, `type` salvou vazio ou rejeitou ao tentar
- UI nunca renderiza botão Conectar (filter por type)
- Reproduzível em qualquer ambiente fresh

**Fix manual aplicado (provisório, não-canon):**
```sql
ALTER TABLE channels MODIFY COLUMN type ENUM(
    'whatsapp_meta','whatsapp_zapi','whatsapp_baileys','whatsapp_whatsmeow',
    'instagram','messenger','email_imap','email_smtp','mercadolivre'
) NOT NULL;
```

**Fix canônico (Fase A):**
Migration nova `2026_05_28_010001_add_whatsmeow_to_channels_type_enum.php`:

```php
<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adiciona 'whatsapp_whatsmeow' ao ENUM channels.type (ADR 0204).
 *
 * Bug catalogado 2026-05-27: agente ADR 0204 adicionou constant na entity
 * mas esqueceu migration. UI não conseguia detectar o novo tipo.
 *
 * Idempotente: MODIFY COLUMN sobrescreve a definição inteira, sem erro
 * se rodar 2x.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            ALTER TABLE channels MODIFY COLUMN type ENUM(
                'whatsapp_meta',
                'whatsapp_zapi',
                'whatsapp_baileys',
                'whatsapp_whatsmeow',
                'instagram',
                'messenger',
                'email_imap',
                'email_smtp',
                'mercadolivre'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        // Defensivo: só faz down se nenhum channel ativo usa whatsmeow
        $count = DB::table('channels')->where('type', 'whatsapp_whatsmeow')->count();
        if ($count > 0) {
            throw new \RuntimeException(
                "Down bloqueado: {$count} channels usam whatsapp_whatsmeow. "
                . "Migrar primeiro pra outro tipo antes de rollback."
            );
        }

        DB::statement("
            ALTER TABLE channels MODIFY COLUMN type ENUM(
                'whatsapp_meta','whatsapp_zapi','whatsapp_baileys',
                'instagram','messenger','email_imap','email_smtp','mercadolivre'
            ) NOT NULL
        ");
    }
};
```

---

### Débito 2 — `business.uuid` coluna ausente (P0)

**Sintoma observado:** `ChannelsController::connectWhatsmeow` ([linha 574-583](../../Modules/Whatsapp/Http/Controllers/Admin/ChannelsController.php#L574)) lê `$business->uuid` pra montar webhook URL multi-tenant; tabela `business` (legacy UltimatePOS) **não tem coluna `uuid`**, retorna NULL, controller faz 500.

**Root cause:**
- ADR 0204 propôs webhook URL com `{business_uuid}` pra resolver multi-tenant ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md))
- Tabela `business` herdada UltimatePOS tem PK numérico `id` apenas
- Agente esqueceu migration adicionando UUID + populate

**Impacto:**
- Webhook URL gerada quebra (vira `.../webhook/whatsmeow/`)
- Daemon não consegue rotear callback de volta
- Vazamento risco: se URL não fosse vazia, viraria fallback inseguro (qualquer business pegaria evento de qualquer um)

**Fix manual aplicado (provisório):**
```sql
ALTER TABLE business ADD COLUMN uuid CHAR(36) NULL UNIQUE AFTER name;
UPDATE business SET uuid = UUID() WHERE uuid IS NULL;
```

**Fix canônico (Fase A — 2 migrations + 1 trait):**

**Migration 1** — `2026_05_28_010002_add_uuid_to_business_table.php`:
```php
<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Adiciona business.uuid (CHAR(36) UNIQUE) — usado por webhooks multi-tenant
 * (whatsmeow, Meta Cloud) pra resolver business_id sem expor PK numérico.
 *
 * Idempotente: checa coluna antes de criar.
 *
 * Multi-tenant Tier 0 (ADR 0093): UUID não substitui business_id global scope,
 * só serve pra URL pública opaca.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('business', 'uuid')) {
            Schema::table('business', function (Blueprint $table) {
                // CHAR(36) > VARCHAR(36) pra fixed-width index perf MySQL
                $table->char('uuid', 36)->nullable()->unique()->after('name');
            });
        }

        // Populate em batches de 100 pra não travar tabela grande
        DB::table('business')->whereNull('uuid')->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('business')
                        ->where('id', $row->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('business', 'uuid')) {
            Schema::table('business', function (Blueprint $table) {
                $table->dropUnique(['uuid']);
                $table->dropColumn('uuid');
            });
        }
    }
};
```

**Trait** — `app/Concerns/HasUuid.php` (NOVA — pattern reutilizável):
```php
<?php
declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Support\Str;

/**
 * HasUuid — auto-gera UUID v4 no boot event `creating`.
 *
 * Aplica em Models que tem coluna `uuid` (CHAR(36) UNIQUE). Não substitui
 * PK numérico — UUID serve pra URLs públicas opacas (webhooks, links).
 *
 * Uso:
 *   class Business extends Model {
 *       use HasUuid;
 *   }
 *
 * Idempotente: se UUID já setado manualmente, respeita.
 */
trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
```

**Aplicar trait** — `app/Business.php` (assumindo Eloquent Model existe):
```php
use App\Concerns\HasUuid;

class Business extends Model {
    use HasUuid;
    // ...
}
```

---

### Débito 3 — `Http::withToken` injeta `Bearer ` (WuzAPI rejeita) (P0)

**Sintoma observado:** TODAS requests pro daemon retornavam 401 Unauthorized. Daemon logs mostravam token recebido como `Bearer <token>` ao invés de `<token>` puro.

**Root cause:** Laravel `Http::withToken($apiKey)` **auto-prepend** `Bearer ` no header `Authorization`. WuzAPI (asternic/wuzapi) espera **token puro** no header `Authorization` (sem `Bearer ` prefix) pra rota `/admin/users`, e header `Token` (sem `Bearer `) pra session endpoints. 7 ocorrências no controller + driver.

**Impacto resolvido:**
- PR #1787 mergeado: substitui `Http::withToken($apiKey)` por `Http::withHeaders(['Authorization' => $apiKey])` em ChannelsController + WhatsmeowDriver
- **Sem Pest guard** — se alguém regredir voltando `withToken`, ninguém pega

**Fix canônico complementar (Fase E):**

**Test** — `Modules/Whatsapp/Tests/Feature/WhatsmeowAuthHeaderTest.php`:
```php
<?php
declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\Whatsapp\Services\Drivers\WhatsmeowDriver;

/**
 * Guard contra regressão Bearer prefix (bug 2026-05-27).
 *
 * WuzAPI rejeita Authorization: Bearer <token>. Espera token puro.
 * Se alguém voltar Http::withToken() no driver, este test pega.
 */
it('whatsmeow driver envia Authorization sem Bearer prefix em /admin/users', function () {
    Http::fake(function ($request) {
        // Header MUST be exatamente o token, sem "Bearer "
        $auth = $request->header('Authorization')[0] ?? null;

        expect($auth)->not->toStartWith('Bearer ');
        expect($auth)->toBe('test-admin-token');

        return Http::response(['id' => 1], 200);
    });

    config([
        'whatsapp.whatsmeow.api_key' => 'test-admin-token',
        'whatsapp.whatsmeow.daemon_url' => 'https://daemon.test',
    ]);

    $channel = \Modules\Whatsapp\Entities\Channel::factory()->make([
        'channel_uuid' => 'abc-123',
        'type' => 'whatsapp_whatsmeow',
    ]);

    app(WhatsmeowDriver::class)->provisionSession($channel, 'biz-uuid-xyz');
});

it('whatsmeow driver envia Token header (não Authorization) em /session/*', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/session/status')) {
            $token = $request->header('Token')[0] ?? null;
            $auth = $request->header('Authorization')[0] ?? null;

            expect($token)->toBe('user-token-xyz');
            expect($auth)->toBeNull();
        }

        return Http::response(['Connected' => true, 'LoggedIn' => true], 200);
    });

    // ... build channel with whatsmeow_user_token = 'user-token-xyz'
    // ... call WhatsmeowDriver::ping() (que chama /session/status)
});
```

---

### Débito 4 — `/session/connect` 500 "already connected" sem reconcile (P0)

**Sintoma observado:** Wagner clicou Conectar. Backend chamou `POST /session/connect`. Daemon retornou 500 com body "already connected". UI mostrou erro genérico. Wagner ficou bloqueado.

**Root cause:** Se user existe no daemon (criação anterior falhou no meio, ficou orphan), `POST /session/connect` em estado já-conectado retorna 500. Daemon **JÁ TEM QR gerado** nesse estado (visto via `GET /admin/users` em resposta), mas backend não consulta esse endpoint. Comportamento documentado em [WuzAPI issue #131](https://github.com/asternic/wuzapi/issues/131).

**Estados possíveis WuzAPI user (de `GET /admin/users` + `GET /session/status`):**

| Estado canônico | Como detectar | Ação backend |
|---|---|---|
| `NOT_EXISTS` | `/admin/users` não retorna nome | `POST /admin/users` → `POST /session/connect` → `GET /session/qr` |
| `EXISTS_NOT_CONNECTED` | user em `/admin/users`, `connected=false` | `POST /session/connect` → `GET /session/qr` |
| `EXISTS_CONNECTED_QR_PENDING` | `connected=true`, `LoggedIn=false` | `GET /session/qr` direto (NÃO chamar connect de novo) |
| `EXISTS_CONNECTED_PAIRED` | `connected=true`, `LoggedIn=true` | retornar `state=connected` direto |
| `EXISTS_LOGGED_OUT` | `connected=true`, `LoggedIn=false` MAS user tinha `jid` antes | `POST /session/logout` → recria fluxo do zero |
| `EXISTS_BANNED` | 403/forbidden recorrente em endpoints | alarme Wagner + bloqueia channel |
| `DAEMON_UNREACHABLE` | timeout / 5xx em `/health` | retorna 503 ao UI, circuit breaker dispara |

**Impacto:**
- Wagner experimentou múltiplas vezes (tinha Jana orphan de tentativa anterior)
- UI mostrou "Daemon retornou 500" sem orientação
- Workaround manual exigiu Wagner pedir Claude pra investigar via SSH

**Fix canônico (Fase B) — Reconciler service:**

`Modules/Whatsapp/Services/WhatsmeowReconciler.php` (NOVO):

```php
<?php
declare(strict_types=1);

namespace Modules\Whatsapp\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Services\Drivers\WhatsmeowDriver;

/**
 * WhatsmeowReconciler — State Machine canônica WuzAPI user lifecycle.
 *
 * Bug raiz catalogado 2026-05-27: ChannelsController chamava POST /session/connect
 * direto sem verificar estado, daemon retornava 500 "already connected".
 *
 * Estratégia: SEMPRE consulta GET /admin/users + GET /session/status ANTES
 * de qualquer mutação. Decide caminho baseado no estado real.
 *
 * Idempotente: re-rodar reconcile é seguro (operação convergente, não acumulativa).
 *
 * Multi-tenant Tier 0 (ADR 0093): channel.business_id global scope respeitado
 * — Reconciler nunca atravessa fronteira business.
 *
 * @see memory/decisions/proposals/0205-state-machine-whatsmeow-reconciliacao.md
 */
final class WhatsmeowReconciler
{
    public function __construct(
        private readonly WhatsmeowDriver $driver,
    ) {}

    /**
     * Reconcilia o estado do channel com o daemon. Retorna ResultDTO com:
     *  - state: NOT_EXISTS | EXISTS_NOT_CONNECTED | QR_PENDING | PAIRED | LOGGED_OUT | BANNED | DAEMON_UNREACHABLE
     *  - qr_base64: ?string (presente se QR_PENDING)
     *  - jid: ?string (presente se PAIRED — phone E.164 normalizado)
     *  - message: string (PT-BR, pronto pra UI)
     */
    public function reconcileForConnect(Channel $channel): WhatsmeowReconcileResult
    {
        if ($channel->type !== Channel::TYPE_WHATSAPP_WHATSMEOW) {
            throw new \InvalidArgumentException("Channel #{$channel->id} não é whatsmeow.");
        }

        // 1. Daemon vivo? (circuit breaker — Fase C)
        if (! $this->daemonHealthy()) {
            return WhatsmeowReconcileResult::daemonUnreachable();
        }

        // 2. Business UUID resolvido?
        $business = \DB::table('business')->where('id', $channel->business_id)->first();
        if ($business === null || empty($business->uuid)) {
            return WhatsmeowReconcileResult::error(
                'Business sem uuid — execute migration 2026_05_28_010002.'
            );
        }

        $userName = $channel->whatsmeowUserName(); // ch-<channel_uuid>

        // 3. User existe no daemon?
        $remoteUsers = $this->listRemoteUsers();
        $remoteUser = collect($remoteUsers)->firstWhere('name', $userName);

        if ($remoteUser === null) {
            // NOT_EXISTS → cria + connect + qr
            $this->provisionFromScratch($channel, (string) $business->uuid);
            return $this->connectAndFetchQr($channel);
        }

        // 4. User existe — consulta status real
        $userToken = $channel->config_json['whatsmeow_user_token'] ?? null;
        if (empty($userToken)) {
            // Edge case: user no daemon mas token perdido no DB → recria
            Log::warning('whatsmeow.reconcile.token_missing_recreate', [
                'channel_id' => $channel->id,
                'remote_user' => $userName,
            ]);
            $this->driver->deleteRemoteUser($remoteUser['id']);
            $this->provisionFromScratch($channel, (string) $business->uuid);
            return $this->connectAndFetchQr($channel);
        }

        $status = $this->fetchSessionStatus($userToken);

        return match (true) {
            $status['Connected'] && $status['LoggedIn']
                => WhatsmeowReconcileResult::paired($status['Jid'] ?? null),

            $status['Connected'] && ! $status['LoggedIn']
                => $this->fetchQrForPendingSession($userToken),

            // EXISTS_NOT_CONNECTED → connect normal
            ! $status['Connected'] && $userToken !== null
                => $this->connectAndFetchQr($channel),

            default => WhatsmeowReconcileResult::error('Estado desconhecido.'),
        };
    }

    private function daemonHealthy(): bool
    {
        // Circuit breaker via Cache (Fase C — abrir circuito se 5 falhas/60s)
        $circuit = Cache::get('whatsmeow.circuit.state', 'closed');
        if ($circuit === 'open') {
            return false;
        }

        try {
            $r = Http::whatsmeowDaemon() // macro Fase C
                ->timeout(3)
                ->get('/health');
            return $r->successful();
        } catch (\Throwable) {
            $this->recordFailure();
            return false;
        }
    }

    private function listRemoteUsers(): array
    {
        $r = Http::whatsmeowDaemon()
            ->withHeaders(['Authorization' => config('whatsapp.whatsmeow.api_key')])
            ->get('/admin/users');

        return $r->successful() ? ($r->json() ?? []) : [];
    }

    private function fetchSessionStatus(string $userToken): array
    {
        $r = Http::whatsmeowDaemon()
            ->withHeaders(['Token' => $userToken])
            ->get('/session/status');

        if (! $r->successful()) {
            return ['Connected' => false, 'LoggedIn' => false];
        }

        // WuzAPI envelopa em {code, data: {...}} ou direto {Connected, LoggedIn}
        $body = $r->json();
        return $body['data'] ?? $body;
    }

    private function fetchQrForPendingSession(string $userToken): WhatsmeowReconcileResult
    {
        $r = Http::whatsmeowDaemon()
            ->withHeaders(['Token' => $userToken])
            ->get('/session/qr');

        if (! $r->successful()) {
            return WhatsmeowReconcileResult::error("Falha ao buscar QR: HTTP {$r->status()}");
        }

        $qrBase64 = $r->json('data.QRCode') ?? $r->json('QRCode');
        return WhatsmeowReconcileResult::qrPending($qrBase64);
    }

    private function provisionFromScratch(Channel $channel, string $businessUuid): void
    {
        $provision = $this->driver->provisionSession($channel, $businessUuid);
        $cfg = $channel->config_json ?? [];
        $cfg['whatsmeow_user_token'] = $provision['token'];
        $cfg['whatsmeow_user_name'] = $provision['name'];
        $cfg['whatsmeow_webhook_url'] = $provision['webhook'];
        $channel->config_json = $cfg;
        $channel->save();
    }

    private function connectAndFetchQr(Channel $channel): WhatsmeowReconcileResult
    {
        $result = $this->driver->connect($channel);
        return $result['qr_base64']
            ? WhatsmeowReconcileResult::qrPending($result['qr_base64'])
            : WhatsmeowReconcileResult::error('Daemon não retornou QR.');
    }

    private function recordFailure(): void
    {
        $key = 'whatsmeow.circuit.failures';
        $failures = (int) Cache::increment($key);
        Cache::expire($key, 60); // janela 60s

        if ($failures >= 5) {
            Cache::put('whatsmeow.circuit.state', 'open', now()->addMinutes(2));
            Log::alert('whatsmeow.circuit.opened', ['failures' => $failures]);
        }
    }
}
```

**DTO** — `Modules/Whatsapp/Services/WhatsmeowReconcileResult.php`:

```php
<?php
declare(strict_types=1);

namespace Modules\Whatsapp\Services;

final readonly class WhatsmeowReconcileResult
{
    private function __construct(
        public string $state,            // NOT_EXISTS, QR_PENDING, PAIRED, LOGGED_OUT, BANNED, DAEMON_UNREACHABLE, ERROR
        public ?string $qrBase64 = null,
        public ?string $jid = null,
        public string $message = '',
    ) {}

    public static function paired(?string $jid): self
    {
        return new self('PAIRED', null, $jid, "Canal pareado ($jid).");
    }

    public static function qrPending(?string $qrBase64): self
    {
        return new self('QR_PENDING', $qrBase64, null,
            'Escaneie o QR no WhatsApp → Dispositivos vinculados.');
    }

    public static function daemonUnreachable(): self
    {
        return new self('DAEMON_UNREACHABLE', null, null,
            'Daemon whatsmeow indisponível. Tente em instantes.');
    }

    public static function error(string $message): self
    {
        return new self('ERROR', null, null, $message);
    }

    public function toUi(): array
    {
        return [
            'state' => $this->state,
            'qr_png_data_url' => $this->qrBase64 ? 'data:image/png;base64,' . $this->qrBase64 : null,
            'jid' => $this->jid,
            'message' => $this->message,
            'paired' => $this->state === 'PAIRED',
            'error' => in_array($this->state, ['ERROR', 'DAEMON_UNREACHABLE', 'BANNED']),
        ];
    }
}
```

**Refactor** `ChannelsController::connectWhatsmeow` (encolhe ~50 LOC):

```php
protected function connectWhatsmeow(Channel $channel): JsonResponse
{
    try {
        $result = app(WhatsmeowReconciler::class)->reconcileForConnect($channel);

        // Atualiza channel.status se mudou
        if ($result->state === 'PAIRED' && $channel->status !== 'active') {
            $channel->status = 'active';
            $channel->channel_health = 'healthy';
            $channel->last_health_check_at = now();
            $channel->save();
        }

        return response()->json(['ok' => true, ...$result->toUi()]);
    } catch (\Throwable $e) {
        Log::error('whatsmeow.connect_exception', [
            'channel_id' => $channel->id,
            'exception' => $e->getMessage(),
        ]);
        return response()->json([
            'ok' => false,
            'error' => 'Falha interna: ' . $e->getMessage(),
        ], 502);
    }
}
```

---

### Débito 5 — QR PNG em `public/qr-suporte-temp.png` (gambiarra) (P0)

**Sintoma observado:** Pra desbloquear Wagner, agente salvou QR PNG manualmente em `public/qr-suporte-temp.png` na Hostinger. URL pública `https://oimpresso.com/qr-suporte-temp.png` ficou exposta — qualquer um com a URL via.

**Root cause:** Backend `connectWhatsmeow` retornava 500 (Débito 4) então UI nunca recebeu QR base64 inline. Gambiarra emergencial.

**Impacto:**
- LGPD: QR é PII (vincula número WhatsApp ao business) — exposição pública é incidente
- Hardcoded path, sem cleanup, sem auth
- Qualquer cliente curioso descobre via Google Dork (`site:oimpresso.com qr`)

**Fix canônico (Fase D — UI inline + remoção arquivo):**

1. **Remover arquivo emergencial AGORA** (não esperar Fase D):
   ```bash
   # SSH Hostinger
   rm public/qr-suporte-temp.png
   ```

2. **Endpoint canônico já existe** — `POST /atendimento/canais/{id}/connect` retorna `qr_png_data_url` inline base64 (depois do refactor Reconciler Fase B). UI Dialog renderiza `<img src={qr_png_data_url} />`.

3. **UI fluxo end-to-end** (`Atendimento/Channels/Index.tsx`):

```tsx
// Dialog "Conectar Canal Whatsmeow"
const [qr, setQr] = useState<string | null>(null);
const [state, setState] = useState<'idle' | 'loading' | 'qr_pending' | 'paired' | 'error'>('idle');

const handleConnect = async () => {
  setState('loading');
  const resp = await fetch(`/atendimento/canais/${channelId}/connect`, {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
  });
  const data = await resp.json();

  if (data.paired) {
    setState('paired');
    return;
  }
  if (data.qr_png_data_url) {
    setQr(data.qr_png_data_url);
    setState('qr_pending');
    startPolling(); // Inertia usePoll → /atendimento/canais/{id}/status 2s
    return;
  }
  setState('error');
};

// Polling enquanto qr_pending
const { start: startPolling, stop: stopPolling } = usePoll(2000, () => {
  router.reload({ only: ['channelStatus'] });
});

useEffect(() => {
  if (state === 'paired') stopPolling();
}, [state]);

// Render
{state === 'loading' && <SkeletonLoader />}
{state === 'qr_pending' && qr && (
  <div>
    <img src={qr} alt="QR Code WhatsApp" className="w-64 h-64" />
    <p>Escaneie no WhatsApp → Configurações → Dispositivos vinculados</p>
  </div>
)}
{state === 'paired' && <SuccessBanner>Canal pareado!</SuccessBanner>}
{state === 'error' && <ErrorBanner>{errorMessage}</ErrorBanner>}
```

4. **Gate de segurança no controller** — endpoint `connect` exige `auth` middleware + `whatsapp.settings.manage` permission (já presente nas rotas, manter):
   ```php
   Route::middleware(['auth', 'permission:whatsapp.settings.manage'])
       ->post('atendimento/canais/{id}/connect', [ChannelsController::class, 'connect']);
   ```

---

### Débito 6 — `asternic/wuzapi:latest` sem pin SHA digest (P1)

**Sintoma:** `docker-compose.yml` linha 33: `image: asternic/wuzapi:latest`. Rebuild futuro pode quebrar prod silenciosamente se asternic publicar versão incompatível (mudança de schema response, breaking API change).

**Root cause:** Convenção 2026 ([Docker Docs digests](https://docs.docker.com/dhi/core-concepts/digests/)): produção sempre pin via SHA-256 digest, nunca `:latest`. Comentário no compose já reconhece o débito mas não foi implementado.

**Fix canônico (Fase A — atualizar docker-compose):**

```bash
# Resolver digest atual (SSH CT 100 antes de pin):
docker pull asternic/wuzapi:latest
docker inspect --format='{{index .RepoDigests 0}}' asternic/wuzapi:latest
# Output exemplo: asternic/wuzapi@sha256:abc123...
```

**docker-compose.yml** modificação:
```yaml
services:
  whatsapp-whatsmeow:
    # Pin SHA digest pra reprodutibilidade.
    # Atualizar via PR explícita pós smoke staging.
    # Última atualização: 2026-05-28 — versão 3.x.
    image: asternic/wuzapi@sha256:<digest-resolvido>
```

**Renovate config** (`renovate.json` ou `.github/renovate.json`) — auto-PR pra novos digests:
```json
{
  "extends": ["config:base"],
  "docker": {
    "enabled": true,
    "pinDigests": true
  },
  "packageRules": [
    {
      "matchPackageNames": ["asternic/wuzapi"],
      "schedule": ["before 9am on monday"],
      "automerge": false,
      "labels": ["whatsmeow", "infra-pin"]
    }
  ]
}
```

---

### Débito 7 — Sem cron backup `/srv/docker/whatsapp-whatsmeow/sessions/` (P1)

**Sintoma:** Volume Docker contém TODAS as sessões WhatsApp pareadas (SQLite + keys whatsmeow). Perder volume = re-pair todos os channels (Wagner faz scan QR em todos os celulares de novo).

**Root cause:** Comentário em `docker-compose.yml` linha 39 reconhece "backup diário via cron CT 100" — não implementado.

**Fix canônico (Fase C — Restic container):**

`/opt/oimpresso/whatsmeow/backup.docker-compose.yml` (compose separado, mesmo network):

```yaml
services:
  whatsmeow-backup:
    image: mazzolino/restic:1.7.1
    container_name: whatsmeow-backup
    restart: unless-stopped
    environment:
      - BACKUP_CRON=0 3 * * *  # 03:00 BRT daily
      - RESTIC_REPOSITORY=/backups/whatsmeow
      - RESTIC_PASSWORD_FILE=/run/secrets/restic_password
      - RESTIC_BACKUP_SOURCES=/source
      - RESTIC_BACKUP_TAGS=whatsmeow,daily
      - RESTIC_FORGET_ARGS=--keep-daily 7 --keep-weekly 4 --keep-monthly 3 --prune
      - TZ=America/Sao_Paulo
    volumes:
      - /srv/docker/whatsapp-whatsmeow/sessions:/source/sessions:ro
      - /srv/docker/whatsapp-whatsmeow/files:/source/files:ro
      - /srv/backups/whatsmeow:/backups/whatsmeow
    secrets:
      - restic_password

secrets:
  restic_password:
    file: /run/secrets/restic_password
```

**Health monitoring** — adicionar healthcheck cron:
```bash
# /etc/cron.d/whatsmeow-backup-monitor
0 6 * * * root /opt/oimpresso/whatsmeow/scripts/backup-health.sh
```

`scripts/backup-health.sh`:
```bash
#!/bin/bash
# Verifica último backup < 26h. Alerta Wagner via webhook se falhou.
LAST_BACKUP=$(docker exec whatsmeow-backup restic snapshots --json --last 1 \
  | jq -r '.[0].time' | xargs -I {} date -d {} +%s)
NOW=$(date +%s)
AGE_HOURS=$(( (NOW - LAST_BACKUP) / 3600 ))

if [ "$AGE_HOURS" -gt 26 ]; then
  curl -X POST https://oimpresso.com/api/internal/alerts \
    -H "Authorization: Bearer $INTERNAL_ALERT_TOKEN" \
    -d "{\"severity\":\"warning\",\"source\":\"whatsmeow-backup\",\"message\":\"Backup atrasou $AGE_HOURS h\"}"
fi
```

**Runbook restore** (`runbooks/whatsmeow-restore-from-backup.md`) — Fase E.

---

### Débito 8 — Sem retry / circuit breaker + sem OTel spans (P1)

**Sintoma:** Daemon down momentâneo = todas requests timeout até manualmente abortar. Falha transitória vira erro permanente. Sem trace, debug via stdout/log apenas.

**Root cause:** Driver/Controller chamam `Http::withHeaders(...)` direto sem retry config. Nenhum span OTel envolvendo chamadas.

**Fix canônico (Fase C — Macro `Http::whatsmeowDaemon()`):**

`app/Providers/AppServiceProvider.php` (boot method):

```php
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;

public function boot(): void
{
    // Macro Http::whatsmeowDaemon() — config padrão (retry + timeout + OTel span)
    Http::macro('whatsmeowDaemon', function () {
        $baseUrl = config('whatsapp.whatsmeow.daemon_url');
        $timeout = (int) config('whatsapp.whatsmeow.request_timeout', 10);

        return Http::baseUrl($baseUrl)
            ->timeout($timeout)
            ->connectTimeout(3)
            ->withoutVerifying() // CT 100 self-signed dev; produção LE cert
            ->acceptJson()
            ->retry(
                times: 3,
                sleepMilliseconds: function (int $attempt, $exception) {
                    // Exponential backoff with jitter: 500ms, 1000ms, 2000ms + random ±200ms
                    return (500 * (2 ** ($attempt - 1))) + random_int(-200, 200);
                },
                when: function ($exception, $request) {
                    // Retry só em: timeout, conexão refused, 502/503/504
                    if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
                        return true;
                    }
                    if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                        $status = $exception->response->status();
                        return in_array($status, [502, 503, 504], true);
                    }
                    return false;
                },
                throw: false, // não throw — Reconciler decide
            )
            ->beforeSending(function ($request, $options) {
                // OTel span — usa OtelHelper já existente
                \App\Support\OtelHelper::span(
                    "whatsmeow.daemon.{$request->method()}",
                    fn () => null, // span só do request — finaliza em afterRequest
                    [
                        'http.url' => $request->url(),
                        'http.method' => $request->method(),
                        'whatsmeow.daemon_url' => config('whatsapp.whatsmeow.daemon_url'),
                    ]
                );
            });
    });
}
```

**Circuit breaker via Cache** (já mostrado no Reconciler §1.4) — pattern simples sem package externo. Estados:
- `closed` (default): permite tudo
- `open`: bloqueia por 2 min (5 falhas/60s dispara)
- `half-open`: tentativa única a cada 30s pra detectar recovery

**Decisão de NÃO usar package externo** (vide §3 alternativas):
- `gregpriday/laravel-retry`: ótimo mas mais features do que precisamos
- `harris21/laravel-fuse`: focado em queue jobs, não HTTP client
- `flikson/laravel-service-client`: novo, baixo trust
- Custom thin layer: 60 LOC, sem dependência, controlado

---

### Débito 9 — Sem alarme daemon banned/disconnected (P2)

**Sintoma:** Se número banido pela Meta ou sessão expira, daemon sabe via events mas backend não dispara alerta. Wagner só descobre quando cliente reclama "WhatsApp parou".

**Root cause:** `WhatsmeowWebhookController` recebe `Connected` / `Disconnected` events mas não escala pra alerta humano em estados críticos (`banned`, `logged_out` repetido, `disconnected > 1h`).

**Fix canônico (Fase C — Health probe expandido + AlertJob):**

`app/Console/Commands/WhatsmeowHealthProbeCommand.php` (NOVO, ou expandir `HealthProbeChannelsCommand`):

```php
<?php
declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Services\WhatsmeowReconciler;
use Illuminate\Support\Facades\Log;

/**
 * Probe periódico de saúde dos channels whatsmeow.
 *
 * Schedule em Kernel.php: `whatsmeow:health-probe` a cada 30 min.
 * Alerta Wagner se:
 *  - channel.status=active mas reconcile retorna LOGGED_OUT ou BANNED
 *  - 3+ channels num mesmo business banned em 24h (cross-tenant ban alarm)
 *  - daemon DAEMON_UNREACHABLE > 5 min (downtime real)
 */
class WhatsmeowHealthProbeCommand extends Command
{
    protected $signature = 'whatsmeow:health-probe {--alert : envia alerta Wagner se anomalia}';
    protected $description = 'Probe saúde channels whatsmeow + dispara alertas.';

    public function handle(WhatsmeowReconciler $reconciler): int
    {
        $channels = Channel::query()
            ->withoutGlobalScopes() // probe é cross-business legítimo
            ->where('type', Channel::TYPE_WHATSAPP_WHATSMEOW)
            ->where('status', 'active')
            ->get();

        $anomalies = [];
        $bannedByBusiness = [];

        foreach ($channels as $channel) {
            try {
                $result = $reconciler->reconcileForConnect($channel);

                if (in_array($result->state, ['LOGGED_OUT', 'BANNED', 'DAEMON_UNREACHABLE'], true)) {
                    $anomalies[] = [
                        'channel_id' => $channel->id,
                        'business_id' => $channel->business_id,
                        'label' => $channel->label,
                        'state' => $result->state,
                        'message' => $result->message,
                    ];

                    // Atualiza channel.channel_health
                    $channel->channel_health = match ($result->state) {
                        'BANNED' => 'banned',
                        'LOGGED_OUT' => 'disconnected',
                        'DAEMON_UNREACHABLE' => 'never_checked',
                    };
                    $channel->save();

                    if ($result->state === 'BANNED') {
                        $bannedByBusiness[$channel->business_id] =
                            ($bannedByBusiness[$channel->business_id] ?? 0) + 1;
                    }
                }
            } catch (\Throwable $e) {
                Log::error('whatsmeow.probe.exception', [
                    'channel_id' => $channel->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        // Cross-tenant ban alarm — 3+ banidos em 1 business = onda detecção Meta
        foreach ($bannedByBusiness as $bizId => $count) {
            if ($count >= 3) {
                Log::alert('whatsmeow.cross_tenant_ban_wave', [
                    'business_id' => $bizId,
                    'banned_count' => $count,
                ]);
                if ($this->option('alert')) {
                    \Modules\Whatsapp\Jobs\AlertWagnerJob::dispatch(
                        severity: 'critical',
                        message: "ALERTA: business #{$bizId} tem {$count} channels banned. Onda detecção Meta provável.",
                    );
                }
            }
        }

        $this->info('Probed ' . count($channels) . ' channels. Anomalias: ' . count($anomalies));
        return self::SUCCESS;
    }
}
```

Schedule em `app/Console/Kernel.php`:
```php
$schedule->command('whatsmeow:health-probe --alert')
    ->everyThirtyMinutes()
    ->withoutOverlapping(10)
    ->onOneServer()
    ->onFailure(function () {
        Log::alert('whatsmeow.health-probe.failed');
    });
```

---

## §2 · State Machine WuzAPI User Lifecycle

### Diagrama

```
                  ┌──────────────────┐
                  │   NOT_EXISTS     │
                  │ (user nunca foi  │
                  │   criado no      │
                  │   daemon)        │
                  └────────┬─────────┘
                           │ provision()
                           │ POST /admin/users
                           ▼
              ┌─────────────────────────┐
              │  EXISTS_NOT_CONNECTED   │
              │ (user existe, socket    │
              │  daemon-side não        │
              │  conectou ainda)        │
              └────────┬────────────────┘
                       │ POST /session/connect
                       ▼
        ┌──────────────────────────────────┐
        │  EXISTS_CONNECTED_QR_PENDING     │
        │ (daemon conectado, QR gerado,    │
        │  WhatsApp web aguardando scan)   │
        │  status: Connected=true,         │
        │          LoggedIn=false          │
        └────────┬─────────────────────────┘
                 │ user escaneia QR no celular
                 │ ◀──────────── webhook Connected
                 ▼
       ┌──────────────────────────────────┐
       │   EXISTS_CONNECTED_PAIRED        │ ◀────┐
       │ (pareado, loggedIn=true)         │      │
       │  status: Connected=true,         │      │ webhook
       │          LoggedIn=true,          │      │ Connected
       │          Jid=5511987654321@...   │      │ (após
       └────┬─────────────────────────────┘      │ recovery)
            │
            │ (a) user desconecta WhatsApp celular
            │ (b) timeout SESSION_TIMEOUT
            │ (c) Meta detecta + sessão expira
            ▼
    ┌─────────────────────────────────────┐
    │      EXISTS_LOGGED_OUT              │
    │ (pareou antes, perdeu sessão)       │
    │  Connected=true, LoggedIn=false     │
    │  MAS jid era válido antes           │
    └────┬────────────────────────────────┘
         │ POST /session/logout → /session/connect
         │ (re-pair com novo QR)
         ▼
    ┌─────────────────────────────────────┐
    │      EXISTS_BANNED                  │
    │ (número banido pela Meta)           │
    │  403 forbidden recorrente           │
    │  ban_reason: logged_out_remote /    │
    │              multidevice_mismatch / │
    │              policy_violation       │
    └─────────────────────────────────────┘
         │
         │ (admin Wagner deleta + recria
         │  com novo número de telefone)
         └─→ NOT_EXISTS

         ┌─────────────────────────────────────┐
         │      DAEMON_UNREACHABLE             │
         │ (daemon CT 100 down — 5xx/timeout)  │
         │  Pull-based detection: /health 4xx  │
         │  Circuit breaker open: 5 falhas/60s │
         └─────────────────────────────────────┘
```

### Tabela transições

| Estado origem | Evento/trigger | Endpoint chamado | Estado destino | Side effects |
|---|---|---|---|---|
| `NOT_EXISTS` | `reconcileForConnect()` | `POST /admin/users` + `POST /session/connect` + `GET /session/qr` | `QR_PENDING` | grava `whatsmeow_user_token` em config_json |
| `EXISTS_NOT_CONNECTED` | `reconcileForConnect()` | `POST /session/connect` + `GET /session/qr` | `QR_PENDING` | nenhum |
| `EXISTS_NOT_CONNECTED` | timeout 30s | (nenhum) | `EXISTS_NOT_CONNECTED` (retry) | log warning |
| `QR_PENDING` | webhook `Connected` | (nenhum) | `PAIRED` | `channel.status='active'`, `channel_health='healthy'`, broadcast Centrifugo |
| `QR_PENDING` | timeout 5min sem scan | `POST /session/logout` | `EXISTS_NOT_CONNECTED` | log warning, UI ofrece "Tentar novo QR" |
| `PAIRED` | webhook `Disconnected` | (nenhum) | `LOGGED_OUT` | `channel.channel_health='disconnected'`, alert Wagner se > 1h |
| `PAIRED` | health probe `LoggedIn=false` | (nenhum) | `LOGGED_OUT` | alert + tentativa reconcile |
| `LOGGED_OUT` | reconcile manual Wagner UI | `POST /session/logout` + `POST /session/connect` + `GET /session/qr` | `QR_PENDING` | nenhum |
| `LOGGED_OUT` | 3+ tentativas em 24h falham | (nenhum) | `BANNED` (suspeita) | `channel.channel_health='banned'`, alert P0 |
| `BANNED` | Wagner deleta channel + recria | `DELETE /admin/users/{id}` | `NOT_EXISTS` | log activity_log destroy |
| `qualquer` | `/health` 5xx + 5 falhas/60s | (circuit breaker) | `DAEMON_UNREACHABLE` | UI mostra "Daemon indisponível", retry automático 2min |
| `DAEMON_UNREACHABLE` | `/health` 200 OK | (nenhum) | restaura estado anterior | circuit closed |

### Persistência do estado

Estado **NÃO** persiste em coluna nova (over-engineering). Persiste implicitamente em:

- `channels.status` ENUM existente: `active` (PAIRED) | `setup` (NOT_EXISTS, QR_PENDING) | `disconnected` (LOGGED_OUT) | `banned` (BANNED) | `inactive` (manual disable)
- `channels.channel_health` ENUM existente: `healthy` (PAIRED) | `degraded` (intermitente) | `disconnected` (LOGGED_OUT) | `banned` (BANNED) | `never_checked` (DAEMON_UNREACHABLE)
- `channels.config_json.whatsmeow_user_token`: presente = `EXISTS_*` , ausente = `NOT_EXISTS`

Reconciler consulta runtime via `GET /admin/users` + `GET /session/status` — single source of truth é o daemon.

---

## §3 · Alternativas pesquisadas (não escolhidas)

### Alternativa A · `spatie/laravel-model-states` pra state machine

**Pros:** maduro, comunidade ampla, integra com Eloquent
**Cons:** adiciona dependência runtime; força state como classe (5+ classes pra 7 estados); overkill quando estado já existe em ENUMs `channels.status`+`channel_health`
**Decisão:** rejeitado — Reconciler service custom thin (~150 LOC) entrega 100% sem adicionar package

### Alternativa B · `sebdesign/laravel-state-machine` (winzou wrapper)

**Pros:** declarativo via config, transitions explicit
**Cons:** YAML-based config, debug runtime obscuro; última release 2024
**Decisão:** rejeitado — mesmo argumento spatie

### Alternativa C · `harris21/laravel-fuse` circuit breaker pra HTTP

**Pros:** novo (Laracon India 2026), focado em circuit breaker
**Cons:** **foco em queue jobs, não HTTP client request por request**; integração com `Http::macro` exige glue code
**Decisão:** rejeitado — circuit breaker custom via Cache (~30 LOC) basta. Reavaliar se ≥ 3 daemons externos surgirem (ML, Insta, MercadoLivre).

### Alternativa D · `Saloon PHP` em vez de `Http::macro`

**Pros:** OOP-first, request/response classes, retry/middleware embutido
**Cons:** refactor de tudo Whatsapp (drivers, controllers) pra Connector pattern; ~30-50h trabalho; viola "1 PR = 1 intent"
**Decisão:** rejeitado pra esta onda — proposta separada review_trigger se 3+ módulos novos precisarem (ML driver, Insta driver). Macro Http resolve agora com 5 LOC.

### Alternativa E · WebSocket polling em vez de Inertia polling 2s

**Pros:** latência menor (real-time), evita N requests/min
**Cons:** Centrifugo channel per-channel (já existe `whatsapp:business:{id}` pra messages), mas dispara complexity pra pareamento — UI dialog efêmero não justifica
**Decisão:** rejeitado por ora — polling 2s × dialog visível ≤ 60s = ≤ 30 reqs. Custo trivial. Review_trigger: 50+ channels paireando simultâneo (improvável).

### Alternativa F · Aplicar `business.uuid` retroativo via Job assíncrono em batches

**Pros:** evita travar deploy se businesses tabela grande
**Cons:** business só tem ~200 rows; populate síncrono via `chunkById(100)` na migration leva < 5s
**Decisão:** rejeitado — populate inline é simples e suficiente

### Alternativa G · Migrar `business` pra novo nome (`businesses`) e usar Laravel canon

**Pros:** elimina dívida tabela legacy
**Cons:** refactor 100+ arquivos referenciando `business`; viola "loop fechado por métrica" sem sinal cliente
**Decisão:** rejeitado — feature wish, vai pra ADR de proposta longe daqui

---

## §4 · Plano implementação faseado (5 fases)

> **Recalibrado [ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) 10x + margem 2x.** Tarefas codáveis em IA-pair têm horas reduzidas. Wagner-h é puro tempo decisão/aprovação/smoke.

### Fase A · Migrations canônicas (~2-4h IA-pair)

**Escopo isolado:** apenas migrations + trait HasUuid + docker-compose pin. ZERO toque em controller/driver/services.

**Arquivos a criar/modificar:**
- `Modules/Whatsapp/Database/Migrations/2026_05_28_010001_add_whatsmeow_to_channels_type_enum.php` (NEW)
- `database/migrations/2026_05_28_010002_add_uuid_to_business_table.php` (NEW)
- `app/Concerns/HasUuid.php` (NEW)
- `app/Business.php` (MODIFY — aplica `use HasUuid`)
- `Modules/Whatsapp/daemon-go/docker-compose.yml` (MODIFY — pin SHA digest)

**Critério de aceite:**
- `php artisan migrate` roda limpo
- `php artisan migrate:rollback --step=2` reverte com sucesso (down() defensivo testado)
- `SHOW COLUMNS FROM business` mostra `uuid CHAR(36) NULL UNIQUE`
- `SHOW COLUMNS FROM channels` mostra ENUM com 9 valores incluindo `whatsapp_whatsmeow`
- `php artisan tinker --execute='echo \App\Business::first()->uuid'` retorna UUID válido
- `docker pull` resolve digest sem erro

**Risco:** baixo. Migrations idempotentes, populate < 5s.

**Dependências:** nenhuma. Pode rodar agora.

---

### Fase B · State Machine + Reconciler (~4-6h IA-pair)

**Escopo isolado:** novo Service + DTO + refactor minimal controller. ZERO toque em UI / docker / tests.

**Arquivos a criar/modificar:**
- `Modules/Whatsapp/Services/WhatsmeowReconciler.php` (NEW ~150 LOC)
- `Modules/Whatsapp/Services/WhatsmeowReconcileResult.php` (NEW DTO ~50 LOC)
- `Modules/Whatsapp/Services/Drivers/WhatsmeowDriver.php` (MODIFY — adiciona `deleteRemoteUser()` helper)
- `Modules/Whatsapp/Http/Controllers/Admin/ChannelsController.php` (MODIFY — refactor `connectWhatsmeow()` pra usar Reconciler; encolhe ~80 LOC → ~30 LOC)
- `Modules/Whatsapp/Entities/Channel.php` (NO CHANGE — `whatsmeowUserName()` já existe)

**Critério de aceite:**
- Reconciler retorna DTO certo pra cada estado (Pest mockando Http)
- Refactor `connectWhatsmeow` sem regressão funcional (Wagner clica Conectar, vê QR)
- Channel `status` + `channel_health` atualizam corretamente em transições

**Risco:** médio. Refactor controller mexe em endpoint produção. Mitigação: feature flag `WHATSMEOW_USE_RECONCILER=true` (default false até validar) — revert via 1 env var.

**Dependências:** Fase A merged.

---

### Fase C · Retry / circuit breaker / OTel / backup / health probe (~3-5h IA-pair)

**Escopo isolado:** macro Http + circuit breaker + Restic compose + health probe. ZERO toque em UI / controllers existentes.

**Arquivos a criar/modificar:**
- `app/Providers/AppServiceProvider.php` (MODIFY — adiciona macro `Http::whatsmeowDaemon()`)
- `Modules/Whatsapp/Services/WhatsmeowReconciler.php` (MODIFY — usa macro; adiciona circuit breaker via Cache)
- `Modules/Whatsapp/Console/Commands/WhatsmeowHealthProbeCommand.php` (NEW)
- `Modules/Whatsapp/Jobs/AlertWagnerJob.php` (NEW — webhook interno ou email Wagner)
- `app/Console/Kernel.php` (MODIFY — schedule `whatsmeow:health-probe` 30min)
- `Modules/Whatsapp/daemon-go/backup.docker-compose.yml` (NEW — Restic)
- `Modules/Whatsapp/daemon-go/scripts/backup-health.sh` (NEW)
- `config/whatsapp.php` (MODIFY — `'whatsmeow' => ['daemon_url', 'api_key', 'request_timeout', 'circuit_breaker' => ['threshold' => 5, 'window_seconds' => 60, 'open_minutes' => 2]]`)

**Critério de aceite:**
- Macro `Http::whatsmeowDaemon()->get('/health')` faz retry com backoff
- Circuit breaker abre após 5 falhas, fecha após 2min (testável via `Cache::put('whatsmeow.circuit.failures', 5)` + verify Reconciler retorna DAEMON_UNREACHABLE imediatamente)
- OTel spans aparecem em traces produção (manual smoke após deploy)
- `restic snapshots` lista backup após 1ª run
- `php artisan whatsmeow:health-probe` reporta corretamente

**Risco:** baixo. Pure-add features, não modifica fluxo existente (Reconciler Fase B já está hospedando).

**Dependências:** Fase B merged (Reconciler usa macro).

---

### Fase D · UI fluxo end-to-end (~3-5h IA-pair)

**Escopo isolado:** apenas `Atendimento/Channels/Index.tsx` + Dialog component. ZERO toque em backend.

**Arquivos a modificar:**
- `resources/js/Pages/Atendimento/Channels/Index.tsx` (MODIFY — Dialog Conectar)
- `resources/js/Pages/Atendimento/Channels/components/WhatsmeowConnectDialog.tsx` (NEW)

**Critério de aceite:**
- Wagner clica Conectar em channel novo → loading skeleton → QR aparece inline ≤ 2s
- Polling automático detecta pareamento em ≤ 4s pós-scan
- Mensagens claras em cada estado (loading, qr_pending, paired, error, daemon_unreachable)
- Remover `public/qr-suporte-temp.png` se ainda existe

**Risco:** baixo. UI isolada. Pode merger atrás de feature flag.

**Dependências:** Fase B merged (endpoint canon retornando estrutura nova).

---

### Fase E · Pest + Runbook (~2-4h IA-pair)

**Escopo isolado:** apenas tests + docs. ZERO toque em code path produção.

**Arquivos a criar:**
- `Modules/Whatsapp/Tests/Feature/WhatsmeowReconcilerTest.php` (NEW — 7 cenários estado)
- `Modules/Whatsapp/Tests/Feature/WhatsmeowAuthHeaderTest.php` (NEW — guard Bearer regression)
- `Modules/Whatsapp/Tests/Feature/ChannelsControllerConnectTest.php` (NEW — endpoint end-to-end mocked)
- `Modules/Whatsapp/Tests/Feature/WhatsmeowHealthProbeCommandTest.php` (NEW)
- `memory/requisitos/Whatsapp/runbooks/whatsmeow-troubleshoot.md` (NEW — 10 cenários)
- `memory/requisitos/Whatsapp/runbooks/whatsmeow-restore-from-backup.md` (NEW)

**Cenários Pest mínimos `WhatsmeowReconcilerTest`:**
1. `NOT_EXISTS` → provisiona + retorna QR
2. `EXISTS_NOT_CONNECTED` (user no daemon, sem socket) → connect + QR
3. `QR_PENDING` (user conectado, não logado) → GET /qr direto (NÃO chama connect de novo — guard contra Débito 4)
4. `PAIRED` (LoggedIn=true) → retorna sem chamar nada
5. `LOGGED_OUT` (user existe, jid era válido) → logout + reconnect + QR
6. `BANNED` (403 recorrente) → marca channel banned + retorna ERROR
7. `DAEMON_UNREACHABLE` (circuit open) → retorna unavailable sem chamar HTTP

**Cenários runbook `whatsmeow-troubleshoot.md`:**
1. "Botão Conectar sumiu" → ENUM channels.type (Fase A migration)
2. "500 ao conectar" → reconcile manual via `php artisan whatsmeow:reconcile --channel=N`
3. "QR não aparece após 10s" → daemon health + circuit
4. "Pareou mas mensagens não chegam" → webhook verify HMAC
5. "Channel virou banned" → procedimento Meta + cooling 24h
6. "Volume sessions corrompido" → restore Restic snapshot
7. "Daemon CT 100 não responde" → check `docker logs whatsapp-whatsmeow`
8. "Token daemon API key rotacionar" → procedimento sem downtime
9. "WuzAPI upgrade nova versão" → smoke staging + pin novo digest
10. "Múltiplos channels banned 24h" → resposta cross-tenant ban alarm

**Critério de aceite:**
- `php artisan test --filter=Whatsmeow` passa 100%
- Coverage > 70% nas linhas WhatsmeowReconciler + WhatsmeowDriver
- Runbook reviewed Wagner

**Risco:** zero. Pure docs/tests.

**Dependências:** B + C + D merged.

---

## §5 · Pré-flight checks (antes de disparar implementadores)

**OBRIGATÓRIO marcar tudo VERDE antes de spawnar agentes Fase 3:**

- [ ] Wagner aceitou ADR 0205 (status `accepted` no frontmatter)
- [ ] `mysqldump --tables business channels > /tmp/preflight-2026-05-28.sql` em Hostinger (rollback emergency)
- [ ] `curl https://whatsapp-whatsmeow.oimpresso.com/health` retorna 200 (daemon CT 100 vivo)
- [ ] `php artisan migrate:status` zero pending antes de Fase A
- [ ] Branch worktree limpa (`git status` empty)
- [ ] Channels existentes biz=1 (`Jana`, `Suporte`) listados em `SELECT id, label, type, status FROM channels WHERE business_id=1`
- [ ] Volume CT 100 `/srv/docker/whatsapp-whatsmeow/sessions/` tem conteúdo (sessões pareadas existem)

---

## §6 · Sequência recomendada (paralelo vs sequencial)

```
Wagner aceita ADR 0205
         │
         ▼
   ┌────────────────────────┐
   │  Fase A (1 dia)        │  ← OBRIGATÓRIO ANTES de tudo
   │  Migrations + trait    │     (B/C/D dependem)
   └─────────┬──────────────┘
             │
             ▼
   ┌───────────────────────────────────────────────────┐
   │  PARALELO SEGURO (3-4 dias se sub-agents simul)   │
   ├────────────────┬─────────────────┬────────────────┤
   │  Fase B        │  Fase C         │  Fase D        │
   │  Reconciler    │  Retry/OTel/    │  UI Dialog     │
   │                │  Backup/Probe   │  + remove      │
   │                │                 │  gambiarra     │
   └────────┬───────┴────────┬────────┴───────┬────────┘
            │                │                │
            └────────────────┼────────────────┘
                             ▼
                  ┌──────────────────────┐
                  │  Fase E (1 dia)      │
                  │  Pest + Runbook      │
                  └──────────┬───────────┘
                             │
                             ▼
                  ┌──────────────────────┐
                  │  Wagner smoke real   │
                  │  + canary 7d         │
                  └──────────────────────┘
```

**Por que B/C/D são paralelo-seguro:**

| Fase | Arquivos tocados | Conflito risk vs outras |
|---|---|---|
| B | Reconciler Service (novo) + ChannelsController (1 método refactor) | nenhum com C/D — métodos isolados |
| C | AppServiceProvider boot + Kernel.php + novo Command + Restic compose | nenhum com B/D — adds-only |
| D | apenas arquivos React `.tsx` | nenhum com B/C — fronteira clara backend/frontend |

**Spawn implementadores juniores (Fase 3 do `/audit-and-fix`):**
```
audit-implement-expert × 3 paralelo (após Fase A merged):
  - sub-agent 1: dossier §1.4 + §4 Fase B (Reconciler)
  - sub-agent 2: dossier §1.6-§1.8 + §4 Fase C (retry/backup/probe)
  - sub-agent 3: dossier §1.5 + §4 Fase D (UI)
audit-implement-expert × 1 (após B+C+D merged):
  - sub-agent 4: dossier §4 Fase E (Pest + runbook)
```

---

## §7 · Custo total projetado

### Dev-days (recalibrado [ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md))

| Item | Tempo IA-pair | Tempo relógio |
|---|---|---|
| Fase A migrations + trait | 2-4h | 1 dia (revisão Wagner inclusa) |
| Fase B Reconciler | 4-6h | 2 dias (canary feature flag) |
| Fase C retry/OTel/backup/probe | 3-5h | 2 dias (smoke produção) |
| Fase D UI | 3-5h | 2 dias (Wagner aprovar visual MWART) |
| Fase E Pest + runbook | 2-4h | 1 dia |
| **TOTAL** | **14-24h IA-pair** | **~8 dias relógio** |
| **TOTAL Wagner-h** | **~4h** (decisões + smoke) | |

### R$ infra incremental

| Item | Custo |
|---|---|
| CT 100 daemon whatsmeow | R$ 0 (já alocado) |
| Restic backup container (~50MB RAM) | R$ 0 (mesma CT 100) |
| Backup storage Hostinger / volume CT | R$ 0 (~100MB/mês esperado) |
| Renovate.io PR automation | R$ 0 (free plan, ≤ 10 repos) |
| **TOTAL incremental** | **R$ 0/mês** |

### R$ LLM (ADR 0094 §4 custo IA tracking)

| Item | Tokens estimados | Custo (Opus 4.7) |
|---|---|---|
| Reconciler implementação (Fase B) | ~50k input + 20k output | ~US$ 1,20 |
| Macro retry + circuit (Fase C) | ~30k + 15k | ~US$ 0,80 |
| UI Dialog refactor (Fase D) | ~25k + 12k | ~US$ 0,65 |
| Pest tests (Fase E) | ~35k + 20k | ~US$ 0,95 |
| Code review parallel agents | ~80k + 5k | ~US$ 0,90 |
| **TOTAL** | ~330k tokens | **~US$ 4,50 (R$ ~25)** |

**Margem segurança 2x:** R$ 50 total LLM. Trivial vs valor entregue.

---

## §8 · Riscos + métricas de sucesso

### Riscos (priorizado)

| Severidade | Risco | Mitigação |
|---|---|---|
| **TIER 0 (irrevogável)** | Cross-tenant leak via webhook URL | Reconciler **sempre** resolve `business_uuid` de `channels.business_id` (global scope ADR 0093). Pest `WhatsmeowChannelIsolationTest` já existe — expandir cobertura Fase E. |
| **Tier 1** | Latência reconcile > 2s degrada UX | Macro Http timeout 3s connect + 10s total. Circuit breaker corta cascata. Polling Inertia 2s aceita esse delay. |
| Tier 1 | Migration `business.uuid` populate trava em produção | `chunkById(100)` < 5s pra ~200 rows. Pré-flight backup garante rollback. |
| Tier 2 | Refactor controller introduz regressão | Feature flag `WHATSMEOW_USE_RECONCILER` toggle. Canary biz=1 (Wagner) por 24h antes liberar geral. |
| Tier 2 | Daemon WuzAPI bump major breaks API | Pin SHA digest evita upgrade involuntário. Renovate PR força review. |
| Tier 3 | Restic password perdido = backup inúteis | Vaultwarden registra password. Runbook §restore exige test 1× /mês. |

### Métricas de sucesso (SLO)

| Métrica | Target | Como medir |
|---|---|---|
| `whatsmeow.qr.fetch_p95` | < 500ms | OTel span Fase C |
| `whatsmeow.reconcile.errors_per_day` | < 1 | log query Loki/Grafana |
| `whatsmeow.session.paired_within_60s` | > 90% | webhook `Connected` timestamp - `connect` request timestamp |
| `whatsmeow.daemon.uptime` | 99.9% | health probe `/health` 30min checks |
| `whatsmeow.circuit.open_per_week` | < 5 | Cache key sampling |
| `business.uuid_coverage` | 100% | `SELECT COUNT(*) FROM business WHERE uuid IS NULL` = 0 |
| Bugs novos sessão pareamento | 0 / mês | Wagner relata |

### Gates fase

**Gate Fase A (1 dia):**
- `php artisan migrate:status` mostra 2 migrations applied
- Wagner cria novo channel test biz=1 e vê opção "WhatsApp Whatsmeow" habilitada
- `SELECT uuid FROM business LIMIT 5` retorna UUIDs válidos

**Gate Fase B+C+D (3-4 dias):**
- Wagner conecta channel novo em ≤ 60s end-to-end (NÃO 5 bugs como em 2026-05-27)
- Daemon down simulado (`docker stop whatsapp-whatsmeow` no CT 100) → UI mostra "Daemon indisponível", não trava
- Backup Restic snapshot existe após 24h

**Gate Fase E (1 dia):**
- `php artisan test --filter=Whatsmeow` 100% green
- Wagner lê runbook e diz "ok claro" (subjetivo mas crítico)

**Gate Mês 1 (smoke canary 7d):**
- 2+ channels paireados sem incidente
- Zero alerta Wagner por bug pareamento
- Métrica `paired_within_60s` > 90%

---

## §9 · Triggers de reabertura (review_triggers)

Conforme [ADR 0094 Constituição v2 §4 loop fechado por métrica](../decisions/0094-constituicao-v2-7-camadas-8-principios.md):

1. **Daemon WuzAPI versão major bump** (3.x → 4.x) — state machine pode mudar, reavaliar Reconciler
2. **WhatsApp Meta soltar novo TOS endurecendo Web** — pode requerir mudança fluxo pareamento
3. **Volume passar 50 channels paireados simultâneo** — re-avaliar polling 2s vs WebSocket Centrifugo dedicado
4. **3+ businesses banidos em 24h** (cross-tenant ban alarm dispara) — investigar onda detecção Meta, possível mitigação anti-ban
5. **Métrica `paired_within_60s` cai abaixo 80% por 7 dias** — diagnóstico daemon ou rede
6. **Custo CT 100 + sessões superar US$ 30/mês** ([ADR 0204 review_trigger](../decisions/0204-whatsmeow-driver-substituto-baileys.md)) — otimizar imagem ou abandonar
7. **Cliente pagante reportar dor "preciso compliance enterprise"** — reabrir BSP Take Blip / 360dialog (ADR 0202 review_trigger)
8. **3+ outros módulos novos precisando HTTP daemon externo** (ML, Insta) — reavaliar Saloon PHP vs múltiplas macros

---

## §10 · Próximo passo imediato (após Wagner aceitar ADR)

```bash
# 1. Backup pré-migration (Hostinger SSH)
ssh hostinger 'cd /home/u832000832/oimpresso && \
  mysqldump --single-transaction --tables business channels > \
  /tmp/preflight-whatsmeow-2026-05-28.sql'

# 2. Spawn sub-agent Fase A (em paralelo zero conflito):
#    audit-implement-expert
#    input:
#      - gap: "Fase A · Migrations canônicas whatsmeow"
#      - dossier_section: §1.1 + §1.2 + §1.6 + §4 Fase A
#      - scope: migrations + trait + docker-compose pin only
#      - target_files: 5 arquivos listados §4 Fase A

# 3. Após Fase A merged + smoke Wagner:
#    Spawn 3 sub-agents paralelo (B/C/D)

# 4. Após B+C+D merged:
#    Spawn 1 sub-agent Fase E

# 5. Wagner smoke real biz=1 + canary 7d biz=Termas
```

---

**Autoria:** audit-senior-expert (opus-4.7) · 2026-05-27 · PT-BR · sem hedge
**WebSearch total:** 9 · **WebFetch total:** 1 · **Tempo sessão:** ~90 min
**Decisão final:** Wagner em ≤10 min via leitura deste dossier + ADR 0205 companion
