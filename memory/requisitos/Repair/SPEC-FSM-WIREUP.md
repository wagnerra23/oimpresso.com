# SPEC-FSM-WIREUP — Modules/Repair adoção do FSM canônico

> **Status:** DRAFT — discovery + proposta. **NÃO IMPLEMENTAR** sem aprovação Wagner.
> **Refs:** [ADR 0129](../../decisions/0129-state-machine-canonica-fsm-rbac.md) Fase 3 · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0 · US-SELL-031..033 (pattern canônico Sells já mergeado)
> **Escopo:** propor wire-up do `App\Domain\Fsm` no `Modules\Repair\Entities\JobSheet` espelhando o que foi feito em `App\Transaction` (Sells). Não toca código de produção Repair nesta US — só docs.
> **Última atualização:** 2026-05-12 — discovery feito em worktree `focused-bohr-b5963f`.

---

## §1 Estado atual

### 1.1 State machine atual (legacy dinâmica per-business)

Repair tem state machine **antes** do canônico FSM/ADR 0129, baseado em duas tabelas:

| Tabela | Coluna | Função |
|---|---|---|
| `repair_statuses` | `id, business_id, name, color, sort_order, is_completed_status, sms_template` | Lista de status **configuráveis pelo cliente via UI** (Settings → Repair → Statuses). Cada business tem o seu set, não há catálogo canônico. |
| `repair_job_sheets` | `status_id` (int, NOT NULL, sem FK no schema original) | Aponta pro status atual da OS. UPDATE direto sem audit-log estruturado (só `activitylog` genérico). |

**Sem stages canônicos.** Cliente cria `Recebido / Em análise / Aguardando peça / Pronto pra retirada` à mão; ROTA LIVRE não usa hoje, mas clientes legacy desktop OfficeImpresso usam com nomenclatura própria (em discovery).

### 1.2 Onde a UI mostra/muda status

- **Listagem OS:** `Modules/Repair/Resources/views/job_sheet/index.blade.php` (Blade — não-migrado pra Inertia). Coluna "Status" via DataTables ajax `JobSheetController@index` (linha 80 — leftJoin `repair_statuses AS rs`).
- **Tela criar/editar OS:** `Modules/Repair/Resources/views/job_sheet/{create,edit}.blade.php`. Dropdown vem de `RepairStatus::getRepairSatuses($business_id)` (linhas 353 e 547 do `JobSheetController`).
- **Modal mudança rápida de status:** `Modules/Repair/Resources/views/job_sheet/partials/edit_status_form.blade.php`. POST pra `JobSheetController@updateStatus` (linha 780).
- **Kanban Board Repair:** `Modules/Repair/Http/Controllers/ProducaoOficinaController.php` (Producao Oficina — visão Kanban da fábrica).
- **Settings:** `Modules/Repair/Resources/views/settings/index.blade.php` (CRUD `repair_statuses`).

### 1.3 RBAC atual

Permissions na `permissions` table (Spatie):

- `job_sheet.view_all` / `job_sheet.view_assigned` — ler
- `job_sheet.create` — criar e atualizar (sem separação)
- `job_sheet.edit` — editar (verificada junto com `create` no `updateStatus`)
- `job_sheet.delete` — deletar

**Sem permissions per-transição.** Qualquer usuário com `job_sheet.edit` pode pular do status `Recebido` direto pro `Entregue` — não há gate de fluxo.

Trecho relevante `JobSheetController@updateStatus` (linha 784):

```php
if (! (auth()->user()->can('superadmin') ||
       ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') &&
        (auth()->user()->can('job_sheet.create') || auth()->user()->can('job_sheet.edit'))))) {
    abort(403, 'Unauthorized action.');
}
```

### 1.4 Eventos existentes

- `Modules\Repair\Events\RepairStatusChanged` (`Modules/Repair/Events/RepairStatusChanged.php`) — **declarado mas com dispatch ATIVO em produção** (linha ~649 do `JobSheetController@update`). Listener vivo: `Modules\Whatsapp\Listeners\NotifyRepairCustomer` (notifica cliente via Whatsapp ao virar `ready`/`waiting_parts` — ADR Repair tech/0001).
- O comentário no `RepairStatusChanged.php` diz "dispatch a fazer em PR coordenado", mas grep confirma que **já foi feito** (linhas 645-650 do Controller — `if ((int) $oldStatusId !== (int) $job_sheet->status_id) event(...)`). Doc do Event está desatualizada.

