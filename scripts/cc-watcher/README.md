# oimpresso-cc-watcher

Watcher Node que ingere `~/.claude/projects/<projeto>/*.jsonl` pro MCP server (`mcp.oimpresso.com/api/cc/ingest`).

Implementa **MEM-CC-UI-1 US-COPI-CC-040/041** (Cycle 02 antecipado).

## Setup (1× por dev)

```bash
cd scripts/cc-watcher
npm install
```

Auto-detecta token do `.claude/settings.local.json` do projeto. Não precisa env var.

## Uso

### Backfill 1× (recomendado primeira vez)

```bash
npm run start
# ou
node index.js
```

Processa **todas** sessões de `~/.claude/projects/D--oimpresso-com*/`. Idempotente — re-rodar é seguro (skip por mtime, dedup por msg_uuid).

### Daemon (modo contínuo)

```bash
npm run watch
# ou
node index.js --watch
```

Monitora mudanças via `chokidar`. Ingere incremental conforme você usa o Claude Code. Ctrl+C pra sair.

### Cron diário (alternativa ao daemon)

Windows Task Scheduler / Linux cron pra rodar `node index.js` 1×/dia 23:00 BRT.

## Config (env opcional)

| Var | Default | Descrição |
|---|---|---|
| `MCP_URL` | `https://mcp.oimpresso.com/api/cc/ingest` | Endpoint backend |
| `MCP_TOKEN` | auto-detect de `.claude/settings.local.json` | Bearer token |
| `PROJECT_GLOB` | `D--oimpresso-com` | Filtra subfolders de `~/.claude/projects/` |
| `STATE_FILE` | `~/.claude/.cc-watcher-state.json` | Offset por arquivo (mtime+lineCount) |

## O que ingere

| Tipo JSONL | Backend `msg_type` | Notas |
|---|---|---|
| `user` | `user` | conteúdo string ou array |
| `assistant` (com text) | `assistant` | extrai texto do content array |
| `assistant` (com tool_use) | `tool_use` | extrai tool_name + input truncado 1000 chars |
| `tool_result` | `tool_result` | conteúdo concatenado |
| `hook` | `hook` | passa direto |
| `system` | `system` | passa direto |

## O que **NÃO** ingere

- `queue-operation` (ruído)
- `attachment` (deferred_tools_delta — só lista de tools)
- Mensagens vazias (<2 chars de conteúdo)

## Redação de segredo na fronteira de ingest

> Por que existe: em 2026-09-15 um `cat` de `.claude/settings.local.json` imprimiu o
> token MCP do [W] no transcript. O transcript tem canal PRÓPRIO de saída — **este
> watcher** — que lia `tool_result` verbatim e mandava pro `mcp_cc_messages`, legível
> pelo time via `cc-search`. Medir "vazou?" no git responde com o oráculo errado: o
> arquivo é gitignored e nunca foi trackeado. Regra violada: **ADR 0057 §10** — *"Token
> raw: nunca em git, log, screenshot, transcript, slack history"*.

Desde a v0.2, **todo** payload passa por [`redact.mjs`](redact.mjs) antes do `fetch`.
O predicado é **shape do valor na saída**, nunca nome de arquivo (acusar `cat` de
arquivo "que parece credencial" puniria `.env.example`, template e config sem segredo
— guard sintático, a família com 8 lápides medidas em `memory/proibicoes.md` §5).

Dois pontos, cada um com motivo próprio:

| Onde | Por quê |
|---|---|
| `parseMessage`, antes do corte de 50k | segredo que atravessasse a truncagem sobreviveria partido ao meio, sem casar padrão |
| `postBatch`, antes do `JSON.stringify` | é o **único** ponto que faz `fetch`; cobre `session` e todo campo que alguém adicione depois, sem precisar lembrar |

O que sai no lugar preserva o rótulo: `DB_PASSWORD=[REDACTED:assign_generic]` ainda diz
**qual** variável era. O log do daemon reporta só a **contagem** por tipo — nunca o valor.

