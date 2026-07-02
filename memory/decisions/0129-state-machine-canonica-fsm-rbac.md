---
slug: 0129-state-machine-canonica-fsm-rbac
number: 129
title: "State Machine canônica — FSM tabular custom + Spatie Permission por transição"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-10
module: Sells
tags: [arquitetura, fsm, state-machine, rbac, side-effects, multi-tenant, sells, repair, projectmgmt, nfe-brasil, fiscal-br]
supersedes: []
supersedes_partially: []
amends: []
superseded_by: []
related: [0093, 0094, 0011, 0070, 0095, 0121]
pii: false
review_triggers:
  - "Spatie/laravel-model-states ou symfony/workflow ganharem multi-tenant nativo + parametrização runtime via UI → reavaliar adoção"
  - "3+ anti-padrões de FSM custom catalogados em produção (race condition, RBAC bypass, side-effect mal isolado) → reescrever ou trocar"
  - "Demanda real BPMN-completo (paralelismo gateways condicionais, swimlanes cross-tenant) → adotar Symfony Workflow APENAS em US específica, mantendo FSM custom como default"
  - "Reforma Tributária 2026 (LC 214/2025) entrar em fase obrigatória 2027 e exigir FSM estendido pra IBS/CBS → emenda este ADR"
  - "Performance degradar (>50ms na resolução de transição) com ≥10k stages cadastrados → indexação adicional ou cache layer"
---

# ADR 0129 — State Machine canônica (FSM tabular custom + Spatie Permission por transição)

## Contexto

### Problema observado

Em 2026-05-10, durante higiene CYCLE-03 → CYCLE-04 e drenagem da fila REVIEW, surgiram simultaneamente:

1. **Pivot conceitual US-RB-044** (auto-emissão NFe ao receber boleto): a feature foi marcada `done` mas o DoD original tinha `Prod-evidence: ≥1 NFe55 autorizada ROTA LIVRE biz=4` como bloqueante. Wagner [W] apontou: **"venda sem nota é caminho feliz, não falha"**. Larissa (vestuário Gravatal/SC) talvez nunca opt-in. A premissa de "todo cliente emite" estava errada.

2. **Caso prático real Wagner** ([Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md](../requisitos/Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md)): uma OS de Comunicação Visual (banner + instalação) gera **2 documentos fiscais distintos** na mesma transaction (NFe55 mercadoria + NFSe56 serviço), com **reserva de estoque** que não baixa até concluir produção. Esse caso aplica-se a Repair (peças + mão de obra), OficinaAuto (peças + serviço), eletricista, dentista, etc.

3. **Investigação sub-agent FSM** ([_AGENT_FSM_FINDINGS-2026-05-10.md](proposals/drafts/_AGENT_FSM_FINDINGS-2026-05-10.md)): mapeou state machines existentes em `Modules/Repair`, `Modules/ProjectMgmt`, `mcp_tasks`. Achados:
   - **Repair:** tabela `repair_statuses` dinâmica por business + `JobSheetController::updateStatus()`. RBAC via Spatie Permission generic (`job_sheet.edit` libera qualquer transição). Event `RepairStatusChanged` declarado mas **disparo comentado** (aguarda PR).
   - **ProjectMgmt:** enum `McpTask::STATUSES` const. SEM controller de transição (PATCH genérico). RBAC todo-ou-nada.
   - **mcp_tasks:** enum migration. Estado parseado de SPECs git via CLI BackfillCommand. Sem RBAC.
   - **composer.json:** `spatie/laravel-model-states` ❌ NÃO instalado · `symfony/workflow` ❌ NÃO instalado · `spatie/laravel-permission` ✅ v6.0.

### Necessidade

oimpresso precisa de **padrão canônico** de Workflow/State Machine pra modelar processos multi-etapa com:
- **RBAC granular por transição** (não apenas por permission generic "edit")
- **Side-effects** (reservar/baixar estoque, emitir NFe/NFSe, baixar financeiro) acoplados às transições mas isolados em classes próprias (SoC brutal — Constituição v2 §5)
- **Multi-tenancy Tier 0** ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)) — todo estado/transição/configuração é per-business
- **Parametrização via UI admin** (não consultoria paga ou YAML estático), pra cliente PME configurar processos próprios sem dev
- **Audit log** de transições (quem mudou de qual estado pra qual estado, quando, com qual ação)
- **Reusabilidade** entre Sells, Repair, ProjectMgmt, OficinaAuto, ComunicacaoVisual, Vestuario futuros

