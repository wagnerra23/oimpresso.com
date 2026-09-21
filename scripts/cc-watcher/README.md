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

**Instância única:** o `--watch` toma um lock em `~/.claude/.cc-watcher.lock`. Subir um 2º daemon sai com **exit 2** em vez de duplicar o tráfego. Lock de processo morto é assumido automaticamente — crash não deixa o pipe cego.

### O `--watch` ficou INERTE de 2026-04-30 a 2026-09-18 (141 dias)

> Vale ler antes de mexer aqui: o daemon ficava **vivo e sem ingerir nada**, o que é
> pior que cair — `whats-active` servia "nenhuma sessão vista" enquanto 13 sessões
> paralelas se atropelavam no mesmo arquivo. Quatro defeitos independentes:
>
> | # | Defeito | Por que passou |
> |---|---|---|
> | A | **chokidar 4 removeu suporte a glob.** `watch('<pasta>/*.jsonl')` observa 0 paths e nunca emite evento | `^4.0.3` está aqui desde o 1º commit (`f20982bb0`) — nunca houve versão em que funcionasse |
> | B | A lista de pastas era lida **1× no boot**, então worktree criada depois ficava invisível | caso dominante: em 18/09 as 10 pastas com atividade do dia eram todas pós-boot |
> | C | `content_json` era inicializado `null` e **nunca atribuído** — e é dele que sai o "paths tocados" do `whats-active` (`whereNotNull`) | o teste do consumidor semeia a coluna **direto no banco**, então ficava verde com a produção quebrada |
> | D | 429 do throttle **descartava** o arquivo (one-shot, sem retry) | só aparecia quando o watch funcionasse — e ele nunca funcionou |
>
> Consertado observando `PROJECTS_DIR` (sem glob) + filtro de handler, `content_json`
> com o path tocado, retry com `Retry-After`, e fila serial com coalescing. O gate é
> [`watch.test.mjs`](watch.test.mjs) — exercita a fiação REAL (`createJsonlWatcher`
> com chokidar real), porque assert sobre cópia paralela é exatamente o que deixou
> (A) verde por 141 dias. Mordida provada por mutação: glob de volta ⇒ 5 de 13 falham.

### Cron diário (alternativa ao daemon)

Windows Task Scheduler / Linux cron pra rodar `node index.js` 1×/dia 23:00 BRT.

⚠️ **Não existe nenhum agendamento hoje** (medido em 18/09: 207 tasks no Task Scheduler, nenhuma do watcher). O daemon é iniciado **à mão**, então ninguém o reinicia depois de reboot — e é por isso que o heartbeat morria sozinho mesmo antes dos defeitos acima. Quem detecta é o `IngestLivenessChecker` (`enforcement: warn`), não um watchdog de cron.

## Config (env opcional)

| Var | Default | Descrição |
|---|---|---|
| `MCP_URL` | `https://mcp.oimpresso.com/api/cc/ingest` | Endpoint backend |
| `MCP_TOKEN` | auto-detect de `.claude/settings.local.json` | Bearer token |
| `PROJECT_GLOB` | `D--oimpresso-com` | Filtra subfolders de `~/.claude/projects/` |
| `STATE_FILE` | `~/.claude/.cc-watcher-state.json` | Offset por arquivo: `mtime` decide se o arquivo é lido, `lineCount` decide de **qual linha** o POST começa. `mtime: 0` marca progresso parcial (entregue pela metade sob 429) — força reler sem pular |
| `CC_PROJECTS_DIR` | `~/.claude/projects` | Raiz dos `.jsonl`. Existe pro bite-test rodar o CLI contra uma sandbox; em produção não se mexe |

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

### "HTTP 429"

⚠️ Esta seção dizia *"bateu cota MCP — espera reset (00:00 BRT) ou Wagner aumenta em `/admin/team`"*. **Medido em 2026-09-21: errado na prática.** Existem **dois** 429 possíveis nesta rota e eles se distinguem pela mensagem do corpo — esperar o reset ou pedir mais cota não resolve o que o watcher de fato batia.