### FP medido antes de armar (regra "LIGUE A MÁQUINA" item 4)

Corpus real: **827 arquivos `.jsonl` · 394.285 mensagens** (`~/.claude/projects/D--oimpresso-com*`).

| | |
|---|---|
| mensagens tocadas | **25** (0,0063%) |
| valores redigidos | **33** |
| verdadeiros positivos | **30** — 3 token MCP vivo, 1 `OPENAI_API_KEY`, 26 senha de env dump (`DB_PASSWORD`, `MARIADB_ROOT_PASSWORD`, `META_APP_SECRET`, `PAYPAL_SANDBOX_API_SECRET`) |
| falsos positivos | **3** (9,1%) — 1 `ghp_` e 2 PEM, **todos fixture de teste já fake** |
| custo de conteúdo | −549 bytes em 303 MB |

⚠️ **O padrão `mcp_token` é `mcp_` + 64 HEX, derivado do gerador** (`'mcp_' . bin2hex(random_bytes(32))`,
`McpToken::gerar`, tamanho fixado por teste em 68). **Não afrouxe sem re-medir:** a forma
frouxa `mcp_[A-Za-z0-9_-]{16,}` dá **10.959 hits e ~99,97% de falso-positivo** — ela casa
**nome de tool** (`mcp__ccd_session_mgmt__list_sessions`), e armá-la redigiria toda chamada
de tool de todo transcript. Há controle negativo no bite-test justamente pra isso.

### Bite-test

```bash
node --test scripts/cc-watcher/redact.test.mjs
```

Morde **e** tem controle negativo, e exercita o `postBatch` real com `fetch` stubado
(assert sobre helper puro não provaria o pipeline). Mordida provada por mutação:
redação desligada ⇒ 6 de 10 falham; padrão `mcp_` afrouxado ⇒ o controle negativo cai.
Roda no CI em `governance-script-tests.yml` (advisory, registrado por nome).

## Dedup + idempotência

- **`msg_uuid`** UNIQUE no servidor (`mcp_cc_messages.msg_uuid`) — 2ª execução não duplica
- **Conteúdo >4KB** vai pra `mcp_cc_blobs` SHA256-deduplicado
- **State local** (`~/.claude/.cc-watcher-state.json`) skipa arquivos sem mudança de mtime

Re-rodar é seguro a qualquer momento.

## Verificar dados

Após rodar, abre `https://oimpresso.com/copiloto/admin/cc-sessions` — deve listar suas sessões.

## Troubleshooting

### "MCP_TOKEN ausente"

Crie `.claude/settings.local.json` com:
```json
{
  "mcpServers": {
    "oimpresso": {
      "url": "https://mcp.oimpresso.com/api/mcp",
      "headers": { "Authorization": "Bearer mcp_..." }
    }
  }
}
```

Token gerado em `https://oimpresso.com/copiloto/admin/team`.

### "HTTP 401 Unauthorized"

Token revogado ou inválido. Gera novo em `/copiloto/admin/team`.

### "HTTP 403 Forbidden"

User não tem permission `copiloto.cc.ingest.self` ou `jana.mcp.use`. Wagner atribui via tinker.

### "HTTP 429 Quota Exceeded"

Bateu cota MCP. Espera reset (00:00 BRT) ou Wagner aumenta em `/admin/team`.

### Falha de rede / 5xx

Re-rode — idempotência cobre.

## Refs

- [SPEC MEM-CC-UI-1](../../memory/requisitos/Copiloto/SPEC-cc-sessions.md)
- [ADR 0053 — MCP server governança](../../memory/decisions/0053-mcp-server-governanca-como-produto.md)
- [ADR 0059 — Governança Anthropic Team](../../memory/decisions/0059-governanca-memoria-estilo-anthropic-team.md)
- Endpoint backend: `Modules/Copiloto/Http/Controllers/Mcp/CcIngestController.php`
