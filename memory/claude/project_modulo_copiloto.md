---
name: Módulo Jana (implementado)
description: Chat IA que sugere e monitora metas de negócio; tenancy híbrida; implementação real mergeada em 6.7-bootstrap em 2026-04-26 (PR #13, 24 testes Pest). Roda só em dry_run hoje (sem pacote OpenAI no composer.lock).
type: project
originSessionId: 3ea423cc-141d-477e-b072-2e0171a6fdd7
---
**Módulo Jana** — criado em spec-ready em 2026-04-24. Marca comercial e técnica iguais (pasta `Modules/Jana/`, URL `/copiloto`, namespace `Modules\Jana`).

**Posicionamento:**
- Jana de IA do negócio — conversa, sugere metas em cenários (fácil/realista/ambicioso), usuário escolhe, sistema monitora com apuração + alertas.
- Pitch: *"Você não precisa ser analista de dados. Sua Jana entende os números, conversa com você e te avisa quando algo sai da rota."*
- Nome escolhido sobre `BI`, `MetasNegocio`, `Farol`, `Compass`: alinhado com conceito IA-first + surfa trend Copilot (GitHub/MS) + escalável como guarda-chuva (v1 metas → v2 comercial → v3+ operacional/financeiro).

**Arquitetura-chave:**
- **Tenancy híbrida:** `business_id` nullable em `copiloto_metas` / `copiloto_conversas` — `null` = meta da plataforma oimpresso (superadmin-only); `not null` = meta do business.
- **Chat é entry-point**, não dashboard. `/copiloto` abre chat. Dashboard em `/copiloto/dashboard`.
- **Dependência IA soft:** interface `AiAdapter` com 2 drivers — `LaravelAiDriver` (quando módulo LaravelAI existir) e `OpenAiDirectDriver` (fallback via `openai-php/laravel`).
- **Drivers de apuração plugáveis:** `sql` / `php` / `http`. SQL valida read-only + binds obrigatórios `:business_id`/`:data_ini`/`:data_fim`.
- **Stack:** Inertia + React + shadcn. Nasce moderno, sem Blade/AdminLTE.

**Áreas funcionais (7):** Chat, Metas, Períodos, Apuração, Fontes, Dashboard, Alertas.

**Entidades (7):** `copiloto_metas`, `copiloto_meta_periodos`, `copiloto_meta_apuracoes`, `copiloto_meta_fontes`, `copiloto_conversas`, `copiloto_mensagens`, `copiloto_sugestoes`.

**Artefatos no cofre** (branch alvo `6.7-bootstrap`, atualmente worktree `lucid-dirac-00f1c6` aguardando commit/PR):
- `memory/requisitos/Jana/README.md` — pitch + frontmatter
- `memory/requisitos/Jana/ARCHITECTURE.md` — 7 áreas, 7 entidades, 4 camadas
- `memory/requisitos/Jana/SPEC.md` — 4 personas, US-COPI-001 a US-COPI-061, Gherkin
- `memory/requisitos/Jana/GLOSSARY.md` — vocabulário canônico
- `memory/requisitos/Jana/RUNBOOK.md` — seed, job, debug, problemas comuns
- `memory/requisitos/Jana/CHANGELOG.md`
- `memory/requisitos/Jana/adr/arq/0001-tenancy-hibrida.md`
- `memory/requisitos/Jana/adr/arq/0002-conversa-como-entry-point.md`
- `memory/requisitos/Jana/adr/tech/0001-drivers-apuracao-plugaveis.md`
- `memory/requisitos/Jana/adr/tech/0002-adapter-ia-laravelai-ou-openai.md`
- `memory/requisitos/Jana/adr/ui/0001-chat-inline-no-dashboard.md`

**Status:** `implementado` — mergeado em `6.7-bootstrap` na sessão 14 (2026-04-26, commit `e9cf6dc1`, PR #13). Em `Modules/Jana/`: 10 controllers (`Chat`, `Dashboard`, `Metas`, `Periodos`, `Fontes`, `Alertas`, `Superadmin`, `Install`, `Data`), 7 entidades, drivers `OpenAiDirectDriver` + `LaravelAiDriver` (stub legado) + `SqlDriver`, jobs `ApurarMetaJob`, services `Apuracao`/`Alerta`/`Suggestion`/`ContextSnapshot`, 24 testes Pest passando.

**Stack-alvo CANÔNICA (Wagner declarou em 2026-04-26 sessão 17 como "melhor ROI", ADR 0035):**
- Camada A: `laravel/ai` (Laravel AI SDK oficial fev/2026) — substitui `OpenAi\Laravel\Facades\OpenAI`. Sprint 1 em execução na branch `feat/copiloto-laravel-ai-sdk-sprint1`.
- Camada B: `vizra/vizra-adk` (sprints 2-3)
- Camada C: `MemoriaContrato` PHP com `Mem0RestDriver` default OU `MeilisearchDriver` fallback (sprints 4-5 e 8-10)
- Tooling DEV: `laravel/boost --dev`

**Pendências do código atual:**
- Sprint 1: deletar stub `LaravelAiDriver.php` + criar `LaravelAiSdkDriver.php` real + 3 Agent classes em `Modules/Jana/Ai/Agents/`
- `ApurarMetasAtivasJob` scheduler
- Drivers `php`/`http` da apuração
- Wizard `metas/create`
- Parsing real do `SuggestionEngine`

**Relações:**
- Materializa `ADR 0022 — Meta R$ 5mi/ano`.
- Usa `memory/11-metas-negocio.md` como seed de metas.
- Primeira materialização do conceito `ideia_chat_ia_contextual.md` (auto-memória).
- Entra na tese de revenue dos 4 módulos promovidos (`reference_revenue_thesis_modulos.md`) — pricing a definir.
