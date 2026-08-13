---
id: requisitos-jana-spec-cc-sessions
---

# SPEC — `/copiloto/admin/cc-sessions` (Memória Claude Code do time)

> **Status:** Proposta — pendente Cycle 02
> **Owner:** Wagner [W]
> **Slug:** `MEM-CC-UI-1`
> **ADRs base:** [0053](../../decisions/0053-mcp-server-governanca-como-produto.md) (MCP governança), [0055](../../decisions/0055-self-host-team-plan-equivalente-anthropic.md) (Self-host Team plan), [0056](../../decisions/0056-mcp-fonte-unica-memoria-copiloto-claude-code.md) (MCP fonte única)
> **Tabelas existentes (schema feito):** `mcp_cc_sessions`, `mcp_cc_messages`, `mcp_cc_blobs`
> **Tool MCP existente:** `cc-search` (sem UI ainda)

---

## 1. Contexto

Wagner gasta R$ [redacted Tier 0]k/dia em Claude Code (smoke 29-abr). Felipe/Maiara/Luiz/Eliana entrarão em breve — projeção R$ [redacted Tier 0]k/dia no time completo. Cada sessão Claude Code dura horas, gera centenas de mensagens, consome tools (Bash/Edit/Read/Grep/...), descobre soluções, comete erros, aprende padrões.

Hoje todo esse aprendizado fica isolado em `~/.claude/projects/*.jsonl` na máquina de cada dev. **Quando Felipe enfrentar bug que Wagner já resolveu, ele re-explora do zero.** R$ pago pelos mesmos tokens de descoberta.

Schema `mcp_cc_*` já existe (3 tabelas, migrations rodadas em prod 29-abr). Tool MCP `cc-search` consulta. **Falta a UI** pra Wagner ver o que o time tá fazendo, governar uso, e capitalizar conhecimento como ativo da empresa.

### Por que é a maior lacuna

- ✅ Schema pronto (já existe, ~5d de trabalho economizado)
- ✅ Tool `cc-search` MCP funciona pelo agente
- ❌ Sem UI: ninguém SEM Claude Code/MCP enxerga
- ❌ Sem watcher: sessions JSONL locais NÃO sobem automático ainda
- ❌ Sem dedup: cada dev consome tokens descobrindo o mesmo

---

## 2. Personas

| Persona | Acesso | Caso de uso |
|---|---|---|
| **Wagner** (owner/governança) | `copiloto.cc.read.all` + admin | Audita time, calcula custo per-dev, descobre quem tá produzindo o quê |
| **Felipe / Maiara** (devs sêniores) | `copiloto.cc.read.team` | Busca cross-dev: "como Wagner fez X mês passado" |
| **Luiz** (junior) | `copiloto.cc.read.self` + `cc.read.team` | Aprende com sessões dos outros antes de pedir ajuda |
| **Eliana** (financeiro) | `copiloto.cc.read.self` | Vê próprio uso, sem cross-dev |

Permission `copiloto.cc.read.all` = ver todas; `cc.read.team` = ver team mas não admin; `cc.read.self` = só próprias.

---

## 3. User Stories

> **DoD mínimo (todas):** rota autorizada (`403`), scope RBAC, FormRequest, JSON shape, Feature test, dark mode, mobile, toast `sonner`.

### Área Lista de sessões

#### US-COPI-CC-001 · Listar sessões do time
- **Rota:** `GET /copiloto/admin/cc-sessions`
- **Controller:** `Admin\CcSessionsController@index`
- **Como** Wagner **quero** ver lista paginada de sessões CC **para** ter overview do time.
- **Colunas:** dev (avatar+nome), data (relativa), duração, msgs, tools usadas (badges), tokens, custo R$, status (active/closed/archived), summary_auto trecho
- **Filtros:** dev (multi), data range, project_path (D:\oimpresso.com / outro), status, tool (Bash/Edit/...), custo min, busca FULLTEXT em `summary_auto`
- **Ordenação:** mais recente / maior custo / mais mensagens
- **DoD extra:** paginação 25/page, filtros server-side, lazy-load thread só ao clicar.