---

## §2 Pipeline FSM canônico Repair proposto

### 2.1 Stages canônicos (13)

| # | Key | Label PT-BR | Cor | Initial? | Terminal? | Notas |
|---|---|---|---|---|---|---|
| 0 | `recebido_para_diagnostico` | Recebido pra diagnóstico | `slate` | ✅ | — | OS aberta; aparelho na bancada |
| 1 | `em_diagnostico` | Em diagnóstico | `blue` | — | — | Técnico avaliando |
| 2 | `diagnosticado_aguardando_aprovacao` | Diagnosticado — aguardando aprovação | `amber` | — | — | Orçamento gerado, esperando cliente |
| 3 | `orcamento_aprovado` | Orçamento aprovado | `cyan` | — | — | Cliente aceitou |
| 4 | `orcamento_rejeitado` | Orçamento rejeitado | `red` | — | — | Cliente recusou; vai pra entrega-sem-conserto |
| 5 | `aguardando_pecas` | Aguardando peças | `purple` | — | — | Reserva estoque (lateral, pode voltar pra `em_execucao`) |
| 6 | `pecas_chegadas` | Peças chegaram | `indigo` | — | — | Estoque confirma; libera técnico |
| 7 | `em_execucao` | Em execução | `orange` | — | — | Conserto em andamento |
| 8 | `pausado` | Pausado | `gray` | — | — | Espera externa (cliente, fornecedor) |
| 9 | `concluido_aguardando_retirada` | Concluído — aguardando retirada | `green` | — | — | Pronto; aciona Whatsapp pro cliente |
| 10 | `entregue_completo` | Entregue ao cliente | `emerald` | — | ✅ | Caso de sucesso |
| 11 | `cancelado` | Cancelado | `zinc` | — | ✅ | Override gerente; libera reservas |
| 12 | `garantia_acionada` | Garantia acionada (re-entrada) | `rose` | — | ✅ | OS filha vinculada; permite KPI taxa-de-retorno |

### 2.2 Matriz de actions × roles (RBAC granular)

| Action key | From stage | To stage | Role obrigatória | `is_critical` | Side-effect |
|---|---|---|---|---|---|
| `iniciar_diagnostico` | `recebido_para_diagnostico` | `em_diagnostico` | `repair.tecnico` | — | — |
| `finalizar_diagnostico` | `em_diagnostico` | `diagnosticado_aguardando_aprovacao` | `repair.tecnico` | — | — |
| `enviar_orcamento` | `diagnosticado_aguardando_aprovacao` | (sem mudança — só side-effect Whatsapp) | `repair.vendedor` | — | `NotificarOrcamentoWhatsapp` |
| `registrar_aprovacao_cliente` | `diagnosticado_aguardando_aprovacao` | `orcamento_aprovado` | `repair.vendedor` | ✅ | `ReservarEstoque` (peças do diagnóstico) |
| `registrar_rejeicao_cliente` | `diagnosticado_aguardando_aprovacao` | `orcamento_rejeitado` | `repair.vendedor` | ✅ | `LiberarReservaEstoque` |
| `pedir_pecas` | `orcamento_aprovado` | `aguardando_pecas` | `repair.estoque` | — | — |
| `confirmar_chegada_pecas` | `aguardando_pecas` | `pecas_chegadas` | `repair.estoque` | — | — |
| `iniciar_execucao` | `orcamento_aprovado` OR `pecas_chegadas` | `em_execucao` | `repair.tecnico` | — | — |
| `pausar_execucao` | `em_execucao` | `pausado` | `repair.tecnico` | — | — |
| `retomar_execucao` | `pausado` | `em_execucao` | `repair.tecnico` | — | — |
| `concluir_execucao` | `em_execucao` | `concluido_aguardando_retirada` | `repair.tecnico` | ✅ | `ConsumirEstoque` + `NotificarClienteWhatsapp` |
| `entregar_ao_cliente` | `concluido_aguardando_retirada` OR `orcamento_rejeitado` | `entregue_completo` | `repair.logistica` OR `repair.recepcao` | ✅ | `EmitirCupomServico` (se aplicável) |
| `cancelar_os` | qualquer não-terminal | `cancelado` | `repair.gerente` (override) | ✅ | `LiberarReservaEstoque` |
| `acionar_garantia` | `entregue_completo` | `garantia_acionada` | `repair.gerente` | ✅ | criar OS filha em `recebido_para_diagnostico` linkada via `parent_job_sheet_id` (coluna nova futura) |

