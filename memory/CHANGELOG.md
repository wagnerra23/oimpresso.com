# Changelog do Projeto oimpresso.com

Registro cronológico de mudanças estruturais, decisões arquiteturais e operações
de manutenção. Diferente dos session logs (`sessions/YYYY-MM-DD-*.md`) que contam
"o que foi feito em cada sessão", este arquivo lista **eventos notáveis** de forma
enxuta e pesquisável.

Formato inspirado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).
Datas no formato `YYYY-MM-DD`. Categorias:

- **Added** — nova funcionalidade / módulo / tela
- **Changed** — mudança no comportamento existente
- **Deprecated** — marcado para remoção futura
- **Removed** — removido do codebase
- **Fixed** — bug corrigido
- **Security** — correção de segurança
- **Migration** — mudança de branch, versão, infra ou fluxo de dev
- **Decision** — decisão arquitetural (geralmente casado com ADR)

---

## [Unreleased] — branch `6.7-bootstrap`

### Decision — 2026-04-27 (revisão de caminho Capterra)
- **Comparativo `revisao_caminho_2026_04_27_capterra.md`** mapeia 5 caminhos pós-sprint 6
  (atual ADR 0037 sequencial / pivot comercial ADR 0026 / Typesense / Mem0 cedo / validar Larissa)
- **Recomendação:** validar com Larissa do ROTA LIVRE ANTES de continuar — sprint 7 pode ser
  RAGAS (continua A) OU pivota pra ADR 0026 dependendo do feedback dela
- 3 GAPs vs caminho atual: zero validação de demanda; Vizra ADK bloqueado em L13;
  embedder Meilisearch ainda não configurado em produção

