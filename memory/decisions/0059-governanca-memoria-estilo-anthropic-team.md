# ADR 0059 — Governança da memória estilo Anthropic Team plan adaptado

**Status:** ✅ Aceita
**Data:** 2026-04-30
**Decisores:** Wagner [W]
**Tags:** governança · memoria · mcp · rbac · lgpd · team-plan

Relacionada: [ADR 0027](0027-gestao-memoria-roles-claros.md) (papéis canônicos),
[ADR 0053](0053-mcp-server-governanca-como-produto.md) (MCP governança),
[ADR 0055](0055-self-host-team-plan-equivalente-anthropic.md) (self-host equivalente),
[ADR 0056](0056-mcp-fonte-unica-memoria-copiloto-claude-code.md) (MCP fonte única),
[ADR 0057](0057-tela-team-admin-regras-governanca-tokens-mcp.md) (regras tela team).

---

## Contexto

Memória do oimpresso cresce exponencial: 488 docs Markdown, 154 ADRs (52 canônicas + 80 por módulo + 22 outros), 38 sessions, 96 references, 15 specs/audits/runbooks/changelogs/comparativos. Wagner gasta R$ 11k/dia em Claude Code; equipe de 5 (W/F/M/L/E) escala isso pra R$ 55k. Tudo precisa de governança real **estilo Anthropic Team plan**.

**Anthropic Team plan canônico** (referência da indústria):
- Workspace owner gerencia membros + roles
- Projects (knowledge bases) com files + permissions
- Per-user spend caps + analytics
- Audit log queryable + retention
- API keys revogáveis individuais
- SSO/SCIM (futuro)

Wagner: *"escolha a melhor governança. estilo Claude Team"*. Decisão arquitetural amarrando 10 áreas.

---

## Decisão

**Adotar modelo Anthropic Team plan adaptado**, com 10 pilares mapeados pra estrutura existente do oimpresso. Git permanece source-of-truth, MCP é cache governado, UI provê governance humana.

### Mapping Anthropic Team → oimpresso

| Anthropic Team plan | Equivalente oimpresso | Estado | ADR |
|---|---|---|---|
| Workspace owner | Wagner [W] (`copiloto.mcp.usage.all`) | ✅ | 0055 |
| Members + roles | Spatie permissions (`copiloto.mcp.*`) | ✅ | 0055 |
| Projects (KBs) | `mcp_memory_documents` agrupado por `module` | ✅ | 0053 |
| Files in Projects | Linhas em `mcp_memory_documents` (typed: adr/session/spec/...) | ✅ | 0053 |
| Spend caps per-user | `mcp_quotas` daily/monthly BRL block_on_exceed | ✅ | 0055 |
| Usage analytics per-user | `mcp_audit_log` + `mcp_usage_diaria` + `/copiloto/admin/team` | ✅ | 0055 |
| API keys revogáveis | `mcp_tokens` SHA256-hashed + soft-delete | ✅ | 0055/0057 |
| Audit log retention | 365d (`COPILOTO_MCP_AUDIT_RETENTION_DAYS`) | ✅ | 0053 |
| Knowledge governance UI | `/copiloto/admin/memoria` + `/admin/team` + `/admin/governanca` | 🟡 v1 | 0057 |
| SSO/SCIM | OAuth Sanctum (próximo: bug `claude-code#10250` resolver) | 🔲 | 0053 |
| Right to explanation | Soft-delete LGPD + history + audit | ✅ | 0053 |
| **Per-doc visibility** (admin/restricted) | `scope_required` + `admin_only` por linha | ✅ | 0053 |

### Os 10 pilares de governança escolhidos

#### Pilar 1 — Source-of-truth: git permanece canônico

- Edição via Cursor/Claude Code → `git commit` → `push` → webhook GitHub → MCP indexa
- **Razão:** versioning + PR + signed commits + GitHub backup automático + workflow dev intacto
- **Exceção:** "manual tags" e anotações leves (✨ útil / 🗑️ trash) viram metadata DB-only, **fora do git**, marcadas com `source='manual'`. Não bloqueiam evolução do doc.

#### Pilar 2 — Edição: dual-mode com aprovação proporcional

| Quem | Como | Approval |
|---|---|---|
| **Wagner [W]** (owner) | Direto via commit | Self-approve (ADR 0040) |
| **Felipe [F]** (sênior) | Direto via commit | Self-approve, peer-review opcional |
| **Maíra [M]** (sênior+) | Direto via commit | Peer-review com Felipe |
| **Luiz [L]** (junior) | Branch via UI ou Cursor → PR | Wagner ou Felipe approve |
| **Eliana [E]** (financeiro+IA) | UI web pra anotação leve | Sem approval (manual tags) |
| **UI users externos** (futuro) | Apenas leitura | n/a |

**Manual tags** (UI-only, sem PR): "review pendente", "lido", "útil", "obsoleto pessoal" — DB rows com `source='manual'`, não vão pro git.

#### Pilar 3 — Approval flow (PR-based pra mudanças canônicas)