Sem esse padrão, qualquer feature multi-etapa nova reinventa roda diferente — 3 state machines existentes já divergem (dinâmica DB, enum const, parseada git).

## Decisão

Adotar **State Machine custom** baseada em **5 tabelas + Service + Spatie Permission por transição**.

### Schema canônico

```sql
-- Catálogo de processos por business
sale_processes
  id, business_id, key (unique per business),
  name, description,
  default_for_contact_type ENUM('cf','pf','pj','any'),  -- resolução automática
  active BOOLEAN,
  timestamps
  INDEX(business_id, active)

-- Estados (etapas) de cada processo
sale_process_stages
  id, process_id, key,
  name, sort_order, is_initial, is_terminal,
  color VARCHAR(20),  -- UI hint
  timestamps
  UNIQUE(process_id, key)

-- Transições disponíveis em cada estado (com side-effects e target)
sale_stage_actions
  id, stage_id, key,
  label,
  target_stage_id NULLABLE,  -- null = ação que NÃO transita (ex: emitir 2ª via)
  event_class VARCHAR(255) NULLABLE,  -- event Laravel a disparar
  side_effect_class VARCHAR(255) NULLABLE,  -- App\Domain\Fsm\SideEffects\*
  side_effect_payload JSON NULLABLE,  -- parâmetros pro side-effect
  requires_confirmation BOOLEAN,
  timestamps
  UNIQUE(stage_id, key)

-- RBAC join: ação × role Spatie
sale_stage_action_roles
  id, action_id, role_name,  -- FK lógica pra spatie_roles.name
  timestamps
  UNIQUE(action_id, role_name)

-- Audit log das transições executadas
sale_stage_history
  id, business_id, transaction_id, action_id,
  from_stage_id NULLABLE, to_stage_id NULLABLE,
  user_id, payload_snapshot JSON,
  executed_at TIMESTAMP
  INDEX(business_id, transaction_id)
  INDEX(business_id, executed_at)
```

> **Nota:** o prefixo `sale_*` reflete o módulo de origem (Sells), mas as tabelas são reutilizáveis pra Repair, ProjectMgmt etc. Em US futura pode-se renomear pra `fsm_*` se a abstração se generalizar — decidir após 2+ módulos adotarem.

### Service canônico

`App\Domain\Fsm\Services\ExecuteStageActionService`:

```php
public function execute(
    Transaction $sale,
    string $actionKey,
    User $user,
    array $payload = []
): StageActionResult
```

Responsabilidades sequenciais:
1. **Resolve action válida** pra `$sale->current_stage_id` + `$actionKey` (ou lança `InvalidActionForCurrentStageException`)
2. **Checa RBAC** via `$user->hasAnyRole($action->roles->pluck('role_name'))` (Spatie Permission) — lança `UnauthorizedActionException` se falhar
3. **Executa side-effect** (se `side_effect_class` definido): instancia `App\Domain\Fsm\SideEffects\<Classe>`, chama `->execute($sale, $payload)`. Side-effect roda **dentro de transaction DB** com a mudança de stage (atomicidade)
4. **Atualiza** `$sale->current_stage_id = $action->target_stage_id` (se não null)
5. **Dispara event** (`event_class` instanciado e `event(new $eventClass($sale, $action, $user))`)
6. **Loga** em `sale_stage_history` (sempre, mesmo se transição = null target)

### Side-effects canônicos (catálogo inicial)