### Added — 2026-04-27 (sessão 18 — Sprints 5/6 + ADR 0037 + revisão)
- **Sprint 5 (PR #26):** bridge memória↔chat dual-layer Hot/Cold
  - `Modules/Copiloto/Ai/Agents/ExtrairFatosAgent` (HasStructuredOutput, schema com 5 categorias enum)
  - `Modules/Copiloto/Jobs/ExtrairFatosDaConversaJob` (queue `copiloto-memoria`, async via Horizon)
  - `LaravelAiSdkDriver::responderChat` ganha `recallMemoria()` antes do LLM + dispatch após
  - `ChatCopilotoAgent` aceita `$memoriaContexto` injetado no system prompt
  - Config flags `copiloto.memoria.recall_enabled` e `write_enabled` (default true)
  - +8 testes Pest, 43 passed total (3 skipped intencional)
- **Sprint 6 (PR #27):** tela `/copiloto/memoria` LGPD (US-COPI-MEM-012)
  - `Modules/Copiloto/Http/Controllers/MemoriaController` (index/update/destroy via DI MemoriaContrato)
  - `resources/js/Pages/Copiloto/Memoria.tsx` Inertia/React (agrupamento por categoria, badges,
    edit inline, esquecer com confirm, AppShell + FabCopiloto)
  - 3 rotas novas: `copiloto.memoria.index/update/destroy`
  - +5 testes Pest, **48/51 total Copiloto passing**
- **ADR 0037** — roadmap evolução Tier 5-6 → Tier 7-9 LongMemEval (5 sprints sequenciais 7-11
  com gates mensuráveis: RAGAS first, depois caching/RRF/HyDE, Mem0 condicional)
- **Auto-memória** `reference_rag_estado_arte_2026.md` (cross-conversation, fora do git)
- **Pesquisa profunda 2026-04-26** documentada com 9 sources externas

### Added — 2026-04-26 (sessões 15-17 — stack canônica IA)
- **PR #24** (sprint 1): `laravel/ai ^0.6.3` + `laravel/boost ^2.4 --dev` + 3 Agents
  (Briefing/Sugestoes/Chat) + `LaravelAiSdkDriver`. Stub `LaravelAiDriver.php` deletado.
- **PR #25** (sprint 4): `MemoriaContrato` + `MemoriaPersistida` (DTO) + `MeilisearchDriver`
  (default canônico) + `NullMemoriaDriver` (dev/CI). Schema `copiloto_memoria_facts` com
  `valid_from/until` + soft delete LGPD + indexes. Eloquent `CopilotoMemoriaFato` com
  `Searchable`+`SoftDeletes`. **+3 pacotes Laravel:** horizon, telescope, pail.
- **Meilisearch local Windows** rodando em `127.0.0.1:7700` (PID 31928)
- **Meilisearch Hostinger v1.10.3** instalado em `~/meilisearch/` (GLIBC 2.34 compat)
- **Comparativos Capterra:** `sistemas_memoria_oimpresso` (camada A — dev memory),
  `copiloto_runtime_memory_vs_mem0_langgraph_letta_zep` (camada C apenas),
  `stack_agente_php_vizra_prism_mem0` (stack completa A+B+C)
- **`memory/comparativos/_INDEX.md`** com convenções de naming
- **CLAUDE.md** ganhou seção 7 "Cofre de comparativos & gestão de memória" + seção 8
  "Acesso à produção (Hostinger)" com SSH credentials (sem chave privada)
- **AGENTS.md** desestaleado (Laravel 10 → 13.6, Inertia v3, lista de módulos)
- **ADRs novos:** 0027 (gestão memória — papéis canônicos), 0028 (numeração monotônica),
  0030 (credenciais nunca em git), 0031 (`MemoriaContrato`+`Mem0RestDriver` default),
  0032 (Vizra ADK + Prism PHP — sprint 1 revisado por 0034), 0033 (vector store —
  pgvector rejeitado, Meilisearch fallback condicional → ADR 0036 promove pra default),
  0034 (Laravel AI ecosystem — `laravel/ai` oficial supersedes Prism), 0035 (verdade
  canônica IA — Wagner *"melhor ROI"*), 0036 (replanejamento Meilisearch first, Mem0
  último — economiza R$1.500-18.000/ano)

### Changed — 2026-04-26
- **Hero da landing** (`Modules/Cms`): hidratação de `cms_pages` revertida (commit
  `039a810d`), copy hardcoded PT-BR ("orça/imprime/monta/entrega") fica como decisão
  final. Tentativa anterior trouxe seed UltimatePOS em inglês.
- **`AiAdapter` bind no `CopilotoServiceProvider`**: `LaravelAiSdkDriver` virou default
  em modo `auto` (resolve quando `class_exists(\Laravel\Ai\AiManager::class)`)
- **CLAUDE.md/AGENTS.md** marcados com "VERDADE CANÔNICA" apontando pro ADR 0035

### Removed — 2026-04-26
- `Modules/Copiloto/Services/Ai/LaravelAiDriver.php` (stub do módulo interno LaravelAI —
  substituído por `LaravelAiSdkDriver` no PR #24)

### Migration — 2026-04-26
- **10 conflitos de auto-memória resolvidos** (Inertia v2/v3, status Copiloto, EvolutionAgent
  bloqueado, CMS hidratação, ADRs lista, branch produção, Connector untracked, etc)
- **Deploy SSH manual** documentado (Hostinger SSH flaky; receita pra `quick-sync.yml`
  voltar via GH secrets atualizados)

---

## [Unreleased] — branch `6.7-react`

### Added — 2026-04-22 (Essentials batch 3 — Lembretes + Feriados + Configurações)
- **Lembretes** (`/essentials/reminder`) — lista cronológica dos lembretes do
  usuário com dialog de create/edit, repetição (one_time, every_day,
  every_week, every_month), filtros de data/hora/end_time. Substitui o
  calendário FullCalendar do Blade por uma lista que é mais prática no
  cotidiano e mantém a estrutura de dados do modelo intacta
- **Feriados** (`/hrm/holiday`) — CRUD React com filtros (localidade, range
  de datas), dialog inline de create/edit, badge calculado de "N dias" por
  feriado, scope por `permitted_locations`. Apenas admin pode criar/editar
- **Configurações do Essentials** (`/hrm/settings`) — 4 cards organizados:
  Prefixos (tarefas, afastamentos, folha), Instruções de afastamento,
  Tolerâncias de ponto (4 campos grace_*), Comportamentos (switches
  `is_location_required` e comissão sem impostos). Valores persistem em
  `businesses.essentials_settings` (JSON) e refletem na sessão pra ToDoController
  ler sem refetch
- **LegacyMenuAdapter** — 3 novos prefixes: `/essentials/reminder`,
  `/hrm/holiday`, `/hrm/settings`
- **Submenu Essentials** — DataController agora inclui "Lembretes" no
  dropdown (5 itens: Tarefas, Mensagens, Documentos, KB, Lembretes). Feriados
  e Configurações ficam sob HRM pois vivem em `/hrm/*`

### Fixed — 2026-04-22 (testes destravados)
- **`accounting()` helper sem guard** (`Modules/Accounting/Helpers/general_helper.php:10`)
  causava fatal `Cannot redeclare function` no bootstrap do phpunit. Solução
  minimalista: envolver em `if (!function_exists(...))`
- **`get_days_past()` guard com nome errado** (linha 194 do mesmo arquivo):
  `if (!function_exists('get_date_range'))` checava nome diferente da função
  declarada. Corrigido o guard para bater com o nome real. Antes desses 2
  fixes, `phpunit --testsuite=Essentials` e `--testsuite=PontoWr2` não
  carregavam; agora rodam
- **`EssentialsTestCase` setup** — `primeSession()` gravava `session.business`
  como array enquanto o middleware `SetSessionData` grava como objeto
  Business, causando `Attempt to read property "time_zone" on array` no
  stack. Removido `primeSession`; `actingAs()` + middleware cuidam.
  `inertiaGet()` agora calcula a versão real do manifest ao montar o header
  `X-Inertia-Version` pra evitar falsos 409. **Resultado: suite 10/10** ✓

### Added — 2026-04-22 (Essentials batch 2 — submenu + 3 telas)
- **Submenu "Essentials" na sidebar** — `DataController::modifyAdminMenu` agora
  cria um dropdown agrupando Tarefas, Mensagens, Documentos e Base de
  Conhecimento (antes eram URLs soltas). Item "HRM" segue à parte por cobrir
  área diferente (`/hrm/*`). Quando cada tela é migrada, o `LegacyMenuAdapter`
  detecta o prefixo e ativa SPA automaticamente
- **Mensagens** (`/essentials/messages`) — chat mural React:
  - `EssentialsMessageController` reescrito para Inertia (4 ações:
    index/store/destroy + endpoint JSON `getNewMessages` para polling)
  - `Pages/Essentials/Messages/Index.tsx` — mural com balões esquerda/direita
    (minhas vs outras), auto-scroll pro fim a cada update, polling via fetch
    no intervalo `chat_refresh_interval` (config), filtro por localidade no
    send, Enter envia/Shift+Enter quebra linha, remoção de mensagem própria
    via AlertDialog, notificações `NewMessageNotification` preservadas
    (sem spam: DB notification só se passou >10min da última na mesma loja)
- **Base de Conhecimento** (`/essentials/knowledge-base`) — hierarquia 3 níveis
  (livro → seção → artigo) React:
  - `KnowledgeBaseController` reescrito para Inertia (7 ações CRUD com eager
    load de `children.children`, scope por business_id + visibilidade
    public/only_with)
  - 4 Pages em `Pages/Essentials/Knowledge/`: **Index** (grid 3 cards com
    seções expansíveis e artigos inline + AlertDialog delete), **Create**
    (inferência automática kb_type pelo parent — livro/seção/artigo, modo
    compartilhamento com multi-select de usuários), **Show** (layout 2 col:
    sidebar de navegação interna + conteúdo HTML renderizado), **Edit** (form
    com compartilhamento só para livros)
- **Documentos** (`/essentials/document`) — arquivos + memos em uma tela React:
  - `DocumentController` reescrito: JOIN em `essentials_document_shares` para
    expor arquivos meus + compartilhados comigo (via user ou role), filtra
    por tipo (`document`/`memos`) numa única query, download restrito
    respeitando DocumentShare
  - `DocumentShareController` reescrito como endpoint JSON consumido pelo
    Dialog do React (retorna users + roles + shared_*_ids; PUT sincroniza)
  - `Pages/Essentials/Documents/Index.tsx` — tabs internas Arquivos/Memos,
    Dialog de upload (progress bar + fileinput), Dialog de novo memo (title
    + body), Dialog de share (chips selecionáveis usuários + roles), Dialog
    de visualização do memo, Download direto, AlertDialog delete
- **LegacyMenuAdapter** — 4 novos prefixes em `$inertiaPrefixes`:
  `/essentials/messages`, `/essentials/knowledge-base`, `/essentials/document`

### Changed — 2026-04-22 (Essentials batch 2)
- Texto do item "Essentials" na sidebar agora abre dropdown em vez de
  redirecionar para `/essentials/todo` direto — o usuário vê todas as áreas
  do módulo

### Added — 2026-04-22 (Essentials/ToDo React)
- **Módulo Essentials — ToDo migrado para React** (`/essentials/todo`) — primeira tela do Essentials no AppShell, paridade total com Blade:
  - 4 Pages React (`resources/js/Pages/Essentials/Todo/`): `Index.tsx`, `Create.tsx`, `Edit.tsx`, `Show.tsx`
  - Index: lista paginada (25/pág) com 5 filtros (status, prioridade, usuário atribuído, date range), troca rápida de status via Dialog, remoção via AlertDialog
  - Show: 3 tabs internas (Comentários com add/delete, Anexos com upload/delete, Atividades via Activity log) + Dialog "Docs compartilhados" (hook `moduleViewPartials` → JSON)
  - Create/Edit: form shadcn (task, multi-assign colaboradores, prioridade, status, data, end_date, estimated_hours, descrição rich text)
  - Upload de arquivos com `forceFormData: true` + progress bar — **primeiro caso de upload React-Inertia do projeto**
- **`ToDoController` reescrito para Inertia** (`Modules/Essentials/Http/Controllers/ToDoController.php`) — métodos retornam `Inertia::render` / `RedirectResponse` em vez de JSON AJAX; preserva scope `business_id`, filtro não-admin (próprias OU atribuídas), notifications (`NewTask`/`Comment`/`Document`), activity log, `task_id` com prefixo configurável
- **4 FormRequests novos** em `Modules/Essentials/Http/Requests/`: `ToDoStoreRequest`, `ToDoUpdateRequest` (com branch `only_status`), `ToDoCommentRequest`, `ToDoUploadDocumentRequest`
- **EssentialsTestCase** + **TodoTest Feature** (`Modules/Essentials/Tests/Feature/`) — 10 testes cobrindo auth, permissões, shape de props, validação, create/delete flow, JSON shared docs. Testsuite "Essentials" adicionada ao `phpunit.xml`
- **Cast `date`/`end_date` → datetime** no `Entities/ToDo.php` (Laravel 9+ não cast automático para colunas DATETIME que não sejam timestamps)

### Changed — 2026-04-22 (Essentials/ToDo React)
- **`LegacyMenuAdapter::isInertiaRoute`** — `/essentials/todo` adicionado em `$inertiaPrefixes`, o item do menu sidebar agora é reconhecido como SPA e não dispara full-page load
- **Rotas `Routes/web.php`** — resource `todo` agora aponta para o controller Inertia; legados `todo/add-comment`, `todo/delete-comment/{id}`, `todo/upload-document`, `todo/delete-document/{id}`, `view-todo-{id}-share-docs` mantidos (mesmas URLs, respostas Inertia/JSON)
- **Parse de data no controller** — `uf_date()` substituído por `Carbon::parse()` (input HTML `<input type="date">` envia ISO, não formato business)

### Added — 2026-04-22
- **Shell React** (`resources/js/Layouts/AppShell.tsx`) — sidebar 2 níveis (col 1 módulos + col 2 sub-páginas do módulo ativo), topbar com search, footer com user + theme toggle
- **Dark mode por usuário** — coluna `users.ui_theme` + anti-flash blade + hook React `useTheme`
- **LegacyMenuAdapter** — shell lê menu dinâmico do nwidart/laravel-menus; todos os módulos aparecem automaticamente
- **Gerenciador de Módulos** (`/modulos`) — tela React shadcn substituindo `/manage-modules` (AdminLTE quebrado). Switch por módulo + install/uninstall com preservação de tabelas
- **ModuleSpecGenerator** — scanner PHP que inspeciona 29 módulos e gera spec markdown por módulo em `memory/modulos/`. Inclui rotas, controllers, entities, migrations, permissões, jobs, events, config, cross-deps, foreign keys, hooks UltimatePOS, assets JS/CSS e diffs vs branches antigas
- **Artisan command** `module:specs` — gera ou regenera as specs
- **Pages/Ponto/Welcome.tsx** + **Pages/Ponto/Relatorios/Index.tsx** — primeiras telas React reais do módulo PontoWR2, dentro do AppShell

### Changed — 2026-04-22
- **`modules_statuses.json`** reorganizado (ordem alfabética, ativos/inativos explícitos)
- **Rota `/manage-modules`** → redireciona automaticamente para `/modulos` via LegacyMenuAdapter

### Removed — 2026-04-22
- 🗑️ **`Modules/Officeimpresso1/`** apagado — bug: mesmo namespace `Modules\Officeimpresso\Providers\OfficeimpressoServiceProvider` do módulo Officeimpresso, causava colisão
- 🗑️ Código Vue (Example.vue + passport/*.vue) — scaffold não montado em blade nenhum
- 🗑️ `daisyui 3` + `tailwindcss-motion` + `tailwindcss@3.4.17` — migramos para Tailwind 4 no pipeline Inertia
- 🗑️ `tailwind.config.js` + `postcss.config.cjs` — artefatos TW3 não usados mais

### Deprecated — 2026-04-22 (desativados em `modules_statuses.json` — aguardam decisão de apagar ou reativar)
- **`AiAssistance`** — Wagner: "não útil". Overlap com plano IA-first via OpenAI direto
- **`Writebot`** — outro módulo de IA legado. Overlap com AiAssistance e plano novo
- **`IProduction`** — overlap com Grow (prioridade produção). Investigar antes de apagar
- **`Officeimpresso`** — licenciamento desktop legado, já coberto por Superadmin + Connector
- **`Grow`** — Wagner: prioridade produção mas precisa avaliar viabilidade (957 views CodeCanyon)

### Migration — 2026-04-22
- Branch atual: **`6.7-react`** criada a partir de `6.7-bootstrap`
- Backup `main-wip-2026-04-22` preservado (6484 arquivos do snapshot WIP local do Wagner)

### Fixed — 2026-04-22
- **Officeimpresso DataController** — `action()` para método `generateQr` falhava porque a rota estava comentada; isolado via `if(false)` temporário. Menu do shell voltou a renderizar (antes derrubava sidebar inteira)
- **Deprecations PHP 8.4** — suprimidas via `public/.user.ini` + `public/index.php` (deprecations do Laravel 9 contra PHP 8.4, ruído sem impacto funcional)

### Decision — 2026-04-22
- **Stack UI Inertia + React 19 + shadcn/ui + Tailwind 4** (= Laravel 12/13 Starter Kit) — escolhido como alvo de migração ao longo de 10 milestones (ver `memory/07-roadmap.md` Fase 13 e user memory `project_roadmap_milestones.md`)
- **Módulos perdidos 3.7 → 6.7** (BI, Boleto, Chat, Dashboard, Fiscal, Help, Jana, Knowledgebase, codecanyon-ticketing) — mapeados em `memory/modulos/RECOMENDACOES.md`. 4 para restaurar via pacotes padrão (F15), 5 para descartar, 3 a investigar
- **Tema por usuário** (coluna `users.ui_theme`) — mais escalável que por empresa; cada usuário escolhe independentemente. Fallback: preferência do SO

---

## Anterior a 2026-04-22

Histórico compactado — detalhe em `memory/sessions/*.md` e `memory/08-handoff.md`.

### 2026-04-21 (sessão 09 — upgrade stack + PontoWR2)
- UltimatePOS atualizado para v6.7 (Laravel 9.51 + PHP 8.3)
- Restrição PHP 7.1 revogada; toda sintaxe PHP 8.x liberada
- `PontoWr2` adaptado para L9 (factories class-based, HasFactory trait, removido `Factory::class`)
- Branch `6.7-bootstrap` criada no GitHub

### 2026-04-18 a 2026-04-20 (sessões 01-08)
- Scaffolding completo do módulo PontoWR2
- Refactor para padrão `Modules/Jana` (ADR 0011)
- Regras CLT implementadas (Art. 58, 66, 71, 59 via `ApuracaoService`)
- Hash chain SHA-256 imutabilidade (Portaria 671/2021)
- AFD parser funcional
- Banco de horas FIFO
- 14 telas migradas de Tailwind → AdminLTE
- 9 testes unitários

---

## Como atualizar este changelog

1. A cada mudança estrutural: adicione entrada sob `[Unreleased]` na categoria certa
2. Ao marcar um marco (milestone, release, deploy prod), crie seção `[vX.Y.Z] — YYYY-MM-DD` acima de `[Unreleased]` e mova os itens para lá
3. Use linguagem **ativa** ("Apagado X", "Adicionado Y") e sempre cite arquivo/path
4. Para decisões grandes, crie também um ADR em `memory/decisions/NNNN-slug.md` e referencie aqui