#### US-COPI-CC-002 · KPIs globais do time
- **Como** Wagner **quero** KPIs no topo da lista **para** sentir o pulso do time.
- **KPIs:** Sessões hoje | Custo hoje (R$) | Devs ativos hoje | Tools mais usadas (top 3) | Sessions abertas agora | Tempo médio sessão

### Área Detalhe da sessão

#### US-COPI-CC-010 · Abrir sessão (preview lateral)
- **Rota:** `GET /copiloto/admin/cc-sessions/{session_uuid}`
- **Controller:** `Admin\CcSessionsController@show` (JSON pra Sheet preview)
- **Como** Wagner **quero** clicar uma linha e ver thread completa à direita **para** entender o que rolou.
- **Layout:** split list/preview (igual `/admin/memoria`), resizable opcional
- **Conteúdo preview:**
  - Header: dev, data início/fim, duração, total msgs, custo, branch git, project_path
  - Summary auto-gerado (`summary_auto`)
  - Thread cronológica de mensagens (user/assistant/tool_use/tool_result agrupados)
  - Tool calls com badge da tool + truncated content (expandir on-click)
  - Tokens cumulativos por mensagem
- **DoD extra:** carrega `mcp_cc_messages` paginado por session_id, blob fetch on-demand pra payload >4KB.

#### US-COPI-CC-011 · Highlight de tool_use/tool_result
- **Como** dev sênior **quero** ver tool calls destacadas **para** entender padrão de uso.
- **Visual:** Bash em verde, Edit em laranja, Read em cinza, Grep em azul, etc. Badge + tempo decorrido.

#### US-COPI-CC-012 · Expandir mensagem longa
- **Como** dev **quero** clicar pra expandir mensagem >300 chars **para** ler completa sem poluir thread.
- **DoD extra:** "ver mais (X chars)" colapsável; blob fetch lazy.

### Área Search cross-dev

#### US-COPI-CC-020 · Busca FULLTEXT em todas as mensagens
- **Rota:** `GET /copiloto/admin/cc-sessions/search?q=...&user=...&tool=...`
- **Controller:** `Admin\CcSessionsController@search`
- **Como** Felipe **quero** buscar "telescope crash" **para** ver como alguém resolveu antes.
- **Mecanismo:** `MATCH(content_text) AGAINST(? IN NATURAL LANGUAGE MODE)` em `mcp_cc_messages` + filtros opcionais user/tool/date
- **Result:** lista de hits (msg + session info + score), click vai pro contexto da session
- **DoD extra:** highlight do termo nos snippets; respeita RBAC (junior não vê outras sessões a menos que tenha `cc.read.team`).

#### US-COPI-CC-021 · Cmd+K command palette
- **Como** dev **quero** Ctrl+K e digitar query rápida **para** achar contexto sem sair do que estou fazendo.
- **DoD extra:** modal `cmdk` (já no projeto), top 8 hits, Enter abre o detalhe.

### Área Governança

#### US-COPI-CC-030 · Drill-down per-dev
- **Rota:** `GET /copiloto/admin/cc-sessions/dev/{user_id}`
- **Como** Wagner **quero** click no Felipe **para** ver perfil de uso CC dele.
- **Conteúdo:**
  - Sessões 30d, custo total, padrão por dia/hora
  - Top 10 tools usadas (heatmap)
  - Sessions com >R$ X (outliers)
  - Comparação vs media do time
- **DoD extra:** read-only; Wagner não edita comportamento.

#### US-COPI-CC-031 · Anomaly detection
- **Como** Wagner **quero** alerta dashboard "Felipe usou 10× a média hoje" **para** investigar.
- **Mecanismo:** job diário compara dev_today vs dev_30d_avg; flag se >3σ.
- **DoD extra:** rede em `mcp_alertas`; notificação via Centrifugo (ADR 0058).

