# ADR 0042 — Reverb (self-hosted) substitui Pusher Cloud como broadcaster

**Status:** ✅ Aceita
**Data:** 2026-04-28
**Escopo:** Plataforma — broadcast/realtime (Copiloto, dashboards, notificações, push Delphi futuro)
**Decisor:** Wagner [W]
**Implementador:** Claude (sessão 2026-04-28)
**Branch:** `claude/reverb-install`

---

## Contexto

UltimatePOS legado trazia `pusher/pusher-php-server` `^5.0` no `composer.json` e config `pusher` em `config/broadcasting.php` apontando pra **Pusher Cloud (pago, por mensagem/conexão)**. **Nenhum lugar** do código usa hoje (`grep -r "ShouldBroadcast|Broadcast::|->broadcast(" app/ Modules/` retorna zero), ou seja, é uma dep dormindo + intenção sem implementação.

Casos de uso emergentes que precisam push do servidor pro cliente:

1. **Streaming token-a-token Copiloto** → Larissa (ROTA LIVRE) vê resposta saindo, em vez de spinner de 8s. Critério de UX dentro do Cycle 01.
2. **Dashboard `/copiloto/admin/qualidade`** atualizando sozinho (faithfulness últimos 7d).
3. **Notificações entre usuários do ERP** (sell criado, pagamento confirmado, intercorrência aberta).
4. **Push pro cliente Delphi** (WR Comercial) — viável via lib WS Delphi (sgcWebSockets), prioridade baixa.

Wagner pediu **"reverb pra comunicação instantânea sem custo do push"** em 2026-04-28.

## Decisão

**Adotar Laravel Reverb (self-hosted, OSS) como broadcaster default.** Remover `pusher/pusher-php-server` `^5.0` (cloud) do `composer.json`. **Manter o protocolo Pusher no front** via `pusher-js` + `@laravel/echo-react` — o Reverb implementa o mesmo wire protocol, então o Echo continua sendo o cliente.

**Driver:** `reverb` (registrado nativamente pelo `BroadcastManager` do Laravel 13.6).

**Stack final:**

| Camada | Pacote | Versão | Papel |
|---|---|---|---|
| Servidor WS | `laravel/reverb` | `^1.10` | Daemon WS, protocolo Pusher |
| Cliente JS | `@laravel/echo-react` | `^2.x` | Wrapper React (hooks `useEcho` etc.) |
| Cliente JS | `laravel-echo` | `^2.x` | Echo subjacente |
| Cliente JS | `pusher-js` | `^8.x` | Implementação do protocolo (não fala com Pusher.com — fala com Reverb) |
| Server PHP (transitivo) | `pusher/pusher-php-server` | `^7.2` | Trazido pelo Reverb pra publicar eventos no canal interno (não vai pra cloud) |

## Alternativas consideradas

| Opção | Por que não |
|---|---|
| **Pusher Cloud** (manter `pusher/pusher-php-server` `^5.0`) | Custo por conexão/mensagem; lock-in num SaaS externo; PII passa por servidor de terceiro (LGPD-blocker). |
| **SSE-only** (Server-Sent Events) | Suficiente pra streaming Copiloto unidirecional (server→browser), mas não cobre canais privados/presence, multi-aba sincronizada nem push pro Delphi. Ficaria stack dupla quando precisar. |
| **Soketi / Pusher-Compatible Server** | OSS, mesmo protocolo, mas mantido por terceiros, comunidade menor. Reverb é first-party Laravel — encaixa melhor com Horizon/Telescope/Pulse já no projeto. |
| **Ably** (cloud) | Mesmo problema de custo + lock-in do Pusher Cloud. |
| **Lib WS custom** | Reinventar roda; nenhum benefício técnico vs Reverb. |

## Consequências

**Positivas:**
- Custo zero por mensagem/conexão (só compute do daemon).
- PII não sai do servidor oimpresso (LGPD).
- API Laravel idiomática (`broadcast(new Event)`, `Broadcast::channel()`, hooks Echo).
- Compatibilidade total com `pusher-js` no front — quando alguém quiser trocar pra cloud temporariamente, é só alterar 4 envs.
- Reverb integra com Pulse e Telescope (campos `pulse_ingest_interval`, `telescope_ingest_interval` em `config/reverb.php`).

**Negativas / dívidas técnicas assumidas:**
- **Daemon novo no Hostinger** — supervisor (PM2 ou systemd-user) + nginx reverse-proxy WS (`/app`, `/apps/{appId}/events`) + porta 8080 ou 443 com TLS termination. Mesma classe de problema do Meilisearch daemon (task A4 do Felipe, [`CURRENT.md`](../../CURRENT.md)). **Não foi deployado nessa PR — fica pendurado pra deploy junto da A4.**
- **Scaling:** 1 instância Hostinger basta até X conexões; quando precisar escalar, ligar `REVERB_SCALING_ENABLED=true` + Redis pubsub.
- **Front consome `pusher-js`** mesmo sem Pusher Cloud — esse é o protocolo, não o serviço. Nome confunde, mas é o caminho oficial.

