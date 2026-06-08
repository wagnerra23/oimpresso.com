# Arquitetura · MemCofre

## Stack

- **Backend**: Laravel 13.6 · PHP 8.4 (Herd em dev, hospedagem Hostinger em produção)
- **Banco**: MySQL 8 (Herd MySQL local / Hostinger shared em produção)
- **Front**: Inertia + React 19 + Tailwind 4 + shadcn/ui
- **Padrão do módulo**: UltimatePOS via `nwidart/laravel-modules` (igual `Modules/PontoWr2`, `Modules/Officeimpresso`)
- **Ativação**: `MemCofre: true` em `modules_statuses.json`
- **Scout driver**: `database` com fulltext MySQL (evita Meilisearch dedicado na Fase 4)

## Camadas

```
┌───────────────────────────────────────────────────────────┐
│ UI (Inertia + React 19 + shadcn/ui)                       │
│ resources/js/Pages/MemCofre/                              │
│   Dashboard · Ingest · Inbox · Modulo · Memoria · Chat    │
├───────────────────────────────────────────────────────────┤
│ HTTP (8 Controllers)                                      │
│ Modules/MemCofre/Http/Controllers/                        │
│   Dashboard · Ingest · Inbox · Modulo · Memoria · Chat    │
│   Data · Install                                          │
├───────────────────────────────────────────────────────────┤
│ Console (7 Commands)                                      │
│ Modules/MemCofre/Console/Commands/                        │
│   SyncMemories · SyncPages · MigrateModule                │
│   AuditModule · Validate · GenTest · InstallHooks         │
├───────────────────────────────────────────────────────────┤
│ Services (5)                                              │
│   RequirementsFileReader  — parse de .md + frontmatter    │
│   ModuleAuditor           — 15 checks de qualidade        │
│   DocValidator            — 5 checks de integridade       │
│   MemoryReader            — 3 fontes de memória           │
│   ChatAssistant           — keyword search / AI stub      │
├───────────────────────────────────────────────────────────┤
│ Persistência (7 tabelas)                                  │
│   docs_sources · docs_evidences · docs_requirements       │
│   docs_links · docs_chat_messages · docs_pages            │
│   docs_validation_runs                                    │
│   + arquivos em memory/requisitos/{Modulo}/*.md           │
└───────────────────────────────────────────────────────────┘
```

## Modelo de dados

### `docs_sources`
Fonte bruta da evidência. `type` ∈ {screenshot, chat, error, file, text, url}. Guarda `storage_path`, `source_url`, `title`, `business_id`.

### `docs_evidences`
Pedaço extraído (anotado) de uma fonte. `kind` ∈ {bug, rule, flow, quote, screenshot, decision}. `status` ∈ {pending, triaged, applied, rejected, duplicate}. Aponta pra `source_id`. Tem fulltext index (migration `2026_04_22_000008`).

### `docs_requirements`
Requisito estruturado (user story ou regra Gherkin). Cache regenerável via `php artisan module:requirements` — fonte da verdade vive nos `SPEC.md` (ADR 0002).

### `docs_links`
Grafo de relações entre qualquer par `(source_type, source_id) → (target_type, target_id)`. Campos `relation` (varchar 32 — `derived_from`/`affects`/`duplicate_of`/`leads_to`) + `weight` (int) já prontos pra Fase 4 (graph viewer).

### `docs_chat_messages`
Histórico de chat por `(user_id, business_id, session_id)`. Role ∈ {user, assistant, system}. Usado pela tela `/docs/chat`.

### `docs_pages`
Registro de cada tela `.tsx` anotada com `// @docvault`. Colunas: `route`, `module`, `stories` JSON, `rules` JSON, `adrs` JSON, `tests` JSON, `last_seen_at`. Populada por `docvault:sync-pages`.

### `docs_validation_runs`
Histórico de execuções do `DocValidator`. Colunas: `health_score`, `issues` JSON (STORY_ORPHAN/RULE_NO_TEST/…), `only_module`, `ran_at`, `duration_ms`. Base pra gráfico de tendência.