Em `App\Domain\Fsm\SideEffects\`:

- `ReservarEstoque` — cria `stock_reservations` (US-SELL-013)
- `ConsumirEstoque` — marca reserva como `consumed` + decrementa `qty_available`
- `LiberarReserva` — marca reserva como `released` (cancelamento)
- `EmitirNFeJob` — dispatch `Modules\NfeBrasil\Jobs\EmitirNfceJob` ou `EmitirNFeJob` (modelo 55)
- `EmitirNFSeJob` — dispatch `Modules\NfeBrasil\Jobs\EmitirNFSeJob` (modelo 56 nacional, US-NFE-060)
- `BaixarFinanceiro` — cria `transaction_payments` parciais ou marca `payment_status=paid`
- `EnviarWhatsappCliente` — dispatch `Modules\Whatsapp\Jobs\SendWhatsappMessageJob`

Cada side-effect implementa interface `App\Domain\Fsm\Contracts\SideEffectInterface::execute(Transaction $sale, array $payload): void`. Multi-tenant Tier 0 obrigatório — side-effect que cria registros em outras tabelas SEMPRE passa `business_id` no constructor (nunca `session()`).

### Authorization Policy

`App\Domain\Fsm\Policies\StageActionPolicy::canExecute(User $user, Transaction $sale, string $actionKey)` — chamável de Controller, View, Job. Reutilizado por Repair (após migração progressiva).

### UI admin (Tier C — nice-to-have, prioridade futura)

Tela em `/admin/fsm` permitirá business admin cadastrar:
- Novos processos (clonar template ou em branco)
- Editar stages (drag&drop sort_order, definir is_initial/is_terminal)
- Editar actions (target_stage, side-effect, payload, roles permitidas)

Sem essa UI no curto prazo, businesses recebem **3 processos seed default** instalados via migration ([US-SELL-012](../requisitos/Sells/SPEC.md#us-sell-012)):
- `Venda Sem Nota` (default pra Contact CF/sem)
- `Venda Com Nota Manual`
- `Venda Com Nota Automática` (gate auto-emissão NFe)

## Alternativas avaliadas

### Alternativa A — `spatie/laravel-model-states` ❌ rejeitada

**Prós:**
- Pacote maduro, mantido por Spatie
- DSL Laravel-native, classes State por estado

**Contras:**
- **Overhead OOP** — cada estado = 1 classe PHP. Não permite parametrização runtime via UI admin
- **RBAC não incluído** — teria que casar com Spatie Permission na mão (não simplifica vs custom)
- **Multi-tenant não nativo** — estados são globais por classe, mas no oimpresso cada business pode ter processos próprios
- **Side-effects acoplados** ao método `transitionTo()` da classe State — viola SoC §5 (lógica do estado misturada com dispatch de jobs)

### Alternativa B — `symfony/workflow` ❌ rejeitada

**Prós:**
- Padrão Symfony, suporta Workflow + StateMachine + Marking Store
- Suporta paralelismo (Petri Net), guards, listeners

**Contras:**
- **Peso** — instala 50+ dependências Symfony pra usar 5%
- **YAML config externo** — definições de workflow ficam em `config/workflows.yaml` (estático), não em DB. Mata UI admin
- **Multi-tenant não nativo** — mesma definição YAML serve todos businesses. Sem hooks pra resolver per-business em runtime
- **Curva de aprendizado** — abstrações conceituais altas (places, transitions, marking stores) pra ganho marginal vs FSM tabular

### Alternativa C — Status quo (enum const + permission generic) ❌ rejeitada

Padrão atual de `Modules/ProjectMgmt` (`McpTask::STATUSES`) e parcial de `Modules/Repair` (`repair_statuses` table mas RBAC generic).

**Contras:**
- **Sem RBAC granular por transição** — qualquer user com `edit` pode mover qualquer task pra qualquer estado. Risco de bypass de fluxo (ex: pular aprovação)
- **Sem side-effects acoplados** — devs esquecem de disparar event/baixa de estoque ao mudar status (achado real: `RepairStatusChanged` declarado mas nunca disparado)
- **Sem audit log** — impossível responder "quem moveu essa OS pra cancelado às 14h?"
- **Não escala** pra caso prático Wagner (N notas por OS, reserva de estoque, gate por venda)

### Alternativa D — Custom 4 tabelas + Spatie Permission ✅ ESCOLHIDA

**Prós:**
- **Multi-tenant nativo** (business_id em todas tabelas, ADR 0093)
- **Parametrização runtime via UI admin** futuro — hard differentiator vs Linx Microvix/ProMoz que cobram consultoria pra customizar workflow
- **RBAC granular** (action × role) reusando Spatie Permission v6.0 já instalado
- **Side-effects isolados** em classes próprias (`App\Domain\Fsm\SideEffects\*`) — SoC brutal preservado
- **Audit log nativo** (`sale_stage_history`)
- **Convergência com investigação sub-agent** — recomendação técnica independente bate com desenho

**Contras:**
- **Custom = manutenção própria** — sem pacote externo recebendo PRs/security patches
- **Falta UI admin no curto prazo** — businesses recebem só processos seed default
- **Validações complexas** (ex: "só pode faturar se tem CNPJ + endereço completo") ficam no `side_effect_class` ou em pre-checks no Service — não tão expressivo quanto Symfony Workflow guards declarativos

**Mitigação dos contras:**
- Manutenção: superfície código pequena (~5 tabelas + 1 Service + N side-effects). Pest cobre 8+ testes em US-SELL-011
- UI admin: 3 processos seed cobrem 80% dos casos PME BR. UI admin entra em US futura quando 1º business pedir customização
- Validações complexas: pre-checks no `ExecuteStageActionService::execute()` antes do side-effect. Em casos extremos, side-effect pode lançar exception que aborta transição (transaction DB roll-back)

## Consequências

### Positivas

1. **Sistema competitivo vs concorrentes** (validado em [CASO-PRATICO-OS-COMUNICACAO-VISUAL.md](../requisitos/Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md)):
   - vs Mubisys/Zênite/Calcgraf: 1 OS = 2 docs (NFe+NFSe), reserva de estoque, FSM RBAC granular
   - vs Bling/Tiny/Conta Azul: NFSe nacional 56, conceito real de OS
   - vs Linx Microvix/Big: parametrização via UI admin (sem consultoria paga)

2. **Pré-requisito pra Reforma Tributária 2026** — estrutura suporta múltiplos documentos e estados que vão coexistir na transição IBS/CBS (NFe55 + NFSe56 + futura NFCom62)

3. **Plataforma reusável** entre Modules/Vestuario, Repair, ProjectMgmt, OficinaAuto, ComunicacaoVisual — 1 padrão, N aplicações

4. **Audit log** atende compliance LGPD/fiscal — trilha completa de quem moveu O QUE QUANDO

5. **Side-effects bem isolados** — testáveis em isolation, mockáveis em Pest

### Negativas / Trade-offs

1. **Aumento de complexidade no checkout** — toda venda nova precisa resolver `process_id` + `current_stage_id` na criação. Mitigação: `process_default_for_contact_type` + UI POS mostra dropdown

2. **Migration de dados** das 3 state machines existentes (Repair, ProjectMgmt, mcp_tasks) pro padrão novo — não-trivial. Mitigação: **migração progressiva**, não big-bang (ver "Plano de adoção")

3. **Backfill `transaction_documents` poly** ([US-SELL-014](../requisitos/Sells/SPEC.md#us-sell-014)) das NFes existentes — mantém idempotência mas custa tempo de execução em prod

4. **Lock-in interno** — cada side-effect novo precisa implementar `SideEffectInterface`. Esforço modesto mas não-zero

5. **Performance** — toda transição faz 1-2 SELECTs (resolver action + verificar role). Indexação cuidadosa em `(business_id, stage_id, key)`. Cache em Redis se necessário (US futura)

## Plano de adoção (progressivo, não big-bang)

### Fase 1 — Foundation (CYCLE-04 / CYCLE-05)

- **[US-SELL-011](../requisitos/Sells/SPEC.md#us-sell-011)** (12h) — 4 tabelas + Service + Pest 8 testes. Sem aplicação real ainda.
- **[US-SELL-013](../requisitos/Sells/SPEC.md#us-sell-013)** (8h) — `stock_reservations` + 3 SideEffect classes (Reservar/Consumir/Liberar)
- **[US-SELL-014](../requisitos/Sells/SPEC.md#us-sell-014)** (6h) — `transaction_documents` poly N:1 + backfill NFe existentes
- **[US-NFE-060](../requisitos/NfeBrasil/SPEC.md#us-nfe-060)** (12h) — `EmitirNFSeJob` modelo 56 nacional + tabela `nfse_emissoes`

### Fase 2 — Aplicação Sells (CYCLE-05 / CYCLE-06)

- **[US-SELL-012](../requisitos/Sells/SPEC.md#us-sell-012)** (8h) — gate emissão por venda + 3 processos seed default + listener auto-emissão refatorado
- **[US-NFE-059](../requisitos/NfeBrasil/SPEC.md#us-nfe-059)** (4h) — smoke prod end-to-end com 1 cliente real (Gold candidato natural)

### Fase 3 — Migração Repair (CYCLE-06+, US futura a criar)

- Migration converte `repair_statuses` → `sale_processes` + `sale_process_stages` por business (1-pra-1)
- `JobSheetController::updateStatus()` refatora pra usar `ExecuteStageActionService`
- Event `RepairStatusChanged` é finalmente disparado (deixa de estar comentado)
- RBAC granular: ações tipo `concluir_os` exigem role `repair.completer` (não mais generic `job_sheet.edit`)

### Fase 4 — Migração ProjectMgmt + mcp_tasks (deprioridade, US futura)

- ProjectMgmt: `McpTask` ganha `current_stage_id` + processo default `Kanban Linear` seed
- mcp_tasks: parser de SPEC markdown ganha resolução de stage via key match
- Cuidado: `mcp_tasks` é parseado de git, não tem `business_id` real (project-scoped). Pode requerer ADR específica de adaptação

## Multi-tenant Tier 0 amarração ([ADR 0093](0093-multi-tenant-isolation-tier-0.md))

- ✅ Todas 5 tabelas têm `business_id` indexado + FK
- ✅ Todos models do FSM aplicam global scope `BusinessScope`
- ✅ Side-effects que criam registros em outras tabelas SEMPRE recebem `business_id` no payload (nunca `session()`)
- ✅ Jobs assíncronos (Emitir*Job, etc) recebem `$businessId` no constructor
- ⛔ `withoutGlobalScopes` proibido sem comentário `// SUPERADMIN: <razão>` (Skill `multi-tenant-patterns` Tier A enforce)