**Neutro:**
- `routes/channels.php` foi republicado pelo `install:broadcasting`; canal default ajustado de `App.Models.User.{id}` (Laravel 11+ style) pra `App.User.{id}` (UltimatePOS legado, classe `App\User`).
- `bootstrap/app.php` desse projeto é Laravel 10 style; `BroadcastServiceProvider` em `app/Providers/` já registra `Broadcast::routes()` + `routes/channels.php`. Não precisou de `withBroadcasting()`.

## Plano de implementação

**Feito (local, branch `claude/reverb-install`):**

1. ✅ `composer remove pusher/pusher-php-server` (versão cloud `^5.0`)
2. ✅ `composer require laravel/reverb` (puxa `pusher-php-server@7.2.7` como dep transitiva pra publicação interna)
3. ✅ `php artisan install:broadcasting --reverb --without-node` (publica `routes/channels.php` + ajusta `resources/js/app.tsx` com `configureEcho({broadcaster:'reverb'})`)
4. ✅ `npm install --save laravel-echo pusher-js @laravel/echo-react`
5. ✅ `php artisan vendor:publish --provider="Laravel\Reverb\ReverbServiceProvider"` → cria `config/reverb.php`
6. ✅ `config/broadcasting.php` reescrito: removido bloco `'pusher'`, adicionado `'reverb'` (driver `reverb`).
7. ✅ `.env` local: `BROADCAST_DRIVER=reverb`, `BROADCAST_CONNECTION=reverb`, `REVERB_APP_*`, `VITE_REVERB_*`.
8. ✅ Ajuste `App.Models.User.{id}` → `App.User.{id}` em `routes/channels.php` (compat UltimatePOS legado).
9. ✅ Smoke test infra: `app/Events/ReverbPing.php` (`ShouldBroadcastNow`, canal `reverb-test`, evento `ping`) + `app/Console/Commands/ReverbPingCommand.php` (`php artisan reverb:ping {message?}`).

**Pendências:**

- 🟡 **Cliente React ouvindo `reverb-test`** — não criado nessa PR pra manter escopo enxuto. Snippet abaixo.
- 🟡 **Tests Pest** — não criados (smoke test imperativo via artisan basta pro Cycle 01).
- 🔴 **Deploy Hostinger** — daemon Reverb + supervisor + nginx WS proxy. Encaixar junto da A4 do Felipe (Meilisearch daemon). Sem deploy, BROADCAST_DRIVER em produção fica `null` (rollback automático).

## Como testar local

```bash
# Terminal 1: subir daemon
php artisan reverb:start --port=8080 --debug

# Terminal 2: disparar evento
php artisan reverb:ping "olá da Larissa"
# → "Broadcast enviado em 'reverb-test' (event: ping, mensagem: olá da Larissa)."

# Browser: abrir DevTools console em qualquer página Inertia logada
# (Echo já está configurado em resources/js/app.tsx)
import Echo from 'laravel-echo';
window.Echo.channel('reverb-test').listen('.ping', (e) => console.log('REVERB:', e));
# Voltar ao Terminal 2 e disparar de novo — deve chegar no console.
```

## Snippet React (uso futuro)

```tsx
import { useEcho } from '@laravel/echo-react';

function CopilotoStream({ conversaId }: { conversaId: string }) {
  const [tokens, setTokens] = useState<string[]>([]);

  useEcho(
    `copiloto.conversa.${conversaId}`, // canal privado
    'TokenStreamed',
    (e: { token: string; index: number }) => setTokens((prev) => [...prev, e.token]),
  );

  return <pre>{tokens.join('')}</pre>;
}
```

## Rollback

- **Local/dev:** `BROADCAST_DRIVER=null` no `.env` → app volta a não broadcast nada. Reverb pode continuar instalado sem efeito.
- **Produção:** mesmo, e parar o daemon supervisor.
- **Reverso completo:** `composer remove laravel/reverb` + `npm uninstall laravel-echo pusher-js @laravel/echo-react` + reverter `config/broadcasting.php` + `routes/channels.php` + `resources/js/app.tsx` + remover `app/Events/ReverbPing.php` + `app/Console/Commands/ReverbPingCommand.php`.

## Relacionadas

- [ADR 0035](0035-stack-ai-canonica-wagner-2026-04-26.md) — Stack canônica IA (Camada A laravel/ai, Camada B Vizra ADK, Camada C Meilisearch)
- [ADR 0036](0036-replanejamento-meilisearch-first.md) — Meilisearch como driver default (memória semântica do Copiloto)
- [ADR 0040](0040-policy-publicacao-claude-supervisiona.md) — Policy publicação (Claude decide rotineiro reversível, escala irreversível alto-impacto)
- [`CURRENT.md`](../../CURRENT.md) — Cycle 01 active (A4 Felipe: Meilisearch daemon Hostinger — Reverb pega carona no mesmo deploy)

---

**Resumo executivo (1 linha):** Reverb (self-hosted, OSS, first-party Laravel) substitui Pusher Cloud (pago, lock-in, PII em terceiro), mantendo o protocolo Pusher no front via `pusher-js` — custo zero, LGPD-friendly, encaixa com o stack já no projeto.
