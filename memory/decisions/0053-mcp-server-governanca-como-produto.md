# ADR 0053 — MCP server da empresa: governança como produto, não overhead

**Status:** Aceito
**Data:** 2026-04-29
**Decidido por:** Wagner (após pesquisa de mercado abr/2026)
**Origem:** Custo Claude Code R$ 11k/dia solo + necessidade de administrar recursos da equipe + LGPD audit + visão estratégica de "ERP com IA" (ADR 0026)
**Supersede parcialmente:** [ADR 0036 — Meilisearch first](0036-replanejamento-meilisearch-first.md) na parte de "MCP futuro condicional" (passa a P0)

---

## Contexto

### O problema que apareceu

Wagner usa Claude Code intensivamente (Sonnet/Opus). Smoke local 29-abr revelou:

- **R$ 11.006/dia** de gasto médio nos últimos 7 dias (USD 14.007 / 7 = USD 2.000/dia)
- **74% do custo** vem de `cache_read` (contexto relido) — 6.9 BILHÕES de tokens em 7 dias
- **Cache hit rate 98,4%** ✅ excelente — sem cache seria 5-10× mais caro
- **Por modelo:** Opus 99,8% / Sonnet 0,2%
- **Top projeto:** `D--oimpresso-com` USD 4.739

Equipe de 5 pessoas (Wagner + Felipe + Maíra + Luiz + Eliana) usaria CC também. **Estimativa equipe completa:** R$ 55k/dia se padrão for similar.

### Pesquisa de mercado abr/2026

Disparada via subagent (resultado em commit anterior). 3 caminhos avaliados:

