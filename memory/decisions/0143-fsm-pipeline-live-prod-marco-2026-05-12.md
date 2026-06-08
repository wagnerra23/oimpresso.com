---
slug: 0143-fsm-pipeline-live-prod-marco-2026-05-12
number: 143
title: "FSM Pipeline Canônico LIVE em prod biz=1 — marco 2026-05-12 (40+ PRs em ~10h)"
type: adr
status: accepted
authority: canonical
lifecycle: canon
decided_by: [W]
decided_at: 2026-05-12
module: Sells
tags: [marco, fsm, pipeline-canon, live-prod, sells, repair, nfse, refund, lgpd, paralelizacao-agents, audit-trail, multi-tenant]
supersedes: []
supersedes_partially: []
amends: [0129]
superseded_by: []
related: [0129, 0093, 0094, 0104, 0107, 0136, 0117, 0093, 0094, 0106]
pii: false
review_triggers:
  - "Próximo cliente além de WR2/ROTA LIVRE ativar o pipeline FSM completo — revisar mapping status legacy → stage"
  - "Receita Federal mudar evento 110111 NFe cancelamento (Reforma Tributária IBS/CBS 2027) → revisar cstat 135/136 success codes"
  - "Asaas API v3 mudar endpoint /payments/{id}/refund → atualizar AsaasDriver"
  - "Inter PJ liberar endpoint refund nativo (hoje stub manual) → ativar RefundCobrancaInterJob real"
  - "Cancelamento em cascade falhar >5% em prod biz=4 → revisar idempotência ou design transacional"
---

# ADR 0143 — FSM Pipeline Canônico LIVE em prod biz=1 — marco 2026-05-12

## Status

**Aceito 2026-05-12.** Wagner aprovou via screenshots da UI funcionando + smoke prod verde (40+ PRs mergeados em ~10h, todos 13/13 classes/methods/migrations check OK em biz=1).

Esta ADR documenta o **marco** entre a fundação descrita em [ADR 0129](0129-state-machine-canonica-fsm-rbac.md) (FSM tabular custom) e o estado **LIVE em produção** com pipeline canônico operacional Orçamento → Produção → Venda → Faturamento + cancelamento em cascade + audit trail + UI + RBAC granular per-business.

## Contexto

### Problema observado (gatilho)

Wagner reportou em 2026-05-12 (~07h BRT) 3 pain points operacionais ROTA LIVRE (biz=4, 99% volume oimpresso):