## Decisões técnicas

### D1. File-based + DB espelhado
**Decisão**: specs vivem em `memory/requisitos/{Modulo}/` (arquivos .md versionados no git) E em `docs_requirements` (MySQL).

**Por quê**: arquivos .md viajam com o código no git (rastreável via `git blame`/`git log`). DB permite busca/relações sem parsear MD a cada request.

**Sincronização**: via `php artisan module:requirements` (regera o DB a partir dos arquivos).

### D2. Padrão UltimatePOS (não módulo stand-alone)
**Decisão**: MemCofre vive em `Modules/MemCofre/` seguindo nwidart/laravel-modules.

**Por quê**: consistência com os outros 20+ módulos do projeto. Facilita ativar/desativar via `modules_statuses.json`.

### D3. IA opcional e desligada por padrão
**Decisão**: `DOCVAULT_AI_ENABLED=false` no .env. Classificação é manual no Inbox.

**Por quê**: validação humana evita divergência/alucinação. IA vira sugestão (Fase 3), não automação cega.

### D4. Uma pasta por módulo com arquivos padrão + expandida
**Decisão**: `memory/requisitos/{Modulo}/` contém `README.md`, `ARCHITECTURE.md`, `SPEC.md`, `CHANGELOG.md` (4 essenciais) + `GLOSSARY.md`, `RUNBOOK.md`, `adr/`, `audits/`, `diagrams/`, `contracts/` (expandidos pelo ADR 0007).

**Por quê**: separa preocupações (arquitetura muda pouco, spec muda sempre, changelog acumula histórico). Fácil de ler — você abre o README e navega pra onde precisa.

**Retrocompat**: módulos que ainda têm só `memory/requisitos/{Modulo}.md` continuam funcionando — o reader faz fallback (ADR 0004).

### D5. Memória tripartite unificada
**Decisão**: `MemoryReader` expõe três fontes distintas no `/docs/memoria`:
- **Primer** (`CLAUDE.md`, `AGENTS.md` na raiz do repo) — ponto de entrada pra agentes de IA
- **Project** (`memory/` versionado no git) — ADRs, sessions, handoffs, requisitos
- **Claude** (`~/.claude/projects/.../memory/`) — auto-memória pessoal fora do repo

**Por quê**: agentes (Claude, Cursor) precisam de visão unificada sem garimpar 3 diretórios. Também documenta ao dev humano onde mora cada tipo de conhecimento.

**Configuração**: `CLAUDE_MEMORY_DIR` no `.env` sobrescreve a detecção automática do caminho Claude (necessário em Windows com perfil diferente).

### D6. Auditoria quantitativa + validação estrutural
**Decisão**: dois níveis de checagem separados.
- **Auditoria** (`ModuleAuditor`, 15 checks) — qualidade de UM módulo, gera score A/B/C/D/F, salva em `audits/YYYY-MM-DD.md`.
- **Validação** (`DocValidator`, 5 checks) — integridade GLOBAL de ligações (story↔página, regra↔teste), grava `docs_validation_runs`, rodável em CI.

**Por quê**: conceitos diferentes. Auditoria responde "esse módulo está bem documentado?". Validação responde "o grafo está íntegro?". Juntar viraria noise.

### D7. Fallback offline de chat
**Decisão**: `ChatAssistant` tem dois modos. Offline (default) faz keyword search + scoring sobre README+ARCHITECTURE+SPEC+CHANGELOG+ADRs de todos os módulos. AI só liga com `DOCVAULT_AI_ENABLED=true` + `OPENAI_API_KEY`.

**Por quê**: evita custo/latência em dev e em produção sem validação. Usuário sempre tem resposta útil (mesmo sem IA) citando fonte por módulo+arquivo.

## Fluxos principais

