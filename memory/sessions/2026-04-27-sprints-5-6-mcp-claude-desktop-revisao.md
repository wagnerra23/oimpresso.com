# Sessão 2026-04-26/27 (sessões 18+19) — Sprints 5-6 + revisão Capterra + Claude Desktop

**Branch trabalho:** `claude/dazzling-lichterman-e59b61` (worktree)
**Branch alvo:** `6.7-bootstrap` (todos PRs mergeados)
**Origem:** Wagner pediu sequência: continuar Sprint 5 (bridge) → Sprint 6 (LGPD) → "aumente score Copiloto no MemCofre" → "profissionalize... descreva nível enterprise" → "compare com Claude Desktop... detalhe e compare" → "consolide tudo na memória pra iniciar amanhã".

---

## 5 PRs mergeados em sequência

| PR | Sprint | Conteúdo principal | Tests Pest |
|---|---|---|---|
| [#26](https://github.com/wagnerra23/oimpresso.com/pull/26) | 5 | Bridge memória↔chat (`recallMemoria()` + `ExtrairFatosDaConversaJob` + `ExtrairFatosAgent`) | 43 passed (3 skipped) |
| [#27](https://github.com/wagnerra23/oimpresso.com/pull/27) | 6 | Tela `/copiloto/memoria` LGPD US-COPI-MEM-012 (`MemoriaController` + `Pages/Copiloto/Memoria.tsx`) | 48 passed (3 skipped) |
| [#28](https://github.com/wagnerra23/oimpresso.com/pull/28) | — | MemCofre score: 3 telas Copiloto com `@memcofre` block sincronizadas em `docs_pages` | — |
| [#29](https://github.com/wagnerra23/oimpresso.com/pull/29) | — | `memory/requisitos/Copiloto/ENTERPRISE.md` (12 seções enterprise) | — |
| direto branch | — | Comparativo Capterra `claude_desktop_vs_laravel_mcp_oimpresso` + `revisao_caminho_2026_04_27_capterra` + ADR 0037 | — |

Todos mergeados em `6.7-bootstrap`. **Deploy SSH parcial confirmado:** PR #25 + PR #28 deployados (`memcofre:sync-pages` rodou e populou `docs_pages` com as 14 telas que têm bloco). PRs #26/#27/#29 ainda precisam de deploy SSH (`composer install` + `php artisan migrate` + `optimize:clear`).

---

## 4 ADRs novos nesta sessão

| ADR | Status | O que decide |
|---|---|---|
| 0035 (sessão 17) | ✅ canônica | Stack-alvo IA: laravel/ai + Vizra ADK + Mem0/Meilisearch — Wagner declarou *"melhor ROI"* |
| 0036 | ✅ canônica | **Replanejamento Meilisearch first, Mem0 último** — economiza R$1.500-18.000/ano até validar tese |
| 0037 | ✅ aceita | Roadmap evolução Tier 5-6 → Tier 7-9 LongMemEval (5 sprints sequenciais 7-11 com gates RAGAS) |

Total ADRs Copiloto consolidados: **0026, 0027, 0030, 0031, 0032, 0033, 0034, 0035, 0036, 0037** — formato Nygard, todos com revisão cruzada.

---

## 4 comparativos Capterra (cofre `memory/comparativos/`)

| Arquivo | Foco | Recomendação principal |
|---|---|---|
| `_TEMPLATE_capterra_oimpresso.md` v1.0 | template oficial | — |
| `oimpresso_vs_concorrentes_capterra_2026_04_25.md` | Produto vs Mubisys/Zênite/Calcgraf/etc | ADR 0026 — posicionamento ERP gráfico com IA |
| `sistemas_memoria_oimpresso_capterra_2026_04_26.md` | 9 sistemas de memória DEV (camada A) | Federação por papel — ADR 0027 |
| `copiloto_runtime_memory_vs_mem0_langgraph_letta_zep_capterra_2026_04_26.md` | 5 frameworks memória runtime (camada C) | REST adapter pra Mem0 — depois revisado |
| `stack_agente_php_vizra_prism_mem0_capterra_2026_04_26.md` | Stack completa A+B+C (7 players) | ADRs 0031/0032/0033/0034 |
| **`revisao_caminho_2026_04_27_capterra.md`** | Auditoria pós-sprint 6 (5 caminhos) | **Validar com Larissa ANTES** de sprint 7 |
| **`claude_desktop_vs_laravel_mcp_oimpresso_2026_04_27.md`** | Plugins Claude Desktop vs nossa stack | Sprint 7 alternativo: MCP server (vácuo no vertical brasileiro) |

---

## Estado da stack-alvo (verdade canônica ADR 0035 + revisões)

| Camada | Pacote | Status |
|---|---|---|
| **A — LLM Wrapper** | `laravel/ai ^0.6.3` | ✅ instalado + driver `LaravelAiSdkDriver` em prod |
| **B — Framework agente** | `vizra/vizra-adk` | 🔴 incompat L13 (sem issue aberta upstream) |
| **B — alternativa atual** | `LaravelAiSdkDriver` + 4 Agents (Briefing/Sugestoes/Chat/ExtrairFatos) | ✅ sustenta sozinho |
| **C — Memória default** | `MeilisearchDriver` (Scout + Meilisearch self-hosted) | ✅ implementado |
| **C — Memória dev** | `NullMemoriaDriver` | ✅ implementado |
| **C — Memória condicional** | `Mem0RestDriver` (sprint 8+) | ⏸️ aguardando trigger ADR 0036 |
| **Bridge Hot/Cold** | `recallMemoria()` síncrono + `ExtrairFatosDaConversaJob` async via Horizon | ✅ implementado (PR #26) |
| **Tela LGPD** | `/copiloto/memoria` (Inertia/React + soft delete) | ✅ implementado (PR #27) |
| **Tooling DEV** | Boost + MCP + Scout + Horizon + Telescope + Pail | ✅ instalados |
| **MemCofre integration** | 3 pages com `@memcofre` block em `docs_pages` | ✅ deployado |

---

## Estado de produção (Hostinger)

- **Branch deployada:** `6.7-bootstrap` — last `git pull` rodou e mostrou criação de PR #25 + ADR 0036 + session log + comparativos
- ✅ `composer install` rodou (132 packages)
- ✅ `php artisan migrate` rodou (`copiloto_memoria_facts` criada — 70ms)
- ✅ `php artisan optimize:clear` rodou
- ✅ **Meilisearch v1.10.3 daemon RODANDO no Hostinger** PID 632084, `/health: available`
- ✅ `php artisan memcofre:sync-pages` rodou — **14 pages sincronizadas em `docs_pages`** (3 do Copiloto)
- 🟡 **Embedder Meilisearch** ainda NÃO configurado — recall funciona em full-text mas não em hybrid semantic
- 🟡 **`.env` ainda não tem** `OPENAI_API_KEY`/`ANTHROPIC_API_KEY` setados — Copiloto está em `dry_run` (devolve fixtures)
- 🟡 **`COPILOTO_AI_DRY_RUN=false`** ainda não foi setado
- 🟡 **PRs #26/#27/#29 não foram deployados** ainda (`composer install` + migrate ainda OK; faltam clear caches + verificar)

---

## Pendências críticas pra próxima sessão (priorizadas)

### 🔴 BLOQUEANTE — antes de qualquer sprint novo

1. **Validar com Larissa do ROTA LIVRE (1-2h)** — recomendação principal da revisão Capterra. Pergunta direta: ela usa Claude Desktop? Quer memória ou outras features (PricingFpv/CT-e)? Sem isso, qualquer sprint próximo é fé.
2. **Deploy completo SSH dos PRs #26/#27/#29:**
   ```bash
   ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115
   cd domains/oimpresso.com/public_html
   git pull origin 6.7-bootstrap
   composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
   php artisan migrate --no-interaction
   php artisan optimize:clear
   ```
3. **Configurar embedder Meilisearch (1h SSH):**
   ```bash
   curl -X PATCH http://127.0.0.1:7700/indexes/copiloto_memoria_facts/settings/embedders \
     -H "Authorization: Bearer TFLfQX3Diuz42MydPn68AYH9Km1JbaBI" \
     -H "Content-Type: application/json" \
     -d '{"openai":{"source":"openAi","model":"text-embedding-3-small","apiKey":"sk-..."}}'
   ```
4. **Setar `.env` Hostinger pra IA real:**
   ```env
   OPENAI_API_KEY=sk-...
   COPILOTO_AI_ADAPTER=auto
   COPILOTO_AI_DRY_RUN=false
   COPILOTO_MEMORIA_DRIVER=auto
   SCOUT_DRIVER=meilisearch
   MEILISEARCH_HOST=http://127.0.0.1:7700
   MEILISEARCH_KEY=TFLfQX3Diuz42MydPn68AYH9Km1JbaBI
   ```
5. **Smoke manual:** abrir `https://oimpresso.com/copiloto`, mandar 1 mensagem, verificar resposta real (não fixture) + `/copiloto/memoria` lista vazia inicial.

### 🟡 DECISÃO — depende do feedback da Larissa

| Cenário Larissa | Sprint 7 sugerido | ADR base |
|---|---|---|
| "Lembrou da minha meta!" / quer mais memória | **A — RAGAS evaluation** (gate antes de otimizar) | 0037 |
| "Preciso PricingFpv / CT-e / MDF-e" | **Pivot pra ADR 0026** (caminho B revisão) | 0026 |
| "Não entendi pra que serve" | **MCP server** (caminho A do comparativo Claude Desktop) | 0036 + 0037 |
| Silêncio (não usou em 30d) | **Pivot comercial** (B do roadmap original) | 0026 |

### 🟢 OUTRAS (não urgentes)

- Vizra ADK aguardar suporte L13 upstream (sem issue aberta no GitHub vizra-ai/vizra-adk em 2026-04-27)
- Reverb (websockets) — confirmar que `BROADCAST_DRIVER=null` em prod e fazer upgrade `pusher 5→7` em PR separado
- Spatie/laravel-data — adiado, conflito `phpdocumentor/reflection 6.0`
- US-COPI-MEM-011: comando `php artisan copiloto:seed-memory --business=N --user=email` pra onboarding

---

## Como retomar amanhã (TL;DR)

1. **Ler:** `memory/08-handoff.md` (este arquivo é session log; handoff é o resumo)
2. **Verificar:** `gh pr list --state merged --limit 10` pra confirmar PRs #26/#27/#28/#29 estão merged
3. **Decidir:** validar com Larissa OU continuar deploy + sprint 7 baseado no feedback (4 cenários acima)
4. **ADRs canônicos vivos:** 0035 (verdade canônica) + 0036 (Meilisearch first) + 0037 (roadmap Tier 7+)
5. **Comparativos canônicos:** `revisao_caminho_2026_04_27_capterra.md` (recomendação validar Larissa) + `claude_desktop_vs_laravel_mcp_oimpresso_2026_04_27.md` (alternativa MCP)
6. **Estado prod:** Sprint 1 + Sprint 4 deployados; #26/#27 código mergeado mas deploy SSH pendente

---

## Arquivos tocados nesta sessão

### Repo (mergeado em `6.7-bootstrap`)
**Sprints 5/6/etc:**
- `Modules/Copiloto/Ai/Agents/{ChatCopilotoAgent,ExtrairFatosAgent}.php`
- `Modules/Copiloto/Jobs/ExtrairFatosDaConversaJob.php`
- `Modules/Copiloto/Services/Ai/LaravelAiSdkDriver.php` (recallMemoria + dispatch)
- `Modules/Copiloto/Http/Controllers/MemoriaController.php`
- `Modules/Copiloto/Http/routes.php` (3 rotas LGPD)
- `Modules/Copiloto/Config/config.php` (memoria.recall_enabled/write_enabled)
- `resources/js/Pages/Copiloto/{Chat,Dashboard,Memoria}.tsx` (+ blocos `@memcofre`)
- `tests/Feature/Modules/Copiloto/{BridgeMemoriaChatTest,MemoriaControllerTest}.php`

**Documentação:**
- `memory/decisions/0037-roadmap-evolucao-tier-7-plus.md` (NOVO)
- `memory/comparativos/revisao_caminho_2026_04_27_capterra.md` (NOVO)
- `memory/comparativos/claude_desktop_vs_laravel_mcp_oimpresso_2026_04_27.md` (NOVO)
- `memory/comparativos/_INDEX.md` (atualizado)
- `memory/requisitos/Copiloto/ENTERPRISE.md` (NOVO — 420 linhas)
- `memory/CHANGELOG.md` (sessões 15-18)
- `memory/08-handoff.md` (sessão 17)

### Auto-memória `~/.claude/projects/D--oimpresso-com/memory/` (não git)
- `reference_rag_estado_arte_2026.md` (NOVO — pesquisa profunda 2026-04-26)
- `MEMORY.md` (índice atualizado)

---

**Estado geral:** 🟢 Stack-alvo IA implementada e formalizada. 7 ADRs canônicos. 6 sprints feitos, 5 PRs merged. Tier 5-6 LongMemEval estimado.
🟡 Deploy SSH dos últimos 3 PRs pendente. Embedder Meilisearch e `.env` IA real pendentes.
🔴 Validação com Larissa = bloqueante crítico antes de sprint 7.

**Próximo passo natural:** Wagner liga pra Larissa com 3 cenários de teste prontos.