### 2.3 Roles propostas (Spatie Permission)

| Role | Quem normalmente é | Setor |
|---|---|---|
| `repair.recepcao` | Atendente do balcão | Recebe + entrega |
| `repair.tecnico` | Técnico de bancada | Diagnóstico + execução |
| `repair.vendedor` | Comercial / orçamentista | Orçamento + aprovação cliente |
| `repair.estoque` | Almoxarife | Compra + chegada de peças |
| `repair.logistica` | Motoboy / entregador | Entrega externa (pick_up/on_site) |
| `repair.gerente` | Supervisor | Override (cancelar / garantia) |

Mapping inicial sugerido (compatibilidade): user com `job_sheet.edit` ganha todas as roles `repair.*` exceto `repair.gerente` (que vai pra `superadmin` + admin do business). Refinamento incremental conforme adoção.

---

## §3 Side-effects relevantes

| Side-effect | Trigger | Ação | Idempotência |
|---|---|---|---|
| `ReservarEstoque` | `registrar_aprovacao_cliente` | Cria linha em `stock_reservations` por peça do `parts` JSON; reduz `available_stock` virtual | Upsert por `(job_sheet_id, variation_id)` |
| `LiberarReservaEstoque` | `registrar_rejeicao_cliente` / `cancelar_os` | Soft-delete `stock_reservations` da OS | Idempotente — múltiplos calls não-op |
| `ConsumirEstoque` | `concluir_execucao` | Converte reserva → baixa real (`Modules/Stock/Services/ConsumeReservation::handle`) | Lock advisory por `job_sheet_id` |
| `NotificarOrcamentoWhatsapp` | `enviar_orcamento` (action sem transição) | Dispatcha job `SendRepairQuoteJob` (Whatsapp daemon CT 100) | Hash do payload — repetir mesmo orçamento não envia 2x |
| `NotificarClienteWhatsapp` | `concluir_execucao` | Reaproveita `NotifyRepairCustomer` Listener existente (ADR Repair tech/0001) — passa a ser disparado pelo Service em vez do controller | Status name = `concluido_aguardando_retirada` na matriz auto-SMS |
| `EmitirCupomServico` | `entregar_ao_cliente` | Opcional — gera Transaction tipo `repair_invoice` se `parts.length > 0` | Lookup por `job_sheet_id` |

---

## §4 Plano de migração (fases) sem big-bang

Cada fase = **1 US separada**. Nenhuma fase é committada sem Wagner aprovar PR anterior.

### Fase A — Seed processo canônico (US-REP-FSM-001)

- Cria `database/seeders/FsmProcessoRepairOSReparoSeeder.php` seguindo o pattern de `FsmProcessoVendaComProducaoSeeder.php`
- Roda em **biz=1 (Wagner WR2)** APENAS — biz=4 ROTA LIVRE confirmou que **não usa OS Repair** (loja de roupa); biz=99 (cross-tenant test fixture)
- Cria stages, actions, action_roles, e roles Spatie `repair.*`
- Pest test idempotência (rodar 2x não duplica)

### Fase B — Schema additivo em `repair_job_sheets` (US-REP-FSM-002)

- Migration `2026_NN_NN_add_current_stage_id_to_repair_job_sheets.php`
  - Adiciona `current_stage_id` (nullable, indexado) — convive com `status_id` legacy
  - Adiciona `parent_job_sheet_id` (nullable) pra suportar `acionar_garantia`
- Migration `2026_NN_NN_add_business_repair_fsm_enabled_flag.php`
  - Adiciona `repair_fsm_enabled` (boolean default false) em `business` — opt-in per-business

### Fase C — Trait + observer em `JobSheet` (US-REP-FSM-003)

- Adiciona `use \App\Domain\Fsm\Concerns\GuardsFsmTransitions;` em `Modules/Repair/Entities/JobSheet.php`
- Adiciona `JobSheetFsmObserver` mirror de `TransactionFsmObserver` (loga `current_stage_id` changes)
- Adiciona casts: `'current_stage_id' => 'integer'`
- Adiciona relação `currentStage()` → `SaleProcessStage` (reaproveita tabela canônica — não duplicar)
- **Não removes** `status_id` legacy nesta fase — coexistência

### Fase D — `RepairFsmActionController` (US-REP-FSM-004)