## Refs

- **Caso prático que motivou:** [Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md](../requisitos/Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md)
- **Findings sub-agent:** [_AGENT_FSM_FINDINGS-2026-05-10.md](proposals/drafts/_AGENT_FSM_FINDINGS-2026-05-10.md)
- **US mãe:** [US-SELL-010](../requisitos/Sells/SPEC.md#us-sell-010) (esta ADR é o entregável principal dela)
- **Cadeia de implementação:**
  - [US-SELL-011](../requisitos/Sells/SPEC.md#us-sell-011) — 4 tabelas FSM
  - [US-SELL-012](../requisitos/Sells/SPEC.md#us-sell-012) — Gate emissão por venda
  - [US-SELL-013](../requisitos/Sells/SPEC.md#us-sell-013) — Stock reservations
  - [US-SELL-014](../requisitos/Sells/SPEC.md#us-sell-014) — Transaction documents poly
  - [US-NFE-059](../requisitos/NfeBrasil/SPEC.md#us-nfe-059) — Smoke prod end-to-end
  - [US-NFE-060](../requisitos/NfeBrasil/SPEC.md#us-nfe-060) — EmitirNFSeJob nacional
- **ADRs relacionadas:**
  - [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 (amarra business_id obrigatório em todas tabelas FSM)
  - [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (§5 SoC brutal — side-effects isolados)
  - [ADR 0011](0011-alinhamento-padrao-jana.md) — Imitar Modules/Repair antes de criar (Repair vai migrar PRA esse padrão depois)
  - [ADR 0070](0070-jira-style-task-management-current-md-removed.md) — mcp_tasks state machine (será adaptado em fase 4)
  - [ADR 0121](0121-oimpresso-modular-especializado-por-vertical.md) — Modular especializado (FSM permite cada vertical configurar processos próprios)
- **Pacote PHP usado:**
  - `spatie/laravel-permission` v6.0 (já instalado) — RBAC por role/permission

## Aprovação

Aprovado conceitualmente por Wagner [W] em sessão 2026-05-10:

> *"aprovado ficou muito bom"*

Status `accepted` aplicado direto (Wagner é dono + aprovador final, ADR 0040 policy publicação). Mudanças incrementais via novas ADRs com `supersedes_partially`/`amends`.

---

**Última atualização:** 2026-05-10 — versão inicial accepted após aprovação Wagner.