- ADR/SPEC novos ou alterados → PR obrigatória
- Owner: Wagner aprova ou delega Felipe (ADR 0055)
- Auto-merge depois de approval (CI verde)
- **Hot-fix** (urgência prod): Wagner direto em main com ADR retroativo em <24h

#### Pilar 4 — Backup/recovery (3-layer redundante)

| Layer | O quê | Frequência | Recovery |
|---|---|---|---|
| **Git/GitHub** | Source canônico | Real-time (push) | `git clone` |
| **MySQL Hostinger** | Cache governado + history | Webhook em <60s | restaurar do git |
| **MySQL backup** | Dump diário | Cron 03:00 BRT (pendente F7+) | mysqldump --routines |
| **S3 archive** | Snapshot semanal (futuro) | 7d retenção | rsync --archive |

Triple-redundância: 2 layers caindo simultaneamente = ainda recuperável.

#### Pilar 5 — Retention (LGPD-aware)

- `mcp_audit_log` retention 365 dias (já em prod)
- `mcp_memory_documents` soft-delete preserva history em `_history`
- **Hard-delete LGPD** (esquecer-me): comando admin `php artisan copiloto:lgpd:esquecer --user-email=X` apaga `mcp_tokens` + `mcp_audit_log` + `copiloto_memoria_facts` + soft-deleted MemoryDocs (Cycle 02)
- Logs PII-redacted: regex BR (CPF/CNPJ/cartão/email/tel) automático no sync (ADR 0030 + IndexarMemoryGitParaDb)

#### Pilar 6 — Audit (transparência total)

- **Cada call MCP** → linha em `mcp_audit_log` (request_id único)
- **Cada doc lido** → futuro `mcp_memory_retrievals` (Cycle 02 F7) com (user, token, ip, query, score)
- **Dashboard cross-team** em `/copiloto/admin/governanca` (existente)
- **Wagner export CSV** anytime via `/admin/team/export.csv`
- **Anomaly alerts** via Centrifugo (ADR 0058) quando dev_today > 3σ dev_30d_avg (Cycle 02)

#### Pilar 7 — Visibility controls (RBAC fino)

**4 níveis de acesso pra cada recurso:**

| Permission Spatie | Quem | O que vê |
|---|---|---|
| `copiloto.mcp.use` | todo dev com token | tools básicas + próprios dados |
| `copiloto.cc.read.self` | default todos | só próprias sessões CC |
| `copiloto.cc.read.team` | F/M sêniores | sessões cross-dev (sem audit) |
| `copiloto.cc.read.all` + `*.usage.all` | Wagner/superadmin | tudo + governance |

**Per-doc:** `scope_required` (Spatie permission) + `admin_only` boolean. Doc com `admin_only=true` invisível a quem não tem `*.read.all`. Cross-tenant safety: token de `business_id=X` só vê docs/dados de X.

#### Pilar 8 — Spend caps + alertas

- `mcp_quotas` (period: daily/monthly, kind: brl, limit, block_on_exceed)
- Default novo dev: **R$ 5/dia, R$ 50/mês**, block=true
- Tiers de alerta: 50% (log), 80% (notif dashboard), 100% (HTTP 429)
- Reset: 00:00 BRT diário, dia 1 mensal
- Wagner edita anytime em `/copiloto/admin/team`

#### Pilar 9 — Token management (zero-fricção onboarding)

- **Bearer mcp_*** SHA256-hashed (raw mostrado 1×, ADR 0057)
- **DXT one-click** (Claude Desktop) — `📦 + DXT` em `/admin/team`
- **`.claude/settings.local.json`** template (Claude Code CLI) — `MEMORY_TEAM_ONBOARDING.md`
- **Revogação <30s** (ADR 0057): clique no contador "tokens ativos"
- **Distribuição segura**: Vaultwarden ou Sinal/WhatsApp E2E. ❌ email plain, Slack, GitHub Issues, SMS

#### Pilar 10 — Lifecycle de docs (status + authority)

(Implementação Cycle 02, F2 do plano KB)

- **status:** `draft | active | deprecated | superseded`
- **authority:** `canonical | reference | draft | informational`
- **Auto-promote** `hits_count >= 5` → `authority_score += 0.1` (ADR 0054 Phase 4)
- **Anti-stale:** docs `status=deprecated` ou `superseded` filtrados de retrieval default
- **Reverse links** automáticos: parsear `supersedes/related/cites` do frontmatter → `mcp_memory_relations`

---

## Alternativas consideradas

### A. SaaS Anthropic Team plan

**Por que rejeitada:**
- LGPD: dado sensível ROTA LIVRE/Larissa não pode sair do Brasil
- Custo: USD 30/dev/mês × 5 = USD 150/mês recorrente vs self-host R$ 0 (já em CT 100)
- Limitação: não permite custom MCP tools nem governance fina por business_id

### B. MCP server primário (DB virou source-of-truth)

**Por que rejeitada:**
- Perde versioning git nativo
- Quebra fluxo Cursor/VSCode dos devs
- Backup vira complexidade nova
- Recovery de desastre vira 3-step (DB → reconciliar git)