- Cria `Modules/Repair/Http/Controllers/RepairFsmActionController.php` espelhando `App\Http\Controllers\SaleFsmActionController` (~270 linhas)
- 3 endpoints: `actions(int $id)`, `execute(Request, int $id)`, `startPipeline(Request, int $id)`
- Mapping inicial de stages legacy → FSM:
  - `is_completed_status=1` → `entregue_completo`
  - default → `recebido_para_diagnostico`
- Rotas em `Modules/Repair/Routes/web.php` ou nova `Modules/Repair/Routes/api.php`
- Pest 4 testes mínimos: actions list, execute happy-path, RBAC deny, startPipeline mapping

### Fase E — `<RepairFsmActionPanel>` no drawer (US-REP-FSM-005)

- **Pré-requisito:** tela JobSheet ainda é Blade. Antes desta fase precisa **MWART** prévia (US-REPA-XXX migrar `job_sheet/show` pra Inertia). Sem React, não há `<FsmActionPanel>` reutilizável.
- **Decisão Wagner:** ou (a) priorizar MWART JobSheet antes; ou (b) adicionar action panel via blade partial chamando endpoints JSON (degrade gracioso)
- Reaproveita componente shared `resources/js/Pages/Sells/_components/FsmActionPanel.tsx` (mover pra `resources/js/Components/Fsm/FsmActionPanel.tsx` shared)

### Fase F — Bulk-start command (US-REP-FSM-006)

- Cria `php artisan repair:fsm:bulk-start --business=N --dry-run`
- Mapeia todas OS abertas (status_id != completed) pro stage FSM apropriado conforme `RepairStatus.name` (heurística por nome/sms_template/is_completed)
- Reporta CSV: `job_sheet_id, old_status_name, new_stage_key, would_change`
- Sem `--dry-run` aplica via `FsmAuthorizationFlag::mark()` + save (sem disparar side-effects — só state)

### Fase G — Canary biz=1 7d → expansão (US-REP-FSM-007)

- Liga `repair_fsm_enabled=true` em biz=1
- Smoke 7d real: monitor `mcp_audit_log` por unauthorized exceptions, count transições por dia, taxa-de-Whatsapp-enviado
- **ROTA LIVRE biz=4 fica DESLIGADA — não usa Repair, sem necessidade de migrar**
- Próximos businesses: candidatos OfficeImpresso (Vargas, Extreme, Gold) que usam Repair legacy desktop migrando pra Laravel — opt-in via Wagner

---

## §5 Riscos identificados

### R1 (CRÍTICO) — `repair_statuses` dinâmica per-business com nomenclatura customizada

Cada cliente legacy criou status à mão na UI Settings. Não há mapping 1-pra-1 garantido entre `RepairStatus.name` ("Esperando OK do cliente") e stage canônico (`diagnosticado_aguardando_aprovacao`). Bulk-start (Fase F) precisa heurística + **revisão humana CSV antes do apply**.

**Mitigação:** opt-in flag `business.repair_fsm_enabled` + comando `--dry-run` obrigatório. Coexistência permite rollback per-business.

### R2 (MÉDIO) — Backfill de OS abertas

Businesses com >5k OS abertas (improvável em piloto biz=1, possível em OfficeImpresso futuros) terão custo de backfill. Sem audit-log granular por OS = perde histórico de transições legacy.

**Mitigação:** bulk-start cria entrada `SaleStageHistory` única "pipeline iniciado em FSM (migrado de legacy)" — sem reconstruir histórico que não existe.

### R3 (BAIXO) — Listener `NotifyRepairCustomer` já produtivo

O Listener existente compara `$event->newStatus` (string, ex: `'ready'`) com matriz hardcoded. Quando FSM virar canônico, `newStatus` passa a vir do `SaleProcessStage.key` (`concluido_aguardando_retirada`). Sem ajustar Listener, Whatsapp para de disparar.

**Mitigação:** Fase D inclui ajustar Listener pra aceitar AMBOS formatos (`switch($newStatus) { case 'ready': case 'concluido_aguardando_retirada': ... }`) com Pest cobrindo ambos.

### R4 (BAIXO) — Doc desatualizada `RepairStatusChanged.php`

Comentário diz "dispatch a fazer" mas dispatch já existe no controller. Pode confundir devs novos.

**Mitigação:** Fase C atualiza docblock.

### R5 (MÉDIO — não documentado em ADR 0129) — Compatibilidade do `App\Domain\Fsm\Models\SaleProcess` com Repair