#### US-COPI-CC-032 · Anotação humana ("útil"/"trash")
- **Como** Wagner **quero** marcar session "✨ útil" ou "🗑️ trash" **para** ranquear conhecimento.
- **DoD extra:** coluna `metadata.curated_quality` ENUM('useful','noise','duplicate','wip'); influencia ranking de `cc-search`.

### Área Watcher (ingestão)

#### US-COPI-CC-040 · Watcher Node ingere sessions JSONL local
- **Path local:** `~/.claude/projects/D--oimpresso-com/*.jsonl`
- **Endpoint:** `POST /api/cc/ingest` (Bearer mcp_*)
- **Como** Felipe **quero** rodar `npm start` no watcher uma vez **para** que minhas sessões subam pro servidor automático.
- **Mecanismo:**
  - Watcher Node (chokidar) monitora `~/.claude/projects/`
  - Pra cada `.jsonl` modificado: incremental upload (só linhas novas via offset)
  - Filtro client-side: pula `queue-operation`, hooks vazios, mensagens repetidas
  - Compactação tool_results >4KB → blobs SHA256 dedup em `mcp_cc_blobs`
- **DoD extra:** daemon Windows/Linux/macOS, retry exponencial 503/429, log local em `~/.claude/cc-watcher.log`.

#### US-COPI-CC-041 · Setup zero-fricção
- **Como** Felipe **quero** rodar 1 comando **para** ativar o watcher.
- **Comando:** `npm run cc-watcher:install` (ou `pnpm`/`bun`)
- **DoD extra:** auto-detecta token MCP do `.claude/settings.local.json`; pergunta consent na 1ª vez.

---

## 4. Layout (wireframe textual)

```
┌────────────────────────────────────────────────────────────────────┐
│  KB MCP — Sessões Claude Code do time                              │
│  ───────────────────────────────────────────────────────────────── │
│  [12 sess hoje] [R$ [redacted Tier 0] hoje] [4 devs ativos] [Top: Bash/Edit]│
│                                                                    │
│  Filtros: [dev▼] [data▼] [tool▼] [project▼] [busca: Ctrl+K]      │
├──────────────────────────────────┬─────────────────────────────────┤
│ LISTA (col-span-5)               │ PREVIEW (col-span-7)            │
│                                  │                                 │
│ ✨ Wagner • 14:32 • 2h12m        │ Wagner • 09:15 • 1h47m          │
│   Bash×42 Edit×18 Read×31        │ branch: feat/copiloto-mcp-kb    │
│   R$ [redacted Tier 0] • 234 msgs            │ project: D:\oimpresso.com       │
│   "fix DXT shell:false..."       │ ────────────────────────────── │
│                                  │ Summary: Fix react-resizable... │
│ Felipe • 13:18 • 47m             │ ────────────────────────────── │
│   Read×12 Bash×8                 │ Thread (234 msgs):              │
│   R$ [redacted Tier 0] • 67 msgs              │ ┌─ user 09:15 ──────────────┐  │
│   "investigate Hostinger..."     │ │ vou conferir mcp...        │  │
│                                  │ ├─ assistant 09:15 ──────────┤  │
│ ▶ Wagner • 09:15 • 1h47m         │ │ Vou disparar 3 agentes...  │  │
│ [SELECTED]                       │ ├─ tool_use Bash 09:16 ─────┤  │
│   Bash×35 Edit×15                │ │ ssh -4 -i ~/.ssh/...       │  │
│   R$ [redacted Tier 0] • 234 msgs             │ ├─ tool_result 09:16 ───────┤  │
│   "Fix react-resizable..."       │ │ Connected. cd domains/...  │  │
│                                  │ │ ▼ ver mais (1.2k chars)    │  │
│ ...                              │ └────────────────────────────┘  │
└──────────────────────────────────┴─────────────────────────────────┘
```

---

## 5. Endpoints (resumo)

