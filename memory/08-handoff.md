# 08 — Handoff

> **Este é o arquivo que você lê PRIMEIRO quando retoma o trabalho.**
>
> Ele sempre reflete o estado mais recente. É sobrescrito a cada sessão.
> Para ver o que mudou ao longo do tempo, consulte `sessions/`.

---

## 🚀 Começo Rápido — leia isso primeiro

**Repo:** `D:\oimpresso.com` · **Branch ativa:** `6.7-bootstrap` · **Data da última sessão:** 2026-04-26

**Rodar local:**
```bash
cd D:\oimpresso.com
# já está rodando em https://oimpresso.test (Herd + Laragon MySQL)
# login: WR23 / Wscrct*2312
```

**Stack real:** Laravel **13.6** · PHP 8.4 (Herd) · MySQL Laragon 127.0.0.1:3306 root sem senha · DB `oimpresso` · Inertia **v3** + React + Tailwind 4 · Pest v4 + PHPUnit v12 · nWidart/laravel-modules ^10

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
- Branch principal: `6.7-bootstrap`
- Último commit: `e9cf6dc1` (feat copiloto)

## 🧭 Comandos úteis

```bash
cd D:\oimpresso.com
git pull origin 6.7-bootstrap
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

**Última atualização:** 2026-04-26 noite (sessão 15 — Hero deploy + conflitos memória + ADR 0027 meta-gestão)
**Estado geral:** 🟢 `6.7-bootstrap` deployado em produção (HTTP 200, Hero PT-BR ok); 🟢 papéis das memórias formalizados (ADR 0027); 🟡 ADR 0024 duplicado aguarda rename
