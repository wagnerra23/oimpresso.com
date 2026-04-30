# ADR 0058 — Reverb substituído por Centrifugo + FrankenPHP

**Status:** ✅ Aceita
**Data:** 2026-04-30
**Decisores:** Wagner [W]
**Tags:** infra · websocket · realtime · ct100 · frankenphp · centrifugo
**Supersedes:** decisão informal de adoção de Reverb feita em sessões anteriores (sem ADR formal).

---

## Contexto

Plano original (CT 100, infra empresa): Laravel **Reverb** como WebSocket server pra eventos realtime do Copiloto chat e dashboards (`/copiloto/admin/qualidade`, `/copiloto/admin/governanca`).

Em testes de carga e smoke local 2026-04-30:
- **Reverb não respondeu** sob payload do Copiloto chat (LLM streaming chunks)
- **Crashou** durante teste de stress com 5 conexões simultâneas
- **FrankenPHP** (já em uso pro `mcp.oimpresso.com` Streamable HTTP / SSE) absorveu as mesmas conexões sem problema
- **Centrifugo** (Go, single-binary) é referência de mercado pra realtime PHP-friendly: ~1M conexões/instância, suporta WebSocket + SSE + HTTP polling, sem Redis obrigatório

Wagner: *"não vai ser usado o reverb pois parece lento e foi colocado centrifugo e frankphp nos teste o reverb não respondeu crashou."*

---

## Decisão

**Adotar Centrifugo + FrankenPHP no CT 100 como stack realtime canônica.** Reverb fica oficialmente abandonado pelo projeto.

### Camadas

| Camada | Antes | Agora |
|---|---|---|
| WebSocket / SSE pro frontend | Reverb (Pusher protocol) | **Centrifugo** (próprio protocolo, JSON-RPC over WS) |
| Server PHP (HTTP + SSE) | nginx + PHP-FPM | **FrankenPHP** (já em prod no `mcp.oimpresso.com`) |
| Pub/sub backend | Reverb in-memory | **Redis pub/sub** (Centrifugo lê do Redis pra fan-out) |
| Cliente JS | `laravel-echo` + `pusher-js` | `centrifuge-js` (oficial) |
| Auth | Sanctum token | **Centrifugo JWT HS256** (servidor Laravel emite token JWT 1h, Centrifugo valida) |

### Endpoints CT 100 propostos

| Subdomínio | Serviço | Estado |
|---|---|---|
| `mcp.oimpresso.com` | FrankenPHP MCP server | ✅ ativo |
| `realtime.oimpresso.com` | Centrifugo (WS + admin UI) | 🔲 deploy pendente |
| `traefik.oimpresso.com` | Traefik (já roteia tudo) | ✅ ativo |

### Fluxo realtime canônico

```
[Browser laravel-echo-react via centrifuge-js]
   ↓ wss://realtime.oimpresso.com/connection/websocket
[Centrifugo Go binary em CT 100 docker]
   ↓ subscribe channel "biz.4.copiloto"
[Redis pub/sub]
   ↑ publish channel "biz.4.copiloto" payload {token chunk, etc}
[Laravel app Hostinger]
   ↑ broadcast() → Redis publisher
```

Auth: Laravel emite JWT HS256 (`sub=user_id, channels=["biz.{id}.*"]`) no `/api/centrifugo/connect`. centrifuge-js usa o token. Servidor Centrifugo valida via secret compartilhado em `CENTRIFUGO_TOKEN_HMAC_SECRET_KEY`.

---

## Razões

1. **Reverb falhou no nosso caso de uso real** (LLM streaming) — não é teórico
2. **Centrifugo é gold-standard PHP+realtime** (1M+ conn/instance, HA-ready)
3. **Reaproveita FrankenPHP** já validado no MCP server (CT 100) — menos infra fragmentada
4. **Sem Redis obrigatório no Reverb** virou Redis obrigatório no Centrifugo, mas Redis já existe no CT 100 stack pra cache/queue
5. **JWT em vez de Pusher protocol** alinha com OAuth/Sanctum existente
6. **Centrifuge admin UI** dá governança (conexões ativas por user, mensagens/s) — Reverb não tem

## Trade-offs aceitos

- **Mais 1 binary (Go) no CT 100** — pequeno (~30MB), bem isolado em container
- **Cliente JS troca de `pusher-js` pra `centrifuge-js`** — refactor pontual em hooks Echo
- **Auth via JWT exige endpoint `/api/centrifugo/connect`** no Laravel — implementação trivial

## Plano de migração

| Dia | Entrega |
|---|---|
| 1 | docker-compose.yml Centrifugo no CT 100 + DNS `realtime.oimpresso.com` + cert Let's Encrypt |
| 2 | Driver `CentrifugoBroadcaster` em `app/Broadcasting/` + endpoint `/api/centrifugo/connect` (JWT) |
| 3 | Migra hooks `useEcho()` pra `useCentrifuge()` em `resources/js/Hooks/` (ou wrapper compatível) |
| 4 | Smoke real Copiloto chat streaming + remove `composer require laravel/reverb` (que nem foi adicionado de fato) |

---

## Trigger pra revisitar

- Centrifugo ficar acima de R$ 50/mês ops cost → reavaliar Caddy push, Soketi
- Cliente externo precisar de Pusher protocol estrito → manter Centrifugo + adicionar adapter (ou voltar pro Reverb upgraded — Reverb v2 prometido com fix de stability)

---

## Referências

- [Centrifugo](https://centrifugal.dev/) (Go realtime messaging)
- [FrankenPHP](https://frankenphp.dev/) — server PHP moderno (já em prod no MCP)
- [centrifuge-js](https://github.com/centrifugal/centrifuge-js) — cliente browser
- [ADR 0042 — Infra empresa Proxmox + Docker + Traefik](0042-infra-empresa-padrao.md) (CT 100 padrão)
- Sessão `2026-04-29-mcp-server-bootstrap.md` (FrankenPHP em prod)

---

**Última atualização:** 2026-04-30