| Método | Rota | Controller | Permission |
|---|---|---|---|
| GET | `/copiloto/admin/cc-sessions` | `Admin\CcSessionsController@index` | `cc.read.team` |
| GET | `/copiloto/admin/cc-sessions/{uuid}` | `Admin\CcSessionsController@show` | scope-based |
| GET | `/copiloto/admin/cc-sessions/search` | `Admin\CcSessionsController@search` | `cc.read.team` |
| GET | `/copiloto/admin/cc-sessions/dev/{user_id}` | `Admin\CcSessionsController@perDev` | `cc.read.all` |
| PATCH | `/copiloto/admin/cc-sessions/{uuid}/curate` | `Admin\CcSessionsController@curate` | `cc.read.all` |
| POST | `/api/cc/ingest` | `Mcp\CcIngestController@ingest` | Bearer mcp_* |

`/api/cc/ingest` já existe no projeto (ver `Modules/Jana/Http/Controllers/Mcp/CcIngestController.php`).

---

## 6. Permissions Spatie

```php
'copiloto.cc.read.self'   // ver SUAS sessões (default todos com jana.mcp.use)
'copiloto.cc.read.team'   // ver sessões do time (Felipe, Maiara)
'copiloto.cc.read.all'    // tudo (Wagner, superadmin)
'copiloto.cc.curate'      // marcar useful/noise/duplicate (Wagner only)
```

Adiciona em `McpScopesSeeder` (já existe pattern).

---

## 7. Plano de implementação (Cycle 02)

| Dia | Entrega |
|---|---|
| 1 | Permissions + `Admin\CcSessionsController` (index/show JSON) + Inertia Page split list/preview |
| 2 | Search FULLTEXT + filtros + Cmd+K command palette + paginação |
| 3 | Drill-down per-dev + KPIs topo + curate buttons |
| 4 | Watcher Node skeleton (chokidar + tail JSONL + POST `/api/cc/ingest`) |
| 5 | Watcher: dedup SHA256 + blobs compactos + retry + setup script |
| 6 | Anomaly detection job + alert via Centrifugo + smoke real Wagner+Felipe |

**Total: 6 dias úteis.** Pode rodar paralelo com F2-F7 da KB (não conflita).

---

## 8. Métricas de sucesso (revisar 30d/60d)

- **30d:** 5 devs ingerindo via watcher, ≥80% das sessões locais subindo automático
- **60d:** Felipe/Maiara/Luiz fizeram ≥3 buscas `cc-search` que pouparam re-trabalho (medir via "found previous solution" flag)
- **90d:** Anomaly detection pegou ≥1 outlier real (uso dev fora do padrão)

Se algum falhar → ADR follow-up + ajuste.

---

## 9. Riscos / trade-offs

| Risco | Mitigação |
|---|---|
| **Volume `mcp_cc_messages`** explode (1M+ rows/mês) | Particionamento por mês + retenção 1 ano + arquivamento S3 |
| **Privacidade** — code interno, credenciais em prompts | PII redactor BR + scope-required por linha + opt-out per-dev |
| **Watcher consumindo recursos local** | Throttle, batch de 100 msgs, idle quando dev fora |
| **Custo MySQL Hostinger crescer** | Já incluído no plano shared; alerta se >1GB tabela |
| **Wagner virar bottleneck de curadoria** | LLM-as-judge auto-curate (próxima iteração) |

---

## 10. Refs

- ADR 0053 — MCP server governança como produto
- ADR 0055 — Self-host Team plan equivalente Anthropic
- ADR 0056 — MCP fonte única memória Jana Claude Code
- `Modules/Jana/Http/Controllers/Mcp/CcIngestController.php` — endpoint ingest existente
- `Modules/Forja/Database/Migrations/2026_04_29_300001..3_*` — schema 3 tabelas
- `MEMORY_TEAM_ONBOARDING.md` — Sprint B watcher mencionado

---

**Última atualização:** 2026-04-30