| Mensagem | Quem devolve | Chave do limite | O que fazer |
|---|---|---|---|
| `Too Many Attempts.` | `ThrottleRequests` do grupo `api` | **IP**, 60/min | ver abaixo — **é este o caso comum** |
| `Quota excedida — chamadas bloqueadas. Detalhes: [...]` | `QuotaEnforcer`, dentro do `mcp.auth` | user_id | aí sim: reset 00:00 BRT ou aumentar em `/copiloto/admin/team` |

**Por que o limite é por IP e não por user.** A rota declara `['api', 'mcp.auth']` sem `throttle:` próprio ([`Modules/Forja/Http/routes.php:181`](../../Modules/Forja/Http/routes.php)); quem limita é o grupo `api` → `RateLimiter::for('api')` → `Limit::perMinute(60)->by($request->user()?->id ?: $request->ip())` ([`app/Providers/RouteServiceProvider.php:49`](../../app/Providers/RouteServiceProvider.php)). O grupo `api` roda **antes** do `mcp.auth`, então não há user autenticado no instante do throttle e a chave cai no `?:` — o **IP**. Ele é compartilhado por todas as sessões Claude da máquina, então um watcher que POSTa demais queima o balde **das sessões vizinhas**, não o próprio.

Conferência barata de que não é cota: a tool MCP `claude-code-usage-self` reporta `quota` por usuário. Em 21/09 ela dava `quota: 0` em 7 dias enquanto o run colecionava 429 — logo, throttle.

**A causa histórica, e o que ela ensina.** Até 21/09 o run re-POSTava quase tudo a cada passada: `readJsonl` lia o arquivo inteiro e o `lineCount` do state era **write-only** como offset. Medido no corpus real: **188 POSTs / 30.189 mensagens** por run, para ~3 mil de fato novas. Somava-se a isso o state só ser gravado **depois** de todos os batches — um 429 no último jogava fora o progresso dos anteriores, e o run seguinte reenviava o arquivo inteiro, gerando mais 429. O indício que denunciou o círculo: **455 sessões POSTadas** num momento em que só **19** dos 883 `.jsonl` tinham sido modificados nas últimas 24h.

Corrigido no mesmo dia (offset real + avanço por batch confirmado): **27 POSTs / 3.322 mensagens** no mesmo corpus, −89% de mensagens. O gate é [`incremental.test.mjs`](incremental.test.mjs), que exercita o CLI de fora; mordida provada por 6 mutações, incluindo a que grava o mtime real no progresso parcial — essa causa **perda silenciosa**, e sobrevivia até o teste parar de tocar o mtime entre os runs.

Se ainda aparecer `Too Many Attempts.` depois disso: o watcher já faz 4 tentativas honrando `Retry-After`, então 429 repetido significa concorrência real no mesmo IP — mais de um watcher rodando (confira o lock e a tarefa agendada) ou muitas sessões ingerindo ao mesmo tempo.

### Falha de rede / 5xx

Re-rode — idempotência cobre.

## Refs

- [SPEC MEM-CC-UI-1](../../memory/requisitos/Copiloto/SPEC-cc-sessions.md)
- [ADR 0053 — MCP server governança](../../memory/decisions/0053-mcp-server-governanca-como-produto.md)
- [ADR 0059 — Governança Anthropic Team](../../memory/decisions/0059-governanca-memoria-estilo-anthropic-team.md)
- Endpoint backend: `Modules/Forja/Http/Controllers/Mcp/CcIngestController.php` (o ponteiro dizia `Modules/Copiloto/...`, que **não existe** — o cluster de ingest veio pra Forja em 2026-07-31; a URL e o route name `jana.cc.ingest` seguem inalterados de propósito)
