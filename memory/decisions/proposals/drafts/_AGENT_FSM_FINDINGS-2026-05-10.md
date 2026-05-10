# Findings sub-agent — Investigação State Machines existentes (FSM)

> Origem: sub-agent Explore disparado em 2026-05-10 sessão Wagner.
> Objetivo: alimentar ADR pendente em US-SELL-010 (decisão padrão FSM canônico).
> Status: **input pra ADR** (não substitui ADR — Wagner aprova ADR formal antes de qualquer código).

## State machines existentes no oimpresso

### 1. Modules/Repair — Kanban OS (Job Sheets)

- **Padrão:** simples — tabela `repair_statuses` com estados cadastráveis (name, color, sort_order, is_completed_status)
- **Estados:** definidos dinamicamente por `business_id` em DB (não enum fixo). Exemplo: "rascunho", "em análise", "aguardando peças", "pronto", "entregue"
- **Transição:** `JobSheetController::updateStatus()` (linha 780) — valida permission `job_sheet.create|edit` + `RepairStatus::find(status_id)` + flag `is_completed_status` dispara fluxo de conclusão
- **Event:** `RepairStatusChanged` declarado em `Modules/Repair/Events/RepairStatusChanged.php` — disparo ainda não implementado (comentado linhas 19-30, aguarda PR coordenada)
- **RBAC:** ✅ integrado com `spatie/laravel-permission` — perms granulares (`job_sheet.view_assigned`, `job_sheet.create`, `job_sheet.edit`, `job_sheet.delete`)
- **Arquivo:** `Modules/Repair/Http/Controllers/JobSheetController.php:780-829`

**Achado crítico:** NÃO há tabela tabulando **quais transições são válidas** (ex: "só pra rascunho→em_análise"). Validação é só "se tem permission criar/editar".

### 2. Modules/ProjectMgmt — Kanban (McpTask)

- **Padrão:** enum constante (`McpTask::STATUSES`)
- **Estados:** `['backlog', 'todo', 'doing', 'review', 'done', 'blocked', 'cancelled']` (ADR 0070)
- **Transição:** `BoardController::index()` (linha 44+) filtra por status — **NÃO HÁ controller de transição específico**. Movimentação é presumida ser API REST genérica (PATCH /task/:id { status: "doing" })
- **RBAC:** ✅ permission `copiloto.mcp.usage.all` (todo-ou-nada, sem granularidade por transição)
- **Arquivo:** `Modules/ProjectMgmt/Http/Controllers/BoardController.php:44-76`

**Achado:** usa modelo Linear-style (`COPI-123`), mas **sem validação de transições permitidas** nem listeners.

### 3. mcp_tasks (Modules/Jana) — Task Registry

- **Padrão:** enum em migration + Model constant
- **Estados:** `enum('status', ['todo', 'doing', 'review', 'done', 'blocked', 'cancelled'])` default `'todo'`
- **Migration:** `Modules/Jana/Database/Migrations/2026_04_30_180001_create_mcp_tasks_table.php:31`
- **Transição:** sem controller dedicado — registro canonicamente parseado de SPECs markdown (`memory/requisitos/*/SPEC.md`). Estado mutável via CLI `BackfillTasksFromMarkdownCommand`
- **RBAC:** nenhum (dados parseados de git, multi-tenant por `project_id`)
- **Model:** `Modules/Jana/Entities/Mcp/McpTask.php:71` (constantes)

## Pacotes disponíveis no composer.json

| Pacote | Status |
|---|---|
| `spatie/laravel-model-states` | ❌ NÃO instalado |
| `symfony/workflow` | ❌ NÃO instalado |
| `spatie/laravel-permission` | ✅ instalado (v6.0) — único framework RBAC do sistema |

## RBAC por transição existe?

**❌ NÃO em lugar nenhum.** Todos os módulos usam permission generic "edit":
- Repair: `job_sheet.edit` libera **qualquer** transição de status
- ProjectMgmt: `copiloto.mcp.usage.all` libera **qualquer** movimento no kanban
- Jana tasks: sem RBAC (parseadas de git)

## Recomendação técnica do agent

**Adotar padrão custom: tabela `estado_transicoes` (FSM tabulada) + Service + Policy Laravel + Spatie Permission.**

1. **Tabela `estado_transicoes`** (= `sale_stage_actions` no nosso desenho de US-SELL-011) — legível, com origem/destino/roles permitidos/validador opcional
2. **Service `StateTransitionService`** (= `ExecuteStageActionService` no US-SELL-011) — valida transição + RBAC + dispara event
3. **Policy `CanTransitionState`** — integra `spatie/permission` pra RBAC granular (reutilizar em Repair + Jana)
4. **Event + Listener** — já existe padrão em `RepairStatusChanged` — estender pra modelo genérico

**Não usar:**
- ❌ Spatie ModelStates — overhead OOP, força 1 classe State por estado, hard pra parametrizar via UI admin
- ❌ Symfony Workflow — peso (~50 dependências), sem multi-tenant nativo, YAML config externo do Laravel

**Razão da escolha custom:** Laravel + Spatie Permission já cobre. Cada estado/ação/role configurável via UI admin (objetivo Wagner: parametrização por business via UI, não consultoria paga).

## Convergência com nosso desenho US-SELL-011

| Nosso desenho (US-SELL-011) | Recomendação agent | Equivalência |
|---|---|---|
| `sale_processes` | (implícito, agent não citou) | catálogo de fluxos por business |
| `sale_process_stages` | "estado" | estados configuráveis |
| `sale_stage_actions` | `estado_transicoes` | transições com origem/destino |
| `sale_stage_action_roles` | RBAC join Spatie | permissão granular por transição |
| `ExecuteStageActionService` | `StateTransitionService` | mesmo |
| `sale_stage_history` | (não citado) | audit log de transições |

✅ **Desenho US-SELL-011 está alinhado com recomendação do agent.** ADR pode ser escrita reutilizando conteúdo desse documento + os achados em §1-§3.

## Pontos pra ADR explorar adicionalmente

1. **Migração progressiva** Repair/ProjectMgmt pro padrão novo (não big-bang) — documentar plano de coexistência
2. **Side-effects** (`side_effect_class` em sale_stage_actions) — não foi tema do agent, mas case fiscal Wagner exige (ReservarEstoque, ConsumirEstoque, EmitirNFeJob, EmitirNFSeJob, BaixarFinanceiro). Ver [Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md](../../requisitos/Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md)
3. **UI admin de configuração** — telas pra business cadastrar processos/etapas/ações sem precisar dev. Tier C nice-to-have, mas vital pro pricing modelo SaaS
4. **Compatibilidade NFCom 62 / MDFe 58 / CT-e 57** futuras — `transaction_documents` poly (US-SELL-014) já cobre

## Próximo passo

Wagner lê este documento + escreve ADR `proposed` em `memory/decisions/NNNN-state-machine-canonica-fsm-rbac.md` referenciando este findings + caso prático.

---

**Última atualização:** 2026-05-10 — output sub-agent + análise de convergência com US-SELL-011.
