# Sessão 2026-04-26 fim do dia — Sprint 1 stack-alvo IA canônica + Meilisearch

**Branch alvo:** `6.7-bootstrap` (mergeado)
**PR:** [#24 — feat(copiloto): sprint 1 — adota laravel/ai SDK oficial + laravel/boost (ADR 0035)](https://github.com/wagnerra23/oimpresso.com/pull/24) — MERGED `3d64e5bb`
**Branch worktree:** `claude/dazzling-lichterman-e59b61` (continua aberta pra docs)
**Origem:** Wagner declarou stack-alvo IA como "verdade canônica + melhor ROI" e pediu execução do Sprint 1.

---

## O que foi feito

### 1. ADR 0035 — Verdade canônica
Consolidou ADRs 0031/0032/0033/0034 num meta-ADR. Documenta:
- Stack-alvo: laravel/ai + Vizra ADK + MemoriaContrato (Mem0/Meilisearch)
- Math do ROI (caminho A canônico = 7 sprints, $25-300/mês Mem0, Tier 6-7 LongMemEval)
- Compromisso: pivot futuro exige ADR de revisão com números

ADRs 0031/0032/0033/0034: header atualizado pra "VERDADE CANÔNICA (consolidada em ADR 0035)".

### 2. Sprint 1 código (PR #24 mergeado)

**Pacotes:**
- `+laravel/ai ^0.6.3` (Laravel AI SDK oficial fev/2026)
- `+laravel/boost ^2.4 --dev` (Cursor/Claude AI guidelines)

**Código novo:**
- `Modules/Copiloto/Services/Ai/LaravelAiSdkDriver.php` — `AiAdapter` orquestrando 3 agents com fallback automático pra fixtures
- `Modules/Copiloto/Ai/Agents/BriefingAgent.php` — gera briefing inicial
- `Modules/Copiloto/Ai/Agents/SugestoesMetasAgent.php` — `HasStructuredOutput` JsonSchema com enum `facil/realista/ambicioso`
- `Modules/Copiloto/Ai/Agents/ChatCopilotoAgent.php` — `messages()` lê histórico de `Conversa.mensagens`

**Removido:**
- `Modules/Copiloto/Services/Ai/LaravelAiDriver.php` (stub do módulo interno LaravelAI)

**Config:**
- `CopilotoServiceProvider`: bind do `AiAdapter` prefere `LaravelAiSdkDriver` quando `class_exists(\Laravel\Ai\AiManager::class)`
- `Modules/Copiloto/Config/config.php`: comments atualizados pra modos `auto` / `laravel_ai_sdk` / `openai_direct` (legado)

**Testes:**
- `AdapterResolverTest`: substituiu teste do stub deletado por testes do driver canônico
- 26/27 Pest passing (1 skipped: requer MySQL + spatie/permission migrado)

### 3. Meilisearch (camada C fallback do MemoriaContrato)

**Local Windows:**
- Binário: `D:\oimpresso.com\meilisearch\meilisearch.exe` (135 MB, downloaded da release oficial)
- **RODANDO** PID 31928 em `http://127.0.0.1:7700`
- Master key: `D:\oimpresso.com\meilisearch\.meilisearch-key.txt`
- Health check: `{"status":"available"}` ✅

**Hostinger:**
- Tem **Go 1.22.0** em `/opt/golang/1.22.0/bin/go` (Wagner estava certo)
- GLIBC 2.34 — **versão latest do Meilisearch (exige 2.35) não roda**
- Solução: **Meilisearch v1.10.3** (set/2024, GLIBC 2.34 compatível) em `~/meilisearch/meilisearch`
- Hybrid search + vector store builtin presentes na v1.10.3
- 🟡 **Daemon ainda não iniciado** — SSH flaky no fim da sessão

### 4. Auto-memória revisada

`MEMORY.md` (índice) + arquivos relacionados a IA atualizados com a stack-alvo canônica:
- `project_modulo_copiloto.md` — pendências do código atual atualizadas
- `project_evolutionagent_spec.md` — stack travada agora aponta `laravel/ai` (não Prism)
- `preference_modulos_prioridade.md` — alternativa de IA atualizada

### 5. CLAUDE.md + AGENTS.md
- Bloco IA do CLAUDE.md reescrito apontando pra ADR 0035 + stack canônica
- AGENTS.md ganhou linha sobre stack-alvo IA + ponteiro pro ADR 0035

---

## Pendências bloqueadas em SSH/produção

### Pendência 1 — Deploy do Sprint 1 em produção
```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  "cd domains/oimpresso.com/public_html && \
   git pull origin 6.7-bootstrap && \
   composer install && \
   php artisan optimize:clear"
```
**Composer install obrigatório** (sprint 1 mexeu em `composer.json`/`composer.lock`).

### Pendência 2 — Iniciar Meilisearch daemon no Hostinger
```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  "cd ~/meilisearch && \
   nohup ./meilisearch \
     --master-key=TFLfQX3Diuz42MydPn68AYH9Km1JbaBI \
     --http-addr=127.0.0.1:7700 \
     --db-path=./data.ms \
     --no-analytics --env=production \
     > logs/meilisearch.log 2>&1 & disown"
```
⚠️ Hostinger compartilhada pode matar processos detached fora de horário de uso. Pra produção real, considerar VPS dedicado quando tiver tração.

### Pendência 3 — Configurar `.env` produção pra IA real
```bash
# editar ~/domains/oimpresso.com/public_html/.env
OPENAI_API_KEY=sk-...                    # OU ANTHROPIC_API_KEY=sk-ant-...
COPILOTO_AI_ADAPTER=auto                 # ou laravel_ai_sdk explícito
COPILOTO_AI_DRY_RUN=false                # quando estiver pronto pra IA real
```

### Pendência 4 — Smoke manual `/copiloto`
Após pendências 1+3, abrir `https://oimpresso.com/copiloto`, mandar 1 mensagem, ver resposta real (não fixture). Verificar logs em `storage/logs/copiloto-ai.log` (canal `copiloto-ai`).

---

## Próximos sprints (roadmap canônico — ADRs 0031/0032/0035)

| Sprint | Entrega | Risco |
|---|---|---|
| **2** | `composer require vizra/vizra-adk` + `CopilotoAgent extends Vizra\Agent` substitui `LaravelAiSdkDriver` direto; migrar `copiloto_conversas` → `vizra_sessions` | Médio (migração de dados) |
| **3** | 5-10 tools registradas em `CopilotoAgent` (snapshot, criar meta, consultar apuracao) + Tenant Scope | Baixo |
| **4** | `MemoriaContrato` interface + `Mem0RestDriver` + `NullMemoriaDriver` | Médio |
| **5** | `ChatController@send` busca antes / escreve depois (job assíncrono `ExtrairFatosDaConversaJob`) | Médio |
| **6 (opt)** | Avaliar Vizra Cloud OU dashboard caseiro pra traces remotos | Baixo |
| **7** | Stress test + LGPD opt-out + tela `/copiloto/memoria` (US-COPI-MEM-012) | Baixo |
| **8-10 (cond.)** | `MeilisearchDriver` quando Mem0 mensal >$300 OU >10k memórias OU Wagner pedir | Médio |

---

## Arquivos tocados nesta sessão (nas branches)

**`6.7-bootstrap` — via merge da `claude/dazzling-lichterman-e59b61` + PR #24:**
- `CLAUDE.md`, `AGENTS.md`
- `memory/decisions/0031` a `0035` (4 atualizações + 1 novo)
- `memory/comparativos/_INDEX.md`, `comparativos/copiloto_runtime_memory_*`, `comparativos/sistemas_memoria_*`, `comparativos/stack_agente_php_*`
- `memory/sessions/2026-04-26-deploy-hero-fix-e-conflitos-memoria.md`, `memory/sessions/2026-04-26-sprint1-stack-canonica.md` (este)
- `memory/08-handoff.md`
- **Sprint 1 (via PR #24):**
  - `composer.json`, `composer.lock`
  - `Modules/Copiloto/Ai/Agents/{Briefing,Sugestoes,Chat}*.php`
  - `Modules/Copiloto/Services/Ai/LaravelAiSdkDriver.php`
  - `Modules/Copiloto/Providers/CopilotoServiceProvider.php`
  - `Modules/Copiloto/Config/config.php`
  - `tests/Feature/Modules/Copiloto/AdapterResolverTest.php`
  - DELETADO: `Modules/Copiloto/Services/Ai/LaravelAiDriver.php`

**Auto-memória `~/.claude/projects/D--oimpresso-com/memory/` (não vai pro git):**
- `MEMORY.md` (índice)
- `project_modulo_copiloto.md`
- `project_evolutionagent_spec.md`
- `preference_modulos_prioridade.md`
- `trigger_guarde_no_cofre.md`
- 9 outros (de sessões anteriores nesta thread)

**Local não-versionado:**
- `D:\oimpresso.com\meilisearch\meilisearch.exe` (binário Windows)
- `D:\oimpresso.com\meilisearch\.meilisearch-key.txt` (master key)
- `D:\oimpresso.com\meilisearch\data.ms\` (database local)
- Hostinger `~/meilisearch/meilisearch` (binário Linux v1.10.3)
- Hostinger `~/meilisearch/data.ms\` (database futura)

---

**Estado geral:** 🟢 PR #24 mergeado e Sprint 1 código em `6.7-bootstrap`; 🟡 Deploy + IA real + Meilisearch daemon Hostinger aguardam SSH estabilizar.