1. *"cancelam nota perdem número pula sequencial"* — bug fiscal grave: NFe cancelada via SEFAZ sofria `forceDelete()` em prod, `proximoNumeroLocked()` pulava sequencial → infração [CONFAZ Ajuste SINIEF 07/2005 Art. 14](https://www.confaz.fazenda.gov.br/legislacao/ajustes/2005/ajuste-007-05) sujeita a multa.
2. *"orçamento foi para estágio voltou sem ninguém ter autorizado"* — bypass RBAC: actions FSM sem role configurada liberavam pra qualquer user (silent bypass).
3. *"produção iniciada sem pessoas ter autorizado"* — pipeline canônico não incluía stages produção (apenas Sem Nota / Com Nota Manual / Com Nota Automática) → clientes produtivos (OficinaAuto/ComunicacaoVisual/Vestuario) caíam em gambiarra.

### Razão estratégica Wagner

> *"se eu fizer sem essa etapa vai ser retrabalho depois quando tiver mais clientes ativos, vai ser pior na minha opinião agora eu posso dizer que foi eles que lançaram errado e não fica feito. Prefiro resolver antes"*

Janela de oportunidade: ROTA LIVRE 99% volume hoje + 6 OfficeImpresso saudáveis em pipeline pré-vendas (Vargas, Extreme, Gold, Zoom, Fixar, Produart) — fundação canônica feita AGORA evita bola de neve com mais clientes.

## Decisão

**FSM Pipeline Canônico passa a ser o caminho ÚNICO de fluxo de vendas no oimpresso**, com aplicação OPT-IN per-venda (`transactions.current_stage_id`) e per-OS (`repair_job_sheets.current_stage_id`).

Componentes deployados em prod (lista completa em §4 abaixo):

### Em prod biz=1 desde 2026-05-12 14:13Z

| Componente | Localização | PR |
|---|---|---|
| **5 tabelas FSM** | `sale_processes`, `sale_process_stages`, `sale_stage_actions`, `sale_stage_action_roles`, `sale_stage_history` | US-SELL-011 PR #501 |
| **`ExecuteStageActionService`** | `app/Domain/Fsm/Services/` | US-SELL-011 PR #501 |
| **`StageActionPolicy`** | `app/Domain/Fsm/Policies/` | US-SELL-011 PR #501 |
| **Trait `GuardsFsmTransitions`** + `FsmAuthorizationFlag` singleton | `app/Domain/Fsm/Concerns/` + `app/Domain/Fsm/Support/` | US-SELL-032 PR #617 + hotfix #640 |
| **Processo seed "Venda Com Produção"** (11 stages × 21 actions × 10 roles per-business) | `database/seeders/FsmProcessoVendaComProducaoSeeder.php` | US-SELL-033 PR #621 |
| **Processo seed "OS Reparo Padrão"** (13 stages × ~15 actions × 6 roles per-business) | `database/seeders/FsmProcessoOsReparoPadraoSeeder.php` | PR #650 |
| **`current_stage_id` coluna** | `transactions` + `repair_job_sheets` | PRs #637 + #650 |
| **`SaleFsmActionController`** + **`RepairFsmActionController`** | `app/Http/Controllers/` + `Modules/Repair/Http/Controllers/` | PRs #637 + #650 |
| **UI drawer SaleSheet**: seção "Pipeline FSM" + seção "Histórico" timeline | `resources/js/Pages/Sells/_components/FsmActionPanel.tsx` + `SaleTimeline.tsx` | PRs #638 + #623 |
| **Botão "Iniciar pipeline FSM"** pra vendas legadas | `FsmActionPanel.tsx` empty state | PR #642 |
| **Comando artisan `fsm:bulk-start-pipeline`** | `app/Console/Commands/` | PR #646 |
| **Comando artisan `fsm:scan-drift`** daily 03:00 BRT | `app/Console/Commands/` | PR #629 |
| **`InitialStageResolver` service** (DRY mapping legacy → stage) | `app/Domain/Fsm/Services/` | PR #654 |
| **`CancelarVendaCascade` side-effect** orquestrador | `app/Domain/Fsm/SideEffects/` | PR #622 |
| **`CancelarNfeJob` real** via `NfeService::cancelar` SEFAZ (cstat 135/136) | `Modules/NfeBrasil/Jobs/` + `Services/` | PR #626 |
| **`NfeInutilizacaoService`** (faixa SEFAZ formal) | `Modules/NfeBrasil/Services/` | PR #630 |
| **`EstornarBoletoJob`** routing automático (refund vs cancel por status) | `app/Jobs/` | PRs #628 + #652 |
| **`RefundCobrancaAsaasJob`** (POST /v3/payments/{id}/refund) com flag `ASAAS_REFUND_ENABLED` | `Modules/RecurringBilling/Jobs/` | PR #652 |
| **`RefundCobrancaInterJob`** (stub manual — Inter PJ não tem endpoint nativo) | `app/Jobs/` | PR #652 |
| **`CancelarCobrancaAsaasJob`** (DELETE pending) + **`CancelarCobrancaInterJob`** | `Modules/RecurringBilling/Jobs/` + `app/Jobs/` | PRs #634 + #635 |
| **`NotificarClienteCancelamentoJob`** + template + fallback email | `Modules/Whatsapp/Jobs/` + `Templates/` | PRs #627 + #636 |
| **LGPD consent**: `contacts.whatsapp_consent` + `email_consent` + helpers Contact | `database/migrations/` + `app/Contact.php` | PR #651 |
| **NFSe modelo 56 cancelamento framework** (interface + driver registry + SPEC per-município) | `Modules/NfeBrasil/Contracts/` + `Services/NfseDrivers/` | PR #653 |
| **NFe cancelada NÃO sofre forceDelete** (preserva sequencial fiscal) | `Modules/NfeBrasil/Services/NfeService.php:380` | PR #614 |
| **Actions `is_critical` fail-secure** (role obrigatória quando crítica) | `app/Domain/Fsm/Services/ExecuteStageActionService.php` | PR #615 |
| **Topnav cleanup Sells** (POS + Lista + Orçamentos) | `config/core_topnavs.php` | PR #610 |
| **localStorage persiste última aba** Sells/Index | `resources/js/Pages/Sells/Index.tsx` | PR #610 |
| **Toast UI sonner** substitui `alert()` | `FsmActionPanel.tsx` | PR #645 |

### Pipeline canônico Sells em vigor

```
quote_draft (initial) → quote_sent → quote_approved → in_production →
ready_for_invoice → invoiced → paid → delivered → completed (T)
Laterais: cancelled (T), on_hold

Actions: enviar_orcamento, cliente_aprovou (🔒 ReservarEstoque),
cliente_rejeitou, iniciar_producao (🔒), pausar_producao,
concluir_producao (🔒 ConsumirEstoque), faturar (🔒), emitir_nfe (🔒),
marcar_pago (🔒), entregar, concluir,
cancelar_venda (🔒 CancelarVendaCascade — qualquer não-terminal),
reabrir_para_revisao (🔒 LiberarReserva)
```

### Pipeline canônico Repair em vigor

```
recebido_para_diagnostico (initial) → em_diagnostico →
diagnosticado_aguardando_aprovacao → orcamento_aprovado | orcamento_rejeitado (T) →
aguardando_pecas → pecas_chegadas → em_execucao → pausado →
concluido_aguardando_retirada → entregue_completo (T)
Laterais: cancelado (T), garantia_acionada (T)

Actions críticas (🔒): cliente_aprovou_orcamento (ReservarEstoque),
iniciar_execucao, concluir_execucao (ConsumirEstoque),
cancelar_os (×9 stages, LiberarReserva), registrar_garantia
```

### Coexistência com sistema legacy

Como `current_stage_id` é nullable em ambas as tabelas, vendas/OS legadas **continuam funcionando sem mudança**. Adoção é opt-in:

- **1 venda** via UI: botão "Iniciar pipeline FSM" no drawer SaleSheet
- **Lote**: `php artisan fsm:bulk-start-pipeline {business_id} --limit=500 [--dry-run]`
- **Repair**: idem via `RepairFsmActionController` + bulk-start futuro (US-REP-FSM-006 backlog)

State machine legacy (`transactions.status` + `repair_statuses` dinâmica) preservada — coexistência permite rollout gradual sem big-bang.

## Multi-tenant Tier 0 amarração ([ADR 0093](0093-multi-tenant-isolation-tier-0.md))

- ✅ Todas tabelas FSM (5 base + LGPD consent + cancelamento NFSe) têm `business_id` indexado + FK
- ✅ Models com `HasBusinessScope` global scope
- ✅ Roles Spatie cadastradas per-business com sufixo `#{biz}` (ex: `vendas.gerente#1`, `producao.iniciar#1`)
- ✅ Jobs assíncronos sempre recebem `$businessId` no constructor (nunca `session()`)
- ✅ Endpoints UI scoped por `session('user.business_id')` — 404 silencioso anti-info-leak
- ⛔ `withoutGlobalScopes` permitido APENAS com comentário `// SUPERADMIN: <razão>` (skill `multi-tenant-patterns` Tier A enforce)

## Gateway obrigatório (consequência crítica nova)

**Mudanças em `current_stage_id` (transactions + repair_job_sheets) PASSAM OBRIGATORIAMENTE pelo `ExecuteStageActionService`** via trait `GuardsFsmTransitions` + singleton `FsmAuthorizationFlag`.

UPDATE direto (Eloquent `$tx->current_stage_id = X; $tx->save()` ou tinker) **lança `UnauthorizedActionException`**.

Mass updates Eloquent (`Model::where()->update()`) e raw `DB::table()->update()` **bypassam o trait** (limitação técnica documentada) — detecção offline via `php artisan fsm:scan-drift transactions` (cron daily 03:00 BRT, exit 1 se >=1 drift → alerta CI/email).

Override superadmin existe via `FsmAuthorizationFlag::mark(Class, $id)` explícito + log estruturado WARNING — uso raro + sempre auditável.

## Auditoria + LGPD

- **`sale_stage_history` append-only** — toda transição registra business_id, transaction_id, action_id (nullable pra "Pipeline iniciado"), from_stage_id, to_stage_id, user_id, payload_snapshot (json), executed_at
- **Timeline visível no UI** drawer SaleSheet seção "Histórico" — permission `sale.history.view` obrigatória
- **`fsm:scan-drift` daily 03:00 BRT** detecta orphan (sem history) + mismatch (current ≠ último to_stage)
- **LGPD consent**: `contacts.whatsapp_consent` + `email_consent` + `consent_updated_at` nullable (NULL=permite back-compat; FALSE=bloqueia). Helpers `canReceiveWhatsappNotification()` / `canReceiveEmailNotification()` em Contact model
- **NotificarClienteCancelamentoJob respeita consent** — opt-out WhatsApp tenta email fallback; opt-out ambos → log warning + Event ClienteSemCanal (TODO workflow humano)

## Alternativas avaliadas (pós-implementação)

Tudo conforme [ADR 0129 §Alternativas](0129-state-machine-canonica-fsm-rbac.md#alternativas-avaliadas) — escolha custom 4-5 tabelas confirmada pelo uso real:

- ✅ **Multi-tenant nativo** — sem hacks (Spatie state-machine não tinha)
- ✅ **Parametrização runtime via UI admin futura** — schema preparado
- ✅ **RBAC granular per-business** via Spatie role suffix `#{biz}`
- ✅ **Side-effects isolados** — `App\Domain\Fsm\SideEffects\*` (`ReservarEstoque`, `ConsumirEstoque`, `LiberarReserva`, `CancelarVendaCascade`) — SoC brutal preservado
- ✅ **Audit log nativo** — `sale_stage_history` + LGPD consent embutido
- ✅ **Reusabilidade** — Sells + Repair compartilham mesma fundação (`sale_*` tables são FSM canon, prefixo histórico)

## Consequências

### Positivas (validadas com smoke prod)

1. **Pain points fiscais resolvidos** — NFe cancelada preserva sequencial; inutilização SEFAZ formal disponível; refund cobranças PAGAS automatizado (Asaas) ou manual rastreado (Inter)
2. **Pain points governança resolvidos** — gateway obrigatório via Observer + fail-secure `is_critical` + audit trail completo
3. **Pain points operacionais resolvidos** — pipeline com produção real (Repair também) + botões UI dinâmicos + timeline visível
4. **Plataforma reusável** — Sells (Vendas) + Repair (OS) compartilham FSM canon; Modules/Project + mcp_tasks roadmap pra Fase 4 ADR 0129
5. **Audit trail LGPD/fiscal completo** — sale_stage_history append-only + consent columns + scan-drift cron
6. **Coexistência opt-in** — state machine legacy preservada, rollout gradual sem big-bang (162 vendas biz=1 prontas pra migrar via `fsm:bulk-start-pipeline`)
7. **Pré-requisito Reforma Tributária 2026** — schema suporta múltiplos documentos (NFe55 + NFSe56 + NFCom62 futura) coexistindo

### Negativas / Trade-offs

1. **Mass updates bypass Observer** — `Model::where()->update()` não dispara `updating` event. Mitigação: `fsm:scan-drift` cron + recurring health-check (ADR 0133)
2. **Eloquent dynamic properties viram colunas SQL** — descoberto via hotfix #640: `$model->_fsmFlag = true` interpretava como atributo persistível. Solução canônica: singleton estático `FsmAuthorizationFlag`. Documentado pra qualquer trait similar futuro
3. **`Model::observe()` em boot recursion** — descoberto via hotfix #639: `static::observe(Class)` durante boot do Model que está sendo bootado lança LogicException. Solução: `static::updating(closure)`. Padrão pra qualquer trait FSM futuro
4. **Inter PJ refund manual** — sem endpoint nativo, RefundCobrancaInterJob é stub que loga "ação manual TED/PIX". Mitigação: TODO US-CASCADE-BOLETO-006b quando Inter v3 beta liberar
5. **NFSe per-município** — framework abstrato + driver registry, mas 0 drivers reais implementados (cada padrão exige cert A1 + sandbox município). Mitigação: 10 US backlog em `memory/requisitos/NfeBrasil/SPEC-NFSE-CANCEL.md` ativadas conforme sinal qualificado (ADR 0105)
6. **Eventos `RepairStatusChanged` legacy preservados** — dispatch ativo em `JobSheetController:649`; FSM Repair coexiste sem migrar listeners atuais (`NotifyRepairCustomer` continua produtivo)

## Padrões de paralelização agents validados nesta sessão

Esta sessão paralelizou **3 waves × 4-5 agents general-purpose simultâneos** com sucesso. Padrão refinado:

- **Áreas isoladas zero overlap** entre agents (cada um toca subset disjunto de arquivos)
- **Agents NÃO fazem git ops** (commit/push/branch) — parent coordena consolidação
- **Consolidação via stash + branch + add seletivo + stash --keep-index** — funciona MAS frágil: arquivos podem ser perdidos no swap se `git stash pop` traz mudanças de outras sessões (aconteceu 2x nesta sessão — recuperação via reimplementação manual com base no reporte do agent)
- **Sumário agent ≤300 palavras** — paths + linhas + decisões + TODOs, sem código completo
- **Smoke prod via SSH + tinker após merge** — detecção rápida de bugs prod (4 hotfixes descobertos em <10min cada)

Detalhes anti-pattern recoverable em [auto-mem `feedback_paralelizacao_5agents_validado.md`](../../auto-mem-pending pra criar).

## Plano de adoção restante (próximas sessões)

### Imediato (sem dependência de Wagner)

- Migrar 162 vendas legadas biz=1 via `fsm:bulk-start-pipeline 1` (dry-run primeiro)
- Wagner valida transição em prod via UI (clicar "Entregar ao cliente" → ver stage mudar + history)

### Médio prazo (US separadas, depende sinal)

- ✅ **US-REP-FSM-005**: Frontend FsmActionPanel reuso pra Repair drawer
- ✅ **US-REP-FSM-006**: `fsm:bulk-start-pipeline-repair` (idem Sells pra OS)
- ✅ **US-LGPD-001**: UI admin privacidade (Contacts/Edit) pra Larissa marcar consent
- ✅ **US-NFSE-CANCEL-002..008**: 8 drivers per-padrão municipal (ABRASF v1/v2.04 SOAP, GINFES, IPM, Tiplan, nfse.gov.br/sefin, PMSP, BHISS)
- ✅ **US-CASCADE-BOLETO-006b**: integração Inter v3 beta cobrança cancelamento (substituir stub manual)
- ✅ **ADR rename `Sale*` → `Fsm*`** tabelas (R5 médio bloqueador do SPEC Repair) — exige nova ADR

### Longo prazo (Fase 4 ADR 0129)

- Migração `Modules/ProjectMgmt` + `mcp_tasks` pro padrão canônico (deprioridade conforme ADR 0129)

## Refs

- **ADR mãe**: [0129](0129-state-machine-canonica-fsm-rbac.md) (FSM tabular custom)
- **CASOS-USO**: [memory/requisitos/Sells/CASOS-USO-PIPELINE-VENDAS.md](../requisitos/Sells/CASOS-USO-PIPELINE-VENDAS.md) (7 casos Given/When/Then)
- **SPEC Repair FSM**: [memory/requisitos/Repair/SPEC-FSM-WIREUP.md](../requisitos/Repair/SPEC-FSM-WIREUP.md) (7 fases A-G)
- **SPEC NFSe Cancel**: [memory/requisitos/NfeBrasil/SPEC-NFSE-CANCEL.md](../requisitos/NfeBrasil/SPEC-NFSE-CANCEL.md) (10 US per-padrão)
- **Session log**: `memory/sessions/2026-05-12-fsm-pipeline-canon-live-prod-50prs.md`
- **Handoff**: `memory/handoffs/2026-05-12-1410-fsm-pipeline-canon-live-50prs.md`

### ADRs relacionadas

- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 (amarração obrigatória)
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (§5 SoC, §6 Tier 0)
- [ADR 0104](0104-processo-mwart-canonico-unico-caminho.md) — Processo MWART (Sells/Create migrado)
- [ADR 0106](0106-recalibracao-velocidade-fator-10x-ia-pair.md) — Estimates IA-pair fator 10x
- [ADR 0107](0107-emendation-0104-visual-comparison-gate-f3.md) — Visual comparison gate
- [ADR 0117](0117-multiplos-numeros-whatsapp-por-business.md) — Múltiplos números Whatsapp
- [ADR 0136](0136-sells-grade-avancada-modo-toggle.md) — Sells Grade Avançada

### Base legal

- [CONFAZ Ajuste SINIEF 07/2005 Art. 14](https://www.confaz.fazenda.gov.br/legislacao/ajustes/2005/ajuste-007-05) — sequencial NFe controle fiscal
- LGPD Lei 13.709/2018 — consent opt-in (Pilar 5 oimpresso Insights)
- Portaria MTP 671/2021 Anexo I — append-only (pattern aplicado em sale_stage_history)

## Aprovação

Aprovado por Wagner [W] em sessão 2026-05-12 (~14:30 BRT) via:

1. ✅ Screenshots da UI funcionando em prod biz=1 (OS00129):
   - Topnav 3 itens
   - Drawer "Pipeline FSM" com stage `[Paga]` + botões `Cancelar venda` + `Entregar ao cliente`
   - Timeline "Histórico" com 6 transições + badges coloridos canônicos
2. ✅ Smoke prod CLI: 8/8 classes/methods/migrations check OK
3. ✅ \"Ficou ótimo pode continuar\" — Wagner literalmente após ver UI
4. ✅ Status `accepted` aplicado direto (ADR 0040 policy publicação — Wagner é dono + aprovador final)

Mudanças incrementais via novas ADRs com `supersedes_partially`/`amends` (não editar este ADR).

---

**Última atualização:** 2026-05-12 — versão inicial accepted após marco LIVE prod biz=1.