1. **Skills lazy-loaded** (`.claude/skills/`) — economia rápida, zero infra. Recomendação da pesquisa: -35-50% sozinho.
2. **`laravel/mcp` server** — v0.7.0 beta-ish, zero case study público massivo, OAuth com bugs abertos no Claude Code (#10250).
3. **Tool Search built-in** — -85% overhead de tools.

**Conclusão da pesquisa pura:** Skills primeiro, MCP só com triggers (Q3/2026 ou nunca).

### Por que a pesquisa NÃO é suficiente — diretriz Wagner

> *"o mcp terá que ser construido, pois as métricas devem estar registradas e analisadas dentro do sistema de gestão, permissões e controles. Governança."*

A pesquisa avaliou MCP como **otimização de tokens**. Wagner enxerga MCP como **infraestrutura de governança da empresa**. Inversão de premissa:

| Premissa pesquisa | Premissa Wagner |
|---|---|
| MCP = otimização (R$/dia) | MCP = ativo de governança (administrar/auditar/escalar) |
| Construir só se Skills não bater meta | Construir porque é o **produto** da empresa |
| ROI medido em economia | ROI medido em capacidade administrativa |

A pesquisa estava certa **dado o framing de economia**. Wagner mudou o framing.

### Skills sozinhas não atendem governança

Skills são texto em git: zero audit log, zero filtro por usuário, zero métricas, sem revogação de acesso após clone. Pra governança real (LGPD audit, RBAC, quotas, dashboard executivo), exige servidor com auth.

---

## Decisão

Construir **MCP server da empresa** como camada operacional do oimpresso, com **governança como produto principal** e economia como efeito colateral.

### Pilares estratégicos

1. **Governança é o produto** — não otimização
2. **Métricas dentro do sistema de gestão** — MySQL UltimatePOS, queryáveis, não JSONL files
3. **RBAC integrado a Spatie roles** existente (UltimatePOS já usa)
4. **Audit log imutável** alinhado com LGPD (responder "quem acessou X em Y" em <15 dias)
5. **Capacidade externa vendável** — clientes podem conectar Claude Desktop no MCP da oimpresso (ADR 0026 ganha sustentação técnica)
6. **Skills + MCP cada um seu papel** — não competem

### Arquitetura técnica

```
[Wagner / Felipe / Luiz / Maíra Claude Code 2.x]
        │ HTTPS streamable
        ▼
[mcp.oimpresso.com] ──→ Traefik CT 100 (cert Let's Encrypt automático)
                          │
                          ▼
              [container `oimpresso-mcp` em CT 100]
              - Laravel 13 + laravel/mcp 0.7
              - Sanctum auth (compartilhado com app principal)
              - SSH tunnel persistente pro MySQL Hostinger
                          │
                          │ MySQL TLS via tunnel
                          ▼
              [Hostinger MySQL u906587222_oimpresso]
              - tabelas mcp_* (governance + memory)
              - users/business/copiloto_* read-only via MCP
              - personal_access_tokens (Sanctum compartilhado)
```

**Decisões finais (Wagner aprovou 29-abr):**

| # | Item | Decisão | Por quê |
|---|---|---|---|
| 1 | Hospedagem | CT 100 Proxmox docker | LAN <1ms, daemon persistente, isolamento Hostinger |
| 2 | Subdomínio | `mcp.oimpresso.com` via DNS API → Traefik | cert auto, mesmo padrão dos 5 containers existentes |
| 3 | App | Laravel 13 + `laravel/mcp` 0.7 | stack canônica oimpresso |
| 4 | DB | MySQL Hostinger compartilhado via SSH tunnel | autoridade central única, Sanctum compartilhado |
| 5 | Auth | Sanctum tokens emitidos pelo app principal | OAuth Claude Code tem bug aberto (#10250); migrar quando fechar |
| 6 | Permissões | Spatie `copiloto.mcp.*` (não custom) | já em uso no UltimatePOS, zero infra nova |
| 7 | **Memory storage** | **Tabela `mcp_memory_documents` + git-as-source** | Governança real: encryption at rest, audit por SELECT, permissão por linha, soft-delete LGPD, vector search pronto |
| 8 | Sync git→DB | GitHub webhook + cron 5min fallback + comando manual | <60s update, sem dependência única |
| 9 | Audit log | `mcp_audit_log` toda chamada, retenção 1 ano | LGPD + compliance |
| 10 | CC versão | 2.x (streamable HTTP) | confirmado nos JSONLs de Wagner |
| 11 | Skills paralelo | MEM-SK-1 nos primeiros 3 dias | captura economia rápida em paralelo |
| 12 | Delphi | Zero impacto físico (app separado em Proxmox) | confirma auto-mem "Delphi contrato IMUTÁVEL" |

---

## Justificativa expandida

### Pilar 1 — Governança é o produto

LGPD obriga responder "quem acessou dado X em Y data" em até 15 dias. Hoje:
- Copiloto consulta `users`, `business`, `transactions` via Eloquent normal (sem audit por chamada IA)
- Claude Code lê arquivos locais (zero rastro)
- Não há resposta defensável pra "Felipe acessou ADR de cliente X em Y data"

Com MCP server, **toda chamada IA passa por endpoint próprio com user_id + ts + ip + scope + payload**. Query SQL retorna resposta em 10s.

### Pilar 2 — Métricas dentro do sistema

Hoje gasto Claude Code está em arquivos JSONL espalhados em 5 máquinas. Sem visibilidade unificada. Sem alertas. Sem cota.

Com tabelas `mcp_audit_log` + `mcp_usage_diaria` em MySQL UltimatePOS:
- Wagner abre `/copiloto/admin/custos` aba "Equipe" e vê todo gasto
- Alerta "Felipe excedeu cota mês" dispara automático
- Defendável em conversa de cota individual

### Pilar 3 — RBAC integrado

UltimatePOS já tem Spatie roles. Adicionar permissions `copiloto.mcp.tasks.read`, `copiloto.mcp.audit.read` (admin only), `copiloto.mcp.metrics.team` (admin only) — zero infra nova.

Mapeamento:
- **Wagner** (owner): tudo
- **Felipe** (dev): técnico, sem audit/team
- **Maíra** (suporte+dev): técnico, sem credentials
- **Luiz** (iniciante): read-only, sem destrutivas
- **Eliana** (financeiro): só `copiloto.metrics.*`, `business.snapshot`

### Pilar 4 — Audit log imutável

Tabela `mcp_audit_log` registra TODA chamada com `request_id` único. Mesmo após RCE no container Proxmox, atacante precisa autenticar no MySQL Hostinger pra ler memória — cada SELECT vira linha de audit.

### Pilar 5 — Capacidade externa vendável

ADR 0026 ("ERP gráfico com IA") ganha técnica concreta:
- Larissa instala Claude Desktop e conecta `mcp.oimpresso.com` com token dela
- "Conversa com seu ERP via IA" sem você construir UI nova
- Filtro RBAC automático: ela vê só dados do business=4
- Capacidade B2B vendável (clientes pedem)

### Pilar 6 — Memory storage em DB (não filesystem)

Wagner questionou pertinentemente: "porque gastar para deixar local? poderia ficar protegido dentro do server?"

Resposta refinada — git é fonte de verdade, DB é cache governado:

```
Wagner edita ADR no editor local
  ↓ git commit + push
Hostinger main
  ↓ webhook GitHub → POST /api/mcp/sync-memory (fallback cron 5min)
  ↓ Job IndexarMemoryGitParaDb scaneia memory/, parseia frontmatter,
    redacta PII (regex CPF/CNPJ), UPSERT em mcp_memory_documents
    com history em _history (UPDATE move row antiga)
  ↓
[mcp_memory_documents] ← FONTE PROTEGIDA
  - audit por SELECT
  - permissão por linha (scope_required)
  - encryption at rest (TLS + opcional TDE)
  - soft-delete LGPD
  - embedding BLOB pronto pra vector search futuro
  ↓
MCP server (Proxmox) só lê DB (nunca filesystem)
  ↓
Claude Code recebe doc via tool decisions.fetch(slug)
```

**Container Proxmox NUNCA tem `memory/` no disco.** Se for comprometido, atacante não pega 52 ADRs num `cat`. Cada ADR exige chamada SQL autenticada que vira linha de audit.

10 capacidades de governança que isso destrava (vs filesystem):

1. Audit "quem leu o quê" mesmo após RCE
2. Permissão por documento (`scope_required`)
3. Encryption at rest nativo MySQL
4. Backup unificado com governança
5. Versionamento queryable (history)
6. PII redaction automática no sync
7. Full-text search nativo (FULLTEXT)
8. Vector search pronto (BLOB column)
9. Soft-delete LGPD configurável
10. Onboarding sem clone do repo (token + permissão)

---

## Schema de dados (9 tabelas)

```sql
-- 1. Catálogo de permissões (mapping → Spatie permissions copiloto.mcp.*)
mcp_scopes (id, slug, nome, descricao, resources_pattern, tools_pattern,
            is_destructive, business_required, timestamps)

-- 2. Mapping user → scopes (extends Spatie)
mcp_user_scopes (user_id, scope_id, business_id?, granted_by, granted_at,
                 revoked_at?, timestamps)

-- 3. Tokens (Sanctum + extensão custom)
mcp_tokens (id, user_id, name, last_used_at, expires_at, scopes_cache JSON,
            sha256_token, timestamps)

-- 4. Quotas (limite por user/período)
mcp_quotas (user_id, period: daily|monthly, kind: tokens|brl,
            limit, current_usage, reset_at, timestamps)

-- 5. Audit log IMUTÁVEL
mcp_audit_log (request_id, user_id, business_id, ts, endpoint,
               tool_or_resource, scope_required, status: ok|denied|error,
               tokens_in, tokens_out, cache_read, cache_write, custo_brl,
               ip, user_agent, claude_code_session, timestamps)

-- 6. Agregação diária
mcp_usage_diaria (user_id, business_id, dia, total_calls, total_tokens,
                  custo_brl, top_tools JSON, alertas_disparados,
                  excedeu_quota, timestamps, unique [user, dia])

-- 7. Alertas configuráveis
mcp_alertas (user_id?, business_id?, kind, threshold, canal, ativo,
             timestamps)

-- 8. NOVA: Documentos da memória (cache governado de memory/)
mcp_memory_documents (id, slug, type: adr|session|reference|spec,
                      module, title, content_md, scope_required,
                      metadata JSON, git_sha, git_path,
                      pii_redactions_count, embedding BLOB,
                      indexed_at, timestamps, softDeletes)

-- 9. NOVA: History de documentos
mcp_memory_documents_history (id, document_id, git_sha, content_md,
                              changed_at, changed_by_user_id?, timestamps)
```

---

## 12 tools + 4 resources + 2 prompts iniciais

### Tools (operações vivas, governança em cada chamada)

| Tool | Scope mínimo | Destrutiva |
|---|---|---|
| `tasks.current()` | `copiloto.mcp.tasks.read` | não |
| `decisions.search(query)` | `copiloto.mcp.decisions.read` | não |
| `decisions.fetch(slug)` | `copiloto.mcp.decisions.read` | não |
| `sessions.recent(n)` | `copiloto.mcp.sessions.read` | não |
| `copiloto.metrics_today(business_id?)` | `copiloto.mcp.metrics.read` | não |
| `copiloto.metrics_trend_30d(business_id)` | `copiloto.mcp.metrics.read` | não |
| `claude_code.usage_self()` | sempre permitido | não |
| `claude_code.usage_team(period)` | `copiloto.mcp.team.usage.read` | não |
| `business.snapshot(business_id)` | `copiloto.mcp.business.read` | não |
| `team.who_owns(file_path)` | `copiloto.mcp.team.read` | não |
| `audit.search(query, period)` | `copiloto.mcp.audit.read` (admin only) | não |
| `quota.check_self()` | sempre | não |

Limite duro 12-15 tools (pesquisa: tool pollution >30 inflam prompt).

### Resources (cacheáveis com `cache_control` 1h)

- `oimpresso://memory/handoff` (sempre fresco)
- `oimpresso://memory/current`
- `oimpresso://memory/decisions/{slug}` (queryável individualmente)
- `oimpresso://memory/sessions/{slug}`

### Prompts (templates injetáveis)

- `briefing_oimpresso(business_id?)` — primer compacto 300 tokens
- `audit_query(period, kind)` — template investigação

---

## Plano em 8 dias úteis (com Skills paralelo Dias 1-3)

| Dia | Entrega |
|---|---|
| **1** | Schema 9 migrations + Entities + factories + sync job `IndexarMemoryGitParaDb` + comando `mcp:sync-memory` + endpoint webhook |
| **2** | Container `oimpresso-mcp` em CT 100 + DNS `mcp.oimpresso.com` + cert R12 + Sanctum auth funcionando |
| **3** | 12 tools + 4 Resources + 2 Prompts (consultam `mcp_memory_documents` via SSH tunnel) |
| **4** | RBAC Spatie (`copiloto.mcp.*`) + middleware filter por scope + audit log middleware |
| **5** | Quotas + alertas + scheduler agregação diária + webhook GitHub configurado |
| **6** | UI `/copiloto/admin/governanca` + `/copiloto/admin/audit` |
| **7** | UI `/copiloto/admin/custos` aba Claude Code + sync JSONL local |
| **8** | Hardening + smoke real Wagner+Felipe+Luiz conectados + documentação playbook |

**Paralelo (Dias 1-3):** MEM-SK-1 — 6 skills novas em `.claude/skills/` capturando economia rápida (-50% prompt).

---

## Triggers de revisão

Reabrir esta ADR se ≥1 ativar:

1. Custo de manutenção MCP server > 1d/mês por mais de 3 meses → simplificar arquitetura
2. MCP spec consolidar OAuth multi-tenant (hoje é discussion) → migrar de Sanctum
3. Cliente externo conectar (LGPD compliance harden requirement) → adicionar 2FA + IP allow-list
4. Equipe passar de 5 → 10+ devs → considerar federated MCP (gateway na frente)

---

## Consequências

**Positivas:**
- Governança real (LGPD, RBAC, quotas, audit) materializada em sistema
- Métricas dentro do sistema de gestão (não JSONL files)
- Capacidade externa vendável (cliente Claude Desktop → MCP oimpresso)
- Espinha dorsal técnica do "ERP com IA" (ADR 0026)
- Economia bonus -75-85% (8-15k → 5-8k tokens prompt)

**Negativas / Trade-offs:**
- 8 dias de dev (vs 1-2 só Skills)
- Manutenção contínua: scopes, quotas, retention policy
- SSH tunnel persistente (autossh) é peça de infra a monitorar
- `laravel/mcp` ainda v0.7 — risco de breaking change na v1.0

**Riscos mitigados:**
- Pin `laravel/mcp:^0.7` evita breaking automático
- Audit log imutável previne contestação LGPD
- Container separado garante zero impacto em Delphi
- Skills paralelas capturam economia rápida mesmo se MCP atrasar

---

## Referências

- ADR 0026 — Posicionamento ERP gráfico com IA
- ADR 0030 — Credenciais NUNCA em git
- ADR 0036 — Meilisearch first (anexo benchmark + triggers)
- ADR 0046 — Gap ChatCopilotoAgent (origem MEM-HOT-2)
- ADR 0047 — Wagner solo + sprint memória
- ADR 0050 — 8 métricas obrigatórias + tabela `copiloto_memoria_metricas`
- ADR 0051 — Schema próprio + adapter + OTel GenAI
- ADR 0052 — ContextoNegocio expor múltiplos ângulos (origem MEM-FAT-1)
- Pesquisa MCP estado da arte 2026-04-29 (subagent transcript)
- `laravel/mcp` repo: github.com/laravel/mcp (v0.7.0)
- MCP 2026 Roadmap: blog.modelcontextprotocol.io/posts/2026-mcp-roadmap/
