# 08 — Handoff

> **Este é o arquivo que você lê PRIMEIRO quando retoma o trabalho.**
>
> Ele sempre reflete o estado mais recente. É sobrescrito a cada sessão.
> Para ver o que mudou ao longo do tempo, consulte `sessions/`.

---

## 🚀 Começo Rápido — leia isso primeiro

**Repo:** `D:\oimpresso.com` · **Branch ativa:** `main` · **Última sessão:** 2026-05-05 (COPI-40 cache semântico fechado — PR [#94](https://github.com/wagnerra23/oimpresso.com/pull/94) mergeado)

### 🆕 Estado pós-2026-05-06 (Governance UI completa em prod + 6 lições documentadas)

**Continuação maratona 2026-05-05/06** — totalizam **17 commits** (`b26781d9` → `5da2fc02`):

**Sessão 2026-05-06 (UI Governance + bugfix marathon):**

- ✅ `https://oimpresso.com/governance` **FUNCIONA em prod** — KPIs grid (6 métricas), ADRs pending (4 atualmente), audit highlights, links docs canônicos
- ✅ 4 Pages React criadas: `Dashboard.tsx`, `Policies.tsx`, `Audit.tsx`, `DriftAlerts.tsx`
- ✅ 4 Controllers: Dashboard + Policies (toggle inline) + Audit (filtros) + DriftAlerts (runtime scan)
- ✅ Sidebar com novo grupo **GOVERNANÇA** visível
- ✅ Lang `pt/` + `en/` (canonical UltimatePOS pattern)
- ✅ topnav i18n (governance::governance.menu.*)
- ✅ Bundles Inertia em `public/build-inertia/manifest.json` (12 entries governance)

**10 bugs encontrados + corrigidos** (sequência intensa de bugfix Wagner→commit):
1. Rotas Install URL `install/install` + action `install` (correto: `install` + `index`)
2. Query `frontmatter_json LIKE` (correto: coluna `status` direto)
3. AuditController `created_at` (canonical: `ts`)
4. DriftAlerts `mcp_alertas.category` (schema: `kind`)
5. DataController `superadmin_package` formato (key string → array com `name` field)
6. Middleware sem `'authh'` + `'SetSessionData'`
7. `mcp_skill_approvals.status` não existe (correto: `mcp_skill_versions.status='review'`)
8. Lang só em `pt-BR/` (canonical: `pt/` + `en/`)
9. Bundles Inertia faltando no manifest (build local)
10. Compliance score 8% bug aritmético (correto: 80%)

**Skill `criar-modulo` atualizada** com 4 seções novas pra próximas sessões não repetirem:
- ⚠️ Erros frequentes em DataController (formato exato)
- ⚠️ Schemas DB que controllers acessam — VERIFICAR antes de query
- ⚠️ Translations: pasta `pt/` (não `pt-BR/`)
- ⚠️ Lição registrada: PRIMEIRO comando ao iniciar criação de módulo = invocar skill `criar-modulo`

---

### Estado anterior — pós-2026-05-06 madrugada (Constituição v1.1.0 + Governance MVP)

**14 commits da maratona 2026-05-05/06** (`b26781d9` → `d8785dbb`):

**Fundação governance:**
- ✅ **Constituição v1.1.0** — 10 artigos supremos + §10.4 cascade review obrigatória ([memory/governance/CONSTITUTION.md](memory/governance/CONSTITUTION.md))
- ✅ **7 documentos governance:** _README, CONSTITUTION, TRUST-TIERS, ARCHITECTURE, ENFORCEMENT, IDENTITY-MESH, MODULE-DRIFT-MIGRATION-PLAN, audit-2026-05-05-v1.1
- ✅ **8 ADRs novas** (0078..0086) + ADR 0077 superseded por 0081

**Identity Mesh operacional:**
- ✅ Tabela `mcp_actors` + 6 actors seed (Wagner L0, Felipe/Maíra L2, Luiz/Eliana L3, claude-code-wagner-laptop ai_agent L2)
- ✅ 12 mcp_tokens com `actor_id` correto (backfill aplicado)
- ✅ McpActor Eloquent + ActorResolver service em Modules/TeamMcp/
- ✅ MyWorkTool + MyInboxTool resolver atualizado (CT 100 deployed)
- ✅ `my-work` (sem owner) e `my-inbox` voltaram a funcionar — 30 tasks + 50 unread

**Module Charter:**
- ✅ 29 SCOPE.md (1 deletado: Writebot) — 100% módulos com charter
- ✅ GUARDA anti-drift: `bin/check-scope.php` + `.githooks/pre-commit` + GitHub Action
- ✅ Trigger MySQL append-only `mcp_audit_log` (ADR 0084) — `ponto_marcacoes` já tinha

**Modules/Governance (Fase 5 MVP — backend + frontend completo):**
- ✅ Scaffold módulo completo (8 peças)
- ✅ ActionGate middleware (modo warn-only default — calibração 4 semanas)
- ✅ DashboardController + Pages/governance/Dashboard.tsx (KPIs + ADRs pending + audit highlights + quick actions)
- ✅ PoliciesController + Policies.tsx (toggle inline rules)
- ✅ AuditController + Audit.tsx (drill-down filtros período/actor/endpoint/status)
- ✅ DriftAlertsController + DriftAlerts.tsx (runtime scan + persisted alerts)
- ✅ Sidebar SIDEBAR_GROUPS reorganizado: novo grupo **GOVERNANÇA** (ADS+TeamMcp+Governance), Jana/SRS preparados pra renames

**Outras entregas:**
- ✅ Skills 16 (incluindo meta-skill-roi-erp-autonomo) — 14 com manifest trust_level + owner
- ✅ Comando `php artisan skill:scaffold "<missão>"` valida 4 testes da meta-skill antes de criar
- ✅ PII Redactor BR (regex CPF/CNPJ/email/telefone/CEP) — Art. 4 LGPD
- ✅ ADS Project (id=23) + CYCLE-02 (planning) + 6 ADS-1..6 tasks status=done com source_git_sha

**Compliance Constitution v1.1.0: 8/10 plenamente, 2/10 parcial**

| Artigo | Status |
|---|---|
| 1 Soberania | ✅ wagner=L0 root |
| 2 Multi-tenancy | ✅ |
| 3 Imutabilidade | ✅ ponto_marcacoes + mcp_audit_log triggers |
| 4 Compliance | ⚠️ PII redactor disponível, falta wire-in nos services externos |
| 5 Trust Tiers | ✅ 6 actors L0-L4 |
| 6 Identity Mesh | ✅ mcp_actors + ActorResolver |
| 7 Module Charter | ✅ 29/29 SCOPE.md + GUARDA |
| 8 Policy Gating | ⚠️ ActionGate em warn — strict após 4 semanas |
| 9 Auditoria | ✅ |
| 10 Evolução | ✅ aplicado v1.0→v1.1 com cascade audit §10.4 |

**P0 próxima sessão (deferred com transparência):**

1. **Fase 3.7 renames** — Copiloto→Jana, PontoWr2→Ponto, MemCofre→SRS, ProjectMgmt→Project + 9 drift controllers (`memory/governance/MODULE-DRIFT-MIGRATION-PLAN.md`). 4-6h sessão dedicada com Pest + 301 redirects + webhook validation.
2. **ActionGate gradual rollout** em rotas L1+ existentes
3. **Mode warn → strict** após 4 semanas calibração
4. **Wagner valida visualmente** `/governance` (UI Inertia em prod após Action build-inertia-auto.yml rodar)

**Pra continuar amanhã:**
- `/governance` em prod → Painel consolidado (após Inertia build action commitar bundles)
- `git config core.hooksPath .githooks` → instala GUARDA local
- Ler `memory/governance/CONSTITUTION.md` v1.1.0

---

### Estado anterior — pós-2026-05-05 noite (COPI-40)

**Entregas:**
- ✅ **COPI-40 Semantic cache fechado** (status `done`) — implementação já existia em prod via `LaravelAiSdkDriver` (`responderChat` + `responderChatStream`); faltavam testes. PR #94 adicionou 15 tests Pest cobrindo o contrato (37 assertions, 0 regressão).
- ✅ **Bug fix bonus**: branch FULLTEXT `MATCH AGAINST` em `SemanticCacheService::buscar()` agora detecta driver e degrada graciosamente em SQLite/Postgres. Antes quebrava qualquer não-MySQL com syntax error.
- 🔓 **Cycle 01 goal #3 destravado** — cache em prod agora pode ser medido pra confirmar -68.8% tokens (ADR 0037 Sprint 8).

**Contexto sessão anterior (mesma data, finalizada antes):**
- ✅ Triagem 135 tasks + 17 canceladas — triage MCP zerada
- ✅ 17 Epics em 14 projects (3 novos: NFSE/ACCO/AI), distribuídos Q2/Q3/Q4
- ✅ ADR 0071 — auditoria 18 tools MCP (13 OK, 5 com bugs/auth-degradação)

**P0 pra próxima sessão (cycle 01 vence 12-mai, 7 dias):**
- **COPI-43** PII redactor BR (LGPD-blocker) p0
- **A4 rodada 2** Larissa — repetir 3 perguntas (vendi/líquido/caixa) → 3 respostas distintas em prod
- **COPI-22** driver MCP no Copiloto (já doing, due 06-mai amanhã)
- **10 testes pré-existentes falhando** em `tests/Feature/Modules/Copiloto/Mcp/` — não tocados nesta sessão; investigar quando der

**Atenção crítica:** **NÃO RODAR `php artisan mcp:tasks:sync`** até PROJECT-3 (frontmatter YAML SPECs, escalar pra p2) fechar. Parser sobrescreve triagem 05-mai. Ver ADR 0071 §B3.

> **⚠️ Sessão 29-abr noite estourou ~970K tokens** — ver `HOW_TO_ASK_CLAUDE.md` na raiz do repo pra padrão correto. **Próximas sessões:** sempre `/clear` ao trocar de escopo, `/compact` após cada feature, e perguntas com arquivo+linha+o-que-mudar.

### 🆕 Estado pós-29-abr noite

**Entregas (commits `e3ea5b92`→`c807d5db`):**
- ✅ ADR 0054 (pacote enterprise busca memória) + ADR 0055 (self-host equiv Anthropic Team plan) + ADR 0056 (MCP fonte única)
- ✅ Self-host Team plan: TeamController + 5 entities Mcp + QuotaEnforcer (brl/calls/tokens) + alertas idempotentes + middleware popular custo
- ✅ MCP fonte única memória: `McpMemoriaDriver` com fallback Meilisearch + tool MCP `memoria-search` + comando `copiloto:mcp:system-token`
- ✅ Onboarding time: `.mcp.json` + `.claude/settings.local.json.example` + skill `oimpresso-team-onboarding` + `MEMORY_TEAM_ONBOARDING.md`
- ✅ Sprint B Claude Code: 3 tabelas `mcp_cc_*` em prod + tool MCP `cc-search` + skill `oimpresso-cc-watcher-setup` (orquestra watcher local)
- ✅ MCP server CT 100: agora expõe **7 tools** (5 originais + `memoria-search` + `cc-search`)

**Pendências manuais (curtas, NÃO requer mais código):**
1. `ssh hostinger && php artisan copiloto:mcp:system-token --user-email=wagner@…` → copia token raw
2. Add `COPILOTO_MEMORIA_DRIVER=mcp` + `COPILOTO_MCP_SYSTEM_TOKEN=mcp_xxx` em `.env` Hostinger
3. Smoke chat real → recall via MCP
4. Wagner abre Claude Code local e roda skill `oimpresso-cc-watcher-setup` 1× → ingere ~83 sessões


**Rodar local:**
```bash
cd D:\oimpresso.com
# rodando em https://oimpresso.test (Herd + Laragon MySQL)
# login: WR23 / Wscrct*2312
# Meilisearch local em http://127.0.0.1:7700 (PID auto, ver D:\oimpresso.com\meilisearch\)
```

**Stack real:** Laravel **13.6** · PHP 8.4 (Herd) · MySQL Laragon · DB `oimpresso` · Inertia **v3** + React + Tailwind 4 · Pest v4 + PHPUnit v12 · nWidart/laravel-modules ^10

**Stack IA (verdade canônica ADR 0035 + 0036):**
- A = `laravel/ai ^0.6.3` (oficial fev/2026)
- B = `LaravelAiSdkDriver` + 4 Agents (Vizra ADK aguarda L13)
- C = `MemoriaContrato` + `MeilisearchDriver` default + `NullDriver` dev (Mem0 sprint 8+ condicional)
- Tooling = Boost + MCP + Scout + Horizon + Telescope + Pail

---

## 🎯 PRA INICIAR (2026-04-29+) — **LEIA ESSA SEÇÃO**

### ✅ Estado em 2026-04-28 fim do dia

**Infra docker-host CT 100 (192.168.0.50)** — 5 containers rodando, todos acessíveis publicamente via Traefik+TLS LE:
- `traefik.oimpresso.com` (dashboard) ✅
- `portainer.oimpresso.com` (admin: `Infra@Docker2026!`) ✅
- `vault.oimpresso.com` (Wagner tem conta; signups OFF) ✅
- `reverb.oimpresso.com` (WebSocket; KEY/SECRET no Hostinger .env) ✅
- `meilisearch.oimpresso.com` (TLS R12 ativo; embedder OpenAI configurado) ✅

**Hostinger .env (oimpresso.com app)** — IA real ativa em prod:
- ✅ OPENAI_API_KEY presente (gpt-4o-mini)
- ✅ MEILISEARCH_HOST=https://meilisearch.oimpresso.com + KEY
- ✅ SCOUT_DRIVER=meilisearch + COPILOTO_AI_*
- ✅ BROADCAST_CONNECTION=reverb + REVERB_APP_KEY/SECRET

**Validado em prod:** Wagner testou /copiloto/chat na conta da Larissa biz=4 — IA responde em PT-BR, não cai mais no fallback "sem conexão".

### 🟡 Gaps de produto (próximo Cycle 02)

1. **`ChatCopilotoAgent` "burrinho"** ([ADR 0046](decisions/0046-chat-agent-gap-contexto-rico.md)) — não tem contexto sobre faturamento/clientes/metas. Larissa pergunta "qual o faturamento desse mês?" e o agent pede pra ela informar período. Resolver com **tools/function-calling** (laravel/ai suporta) OU injetando `ContextoNegocio` no system prompt.

2. **`MeilisearchDriver::buscar` usa Scout default** — só full-text, sem hybrid embedder. Recall não traz semantic matches em prod. Fix: override Scout `search()` callback pra passar `hybrid:{embedder,semanticRatio}`. Curl direto na API Meilisearch funciona perfeito (semanticHitCount=2).

### 🔴 Único bloqueio crítico restante

**Validar com Larissa do ROTA LIVRE (1-2h)** — determina Sprint 7:
1. Pergunta sobre meta atual
2. Conversa >15 turnos (testa contexto longo)
3. Corrige um fato (testa LGPD `/copiloto/memoria`)

Larissa **provavelmente vai descobrir o Gap 1 acima** — e isso é OK, vira input pro Cycle 02.

Resposta dela determina sprint 7:

| Feedback Larissa | Sprint 7 = | ADR base |
|---|---|---|
| "Lembrou minha meta!" / quer + memória | **A — RAGAS evaluation** | 0037 |
| "Preciso PricingFpv/CT-e" | **Pivot ADR 0026** (caminho B) | 0026 |
| "Não entendi pra que serve" | **MCP server pro Claude Desktop** | 0036 + comparativo 2026-04-27 |
| Silêncio em 30d | **Pivot comercial** | 0026 |

### 🟡 Operacional (antes/depois da call)

**Deploy completo SSH (PRs #26/#27/#29 ainda pendentes):**
```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115
cd domains/oimpresso.com/public_html
git pull origin main
composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
php artisan migrate --no-interaction
php artisan optimize:clear
```

**Configurar embedder Meilisearch (1h):**
```bash
curl -X PATCH http://127.0.0.1:7700/indexes/copiloto_memoria_facts/settings/embedders \
  -H "Authorization: Bearer TFLfQX3Diuz42MydPn68AYH9Km1JbaBI" \
  -H "Content-Type: application/json" \
  -d '{"openai":{"source":"openAi","model":"text-embedding-3-small","apiKey":"sk-..."}}'
```

**`.env` Hostinger pra IA real:**
```env
OPENAI_API_KEY=sk-...           # Wagner gera no platform.openai.com/api-keys
COPILOTO_AI_ADAPTER=auto
COPILOTO_AI_DRY_RUN=false
COPILOTO_MEMORIA_DRIVER=auto
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=TFLfQX3Diuz42MydPn68AYH9Km1JbaBI
```

**Smoke manual:** abrir https://oimpresso.com/copiloto + mandar 1 mensagem + confirmar resposta real (não fixture).

---

## 📚 ADRs canônicos (memory/decisions/) — leitura obrigatória

| ADR | Tema | Status |
|---|---|---|
| 0026 | Posicionamento "ERP gráfico com IA" | ✅ |
| **0029** | **Padrão Inertia + React + UPos** (era 0024 duplicado, renomeado em 2026-04-27) | ✅ |
| 0027 | Gestão de memória (papéis canônicos) | ✅ |
| 0028 | ADRs numeração monotônica | ✅ |
| 0030 | Credenciais nunca em git | ✅ |
| 0031 | `MemoriaContrato` interface | ✅ revisado por 0036 |
| 0032 | Vizra ADK + Prism | ✅ sprint 1 revisado por 0034 |
| 0033 | Vector store backend | ✅ revisado por 0036 |
| 0034 | Laravel AI ecosystem 2026 | ✅ |
| **0035** | **Stack canônica IA (verdade)** | ✅ Wagner *"melhor ROI"* |
| **0036** | **Replanejamento Meilisearch first** | ✅ economiza R$1.500-18k/ano |
| **0037** | **Roadmap Tier 7-9 LongMemEval** | ✅ aceita |
| **0038** | **Promoção `6.7-bootstrap` → `main`** | ✅ executada 2026-04-27 |

## 🗂️ Comparativos Capterra canônicos (memory/comparativos/)

| Arquivo | Pra quê |
|---|---|
| `_TEMPLATE_capterra_oimpresso.md` v1.0 | template oficial pra novos comparativos |
| `oimpresso_vs_concorrentes_capterra_2026_04_25.md` | Produto vs Mubisys/Zênite/Calcgraf/Calcme/Visua |
| `sistemas_memoria_oimpresso_capterra_2026_04_26.md` | Camada A — dev memory (9 sistemas) |
| `copiloto_runtime_memory_vs_mem0_*` | Camada C — memória runtime (5 frameworks) |
| `stack_agente_php_vizra_prism_mem0_*` | Stack completa A+B+C (7 players) |
| **`revisao_caminho_2026_04_27_capterra.md`** | **Auditoria pós-sprint 6** — recomenda validar Larissa |
| **`claude_desktop_vs_laravel_mcp_oimpresso_2026_04_27.md`** | **Plugins Claude Desktop** vs nossa stack — vácuo no vertical brasileiro |

## 📜 Documentos enterprise

- [memory/requisitos/Copiloto/ENTERPRISE.md](requisitos/Copiloto/ENTERPRISE.md) — overview executivo + ops + compliance LGPD (12 seções, 420 linhas)
- [memory/CHANGELOG.md](CHANGELOG.md) — Keep-a-Changelog format, sessões 15-18

---

---

## 🎯 Estado em 2026-04-26 (sessão 14 — Copiloto completo + merges financeiro)

### ✅ Mergeado em `6.7-bootstrap` nesta sessão (3 PRs fechados)

| Commit | PR | O que entrou |
|---|---|---|
| `626c5696` | #10 | `fix(financeiro)`: contas-bancarias 500 — `account_type` → `account_type_id` + fix cache Inertia em `LegacyMenuAdapter` |
| `8475603a` | #11 | `feat(financeiro)`: `/relatorios` MVP — DRE gerencial + fluxo de caixa + resumo, filtros, export CSV UTF-8, redirect `/financeiro/dashboard → /financeiro` |
| `e9cf6dc1` | #13 | `feat(copiloto)`: implementação real — OpenAiDirectDriver, SqlDriver idempotente, ApurarMetaJob, AlertaService + eventos, Pages React Chat/Dashboard/FabCopiloto, 4 arquivos de testes Pest |

> **Nota de merge:** conflitos eram todos em `public/build-inertia/` (assets compilados com hashes diferentes por branch). Estratégia: cherry-pick dos arquivos-fonte apenas; assets precisam de rebuild local (`npm run build:inertia`) após `git pull`.

### ✅ Módulo Copiloto — o que está pronto

| Peça | Arquivo(s) | Status |
|---|---|---|
| OpenAI driver | `Modules/Copiloto/Services/Ai/OpenAiDirectDriver.php` | ✅ |
| SqlDriver + hash idempotente | `Modules/Copiloto/Drivers/Sql/SqlDriver.php` | ✅ |
| ApurarMetaJob | `Modules/Copiloto/Jobs/ApurarMetaJob.php` | ✅ |
| ApuracaoService | `Modules/Copiloto/Services/ApuracaoService.php` | ✅ |
| AlertaService + evento + notificação | `Services/AlertaService.php`, `Events/`, `Notifications/`, `Listeners/` | ✅ |
| Pages React: Chat, Dashboard, FabCopiloto | `resources/js/Pages/Copiloto/` | ✅ |
| Testes Pest (SQLite in-memory) | `tests/Feature/Modules/Copiloto/` — 24 passed, 1 skipped | ✅ |

### ⚠️ O que ficou pendente no Copiloto

- `ApurarMetasAtivasJob` (scheduler que descobre todas as metas ativas) — não criado
- Drivers `php` e `http` — apenas `SqlDriver` implementado
- Wizard 3 passos `/copiloto/metas/create` — Pages React não criadas
- `SuggestionEngine`: parsear resposta JSON → criar `Sugestao` rows (stub no `ChatController::send()`)
- Testes superadmin (`copiloto.superadmin`) marcados `->skip()` — requerem MySQL + spatie/permission migrado

### ⚠️ O que ficou pendente no Financeiro

- `ContaBancariaIndexTest` e `RelatoriosTest` — não rodaram (requerem MySQL dev; validar localmente)
- Build assets desatualizados — rodar `npm run build:inertia` após `git pull`

### ⚠️ PRs #2 e #3 — NÃO mergeados (targettam `main`, não `6.7-bootstrap`)

- **PR #2** (`claude/cranky-aryabhata-8c8af7` → `main`) — branch muito antiga, verificar relevância
- **PR #3** (`feat/inertia-v3` → `main`) — branch de experimento Inertia v3; verificar se ainda relevante ou fechar

Recomendação: fechar #2 e #3 manualmente se não houver intenção de mergear para `main`.

---

## 📋 Próximos passos sugeridos

1. **Deploy em staging:** `git pull origin 6.7-bootstrap && npm run build:inertia && php artisan optimize:clear`
2. **Smoke test financeiro:** `/financeiro/contas-bancarias` (era 500 → deve ser 200); `/financeiro/relatorios` (nova tela)
3. **Ativar Copiloto:** configurar `OPENAI_API_KEY` e `COPILOTO_DRY_RUN=false` no `.env`
4. **Criar `ApurarMetasAtivasJob`** + registrar no scheduler para apuração automática diária
5. **Rebuild assets:** `npm run build:inertia` (assets compilados não foram mergeados — só fonte)

---

## 🔑 Dev local

- Site: `https://oimpresso.test` (Herd SSL)
- MySQL: Laragon `127.0.0.1:3306` root sem senha, DB `oimpresso`
- PHP: 8.4 Herd
- Branch principal: `main` (era `6.7-bootstrap` até 2026-04-27)
- Último commit: `bd74b80f` (Merge PR #36 — header scandir defensivo)

## 🧭 Comandos úteis

```bash
cd D:\oimpresso.com
git pull origin main
npm run build:inertia                # NECESSÁRIO após pull (assets não mergeados)
php artisan optimize:clear
./vendor/bin/pest tests/Feature/Modules/Copiloto/ --no-coverage  # 24 passed, 1 skipped
```

---

## Preferências Wagner

- Sempre IPv4 pra Hostinger
- PT-BR em tudo (commits, comments, labels)
- Confirmar escopo antes de implementar massivamente
- Grow = prioridade produção

---

## 🔄 Sessões 2026-04-28 (infra Reverb + Meilisearch + Vaultwarden)

**Estado pós-sessão:** PR #64 (Reverb) + PR #68 (Meilisearch compose) **mergeados em main**.

### ✅ Entregues (2026-04-28)
- **CT 100 docker-host** operacional: 5 containers rodando (`traefik`, `portainer`, `vaultwarden`, `reverb`, `meilisearch`)
- **Reverb ativo em produção** — Hostinger `.env` tem KEY/SECRET corretos; `reverb:ping` → 200 OK
- **Vaultwarden** — Wagner criou conta `wagnerra@gmail.com`; SIGNUPS desabilitado
- **Meilisearch v1.10.3** — container rodando em CT 100, volume `meilisearch-data` persistente
- **ADRs 0042/0043/0044** em main — Reverb, Docker+Traefik, Vaultwarden
- **build fix**: `npm run build` agora usa `vite.config.ts` explicitamente; `_oimpresso.scss` criado

### 🔴 Ainda pendente (próxima sessão Wagner)

1. **OPENAI_API_KEY no Hostinger** — bloqueio crítico de toda IA real (platform.openai.com/api-keys)
2. **DNS `meilisearch.oimpresso.com`** — Hostinger API HTTP 530 (Cloudflare down). Fazer manual:
   - hPanel → Domínios → oimpresso.com → DNS → A record `meilisearch` → `177.74.67.30` (Proxy OFF)
3. **`.env` Hostinger — vars Meilisearch** (após DNS propagar):
   ```env
   SCOUT_DRIVER=meilisearch
   MEILISEARCH_HOST=https://meilisearch.oimpresso.com
   MEILISEARCH_KEY=9c08945878571ecb76b70d25deb3852b
   COPILOTO_AI_ADAPTER=auto
   COPILOTO_MEMORIA_DRIVER=auto
   COPILOTO_AI_DRY_RUN=false
   ```
4. **Embedder OpenAI no índice Meilisearch** (após key + host configurados):
   ```bash
   curl -X PATCH https://meilisearch.oimpresso.com/indexes/copiloto_memoria_facts/settings/embedders \
     -H "Authorization: Bearer 9c08945878571ecb76b70d25deb3852b" \
     -H "Content-Type: application/json" \
     -d '{"openai":{"source":"openAi","model":"text-embedding-3-small","apiKey":"$OPENAI_API_KEY"}}'
   ```
5. **Migrar credenciais pro Vaultwarden** (vault.oimpresso.com — Wagner tem acesso)

### 📊 Stack de memória IA — estado-da-arte (ADR 0037 roadmap)

```
HOJE (prod): NullDriver (sem OPENAI_API_KEY) — Tier ~2 funcional
APÓS desbloqueio: MeilisearchDriver ativo — Tier 5-6 estimado
SPRINT 7: RAGAS evaluation (gate obrigatório) — mede baseline real
SPRINT 8: Semantic caching (-68.8% tokens, maior ROI)
SPRINT 9: RRF tuning (+10-15% recall)
SPRINT 10: HyDE query expansion (+15% recall)
SPRINT 11: Mem0/Zep condicional (5 triggers ADR 0036)
```

Session log completo: `memory/sessions/2026-04-28-meilisearch-vaultwarden.md`

---

## 🔄 Sessão 2026-04-27 noite — Promoção `6.7-bootstrap` → `main` + cleanup ADR 0024

- ✅ **Branch principal trocada**: `6.7-bootstrap` (326 commits únicos) promovida pra `main` via force-push (`origin/main` antigo, com 7 commits 3.7-com-nfe + city migration, foi descartado).
- ✅ **Backup preservado** em `origin/archive/main-pre-6.7-merge` (SHA `0c3a8300`) — recomendado manter por 90 dias.
- ✅ **6.7-bootstrap deletada** (local + remoto). Worktree `D:/oimpresso.com` movido pra `main`.
- ✅ **Cleanup do ADR 0024 duplicado** (pendência desde sessão 15): `0024-padrao-inertia-react-ultimatepos.md` renomeado pra `0029-...md` via `git mv`. 11 referências cruzadas atualizadas (sessions, requisitos Financeiro, 5 arquivos PHP em `Modules/Financeiro/Http/`).
- ✅ **ADR 0038** criado documentando a promoção (formato Nygard, com seção de reversão).
- ✅ **Evidência MemCofre** em `Modules/MemCofre/Database/evidences/2026-04-27-promocao-main.md` (timeline literal de comandos + SHAs).
- ✅ Auto-memórias `project_current_branch.md` e `reference_composer_install_obrigatorio_pos_deploy.md` atualizadas.
- 📝 Detalhes em [memory/sessions/2026-04-27-promocao-6-7-bootstrap-para-main.md](sessions/2026-04-27-promocao-6-7-bootstrap-para-main.md).

**Pendências:**
- 🟡 PR de cleanup pra `.github/workflows/deploy.yml` (linhas 83-89), `.github/workflows/quick-sync.yml` (linhas 9, 54) e `CLAUDE.md` (linhas 193, 194, 201) — ainda hardcoded em `6.7-bootstrap`. Wagner aguardado pra autorizar.
- 🟡 PR #18 (DRAFT) vai precisar rebase quando virar não-draft.

---

## 🔄 Sessão 18 (2026-04-26 madrugada) — Sprint 4 + ferramentas Laravel IA

- ✅ **PR #25 mergeado** em `6.7-bootstrap` (`e1d4c9de`): Sprint 4 do roadmap canônico (ADR 0036).
  - **MemoriaContrato + MeilisearchDriver + NullMemoriaDriver** implementados
  - Tabela `copiloto_memoria_facts` com schema temporal (`valid_from/until`) + LGPD soft delete
  - **Eloquent `CopilotoMemoriaFato`** com `Searchable` + `SoftDeletes`
  - **37/38 Pest passing** (11 testes novos cobrem multi-tenant, append-only temporal, LGPD opt-out)
- ✅ **Pacotes Laravel IA instalados:** `laravel/horizon` + `laravel/telescope` + `laravel/pail`
  - `Vizra ADK` ❌ adiado (exige `^11|^12`, projeto é `^13.0`); `LaravelAiSdkDriver` (PR #24) sustenta Copiloto sozinho
  - `Reverb` ❌ adiado (conflita com `pusher 5.0` lockado; `BROADCAST_DRIVER=null` em uso real, upgrade pusher 5→7 pode fazer em PR separado)
  - `spatie/laravel-data` ❌ adiado (conflito `phpdocumentor/reflection 6.0`)
- 🟡 **Deploy SSH em curso** (background) — verificar `composer install` + `php artisan migrate` no Hostinger
- 📝 Detalhes: ADR 0036 + commit `f6fefa9a`

**Pendências críticas pra próxima sessão (revisado):**

🚨 **Após deploy de Sprint 4 completar, validar:**
1. `php artisan migrate` rodou (tabela `copiloto_memoria_facts`)
2. Setar Meilisearch no `.env`: `SCOUT_DRIVER=meilisearch` + `MEILISEARCH_HOST=http://127.0.0.1:7700` + `MEILISEARCH_KEY=TFLfQX3Diuz42MydPn68AYH9Km1JbaBI`
3. Setar `OPENAI_API_KEY` (ou `ANTHROPIC_API_KEY`) no `.env`
4. Setar `COPILOTO_AI_DRY_RUN=false`
5. Configurar embedder no Meilisearch index (POST settings/embedders com OpenAI text-embedding-3-small)

📋 **Sprint 5 (próximo):** `ExtrairFatosDaConversaJob` async via Horizon + bridge `ChatController@send` → busca top-K antes / extrai fatos depois.

📋 **Sprint 6:** Tela `/copiloto/memoria` (LGPD US-COPI-MEM-012).

📋 **PRs separados pendentes:**
- Reverb: confirmar Pusher não-usado em produção (`isPusherEnabled()` em `app/Http/helpers.php`) → upgrade `pusher/pusher-php-server 5→7` + `composer require laravel/reverb`
- Vizra ADK: aguardar upstream lançar suporte L13 (sem issue aberta no GitHub vizra-ai/vizra-adk)

---

## 🔄 Sessão 17 (2026-04-26 fim do dia) — Sprint 1 stack-alvo IA canônica

- ✅ **PR #24 mergeado** em `6.7-bootstrap` (`3d64e5bb`): Sprint 1 do roadmap canônico ADR 0035.
  - `composer require laravel/ai ^0.6.3 + laravel/boost ^2.4 --dev`
  - 4 arquivos novos: `LaravelAiSdkDriver` + 3 Agents (`BriefingAgent` / `SugestoesMetasAgent` / `ChatCopilotoAgent`)
  - Stub legado `LaravelAiDriver.php` removido
  - **26/27 testes Pest passing** (1 skipped intencional)
- ✅ **ADR 0035 — verdade canônica** declarada por Wagner ("melhor ROI"). Stack-alvo: `laravel/ai` (camada A) + Vizra ADK (camada B, sprints 2-3) + `MemoriaContrato`/Mem0/Meilisearch (camada C, sprints 4-5/8-10) + Boost (DEV).
- ✅ ADRs 0031/0032/0033/0034 atualizados com header "VERDADE CANÔNICA" apontando pro 0035.
- ✅ CLAUDE.md + AGENTS.md + auto-memória relevante revisados.
- ✅ **Meilisearch local Windows** rodando em `http://127.0.0.1:7700` (PID 31928, master key `D:\oimpresso.com\meilisearch\.meilisearch-key.txt`).
- ✅ **Meilisearch v1.10.3 instalado no Hostinger** em `~/meilisearch/` (versão antiga compatível com GLIBC 2.34).
- ✅ **Deploy do PR #24 em produção CONFIRMADO** — `git pull` + `composer install` (laravel/ai + boost) + `optimize:clear` rodaram OK.
- ✅ **Meilisearch daemon RODANDO no Hostinger** — PID 632084, `http://127.0.0.1:7700/health` retornou `{"status":"available"}`, 32 workers iniciados. Log em `~/meilisearch/logs/meilisearch.log`.
- 📝 Detalhes em [memory/sessions/2026-04-26-sprint1-stack-canonica.md](sessions/2026-04-26-sprint1-stack-canonica.md).

**Pendências críticas pra próxima sessão (ordem revisada por ADR 0036 — Meilisearch first, Mem0 último):**

🚨 **Sprint 2 = DEPLOY URGENTE** (não Vizra ADK ainda):
1. Deploy SSH no Hostinger: `git pull origin 6.7-bootstrap && composer install && php artisan optimize:clear`
2. **Iniciar daemon Meilisearch no Hostinger** com nohup (comando completo em [memory/sessions/2026-04-26-sprint1-stack-canonica.md](sessions/2026-04-26-sprint1-stack-canonica.md))
3. Setar `OPENAI_API_KEY` (ou `ANTHROPIC_API_KEY`) no `.env` de produção
4. Setar `COPILOTO_AI_DRY_RUN=false`
5. Smoke manual em `/copiloto` — **resultado:** Copiloto sai de fixtures EM PRODUÇÃO

📋 **Sprints 3-7** seguem ADR 0036:
- Sprint 3: Vizra ADK + tools registry
- Sprint 4-5: **MeilisearchDriver primeiro** (não Mem0!) — R$0/mês recorrente
- Sprint 6: Tela LGPD `/copiloto/memoria`
- Sprint 7: Eval LLM-as-Judge + stress

⏭️ **Sprint 8+ CONDICIONAL:** Mem0 só se trigger ativar (dedup Meilisearch falhar, conversas longas perderem contexto, Wagner pedir explicitamente). Ver ADR 0036 pra triggers mensuráveis.

---

## 🔄 Sessão 15 (2026-04-26 noite) — Deploy Hero fix + conflitos de memória

- ✅ Deploy manual de `039a810d` em produção (Hero CMS hardcoded). Validado: HTTP 200 + bundle PT-BR.
- ✅ Comparativo Capterra de 9 sistemas de memória (15 funções) com vencedor por categoria.
- ✅ 10 conflitos de auto-memória resolvidos (Inertia v2/v3, stack IA, status módulos, SSH 65002, EvolutionAgent, CMS hidratação, ADRs lista, branch produção, Connector untracked).
- ✅ ADRs novos: 0027 (gestão memória, meta-ADR), 0028 (numeração monotônica), 0030 (credenciais nunca em git).
- ✅ CLAUDE.md ganhou seção 7 "Acesso à produção (Hostinger)" + reescrita do bloco IA.
- ✅ AGENTS.md desestaleado.
- 📝 Detalhe completo em [memory/sessions/2026-04-26-deploy-hero-fix-e-conflitos-memoria.md](sessions/2026-04-26-deploy-hero-fix-e-conflitos-memoria.md).

**Pendente:** rename ADR 0024 duplicado pra 0029 (aguarda aval); materializar ADRs 0031–0036 se aprovar; auditoria untracked Modules/Connector no servidor (SSH flaky impediu na sessão).

---

**Última atualização:** 2026-05-05 noite (triagem + roadmap + auditoria MCP — 135 tasks, 17 epics, ADR 0071, **71 ADRs total**)
**Estado geral:** 🟢 Copiloto IA real ativo prod desde 28-abr; 🟢 backlog 100% triado (0 sem owner, 0 backlog); 🟢 roadmap mapeado em 3 quarters; 🟡 5 tools MCP com auth-degradação (workarounds OK); 🟡 cache semântico COPI-40 ainda não-iniciado (handoff próxima sessão)

---

## 🔄 Sessão 16 (2026-04-28) — Reverb + Meilisearch + IA real ativa

- ✅ CT 100 docker-host LXC Debian 12 provisionado em Proxmox empresa
- ✅ Stack Docker: Traefik v3.6 + Portainer + Vaultwarden + Reverb + Meilisearch v1.10.3 (5/5 running)
- ✅ DNS criado via API canônica `developers.hostinger.com/api/dns/v1/zones/{domain}` (ADR 0045) — `api.hostinger.com` está com HTTP 530 crônico
- ✅ Cert Let's Encrypt R12 emitido pra reverb/portainer/traefik/vault/meilisearch.oimpresso.com
- ✅ OPENAI_API_KEY no Hostinger .env + SCOUT_DRIVER=meilisearch + embedder OpenAI text-embedding-3-small no índice
- ✅ `config/ai.php` commitado (era untracked → laravel/ai caía no fallback `gpt-5.4`); log channel `copiloto-ai` adicionado
- ✅ **Copiloto IA real respondendo Larissa em prod** (gpt-4o-mini)
- 🟡 Gap descoberto: ChatCopilotoAgent "burrinho" — sem ContextoNegocio (ADR 0046)
- 🟡 Gap descoberto: MeilisearchDriver::buscar usa Scout default (full-text) — `memoria_recall_chars: 0` mesmo com fato indexado
- 📝 Detalhe completo em [memory/sessions/2026-04-28-meilisearch-vaultwarden.md](sessions/2026-04-28-meilisearch-vaultwarden.md) + [memory/sessions/2026-04-28-reverb-docker-host.md](sessions/2026-04-28-reverb-docker-host.md)
- ✅ ADRs criados: 0042 (Reverb) · 0043 (Docker+Traefik) · 0044 (Vaultwarden) · 0045 (Hostinger DNS API) · 0046 (Gap ChatAgent)

---

## 🔄 Sessão 17 (2026-04-29) — Sprint memória completa: 8 entregas em 1 dia

Wagner pediu modo solo + foco em token economy + assertividade. Time delegated → todos os donos para [W].

**8 entregas em prod:**

1. **ADR 0047** Wagner solo + sprint memória priorizado (`da6ce166`)
2. **MEM-HOT-1** Hybrid embedder MeilisearchDriver (`c631042c`) — recall **0 → 190 chars** em log conversa Larissa real
3. **MEM-HOT-2** ContextoNegocio injetado no ChatCopilotoAgent (`2be9930c`) — system prompt biz=4 ROTA LIVRE com 4 meses faturamento + 5993 clientes em **164 tokens**
4. **ADRs 0048-0050 + 0036 estendida** consolidam pesquisa Wagner (ZIP `files.zip`):
   - 0048 — Vizra ADK rejeitada oficialmente (quebrou L13); **COP-015 cancelada**
   - 0049 — 6 camadas memória + gate Recall@3>0.80
   - 0050 — 8 métricas obrigatórias + tabela `copiloto_memoria_metricas`
   - 0036 anexo — benchmark BM25+vetor=95.2% LongMemEval (supera Mem0 93.4%, Zep 71.2%)
5. **ADR 0051** Schema próprio + adapter + OTel GenAI (após pesquisa de tendências) (`21644f4e`)
6. **MEM-MET-1** Tabela `copiloto_memoria_metricas` em prod com 14 colunas (8 obrigatórias + 3 RAGAS-aligned `faithfulness/answer_relevancy/context_precision` + 3 contexto)
7. **MEM-OTEL-1** Emissão `gen_ai.*` OpenTelemetry GenAI no log channel `otel-gen-ai` (`5acf27de`) — 12 atributos OTel-compliant por evento
8. **MEM-MET-2** Comando `copiloto:metrics:apurar` + baseline 2026-04-29 gravado em prod (`6d2dc7eb`+`6aa9b524`):

   ```
   | apurado_em | biz_id      | p95_ms | tokens | inter | mem | bloat | contr |
   |------------|-------------|--------|--------|-------|-----|-------|-------|
   | 2026-04-29 | NULL (plat) |   1234 |    307 |     6 |   2 | 1.000 |  0.00 |
   | 2026-04-29 |           1 |   NULL |   NULL |     0 |   0 |  NULL |  NULL |
   | 2026-04-29 |           4 |   1234 |    307 |     6 |   2 | 1.000 |  0.00 |
   ```

**Suite Copiloto:** 50 → **77 passed (+27 testes)**, 3 skipped, **zero regressão**.

**Estratégia formalizada (ADR 0051):** 4 pilares — schema próprio + adapter sobre `Laravel\Ai\Contracts\ConversationStore` + métricas RAGAS-aligned + emissão OTel GenAI. Triggers trimestrais pra reavaliar (laravel/ai 1.0 saiu 17-mar-2026 sem eval framework nem multi-tenancy).

📝 Detalhe completo: [memory/sessions/2026-04-29-sprint-memoria-completa.md](sessions/2026-04-29-sprint-memoria-completa.md)

**Pendências P0 imediatas (sex 02-mai):**
- A4 rodada 2 — Validar Larissa repetir 3 perguntas (vendi/líquido/caixa) → 3 respostas distintas
- MEM-MET-3 — scheduler diário `daily()` chama `copiloto:metrics:apurar --all` (15 min)
- COP-002 = MEM-MET-5 — Golden set 50 perguntas Larissa-style (destrava 6 colunas RAGAS)

---

## 🔄 Sessão 18 (2026-04-29 noite) — MEM-FAT-1 + ADR 0052 (validação Larissa expôs gap semântico)

Larissa testou as 3 perguntas em prod (Quanto vendi? / Faturamento líquido? / Quanto entrou no caixa?) e recebeu **mesmo R$ 31.513,29** pras 3 — gap exposto.

**Causa-raiz**: `ContextoNegocio.faturamento90d` só tinha 1 valor por mês. LLM não tinha como saber que líquido e caixa eram números diferentes.

**Fix MEM-FAT-1** (commit `fac96a19`):
- `ContextSnapshotService::faturamento90d()` retorna 3 ângulos: `bruto` (sell.final) + `liquido` (bruto - sell_return) + `caixa` (transaction_payments.amount via paid_on)
- Glossário inline no system prompt define cada métrica
- BC-compat: campo `valor` mantido como alias do bruto

**Smoke prod**: prompt biz=4 ROTA LIVRE = 270 tokens com 4 meses × 3 ângulos. Mar/2026: bruto R$ 38.215,07 · líquido R$ 37.518,47 · caixa R$ 37.141,22.

**ADR 0052** formaliza princípio: quando métrica admite múltiplos recortes legítimos, `ContextoNegocio` expõe TODOS — não confiar que LLM deriva matemática que ele não tem como fazer. Padrão replicável pra custos / lucro / inadimplência / metas.

**Aprendizado meta**: smoke técnico passou em MEM-HOT-2 (`2be9930c`) com bug semântico latente. Validação real do usuário foi o único filtro que detectou. A4 (validar Larissa) **NÃO é formalidade** — é gate de produto.

**Suite Copiloto**: 79 passed (era 77, +2), 3 skipped, zero regressão.
**52 ADRs total.**

**Última atualização:** 2026-04-29 noite — MEM-FAT-1 deployed + ADR 0052

---

## 🌟 Sessão maratona 2026-05-05 — UI Skills end-to-end (24 commits, 5 ADRs novas, ~5h)

**Contexto:** Wagner pediu pra "amadurecer memória + Team MCP" → virou pesquisa profunda + 5 ADRs + UI completa de gestão de skills do Claude Code em prod.

### Decisões arquiteturais (5 ADRs novas, 57 ADRs total)

- **[ADR 0072](decisions/0072-maturacao-memoria-team-mcp-openclaw-soa-2026.md)** — Roadmap maturação memória + Team MCP (P0–P3). 2 erratums no mesmo dia após levantamento real.
- **[ADR 0073](decisions/0073-team-mcp-skills-policies-entidades-governadas.md)** — P0 inicial. **SUPERSEDED** pelo 0076.
- **[ADR 0074](decisions/0074-temporal-validity-bi-temporal-time-travel.md)** — P1 bi-temporal. Status: proposto.
- **[ADR 0075](decisions/0075-team-mcp-skills-ui-prompt-management-style.md)** — P0 v2. **SUPERSEDED** pelo 0076.
- **[ADR 0076](decisions/0076-skills-db-primary-git-destino-drift-alert.md)** — **canônica.** DB primary, git destino, drift por-skill (auto/manual/pinned). Inversão a pedido de Wagner: "deixa eu decidir, testar, evoluir".

### Comparativo cofre

[`prompt_skill_management_2026_05_05.md`](comparativos/prompt_skill_management_2026_05_05.md) — 10 ferramentas (Langfuse/LangSmith/Humanloop/Vellum/PromptLayer/Portkey/Agenta/Helicone/Anthropic Console/Anthropic Skills) × 31 features.

### UI Skills em prod

URL: **https://oimpresso.com/ads/admin/skills**

| Rota | O que faz |
|---|---|
| `/ads/admin/skills` | Lista 15 skills (DB) + Approval queue button |
| `/ads/admin/skills/{slug}` | Detalhe + timeline versions + "Promover production" + "Publish to git" |
| `/ads/admin/skills/{slug}/edit` | Editor + 4 rationales obrigatórios + warning amber se frontmatter mudar |
| `/ads/admin/skills/{slug}/test` | Test Runner: source manual OU "últimas N conversas reais multi-tenant" + PII redactor |
| `/ads/admin/skills-review` | Approval queue: drafts + Aprovar/Rejeitar inline |

### Backend (DB-primary — ADR 0076)

**6 migrations:** `mcp_skills`, `mcp_skill_versions` (append-only, 4 rationales), `mcp_skill_labels` (Langfuse-style), `mcp_skill_test_runs`, `mcp_skill_approvals`.

**Services:** `ImportarSkillsDoGitService`, `SkillTestRunnerService` (PII redactor), `PublicarSkillNoGitService` (GitHub API), `SkillsService` (DB com fallback filesystem).

**Controller:** `SkillsController` (10 métodos: index/show/edit/store/test/runTest/review/approve/reject/publish/moveLabel).

### Permissions Spatie atribuídas

Wagner (id=1, `WR23`) tem todas 6: `read/edit/test/approve/publish/config`. Verificado em prod: `$u->can('ads.admin.skills.read') = 1` ✅

### Skills Claude Code novas

- `ads-decision-flow` — fluxo Risk→Confidence→Policy→Router→Brain A/B
- `memoria-recall-flow` — Meilisearch hybrid + 14 gotchas

### Slash command + hook + CI

- `/sync-skills` — detecta drift filesystem
- Hook `SessionStart` `check-skills-fresh.ps1` — auto-detecta drift
- GitHub Action `build-inertia-auto.yml` — auto-rebuild bundles ao push tocar `resources/{js,css}` (previne reprise do bug do sidebar)

### Status goals do CYCLE-02 (proposto, não criado em DB)

| Goal | Status |
|---|---|
| 1. Skills DB ≥16 | 🟡 15 (1 SKILL.md fora do glob — investigar) |
| 2. Versions ≥16 | 🟡 15 |
| 3. UI lista+detalhe+editor em prod | ✅ + bonus (Test, Review) |
| 4. Tool MCP `skills-search` | 🔴 não criada |
| 5. Wagner editou ≥1 skill via UI | 🔲 pendente teste real |

### Pendências P0 amanhã

1. **Wagner testar fluxo end-to-end** (Goal 5) — ~5min.
2. **Tool MCP `skills-search`** (Goal 4) — ~1h.
3. **Investigar 15 vs 16 skills** — qual SKILL.md ficou de fora.
4. **Criar CYCLE-02 oficial em DB** — SQL ou criar tool `cycles-create` (~30 linhas).
5. **CYCLE-01 fechar em 12/05** — `cycles-close CYCLE-01 --rollover-to=CYCLE-02` com retro.

### Bugs resolvidos durante a sessão

- **Sidebar build stale** — 5 commits anteriores sem `npm run build:inertia` deixaram bundles velhos. Action CI previne reprise.
- **Conflict markers no manifest** — rebase do FASE 4 vs CI deixou `<<<<<<< HEAD`. Regenerado.

**24 commits** em main: `c04eaa53` → `62be2152`. **57 ADRs total.** **6 fases UI.** **5 telas em prod HTTP 200.**

**Última atualização:** 2026-05-05 noite — UI Skills end-to-end deployed (Wagner testa amanhã)
