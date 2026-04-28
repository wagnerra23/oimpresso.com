# 08 — Handoff

> **Este é o arquivo que você lê PRIMEIRO quando retoma o trabalho.**
>
> Ele sempre reflete o estado mais recente. É sobrescrito a cada sessão.
> Para ver o que mudou ao longo do tempo, consulte `sessions/`.

---

## 🚀 Começo Rápido — leia isso primeiro

**Repo:** `D:\oimpresso.com` · **Branch ativa:** `main` (promoção `6.7-bootstrap`→`main` em 2026-04-27, ver ADR 0038) · **Última sessão:** 2026-04-27 noite (promoção branch + cleanup ADR 0024)

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

**Última atualização:** 2026-04-27 noite (promoção `6.7-bootstrap` → `main` + cleanup ADR 0024 duplicado)
**Estado geral:** 🟢 branch principal agora é `main` (ADR 0038); 🟢 ADR 0024 duplicado resolvido (renomeado pra 0029); 🟡 workflows CI (`deploy.yml`, `quick-sync.yml`) e CLAUDE.md ainda referenciam `6.7-bootstrap` — PR de cleanup pendente; 🟡 PR #18 DRAFT vai precisar rebase