Modelos canônicos têm prefixo `Sale*` (`SaleProcess`, `SaleProcessStage`, `SaleStageAction`). Pra Repair adotar SEM duplicar tabelas, precisa decidir:

- **Opção A (recomendada):** renomear tabelas/models pra `FsmProcess`, `FsmProcessStage`, `FsmStageAction` (cross-domain). Custo: migration rename + atualizar Sells. Pequena.
- **Opção B:** Repair usa tabelas `Sale*` com `entity_type='job_sheet'` discriminator. Aceita mas semanticamente ruim ("sale_processes" pra OS Repair é confuso).
- **Opção C:** Duplicar (`RepairProcess`, etc) — viola DRY, rejeitada.

**Mitigação:** ADR específica antes da Fase A. Recomenda Opção A; impacto mínimo em Sells (rename migration + grep/replace ~20 arquivos).

---

## §6 Recomendação Wagner

### Estratégia opt-in per-business

Adicionar `business.repair_fsm_enabled` (boolean default `false`). Cada business escolhe migrar quando quiser. Código convive:

```php
// Modules/Repair/Http/Controllers/JobSheetController::update()
if ($business->repair_fsm_enabled) {
    // Roteamento FSM — usa ExecuteStageActionService
} else {
    // Caminho legacy — UPDATE direto em status_id (preservado)
}
```

Vantagens:
- ✅ Zero risco de quebrar OfficeImpresso legacy migration em andamento
- ✅ Permite testar canary biz=1 (Wagner WR2 internal) sem afetar clientes pagantes
- ✅ Rollback trivial (flipa flag)
- ✅ Não força fase F (bulk-start) — businesses novos nascem opt-in true; legacy continuam false até cliente pedir

### Bloqueador antes de iniciar

⚠️ **Resolver R5 primeiro** — ADR de extensão decidindo Opção A/B/C pra naming das tabelas FSM. Sem isso, Fase A não pode rodar.

### NÃO recomendado

- ❌ Big-bang migration de todos businesses
- ❌ Remover `status_id` legacy antes de **6 meses** de coexistência verificada
- ❌ Tocar ROTA LIVRE (biz=4) — não usa Repair, sem ROI

---

## §7 US propostas (lista — NÃO criar via MCP sem aprovação)

> Estimates recalibrados ADR 0106 (fator 10x IA-pair + margem 2x). Tarefas humano-limitadas (canary, smoke) mantém relógio real.

| ID | Título | Estimate | Tipo | Bloqueador |
|---|---|---|---|---|
| US-REP-FSM-001 | Seed processo "OS Reparo Padrão" + roles `repair.*` (biz=1) | 1.5h | codável | R5 resolvido |
| US-REP-FSM-002 | Schema additivo: `current_stage_id` + `parent_job_sheet_id` + `repair_fsm_enabled` | 1h | codável | nenhum |
| US-REP-FSM-003 | Trait `GuardsFsmTransitions` + `JobSheetFsmObserver` em JobSheet | 1.5h | codável | 002 |
| US-REP-FSM-004 | `RepairFsmActionController` + 3 endpoints + Pest 4 testes | 2h | codável | 003 |
| US-REP-FSM-005 | `<FsmActionPanel>` shared + integração drawer Repair (pode adiar se Blade) | 2.5h | codável | MWART JobSheet |
| US-REP-FSM-006 | Comando `repair:fsm:bulk-start --dry-run` + relatório CSV | 1.5h | codável | 004 |
| US-REP-FSM-007 | Canary biz=1 7d + monitor 30d + smoke real OS criação→entrega | 7d wall-clock | humano | 006 |

**Total codável:** ~10h IA-pair (vs ~100h pré-ADR 0106).
**Total humano:** 7d canary + 30d monitor (relógio real, não acelerável).

---

## §8 Decisão pendente Wagner

Antes de qualquer US ser criada via MCP `tasks-create`:

1. ✅ Aprovar §5 R5 — opção A/B/C pra naming tabelas FSM
2. ✅ Confirmar §4 Fase E — MWART JobSheet vai antes OU action panel via Blade partial?
3. ✅ Confirmar §6 — estratégia opt-in `repair_fsm_enabled` aprovada?
4. ✅ Aprovar criação das 7 US propostas no MCP (com estimates recalibrados)

---

**Próximo passo:** Wagner revisa, comenta em PR/sessão. Se aprovado, criar 7 US via `mcp__oimpresso__tasks-create` com refs `SPRINT-? PASSO ?` apropriado.