### F1. Ingestão de evidência
```
Usuário → Ingest.tsx → IngestController::store
  → salva DocSource (arquivo/URL/texto)
  → cria DocEvidence (status=pending, módulo inferido do contexto)
  → redireciona pra Inbox
```

### F2. Triagem no Inbox
```
Usuário no Inbox.tsx → escolhe evidência
  → define kind, module_target, suggested_story_id
  → status: pending → triaged → applied
  → triaged_by + triaged_at carimbados
```

### F3. Apply (manual na Fase 1, automático na Fase 3)
```
Fase 1: botão "Apply" só marca status=applied
Fase 3: ao apply, o sistema edita memory/requisitos/{Mod}/SPEC.md
  adicionando a story/regra sugerida, e regrava o .md.
```

### F4. Leitura de um módulo
```
Usuário → /docs/modulos/{Nome} → ModuloController::show
  → RequirementsFileReader::readModule(Nome)
    → tenta memory/requisitos/{Nome}/SPEC.md + README + ARCHITECTURE + CHANGELOG (pasta)
    → fallback pra memory/requisitos/{Nome}.md (plano)
  → parse frontmatter YAML + stories (regex US-XXX-NNN) + rules (regex R-XXX-NNN)
  → lê ADRs de adr/*.md, auditorias de audits/*.md
  → retorna 5+ props pro Inertia (raw + parsed + architecture + changelog + adrs)
```

### F5. Sync de memórias (scheduled diário)
```
Scheduler 23h → php artisan docvault:sync-memories
  → lê as 3 fontes via MemoryReader
  → indexa títulos + contagem de arquivos por seção
  → popula DocSource entries com type=memory
```

### F6. Sync de telas (ao salvar .tsx)
```
CLI/CI/hook → php artisan docvault:sync-pages
  → varre resources/js/Pages/**/*.tsx
  → procura bloco "// @docvault" no topo
  → upsert em docs_pages (1 linha por tela anotada)
  → DocValidator usa pra check PAGE_NO_META / STORY_ORPHAN
```

### F7. Auditoria sob demanda
```
Dev → php artisan docvault:audit-module MemCofre --save
  → ModuleAuditor aplica 15 checks no módulo
  → calcula score 0-100 ponderado + classificação (A/B/C/D/F)
  → imprime tabela markdown no terminal
  → se --save, grava memory/requisitos/MemCofre/audits/2026-04-24.md
```

### F8. Validação global (CI/pre-commit)
```
CI/pre-commit → php artisan docvault:validate [--module=X]
  → DocValidator varre 5 checks
  → persiste DocValidationRun (health_score + issues)
  → exit code != 0 se abaixo de threshold
```

### F9. Chat assistente
```
Usuário → Chat.tsx → POST /docs/chat/ask
  → ChatController::ask
  → ChatAssistant::ask(pergunta, escopo)
    → MODO OFFLINE: keyword ranking sobre arquivos .md (fonte citada)
    → MODO AI (stub): prepara prompt com top-N chunks, chama OpenAI
  → persiste em docs_chat_messages
  → retorna resposta + citações
```

## Pontos de extensão (futuro)

- **Scout + MySQL fulltext**: já ativo nos 3 modelos (`DocSource`, `DocEvidence`, `DocRequirement`). Busca de similaridade sem +1 serviço.
- **Meilisearch** (opcional, quando volume > 5k): vector + hybrid search — swap de driver Scout, zero mudança de código.
- **Graph viewer**: react-force-graph em cima de `docs_links`. `relation` e `weight` já prontos no schema.
- **Auto-apply IA**: `ClassifierAgent` preenche sugestões no Inbox; `ApplierAgent` regrava .md (ADR 0006 itens B1-B3).
- **Integração OpenAI real**: `ChatAssistant::askWithAi()` ainda é stub (RUNBOOK item "Chat retorna desabilitado").
- **Webhook de CI**: expor `docvault:validate --format=json` pro GitHub Actions reportar no PR.