### C. Filesystem-only (descarta MCP/UI)

**Por que rejeitada:**
- Sem audit log → falha LGPD Art. 18
- Sem revogação por user (clone ≠ revogação)
- Sem analytics → invisibilidade dos R$ 11k/dia
- Sem onboarding zero-fricção pros 4 devs futuros

### D. Notion/Confluence + integração

**Por que rejeitada:**
- Lock-in fornecedor (history exportável mas custom queries não)
- Conflito de single-source com git
- Custo recorrente
- Latência adicional MCP→Notion API

---

## Consequências

### Positivas

- **Wagner mantém workflow Cursor/Claude Code** — zero atrito de adoção
- **Devs novos onboarded em 5 min** via `.dxt` + Vaultwarden token
- **Audit defensável LGPD** — query SQL em <10s responde "quem acessou X em Y data"
- **Spend visível em tempo real** — `/copiloto/admin/team` cross-team
- **Knowledge institucional** acumula via `mcp_cc_sessions` (MEM-CC-UI-1 em build)
- **Self-host = zero recorrente** (CT 100 já existe; Hostinger MySQL idem)
- **Reverse engineering** simples: git é fonte → MCP é replay determinístico

### Negativas / aceitas

- **Manual tags só em DB** (não no git) — pequeno drift aceitável; auditoria mostra quem marcou
- **Watcher Node pendente** (Sprint B) — sem ele `mcp_cc_sessions` fica vazia; Wagner pode rodar manual ingest enquanto isso
- **Wagner é gargalo** pra approve PR junior — mitigar delegando Felipe (ADR 0055)
- **SSO/SCIM ainda manual** — re-avaliar quando time crescer pra 10+ devs

### Pegadinhas operacionais

- **PII redactor é regex BR** — não pega CEP, RG. Reforçar com LLM-judge em batch (futuro)
- **`scope_required` por linha** exige disciplina de admin — fácil esquecer ao criar ADR
- **Cross-tenant** ainda não testada com 2 businesses simultâneos no MCP — testar antes de Eliana(WR2) entrar
- **Backup snapshot diário** ainda não automatizado — manual mysqldump (Cycle 02 F4 backup)

---

## Plano de execução faseado

| Fase | Conteúdo | Status | Cycle |
|---|---|---|---|
| **F0 ✅** | Pilares 1, 2 (parcial), 3, 5, 6 (parcial), 7, 8, 9 já em prod | ✅ | atual |
| **F1 ✅** | Sync expansion (Pilar 1 cobertura 488 docs) — entregue 30-abr | ✅ | atual |
| **F2 🔲** | Lifecycle/status/authority (Pilar 10) + frontmatter padronizado | 🔲 | 02 |
| **F3 🔲** | Taxonomia (tags + stakeholders) — já no on-deck O13 | 🔲 | 02 |
| **F4 🔲** | Knowledge graph (Pilar 10 reverse links) | 🔲 | 02 |
| **F5 🔲** | Chunking + retrieval scoring (Pilar 6 expandido) | 🔲 | 02 |
| **F6 🔲** | Signals dinâmicos + auto-promote (Pilar 10 fim) | 🔲 | 02 |
| **F7 🔲** | mcp_memory_retrievals + dashboard "docs mais lidos" (Pilar 6 fim) | 🔲 | 02 |
| **F8 🔲** | Watcher Node (Pilar 9 zero-fricção pra CC) — MEM-CC-UI-1 US-040/041 | 🔲 | 02 |
| **F9 🔲** | LGPD hard-delete + S3 backup + anomaly Centrifugo (Pilares 5/6) | 🔲 | 03 |
| **F10 🔲** | SSO/SCIM (Pilar 9 enterprise) | 🔲 | 04+ |

Cycle 01 entregou F0+F1 (governance funcional). Cycle 02 fecha F2-F8. Cycle 03+ é hardening.

---

## Métricas de sucesso

| Métrica | Alvo Cycle 02 | Alvo Cycle 04 |
|---|---|---|
| Devs onboarded com `.dxt` | 5/5 | 10/10 (clientes externos) |
| % calls MCP com audit log | 100% | 100% |
| Mediana tempo "quem acessou X" | <10s | <5s |
| LGPD hard-delete tested | 1× simulada | 1× real |
| Anomaly detection caught | ≥1 outlier real | ≥3/mês |
| Drift git ↔ DB | 0 docs órfãos | 0 |
| Custo IA cap respeitado | 100% (block_on_exceed=true) | 100% |
| Knowledge institucional | 50+ buscas `cc-search` que pouparam re-trabalho | 200+/mês |

Se algum falhar → ADR follow-up + ajuste.

---

## Refs externas

- [Anthropic Team plan](https://www.anthropic.com/api/team) (referência canônica)
- [GDPR Art. 15 right of access](https://gdpr-info.eu/art-15-gdpr/) + LGPD Art. 18
- [MCP roadmap 2026](https://blog.modelcontextprotocol.io/posts/2026-mcp-roadmap/)
- ADR 0027/0053/0055/0056/0057 internas

---

**Última atualização:** 2026-04-30
